using Mars.Api.Foundation.Errors;
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

        purchasing.MapPost("/receipts/{id:guid}/post",async(Guid id,HttpRequest http,IExecutionContext c,PurchasingCommandHandler h,CancellationToken ct)=>
            Map(await h.PostReceiptAsync(id,Key(http),c,ct),c))
            .WithName("PostGoodsReceipt");

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
                    Key(http)),c,ct),c))
            .WithName("CreateSupplierInvoiceDraft");

        purchasing.MapGet("/match-preview",async(
            SupplierInvoiceSourceMode mode,Guid sourceDocumentPublicId,IExecutionContext c,PurchasingQueryHandler h,CancellationToken ct)=>
            Map(await h.PreviewMatchAsync(mode,sourceDocumentPublicId,c,ct),c))
            .WithName("PreviewPurchaseMatch");
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

    private static string Key(HttpRequest request)=>request.Headers["Idempotency-Key"].ToString();
}
