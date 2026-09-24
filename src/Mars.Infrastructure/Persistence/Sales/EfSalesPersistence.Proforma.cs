using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Application.Sales;
using Mars.Domain.Sales;
using Microsoft.EntityFrameworkCore;

namespace Mars.Infrastructure.Persistence.Sales;

public sealed partial class EfSalesPersistence : ISalesProformaPersistence
{
    public async Task<IReadOnlyList<SalesDocumentListItem>> ListAsync(Guid companyId, CancellationToken ct) =>
        await dbContext.Set<SalesProformaRecord>().AsNoTracking()
            .Where(x => x.CompanyId == companyId)
            .OrderByDescending(x => x.CreatedAt).ThenByDescending(x => x.Id)
            .Take(250)
            .Select(x => new SalesDocumentListItem(
                x.PublicId, x.Number, ProformaStateCode(x.State),
                x.CustomerCodeSnapshot, x.CustomerNameSnapshot, x.Version, x.CreatedAt))
            .ToArrayAsync(ct);

    public async Task<SalesDocumentDetailView?> GetAsync(Guid companyId, Guid publicId, CancellationToken ct)
    {
        var proforma = await dbContext.Set<SalesProformaRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x => x.CompanyId == companyId && x.PublicId == publicId, ct);
        if (proforma is null) return null;

        var lines = await dbContext.Set<SalesProformaLineRecord>().AsNoTracking()
            .Where(x => x.CompanyId == companyId && x.SalesProformaId == proforma.Id)
            .OrderBy(x => x.Sequence)
            .ToArrayAsync(ct);

