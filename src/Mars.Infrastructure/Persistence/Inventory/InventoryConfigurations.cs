using Mars.Domain.Inventory;
using Mars.Infrastructure.Persistence.Products;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Mars.Infrastructure.Persistence.Inventory;

internal sealed class WarehouseConfiguration : IEntityTypeConfiguration<WarehouseRecord>
{
    public void Configure(EntityTypeBuilder<WarehouseRecord> b)
    {
        b.ToTable("warehouses", "inventory");
        b.HasKey(x => x.Id).HasName("pk_inventory_warehouses");
        b.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        b.Property(x => x.PublicId).HasColumnName("public_id");
        b.Property(x => x.CompanyId).HasColumnName("company_id");
        b.Property(x => x.Code).HasColumnName("code").IsRequired();
        b.Property(x => x.Name).HasColumnName("name").IsRequired();
        b.Property(x => x.State).HasColumnName("state").HasConversion<string>().HasMaxLength(16).IsRequired();
        b.Property(x => x.Version).HasColumnName("version").IsConcurrencyToken();
        b.Property(x => x.CreatedAt).HasColumnName("created_at");
        b.HasAlternateKey(x => new { x.Id, x.CompanyId }).HasName("ak_inventory_warehouses_id_company");
        b.HasIndex(x => x.PublicId).IsUnique().HasDatabaseName("ux_inventory_warehouses_public_id");
        b.HasIndex(x => new { x.CompanyId, x.Code }).IsUnique().HasDatabaseName("ux_inventory_warehouses_company_code");
        b.ToTable(t =>
        {
            t.HasCheckConstraint("ck_inventory_warehouses_code", "length(btrim(code)) > 0 AND code = btrim(code)");
            t.HasCheckConstraint("ck_inventory_warehouses_name", "length(btrim(name)) > 0 AND name = btrim(name)");
            t.HasCheckConstraint("ck_inventory_warehouses_state", "state IN ('Active','Inactive')");
            t.HasCheckConstraint("ck_inventory_warehouses_version", "version > 0");
        });
    }
}

