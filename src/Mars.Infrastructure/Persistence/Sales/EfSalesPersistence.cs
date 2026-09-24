using System.Text.Json;
using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Foundation.Outbox;
using Mars.Application.Foundation.Results;
using Mars.Application.Sales;
using Mars.Domain.Inventory;
using Mars.Domain.Parties;
using Mars.Domain.Products;
using Mars.Domain.Sales;
using Mars.Infrastructure.Persistence.Foundation;
using Mars.Infrastructure.Persistence.Inventory;
using Mars.Infrastructure.Persistence.Parties;
using Mars.Infrastructure.Persistence.Products;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Storage;
using Npgsql;

namespace Mars.Infrastructure.Persistence.Sales;

public sealed partial class EfSalesPersistence(
    MarsDbContext dbContext,
    IAuditWriter auditWriter,
    IIdempotencyStore idempotencyStore,
    IOutboxWriter outboxWriter)
    : ISalesPersistence
{
    private sealed record ResolvedCustomer(
        long Id,
        Guid PublicId,
        string Code,
        string LegalName);

    private sealed record ResolvedTrade(
        long ProductId,
        long? VariantId,
        long UomId,
        decimal ConversionFactor,
        string ProductCode,
        string ProductName,
        string? VariantCode,
        string? VariantName,
        string UomCode,
        string UomName);

    public async Task<IReadOnlyList<SalesDocumentListItem>> ListQuotesAsync(Guid companyId, CancellationToken ct) =>
        await dbContext.Set<QuoteRecord>().AsNoTracking()
            .Where(x => x.CompanyId == companyId)
            .OrderByDescending(x => x.CreatedAt).ThenByDescending(x => x.Id)
            .Take(250)
            .Select(x => new SalesDocumentListItem(
                x.PublicId, x.Number, QuoteStateCode(x.State),
                x.CustomerCodeSnapshot, x.CustomerNameSnapshot, x.Version, x.CreatedAt))
            .ToArrayAsync(ct);

    public async Task<SalesDocumentDetailView?> GetQuoteAsync(Guid companyId, Guid publicId, CancellationToken ct)
    {
        var quote = await dbContext.Set<QuoteRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x => x.CompanyId == companyId && x.PublicId == publicId, ct);
        if (quote is null) return null;

        var revision = await dbContext.Set<QuoteRevisionRecord>().AsNoTracking()
            .SingleAsync(x => x.CompanyId == companyId && x.QuoteId == quote.Id &&
                              x.RevisionNumber == quote.CurrentRevisionNumber, ct);
        var lines = await dbContext.Set<QuoteLineRecord>().AsNoTracking()
            .Where(x => x.CompanyId == companyId && x.RevisionId == revision.Id)
            .OrderBy(x => x.Sequence).ToArrayAsync(ct);
        var lineIds = lines.Select(x => x.PublicId).ToArray();
        var converted = lineIds.Length == 0
            ? Array.Empty<(Guid Line, decimal Qty)>()
            : await dbContext.Set<QuoteConversionLinkRecord>().AsNoTracking()
                .Where(x => x.CompanyId == companyId && x.QuoteRevisionId == revision.Id &&
                            lineIds.Contains(x.QuoteLinePublicId))
                .GroupBy(x => x.QuoteLinePublicId)
                .Select(g => new ValueTuple<Guid, decimal>(g.Key, g.Sum(x => x.Quantity)))
                .ToArrayAsync(ct);

        var processed = converted.ToDictionary(x => x.Item1, x => x.Item2);
        return new SalesDocumentDetailView(
            quote.PublicId, quote.Number, QuoteStateCode(quote.State),
            quote.CustomerCodeSnapshot, quote.CustomerNameSnapshot,
            quote.CurrencyCode, quote.PaymentTerms, quote.Version,
            lines.Select(x =>
            {
                var done = processed.GetValueOrDefault(x.PublicId);
                return new SalesDocumentLineView(
                    x.PublicId, x.Sequence, x.ProductCodeSnapshot, x.ProductNameSnapshot,
                    x.UomCodeSnapshot, x.Quantity, done, x.Quantity - done,
                    x.UnitPrice, x.LineDiscountPercent, x.TaxPercent);
            }).ToArray());
    }

    public async Task<IReadOnlyList<SalesDocumentListItem>> ListOrdersAsync(Guid companyId, CancellationToken ct) =>
        await dbContext.Set<SalesOrderRecord>().AsNoTracking()
            .Where(x => x.CompanyId == companyId)
            .OrderByDescending(x => x.CreatedAt).ThenByDescending(x => x.Id)
            .Take(250)
            .Select(x => new SalesDocumentListItem(
                x.PublicId, x.Number, OrderStateCode(x.State),
                x.CustomerCodeSnapshot, x.CustomerNameSnapshot, x.Version, x.CreatedAt))
            .ToArrayAsync(ct);

    public async Task<SalesDocumentDetailView?> GetOrderAsync(Guid companyId, Guid publicId, CancellationToken ct)
    {
        var order = await dbContext.Set<SalesOrderRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x => x.CompanyId == companyId && x.PublicId == publicId, ct);
        if (order is null) return null;

        var version = await dbContext.Set<SalesOrderVersionRecord>().AsNoTracking()
            .SingleAsync(x => x.CompanyId == companyId && x.SalesOrderId == order.Id &&
                              x.VersionNumber == order.CurrentVersionNumber, ct);
        var lines = await dbContext.Set<SalesOrderLineRecord>().AsNoTracking()
            .Where(x => x.CompanyId == companyId && x.SalesOrderVersionId == version.Id)
            .OrderBy(x => x.Sequence).ToArrayAsync(ct);
        var lineIds = lines.Select(x => x.LinePublicId).Distinct().ToArray();
        var shipped = await GetNetDispatchedByOrderLineAsync(companyId, order.Id, lineIds, ct);

        return new SalesDocumentDetailView(
            order.PublicId, order.Number, OrderStateCode(order.State),
            order.CustomerCodeSnapshot, order.CustomerNameSnapshot,
            order.CurrencyCode, order.PaymentTerms, order.Version,
            lines.Select(x =>
            {
                var done = shipped.GetValueOrDefault(x.LinePublicId);
                return new SalesDocumentLineView(
                    x.LinePublicId, x.Sequence, x.ProductCodeSnapshot, x.ProductNameSnapshot,
                    x.UomCodeSnapshot, x.Quantity, done, x.Quantity - done,
                    x.UnitPrice, x.LineDiscountPercent, x.TaxPercent);
            }).ToArray());
    }

    public async Task<IReadOnlyList<SalesDocumentListItem>> ListDispatchesAsync(Guid companyId, CancellationToken ct) =>
        await (
            from d in dbContext.Set<DispatchRecord>().AsNoTracking()
            join o in dbContext.Set<SalesOrderRecord>().AsNoTracking() on d.SalesOrderId equals o.Id
            where d.CompanyId == companyId && o.CompanyId == companyId
            orderby d.CreatedAt descending, d.Id descending
            select new SalesDocumentListItem(
                d.PublicId, d.Number, DispatchStateCode(d.State),
                o.CustomerCodeSnapshot, o.CustomerNameSnapshot, d.Version, d.CreatedAt))
            .Take(250).ToArrayAsync(ct);

    public async Task<SalesDocumentDetailView?> GetDispatchAsync(Guid companyId, Guid publicId, CancellationToken ct)
    {
        var dispatch = await dbContext.Set<DispatchRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x => x.CompanyId == companyId && x.PublicId == publicId, ct);
        if (dispatch is null) return null;
        var order = await dbContext.Set<SalesOrderRecord>().AsNoTracking()
            .SingleAsync(x => x.Id == dispatch.SalesOrderId && x.CompanyId == companyId, ct);
        var version = await dbContext.Set<SalesOrderVersionRecord>().AsNoTracking()
            .SingleAsync(x => x.SalesOrderId == order.Id && x.CompanyId == companyId &&
                              x.VersionNumber == dispatch.SalesOrderVersionNumber, ct);
        var orderLines = await dbContext.Set<SalesOrderLineRecord>().AsNoTracking()
            .Where(x => x.CompanyId == companyId && x.SalesOrderVersionId == version.Id)
            .ToDictionaryAsync(x => x.LinePublicId, ct);
        var lines = await dbContext.Set<DispatchLineRecord>().AsNoTracking()
            .Where(x => x.CompanyId == companyId && x.DispatchId == dispatch.Id)
            .OrderBy(x => x.Sequence).ToArrayAsync(ct);

        return new SalesDocumentDetailView(
            dispatch.PublicId, dispatch.Number, DispatchStateCode(dispatch.State),
            order.CustomerCodeSnapshot, order.CustomerNameSnapshot,
            order.CurrencyCode, order.PaymentTerms, dispatch.Version,
            lines.Select(x =>
            {
                var source = orderLines[x.SalesOrderLinePublicId];
                return new SalesDocumentLineView(
                    x.PublicId, x.Sequence, source.ProductCodeSnapshot, source.ProductNameSnapshot,
                    source.UomCodeSnapshot, x.Quantity,
                    dispatch.State is DispatchState.Posted or DispatchState.HandedOver or DispatchState.Delivered ? x.Quantity : 0m,
                    dispatch.State is DispatchState.Posted or DispatchState.HandedOver or DispatchState.Delivered ? 0m : x.Quantity,
                    source.UnitPrice, source.LineDiscountPercent, source.TaxPercent);
            }).ToArray());
    }

    public async Task<IReadOnlyList<SalesDocumentListItem>> ListInvoicesAsync(Guid companyId, CancellationToken ct) =>
        await dbContext.Set<SalesInvoiceRecord>().AsNoTracking()
            .Where(x => x.CompanyId == companyId)
            .OrderByDescending(x => x.CreatedAt).ThenByDescending(x => x.Id)
            .Take(250)
            .Select(x => new SalesDocumentListItem(
                x.PublicId, x.Number, InvoiceStateCode(x.State),
                x.CustomerCodeSnapshot, x.CustomerLegalNameSnapshot, x.Version, x.CreatedAt))
            .ToArrayAsync(ct);

    public async Task<SalesInvoiceDraftView?> GetInvoiceAsync(Guid companyId, Guid publicId, CancellationToken ct)
    {
        var invoice = await dbContext.Set<SalesInvoiceRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x => x.CompanyId == companyId && x.PublicId == publicId, ct);
        if (invoice is null) return null;
        var lines = await dbContext.Set<SalesInvoiceLineRecord>().AsNoTracking()
            .Where(x => x.CompanyId == companyId && x.SalesInvoiceId == invoice.Id)
            .OrderBy(x => x.Sequence).ToArrayAsync(ct);

        return new SalesInvoiceDraftView(
            invoice.PublicId, invoice.Number, InvoiceStateCode(invoice.State),
            invoice.CustomerCodeSnapshot, invoice.CustomerLegalNameSnapshot,
            invoice.CurrencyCode, invoice.DocumentDate, invoice.DueDate,
            invoice.NetTotal, invoice.TaxTotal, invoice.GrossTotal, invoice.Version,
            lines.Select(x => new SalesDocumentLineView(
                x.PublicId, x.Sequence, x.ProductCodeSnapshot, x.ProductNameSnapshot,
                x.UomCodeSnapshot, x.Quantity, 0m, x.Quantity,
                x.UnitPrice, x.LineDiscountPercent, x.TaxPercent)).ToArray());
    }

    public async Task<IReadOnlyList<InvoiceSourceEligibilityView>> PreviewInvoiceSourceAsync(
        Guid companyId, SalesInvoiceSourceMode mode, Guid sourceDocumentPublicId, CancellationToken ct)
    {
        if (mode == SalesInvoiceSourceMode.Order)
        {
            var order = await dbContext.Set<SalesOrderRecord>().AsNoTracking()
                .SingleOrDefaultAsync(x => x.CompanyId == companyId && x.PublicId == sourceDocumentPublicId, ct);
            if (order is null) return Array.Empty<InvoiceSourceEligibilityView>();
            var version = await dbContext.Set<SalesOrderVersionRecord>().AsNoTracking()
                .SingleAsync(x => x.CompanyId == companyId && x.SalesOrderId == order.Id &&
                                  x.VersionNumber == order.CurrentVersionNumber, ct);
            var lines = await dbContext.Set<SalesOrderLineRecord>().AsNoTracking()
                .Where(x => x.CompanyId == companyId && x.SalesOrderVersionId == version.Id)
                .ToArrayAsync(ct);
            return await BuildInvoiceEligibilityAsync(companyId, mode, sourceDocumentPublicId,
                lines.Select(x => (x.LinePublicId, x.Quantity)).ToArray(), ct);
        }

        if (mode == SalesInvoiceSourceMode.Dispatch)
        {
            var dispatch = await dbContext.Set<DispatchRecord>().AsNoTracking()
                .SingleOrDefaultAsync(x => x.CompanyId == companyId && x.PublicId == sourceDocumentPublicId &&
                    (x.State == DispatchState.Posted || x.State == DispatchState.HandedOver || x.State == DispatchState.Delivered), ct);
            if (dispatch is null) return Array.Empty<InvoiceSourceEligibilityView>();
            var lines = await dbContext.Set<DispatchLineRecord>().AsNoTracking()
                .Where(x => x.CompanyId == companyId && x.DispatchId == dispatch.Id).ToArrayAsync(ct);
            return await BuildInvoiceEligibilityAsync(companyId, mode, sourceDocumentPublicId,
                lines.Select(x => (x.PublicId, x.Quantity)).ToArray(), ct);
        }

        return Array.Empty<InvoiceSourceEligibilityView>();
    }

    private async Task<IReadOnlyList<InvoiceSourceEligibilityView>> BuildInvoiceEligibilityAsync(
        Guid companyId, SalesInvoiceSourceMode mode, Guid sourceDocumentPublicId,
        IReadOnlyList<(Guid LineId, decimal Quantity)> sourceLines, CancellationToken ct)
    {
        var ids = sourceLines.Select(x => x.LineId).ToArray();
        var drafted = ids.Length == 0
            ? Array.Empty<(Guid Line, decimal Qty)>()
            : await (
                from link in dbContext.Set<SalesInvoiceSourceLinkRecord>().AsNoTracking()
                join invoiceLine in dbContext.Set<SalesInvoiceLineRecord>().AsNoTracking()
                    on link.SalesInvoiceLineId equals invoiceLine.Id
                join invoice in dbContext.Set<SalesInvoiceRecord>().AsNoTracking()
                    on invoiceLine.SalesInvoiceId equals invoice.Id
                where link.CompanyId == companyId && invoice.CompanyId == companyId &&
                      invoice.State == SalesInvoiceState.Draft &&
                      link.SourceMode == mode &&
                      link.SourceDocumentPublicId == sourceDocumentPublicId &&
                      link.SourceLinePublicId.HasValue &&
                      ids.Contains(link.SourceLinePublicId.Value)
                group link by link.SourceLinePublicId!.Value into g
                select new ValueTuple<Guid, decimal>(g.Key, g.Sum(x => x.Quantity)))
                .ToArrayAsync(ct);
        var byLine = drafted.ToDictionary(x => x.Item1, x => x.Item2);
        return sourceLines.Select(x =>
        {
            var used = byLine.GetValueOrDefault(x.LineId);
            return new InvoiceSourceEligibilityView(mode, sourceDocumentPublicId, x.LineId,
                x.Quantity, used, Math.Max(0m, x.Quantity - used));
        }).ToArray();
    }

    private async Task<ResolvedCustomer?> ResolveCustomerAsync(Guid companyId, Guid publicId, CancellationToken ct)
    {
        var party = await dbContext.Set<PartyRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x => x.CompanyId == companyId && x.PublicId == publicId &&
                                      x.State == PartyState.Active, ct);
        if (party is null) return null;
        var isCustomer = await dbContext.Set<PartyRoleRecord>().AsNoTracking()
            .AnyAsync(x => x.PartyId == party.Id && x.RoleType == PartyRoleType.Customer &&
                           x.State == PartyRoleState.Active, ct);
        return isCustomer ? new ResolvedCustomer(party.Id, party.PublicId, party.PartyCode, party.LegalName) : null;
    }

    private async Task<Result<ResolvedTrade>> ResolveTradeAsync(
        Guid companyId, SalesTradeLineInput line, CancellationToken ct) =>
        await ResolveTradeAsync(companyId, line.ProductPublicId, line.VariantPublicId,
            line.UomPublicId, ct);

    private async Task<Result<ResolvedTrade>> ResolveTradeAsync(
        Guid companyId, Guid productPublicId, Guid? variantPublicId, Guid uomPublicId, CancellationToken ct)
    {
        var product = await dbContext.Set<ProductRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x => x.CompanyId == companyId && x.PublicId == productPublicId, ct);
        if (product is null || product.State != ProductState.Active || !product.Sellable)
            return Business<ResolvedTrade>("sales.product.not_sellable", "Product must be ACTIVE and sellable.");

        ProductVariantRecord? variant = null;
        if (variantPublicId.HasValue)
        {
            variant = await dbContext.Set<ProductVariantRecord>().AsNoTracking()
                .SingleOrDefaultAsync(x => x.CompanyId == companyId && x.PublicId == variantPublicId.Value &&
                                          x.ProductId == product.Id, ct);
            if (variant is null || variant.State != ProductMasterRecordState.Active)
                return Business<ResolvedTrade>("sales.variant.not_active", "Variant must belong to the Product and be ACTIVE.");
        }

        var uom = await dbContext.Set<UnitOfMeasureRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x => x.CompanyId == companyId && x.PublicId == uomPublicId &&
                                      x.State == ProductMasterRecordState.Active, ct);
        if (uom is null)
            return Business<ResolvedTrade>("sales.uom.not_active", "UOM must be ACTIVE.");

        var productUom = await dbContext.Set<ProductUomRecord>().AsNoTracking()
            .Where(x => x.CompanyId == companyId && x.ProductId == product.Id &&
                        x.UomId == uom.Id && x.State == ProductMasterRecordState.Active &&
                        x.VariantId == (variant == null ? null : variant.Id))
            .OrderByDescending(x => x.Id)
            .FirstOrDefaultAsync(ct);
        if (productUom is null)
            return Business<ResolvedTrade>("sales.uom.not_allowed", "The selected UOM is not active for this Product/Variant.");

        return Result<ResolvedTrade>.Success(new ResolvedTrade(
            product.Id, variant?.Id, uom.Id, productUom.ConversionFactor,
            product.ProductCode, product.Name, variant?.VariantCode, variant?.Name, uom.Code, uom.Name));
    }

    private async Task<Dictionary<Guid, decimal>> GetNetDispatchedByOrderLineAsync(
        Guid companyId, long salesOrderId, Guid[] lineIds, CancellationToken ct)
    {
        if (lineIds.Length == 0) return new();
        var rows = await (
            from line in dbContext.Set<DispatchLineRecord>().AsNoTracking()
            join dispatch in dbContext.Set<DispatchRecord>().AsNoTracking() on line.DispatchId equals dispatch.Id
            where line.CompanyId == companyId && dispatch.CompanyId == companyId &&
                  dispatch.SalesOrderId == salesOrderId && lineIds.Contains(line.SalesOrderLinePublicId) &&
                  (dispatch.State == DispatchState.Posted || dispatch.State == DispatchState.HandedOver ||
                   dispatch.State == DispatchState.Delivered || dispatch.State == DispatchState.Reversed)
            group new { line, dispatch } by line.SalesOrderLinePublicId into g
            select new ValueTuple<Guid, decimal>(
                g.Key,
                g.Sum(x => x.dispatch.ReversalOfDispatchId == null ? x.line.Quantity : -x.line.Quantity)))
            .ToArrayAsync(ct);
        return rows.ToDictionary(x => x.Item1, x => x.Item2);
    }

    private async Task<Result<SalesMutationReceipt>> MutateAsync(
        string scope, string operationKey, string action, string entityType,
        string resultCode, IExecutionContext context,
        Func<CancellationToken, Task<Result<SalesMutationReceipt>>> mutation,
        CancellationToken ct)
    {
        if (string.IsNullOrWhiteSpace(operationKey))
            return Validation<SalesMutationReceipt>("sales.operation_key.required", "Idempotency operation key is required.");

        var ownsTransaction = dbContext.Database.CurrentTransaction is null;
        IDbContextTransaction? tx = null;
        if (ownsTransaction) tx = await dbContext.Database.BeginTransactionAsync(ct);

        try
        {
            var result = await mutation(ct);
            if (result.IsFailure)
            {
                if (tx is not null) await tx.RollbackAsync(ct);
                dbContext.ChangeTracker.Clear();
                return result;
            }

            var now = DateTimeOffset.UtcNow;
            idempotencyStore.Add(new IdempotencyOperation(scope, operationKey, null, now));
            auditWriter.Append(new AuditEntry(
                context.ActorId, context.CompanyId, context.BranchId, context.CorrelationId.Value,
                "Sales", action, entityType, result.Value!.PublicId, null, now));
            outboxWriter.Enqueue(new OutboxMessage(
                Guid.NewGuid(), $"Sales.{action}", "Sales", result.Value.PublicId, 1,
                JsonSerializer.Serialize(new {
                    entityType,
                    entityPublicId = result.Value.PublicId,
                    state = result.Value.State,
                    version = result.Value.Version
                }), now, now));

            await dbContext.SaveChangesAsync(ct);
            if (!await idempotencyStore.MarkSucceededAsync(scope, operationKey, resultCode, now, ct))
                throw new InvalidOperationException("Sales idempotency state could not be completed.");

            if (tx is not null) await tx.CommitAsync(ct);
            return result;
        }
        catch (DbUpdateConcurrencyException)
        {
            if (tx is not null) await tx.RollbackAsync(ct);
            dbContext.ChangeTracker.Clear();
            return Conflict<SalesMutationReceipt>("sales.concurrency.stale", "The Sales document was changed by another operation.", true);
        }
        catch (DbUpdateException ex) when (IsConstraint(ex, "ux_idempotency_scope_key"))
        {
            if (tx is not null) await tx.RollbackAsync(ct);
            dbContext.ChangeTracker.Clear();
            return Conflict<SalesMutationReceipt>("sales.operation.duplicate", "The operation key was already used.");
        }
        catch (DbUpdateException ex) when (IsUniqueViolation(ex))
        {
            if (tx is not null) await tx.RollbackAsync(ct);
            dbContext.ChangeTracker.Clear();
            return Conflict<SalesMutationReceipt>("sales.unique.conflict", "A Sales document or source identity conflicts with an existing record.");
        }
        finally
        {
            if (tx is not null) await tx.DisposeAsync();
        }
    }

    private static Result<T> Validation<T>(string code, string message) =>
        Result<T>.Failure(new ApplicationError(ErrorCategory.Validation, code, message));
    private static Result<T> NotFound<T>(string code, string message) =>
        Result<T>.Failure(new ApplicationError(ErrorCategory.NotFound, code, message));
    private static Result<T> Business<T>(string code, string message) =>
        Result<T>.Failure(new ApplicationError(ErrorCategory.BusinessRule, code, message));
    private static Result<T> Conflict<T>(string code, string message, bool concurrency = false) =>
        Result<T>.Failure(new ApplicationError(concurrency ? ErrorCategory.Concurrency : ErrorCategory.Conflict, code, message));

    private static bool IsConstraint(DbUpdateException ex, string name) =>
        ex.InnerException is PostgresException pg &&
        pg.SqlState == PostgresErrorCodes.UniqueViolation &&
        string.Equals(pg.ConstraintName, name, StringComparison.Ordinal);
    private static bool IsUniqueViolation(DbUpdateException ex) =>
        ex.InnerException is PostgresException pg && pg.SqlState == PostgresErrorCodes.UniqueViolation;

    private static string QuoteStateCode(QuoteState state) => state switch
    {
        QuoteState.Draft => "DRAFT",
        QuoteState.PendingInternalApproval => "PENDING_INTERNAL_APPROVAL",
        QuoteState.CustomerReview => "CUSTOMER_REVIEW",
        QuoteState.Accepted => "ACCEPTED",
        QuoteState.PartiallyConverted => "PARTIALLY_CONVERTED",
        QuoteState.Converted => "CONVERTED",
        QuoteState.Superseded => "SUPERSEDED",
        QuoteState.Expired => "EXPIRED",
        _ => "CANCELLED"
    };

    private static string OrderStateCode(SalesOrderState state) => state switch
    {
        SalesOrderState.Draft => "DRAFT",
        SalesOrderState.PendingApproval => "PENDING_APPROVAL",
        SalesOrderState.Confirmed => "CONFIRMED",
        SalesOrderState.OnHold => "ON_HOLD",
        SalesOrderState.PartiallyCompleted => "PARTIALLY_COMPLETED",
        SalesOrderState.Completed => "COMPLETED",
        SalesOrderState.CancelledRemainder => "CANCELLED_REMAINDER",
        _ => "CANCELLED"
    };

    private static string DispatchStateCode(DispatchState state) => state switch
    {
        DispatchState.Draft => "DRAFT",
        DispatchState.Ready => "READY",
        DispatchState.Posted => "POSTED",
        DispatchState.HandedOver => "HANDED_OVER",
        DispatchState.Delivered => "DELIVERED",
        DispatchState.Reversed => "REVERSED",
        _ => "CANCELLED"
    };

    private static string InvoiceStateCode(SalesInvoiceState state) =>
        state == SalesInvoiceState.Draft ? "DRAFT" : "CANCELLED";
}

public sealed class EfSalesTransactionCoordinator(MarsDbContext dbContext) : ISalesTransactionCoordinator
{
    public async Task<Result<T>> ExecuteAsync<T>(
        Func<CancellationToken, Task<Result<T>>> operation,
        CancellationToken cancellationToken)
    {
        if (dbContext.Database.CurrentTransaction is not null)
            return await operation(cancellationToken);

        await using var tx = await dbContext.Database.BeginTransactionAsync(cancellationToken);
        try
        {
            var result = await operation(cancellationToken);
            if (result.IsFailure)
            {
                await tx.RollbackAsync(cancellationToken);
                dbContext.ChangeTracker.Clear();
                return result;
            }

            await tx.CommitAsync(cancellationToken);
            return result;
        }
        catch
        {
            await tx.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            throw;
        }
    }
}
