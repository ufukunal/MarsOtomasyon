using Mars.Api.Foundation.Errors;
using Mars.Application.Foundation.Approvals;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Application.Sales;
using Mars.Domain.Sales;

namespace Mars.Api.Sales;

public static class SalesEndpoints
{
    public static void MapSalesEndpoints(this WebApplication app)
    {
        var sales = app.MapGroup("/api/v1/sales").RequireAuthorization();

        sales.MapGet("/quotes", async (IExecutionContext c, SalesQueryHandler h, CancellationToken ct) =>
            Map(await h.ListQuotesAsync(c, ct), c)).WithName("ListSalesQuotes");
        sales.MapGet("/quotes/{id:guid}", async (Guid id, IExecutionContext c, SalesQueryHandler h, CancellationToken ct) =>
            Map(await h.GetQuoteAsync(id, c, ct), c)).WithName("GetSalesQuote");
        sales.MapPost("/quotes", async (CreateQuoteRequest r, HttpRequest http, IExecutionContext c, SalesCommandHandler h, CancellationToken ct) =>
            Created("/api/v1/sales/quotes", await h.CreateQuoteAsync(
                new CreateQuoteCommand(r.Number,r.CustomerPartyPublicId,r.CurrencyCode,r.PaymentTerms,r.DocumentDiscountPercent,
                    r.Lines.Select(Line).ToArray(),Key(http)),c,ct),c)).WithName("CreateSalesQuote");
        sales.MapPost("/quotes/{id:guid}/revisions", async (Guid id, ReviseQuoteRequest r, HttpRequest http, IExecutionContext c, SalesCommandHandler h, CancellationToken ct) =>
            Map(await h.ReviseQuoteAsync(new ReviseQuoteCommand(id,r.Version,r.PaymentTerms,r.DocumentDiscountPercent,r.Lines.Select(Line).ToArray(),Key(http)),c,ct),c))
            .WithName("ReviseSalesQuote");
        sales.MapPost("/quotes/{id:guid}/submit-approval", async (Guid id, VersionRequest r, HttpRequest http, IExecutionContext c, SalesCommandHandler h, CancellationToken ct) =>
            Map(await h.SubmitQuoteApprovalAsync(id,r.Version,Key(http),c,ct),c)).WithName("SubmitSalesQuoteApproval");
        sales.MapPost("/quotes/{id:guid}/approval", async (Guid id, ApprovalRequest r, HttpRequest http, IExecutionContext c, SalesCommandHandler h, CancellationToken ct) =>
            ParseDecision(r.Decision) is { } d
                ? Map(await h.ApproveQuoteAsync(id,d,r.Reason,Key(http),c,ct),c)
                : Invalid("sales.approval.decision","Decision must be APPROVED or REJECTED.",c))
            .WithName("DecideSalesQuoteApproval");
        sales.MapPost("/quotes/{id:guid}/customer-review", async (Guid id, HttpRequest http, IExecutionContext c, SalesCommandHandler h, CancellationToken ct) =>
            Map(await h.SendQuoteToCustomerAsync(id,Key(http),c,ct),c)).WithName("SendSalesQuoteToCustomer");
        sales.MapPost("/quotes/{id:guid}/accept", async (Guid id, HttpRequest http, IExecutionContext c, SalesCommandHandler h, CancellationToken ct) =>
            Map(await h.AcceptQuoteAsync(id,Key(http),c,ct),c)).WithName("AcceptSalesQuote");
        sales.MapPost("/quotes/{id:guid}/cancel", async (Guid id, ReasonRequest r, HttpRequest http, IExecutionContext c, SalesCommandHandler h, CancellationToken ct) =>
            Map(await h.CancelQuoteAsync(id,r.Reason,Key(http),c,ct),c)).WithName("CancelSalesQuote");
        sales.MapPost("/quotes/{id:guid}/expire", async (Guid id, HttpRequest http, IExecutionContext c, SalesCommandHandler h, CancellationToken ct) =>
            Map(await h.ExpireQuoteAsync(id,Key(http),c,ct),c)).WithName("ExpireSalesQuote");
        sales.MapPost("/quotes/{id:guid}/convert", async (Guid id, ConvertQuoteRequest r, HttpRequest http, IExecutionContext c, SalesCommandHandler h, CancellationToken ct) =>
            Created("/api/v1/sales/orders",await h.ConvertQuoteAsync(
                new ConvertQuoteCommand(id,r.QuoteRevisionNumber,r.OrderNumber,r.Lines.Select(x=>new QuoteConversionSelection(x.QuoteLinePublicId,x.Quantity)).ToArray(),Key(http)),c,ct),c))
            .WithName("ConvertSalesQuote");

        sales.MapGet("/orders", async (IExecutionContext c, SalesQueryHandler h, CancellationToken ct) =>
            Map(await h.ListOrdersAsync(c,ct),c)).WithName("ListSalesOrders");
        sales.MapGet("/orders/{id:guid}", async (Guid id,IExecutionContext c,SalesQueryHandler h,CancellationToken ct)=>
            Map(await h.GetOrderAsync(id,c,ct),c)).WithName("GetSalesOrder");
        sales.MapPost("/orders", async (CreateDirectOrderRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Created("/api/v1/sales/orders",await h.CreateDirectOrderAsync(
                new CreateDirectOrderCommand(r.Number,r.CustomerPartyPublicId,r.CurrencyCode,r.PaymentTerms,r.DocumentDiscountPercent,r.Lines.Select(Line).ToArray(),Key(http)),c,ct),c))
            .WithName("CreateDirectSalesOrder");
        sales.MapPost("/orders/{id:guid}/submit-approval", async (Guid id,VersionRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Map(await h.SubmitOrderApprovalAsync(id,r.Version,Key(http),c,ct),c)).WithName("SubmitSalesOrderApproval");
        sales.MapPost("/orders/{id:guid}/approval", async (Guid id,ApprovalRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            ParseDecision(r.Decision) is { } d
                ? Map(await h.ApproveOrderAsync(id,d,r.Reason,Key(http),c,ct),c)
                : Invalid("sales.approval.decision","Decision must be APPROVED or REJECTED.",c))
            .WithName("DecideSalesOrderApproval");
        sales.MapPost("/orders/{id:guid}/confirm", async (Guid id,VersionRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Map(await h.ConfirmOrderAsync(id,r.Version,Key(http),c,ct),c)).WithName("ConfirmSalesOrder");
        sales.MapPost("/orders/{id:guid}/hold", async (Guid id,ReasonVersionRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Map(await h.HoldOrderAsync(id,r.Version,r.Reason,Key(http),c,ct),c)).WithName("HoldSalesOrder");
        sales.MapPost("/orders/{id:guid}/release", async (Guid id,VersionRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Map(await h.ReleaseOrderAsync(id,r.Version,Key(http),c,ct),c)).WithName("ReleaseSalesOrder");
        sales.MapPost("/orders/{id:guid}/cancel-remainder", async (Guid id,ReasonVersionRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Map(await h.CancelOrderRemainderAsync(id,r.Version,r.Reason,Key(http),c,ct),c)).WithName("CancelSalesOrderRemainder");
        sales.MapPost("/orders/{id:guid}/close", async (Guid id,VersionRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Map(await h.CloseOrderAsync(id,r.Version,Key(http),c,ct),c)).WithName("CloseSalesOrder");
        sales.MapPost("/orders/{id:guid}/amendments", async (Guid id,CreateAmendmentRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Created("/api/v1/sales/orders/"+id+"/amendments",await h.CreateAmendmentAsync(
                new CreateSalesOrderAmendmentCommand(id,r.Version,r.NewPaymentTerms,r.Reason,
                    r.Deltas.Select(x=>new SalesOrderAmendmentDeltaInput(x.SalesOrderLinePublicId,x.QuantityDelta,x.NewUnitPrice,x.NewLineDiscountPercent,x.NewTaxPercent)).ToArray(),Key(http)),c,ct),c))
            .WithName("CreateSalesOrderAmendment");
        sales.MapPost("/amendments/{id:guid}/submit-approval", async (Guid id,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Map(await h.SubmitAmendmentApprovalAsync(id,Key(http),c,ct),c)).WithName("SubmitSalesOrderAmendmentApproval");
        sales.MapPost("/amendments/{id:guid}/approval", async (Guid id,ApprovalRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            ParseDecision(r.Decision) is { } d
                ? Map(await h.ApproveAmendmentAsync(id,d,r.Reason,Key(http),c,ct),c)
                : Invalid("sales.approval.decision","Decision must be APPROVED or REJECTED.",c))
            .WithName("DecideSalesOrderAmendmentApproval");
        sales.MapPost("/amendments/{id:guid}/activate", async (Guid id,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Map(await h.ActivateAmendmentAsync(id,Key(http),c,ct),c)).WithName("ActivateSalesOrderAmendment");

        sales.MapPost("/reservations", async (CreateSalesReservationRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Map(await h.CreateReservationAsync(new CreateReservationFromOrderCommand(r.SalesOrderPublicId,r.SalesOrderVersion,r.SalesOrderLinePublicId,r.WarehousePublicId,r.Quantity,Key(http)),c,ct),c))
            .WithName("CreateSalesReservation");
        sales.MapPost("/reservations/{id:guid}/increase", async (Guid id,ChangeSalesReservationRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Map(await h.IncreaseReservationAsync(new ChangeSalesReservationCommand(id,r.Quantity,Key(http)),c,ct),c)).WithName("IncreaseSalesReservation");
        sales.MapPost("/reservations/{id:guid}/release", async (Guid id,ChangeSalesReservationRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Map(await h.ReleaseReservationAsync(new ChangeSalesReservationCommand(id,r.Quantity,Key(http)),c,ct),c)).WithName("ReleaseSalesReservation");

        sales.MapGet("/dispatches", async (IExecutionContext c,SalesQueryHandler h,CancellationToken ct)=>
            Map(await h.ListDispatchesAsync(c,ct),c)).WithName("ListSalesDispatches");
        sales.MapGet("/dispatches/{id:guid}", async (Guid id,IExecutionContext c,SalesQueryHandler h,CancellationToken ct)=>
            Map(await h.GetDispatchAsync(id,c,ct),c)).WithName("GetSalesDispatch");
        sales.MapPost("/dispatches", async (CreateDispatchRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Created("/api/v1/sales/dispatches",await h.CreateDispatchAsync(new CreateDispatchCommand(
                r.Number,r.SalesOrderPublicId,r.SalesOrderVersion,r.WarehousePublicId,
                r.Lines.Select(x=>new CreateDispatchLineInput(x.Sequence,x.SalesOrderLinePublicId,x.Quantity,x.LocationPublicId,x.LotPublicId,x.SerialPublicId,x.ReservationPublicId)).ToArray(),Key(http)),c,ct),c))
            .WithName("CreateSalesDispatch");
        sales.MapPost("/dispatches/{id:guid}/ready", async (Guid id,VersionRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Map(await h.ReadyDispatchAsync(id,r.Version,Key(http),c,ct),c)).WithName("ReadySalesDispatch");
        sales.MapPost("/dispatches/{id:guid}/post", async (Guid id,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Map(await h.PostDispatchAsync(id,Key(http),c,ct),c)).WithName("PostSalesDispatch");
        sales.MapPost("/dispatches/{id:guid}/handoff", async (Guid id,VersionRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Map(await h.HandoffDispatchAsync(id,r.Version,Key(http),c,ct),c)).WithName("HandoffSalesDispatch");
        sales.MapPost("/dispatches/{id:guid}/deliver", async (Guid id,VersionRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Map(await h.DeliverDispatchAsync(id,r.Version,Key(http),c,ct),c)).WithName("DeliverSalesDispatch");
        sales.MapPost("/dispatches/{id:guid}/cancel", async (Guid id,ReasonVersionRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Map(await h.CancelDispatchAsync(id,r.Version,r.Reason,Key(http),c,ct),c)).WithName("CancelSalesDispatch");
        sales.MapPost("/dispatches/{id:guid}/reverse", async (Guid id,ReverseDispatchRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Created("/api/v1/sales/dispatches",await h.ReverseDispatchAsync(new ReverseDispatchCommand(id,r.ReversalNumber,r.Reason,Key(http)),c,ct),c))
            .WithName("ReverseSalesDispatch");

        sales.MapGet("/invoices", async (IExecutionContext c,SalesQueryHandler h,CancellationToken ct)=>
            Map(await h.ListInvoicesAsync(c,ct),c)).WithName("ListSalesInvoiceDrafts");
        sales.MapGet("/invoices/{id:guid}", async (Guid id,IExecutionContext c,SalesQueryHandler h,CancellationToken ct)=>
            Map(await h.GetInvoiceAsync(id,c,ct),c)).WithName("GetSalesInvoiceDraft");
        sales.MapGet("/invoice-source-preview", async (SalesInvoiceSourceMode mode,Guid sourceDocumentPublicId,IExecutionContext c,SalesQueryHandler h,CancellationToken ct)=>
            Map(await h.PreviewInvoiceSourceAsync(mode,sourceDocumentPublicId,c,ct),c)).WithName("PreviewSalesInvoiceSource");
        sales.MapPost("/invoices", async (CreateInvoiceDraftRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Created("/api/v1/sales/invoices",await h.CreateInvoiceDraftAsync(Invoice(r,Key(http)),c,ct),c)).WithName("CreateSalesInvoiceDraft");
        sales.MapPut("/invoices/{id:guid}", async (Guid id,ReplaceInvoiceDraftRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Map(await h.ReplaceInvoiceDraftAsync(id,r.Version,Invoice(r,Key(http)),c,ct),c)).WithName("ReplaceSalesInvoiceDraft");
        sales.MapPost("/invoices/{id:guid}/cancel", async (Guid id,ReasonVersionRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Map(await h.CancelInvoiceDraftAsync(id,r.Version,r.Reason,Key(http),c,ct),c)).WithName("CancelSalesInvoiceDraft");
        sales.MapPost("/invoices/{id:guid}/post", async (Guid id,VersionRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Map(await h.PostInvoiceAsync(id,r.Version,Key(http),c,ct),c)).WithName("PostSalesInvoice");
        sales.MapPost("/invoices/{id:guid}/reverse", async (Guid id,VersionRequest r,HttpRequest http,IExecutionContext c,SalesCommandHandler h,CancellationToken ct)=>
            Map(await h.ReverseInvoiceAsync(id,r.Version,Key(http),c,ct),c)).WithName("ReverseSalesInvoice");
    }

    private static SalesTradeLineInput Line(SalesTradeLineRequest x) =>
        new(x.Sequence,x.ProductPublicId,x.VariantPublicId,x.UomPublicId,x.Quantity,x.UnitPrice,x.LineDiscountPercent,x.TaxPercent);

    private static CreateInvoiceDraftCommand Invoice(CreateInvoiceDraftRequest r,string key) =>
        new(r.Number,r.CustomerPartyPublicId,r.SourceMode,r.DocumentDate,r.DueDate,r.CurrencyCode,r.DocumentDiscountPercent,
            r.Lines.Select(InvoiceLine).ToArray(),key);
    private static CreateInvoiceDraftCommand Invoice(ReplaceInvoiceDraftRequest r,string key) =>
        new(r.Number,r.CustomerPartyPublicId,r.SourceMode,r.DocumentDate,r.DueDate,r.CurrencyCode,r.DocumentDiscountPercent,
            r.Lines.Select(InvoiceLine).ToArray(),key);
    private static CreateInvoiceDraftLineInput InvoiceLine(CreateInvoiceDraftLineRequest x) =>
        new(x.Sequence,x.ProductPublicId,x.VariantPublicId,x.UomPublicId,x.Quantity,x.UnitPrice,x.LineDiscountPercent,x.TaxPercent,
            x.SourceDocumentPublicId,x.SourceLinePublicId,x.SourceVersion);

    private static ApprovalDecisionKind? ParseDecision(string value) => value.Trim().ToUpperInvariant() switch
    {
        "APPROVED" => ApprovalDecisionKind.Approved,
        "REJECTED" => ApprovalDecisionKind.Rejected,
        _ => null
    };

    private static IResult Map<T>(Result<T> result,IExecutionContext context) =>
        result.IsFailure ? ApplicationErrorHttpMapper.ToResult(result.Error!,context.CorrelationId.Value) : Results.Ok(result.Value);
    private static IResult Created<T>(string basePath,Result<T> result,IExecutionContext context) =>
        result.IsFailure ? ApplicationErrorHttpMapper.ToResult(result.Error!,context.CorrelationId.Value)
            : Results.Created(basePath+"/"+ExtractPublicId(result.Value),result.Value);
    private static object ExtractPublicId<T>(T? value) => value switch
    {
        SalesMutationReceipt x => x.PublicId,
        _ => "created"
    };
    private static string Key(HttpRequest request)=>request.Headers["Idempotency-Key"].ToString();
    private static IResult Invalid(string code,string message,IExecutionContext context)=>
        ApplicationErrorHttpMapper.ToResult(new ApplicationError(ErrorCategory.Validation,code,message),context.CorrelationId.Value);
}
