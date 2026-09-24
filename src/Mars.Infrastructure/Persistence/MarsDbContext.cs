using Mars.Infrastructure.Identity;
using Mars.Infrastructure.Persistence.Foundation;
using Mars.Infrastructure.Persistence.Inventory;
using Mars.Infrastructure.Persistence.Parties;
using Mars.Infrastructure.Persistence.Products;
using Mars.Infrastructure.Persistence.Purchasing;
using Mars.Infrastructure.Persistence.Sales;
using Microsoft.AspNetCore.Identity;
using Microsoft.AspNetCore.Identity.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore;

namespace Mars.Infrastructure.Persistence;

public sealed class MarsDbContext(DbContextOptions<MarsDbContext> options)
    : IdentityUserContext<MarsIdentityUser, Guid>(options)
{
    protected override void OnModelCreating(ModelBuilder modelBuilder)
    {
        base.OnModelCreating(modelBuilder);

        modelBuilder.HasDefaultSchema("identity");

        modelBuilder.Entity<MarsIdentityUser>().ToTable("users", "identity");
        modelBuilder.Entity<IdentityUserClaim<Guid>>().ToTable("user_claims", "identity");
        modelBuilder.Entity<IdentityUserLogin<Guid>>().ToTable("user_logins", "identity");
        modelBuilder.Entity<IdentityUserToken<Guid>>().ToTable("user_tokens", "identity");

        modelBuilder.ApplyConfiguration(new AuditEventConfiguration());
        modelBuilder.ApplyConfiguration(new ApprovalDecisionConfiguration());
        modelBuilder.ApplyConfiguration(new IdempotencyOperationConfiguration());
        modelBuilder.ApplyConfiguration(new OutboxMessageConfiguration());
        modelBuilder.ApplyConfiguration(new PermissionGrantConfiguration());
        modelBuilder.ApplyConfiguration(new PartyConfiguration());
        modelBuilder.ApplyConfiguration(new PartyRoleConfiguration());
        modelBuilder.ApplyConfiguration(new PartyTaxIdentityConfiguration());
        modelBuilder.ApplyConfiguration(new PartyContactConfiguration());
        modelBuilder.ApplyConfiguration(new PartyCommunicationPointConfiguration());
        modelBuilder.ApplyConfiguration(new PartyAddressConfiguration());
        modelBuilder.ApplyConfiguration(new PartyExternalMappingConfiguration());
        modelBuilder.ApplyConfiguration(new PartyMergeLineageConfiguration());
        modelBuilder.ApplyConfiguration(new ProductConfiguration());
        modelBuilder.ApplyConfiguration(new UnitOfMeasureConfiguration());
        modelBuilder.ApplyConfiguration(new ProductVariantConfiguration());
        modelBuilder.ApplyConfiguration(new ProductUomConfiguration());
        modelBuilder.ApplyConfiguration(new ProductBarcodeConfiguration());
        modelBuilder.ApplyConfiguration(new ProductCategoryConfiguration());
        modelBuilder.ApplyConfiguration(new ProductCategoryLinkConfiguration());
        modelBuilder.ApplyConfiguration(new ProductExternalMappingConfiguration());
        modelBuilder.ApplyConfiguration(new WarehouseConfiguration());
        modelBuilder.ApplyConfiguration(new LocationConfiguration());
        modelBuilder.ApplyConfiguration(new InventoryDispositionConfiguration());
        modelBuilder.ApplyConfiguration(new InventoryLotConfiguration());
        modelBuilder.ApplyConfiguration(new InventorySerialConfiguration());
        modelBuilder.ApplyConfiguration(new InventoryMovementConfiguration());
        modelBuilder.ApplyConfiguration(new InventoryReservationConfiguration());
        modelBuilder.ApplyConfiguration(new InventoryReservationMovementConfiguration());
        modelBuilder.ApplyConfiguration(new WarehouseAccessGrantConfiguration());
        SalesModelConfiguration.Configure(modelBuilder);
        ProformaModelConfiguration.Configure(modelBuilder);
        PurchasingModelConfiguration.Configure(modelBuilder);
    }
}
