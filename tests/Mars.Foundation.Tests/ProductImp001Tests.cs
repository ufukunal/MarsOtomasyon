using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Configuration;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Application.Products;
using Mars.Application.Products.ProductMaster;
using Mars.Domain.Products;
using Mars.Infrastructure.Persistence;
using Microsoft.EntityFrameworkCore;
using MarsExecutionContext = Mars.Application.Foundation.Context.ExecutionContext;

internal static class ProductImp001Tests
{
    public static IReadOnlyList<(string Name, Action Test)> Cases { get; } =
    [
        ("Product domain rejects SERVICE STOCKABLE and non-stock tracking", ProductDomainInvariants),
        ("Product master read requires product.read", ReadRequiresPermission),
        ("Product create produces scoped audited idempotent write", CreateProducesScopedWrite),
        ("Product UOM conversion is positive decimal", ProductUomInvariant),
        ("PRODUCT-IMP-001 model contains normalized Product master tables", ModelContainsTables)
    ];

    private static void ProductDomainInvariants()
    {
        var companyId = Guid.NewGuid();
        var now = DateTimeOffset.UtcNow;

        AssertThrows<ArgumentException>(() =>
            Product.Create(
                Guid.NewGuid(), companyId, "SVC", "Service", null,
                ProductKind.Service, true, true, true, ProductTrackingStrategy.None, now));

        AssertThrows<ArgumentException>(() =>
            Product.Create(
                Guid.NewGuid(), companyId, "NST", "Nonstock", null,
                ProductKind.Goods, true, true, false, ProductTrackingStrategy.Lot, now));

        var product = Product.Create(
            Guid.NewGuid(), companyId, "G-1", "Goods", null,
            ProductKind.Goods, true, true, true, ProductTrackingStrategy.Lot, now);
        AssertEqual(ProductState.Active, product.State);
        AssertEqual(1L, product.Version);
    }

    private static void ReadRequiresPermission()
    {
        var handler = new ProductMasterQueryHandler(
            new FakePermissionEvaluator(),
            new FakeReadPersistence());

        var result = handler.ListProductsAsync(null, NewContext(), CancellationToken.None)
            .GetAwaiter().GetResult();

        AssertTrue(result.IsFailure);
        AssertEqual(ErrorCategory.Authorization, result.Error!.Category);
    }

    private static void CreateProducesScopedWrite()
    {
        var persistence = new FakeMutationPersistence();
        var handler = new ProductMasterCommandHandler(
            new FakePermissionEvaluator(ProductPermissions.Create),
            persistence);
        var context = NewContext();

        var result = handler.CreateProductAsync(
                new CreateProductCommand(
                    "P-1", "Product", null, "GOODS",
                    true, true, false, "NONE", Guid.NewGuid(), "product-create-op"),
                context,
                CancellationToken.None)
            .GetAwaiter().GetResult();

        AssertTrue(result.IsSuccess);
        AssertTrue(persistence.CreateProductWrite is not null);
        AssertEqual(context.CompanyId, persistence.CreateProductWrite!.Context.CompanyId);
        AssertEqual(
            "products.create:" + context.CompanyId.ToString("D"),
            persistence.CreateProductWrite.Context.Idempotency.Scope);
        AssertEqual("ProductCreated", persistence.CreateProductWrite.Context.Audit.Action);
    }

    private static void ProductUomInvariant()
    {
        var now = DateTimeOffset.UtcNow;
        AssertThrows<ArgumentOutOfRangeException>(() =>
            ProductUomAssignment.Create(
                Guid.NewGuid(), Guid.NewGuid(), null, Guid.NewGuid(), Guid.NewGuid(),
                ProductUomRole.Alternate, 0m, now));

        var assignment = ProductUomAssignment.Create(
            Guid.NewGuid(), Guid.NewGuid(), null, Guid.NewGuid(), Guid.NewGuid(),
            ProductUomRole.Alternate, 12.5m, now);
        AssertEqual(12.5m, assignment.ConversionFactor);
    }

