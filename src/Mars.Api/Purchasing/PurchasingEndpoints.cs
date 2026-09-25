using Mars.Api.Foundation.Errors;
using Mars.Application.Foundation.Approvals;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Application.Purchasing;
using Mars.Domain.Purchasing;

namespace Mars.Api.Purchasing;

public static class PurchasingEndpoints
{
    public static void MapPurchasingEndpoints(this WebApplication app)
    {
        var purchasing=app.MapGroup("/api/v1/purchasing").RequireAuthorization();

        purchasing.MapGet("/orders",async(IExecutionContext c,PurchasingQueryHandler h,CancellationToken ct)=>
            Map(await h.ListOrdersAsync(c,ct),c))
            .WithName("ListPurchaseOrders");

        purchasing.MapGet("/orders/{id:guid}",async(Guid id,IExecutionContext c,PurchasingQueryHandler h,CancellationToken ct)=>
            Map(await h.GetOrderAsync(id,c,ct),c))
            .WithName("GetPurchaseOrder");

        purchasing.MapPost("/orders",async(CreatePurchaseOrderRequest r,HttpRequest http,IExecutionContext c,PurchasingCommandHandler h,CancellationToken ct)=>
            Created("/api/v1/purchasing/orders",await h.CreateOrderAsync(
                new CreatePurchaseOrderCommand(
                    r.Number,r.SupplierPartyPublicId,r.CurrencyCode,r.PaymentTerms,r.DocumentDiscountPercent,
                    r.Lines.Select(Line).ToArray(),Key(http)),c,ct),c))
            .WithName("CreatePurchaseOrder");

        purchasing.MapPost("/orders/{id:guid}/confirm",async(Guid id,PurchasingVersionRequest r,HttpRequest http,IExecutionContext c,PurchasingCommandHandler h,CancellationToken ct)=>
            Map(await h.ConfirmOrderAsync(id,r.Version,Key(http),c,ct),c))
            .WithName("ConfirmPurchaseOrder");
        purchasing.MapPost("/orders/{id:guid}/amendments",async(Guid id,AmendPurchaseOrderRequest r,HttpRequest http,IExecutionContext c,PurchasingCommandHandler h,CancellationToken ct)=>
            Map(await h.AmendOrderAsync(
                new AmendPurchaseOrderCommand(id,r.Version,r.Reason,
                    r.Deltas.Select(x=>new PurchaseOrderAmendmentDeltaInput(x.PurchaseOrderLinePublicId,x.QuantityDelta)).ToArray(),
                    Key(http)),c,ct),c))
            .WithName("AmendPurchaseOrderRemainder");
        purchasing.MapPost("/orders/{id:guid}/cancel-remainder",async(Guid id,PurchasingReasonVersionRequest r,HttpRequest http,IExecutionContext c,PurchasingCommandHandler h,CancellationToken ct)=>
            Map(await h.CancelOrderRemainderAsync(id,r.Version,r.Reason,Key(http),c,ct),c))
            .WithName("CancelPurchaseOrderRemainder");
        purchasing.MapPost("/orders/{id:guid}/close",async(Guid id,PurchasingVersionRequest r,HttpRequest http,IExecutionContext c,PurchasingCommandHandler h,CancellationToken ct)=>
            Map(await h.CloseOrderAsync(id,r.Version,Key(http),c,ct),c))
            .WithName("ClosePurchaseOrder");

        purchasing.MapGet("/receipts",async(IExecutionContext c,PurchasingQueryHandler h,CancellationToken ct)=>
            Map(await h.ListReceiptsAsync(c,ct),c))
            .WithName("ListGoodsReceipts");

        purchasing.MapPost("/receipts",async(CreateGoodsReceiptRequest r,HttpRequest http,IExecutionContext c,PurchasingCommandHandler h,CancellationToken ct)=>
            Created("/api/v1/purchasing/receipts",await h.CreateReceiptAsync(
                new CreateGoodsReceiptCommand(
                    r.Number,r.PurchaseOrderPublicId,r.PurchaseOrderVersion,r.WarehousePublicId,
                    r.Lines.Select(x=>new CreateGoodsReceiptLineInput(
                        x.Sequence,x.PurchaseOrderLinePublicId,x.Quantity,x.LocationPublicId,x.LotPublicId,x.SerialPublicId)).ToArray(),
                    Key(http)),c,ct),c))
            .WithName("CreateGoodsReceipt");

        purchasing.MapPost("/receipts/{id:guid}/ready",async(Guid id,PurchasingVersionRequest r,HttpRequest http,IExecutionContext c,PurchasingCommandHandler h,CancellationToken ct)=>
            Map(await h.ReadyReceiptAsync(id,r.Version,Key(http),c,ct),c))
            .WithName("ReadyGoodsReceipt");
        purchasing.MapPost("/receipts/{id:guid}/cancel",async(Guid id,PurchasingReasonVersionRequest r,HttpRequest http,IExecutionContext c,PurchasingCommandHandler h,CancellationToken ct)=>
            Map(await h.CancelReceiptAsync(id,r.Version,r.Reason,Key(http),c,ct),c))
            .WithName("CancelGoodsReceipt");
        purchasing.MapPost("/receipts/{id:guid}/post",async(Guid id,HttpRequest http,IExecutionContext c,PurchasingCommandHandler h,CancellationToken ct)=>
            Map(await h.PostReceiptAsync(id,Key(http),c,ct),c))
            .WithName("PostGoodsReceipt");
        purchasing.MapPost("/receipts/{id:guid}/reverse",async(Guid id,HttpRequest http,IExecutionContext c,PurchasingCommandHandler h,CancellationToken ct)=>
            Map(await h.ReverseReceiptAsync(id,Key(http),c,ct),c))
            .WithName("ReverseGoodsReceipt");

        purchasing.MapGet("/invoices",async(IExecutionContext c,PurchasingQueryHandler h,CancellationToken ct)=>
            Map(await h.ListInvoicesAsync(c,ct),c))
            .WithName("ListSupplierInvoiceDrafts");

        purchasing.MapPost("/invoices",async(CreateSupplierInvoiceDraftRequest r,HttpRequest http,IExecutionContext c,PurchasingCommandHandler h,CancellationToken ct)=>
            Created("/api/v1/purchasing/invoices",await h.CreateInvoiceDraftAsync(
                new CreateSupplierInvoiceDraftCommand(
                    r.Number,r.SupplierPartyPublicId,r.SourceMode,r.DocumentDate,r.DueDate,r.CurrencyCode,
                    r.DocumentDiscountPercent,
                    r.Lines.Select(x=>new CreateSupplierInvoiceDraftLineInput(
                        x.Sequence,x.ProductPublicId,x.VariantPublicId,x.UomPublicId,x.Quantity,x.UnitPrice,
                        x.LineDiscountPercent,x.TaxPercent,x.SourceDocumentPublicId,x.SourceLinePublicId,x.SourceVersion)).ToArray(),
                    r.DirectReason,Key(http)),c,ct),c))
            .WithName("CreateSupplierInvoiceDraft");

        purchasing.MapPut("/invoices/{id:guid}",async(Guid id,ReplaceSupplierInvoiceDraftRequest r,HttpRequest http,IExecutionContext c,PurchasingCommandHandler h,CancellationToken ct)=>
            Map(await h.ReplaceInvoiceDraftAsync(id,r.Version,
                new CreateSupplierInvoiceDraftCommand(
                    r.Number,r.SupplierPartyPublicId,r.SourceMode,r.DocumentDate,r.DueDate,r.CurrencyCode,
                    r.DocumentDiscountPercent,
                    r.Lines.Select(x=>new CreateSupplierInvoiceDraftLineInput(
                        x.Sequence,x.ProductPublicId,x.VariantPublicId,x.UomPublicId,x.Quantity,x.UnitPrice,
                        x.LineDiscountPercent,x.TaxPercent,x.SourceDocumentPublicId,x.SourceLinePublicId,x.SourceVersion)).ToArray(),
                    r.DirectReason,Key(http)),c,ct),c))
            .WithName("ReplaceSupplierInvoiceDraft");

        purchasing.MapPost("/invoices/{id:guid}/cancel",async(Guid id,PurchasingReasonVersionRequest r,HttpRequest http,IExecutionContext c,PurchasingCommandHandler h,CancellationToken ct)=>
            Map(await h.CancelInvoiceDraftAsync(id,r.Version,r.Reason,Key(http),c,ct),c))
            .WithName("CancelSupplierInvoiceDraft");

        purchasing.MapPost("/invoices/{id:guid}/post",async(Guid id,PurchasingVersionRequest r,HttpRequest http,IExecutionContext c,PurchasingCommandHandler h,CancellationToken ct)=>
            Map(await h.PostInvoiceAsync(id,r.Version,Key(http),c,ct),c))
            .WithName("PostSupplierInvoice");

        purchasing.MapPost("/invoices/{id:guid}/reverse",async(Guid id,PurchasingVersionRequest r,HttpRequest http,IExecutionContext c,PurchasingCommandHandler h,CancellationToken ct)=>
            Map(await h.ReverseInvoiceAsync(id,r.Version,Key(http),c,ct),c))
            .WithName("ReverseSupplierInvoice");

        purchasing.MapGet("/matches",async(IExecutionContext c,PurchasingQueryHandler h,CancellationToken ct)=>
            Map(await h.ListMatchesAsync(c,ct),c))
            .WithName("ListPurchaseMatches");

        purchasing.MapGet("/match-preview",async(
            SupplierInvoiceSourceMode mode,Guid sourceDocumentPublicId,IExecutionContext c,PurchasingQueryHandler h,CancellationToken ct)=>
            Map(await h.PreviewMatchAsync(mode,sourceDocumentPublicId,c,ct),c))
            .WithName("PreviewPurchaseMatch");

        purchasing.MapPost("/matches/{id:guid}/approval",async(
            Guid id,PurchaseMatchApprovalRequest r,HttpRequest http,IExecutionContext c,PurchasingCommandHandler h,CancellationToken ct)=>
            ParseDecision(r.Decision) is { } decision
                ? Map(await h.ApproveMatchExceptionAsync(id,decision,r.Reason,Key(http),c,ct),c)
                : Invalid("purchasing.match.approval.decision","Decision must be APPROVED or REJECTED.",c))
            .WithName("DecidePurchaseMatchException");

        purchasing.MapGet("/return-source-preview",async(
            Guid goodsReceiptPublicId,IExecutionContext c,PurchasingQueryHandler h,CancellationToken ct)=>
            Map(await h.PreviewReturnSourceAsync(goodsReceiptPublicId,c,ct),c))
            .WithName("PreviewPurchaseReturnSource");
    }

