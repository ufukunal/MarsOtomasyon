using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Application.Sales;
using Mars.Domain.Inventory;
using Mars.Domain.Sales;
using Mars.Infrastructure.Persistence.Inventory;
using Mars.Infrastructure.Persistence.Products;
using Microsoft.EntityFrameworkCore;

namespace Mars.Infrastructure.Persistence.Sales;

public sealed partial class EfSalesPersistence
{
    public Task<Result<SalesMutationReceipt>> CreateQuoteAsync(
        CreateQuoteCommand command, IExecutionContext context, CancellationToken ct) =>
        MutateAsync("sales.quote.create", command.OperationKey, "QuoteCreated", "Quote",
            "sales.quote.create.completed", context,
            async innerCt =>
            {
                var invalid = ValidateDocument(command.Number, command.CurrencyCode, command.DocumentDiscountPercent, command.Lines);
                if (invalid is not null) return Result<SalesMutationReceipt>.Failure(invalid);
                var customer = await ResolveCustomerAsync(context.CompanyId, command.CustomerPartyPublicId, innerCt);
                if (customer is null)
                    return Business<SalesMutationReceipt>("sales.customer.not_eligible", "Customer must be ACTIVE with an ACTIVE Customer role.");

                var resolved = await ResolveLinesAsync(context.CompanyId, command.Lines, innerCt);
                if (resolved.IsFailure) return Result<SalesMutationReceipt>.Failure(resolved.Error!);

                var now=DateTimeOffset.UtcNow;
                var quote=new QuoteRecord {
                    PublicId=Guid.NewGuid(), CompanyId=context.CompanyId, Number=command.Number.Trim(),
                    CustomerPartyId=customer.Id, CustomerCodeSnapshot=customer.Code, CustomerNameSnapshot=customer.LegalName,
                    CurrencyCode=command.CurrencyCode.Trim().ToUpperInvariant(),
                    PaymentTerms=Normalize(command.PaymentTerms), State=QuoteState.Draft, CurrentRevisionNumber=1,
                    Version=1, CreatorActorId=context.ActorId, CreatedAt=now
                };
                dbContext.Add(quote);
                await dbContext.SaveChangesAsync(innerCt);
                var revision=new QuoteRevisionRecord {
                    PublicId=Guid.NewGuid(), QuoteId=quote.Id, CompanyId=context.CompanyId, RevisionNumber=1,
                    DocumentDiscountPercent=command.DocumentDiscountPercent, CreatorActorId=context.ActorId, CreatedAt=now
                };
                dbContext.Add(revision);
                await dbContext.SaveChangesAsync(innerCt);
                AddQuoteLines(revision.Id, context.CompanyId, command.Lines, resolved.Value!);
                return Result<SalesMutationReceipt>.Success(
                    new SalesMutationReceipt(quote.PublicId,"DRAFT",quote.Version,context.CorrelationId.Value));
            },ct);