internal sealed class LocationConfiguration : IEntityTypeConfiguration<LocationRecord>
{
    public void Configure(EntityTypeBuilder<LocationRecord> b)
    {
        b.ToTable("locations", "inventory");
        b.HasKey(x => x.Id).HasName("pk_inventory_locations");
        b.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        b.Property(x => x.PublicId).HasColumnName("public_id");
        b.Property(x => x.CompanyId).HasColumnName("company_id");
        b.Property(x => x.WarehouseId).HasColumnName("warehouse_id");
        b.Property(x => x.ParentLocationId).HasColumnName("parent_location_id");
        b.Property(x => x.Code).HasColumnName("code").IsRequired();
        b.Property(x => x.Name).HasColumnName("name").IsRequired();
        b.Property(x => x.StockBearing).HasColumnName("stock_bearing");
        b.Property(x => x.State).HasColumnName("state").HasConversion<string>().HasMaxLength(16).IsRequired();
        b.Property(x => x.Version).HasColumnName("version").IsConcurrencyToken();
        b.Property(x => x.CreatedAt).HasColumnName("created_at");

        b.HasAlternateKey(x => new { x.Id, x.CompanyId }).HasName("ak_inventory_locations_id_company");
        b.HasAlternateKey(x => new { x.Id, x.WarehouseId, x.CompanyId }).HasName("ak_inventory_locations_id_warehouse_company");
        b.HasIndex(x => x.PublicId).IsUnique().HasDatabaseName("ux_inventory_locations_public_id");
        b.HasIndex(x => new { x.WarehouseId, x.Code }).IsUnique().HasDatabaseName("ux_inventory_locations_warehouse_code");

        b.HasOne<WarehouseRecord>().WithMany()
            .HasForeignKey(x => new { x.WarehouseId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_locations_warehouse_company");

        b.HasOne<LocationRecord>().WithMany()
            .HasForeignKey(x => new { x.ParentLocationId, x.WarehouseId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.WarehouseId, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_locations_parent_warehouse_company");

        b.ToTable(t =>
        {
            t.HasCheckConstraint("ck_inventory_locations_code", "length(btrim(code)) > 0 AND code = btrim(code)");
            t.HasCheckConstraint("ck_inventory_locations_name", "length(btrim(name)) > 0 AND name = btrim(name)");
            t.HasCheckConstraint("ck_inventory_locations_state", "state IN ('Active','Inactive')");
            t.HasCheckConstraint("ck_inventory_locations_version", "version > 0");
            t.HasCheckConstraint("ck_inventory_locations_parent", "parent_location_id IS NULL OR parent_location_id <> id");
        });
    }
}

internal sealed class InventoryDispositionConfiguration : IEntityTypeConfiguration<InventoryDispositionRecord>
{
    public void Configure(EntityTypeBuilder<InventoryDispositionRecord> b)
    {
        b.ToTable("dispositions", "inventory");
        b.HasKey(x => x.Id).HasName("pk_inventory_dispositions");
        b.Property(x => x.Id).HasColumnName("id").ValueGeneratedNever();
        b.Property(x => x.Code).HasColumnName("code").HasConversion<string>().HasMaxLength(32).IsRequired();
        b.HasIndex(x => x.Code).IsUnique().HasDatabaseName("ux_inventory_dispositions_code");
        b.HasData(
            new InventoryDispositionRecord { Id = 1, Code = InventoryDispositionCode.Available },
            new InventoryDispositionRecord { Id = 2, Code = InventoryDispositionCode.Quarantine },
            new InventoryDispositionRecord { Id = 3, Code = InventoryDispositionCode.QualityHold },
            new InventoryDispositionRecord { Id = 4, Code = InventoryDispositionCode.Rework },
            new InventoryDispositionRecord { Id = 5, Code = InventoryDispositionCode.Damaged },
            new InventoryDispositionRecord { Id = 6, Code = InventoryDispositionCode.Transit });
        b.ToTable(t =>
            t.HasCheckConstraint(
                "ck_inventory_dispositions_code",
                "code IN ('Available','Quarantine','QualityHold','Rework','Damaged','Transit')"));
    }
}

internal sealed class InventoryLotConfiguration : IEntityTypeConfiguration<InventoryLotRecord>
{
    public void Configure(EntityTypeBuilder<InventoryLotRecord> b)
    {
        b.ToTable("lots", "inventory");
        b.HasKey(x => x.Id).HasName("pk_inventory_lots");
        b.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        b.Property(x => x.PublicId).HasColumnName("public_id");
        b.Property(x => x.CompanyId).HasColumnName("company_id");
        b.Property(x => x.ProductId).HasColumnName("product_id");
        b.Property(x => x.VariantId).HasColumnName("variant_id");
        b.Property(x => x.Code).HasColumnName("code").IsRequired();
        b.Property(x => x.ManufactureDate).HasColumnName("manufacture_date");
        b.Property(x => x.ExpiryDate).HasColumnName("expiry_date");
        b.Property(x => x.Version).HasColumnName("version").IsConcurrencyToken();
        b.Property(x => x.CreatedAt).HasColumnName("created_at");

        b.HasAlternateKey(x => new { x.Id, x.CompanyId }).HasName("ak_inventory_lots_id_company");
        b.HasIndex(x => x.PublicId).IsUnique().HasDatabaseName("ux_inventory_lots_public_id");
        b.HasIndex(x => new { x.CompanyId, x.ProductId, x.Code }).IsUnique()
            .HasFilter("variant_id IS NULL")
            .HasDatabaseName("ux_inventory_lots_product_code");
        b.HasIndex(x => new { x.CompanyId, x.ProductId, x.VariantId, x.Code }).IsUnique()
            .HasFilter("variant_id IS NOT NULL")
            .HasDatabaseName("ux_inventory_lots_variant_code");

        b.HasOne<ProductRecord>().WithMany()
            .HasForeignKey(x => new { x.ProductId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_lots_product_company");

        b.HasOne<ProductVariantRecord>().WithMany()
            .HasForeignKey(x => new { x.VariantId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_lots_variant_company");

        b.ToTable(t =>
        {
            t.HasCheckConstraint("ck_inventory_lots_code", "length(btrim(code)) > 0 AND code = btrim(code)");
            t.HasCheckConstraint("ck_inventory_lots_version", "version > 0");
            t.HasCheckConstraint("ck_inventory_lots_dates", "manufacture_date IS NULL OR expiry_date IS NULL OR expiry_date >= manufacture_date");
        });
    }
}

internal sealed class InventorySerialConfiguration : IEntityTypeConfiguration<InventorySerialRecord>
{
    public void Configure(EntityTypeBuilder<InventorySerialRecord> b)
    {
        b.ToTable("serials", "inventory");
        b.HasKey(x => x.Id).HasName("pk_inventory_serials");
        b.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        b.Property(x => x.PublicId).HasColumnName("public_id");
        b.Property(x => x.CompanyId).HasColumnName("company_id");
        b.Property(x => x.ProductId).HasColumnName("product_id");
        b.Property(x => x.VariantId).HasColumnName("variant_id");
        b.Property(x => x.LotId).HasColumnName("lot_id");
        b.Property(x => x.Value).HasColumnName("value").IsRequired();
        b.Property(x => x.Version).HasColumnName("version").IsConcurrencyToken();
        b.Property(x => x.CreatedAt).HasColumnName("created_at");

        b.HasAlternateKey(x => new { x.Id, x.CompanyId }).HasName("ak_inventory_serials_id_company");
        b.HasIndex(x => x.PublicId).IsUnique().HasDatabaseName("ux_inventory_serials_public_id");
        b.HasIndex(x => new { x.CompanyId, x.ProductId, x.Value }).IsUnique()
            .HasFilter("variant_id IS NULL")
            .HasDatabaseName("ux_inventory_serials_product_value");
        b.HasIndex(x => new { x.CompanyId, x.ProductId, x.VariantId, x.Value }).IsUnique()
            .HasFilter("variant_id IS NOT NULL")
            .HasDatabaseName("ux_inventory_serials_variant_value");

        b.HasOne<ProductRecord>().WithMany()
            .HasForeignKey(x => new { x.ProductId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_serials_product_company");

        b.HasOne<ProductVariantRecord>().WithMany()
            .HasForeignKey(x => new { x.VariantId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_serials_variant_company");

        b.HasOne<InventoryLotRecord>().WithMany()
            .HasForeignKey(x => new { x.LotId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_serials_lot_company");

        b.ToTable(t =>
        {
            t.HasCheckConstraint("ck_inventory_serials_value", "length(btrim(value)) > 0 AND value = btrim(value)");
            t.HasCheckConstraint("ck_inventory_serials_version", "version > 0");
        });
    }
}

internal sealed class InventoryMovementConfiguration : IEntityTypeConfiguration<InventoryMovementRecord>
{
    public void Configure(EntityTypeBuilder<InventoryMovementRecord> b)
    {
        b.ToTable("movements", "inventory");
        b.HasKey(x => x.Id).HasName("pk_inventory_movements");
        b.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        b.Property(x => x.PublicId).HasColumnName("public_id");
        b.Property(x => x.CompanyId).HasColumnName("company_id");
        b.Property(x => x.ProductId).HasColumnName("product_id");
        b.Property(x => x.VariantId).HasColumnName("variant_id");
        b.Property(x => x.UomId).HasColumnName("uom_id");
        b.Property(x => x.EnteredQuantity).HasColumnName("entered_quantity").HasPrecision(28, 9);
        b.Property(x => x.ConversionFactorSnapshot).HasColumnName("conversion_factor_snapshot").HasPrecision(28, 9);
        b.Property(x => x.BaseQuantity).HasColumnName("base_quantity").HasPrecision(28, 9);
        b.Property(x => x.SourceWarehouseId).HasColumnName("source_warehouse_id");
        b.Property(x => x.SourceLocationId).HasColumnName("source_location_id");
        b.Property(x => x.SourceDispositionId).HasColumnName("source_disposition_id");
        b.Property(x => x.TargetWarehouseId).HasColumnName("target_warehouse_id");
        b.Property(x => x.TargetLocationId).HasColumnName("target_location_id");
        b.Property(x => x.TargetDispositionId).HasColumnName("target_disposition_id");
        b.Property(x => x.LotId).HasColumnName("lot_id");
        b.Property(x => x.SerialId).HasColumnName("serial_id");
        b.Property(x => x.SourceModule).HasColumnName("source_module").HasMaxLength(64).IsRequired();
        b.Property(x => x.SourceEntityType).HasColumnName("source_entity_type").HasMaxLength(64).IsRequired();
        b.Property(x => x.SourceDocumentPublicId).HasColumnName("source_document_public_id");
        b.Property(x => x.SourceLinePublicId).HasColumnName("source_line_public_id");
        b.Property(x => x.PostedAt).HasColumnName("posted_at");
        b.Property(x => x.ActorId).HasColumnName("actor_id");
        b.Property(x => x.CorrelationId).HasColumnName("correlation_id").HasMaxLength(128).IsRequired();
        b.Property(x => x.ReversalOfMovementId).HasColumnName("reversal_of_movement_id");

        b.HasIndex(x => x.PublicId).IsUnique().HasDatabaseName("ux_inventory_movements_public_id");
        b.HasIndex(x => x.ReversalOfMovementId).IsUnique()
            .HasFilter("reversal_of_movement_id IS NOT NULL")
            .HasDatabaseName("ux_inventory_movements_reversal");
        b.HasIndex(x => new { x.CompanyId, x.ProductId, x.VariantId, x.PostedAt })
            .HasDatabaseName("ix_inventory_movements_product_posted");
        b.HasIndex(x => new { x.CompanyId, x.SourceDocumentPublicId, x.SourceLinePublicId })
            .HasDatabaseName("ix_inventory_movements_source");

        b.HasOne<ProductRecord>().WithMany()
            .HasForeignKey(x => new { x.ProductId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_movements_product_company");

        b.HasOne<ProductVariantRecord>().WithMany()
            .HasForeignKey(x => new { x.VariantId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_movements_variant_company");

        b.HasOne<UnitOfMeasureRecord>().WithMany()
            .HasForeignKey(x => new { x.UomId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_movements_uom_company");

        b.HasOne<WarehouseRecord>().WithMany()
            .HasForeignKey(x => new { x.SourceWarehouseId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_movements_source_warehouse_company");

        b.HasOne<WarehouseRecord>().WithMany()
            .HasForeignKey(x => new { x.TargetWarehouseId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_movements_target_warehouse_company");

        b.HasOne<LocationRecord>().WithMany()
            .HasForeignKey(x => new { x.SourceLocationId, x.SourceWarehouseId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.WarehouseId, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_movements_source_location");

        b.HasOne<LocationRecord>().WithMany()
            .HasForeignKey(x => new { x.TargetLocationId, x.TargetWarehouseId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.WarehouseId, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_movements_target_location");

        b.HasOne<InventoryDispositionRecord>().WithMany()
            .HasForeignKey(x => x.SourceDispositionId)
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_movements_source_disposition");

        b.HasOne<InventoryDispositionRecord>().WithMany()
            .HasForeignKey(x => x.TargetDispositionId)
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_movements_target_disposition");

        b.HasOne<InventoryLotRecord>().WithMany()
            .HasForeignKey(x => new { x.LotId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_movements_lot_company");

        b.HasOne<InventorySerialRecord>().WithMany()
            .HasForeignKey(x => new { x.SerialId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_movements_serial_company");

        b.HasOne<InventoryMovementRecord>().WithMany()
            .HasForeignKey(x => x.ReversalOfMovementId)
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_movements_reversal_original");

        b.ToTable(t =>
        {
            t.HasCheckConstraint("ck_inventory_movements_entered_quantity", "entered_quantity > 0");
            t.HasCheckConstraint("ck_inventory_movements_conversion", "conversion_factor_snapshot > 0");
            t.HasCheckConstraint("ck_inventory_movements_base_quantity", "base_quantity > 0");
            t.HasCheckConstraint(
                "ck_inventory_movements_source_side",
                "(source_warehouse_id IS NULL AND source_location_id IS NULL AND source_disposition_id IS NULL) OR " +
                "(source_warehouse_id IS NOT NULL AND source_disposition_id IS NOT NULL)");
            t.HasCheckConstraint(
                "ck_inventory_movements_target_side",
                "(target_warehouse_id IS NULL AND target_location_id IS NULL AND target_disposition_id IS NULL) OR " +
                "(target_warehouse_id IS NOT NULL AND target_disposition_id IS NOT NULL)");
            t.HasCheckConstraint(
                "ck_inventory_movements_has_side",
                "source_warehouse_id IS NOT NULL OR target_warehouse_id IS NOT NULL");
            t.HasCheckConstraint("ck_inventory_movements_source_module", "length(btrim(source_module)) > 0 AND source_module = btrim(source_module)");
            t.HasCheckConstraint("ck_inventory_movements_source_type", "length(btrim(source_entity_type)) > 0 AND source_entity_type = btrim(source_entity_type)");
            t.HasCheckConstraint("ck_inventory_movements_correlation", "length(btrim(correlation_id)) > 0 AND correlation_id = btrim(correlation_id)");
        });
    }
}

internal sealed class InventoryReservationConfiguration : IEntityTypeConfiguration<InventoryReservationRecord>
{
    public void Configure(EntityTypeBuilder<InventoryReservationRecord> b)
    {
        b.ToTable("reservations", "inventory");
        b.HasKey(x => x.Id).HasName("pk_inventory_reservations");
        b.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        b.Property(x => x.PublicId).HasColumnName("public_id");
        b.Property(x => x.CompanyId).HasColumnName("company_id");
        b.Property(x => x.SalesOrderPublicId).HasColumnName("sales_order_public_id");
        b.Property(x => x.SalesOrderVersion).HasColumnName("sales_order_version");
        b.Property(x => x.SalesOrderLinePublicId).HasColumnName("sales_order_line_public_id");
        b.Property(x => x.ProductId).HasColumnName("product_id");
        b.Property(x => x.VariantId).HasColumnName("variant_id");
        b.Property(x => x.WarehouseId).HasColumnName("warehouse_id");
        b.Property(x => x.UomId).HasColumnName("uom_id");
        b.Property(x => x.ConversionFactorSnapshot).HasColumnName("conversion_factor_snapshot").HasPrecision(28, 9);
        b.Property(x => x.CreatedAt).HasColumnName("created_at");

        b.HasAlternateKey(x => new { x.Id, x.CompanyId }).HasName("ak_inventory_reservations_id_company");
        b.HasIndex(x => x.PublicId).IsUnique().HasDatabaseName("ux_inventory_reservations_public_id");
        b.HasIndex(x => new { x.CompanyId, x.ProductId, x.VariantId, x.WarehouseId })
            .HasDatabaseName("ix_inventory_reservations_eligibility");
        b.HasIndex(x => new { x.CompanyId, x.SalesOrderPublicId, x.SalesOrderVersion, x.SalesOrderLinePublicId })
            .HasDatabaseName("ix_inventory_reservations_sales_source");

        b.HasOne<ProductRecord>().WithMany()
            .HasForeignKey(x => new { x.ProductId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_reservations_product_company");

        b.HasOne<ProductVariantRecord>().WithMany()
            .HasForeignKey(x => new { x.VariantId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_reservations_variant_company");

        b.HasOne<WarehouseRecord>().WithMany()
            .HasForeignKey(x => new { x.WarehouseId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_reservations_warehouse_company");

        b.HasOne<UnitOfMeasureRecord>().WithMany()
            .HasForeignKey(x => new { x.UomId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_reservations_uom_company");

        b.ToTable(t =>
        {
            t.HasCheckConstraint("ck_inventory_reservations_sales_version", "sales_order_version > 0");
            t.HasCheckConstraint("ck_inventory_reservations_conversion", "conversion_factor_snapshot > 0");
        });
    }
}

internal sealed class InventoryReservationMovementConfiguration : IEntityTypeConfiguration<InventoryReservationMovementRecord>
{
    public void Configure(EntityTypeBuilder<InventoryReservationMovementRecord> b)
    {
        b.ToTable("reservation_movements", "inventory");
        b.HasKey(x => x.Id).HasName("pk_inventory_reservation_movements");
        b.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        b.Property(x => x.PublicId).HasColumnName("public_id");
        b.Property(x => x.ReservationId).HasColumnName("reservation_id");
        b.Property(x => x.CompanyId).HasColumnName("company_id");
        b.Property(x => x.Kind).HasColumnName("kind").HasConversion<string>().HasMaxLength(16).IsRequired();
        b.Property(x => x.EnteredQuantity).HasColumnName("entered_quantity").HasPrecision(28, 9);
        b.Property(x => x.BaseQuantity).HasColumnName("base_quantity").HasPrecision(28, 9);
        b.Property(x => x.SourceModule).HasColumnName("source_module").HasMaxLength(64);
        b.Property(x => x.SourceDocumentPublicId).HasColumnName("source_document_public_id");
        b.Property(x => x.SourceLinePublicId).HasColumnName("source_line_public_id");
        b.Property(x => x.ActorId).HasColumnName("actor_id");
        b.Property(x => x.CorrelationId).HasColumnName("correlation_id").HasMaxLength(128).IsRequired();
        b.Property(x => x.OccurredAt).HasColumnName("occurred_at");

        b.HasIndex(x => x.PublicId).IsUnique().HasDatabaseName("ux_inventory_reservation_movements_public_id");
        b.HasIndex(x => new { x.ReservationId, x.OccurredAt })
            .HasDatabaseName("ix_inventory_reservation_movements_history");

        b.HasOne<InventoryReservationRecord>().WithMany()
            .HasForeignKey(x => new { x.ReservationId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_inventory_reservation_movements_reservation_company");

        b.ToTable(t =>
        {
            t.HasCheckConstraint("ck_inventory_reservation_movements_kind", "kind IN ('Create','Increase','Release','Consume')");
            t.HasCheckConstraint("ck_inventory_reservation_movements_entered_quantity", "entered_quantity > 0");
            t.HasCheckConstraint("ck_inventory_reservation_movements_base_quantity", "base_quantity > 0");
            t.HasCheckConstraint("ck_inventory_reservation_movements_correlation", "length(btrim(correlation_id)) > 0 AND correlation_id = btrim(correlation_id)");
        });
    }
}
