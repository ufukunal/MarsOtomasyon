using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Mars.Infrastructure.Persistence.Inventory;

internal sealed class WarehouseAccessGrantConfiguration
    : IEntityTypeConfiguration<WarehouseAccessGrantRecord>
{
    public void Configure(EntityTypeBuilder<WarehouseAccessGrantRecord> b)
    {
        b.ToTable("warehouse_access_grants", "inventory");
        b.HasKey(x => x.Id).HasName("pk_inventory_warehouse_access_grants");
        b.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        b.Property(x => x.PublicId).HasColumnName("public_id");
        b.Property(x => x.ActorId).HasColumnName("actor_id");
        b.Property(x => x.CompanyId).HasColumnName("company_id");
        b.Property(x => x.WarehouseId).HasColumnName("warehouse_id");
        b.Property(x => x.GrantedByActorId).HasColumnName("granted_by_actor_id");
        b.Property(x => x.GrantedAt).HasColumnName("granted_at");
        b.Property(x => x.RevokedByActorId).HasColumnName("revoked_by_actor_id");
        b.Property(x => x.RevokedAt).HasColumnName("revoked_at");

        b.HasOne<WarehouseRecord>().WithMany()
            .HasForeignKey(x => new { x.WarehouseId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_warehouse_access_grants_warehouse_company");

        b.HasIndex(x => x.PublicId).IsUnique()
            .HasDatabaseName("ux_inventory_warehouse_access_grants_public_id");
        b.HasIndex(x => new { x.ActorId, x.CompanyId, x.WarehouseId })
            .IsUnique()
            .HasFilter("revoked_at IS NULL")
            .HasDatabaseName("ux_inventory_warehouse_access_grants_active_scope");
        b.HasIndex(x => new { x.CompanyId, x.WarehouseId, x.ActorId })
            .HasDatabaseName("ix_inventory_warehouse_access_grants_scope");

        b.ToTable(t => t.HasCheckConstraint(
            "ck_inventory_warehouse_access_grants_revoke_evidence",
            "(revoked_at IS NULL AND revoked_by_actor_id IS NULL) OR (revoked_at IS NOT NULL AND revoked_by_actor_id IS NOT NULL)"));
    }
}
