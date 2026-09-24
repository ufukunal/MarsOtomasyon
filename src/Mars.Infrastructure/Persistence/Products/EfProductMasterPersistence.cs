using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Products.ProductMaster;
using Mars.Domain.Products;
using Microsoft.EntityFrameworkCore;
using Npgsql;

namespace Mars.Infrastructure.Persistence.Products;

public sealed class EfProductMasterPersistence(
    MarsDbContext dbContext,
    IAuditWriter auditWriter,
    IIdempotencyStore idempotencyStore)
    : IProductMasterReadPersistence, IProductMasterMutationPersistence
{
    public async Task<IReadOnlyList<ProductListItem>> ListProductsAsync(
        Guid companyId, string? search, CancellationToken cancellationToken)
    {
        var query = dbContext.Set<ProductRecord>().AsNoTracking().Where(x => x.CompanyId == companyId);
        if (!string.IsNullOrWhiteSpace(search))
        {
            var term = search.Trim();
            query = query.Where(p =>
                p.ProductCode.Contains(term) ||
                p.Name.Contains(term) ||
                dbContext.Set<ProductVariantRecord>().Any(v =>
                    v.ProductId == p.Id &&
                    ((v.VariantCode != null && v.VariantCode.Contains(term)) || v.Name.Contains(term))) ||
                dbContext.Set<ProductBarcodeRecord>().Any(b => b.ProductId == p.Id && b.Value.Contains(term)) ||
                (from link in dbContext.Set<ProductCategoryLinkRecord>()
                 join category in dbContext.Set<ProductCategoryRecord>() on link.CategoryId equals category.Id
                 where link.ProductId == p.Id &&
                       (category.Code.Contains(term) || category.Name.Contains(term))
                 select link.Id).Any());
        }

        var rows = await query.OrderBy(x => x.ProductCode).Take(250).ToArrayAsync(cancellationToken);
        var ids = rows.Select(x => x.Id).ToArray();

        var baseRows = await (
            from assignment in dbContext.Set<ProductUomRecord>().AsNoTracking()
            join uom in dbContext.Set<UnitOfMeasureRecord>().AsNoTracking() on assignment.UomId equals uom.Id
            where ids.Contains(assignment.ProductId) &&
                  assignment.Role == ProductUomRole.Base &&
                  assignment.State == ProductMasterRecordState.Active
            select new { assignment.ProductId, uom.Code }).ToArrayAsync(cancellationToken);
        var baseUoms = baseRows.ToDictionary(x => x.ProductId, x => x.Code);

        var primaryRows = await (
            from link in dbContext.Set<ProductCategoryLinkRecord>().AsNoTracking()
            join category in dbContext.Set<ProductCategoryRecord>().AsNoTracking() on link.CategoryId equals category.Id
            where ids.Contains(link.ProductId) && link.IsPrimary
            select new { link.ProductId, category.Name }).ToArrayAsync(cancellationToken);
        var primary = primaryRows.ToDictionary(x => x.ProductId, x => x.Name);

        return rows.Select(x => new ProductListItem(
            x.PublicId,
            x.ProductCode,
            x.Name,
            KindCode(x.Kind),
            x.Sellable,
            x.Purchasable,
            x.Stockable,
            TrackingCode(x.TrackingStrategy),
            baseUoms.GetValueOrDefault(x.Id) ?? string.Empty,
            primary.GetValueOrDefault(x.Id),
            StateCode(x.State),
            x.Version)).ToArray();
    }

    public async Task<ProductDetailView?> GetProductAsync(
        Guid companyId, Guid productPublicId, ProductDetailReadOptions options, CancellationToken cancellationToken)
    {
        var product = await dbContext.Set<ProductRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x => x.CompanyId == companyId && x.PublicId == productPublicId, cancellationToken);
        if (product is null) return null;

        IReadOnlyList<ProductVariantView> variants = Array.Empty<ProductVariantView>();
        if (options.IncludeVariants)
        {
            var rows = await dbContext.Set<ProductVariantRecord>().AsNoTracking()
                .Where(x => x.ProductId == product.Id)
                .OrderBy(x => x.Name)
                .ToArrayAsync(cancellationToken);
            variants = rows.Select(x => new ProductVariantView(
                x.PublicId, x.VariantCode, x.Name,
                x.TrackingStrategy.HasValue ? TrackingCode(x.TrackingStrategy.Value) : null,
                MasterStateCode(x.State), x.Version)).ToArray();
        }

        IReadOnlyList<ProductUomView> uoms = Array.Empty<ProductUomView>();
        if (options.IncludeUoms)
        {
            var rows = await (
                from a in dbContext.Set<ProductUomRecord>().AsNoTracking()
                join u in dbContext.Set<UnitOfMeasureRecord>().AsNoTracking() on a.UomId equals u.Id
                join v0 in dbContext.Set<ProductVariantRecord>().AsNoTracking()
                    on a.VariantId equals (long?)v0.Id into vg
                from v in vg.DefaultIfEmpty()
                where a.ProductId == product.Id
                orderby a.Role, a.Id
                select new
                {
                    a.PublicId,
                    UomPublicId = u.PublicId,
                    UomCode = u.Code,
                    UomName = u.Name,
                    VariantPublicId = v == null ? (Guid?)null : v.PublicId,
                    a.Role,
                    a.ConversionFactor,
                    a.State,
                    a.Version
                }).ToArrayAsync(cancellationToken);
            uoms = rows.Select(x => new ProductUomView(
                x.PublicId, x.UomPublicId, x.UomCode, x.UomName, x.VariantPublicId,
                x.Role == ProductUomRole.Base ? "BASE" : "ALTERNATE",
                x.ConversionFactor, MasterStateCode(x.State), x.Version)).ToArray();
        }

        IReadOnlyList<ProductBarcodeView> barcodes = Array.Empty<ProductBarcodeView>();
        if (options.IncludeBarcodes)
        {
            var rows = await (
                from b in dbContext.Set<ProductBarcodeRecord>().AsNoTracking()
                join v0 in dbContext.Set<ProductVariantRecord>().AsNoTracking()
                    on b.VariantId equals (long?)v0.Id into vg
                from v in vg.DefaultIfEmpty()
                join u0 in dbContext.Set<ProductUomRecord>().AsNoTracking()
                    on b.ProductUomId equals (long?)u0.Id into ug
                from u in ug.DefaultIfEmpty()
                where b.ProductId == product.Id
                orderby b.Namespace, b.Value
                select new
                {
                    b.PublicId,
                    b.Namespace,
                    b.Value,
                    VariantPublicId = v == null ? (Guid?)null : v.PublicId,
                    ProductUomPublicId = u == null ? (Guid?)null : u.PublicId,
                    b.State,
                    b.Version
                }).ToArrayAsync(cancellationToken);
            barcodes = rows.Select(x => new ProductBarcodeView(
                x.PublicId, x.Namespace, x.Value, x.VariantPublicId, x.ProductUomPublicId,
                MasterStateCode(x.State), x.Version)).ToArray();
        }

        IReadOnlyList<ProductCategoryView> categories = Array.Empty<ProductCategoryView>();
        if (options.IncludeCategories)
        {
            var rows = await (
                from link in dbContext.Set<ProductCategoryLinkRecord>().AsNoTracking()
                join c in dbContext.Set<ProductCategoryRecord>().AsNoTracking() on link.CategoryId equals c.Id
                join p0 in dbContext.Set<ProductCategoryRecord>().AsNoTracking()
                    on c.ParentCategoryId equals (long?)p0.Id into pg
                from parent in pg.DefaultIfEmpty()
                where link.ProductId == product.Id
                orderby link.IsPrimary descending, c.Name
                select new ProductCategoryView(
                    c.PublicId, c.Code, c.Name,
                    parent == null ? null : parent.PublicId,
                    link.IsPrimary, c.Version)).ToArrayAsync(cancellationToken);
            categories = rows;
        }

        var mappingRows = await (
            from m in dbContext.Set<ProductExternalMappingRecord>().AsNoTracking()
            join v0 in dbContext.Set<ProductVariantRecord>().AsNoTracking()
                on m.VariantId equals (long?)v0.Id into vg
            from v in vg.DefaultIfEmpty()
            where m.ProductId == product.Id
            orderby m.SystemCode, m.ExternalIdentity
            select new
            {
                m.PublicId,
                m.SystemCode,
                m.AccountScope,
                m.ExternalIdentity,
                VariantPublicId = v == null ? (Guid?)null : v.PublicId,
                m.State,
                m.Version
            }).ToArrayAsync(cancellationToken);
        var mappings = mappingRows.Select(x => new ProductExternalMappingView(
            x.PublicId, x.SystemCode,
            string.IsNullOrEmpty(x.AccountScope) ? null : x.AccountScope,
            x.ExternalIdentity, x.VariantPublicId, MasterStateCode(x.State), x.Version)).ToArray();

        return new ProductDetailView(
            product.PublicId, product.ProductCode, product.Name, product.Description,
            KindCode(product.Kind), product.Sellable, product.Purchasable, product.Stockable,
            TrackingCode(product.TrackingStrategy), StateCode(product.State), product.Version,
            variants, uoms, barcodes, categories, mappings);
    }

    public async Task<IReadOnlyList<UnitOfMeasureView>> ListUomsAsync(
        Guid companyId, CancellationToken cancellationToken)
    {
        var rows = await dbContext.Set<UnitOfMeasureRecord>().AsNoTracking()
            .Where(x => x.CompanyId == companyId)
            .OrderBy(x => x.Code)
            .ToArrayAsync(cancellationToken);
        return rows.Select(x => new UnitOfMeasureView(
            x.PublicId, x.Code, x.Name, MasterStateCode(x.State), x.Version)).ToArray();
    }

    public async Task<IReadOnlyList<CategoryLookupView>> ListCategoriesAsync(
        Guid companyId, CancellationToken cancellationToken)
    {
        return await (
            from c in dbContext.Set<ProductCategoryRecord>().AsNoTracking()
            join p0 in dbContext.Set<ProductCategoryRecord>().AsNoTracking()
                on c.ParentCategoryId equals (long?)p0.Id into pg
            from parent in pg.DefaultIfEmpty()
            where c.CompanyId == companyId
            orderby c.Code
            select new CategoryLookupView(
                c.PublicId, c.Code, c.Name,
                parent == null ? null : parent.PublicId,
                c.Version)).ToArrayAsync(cancellationToken);
    }

    public Task<ProductMasterMutationPersistenceResult> CreateUomAsync(
        CreateUomWrite write, CancellationToken cancellationToken) =>
        ExecuteAsync(write.Context, () =>
        {
            dbContext.Add(new UnitOfMeasureRecord
            {
                PublicId = write.Uom.PublicId,
                CompanyId = write.Uom.CompanyId,
                Code = write.Uom.Code,
                Name = write.Uom.Name,
                State = write.Uom.State,
                Version = write.Uom.Version,
                CreatedAt = write.Uom.CreatedAt
            });
            return Task.FromResult(Success(write.Uom.PublicId, "ACTIVE", write.Uom.Version));
        }, "products.uom.create.completed", cancellationToken);

    public Task<ProductMasterMutationPersistenceResult> ChangeUomStateAsync(
        ChangeUomStateWrite write, CancellationToken cancellationToken) =>
        ExecuteAsync(write.Context, async () =>
        {
            var uom = await dbContext.Set<UnitOfMeasureRecord>()
                .SingleOrDefaultAsync(x => x.PublicId == write.UomPublicId && x.CompanyId == write.Context.CompanyId, cancellationToken);
            if (uom is null) return Outcome(ProductMasterMutationOutcome.ChildNotFound);
            if (uom.Version != write.ExpectedVersion) return Outcome(ProductMasterMutationOutcome.StaleVersion);
            if (uom.State == write.TargetState) return Outcome(ProductMasterMutationOutcome.StateConflict);
            if (write.TargetState == ProductMasterRecordState.Inactive &&
                await dbContext.Set<ProductUomRecord>().AnyAsync(
                    x => x.UomId == uom.Id && x.State == ProductMasterRecordState.Active, cancellationToken))
                return Outcome(ProductMasterMutationOutcome.BusinessConflict);
            uom.State = write.TargetState;
            uom.Version = checked(uom.Version + 1);
            return Success(uom.PublicId, MasterStateCode(uom.State), uom.Version);
        }, "products.uom.state.completed", cancellationToken);

    public Task<ProductMasterMutationPersistenceResult> CreateProductAsync(
        CreateProductWrite write, CancellationToken cancellationToken) =>
        ExecuteAsync(write.Context, async () =>
        {
            var uom = await dbContext.Set<UnitOfMeasureRecord>().SingleOrDefaultAsync(
                x => x.PublicId == write.BaseUomPublicId &&
                     x.CompanyId == write.Context.CompanyId &&
                     x.State == ProductMasterRecordState.Active,
                cancellationToken);
            if (uom is null) return Outcome(ProductMasterMutationOutcome.ChildNotFound);

            var record = new ProductRecord
            {
                PublicId = write.Product.PublicId,
                CompanyId = write.Product.CompanyId,
                ProductCode = write.Product.ProductCode,
                Name = write.Product.Name,
                Description = write.Product.Description,
                Kind = write.Product.Kind,
                Sellable = write.Product.Sellable,
                Purchasable = write.Product.Purchasable,
                Stockable = write.Product.Stockable,
                TrackingStrategy = write.Product.TrackingStrategy,
                State = write.Product.State,
                Version = write.Product.Version,
                CreatedAt = write.Product.CreatedAt
            };
            dbContext.Add(record);
            await dbContext.SaveChangesAsync(cancellationToken);

            dbContext.Add(new ProductUomRecord
            {
                PublicId = Guid.NewGuid(),
                ProductId = record.Id,
                VariantId = null,
                UomId = uom.Id,
                CompanyId = record.CompanyId,
                Role = ProductUomRole.Base,
                ConversionFactor = 1m,
                State = ProductMasterRecordState.Active,
                Version = 1,
                CreatedAt = write.Product.CreatedAt
            });
            return Success(record.PublicId, "ACTIVE", record.Version);
        }, "products.create.completed", cancellationToken);

    public Task<ProductMasterMutationPersistenceResult> EditProductAsync(
        EditProductWrite write, CancellationToken cancellationToken) =>
        ExecuteAsync(write.Context, async () =>
        {
            var product = await FindProductAsync(write.ProductPublicId, write.Context.CompanyId, cancellationToken);
            if (product is null) return Outcome(ProductMasterMutationOutcome.ProductNotFound);
            if (product.Version != write.ExpectedVersion) return Outcome(ProductMasterMutationOutcome.StaleVersion);
            product.ProductCode = write.ProductCode;
            product.Name = write.Name;
            product.Description = write.Description;
            product.Sellable = write.Sellable;
            product.Purchasable = write.Purchasable;
            product.Version = checked(product.Version + 1);
            return Success(product.PublicId, StateCode(product.State), product.Version);
        }, "products.edit.completed", cancellationToken);

    public Task<ProductMasterMutationPersistenceResult> ChangeProductStateAsync(
        ChangeProductStateWrite write, CancellationToken cancellationToken) =>
        ExecuteAsync(write.Context, async () =>
        {
            var product = await FindProductAsync(write.ProductPublicId, write.Context.CompanyId, cancellationToken);
            if (product is null) return Outcome(ProductMasterMutationOutcome.ProductNotFound);
            if (product.Version != write.ExpectedVersion) return Outcome(ProductMasterMutationOutcome.StaleVersion);
            if (product.State == write.TargetState) return Outcome(ProductMasterMutationOutcome.StateConflict);
            product.State = write.TargetState;
            product.Version = checked(product.Version + 1);
            return Success(product.PublicId, StateCode(product.State), product.Version);
        }, "products.state.completed", cancellationToken);

    public Task<ProductMasterMutationPersistenceResult> CreateVariantAsync(
        CreateVariantWrite write, CancellationToken cancellationToken) =>
        ExecuteAsync(write.Context, async () =>
        {
            var product = await FindProductAsync(write.Variant.ProductPublicId, write.Context.CompanyId, cancellationToken);
            if (product is null) return Outcome(ProductMasterMutationOutcome.ProductNotFound);
            if (write.Variant.TrackingStrategy.HasValue &&
                write.Variant.TrackingStrategy.Value != ProductTrackingStrategy.None &&
                !product.Stockable)
                return Outcome(ProductMasterMutationOutcome.BusinessConflict);

            dbContext.Add(new ProductVariantRecord
            {
                PublicId = write.Variant.PublicId,
                ProductId = product.Id,
                CompanyId = product.CompanyId,
                VariantCode = write.Variant.VariantCode,
                Name = write.Variant.Name,
                TrackingStrategy = write.Variant.TrackingStrategy,
                State = write.Variant.State,
                Version = write.Variant.Version,
                CreatedAt = write.Variant.CreatedAt
            });
            return Success(write.Variant.PublicId, "ACTIVE", write.Variant.Version);
        }, "products.variant.create.completed", cancellationToken);

    public Task<ProductMasterMutationPersistenceResult> EditVariantAsync(
        EditVariantWrite write, CancellationToken cancellationToken) =>
        ExecuteAsync(write.Context, async () =>
        {
            var product = await FindProductAsync(write.ProductPublicId, write.Context.CompanyId, cancellationToken);
            if (product is null) return Outcome(ProductMasterMutationOutcome.ProductNotFound);
            if (write.TrackingStrategy.HasValue &&
                write.TrackingStrategy.Value != ProductTrackingStrategy.None &&
                !product.Stockable)
                return Outcome(ProductMasterMutationOutcome.BusinessConflict);

            var variant = await dbContext.Set<ProductVariantRecord>().SingleOrDefaultAsync(
                x => x.PublicId == write.VariantPublicId &&
                     x.ProductId == product.Id &&
                     x.CompanyId == product.CompanyId,
                cancellationToken);
            if (variant is null) return Outcome(ProductMasterMutationOutcome.ChildNotFound);
            if (variant.Version != write.ExpectedVersion) return Outcome(ProductMasterMutationOutcome.StaleVersion);

            variant.VariantCode = write.VariantCode;
            variant.Name = write.Name;
            variant.TrackingStrategy = write.TrackingStrategy;
            variant.Version = checked(variant.Version + 1);
            return Success(variant.PublicId, MasterStateCode(variant.State), variant.Version);
        }, "products.variant.edit.completed", cancellationToken);

    public Task<ProductMasterMutationPersistenceResult> ChangeVariantStateAsync(
        ChangeVariantStateWrite write, CancellationToken cancellationToken) =>
        ExecuteAsync(write.Context, async () =>
        {
            var product = await FindProductAsync(write.ProductPublicId, write.Context.CompanyId, cancellationToken);
            if (product is null) return Outcome(ProductMasterMutationOutcome.ProductNotFound);
            var variant = await dbContext.Set<ProductVariantRecord>().SingleOrDefaultAsync(
                x => x.PublicId == write.VariantPublicId &&
                     x.ProductId == product.Id &&
                     x.CompanyId == product.CompanyId,
                cancellationToken);
            if (variant is null) return Outcome(ProductMasterMutationOutcome.ChildNotFound);
            if (variant.Version != write.ExpectedVersion) return Outcome(ProductMasterMutationOutcome.StaleVersion);
            if (variant.State == write.TargetState) return Outcome(ProductMasterMutationOutcome.StateConflict);
            variant.State = write.TargetState;
            variant.Version = checked(variant.Version + 1);
            return Success(variant.PublicId, MasterStateCode(variant.State), variant.Version);
        }, "products.variant.state.completed", cancellationToken);

    public Task<ProductMasterMutationPersistenceResult> AddProductUomAsync(
        AddProductUomWrite write, CancellationToken cancellationToken) =>
        ExecuteAsync(write.Context, async () =>
        {
            var product = await FindProductAsync(write.Assignment.ProductPublicId, write.Context.CompanyId, cancellationToken);
            if (product is null) return Outcome(ProductMasterMutationOutcome.ProductNotFound);
            var uom = await dbContext.Set<UnitOfMeasureRecord>().SingleOrDefaultAsync(
                x => x.PublicId == write.Assignment.UomPublicId &&
                     x.CompanyId == product.CompanyId &&
                     x.State == ProductMasterRecordState.Active,
                cancellationToken);
            if (uom is null) return Outcome(ProductMasterMutationOutcome.ChildNotFound);

            long? variantId = null;
            if (write.Assignment.VariantPublicId.HasValue)
            {
                var variant = await dbContext.Set<ProductVariantRecord>().SingleOrDefaultAsync(
                    x => x.PublicId == write.Assignment.VariantPublicId.Value &&
                         x.ProductId == product.Id &&
                         x.CompanyId == product.CompanyId,
                    cancellationToken);
                if (variant is null) return Outcome(ProductMasterMutationOutcome.ChildNotFound);
                variantId = variant.Id;
            }

            dbContext.Add(new ProductUomRecord
            {
                PublicId = write.Assignment.PublicId,
                ProductId = product.Id,
                VariantId = variantId,
                UomId = uom.Id,
                CompanyId = product.CompanyId,
                Role = ProductUomRole.Alternate,
                ConversionFactor = write.Assignment.ConversionFactor,
                State = write.Assignment.State,
                Version = write.Assignment.Version,
                CreatedAt = write.Assignment.CreatedAt
            });
            return Success(write.Assignment.PublicId, "ACTIVE", write.Assignment.Version);
        }, "products.product_uom.create.completed", cancellationToken);

    public Task<ProductMasterMutationPersistenceResult> UpdateProductUomAsync(
        UpdateProductUomWrite write, CancellationToken cancellationToken) =>
        ExecuteAsync(write.Context, async () =>
        {
            var product = await FindProductAsync(write.ProductPublicId, write.Context.CompanyId, cancellationToken);
            if (product is null) return Outcome(ProductMasterMutationOutcome.ProductNotFound);
            var assignment = await dbContext.Set<ProductUomRecord>().SingleOrDefaultAsync(
                x => x.PublicId == write.ProductUomPublicId &&
                     x.ProductId == product.Id &&
                     x.CompanyId == product.CompanyId,
                cancellationToken);
            if (assignment is null) return Outcome(ProductMasterMutationOutcome.ChildNotFound);
            if (assignment.Role == ProductUomRole.Base) return Outcome(ProductMasterMutationOutcome.BusinessConflict);
            if (assignment.Version != write.ExpectedVersion) return Outcome(ProductMasterMutationOutcome.StaleVersion);

            assignment.ConversionFactor = write.ConversionFactor;
            assignment.Version = checked(assignment.Version + 1);
            return Success(assignment.PublicId, MasterStateCode(assignment.State), assignment.Version);
        }, "products.product_uom.edit.completed", cancellationToken);

    public Task<ProductMasterMutationPersistenceResult> ChangeProductUomStateAsync(
        ChangeProductUomStateWrite write, CancellationToken cancellationToken) =>
        ExecuteAsync(write.Context, async () =>
        {
            var product = await FindProductAsync(write.ProductPublicId, write.Context.CompanyId, cancellationToken);
            if (product is null) return Outcome(ProductMasterMutationOutcome.ProductNotFound);
            var assignment = await dbContext.Set<ProductUomRecord>().SingleOrDefaultAsync(
                x => x.PublicId == write.ProductUomPublicId &&
                     x.ProductId == product.Id &&
                     x.CompanyId == product.CompanyId,
                cancellationToken);
            if (assignment is null) return Outcome(ProductMasterMutationOutcome.ChildNotFound);
            if (assignment.Role == ProductUomRole.Base) return Outcome(ProductMasterMutationOutcome.BusinessConflict);
            if (assignment.Version != write.ExpectedVersion) return Outcome(ProductMasterMutationOutcome.StaleVersion);
            if (assignment.State == write.TargetState) return Outcome(ProductMasterMutationOutcome.StateConflict);

            assignment.State = write.TargetState;
            assignment.Version = checked(assignment.Version + 1);
            return Success(assignment.PublicId, MasterStateCode(assignment.State), assignment.Version);
        }, "products.product_uom.state.completed", cancellationToken);

    public Task<ProductMasterMutationPersistenceResult> CreateBarcodeAsync(
        CreateBarcodeWrite write, CancellationToken cancellationToken) =>
        ExecuteAsync(write.Context, async () =>
        {
            var product = await FindProductAsync(write.Barcode.ProductPublicId, write.Context.CompanyId, cancellationToken);
            if (product is null) return Outcome(ProductMasterMutationOutcome.ProductNotFound);

            long? variantId = null;
            long? productUomId = null;
            if (write.Barcode.VariantPublicId.HasValue)
            {
                var variant = await dbContext.Set<ProductVariantRecord>().SingleOrDefaultAsync(
                    x => x.PublicId == write.Barcode.VariantPublicId.Value &&
                         x.ProductId == product.Id &&
                         x.CompanyId == product.CompanyId,
                    cancellationToken);
                if (variant is null) return Outcome(ProductMasterMutationOutcome.ChildNotFound);
                variantId = variant.Id;
            }

            if (write.Barcode.ProductUomPublicId.HasValue)
            {
                var assignment = await dbContext.Set<ProductUomRecord>().SingleOrDefaultAsync(
                    x => x.PublicId == write.Barcode.ProductUomPublicId.Value &&
                         x.ProductId == product.Id &&
                         x.CompanyId == product.CompanyId,
                    cancellationToken);
                if (assignment is null) return Outcome(ProductMasterMutationOutcome.ChildNotFound);
                if ((variantId.HasValue && assignment.VariantId != variantId) ||
                    (!variantId.HasValue && assignment.VariantId.HasValue))
                    return Outcome(ProductMasterMutationOutcome.BusinessConflict);
                productUomId = assignment.Id;
            }

            dbContext.Add(new ProductBarcodeRecord
            {
                PublicId = write.Barcode.PublicId,
                ProductId = product.Id,
                VariantId = variantId,
                ProductUomId = productUomId,
                CompanyId = product.CompanyId,
                Namespace = write.Barcode.Namespace,
                Value = write.Barcode.Value,
                State = write.Barcode.State,
                Version = write.Barcode.Version,
                CreatedAt = write.Barcode.CreatedAt
            });
            return Success(write.Barcode.PublicId, "ACTIVE", write.Barcode.Version);
        }, "products.barcode.create.completed", cancellationToken);

    public Task<ProductMasterMutationPersistenceResult> ChangeBarcodeStateAsync(
        ChangeBarcodeStateWrite write, CancellationToken cancellationToken) =>
        ExecuteAsync(write.Context, async () =>
        {
            var product = await FindProductAsync(write.ProductPublicId, write.Context.CompanyId, cancellationToken);
            if (product is null) return Outcome(ProductMasterMutationOutcome.ProductNotFound);
            var barcode = await dbContext.Set<ProductBarcodeRecord>().SingleOrDefaultAsync(
                x => x.PublicId == write.BarcodePublicId &&
                     x.ProductId == product.Id &&
                     x.CompanyId == product.CompanyId,
                cancellationToken);
            if (barcode is null) return Outcome(ProductMasterMutationOutcome.ChildNotFound);
            if (barcode.Version != write.ExpectedVersion) return Outcome(ProductMasterMutationOutcome.StaleVersion);
            if (barcode.State == write.TargetState) return Outcome(ProductMasterMutationOutcome.StateConflict);

            barcode.State = write.TargetState;
            barcode.Version = checked(barcode.Version + 1);
            return Success(barcode.PublicId, MasterStateCode(barcode.State), barcode.Version);
        }, "products.barcode.state.completed", cancellationToken);

    public Task<ProductMasterMutationPersistenceResult> CreateCategoryAsync(
        CreateCategoryWrite write, CancellationToken cancellationToken) =>
        ExecuteAsync(write.Context, async () =>
        {
            long? parentId = null;
            if (write.Category.ParentCategoryPublicId.HasValue)
            {
                var parent = await dbContext.Set<ProductCategoryRecord>().SingleOrDefaultAsync(
                    x => x.PublicId == write.Category.ParentCategoryPublicId.Value &&
                         x.CompanyId == write.Context.CompanyId,
                    cancellationToken);
                if (parent is null) return Outcome(ProductMasterMutationOutcome.ChildNotFound);
                parentId = parent.Id;
            }

            dbContext.Add(new ProductCategoryRecord
            {
                PublicId = write.Category.PublicId,
                CompanyId = write.Category.CompanyId,
                Code = write.Category.Code,
                Name = write.Category.Name,
                ParentCategoryId = parentId,
                Version = 1,
                CreatedAt = write.Category.CreatedAt
            });
            return Success(write.Category.PublicId, "ACTIVE", 1);
        }, "products.category.create.completed", cancellationToken);

    public Task<ProductMasterMutationPersistenceResult> EditCategoryAsync(
        EditCategoryWrite write, CancellationToken cancellationToken) =>
        ExecuteAsync(write.Context, async () =>
        {
            var category = await dbContext.Set<ProductCategoryRecord>().SingleOrDefaultAsync(
                x => x.PublicId == write.CategoryPublicId && x.CompanyId == write.Context.CompanyId,
                cancellationToken);
            if (category is null) return Outcome(ProductMasterMutationOutcome.ChildNotFound);
            if (category.Version != write.ExpectedVersion) return Outcome(ProductMasterMutationOutcome.StaleVersion);

            long? parentId = null;
            if (write.ParentCategoryPublicId.HasValue)
            {
                var parent = await dbContext.Set<ProductCategoryRecord>().SingleOrDefaultAsync(
                    x => x.PublicId == write.ParentCategoryPublicId.Value &&
                         x.CompanyId == write.Context.CompanyId,
                    cancellationToken);
                if (parent is null) return Outcome(ProductMasterMutationOutcome.ChildNotFound);
                if (parent.Id == category.Id ||
                    await IsDescendantAsync(parent.Id, category.Id, write.Context.CompanyId, cancellationToken))
                    return Outcome(ProductMasterMutationOutcome.BusinessConflict);
                parentId = parent.Id;
            }

            category.Code = write.Code;
            category.Name = write.Name;
            category.ParentCategoryId = parentId;
            category.Version = checked(category.Version + 1);
            return Success(category.PublicId, "ACTIVE", category.Version);
        }, "products.category.edit.completed", cancellationToken);

    public Task<ProductMasterMutationPersistenceResult> AssignCategoryAsync(
        AssignCategoryWrite write, CancellationToken cancellationToken) =>
        ExecuteAsync(write.Context, async () =>
        {
            var product = await FindProductAsync(write.ProductPublicId, write.Context.CompanyId, cancellationToken);
            if (product is null) return Outcome(ProductMasterMutationOutcome.ProductNotFound);
            var category = await dbContext.Set<ProductCategoryRecord>().SingleOrDefaultAsync(
                x => x.PublicId == write.CategoryPublicId && x.CompanyId == product.CompanyId,
                cancellationToken);
            if (category is null) return Outcome(ProductMasterMutationOutcome.ChildNotFound);

            if (write.IsPrimary)
            {
                var primaries = await dbContext.Set<ProductCategoryLinkRecord>()
                    .Where(x => x.ProductId == product.Id && x.IsPrimary)
                    .ToArrayAsync(cancellationToken);
                foreach (var existing in primaries) existing.IsPrimary = false;
            }

            dbContext.Add(new ProductCategoryLinkRecord
            {
                ProductId = product.Id,
                CategoryId = category.Id,
                CompanyId = product.CompanyId,
                IsPrimary = write.IsPrimary,
                CreatedAt = write.Context.Audit.OccurredAt
            });
            return Success(product.PublicId, StateCode(product.State), product.Version);
        }, "products.category.assign.completed", cancellationToken);

    public Task<ProductMasterMutationPersistenceResult> UnassignCategoryAsync(
        UnassignCategoryWrite write, CancellationToken cancellationToken) =>
        ExecuteAsync(write.Context, async () =>
        {
            var product = await FindProductAsync(write.ProductPublicId, write.Context.CompanyId, cancellationToken);
            if (product is null) return Outcome(ProductMasterMutationOutcome.ProductNotFound);
            var category = await dbContext.Set<ProductCategoryRecord>().SingleOrDefaultAsync(
                x => x.PublicId == write.CategoryPublicId && x.CompanyId == product.CompanyId,
                cancellationToken);
            if (category is null) return Outcome(ProductMasterMutationOutcome.ChildNotFound);

            var link = await dbContext.Set<ProductCategoryLinkRecord>().SingleOrDefaultAsync(
                x => x.ProductId == product.Id && x.CategoryId == category.Id,
                cancellationToken);
            if (link is null) return Outcome(ProductMasterMutationOutcome.ChildNotFound);
            dbContext.Remove(link);
            return Success(product.PublicId, StateCode(product.State), product.Version);
        }, "products.category.unassign.completed", cancellationToken);

    public Task<ProductMasterMutationPersistenceResult> CreateExternalMappingAsync(
        CreateProductExternalMappingWrite write, CancellationToken cancellationToken) =>
        ExecuteAsync(write.Context, async () =>
        {
            var product = await FindProductAsync(write.Mapping.ProductPublicId, write.Context.CompanyId, cancellationToken);
            if (product is null) return Outcome(ProductMasterMutationOutcome.ProductNotFound);
            long? variantId = null;
            if (write.Mapping.VariantPublicId.HasValue)
            {
                var variant = await dbContext.Set<ProductVariantRecord>().SingleOrDefaultAsync(
                    x => x.PublicId == write.Mapping.VariantPublicId.Value &&
                         x.ProductId == product.Id &&
                         x.CompanyId == product.CompanyId,
                    cancellationToken);
                if (variant is null) return Outcome(ProductMasterMutationOutcome.ChildNotFound);
                variantId = variant.Id;
            }

            dbContext.Add(new ProductExternalMappingRecord
            {
                PublicId = write.Mapping.PublicId,
                ProductId = product.Id,
                VariantId = variantId,
                CompanyId = product.CompanyId,
                SystemCode = write.Mapping.SystemCode,
                AccountScope = write.Mapping.AccountScope,
                ExternalIdentity = write.Mapping.ExternalIdentity,
                State = write.Mapping.State,
                Version = write.Mapping.Version,
                CreatedAt = write.Mapping.CreatedAt
            });
            return Success(write.Mapping.PublicId, "ACTIVE", write.Mapping.Version);
        }, "products.external_mapping.create.completed", cancellationToken);

    public Task<ProductMasterMutationPersistenceResult> ChangeExternalMappingStateAsync(
        ChangeProductExternalMappingStateWrite write, CancellationToken cancellationToken) =>
        ExecuteAsync(write.Context, async () =>
        {
            var product = await FindProductAsync(write.ProductPublicId, write.Context.CompanyId, cancellationToken);
            if (product is null) return Outcome(ProductMasterMutationOutcome.ProductNotFound);
            var mapping = await dbContext.Set<ProductExternalMappingRecord>().SingleOrDefaultAsync(
                x => x.PublicId == write.MappingPublicId &&
                     x.ProductId == product.Id &&
                     x.CompanyId == product.CompanyId,
                cancellationToken);
            if (mapping is null) return Outcome(ProductMasterMutationOutcome.ChildNotFound);
            if (mapping.Version != write.ExpectedVersion) return Outcome(ProductMasterMutationOutcome.StaleVersion);
            if (mapping.State == write.TargetState) return Outcome(ProductMasterMutationOutcome.StateConflict);

            mapping.State = write.TargetState;
            mapping.Version = checked(mapping.Version + 1);
            return Success(mapping.PublicId, MasterStateCode(mapping.State), mapping.Version);
        }, "products.external_mapping.state.completed", cancellationToken);

    private async Task<ProductMasterMutationPersistenceResult> ExecuteAsync(
        ProductMasterWriteContext context,
        Func<Task<ProductMasterMutationPersistenceResult>> mutation,
        string resultCode,
        CancellationToken cancellationToken)
    {
        await using var transaction = await dbContext.Database.BeginTransactionAsync(cancellationToken);
        try
        {
            var result = await mutation();
            if (result.Outcome != ProductMasterMutationOutcome.Succeeded)
            {
                await transaction.RollbackAsync(cancellationToken);
                dbContext.ChangeTracker.Clear();
                return result;
            }

            idempotencyStore.Add(context.Idempotency);
            auditWriter.Append(context.Audit);
            await dbContext.SaveChangesAsync(cancellationToken);

            var marked = await idempotencyStore.MarkSucceededAsync(
                context.Idempotency.Scope,
                context.Idempotency.OperationKey,
                resultCode,
                context.Audit.OccurredAt,
                cancellationToken);
            if (!marked)
                throw new InvalidOperationException("Product master idempotency state could not be completed.");

            await transaction.CommitAsync(cancellationToken);
            return result;
        }
        catch (DbUpdateConcurrencyException)
        {
            await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return Outcome(ProductMasterMutationOutcome.StaleVersion);
        }
        catch (DbUpdateException exception) when (IsConstraint(exception, "ux_idempotency_scope_key"))
        {
            await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return Outcome(ProductMasterMutationOutcome.DuplicateOperation);
        }
        catch (DbUpdateException exception) when (IsUniqueViolation(exception))
        {
            await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return Outcome(ProductMasterMutationOutcome.DeterministicConflict);
        }
    }

    private Task<ProductRecord?> FindProductAsync(
        Guid publicId, Guid companyId, CancellationToken cancellationToken) =>
        dbContext.Set<ProductRecord>().SingleOrDefaultAsync(
            x => x.PublicId == publicId && x.CompanyId == companyId,
            cancellationToken);

    private async Task<bool> IsDescendantAsync(
        long candidateParentId, long categoryId, Guid companyId, CancellationToken cancellationToken)
    {
        var current = candidateParentId;
        for (var depth = 0; depth < 128; depth++)
        {
            if (current == categoryId) return true;
            var parent = await dbContext.Set<ProductCategoryRecord>().AsNoTracking()
                .Where(x => x.Id == current && x.CompanyId == companyId)
                .Select(x => x.ParentCategoryId)
                .SingleOrDefaultAsync(cancellationToken);
            if (!parent.HasValue) return false;
            current = parent.Value;
        }
        return true;
    }

    private static ProductMasterMutationPersistenceResult Success(Guid publicId, string state, long version) =>
        new(ProductMasterMutationOutcome.Succeeded, publicId, state, version);

    private static ProductMasterMutationPersistenceResult Outcome(ProductMasterMutationOutcome outcome) =>
        new(outcome);

    private static string KindCode(ProductKind kind) => kind == ProductKind.Goods ? "GOODS" : "SERVICE";
    private static string TrackingCode(ProductTrackingStrategy tracking) => tracking switch
    {
        ProductTrackingStrategy.None => "NONE",
        ProductTrackingStrategy.Lot => "LOT",
        ProductTrackingStrategy.Serial => "SERIAL",
        _ => "LOT_SERIAL"
    };
    private static string StateCode(ProductState state) => state == ProductState.Active ? "ACTIVE" : "INACTIVE";
    private static string MasterStateCode(ProductMasterRecordState state) => state == ProductMasterRecordState.Active ? "ACTIVE" : "INACTIVE";

    private static bool IsConstraint(DbUpdateException exception, string constraintName) =>
        exception.InnerException is PostgresException postgres &&
        postgres.SqlState == PostgresErrorCodes.UniqueViolation &&
        string.Equals(postgres.ConstraintName, constraintName, StringComparison.Ordinal);

    private static bool IsUniqueViolation(DbUpdateException exception) =>
        exception.InnerException is PostgresException postgres &&
        postgres.SqlState == PostgresErrorCodes.UniqueViolation;
}