    private static void ModelContainsTables()
    {
        using var context = CreateModelContext();
        var tables = context.Model.GetEntityTypes()
            .Select(x => x.GetSchema() + "." + x.GetTableName())
            .ToHashSet(StringComparer.Ordinal);

        foreach (var table in new[]
                 {
                     "products.products",
                     "products.uoms",
                     "products.variants",
                     "products.product_uoms",
                     "products.barcodes",
                     "products.categories",
                     "products.product_categories",
                     "products.external_mappings"
                 })
        {
            AssertTrue(tables.Contains(table));
        }

        var productUom = context.Model.GetEntityTypes()
            .Single(x => x.GetTableName() == "product_uoms");
        var factor = productUom.FindProperty("ConversionFactor");
        AssertEqual(28, factor?.GetPrecision());
        AssertEqual(9, factor?.GetScale());

        var product = context.Model.GetEntityTypes()
            .Single(x => x.GetTableName() == "products");
        AssertTrue(product.GetIndexes().Any(x =>
            x.IsUnique && x.GetDatabaseName() == "ux_products_company_code"));
    }

    private static MarsDbContext CreateModelContext()
    {
        var options = MarsDbContextOptions.CreateRuntime(
            new PostgreSqlRuntimeOptions("Host=localhost;Database=mars_product_imp_001_model_probe"));
        return new MarsDbContext(options);
    }

    private static IExecutionContext NewContext() =>
        new MarsExecutionContext(
            Guid.Parse("aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"),
            Guid.Parse("bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb"),
            null,
            new CorrelationId("corr-product-imp-001"));

    private static void AssertTrue(bool condition)
    {
        if (!condition) throw new InvalidOperationException("Assertion failed.");
    }

    private static void AssertEqual<T>(T expected, T actual)
    {
        if (!EqualityComparer<T>.Default.Equals(expected, actual))
            throw new InvalidOperationException($"Expected '{expected}', actual '{actual}'.");
    }

    private static TException AssertThrows<TException>(Action action)
        where TException : Exception
    {
        try { action(); }
        catch (TException exception) { return exception; }
        throw new InvalidOperationException("Expected exception was not thrown.");
    }

    private sealed class FakePermissionEvaluator(params string[] permissions) : IPermissionEvaluator
    {
        private readonly HashSet<string> granted = new(permissions, StringComparer.Ordinal);

        public Task<bool> IsGrantedAsync(
            Guid actorId, Guid companyId, string permissionCode, CancellationToken cancellationToken) =>
            Task.FromResult(granted.Contains(permissionCode));
    }

    private sealed class FakeReadPersistence : IProductMasterReadPersistence
    {
        public Task<IReadOnlyList<ProductListItem>> ListProductsAsync(
            Guid companyId, string? search, CancellationToken cancellationToken) =>
            Task.FromResult<IReadOnlyList<ProductListItem>>(Array.Empty<ProductListItem>());

        public Task<ProductDetailView?> GetProductAsync(
            Guid companyId, Guid productPublicId, ProductDetailReadOptions options, CancellationToken cancellationToken) =>
            Task.FromResult<ProductDetailView?>(null);

        public Task<IReadOnlyList<UnitOfMeasureView>> ListUomsAsync(
            Guid companyId, CancellationToken cancellationToken) =>
            Task.FromResult<IReadOnlyList<UnitOfMeasureView>>(Array.Empty<UnitOfMeasureView>());

        public Task<IReadOnlyList<CategoryLookupView>> ListCategoriesAsync(
            Guid companyId, CancellationToken cancellationToken) =>
            Task.FromResult<IReadOnlyList<CategoryLookupView>>(Array.Empty<CategoryLookupView>());
    }

    private sealed class FakeMutationPersistence : IProductMasterMutationPersistence
    {
        public CreateProductWrite? CreateProductWrite { get; private set; }

        private static ProductMasterMutationPersistenceResult Success(Guid? id = null) =>
            new(ProductMasterMutationOutcome.Succeeded, id ?? Guid.NewGuid(), "ACTIVE", 1);