        return new SalesDocumentDetailView(
            proforma.PublicId, proforma.Number, ProformaStateCode(proforma.State),
            proforma.CustomerCodeSnapshot, proforma.CustomerNameSnapshot,
            proforma.CurrencyCode, proforma.PaymentTerms, proforma.Version,
            lines.Select(x => new SalesDocumentLineView(
                x.PublicId, x.Sequence, x.ProductCodeSnapshot, x.ProductNameSnapshot,
                x.UomCodeSnapshot, x.Quantity, 0m, x.Quantity,
                x.UnitPrice, x.LineDiscountPercent, x.TaxPercent)).ToArray());
    }

    public Task<Result<SalesMutationReceipt>> CreateAsync(
        CreateSalesProformaCommand command,
        IExecutionContext context,
        CancellationToken ct) =>
        MutateAsync(
            "sales.proforma.create", command.OperationKey, "ProformaCreated", "SalesProforma",
            "sales.proforma.create.completed", context,
            async innerCt =>
            {
                if (string.IsNullOrWhiteSpace(command.Number) || command.SourceDocumentPublicId == Guid.Empty)
                    return Validation<SalesMutationReceipt>(
                        "sales.proforma.invalid",
                        "Proforma number and Quote/Order source identity are required.");

                return command.SourceMode switch
                {
                    SalesProformaSourceMode.Quote =>
                        await CreateFromQuoteAsync(command, context, innerCt),
                    SalesProformaSourceMode.Order =>
                        await CreateFromOrderAsync(command, context, innerCt),
                    _ => Validation<SalesMutationReceipt>(
                        "sales.proforma.source_mode",
                        "Proforma source must be Quote or Order.")
                };
            },
            ct);

    public Task<Result<SalesMutationReceipt>> CancelAsync(
        Guid publicId,
        long expectedVersion,
        string reason,
        string operationKey,
        IExecutionContext context,
        CancellationToken ct) =>
        MutateAsync(
            "sales.proforma.cancel", operationKey, "ProformaCancelled", "SalesProforma",
            "sales.proforma.cancel.completed", context,
            async innerCt =>
            {
                if (publicId == Guid.Empty || expectedVersion <= 0 || string.IsNullOrWhiteSpace(reason))
                    return Validation<SalesMutationReceipt>(
                        "sales.proforma.cancel.invalid",
                        "Proforma identity, positive version and cancellation reason are required.");

                var proforma = await dbContext.Set<SalesProformaRecord>()
                    .FromSqlInterpolated(
                        $"SELECT * FROM sales.proformas WHERE public_id = {publicId} AND company_id = {context.CompanyId} FOR UPDATE")
                    .SingleOrDefaultAsync(innerCt);
                if (proforma is null)
                    return NotFound<SalesMutationReceipt>("sales.proforma.not_found", "Proforma was not found.");
                if (proforma.Version != expectedVersion)
                    return Conflict<SalesMutationReceipt>("sales.proforma.stale", "Proforma version is stale.", true);
                if (proforma.State != SalesProformaState.Draft)
                    return Business<SalesMutationReceipt>("sales.proforma.state", "Only DRAFT Proforma can be cancelled.");

                proforma.State = SalesProformaState.Cancelled;
                proforma.CancelledAt = DateTimeOffset.UtcNow;
                proforma.Version++;
                return Result<SalesMutationReceipt>.Success(
                    new(proforma.PublicId, "CANCELLED", proforma.Version, context.CorrelationId.Value));
            },
            ct);

    private async Task<Result<SalesMutationReceipt>> CreateFromQuoteAsync(
        CreateSalesProformaCommand command,
        IExecutionContext context,
        CancellationToken ct)
    {
        var quote = await dbContext.Set<QuoteRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x => x.CompanyId == context.CompanyId &&
                                       x.PublicId == command.SourceDocumentPublicId, ct);
        if (quote is null)
            return NotFound<SalesMutationReceipt>("sales.proforma.quote_not_found", "Source Quote was not found.");

        var revision = await dbContext.Set<QuoteRevisionRecord>().AsNoTracking()
            .SingleAsync(x => x.CompanyId == context.CompanyId && x.QuoteId == quote.Id &&
                              x.RevisionNumber == quote.CurrentRevisionNumber, ct);
        var lines = await dbContext.Set<QuoteLineRecord>().AsNoTracking()
            .Where(x => x.CompanyId == context.CompanyId && x.RevisionId == revision.Id)
            .OrderBy(x => x.Sequence).ToArrayAsync(ct);

        return await AddProformaAsync(
            command, context, quote.CustomerPartyId, quote.CustomerCodeSnapshot,
            quote.CustomerNameSnapshot, quote.CurrencyCode, quote.PaymentTerms,
            revision.DocumentDiscountPercent, quote.CurrentRevisionNumber,
            lines.Select(x => new ProformaLineSnapshot(
                x.PublicId, x.Sequence, x.ProductId, x.VariantId, x.UomId,
                x.ConversionFactorSnapshot, x.ProductCodeSnapshot, x.ProductNameSnapshot,
                x.VariantCodeSnapshot, x.VariantNameSnapshot, x.UomCodeSnapshot,
                x.UomNameSnapshot, x.Quantity, x.UnitPrice, x.LineDiscountPercent, x.TaxPercent)).ToArray(),
            ct);
    }

    private async Task<Result<SalesMutationReceipt>> CreateFromOrderAsync(
        CreateSalesProformaCommand command,
        IExecutionContext context,
        CancellationToken ct)
    {
        var order = await dbContext.Set<SalesOrderRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x => x.CompanyId == context.CompanyId &&
                                       x.PublicId == command.SourceDocumentPublicId, ct);
        if (order is null)
            return NotFound<SalesMutationReceipt>("sales.proforma.order_not_found", "Source Sales Order was not found.");

        var version = await dbContext.Set<SalesOrderVersionRecord>().AsNoTracking()
            .SingleAsync(x => x.CompanyId == context.CompanyId && x.SalesOrderId == order.Id &&
                              x.VersionNumber == order.CurrentVersionNumber, ct);
        var lines = await dbContext.Set<SalesOrderLineRecord>().AsNoTracking()
            .Where(x => x.CompanyId == context.CompanyId && x.SalesOrderVersionId == version.Id)
            .OrderBy(x => x.Sequence).ToArrayAsync(ct);

        return await AddProformaAsync(
            command, context, order.CustomerPartyId, order.CustomerCodeSnapshot,
            order.CustomerNameSnapshot, order.CurrencyCode, order.PaymentTerms,
            version.DocumentDiscountPercent, order.CurrentVersionNumber,
            lines.Select(x => new ProformaLineSnapshot(
                x.LinePublicId, x.Sequence, x.ProductId, x.VariantId, x.UomId,
                x.ConversionFactorSnapshot, x.ProductCodeSnapshot, x.ProductNameSnapshot,
                x.VariantCodeSnapshot, x.VariantNameSnapshot, x.UomCodeSnapshot,
                x.UomNameSnapshot, x.Quantity, x.UnitPrice, x.LineDiscountPercent, x.TaxPercent)).ToArray(),
            ct);
    }

    private async Task<Result<SalesMutationReceipt>> AddProformaAsync(
        CreateSalesProformaCommand command,
        IExecutionContext context,
        long customerPartyId,
        string customerCode,
        string customerName,
        string currencyCode,
        string? paymentTerms,
        decimal documentDiscountPercent,
        long sourceVersion,
        IReadOnlyList<ProformaLineSnapshot> lines,
        CancellationToken ct)
    {
        if (lines.Count == 0)
            return Business<SalesMutationReceipt>("sales.proforma.source_empty", "Source document has no lines.");

        var now = DateTimeOffset.UtcNow;
        var proforma = new SalesProformaRecord
        {
            PublicId = Guid.NewGuid(),
            CompanyId = context.CompanyId,
            Number = command.Number.Trim(),
            CustomerPartyId = customerPartyId,
            CustomerCodeSnapshot = customerCode,
            CustomerNameSnapshot = customerName,
            CurrencyCode = currencyCode,
            PaymentTerms = paymentTerms,
            DocumentDiscountPercent = documentDiscountPercent,
            SourceMode = command.SourceMode,
            SourceDocumentPublicId = command.SourceDocumentPublicId,
            SourceVersion = sourceVersion,
            State = SalesProformaState.Draft,
            Version = 1,
            CreatorActorId = context.ActorId,
            CreatedAt = now
        };
        dbContext.Add(proforma);
        await dbContext.SaveChangesAsync(ct);

        foreach (var x in lines)
        {
            dbContext.Add(new SalesProformaLineRecord
            {
                PublicId = Guid.NewGuid(),
                SalesProformaId = proforma.Id,
                CompanyId = context.CompanyId,
                Sequence = x.Sequence,
                SourceLinePublicId = x.SourceLinePublicId,
                ProductId = x.ProductId,
                VariantId = x.VariantId,
                UomId = x.UomId,
                ConversionFactorSnapshot = x.ConversionFactorSnapshot,
                ProductCodeSnapshot = x.ProductCodeSnapshot,
                ProductNameSnapshot = x.ProductNameSnapshot,
                VariantCodeSnapshot = x.VariantCodeSnapshot,
                VariantNameSnapshot = x.VariantNameSnapshot,
                UomCodeSnapshot = x.UomCodeSnapshot,
                UomNameSnapshot = x.UomNameSnapshot,
                Quantity = x.Quantity,
                UnitPrice = x.UnitPrice,
                LineDiscountPercent = x.LineDiscountPercent,
                TaxPercent = x.TaxPercent
            });
        }

        return Result<SalesMutationReceipt>.Success(
            new(proforma.PublicId, "DRAFT", proforma.Version, context.CorrelationId.Value));
    }

    private sealed record ProformaLineSnapshot(
        Guid SourceLinePublicId,
        int Sequence,
        long ProductId,
        long? VariantId,
        long UomId,
        decimal ConversionFactorSnapshot,
        string ProductCodeSnapshot,
        string ProductNameSnapshot,
        string? VariantCodeSnapshot,
        string? VariantNameSnapshot,
        string UomCodeSnapshot,
        string UomNameSnapshot,
        decimal Quantity,
        decimal UnitPrice,
        decimal LineDiscountPercent,
        decimal TaxPercent);

    private static string ProformaStateCode(SalesProformaState state) => state switch
    {
        SalesProformaState.Draft => "DRAFT",
        SalesProformaState.Cancelled => "CANCELLED",
        _ => state.ToString().ToUpperInvariant()
    };
}
