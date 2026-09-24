using Mars.Api.Foundation.Errors;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Application.Sales;

namespace Mars.Api.Sales;

public static class SalesProformaEndpoints
{
    public static void MapSalesProformaEndpoints(this WebApplication app)
    {
        var proformas = app.MapGroup("/api/v1/sales/proformas").RequireAuthorization();

        proformas.MapGet("", async (
                IExecutionContext context,
                SalesProformaQueryHandler handler,
                CancellationToken ct) =>
            Map(await handler.ListAsync(context, ct), context))
            .WithName("ListSalesProformas");

        proformas.MapGet("/{id:guid}", async (
                Guid id,
                IExecutionContext context,
                SalesProformaQueryHandler handler,
                CancellationToken ct) =>
            Map(await handler.GetAsync(id, context, ct), context))
            .WithName("GetSalesProforma");

        proformas.MapGet("/{id:guid}/export", async (
                Guid id,
                IExecutionContext context,
                SalesProformaQueryHandler handler,
                CancellationToken ct) =>
            Map(await handler.GetAsync(id, context, ct, export: true), context))
            .WithName("ExportSalesProforma");

        proformas.MapPost("", async (
                CreateSalesProformaRequest request,
                HttpRequest http,
                IExecutionContext context,
                SalesProformaCommandHandler handler,
                CancellationToken ct) =>
            Created(await handler.CreateAsync(
                new CreateSalesProformaCommand(
                    request.Number,
                    request.SourceMode,
                    request.SourceDocumentPublicId,
                    Key(http)),
                context,
                ct), context))
            .WithName("CreateSalesProforma");

        proformas.MapPost("/{id:guid}/cancel", async (
                Guid id,
                CancelSalesProformaRequest request,
                HttpRequest http,
                IExecutionContext context,
                SalesProformaCommandHandler handler,
                CancellationToken ct) =>
            Map(await handler.CancelAsync(
                id,
                request.Version,
                request.Reason,
                Key(http),
                context,
                ct), context))
            .WithName("CancelSalesProforma");
    }

    private static IResult Map<T>(Result<T> result, IExecutionContext context) =>
        result.IsFailure
            ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
            : Results.Ok(result.Value);

    private static IResult Created(Result<SalesMutationReceipt> result, IExecutionContext context) =>
        result.IsFailure
            ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
            : Results.Created($"/api/v1/sales/proformas/{result.Value!.PublicId:D}", result.Value);

    private static string Key(HttpRequest request) =>
        request.Headers["Idempotency-Key"].ToString();
}