        public Task<ProductMasterMutationPersistenceResult> CreateProductAsync(
            CreateProductWrite write, CancellationToken cancellationToken)
        {
            CreateProductWrite = write;
            return Task.FromResult(Success(write.Product.PublicId));
        }

        public Task<ProductMasterMutationPersistenceResult> CreateUomAsync(CreateUomWrite write, CancellationToken cancellationToken) => Task.FromResult(Success(write.Uom.PublicId));
        public Task<ProductMasterMutationPersistenceResult> ChangeUomStateAsync(ChangeUomStateWrite write, CancellationToken cancellationToken) => Task.FromResult(Success(write.UomPublicId));
        public Task<ProductMasterMutationPersistenceResult> EditProductAsync(EditProductWrite write, CancellationToken cancellationToken) => Task.FromResult(Success(write.ProductPublicId));
        public Task<ProductMasterMutationPersistenceResult> ChangeProductStateAsync(ChangeProductStateWrite write, CancellationToken cancellationToken) => Task.FromResult(Success(write.ProductPublicId));
        public Task<ProductMasterMutationPersistenceResult> CreateVariantAsync(CreateVariantWrite write, CancellationToken cancellationToken) => Task.FromResult(Success(write.Variant.PublicId));
        public Task<ProductMasterMutationPersistenceResult> EditVariantAsync(EditVariantWrite write, CancellationToken cancellationToken) => Task.FromResult(Success(write.VariantPublicId));
        public Task<ProductMasterMutationPersistenceResult> ChangeVariantStateAsync(ChangeVariantStateWrite write, CancellationToken cancellationToken) => Task.FromResult(Success(write.VariantPublicId));
        public Task<ProductMasterMutationPersistenceResult> AddProductUomAsync(AddProductUomWrite write, CancellationToken cancellationToken) => Task.FromResult(Success(write.Assignment.PublicId));
        public Task<ProductMasterMutationPersistenceResult> UpdateProductUomAsync(UpdateProductUomWrite write, CancellationToken cancellationToken) => Task.FromResult(Success(write.ProductUomPublicId));
        public Task<ProductMasterMutationPersistenceResult> ChangeProductUomStateAsync(ChangeProductUomStateWrite write, CancellationToken cancellationToken) => Task.FromResult(Success(write.ProductUomPublicId));
        public Task<ProductMasterMutationPersistenceResult> CreateBarcodeAsync(CreateBarcodeWrite write, CancellationToken cancellationToken) => Task.FromResult(Success(write.Barcode.PublicId));
        public Task<ProductMasterMutationPersistenceResult> ChangeBarcodeStateAsync(ChangeBarcodeStateWrite write, CancellationToken cancellationToken) => Task.FromResult(Success(write.BarcodePublicId));
        public Task<ProductMasterMutationPersistenceResult> CreateCategoryAsync(CreateCategoryWrite write, CancellationToken cancellationToken) => Task.FromResult(Success(write.Category.PublicId));
        public Task<ProductMasterMutationPersistenceResult> EditCategoryAsync(EditCategoryWrite write, CancellationToken cancellationToken) => Task.FromResult(Success(write.CategoryPublicId));
        public Task<ProductMasterMutationPersistenceResult> AssignCategoryAsync(AssignCategoryWrite write, CancellationToken cancellationToken) => Task.FromResult(Success(write.ProductPublicId));
        public Task<ProductMasterMutationPersistenceResult> UnassignCategoryAsync(UnassignCategoryWrite write, CancellationToken cancellationToken) => Task.FromResult(Success(write.ProductPublicId));
        public Task<ProductMasterMutationPersistenceResult> CreateExternalMappingAsync(CreateProductExternalMappingWrite write, CancellationToken cancellationToken) => Task.FromResult(Success(write.Mapping.PublicId));
        public Task<ProductMasterMutationPersistenceResult> ChangeExternalMappingStateAsync(ChangeProductExternalMappingStateWrite write, CancellationToken cancellationToken) => Task.FromResult(Success(write.MappingPublicId));
    }
}