    private static PurchasingTradeLineInput Line(PurchasingTradeLineRequest x)=>
        new(x.Sequence,x.ProductPublicId,x.VariantPublicId,x.UomPublicId,x.Quantity,x.UnitPrice,x.LineDiscountPercent,x.TaxPercent);

    private static IResult Map<T>(Result<T> result,IExecutionContext context)=>
        result.IsFailure
            ? ApplicationErrorHttpMapper.ToResult(result.Error!,context.CorrelationId.Value)
            : Results.Ok(result.Value);

    private static IResult Created<T>(string basePath,Result<T> result,IExecutionContext context)=>
        result.IsFailure
            ? ApplicationErrorHttpMapper.ToResult(result.Error!,context.CorrelationId.Value)
            : Results.Created(basePath+"/"+ExtractPublicId(result.Value),result.Value);

    private static object ExtractPublicId<T>(T? value)=>value switch
    {
        PurchasingMutationReceipt x=>x.PublicId,
        _=>"created"
    };

    private static ApprovalDecisionKind? ParseDecision(string value)=>value.Trim().ToUpperInvariant() switch
    {
        "APPROVED"=>ApprovalDecisionKind.Approved,
        "REJECTED"=>ApprovalDecisionKind.Rejected,
        _=>null
    };

    private static string Key(HttpRequest request)=>request.Headers["Idempotency-Key"].ToString();

    private static IResult Invalid(string code,string message,IExecutionContext context)=>
        ApplicationErrorHttpMapper.ToResult(
            new ApplicationError(ErrorCategory.Validation,code,message),
            context.CorrelationId.Value);
}