    public Task<Result<SalesMutationReceipt>> ReviseQuoteAsync(
        ReviseQuoteCommand command,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("sales.quote.revise",command.OperationKey,"QuoteRevised","Quote",
            "sales.quote.revise.completed",context,
            async innerCt =>
            {
                var quote=await LockQuoteAsync(command.QuotePublicId,context.CompanyId,innerCt);
                if(quote is null) return NotFound<SalesMutationReceipt>("sales.quote.not_found","Quote was not found.");
                if(quote.Version!=command.ExpectedVersion) return Conflict<SalesMutationReceipt>("sales.quote.stale","Quote version is stale.",true);
                if(quote.State is QuoteState.Cancelled or QuoteState.Expired or QuoteState.Converted)
                    return Business<SalesMutationReceipt>("sales.quote.state","Quote cannot be revised in its current state.");
                if(command.Lines.Count==0 || command.DocumentDiscountPercent<0m || command.DocumentDiscountPercent>100m)
                    return Validation<SalesMutationReceipt>("sales.quote.revision.invalid","Revision lines and document discount are invalid.");

                var resolved=await ResolveLinesAsync(context.CompanyId,command.Lines,innerCt);
                if(resolved.IsFailure) return Result<SalesMutationReceipt>.Failure(resolved.Error!);

                var previous=await dbContext.Set<QuoteRevisionRecord>()
                    .SingleAsync(x=>x.CompanyId==context.CompanyId&&x.QuoteId==quote.Id&&x.RevisionNumber==quote.CurrentRevisionNumber,innerCt);
                var converted=await dbContext.Set<QuoteConversionLinkRecord>().AsNoTracking()
                    .AnyAsync(x=>x.CompanyId==context.CompanyId&&x.QuoteRevisionId==previous.Id,innerCt);
                if(converted) return Business<SalesMutationReceipt>("sales.quote.revision.converted","A revision with conversion history is immutable.");

                var now=DateTimeOffset.UtcNow;
                previous.AcceptedAt=null;
                quote.CurrentRevisionNumber=checked(quote.CurrentRevisionNumber+1);
                quote.PaymentTerms=Normalize(command.PaymentTerms);
                quote.State=QuoteState.Draft;
                quote.Version=checked(quote.Version+1);
                var revision=new QuoteRevisionRecord {
                    PublicId=Guid.NewGuid(),QuoteId=quote.Id,CompanyId=context.CompanyId,
                    RevisionNumber=quote.CurrentRevisionNumber,DocumentDiscountPercent=command.DocumentDiscountPercent,
                    CreatorActorId=context.ActorId,CreatedAt=now
                };
                dbContext.Add(revision);
                await dbContext.SaveChangesAsync(innerCt);
                AddQuoteLines(revision.Id,context.CompanyId,command.Lines,resolved.Value!);
                return Result<SalesMutationReceipt>.Success(
                    new SalesMutationReceipt(quote.PublicId,"DRAFT",quote.Version,context.CorrelationId.Value));
            },ct);

    public Task<Result<SalesMutationReceipt>> SubmitQuoteApprovalAsync(
        Guid quotePublicId,long expectedVersion,string operationKey,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("sales.quote.submit_approval",operationKey,"QuoteApprovalSubmitted","Quote",
            "sales.quote.submit_approval.completed",context,
            async innerCt =>
            {
                var quote=await LockQuoteAsync(quotePublicId,context.CompanyId,innerCt);
                if(quote is null)return NotFound<SalesMutationReceipt>("sales.quote.not_found","Quote was not found.");
                if(quote.Version!=expectedVersion)return Conflict<SalesMutationReceipt>("sales.quote.stale","Quote version is stale.",true);
                if(quote.State!=QuoteState.Draft)return Business<SalesMutationReceipt>("sales.quote.state","Only DRAFT Quote can be submitted.");
                var revision=await CurrentQuoteRevisionAsync(quote,innerCt);
                revision.SubmittedForApprovalAt=DateTimeOffset.UtcNow;
                quote.State=QuoteState.PendingInternalApproval; quote.Version++;
                return Result<SalesMutationReceipt>.Success(new(quote.PublicId,"PENDING_INTERNAL_APPROVAL",quote.Version,context.CorrelationId.Value));
            },ct);

    public async Task<Result<SalesApprovalTarget>> GetQuoteApprovalTargetAsync(
        Guid quotePublicId,IExecutionContext context,CancellationToken ct)
    {
        var quote=await dbContext.Set<QuoteRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==quotePublicId,ct);
        if(quote is null)return NotFound<SalesApprovalTarget>("sales.quote.not_found","Quote was not found.");
        if(quote.State is not (QuoteState.PendingInternalApproval or QuoteState.CustomerReview))
            return Business<SalesApprovalTarget>(
                "sales.quote.approval.state",
                "Quote approval target is available only after submission and for the approved customer-review snapshot.");
        var revision=await dbContext.Set<QuoteRevisionRecord>().AsNoTracking()
            .SingleAsync(x=>x.CompanyId==context.CompanyId&&x.QuoteId==quote.Id&&x.RevisionNumber==quote.CurrentRevisionNumber,ct);
        return Result<SalesApprovalTarget>.Success(new(revision.PublicId,revision.RevisionNumber,revision.CreatorActorId,"QuoteRevision"));
    }

    public Task<Result<SalesMutationReceipt>> SendQuoteToCustomerAsync(
        Guid quotePublicId,string operationKey,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("sales.quote.send_customer",operationKey,"QuoteSentToCustomer","Quote",
            "sales.quote.send_customer.completed",context,
            async innerCt =>
            {
                var quote=await LockQuoteAsync(quotePublicId,context.CompanyId,innerCt);
                if(quote is null)return NotFound<SalesMutationReceipt>("sales.quote.not_found","Quote was not found.");
                if(quote.State!=QuoteState.PendingInternalApproval&&quote.State!=QuoteState.Draft)
                    return Business<SalesMutationReceipt>("sales.quote.state","Quote is not eligible for customer review.");
                var rev=await CurrentQuoteRevisionAsync(quote,innerCt);
                rev.SentToCustomerAt=DateTimeOffset.UtcNow; quote.State=QuoteState.CustomerReview; quote.Version++;
                return Result<SalesMutationReceipt>.Success(new(quote.PublicId,"CUSTOMER_REVIEW",quote.Version,context.CorrelationId.Value));
            },ct);

    public Task<Result<SalesMutationReceipt>> AcceptQuoteAsync(
        Guid quotePublicId,string operationKey,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("sales.quote.accept",operationKey,"QuoteAccepted","Quote","sales.quote.accept.completed",context,
            async innerCt =>
            {
                var quote=await LockQuoteAsync(quotePublicId,context.CompanyId,innerCt);
                if(quote is null)return NotFound<SalesMutationReceipt>("sales.quote.not_found","Quote was not found.");
                if(quote.CurrencyCode!="TRY")
                    return Business<SalesMutationReceipt>("sales.currency.fx_authority_required","Non-TRY authoritative finalization is unavailable until server-side FX authority exists.");
                if(quote.State!=QuoteState.CustomerReview)
                    return Business<SalesMutationReceipt>("sales.quote.state","Only CUSTOMER_REVIEW Quote can be accepted.");
                var rev=await CurrentQuoteRevisionAsync(quote,innerCt);
                var now=DateTimeOffset.UtcNow; rev.AcceptedAt=now; quote.AcceptedAt=now; quote.State=QuoteState.Accepted; quote.Version++;
                return Result<SalesMutationReceipt>.Success(new(quote.PublicId,"ACCEPTED",quote.Version,context.CorrelationId.Value));
            },ct);

    public Task<Result<SalesMutationReceipt>> CancelQuoteAsync(
        Guid quotePublicId,string reason,string operationKey,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("sales.quote.cancel",operationKey,"QuoteCancelled","Quote","sales.quote.cancel.completed",context,
            async innerCt =>
            {
                if(string.IsNullOrWhiteSpace(reason))return Validation<SalesMutationReceipt>("sales.reason.required","Cancellation reason is required.");
                var quote=await LockQuoteAsync(quotePublicId,context.CompanyId,innerCt);
                if(quote is null)return NotFound<SalesMutationReceipt>("sales.quote.not_found","Quote was not found.");
                if(quote.State is QuoteState.Converted or QuoteState.Cancelled)return Business<SalesMutationReceipt>("sales.quote.state","Quote cannot be cancelled.");
                quote.State=QuoteState.Cancelled;quote.CancelledAt=DateTimeOffset.UtcNow;quote.Version++;
                return Result<SalesMutationReceipt>.Success(new(quote.PublicId,"CANCELLED",quote.Version,context.CorrelationId.Value));
            },ct);

    public Task<Result<SalesMutationReceipt>> ExpireQuoteAsync(
        Guid quotePublicId,string operationKey,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("sales.quote.expire",operationKey,"QuoteExpired","Quote","sales.quote.expire.completed",context,
            async innerCt =>
            {
                var quote=await LockQuoteAsync(quotePublicId,context.CompanyId,innerCt);
                if(quote is null)return NotFound<SalesMutationReceipt>("sales.quote.not_found","Quote was not found.");
                if(quote.State is QuoteState.Converted or QuoteState.Cancelled or QuoteState.Expired)
                    return Business<SalesMutationReceipt>("sales.quote.state","Quote cannot be expired.");
                quote.State=QuoteState.Expired;quote.ExpiredAt=DateTimeOffset.UtcNow;quote.Version++;
                return Result<SalesMutationReceipt>.Success(new(quote.PublicId,"EXPIRED",quote.Version,context.CorrelationId.Value));
            },ct);

    public Task<Result<SalesMutationReceipt>> ConvertQuoteAsync(
        ConvertQuoteCommand command,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("sales.quote.convert_order",command.OperationKey,"QuoteConverted","SalesOrder","sales.quote.convert.completed",context,
            async innerCt =>
            {
                if(string.IsNullOrWhiteSpace(command.OrderNumber)||command.Lines.Count==0)
                    return Validation<SalesMutationReceipt>("sales.quote.convert.invalid","Order number and at least one conversion line are required.");
                var quote=await LockQuoteAsync(command.QuotePublicId,context.CompanyId,innerCt);
                if(quote is null)return NotFound<SalesMutationReceipt>("sales.quote.not_found","Quote was not found.");
                if(quote.State is not (QuoteState.Accepted or QuoteState.PartiallyConverted))
                    return Business<SalesMutationReceipt>("sales.quote.state","Only accepted Quote can be converted.");
                if(command.QuoteRevisionNumber!=quote.CurrentRevisionNumber)
                    return Conflict<SalesMutationReceipt>("sales.quote.revision.stale","Exact accepted Quote revision is required.",true);
                var rev=await CurrentQuoteRevisionAsync(quote,innerCt);
                if(rev.AcceptedAt is null)return Business<SalesMutationReceipt>("sales.quote.acceptance.required","Accepted revision is required.");

                var sourceLines=await dbContext.Set<QuoteLineRecord>()
                    .Where(x=>x.CompanyId==context.CompanyId&&x.RevisionId==rev.Id).ToArrayAsync(innerCt);
                var selections=command.Lines.GroupBy(x=>x.QuoteLinePublicId)
                    .ToDictionary(g=>g.Key,g=>g.Sum(x=>x.Quantity));
                if(selections.Any(x=>x.Value<=0m)||selections.Keys.Any(id=>sourceLines.All(x=>x.PublicId!=id)))
                    return Validation<SalesMutationReceipt>("sales.quote.convert.lines","Conversion lines are invalid.");

                foreach(var source in sourceLines.Where(x=>selections.ContainsKey(x.PublicId)))
                {
                    var already=await dbContext.Set<QuoteConversionLinkRecord>().AsNoTracking()
                        .Where(x=>x.CompanyId==context.CompanyId&&x.QuoteRevisionId==rev.Id&&x.QuoteLinePublicId==source.PublicId)
                        .SumAsync(x=>(decimal?)x.Quantity,innerCt)??0m;
                    if(already+selections[source.PublicId]>source.Quantity)
                        return Business<SalesMutationReceipt>("sales.quote.convert.cap","Cumulative converted quantity exceeds Quote source quantity.");
                }

                var now=DateTimeOffset.UtcNow;
                var order=new SalesOrderRecord {
                    PublicId=Guid.NewGuid(),CompanyId=context.CompanyId,Number=command.OrderNumber.Trim(),
                    CustomerPartyId=quote.CustomerPartyId,CustomerCodeSnapshot=quote.CustomerCodeSnapshot,
                    CustomerNameSnapshot=quote.CustomerNameSnapshot,CurrencyCode=quote.CurrencyCode,PaymentTerms=quote.PaymentTerms,
                    State=SalesOrderState.Draft,CurrentVersionNumber=1,ApprovalInheritedFromAcceptedQuote=true,
                    CreatorActorId=context.ActorId,Version=1,CreatedAt=now
                };
                dbContext.Add(order);await dbContext.SaveChangesAsync(innerCt);
                var ov=new SalesOrderVersionRecord {
                    PublicId=Guid.NewGuid(),SalesOrderId=order.Id,CompanyId=context.CompanyId,VersionNumber=1,
                    SourceQuoteRevisionId=rev.Id,DocumentDiscountPercent=rev.DocumentDiscountPercent,
                    CreatorActorId=context.ActorId,CreatedAt=now
                };
                dbContext.Add(ov);await dbContext.SaveChangesAsync(innerCt);

                foreach(var source in sourceLines.Where(x=>selections.ContainsKey(x.PublicId)).OrderBy(x=>x.Sequence))
                {
                    var lineId=Guid.NewGuid();
                    dbContext.Add(ToOrderLine(ov.Id,context.CompanyId,lineId,source,selections[source.PublicId]));
                    dbContext.Add(new QuoteConversionLinkRecord {
                        PublicId=Guid.NewGuid(),CompanyId=context.CompanyId,QuoteRevisionId=rev.Id,
                        QuoteLinePublicId=source.PublicId,SalesOrderId=order.Id,SalesOrderVersionNumber=1,
                        SalesOrderLinePublicId=lineId,Quantity=selections[source.PublicId],ActorId=context.ActorId,CreatedAt=now
                    });
                }

                var allConverted=true;
                foreach(var source in sourceLines)
                {
                    var already=await dbContext.Set<QuoteConversionLinkRecord>().AsNoTracking()
                        .Where(x=>x.CompanyId==context.CompanyId&&x.QuoteRevisionId==rev.Id&&x.QuoteLinePublicId==source.PublicId)
                        .SumAsync(x=>(decimal?)x.Quantity,innerCt)??0m;
                    if(already+selections.GetValueOrDefault(source.PublicId)<source.Quantity){allConverted=false;break;}
                }
                quote.State=allConverted?QuoteState.Converted:QuoteState.PartiallyConverted;quote.Version++;
                return Result<SalesMutationReceipt>.Success(new(order.PublicId,"DRAFT",order.Version,context.CorrelationId.Value));
            },ct);

    public Task<Result<SalesMutationReceipt>> CreateDirectOrderAsync(
        CreateDirectOrderCommand command,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("sales.order.create",command.OperationKey,"SalesOrderCreated","SalesOrder","sales.order.create.completed",context,
            async innerCt =>
            {
                var invalid=ValidateDocument(command.Number,command.CurrencyCode,command.DocumentDiscountPercent,command.Lines);
                if(invalid is not null)return Result<SalesMutationReceipt>.Failure(invalid);
                var customer=await ResolveCustomerAsync(context.CompanyId,command.CustomerPartyPublicId,innerCt);
                if(customer is null)return Business<SalesMutationReceipt>("sales.customer.not_eligible","Customer must be ACTIVE with an ACTIVE Customer role.");
                var resolved=await ResolveLinesAsync(context.CompanyId,command.Lines,innerCt);
                if(resolved.IsFailure)return Result<SalesMutationReceipt>.Failure(resolved.Error!);
                var now=DateTimeOffset.UtcNow;
                var order=new SalesOrderRecord {
                    PublicId=Guid.NewGuid(),CompanyId=context.CompanyId,Number=command.Number.Trim(),CustomerPartyId=customer.Id,
                    CustomerCodeSnapshot=customer.Code,CustomerNameSnapshot=customer.LegalName,
                    CurrencyCode=command.CurrencyCode.Trim().ToUpperInvariant(),PaymentTerms=Normalize(command.PaymentTerms),
                    State=SalesOrderState.Draft,CurrentVersionNumber=1,ApprovalInheritedFromAcceptedQuote=false,
                    CreatorActorId=context.ActorId,Version=1,CreatedAt=now
                };
                dbContext.Add(order);await dbContext.SaveChangesAsync(innerCt);
                var version=new SalesOrderVersionRecord {
                    PublicId=Guid.NewGuid(),SalesOrderId=order.Id,CompanyId=context.CompanyId,VersionNumber=1,
                    DocumentDiscountPercent=command.DocumentDiscountPercent,CreatorActorId=context.ActorId,CreatedAt=now
                };
                dbContext.Add(version);await dbContext.SaveChangesAsync(innerCt);
                AddOrderLines(version.Id,context.CompanyId,command.Lines,resolved.Value!);
                return Result<SalesMutationReceipt>.Success(new(order.PublicId,"DRAFT",order.Version,context.CorrelationId.Value));
            },ct);

    public Task<Result<SalesMutationReceipt>> SubmitOrderApprovalAsync(
        Guid id,long version,string key,IExecutionContext context,CancellationToken ct) =>
        ChangeOrderState(id,version,key,context,SalesOrderState.Draft,SalesOrderState.PendingApproval,
            "sales.order.submit_approval","SalesOrderApprovalSubmitted","PENDING_APPROVAL",ct);

    public async Task<Result<SalesApprovalTarget>> GetOrderApprovalTargetAsync(
        Guid id,IExecutionContext context,CancellationToken ct)
    {
        var order=await dbContext.Set<SalesOrderRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==id,ct);
        if(order is null)return NotFound<SalesApprovalTarget>("sales.order.not_found","Sales Order was not found.");
        if(!order.ApprovalInheritedFromAcceptedQuote && order.State!=SalesOrderState.PendingApproval)
            return Business<SalesApprovalTarget>(
                "sales.order.approval.state",
                "Direct or commercially changed Sales Order must be submitted before approval or confirmation.");
        var type=order.ApprovalInheritedFromAcceptedQuote?"SalesOrderInheritedApproval":"SalesOrder";
        return Result<SalesApprovalTarget>.Success(new(order.PublicId,order.CurrentVersionNumber,order.CreatorActorId,type));
    }

    public Task<Result<SalesMutationReceipt>> ConfirmOrderAsync(
        Guid id,long version,string key,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("sales.order.confirm",key,"SalesOrderConfirmed","SalesOrder","sales.order.confirm.completed",context,
            async innerCt =>
            {
                var order=await LockOrderAsync(id,context.CompanyId,innerCt);
                if(order is null)return NotFound<SalesMutationReceipt>("sales.order.not_found","Sales Order was not found.");
                if(order.Version!=version)return Conflict<SalesMutationReceipt>("sales.order.stale","Sales Order version is stale.",true);
                if(order.CurrencyCode!="TRY")return Business<SalesMutationReceipt>("sales.currency.fx_authority_required","Non-TRY authoritative finalization is unavailable until server-side FX authority exists.");
                if(order.State is not (SalesOrderState.Draft or SalesOrderState.PendingApproval))
                    return Business<SalesMutationReceipt>("sales.order.state","Sales Order is not confirmable.");
                order.State=SalesOrderState.Confirmed;order.ConfirmedAt=DateTimeOffset.UtcNow;order.Version++;
                return Result<SalesMutationReceipt>.Success(new(order.PublicId,"CONFIRMED",order.Version,context.CorrelationId.Value));
            },ct);

    public Task<Result<SalesMutationReceipt>> HoldOrderAsync(Guid id,long version,string reason,string key,IExecutionContext context,CancellationToken ct)
    {
        if(string.IsNullOrWhiteSpace(reason))return Task.FromResult(Validation<SalesMutationReceipt>("sales.reason.required","Hold reason is required."));
        return ChangeOrderState(id,version,key,context,SalesOrderState.Confirmed,SalesOrderState.OnHold,
            "sales.order.hold","SalesOrderHeld","ON_HOLD",ct);
    }

    public Task<Result<SalesMutationReceipt>> ReleaseOrderAsync(Guid id,long version,string key,IExecutionContext context,CancellationToken ct) =>
        ChangeOrderState(id,version,key,context,SalesOrderState.OnHold,SalesOrderState.Confirmed,
            "sales.order.release","SalesOrderReleased","CONFIRMED",ct);

    public Task<Result<SalesMutationReceipt>> CancelOrderRemainderAsync(Guid id,long version,string reason,string key,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("sales.order.cancel_remaining",key,"SalesOrderRemainderCancelled","SalesOrder","sales.order.cancel_remaining.completed",context,
            async innerCt =>
            {
                if(string.IsNullOrWhiteSpace(reason))return Validation<SalesMutationReceipt>("sales.reason.required","Cancellation reason is required.");
                var order=await LockOrderAsync(id,context.CompanyId,innerCt);
                if(order is null)return NotFound<SalesMutationReceipt>("sales.order.not_found","Sales Order was not found.");
                if(order.Version!=version)return Conflict<SalesMutationReceipt>("sales.order.stale","Sales Order version is stale.",true);
                if(order.State is not (SalesOrderState.Confirmed or SalesOrderState.OnHold or SalesOrderState.PartiallyCompleted))
                    return Business<SalesMutationReceipt>("sales.order.state","Sales Order remainder cannot be cancelled.");
                order.State=SalesOrderState.CancelledRemainder;order.Version++;
                return Result<SalesMutationReceipt>.Success(new(order.PublicId,"CANCELLED_REMAINDER",order.Version,context.CorrelationId.Value));
            },ct);

    public Task<Result<SalesMutationReceipt>> CloseOrderAsync(Guid id,long version,string key,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("sales.order.close",key,"SalesOrderClosed","SalesOrder","sales.order.close.completed",context,
            async innerCt =>
            {
                var order=await LockOrderAsync(id,context.CompanyId,innerCt);
                if(order is null)return NotFound<SalesMutationReceipt>("sales.order.not_found","Sales Order was not found.");
                if(order.Version!=version)return Conflict<SalesMutationReceipt>("sales.order.stale","Sales Order version is stale.",true);
                var ov=await CurrentOrderVersionAsync(order,innerCt);
                var lines=await dbContext.Set<SalesOrderLineRecord>().AsNoTracking()
                    .Where(x=>x.CompanyId==context.CompanyId&&x.SalesOrderVersionId==ov.Id).ToArrayAsync(innerCt);
                var shipped=await GetNetDispatchedByOrderLineAsync(context.CompanyId,order.Id,lines.Select(x=>x.LinePublicId).ToArray(),innerCt);
                if(lines.Any(x=>shipped.GetValueOrDefault(x.LinePublicId)<x.Quantity)&&order.State!=SalesOrderState.CancelledRemainder)
                    return Business<SalesMutationReceipt>("sales.order.remainder","Order cannot close while eligible remainder exists.");
                order.State=SalesOrderState.Completed;order.ClosedAt=DateTimeOffset.UtcNow;order.Version++;
                return Result<SalesMutationReceipt>.Success(new(order.PublicId,"COMPLETED",order.Version,context.CorrelationId.Value));
            },ct);

    public Task<Result<SalesMutationReceipt>> CreateAmendmentAsync(
        CreateSalesOrderAmendmentCommand command,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("sales.order.amend",command.OperationKey,"SalesOrderAmendmentCreated","SalesOrderAmendment",
            "sales.order.amendment.create.completed",context,
            async innerCt =>
            {
                if(string.IsNullOrWhiteSpace(command.Reason)||command.Deltas.Count==0)
                    return Validation<SalesMutationReceipt>("sales.order.amendment.invalid","Reason and at least one normalized delta are required.");
                var order=await LockOrderAsync(command.SalesOrderPublicId,context.CompanyId,innerCt);
                if(order is null)return NotFound<SalesMutationReceipt>("sales.order.not_found","Sales Order was not found.");
                if(order.Version!=command.ExpectedOrderVersion)return Conflict<SalesMutationReceipt>("sales.order.stale","Sales Order version is stale.",true);
                if(order.State is not (SalesOrderState.Confirmed or SalesOrderState.OnHold or SalesOrderState.PartiallyCompleted))
                    return Business<SalesMutationReceipt>("sales.order.state","Only effective Sales Order can be amended.");
                var ov=await CurrentOrderVersionAsync(order,innerCt);
                var currentLines=await dbContext.Set<SalesOrderLineRecord>().AsNoTracking()
                    .Where(x=>x.CompanyId==context.CompanyId&&x.SalesOrderVersionId==ov.Id).ToArrayAsync(innerCt);
                if(command.Deltas.GroupBy(x=>x.SalesOrderLinePublicId).Any(g=>g.Count()!=1) ||
                   command.Deltas.Any(d=>currentLines.All(x=>x.LinePublicId!=d.SalesOrderLinePublicId)))
                    return Validation<SalesMutationReceipt>("sales.order.amendment.lines","Amendment deltas must target unique current Order lines.");

                var requires=Normalize(command.NewPaymentTerms)!=order.PaymentTerms ||
                    command.Deltas.Any(d=>d.QuantityDelta>0m||d.NewUnitPrice.HasValue||d.NewLineDiscountPercent.HasValue||d.NewTaxPercent.HasValue);
                var shipped=await GetNetDispatchedByOrderLineAsync(
                    context.CompanyId,order.Id,currentLines.Select(x=>x.LinePublicId).ToArray(),innerCt);
                foreach(var delta in command.Deltas)
                {
                    var line=currentLines.Single(x=>x.LinePublicId==delta.SalesOrderLinePublicId);
                    var nextQuantity=line.Quantity+delta.QuantityDelta;
                    if(nextQuantity<0m)
                        return Business<SalesMutationReceipt>("sales.order.amendment.quantity","Amendment cannot make Order quantity negative.");
                    if(nextQuantity<shipped.GetValueOrDefault(line.LinePublicId))
                        return Business<SalesMutationReceipt>(
                            "sales.order.amendment.shipped_floor",
                            "Amendment cannot reduce Order quantity below the net quantity already dispatched.");
                }
                var now=DateTimeOffset.UtcNow;
                var a=new SalesOrderAmendmentRecord {
                    PublicId=Guid.NewGuid(),SalesOrderId=order.Id,CompanyId=context.CompanyId,BaseVersionNumber=order.CurrentVersionNumber,
                    State=SalesOrderAmendmentState.Draft,RequiresApproval=requires,NewPaymentTerms=Normalize(command.NewPaymentTerms),
                    Reason=command.Reason.Trim(),CreatorActorId=context.ActorId,CreatedAt=now
                };
                dbContext.Add(a);await dbContext.SaveChangesAsync(innerCt);
                foreach(var d in command.Deltas)dbContext.Add(new SalesOrderAmendmentDeltaRecord {
                    PublicId=Guid.NewGuid(),AmendmentId=a.Id,CompanyId=context.CompanyId,SalesOrderLinePublicId=d.SalesOrderLinePublicId,
                    QuantityDelta=d.QuantityDelta,NewUnitPrice=d.NewUnitPrice,NewLineDiscountPercent=d.NewLineDiscountPercent,NewTaxPercent=d.NewTaxPercent
                });
                return Result<SalesMutationReceipt>.Success(new(a.PublicId,"DRAFT",1,context.CorrelationId.Value));
            },ct);

    public Task<Result<SalesMutationReceipt>> SubmitAmendmentApprovalAsync(
        Guid id,string key,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("sales.order.amendment.submit_approval",key,"SalesOrderAmendmentApprovalSubmitted","SalesOrderAmendment",
            "sales.order.amendment.submit.completed",context,
            async innerCt =>
            {
                var a=await dbContext.Set<SalesOrderAmendmentRecord>()
                    .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==id,innerCt);
                if(a is null)return NotFound<SalesMutationReceipt>("sales.order.amendment.not_found","Amendment was not found.");
                if(a.State!=SalesOrderAmendmentState.Draft)return Business<SalesMutationReceipt>("sales.order.amendment.state","Only DRAFT amendment can be submitted.");
                a.State=SalesOrderAmendmentState.PendingApproval;
                return Result<SalesMutationReceipt>.Success(new(a.PublicId,"PENDING_APPROVAL",1,context.CorrelationId.Value));
            },ct);

    public async Task<Result<SalesApprovalTarget>> GetAmendmentApprovalTargetAsync(
        Guid id,IExecutionContext context,CancellationToken ct)
    {
        var a=await dbContext.Set<SalesOrderAmendmentRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==id,ct);
        if(a is null)return NotFound<SalesApprovalTarget>("sales.order.amendment.not_found","Amendment was not found.");
        if(!a.RequiresApproval)
            return Business<SalesApprovalTarget>(
                "sales.order.amendment.approval.not_required",
                "This amendment does not require an approval decision.");
        if(a.State!=SalesOrderAmendmentState.PendingApproval)
            return Business<SalesApprovalTarget>(
                "sales.order.amendment.approval.state",
                "Approval-required amendment must be submitted before approval.");
        return Result<SalesApprovalTarget>.Success(new(a.PublicId,a.BaseVersionNumber,a.CreatorActorId,"SalesOrderAmendment"));
    }

    public async Task<Result<SalesAmendmentActivationPlan>> PrepareAmendmentActivationAsync(
        Guid id,IExecutionContext context,CancellationToken ct)
    {
        var a=await dbContext.Set<SalesOrderAmendmentRecord>()
            .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==id,ct);
        if(a is null)return NotFound<SalesAmendmentActivationPlan>("sales.order.amendment.not_found","Amendment was not found.");
        if(a.State is not (SalesOrderAmendmentState.Draft or SalesOrderAmendmentState.PendingApproval))
            return Business<SalesAmendmentActivationPlan>("sales.order.amendment.state","Amendment is not activatable.");
        var order=await dbContext.Set<SalesOrderRecord>().AsNoTracking().SingleAsync(x=>x.Id==a.SalesOrderId&&x.CompanyId==context.CompanyId,ct);
        if(order.CurrentVersionNumber!=a.BaseVersionNumber)
            return Conflict<SalesAmendmentActivationPlan>("sales.order.amendment.stale","Amendment base version is stale.",true);
        var deltas=await dbContext.Set<SalesOrderAmendmentDeltaRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&x.AmendmentId==a.Id&&x.QuantityDelta<0m).ToArrayAsync(ct);
        var releases=new List<SalesReservationReleaseInstruction>();
        foreach(var d in deltas)
        {
            var reservations=await dbContext.Set<InventoryReservationRecord>().AsNoTracking()
                .Where(x=>x.CompanyId==context.CompanyId&&x.SalesOrderPublicId==order.PublicId&&x.SalesOrderLinePublicId==d.SalesOrderLinePublicId)
                .ToArrayAsync(ct);
            var remainingRelease=-d.QuantityDelta;
            foreach(var reservation in reservations)
            {
                var balance=await ReservationBalanceAsync(reservation.Id,context.CompanyId,ct);
                var qty=Math.Min(balance,remainingRelease);
                if(qty>0m){releases.Add(new(reservation.PublicId,order.PublicId,d.SalesOrderLinePublicId,qty));remainingRelease-=qty;}
                if(remainingRelease<=0m)break;
            }
        }
        return Result<SalesAmendmentActivationPlan>.Success(new(
            a.PublicId,order.PublicId,a.RequiresApproval,new(a.PublicId,a.BaseVersionNumber,a.CreatorActorId,"SalesOrderAmendment"),releases));
    }

    public Task<Result<SalesMutationReceipt>> ActivateAmendmentAsync(
        Guid id,string key,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("sales.order.amendment.activate",key,"SalesOrderAmendmentActivated","SalesOrder",
            "sales.order.amendment.activate.completed",context,
            async innerCt =>
            {
                var a=await dbContext.Set<SalesOrderAmendmentRecord>()
                    .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==id,innerCt);
                if(a is null)return NotFound<SalesMutationReceipt>("sales.order.amendment.not_found","Amendment was not found.");
                if(a.State is not (SalesOrderAmendmentState.Draft or SalesOrderAmendmentState.PendingApproval))
                    return Business<SalesMutationReceipt>("sales.order.amendment.state","Amendment is not activatable.");
                var order=await LockOrderByIdAsync(a.SalesOrderId,context.CompanyId,innerCt);
                if(order is null)return NotFound<SalesMutationReceipt>("sales.order.not_found","Sales Order was not found.");
                if(order.CurrentVersionNumber!=a.BaseVersionNumber)
                    return Conflict<SalesMutationReceipt>("sales.order.amendment.stale","Amendment base version is stale.",true);
                var current=await CurrentOrderVersionAsync(order,innerCt);
                var sourceLines=await dbContext.Set<SalesOrderLineRecord>().AsNoTracking()
                    .Where(x=>x.CompanyId==context.CompanyId&&x.SalesOrderVersionId==current.Id).OrderBy(x=>x.Sequence).ToArrayAsync(innerCt);
                var deltas=await dbContext.Set<SalesOrderAmendmentDeltaRecord>().AsNoTracking()
                    .Where(x=>x.CompanyId==context.CompanyId&&x.AmendmentId==a.Id).ToDictionaryAsync(x=>x.SalesOrderLinePublicId,innerCt);
                var now=DateTimeOffset.UtcNow;
                var nextNo=checked(order.CurrentVersionNumber+1);
                var next=new SalesOrderVersionRecord {
                    PublicId=Guid.NewGuid(),SalesOrderId=order.Id,CompanyId=context.CompanyId,VersionNumber=nextNo,
                    SourceQuoteRevisionId=current.SourceQuoteRevisionId,SourceAmendmentId=a.Id,
                    DocumentDiscountPercent=current.DocumentDiscountPercent,CreatorActorId=context.ActorId,CreatedAt=now
                };
                dbContext.Add(next);await dbContext.SaveChangesAsync(innerCt);
                foreach(var src in sourceLines)
                {
                    var d=deltas.GetValueOrDefault(src.LinePublicId);
                    var qty=src.Quantity+(d?.QuantityDelta??0m);
                    if(qty==0m)continue;
                    dbContext.Add(new SalesOrderLineRecord {
                        LinePublicId=src.LinePublicId,SalesOrderVersionId=next.Id,CompanyId=context.CompanyId,Sequence=src.Sequence,
                        ProductId=src.ProductId,VariantId=src.VariantId,UomId=src.UomId,ConversionFactorSnapshot=src.ConversionFactorSnapshot,
                        ProductCodeSnapshot=src.ProductCodeSnapshot,ProductNameSnapshot=src.ProductNameSnapshot,
                        VariantCodeSnapshot=src.VariantCodeSnapshot,VariantNameSnapshot=src.VariantNameSnapshot,
                        UomCodeSnapshot=src.UomCodeSnapshot,UomNameSnapshot=src.UomNameSnapshot,Quantity=qty,
                        UnitPrice=d?.NewUnitPrice??src.UnitPrice,LineDiscountPercent=d?.NewLineDiscountPercent??src.LineDiscountPercent,
                        TaxPercent=d?.NewTaxPercent??src.TaxPercent
                    });
                }
                if(a.NewPaymentTerms is not null)order.PaymentTerms=a.NewPaymentTerms;
                order.CurrentVersionNumber=nextNo;order.ApprovalInheritedFromAcceptedQuote=false;order.Version++;
                a.State=SalesOrderAmendmentState.Active;a.ActivatedAt=now;
                return Result<SalesMutationReceipt>.Success(new(order.PublicId,OrderStateCode(order.State),order.Version,context.CorrelationId.Value));
            },ct);

    public async Task<Result<SalesReservationPlan>> GetReservationCreatePlanAsync(
        CreateReservationFromOrderCommand command,IExecutionContext context,CancellationToken ct)
    {
        if(command.Quantity<=0m)return Validation<SalesReservationPlan>("sales.reservation.quantity","Reservation quantity must be positive.");
        var order=await dbContext.Set<SalesOrderRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==command.SalesOrderPublicId,ct);
        if(order is null)return NotFound<SalesReservationPlan>("sales.order.not_found","Sales Order was not found.");
        if(order.State is not (SalesOrderState.Confirmed or SalesOrderState.PartiallyCompleted))
            return Business<SalesReservationPlan>("sales.order.state","Reservations require an effective Sales Order.");
        if(order.CurrentVersionNumber!=command.SalesOrderVersion)return Conflict<SalesReservationPlan>("sales.order.version","Exact effective Order version is required.",true);
        var ov=await CurrentOrderVersionAsync(order,ct);
        var line=await dbContext.Set<SalesOrderLineRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.SalesOrderVersionId==ov.Id&&x.LinePublicId==command.SalesOrderLinePublicId,ct);
        if(line is null)return NotFound<SalesReservationPlan>("sales.order.line.not_found","Sales Order line was not found.");
        var warehouse=await dbContext.Set<WarehouseRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==command.WarehousePublicId&&x.State==InventoryMasterState.Active,ct);
        if(warehouse is null)return Business<SalesReservationPlan>("sales.warehouse.not_active","Warehouse must be ACTIVE.");
        var shipped=(await GetNetDispatchedByOrderLineAsync(context.CompanyId,order.Id,[line.LinePublicId],ct)).GetValueOrDefault(line.LinePublicId);
        var already=await ActiveReservedForOrderLineAsync(context.CompanyId,order.PublicId,line.LinePublicId,ct);
        if(shipped+already+command.Quantity>line.Quantity)
            return Business<SalesReservationPlan>("sales.reservation.cap","Reservation exceeds eligible Order remainder.");
        var product=await dbContext.Set<ProductRecord>().AsNoTracking().SingleAsync(x=>x.Id==line.ProductId,ct);
        var variant=line.VariantId.HasValue?await dbContext.Set<ProductVariantRecord>().AsNoTracking().SingleAsync(x=>x.Id==line.VariantId.Value,ct):null;
        var uom=await dbContext.Set<UnitOfMeasureRecord>().AsNoTracking().SingleAsync(x=>x.Id==line.UomId,ct);
        return Result<SalesReservationPlan>.Success(new(order.PublicId,order.CurrentVersionNumber,line.LinePublicId,
            product.PublicId,variant?.PublicId,uom.PublicId,line.ConversionFactorSnapshot,warehouse.PublicId,command.Quantity));
    }

    public async Task<Result<SalesReservationChangePlan>> GetReservationChangePlanAsync(
        Guid reservationPublicId,decimal quantity,bool increase,IExecutionContext context,CancellationToken ct)
    {
        if(quantity<=0m)return Validation<SalesReservationChangePlan>("sales.reservation.quantity","Reservation quantity must be positive.");
        var reservation=await dbContext.Set<InventoryReservationRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==reservationPublicId,ct);
        if(reservation is null)return NotFound<SalesReservationChangePlan>("sales.reservation.not_found","Reservation was not found.");
        var order=await dbContext.Set<SalesOrderRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==reservation.SalesOrderPublicId,ct);
        if(order is null)return NotFound<SalesReservationChangePlan>("sales.order.not_found","Sales Order was not found.");
        var warehouse=await dbContext.Set<WarehouseRecord>().AsNoTracking().SingleAsync(x=>x.Id==reservation.WarehouseId,ct);
        if(increase)
        {
            var ov=await CurrentOrderVersionAsync(order,ct);
            var line=await dbContext.Set<SalesOrderLineRecord>().AsNoTracking()
                .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.SalesOrderVersionId==ov.Id&&x.LinePublicId==reservation.SalesOrderLinePublicId,ct);
            if(line is null)return Business<SalesReservationChangePlan>("sales.reservation.source_stale","Reservation source line is not in the effective Order version.");
            var shipped=(await GetNetDispatchedByOrderLineAsync(context.CompanyId,order.Id,[line.LinePublicId],ct)).GetValueOrDefault(line.LinePublicId);
            var reserved=await ActiveReservedForOrderLineAsync(context.CompanyId,order.PublicId,line.LinePublicId,ct);
            if(shipped+reserved+quantity>line.Quantity)return Business<SalesReservationChangePlan>("sales.reservation.cap","Reservation increase exceeds eligible Order remainder.");
        }
        return Result<SalesReservationChangePlan>.Success(new(reservation.PublicId,order.PublicId,reservation.SalesOrderLinePublicId,warehouse.PublicId,quantity));
    }

    private Task<Result<SalesMutationReceipt>> ChangeOrderState(
        Guid id,long version,string key,IExecutionContext context,SalesOrderState expected,SalesOrderState target,
        string scope,string action,string state,CancellationToken ct) =>
        MutateAsync(scope,key,action,"SalesOrder",$"{scope}.completed",context,
            async innerCt=>{
                var order=await LockOrderAsync(id,context.CompanyId,innerCt);
                if(order is null)return NotFound<SalesMutationReceipt>("sales.order.not_found","Sales Order was not found.");
                if(order.Version!=version)return Conflict<SalesMutationReceipt>("sales.order.stale","Sales Order version is stale.",true);
                if(order.State!=expected)return Business<SalesMutationReceipt>("sales.order.state","Sales Order is not in the required state.");
                order.State=target;order.Version++;
                return Result<SalesMutationReceipt>.Success(new(order.PublicId,state,order.Version,context.CorrelationId.Value));
            },ct);

    private async Task<Result<IReadOnlyList<ResolvedTrade>>> ResolveLinesAsync(
        Guid companyId,IReadOnlyList<SalesTradeLineInput> lines,CancellationToken ct)
    {
        if(lines.Count==0||lines.Any(x=>x.Sequence<=0||x.Quantity<=0m||x.UnitPrice<0m||
            x.LineDiscountPercent<0m||x.LineDiscountPercent>100m||x.TaxPercent<0m||x.TaxPercent>100m)||
            lines.GroupBy(x=>x.Sequence).Any(g=>g.Count()!=1))
            return Validation<IReadOnlyList<ResolvedTrade>>("sales.lines.invalid","Sales lines require unique positive sequence, positive quantity and valid commercial percentages.");
        var result=new List<ResolvedTrade>(lines.Count);
        foreach(var line in lines.OrderBy(x=>x.Sequence))
        {
            var r=await ResolveTradeAsync(companyId,line,ct);
            if(r.IsFailure)return Result<IReadOnlyList<ResolvedTrade>>.Failure(r.Error!);
            result.Add(r.Value!);
        }
        return Result<IReadOnlyList<ResolvedTrade>>.Success(result);
    }

    private static ApplicationError? ValidateDocument(
        string number,string currency,decimal discount,IReadOnlyList<SalesTradeLineInput> lines)
    {
        if(string.IsNullOrWhiteSpace(number))return new(ErrorCategory.Validation,"sales.number.required","Document number is required.");
        var c=currency?.Trim().ToUpperInvariant();
        if(c is null||c.Length!=3||c.Any(x=>x<'A'||x>'Z'))return new(ErrorCategory.Validation,"sales.currency.invalid","Currency must be a three-letter ISO-style code.");
        if(discount<0m||discount>100m)return new(ErrorCategory.Validation,"sales.discount.invalid","Document discount must be between 0 and 100.");
        if(lines.Count==0)return new(ErrorCategory.Validation,"sales.lines.required","At least one Sales line is required.");
        return null;
    }

    private void AddQuoteLines(long revisionId,Guid companyId,IReadOnlyList<SalesTradeLineInput> inputs,IReadOnlyList<ResolvedTrade> resolved)
    {
        var ordered=inputs.OrderBy(x=>x.Sequence).ToArray();
        for(var i=0;i<ordered.Length;i++){var x=ordered[i];var r=resolved[i];dbContext.Add(new QuoteLineRecord {
            PublicId=Guid.NewGuid(),RevisionId=revisionId,CompanyId=companyId,Sequence=x.Sequence,ProductId=r.ProductId,VariantId=r.VariantId,
            UomId=r.UomId,ConversionFactorSnapshot=r.ConversionFactor,ProductCodeSnapshot=r.ProductCode,ProductNameSnapshot=r.ProductName,
            VariantCodeSnapshot=r.VariantCode,VariantNameSnapshot=r.VariantName,UomCodeSnapshot=r.UomCode,UomNameSnapshot=r.UomName,
            Quantity=x.Quantity,UnitPrice=x.UnitPrice,LineDiscountPercent=x.LineDiscountPercent,TaxPercent=x.TaxPercent
        });}
    }

    private void AddOrderLines(long versionId,Guid companyId,IReadOnlyList<SalesTradeLineInput> inputs,IReadOnlyList<ResolvedTrade> resolved)
    {
        var ordered=inputs.OrderBy(x=>x.Sequence).ToArray();
        for(var i=0;i<ordered.Length;i++){var x=ordered[i];var r=resolved[i];dbContext.Add(new SalesOrderLineRecord {
            LinePublicId=Guid.NewGuid(),SalesOrderVersionId=versionId,CompanyId=companyId,Sequence=x.Sequence,ProductId=r.ProductId,VariantId=r.VariantId,
            UomId=r.UomId,ConversionFactorSnapshot=r.ConversionFactor,ProductCodeSnapshot=r.ProductCode,ProductNameSnapshot=r.ProductName,
            VariantCodeSnapshot=r.VariantCode,VariantNameSnapshot=r.VariantName,UomCodeSnapshot=r.UomCode,UomNameSnapshot=r.UomName,
            Quantity=x.Quantity,UnitPrice=x.UnitPrice,LineDiscountPercent=x.LineDiscountPercent,TaxPercent=x.TaxPercent
        });}
    }

    private static SalesOrderLineRecord ToOrderLine(long versionId,Guid companyId,Guid lineId,QuoteLineRecord x,decimal quantity)=>new(){
        LinePublicId=lineId,SalesOrderVersionId=versionId,CompanyId=companyId,Sequence=x.Sequence,ProductId=x.ProductId,VariantId=x.VariantId,
        UomId=x.UomId,ConversionFactorSnapshot=x.ConversionFactorSnapshot,ProductCodeSnapshot=x.ProductCodeSnapshot,ProductNameSnapshot=x.ProductNameSnapshot,
        VariantCodeSnapshot=x.VariantCodeSnapshot,VariantNameSnapshot=x.VariantNameSnapshot,UomCodeSnapshot=x.UomCodeSnapshot,UomNameSnapshot=x.UomNameSnapshot,
        Quantity=quantity,UnitPrice=x.UnitPrice,LineDiscountPercent=x.LineDiscountPercent,TaxPercent=x.TaxPercent
    };

    private Task<QuoteRecord?> LockQuoteAsync(Guid id,Guid companyId,CancellationToken ct)=>
        dbContext.Set<QuoteRecord>().FromSqlInterpolated(
            $"SELECT * FROM sales.quotes WHERE public_id = {id} AND company_id = {companyId} FOR UPDATE").SingleOrDefaultAsync(ct);
    private Task<SalesOrderRecord?> LockOrderAsync(Guid id,Guid companyId,CancellationToken ct)=>
        dbContext.Set<SalesOrderRecord>().FromSqlInterpolated(
            $"SELECT * FROM sales.sales_orders WHERE public_id = {id} AND company_id = {companyId} FOR UPDATE").SingleOrDefaultAsync(ct);
    private Task<SalesOrderRecord?> LockOrderByIdAsync(long id,Guid companyId,CancellationToken ct)=>
        dbContext.Set<SalesOrderRecord>().FromSqlInterpolated(
            $"SELECT * FROM sales.sales_orders WHERE id = {id} AND company_id = {companyId} FOR UPDATE").SingleOrDefaultAsync(ct);
    private Task<QuoteRevisionRecord> CurrentQuoteRevisionAsync(QuoteRecord quote,CancellationToken ct)=>
        dbContext.Set<QuoteRevisionRecord>().SingleAsync(x=>x.CompanyId==quote.CompanyId&&x.QuoteId==quote.Id&&x.RevisionNumber==quote.CurrentRevisionNumber,ct);
    private Task<SalesOrderVersionRecord> CurrentOrderVersionAsync(SalesOrderRecord order,CancellationToken ct)=>
        dbContext.Set<SalesOrderVersionRecord>().SingleAsync(x=>x.CompanyId==order.CompanyId&&x.SalesOrderId==order.Id&&x.VersionNumber==order.CurrentVersionNumber,ct);

    private async Task<decimal> ReservationBalanceAsync(long reservationId,Guid companyId,CancellationToken ct)
    {
        var movements=await dbContext.Set<InventoryReservationMovementRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId&&x.ReservationId==reservationId).ToArrayAsync(ct);
        return movements.Sum(x=>x.Kind is ReservationMovementKind.Create or ReservationMovementKind.Increase?x.EnteredQuantity:-x.EnteredQuantity);
    }
    private async Task<decimal> ActiveReservedForOrderLineAsync(Guid companyId,Guid orderId,Guid lineId,CancellationToken ct)
    {
        var reservations=await dbContext.Set<InventoryReservationRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId&&x.SalesOrderPublicId==orderId&&x.SalesOrderLinePublicId==lineId).ToArrayAsync(ct);
        decimal total=0m;foreach(var r in reservations)total+=await ReservationBalanceAsync(r.Id,companyId,ct);return total;
    }
    private static string? Normalize(string? value)=>string.IsNullOrWhiteSpace(value)?null:value.Trim();
}
