using Mars.Domain.Warehouse;
using Mars.Infrastructure.Persistence.Inventory;
using Mars.Infrastructure.Persistence.Products;
using Microsoft.EntityFrameworkCore;

namespace Mars.Infrastructure.Persistence.Warehouse;

public static class WarehouseModelConfiguration
{
    public static void Configure(ModelBuilder m)
    {
        Operation(m); Disposition(m); Pick(m); Package(m); PackageItem(m);
        StageLoad(m); StageLoadPackage(m);
        Transfer(m); TransferLine(m); TransferEffect(m);
        CountSession(m); CountScope(m); CountLine(m); CountObservation(m); CountEffect(m);
        Scrap(m); Offline(m);
    }

    private static void Operation(ModelBuilder m)
    {
        var b=m.Entity<WarehouseOperationRecord>();
        b.ToTable("operations","warehouse"); Id(b); Public(b); Company(b);
        Enum(b.Property(x=>x.Kind).HasColumnName("kind"),20);
        b.Property(x=>x.WarehouseId).HasColumnName("warehouse_id");
        b.Property(x=>x.SourceLocationId).HasColumnName("source_location_id");
        b.Property(x=>x.TargetLocationId).HasColumnName("target_location_id");
        b.Property(x=>x.ProductId).HasColumnName("product_id");
        b.Property(x=>x.VariantId).HasColumnName("variant_id");
        b.Property(x=>x.UomId).HasColumnName("uom_id");
        b.Property(x=>x.LotId).HasColumnName("lot_id");
        b.Property(x=>x.SerialId).HasColumnName("serial_id");
        b.Property(x=>x.Disposition).HasColumnName("disposition").HasConversion<string>().HasMaxLength(24);
        Qty(b.Property(x=>x.Quantity).HasColumnName("quantity"));
        b.Property(x=>x.InventoryMovementPublicId).HasColumnName("inventory_movement_public_id");
        b.Property(x=>x.SourceDocumentPublicId).HasColumnName("source_document_public_id");
        b.Property(x=>x.SourceLinePublicId).HasColumnName("source_line_public_id");
        Enum(b.Property(x=>x.State).HasColumnName("state"),24);
        Version(b.Property(x=>x.Version).HasColumnName("version"));
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.Property(x=>x.CompletedAt).HasColumnName("completed_at");
        b.HasIndex(x=>x.InventoryMovementPublicId).IsUnique().HasDatabaseName("ux_warehouse_operations_inventory_movement");
        b.HasIndex(x=>new{x.CompanyId,x.WarehouseId,x.State}).HasDatabaseName("ix_warehouse_operations_work");
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_warehouse_operations_quantity","quantity > 0");
            t.HasCheckConstraint("ck_warehouse_operations_locations","source_location_id IS DISTINCT FROM target_location_id");
            t.HasCheckConstraint("ck_warehouse_operations_version","version > 0");
        });
        MasterFks(b,"warehouse_operations");
    }

    private static void Disposition(ModelBuilder m)
    {
        var b=m.Entity<WarehouseDispositionRecord>();
        b.ToTable("disposition_effects","warehouse"); Id(b); Public(b); Company(b);
        b.Property(x=>x.WarehouseId).HasColumnName("warehouse_id");
        b.Property(x=>x.LocationId).HasColumnName("location_id");
        b.Property(x=>x.ProductId).HasColumnName("product_id");
        b.Property(x=>x.VariantId).HasColumnName("variant_id");
        b.Property(x=>x.UomId).HasColumnName("uom_id");
        b.Property(x=>x.LotId).HasColumnName("lot_id");
        b.Property(x=>x.SerialId).HasColumnName("serial_id");
        b.Property(x=>x.SourceDisposition).HasColumnName("source_disposition").HasConversion<string>().HasMaxLength(24);
        b.Property(x=>x.TargetDisposition).HasColumnName("target_disposition").HasConversion<string>().HasMaxLength(24);
        Qty(b.Property(x=>x.Quantity).HasColumnName("quantity"));
        b.Property(x=>x.Reason).HasColumnName("reason").HasMaxLength(512).IsRequired();
        b.Property(x=>x.InventoryMovementPublicId).HasColumnName("inventory_movement_public_id");
        b.Property(x=>x.SourceDocumentPublicId).HasColumnName("source_document_public_id");
        b.Property(x=>x.SourceLinePublicId).HasColumnName("source_line_public_id");
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasIndex(x=>x.InventoryMovementPublicId).IsUnique();
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_warehouse_disposition_quantity","quantity > 0");
            t.HasCheckConstraint("ck_warehouse_disposition_change","source_disposition <> target_disposition");
        });
        MasterFks(b,"warehouse_disposition");
    }

    private static void Pick(ModelBuilder m)
    {
        var b=m.Entity<PickWorkRecord>();
        b.ToTable("pick_works","warehouse"); Id(b); Public(b); Company(b);
        b.Property(x=>x.DispatchPublicId).HasColumnName("dispatch_public_id");
        b.Property(x=>x.DispatchLinePublicId).HasColumnName("dispatch_line_public_id");
        b.Property(x=>x.WarehouseId).HasColumnName("warehouse_id");
        b.Property(x=>x.LocationId).HasColumnName("location_id");
        b.Property(x=>x.ProductId).HasColumnName("product_id");
        b.Property(x=>x.VariantId).HasColumnName("variant_id");
        b.Property(x=>x.UomId).HasColumnName("uom_id");
        Qty(b.Property(x=>x.ConversionFactorSnapshot).HasColumnName("conversion_factor_snapshot"));
        b.Property(x=>x.LotId).HasColumnName("lot_id");
        b.Property(x=>x.SerialId).HasColumnName("serial_id");
        b.Property(x=>x.ReservationPublicId).HasColumnName("reservation_public_id");
        Qty(b.Property(x=>x.Quantity).HasColumnName("quantity"));
        Enum(b.Property(x=>x.Strategy).HasColumnName("strategy"),12);
        b.Property(x=>x.StrategyOverride).HasColumnName("strategy_override");
        b.Property(x=>x.OverrideReason).HasColumnName("override_reason").HasMaxLength(512);
        Enum(b.Property(x=>x.State).HasColumnName("state"),24);
        Version(b.Property(x=>x.Version).HasColumnName("version"));
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasIndex(x=>new{x.CompanyId,x.DispatchPublicId,x.DispatchLinePublicId}).HasDatabaseName("ix_warehouse_pick_dispatch_line");
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_warehouse_pick_quantity","quantity > 0");
            t.HasCheckConstraint("ck_warehouse_pick_conversion","conversion_factor_snapshot > 0");
            t.HasCheckConstraint("ck_warehouse_pick_override_reason","NOT strategy_override OR length(btrim(override_reason)) > 0");
            t.HasCheckConstraint("ck_warehouse_pick_version","version > 0");
        });
        MasterFks(b,"warehouse_pick");
    }

    private static void Package(ModelBuilder m)
    {
        var b=m.Entity<PackageRecord>();
        b.ToTable("packages","warehouse"); Id(b); Public(b); Company(b);
        b.Property(x=>x.PackageCode).HasColumnName("package_code").HasMaxLength(64).IsRequired();
        b.Property(x=>x.DispatchPublicId).HasColumnName("dispatch_public_id");
        b.Property(x=>x.WarehouseId).HasColumnName("warehouse_id");
        Enum(b.Property(x=>x.State).HasColumnName("state"),16);
        b.Property(x=>x.TrackingReference).HasColumnName("tracking_reference").HasMaxLength(128);
        b.Property(x=>x.CarrierReference).HasColumnName("carrier_reference").HasMaxLength(128);
        Version(b.Property(x=>x.Version).HasColumnName("version"));
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasIndex(x=>new{x.CompanyId,x.PackageCode}).IsUnique();
        b.HasIndex(x=>new{x.CompanyId,x.DispatchPublicId});
        b.HasOne<WarehouseRecord>().WithMany().HasForeignKey(x=>new{x.WarehouseId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.ToTable(t=>t.HasCheckConstraint("ck_warehouse_packages_code","length(btrim(package_code)) > 0 AND package_code=btrim(package_code)"));
    }

    private static void PackageItem(ModelBuilder m)
    {
        var b=m.Entity<PackageItemRecord>();
        b.ToTable("package_items","warehouse"); Id(b); Public(b); Company(b);
        b.Property(x=>x.PackageId).HasColumnName("package_id");
        b.Property(x=>x.DispatchLinePublicId).HasColumnName("dispatch_line_public_id");
        Qty(b.Property(x=>x.Quantity).HasColumnName("quantity"));
        b.Property(x=>x.LotId).HasColumnName("lot_id");
        b.Property(x=>x.SerialId).HasColumnName("serial_id");
        b.HasIndex(x=>new{x.PackageId,x.DispatchLinePublicId,x.LotId,x.SerialId});
        b.HasOne<PackageRecord>().WithMany().HasForeignKey(x=>new{x.PackageId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.ToTable(t=>t.HasCheckConstraint("ck_warehouse_package_items_quantity","quantity > 0"));
    }

    private static void StageLoad(ModelBuilder m)
    {
        var b=m.Entity<StageLoadWorkRecord>();
        b.ToTable("stage_load_works","warehouse"); Id(b); Public(b); Company(b);
        b.Property(x=>x.DispatchPublicId).HasColumnName("dispatch_public_id");
        b.Property(x=>x.WarehouseId).HasColumnName("warehouse_id");
        Enum(b.Property(x=>x.Kind).HasColumnName("kind"),12);
        Enum(b.Property(x=>x.State).HasColumnName("state"),24);
        Version(b.Property(x=>x.Version).HasColumnName("version"));
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasIndex(x=>new{x.CompanyId,x.DispatchPublicId,x.Kind}).IsUnique();
        b.HasOne<WarehouseRecord>().WithMany().HasForeignKey(x=>new{x.WarehouseId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
    }

    private static void StageLoadPackage(ModelBuilder m)
    {
        var b=m.Entity<StageLoadPackageRecord>();
        b.ToTable("stage_load_packages","warehouse"); Id(b); Company(b);
        b.Property(x=>x.StageLoadWorkId).HasColumnName("stage_load_work_id");
        b.Property(x=>x.PackageId).HasColumnName("package_id");
        b.HasIndex(x=>new{x.StageLoadWorkId,x.PackageId}).IsUnique();
        b.HasOne<StageLoadWorkRecord>().WithMany().HasForeignKey(x=>new{x.StageLoadWorkId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.HasOne<PackageRecord>().WithMany().HasForeignKey(x=>new{x.PackageId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
    }

    private static void Transfer(ModelBuilder m)
    {
        var b=m.Entity<WarehouseTransferRecord>();
        b.ToTable("transfers","warehouse"); Id(b); Public(b); Company(b);
        b.Property(x=>x.Number).HasColumnName("number").HasMaxLength(64).IsRequired();
        b.Property(x=>x.SourceWarehouseId).HasColumnName("source_warehouse_id");
        b.Property(x=>x.TargetWarehouseId).HasColumnName("target_warehouse_id");
        Enum(b.Property(x=>x.State).HasColumnName("state"),32);
        Version(b.Property(x=>x.Version).HasColumnName("version"));
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.Property(x=>x.IssuedAt).HasColumnName("issued_at");
        b.Property(x=>x.ClosedAt).HasColumnName("closed_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId});
        b.HasIndex(x=>new{x.CompanyId,x.Number}).IsUnique();
        b.HasIndex(x=>new{x.CompanyId,x.State,x.CreatedAt});
        b.HasOne<WarehouseRecord>().WithMany().HasForeignKey(x=>new{x.SourceWarehouseId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.HasOne<WarehouseRecord>().WithMany().HasForeignKey(x=>new{x.TargetWarehouseId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_warehouse_transfer_number","length(btrim(number)) > 0 AND number=btrim(number)");
            t.HasCheckConstraint("ck_warehouse_transfer_distinct","source_warehouse_id <> target_warehouse_id");
            t.HasCheckConstraint("ck_warehouse_transfer_version","version > 0");
        });
    }

    private static void TransferLine(ModelBuilder m)
    {
        var b=m.Entity<WarehouseTransferLineRecord>();
        b.ToTable("transfer_lines","warehouse"); Id(b); Public(b); Company(b);
        b.Property(x=>x.TransferId).HasColumnName("transfer_id");
        b.Property(x=>x.Sequence).HasColumnName("sequence");
        b.Property(x=>x.ProductId).HasColumnName("product_id");
        b.Property(x=>x.VariantId).HasColumnName("variant_id");
        b.Property(x=>x.UomId).HasColumnName("uom_id");
        Qty(b.Property(x=>x.ConversionFactorSnapshot).HasColumnName("conversion_factor_snapshot"));
        Qty(b.Property(x=>x.RequestedQuantity).HasColumnName("requested_quantity"));
        Qty(b.Property(x=>x.IssuedQuantity).HasColumnName("issued_quantity"));
        Qty(b.Property(x=>x.ReceivedQuantity).HasColumnName("received_quantity"));
        Qty(b.Property(x=>x.DamagedReceivedQuantity).HasColumnName("damaged_received_quantity"));
        Qty(b.Property(x=>x.ResolvedLossQuantity).HasColumnName("resolved_loss_quantity"));
        b.Property(x=>x.SourceLocationId).HasColumnName("source_location_id");
        b.Property(x=>x.TargetLocationId).HasColumnName("target_location_id");
        b.Property(x=>x.LotId).HasColumnName("lot_id");
        b.Property(x=>x.SerialId).HasColumnName("serial_id");
        b.HasIndex(x=>new{x.TransferId,x.Sequence}).IsUnique();
        b.HasOne<WarehouseTransferRecord>().WithMany().HasForeignKey(x=>new{x.TransferId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_warehouse_transfer_line_sequence","sequence > 0");
            t.HasCheckConstraint("ck_warehouse_transfer_line_requested","requested_quantity > 0");
            t.HasCheckConstraint("ck_warehouse_transfer_line_conversion","conversion_factor_snapshot > 0");
            t.HasCheckConstraint("ck_warehouse_transfer_line_progress","issued_quantity >= 0 AND received_quantity >= 0 AND damaged_received_quantity >= 0 AND resolved_loss_quantity >= 0 AND received_quantity + resolved_loss_quantity <= issued_quantity");
        });
    }

    private static void TransferEffect(ModelBuilder m)
    {
        var b=m.Entity<WarehouseTransferEffectRecord>();
        b.ToTable("transfer_effect_links","warehouse"); Id(b); Public(b); Company(b);
        b.Property(x=>x.TransferLineId).HasColumnName("transfer_line_id");
        b.Property(x=>x.InventoryMovementPublicId).HasColumnName("inventory_movement_public_id");
        b.Property(x=>x.EffectKind).HasColumnName("effect_kind").HasMaxLength(24).IsRequired();
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasIndex(x=>x.InventoryMovementPublicId).IsUnique();
        b.HasOne<WarehouseTransferLineRecord>().WithMany().HasForeignKey(x=>new{x.TransferLineId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
    }

    private static void CountSession(ModelBuilder m)
    {
        var b=m.Entity<StockCountSessionRecord>();
        b.ToTable("stock_count_sessions","warehouse"); Id(b); Public(b); Company(b);
        b.Property(x=>x.Number).HasColumnName("number").HasMaxLength(64).IsRequired();
        b.Property(x=>x.WarehouseId).HasColumnName("warehouse_id");
        Enum(b.Property(x=>x.State).HasColumnName("state"),24);
        Version(b.Property(x=>x.Version).HasColumnName("version"));
        b.Property(x=>x.SnapshotMovementId).HasColumnName("snapshot_movement_id");
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x=>x.ApprovalDecisionPublicId).HasColumnName("approval_decision_public_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.Property(x=>x.StartedAt).HasColumnName("started_at");
        b.Property(x=>x.ReviewedAt).HasColumnName("reviewed_at");
        b.Property(x=>x.PostedAt).HasColumnName("posted_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId});
        b.HasIndex(x=>new{x.CompanyId,x.Number}).IsUnique();
        b.HasOne<WarehouseRecord>().WithMany().HasForeignKey(x=>new{x.WarehouseId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
    }

    private static void CountScope(ModelBuilder m)
    {
        var b=m.Entity<StockCountScopeRecord>();
        b.ToTable("stock_count_scopes","warehouse"); Id(b); Company(b);
        b.Property(x=>x.CountSessionId).HasColumnName("count_session_id");
        b.Property(x=>x.LocationId).HasColumnName("location_id");
        b.HasIndex(x=>new{x.CountSessionId,x.LocationId}).IsUnique();
        b.HasOne<StockCountSessionRecord>().WithMany().HasForeignKey(x=>new{x.CountSessionId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
    }

    private static void CountLine(ModelBuilder m)
    {
        var b=m.Entity<StockCountLineRecord>();
        b.ToTable("stock_count_lines","warehouse"); Id(b); Public(b); Company(b);
        b.Property(x=>x.CountSessionId).HasColumnName("count_session_id");
        b.Property(x=>x.ProductId).HasColumnName("product_id");
        b.Property(x=>x.VariantId).HasColumnName("variant_id");
        b.Property(x=>x.UomId).HasColumnName("uom_id");
        Qty(b.Property(x=>x.ConversionFactorSnapshot).HasColumnName("conversion_factor_snapshot"));
        b.Property(x=>x.LocationId).HasColumnName("location_id");
        b.Property(x=>x.DispositionId).HasColumnName("disposition_id");
        b.Property(x=>x.LotId).HasColumnName("lot_id");
        b.Property(x=>x.SerialId).HasColumnName("serial_id");
        Qty(b.Property(x=>x.ExpectedStartQuantity).HasColumnName("expected_start_quantity"));
        Qty(b.Property(x=>x.NetInterveningQuantity).HasColumnName("net_intervening_quantity"));
        Qty(b.Property(x=>x.ExpectedReconciliationQuantity).HasColumnName("expected_reconciliation_quantity"));
        Qty(b.Property(x=>x.AcceptedCountQuantity).HasColumnName("accepted_count_quantity"));
        Qty(b.Property(x=>x.DiscrepancyQuantity).HasColumnName("discrepancy_quantity"));
        b.HasIndex(x=>new{x.CountSessionId,x.ProductId,x.VariantId,x.LocationId,x.DispositionId,x.LotId,x.SerialId}).IsUnique();
        b.HasOne<StockCountSessionRecord>().WithMany().HasForeignKey(x=>new{x.CountSessionId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
    }

    private static void CountObservation(ModelBuilder m)
    {
        var b=m.Entity<StockCountObservationRecord>();
        b.ToTable("stock_count_observations","warehouse"); Id(b); Public(b); Company(b);
        b.Property(x=>x.CountLineId).HasColumnName("count_line_id");
        Qty(b.Property(x=>x.CountedQuantity).HasColumnName("counted_quantity"));
        b.Property(x=>x.IsRecount).HasColumnName("is_recount");
        b.Property(x=>x.ActorId).HasColumnName("actor_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasOne<StockCountLineRecord>().WithMany().HasForeignKey(x=>new{x.CountLineId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.ToTable(t=>t.HasCheckConstraint("ck_warehouse_count_observation_nonnegative","counted_quantity >= 0"));
    }

    private static void CountEffect(ModelBuilder m)
    {
        var b=m.Entity<StockCountEffectRecord>();
        b.ToTable("stock_count_effect_links","warehouse"); Id(b); Public(b); Company(b);
        b.Property(x=>x.CountLineId).HasColumnName("count_line_id");
        b.Property(x=>x.InventoryMovementPublicId).HasColumnName("inventory_movement_public_id");
        b.Property(x=>x.IsReversal).HasColumnName("is_reversal");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasIndex(x=>x.InventoryMovementPublicId).IsUnique();
        b.HasOne<StockCountLineRecord>().WithMany().HasForeignKey(x=>new{x.CountLineId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
    }

    private static void Scrap(ModelBuilder m)
    {
        var b=m.Entity<WarehouseScrapRecord>();
        b.ToTable("scrap_requests","warehouse"); Id(b); Public(b); Company(b);
        b.Property(x=>x.Number).HasColumnName("number").HasMaxLength(64).IsRequired();
        b.Property(x=>x.WarehouseId).HasColumnName("warehouse_id");
        b.Property(x=>x.LocationId).HasColumnName("location_id");
        b.Property(x=>x.ProductId).HasColumnName("product_id");
        b.Property(x=>x.VariantId).HasColumnName("variant_id");
        b.Property(x=>x.UomId).HasColumnName("uom_id");
        Qty(b.Property(x=>x.ConversionFactorSnapshot).HasColumnName("conversion_factor_snapshot"));
        b.Property(x=>x.DispositionId).HasColumnName("disposition_id");
        b.Property(x=>x.LotId).HasColumnName("lot_id");
        b.Property(x=>x.SerialId).HasColumnName("serial_id");
        Qty(b.Property(x=>x.Quantity).HasColumnName("quantity"));
        b.Property(x=>x.Reason).HasColumnName("reason").HasMaxLength(512).IsRequired();
        Enum(b.Property(x=>x.State).HasColumnName("state"),24);
        Version(b.Property(x=>x.Version).HasColumnName("version"));
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x=>x.ApprovalDecisionPublicId).HasColumnName("approval_decision_public_id");
        b.Property(x=>x.InventoryMovementPublicId).HasColumnName("inventory_movement_public_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.Property(x=>x.PostedAt).HasColumnName("posted_at");
        b.HasIndex(x=>new{x.CompanyId,x.Number}).IsUnique();
        b.HasIndex(x=>x.InventoryMovementPublicId).IsUnique().HasFilter("inventory_movement_public_id IS NOT NULL");
        b.ToTable(t=>t.HasCheckConstraint("ck_warehouse_scrap_quantity","quantity > 0"));
    }

    private static void Offline(ModelBuilder m)
    {
        var b=m.Entity<WarehouseOfflineOperationRecord>();
        b.ToTable("offline_operations","warehouse"); Id(b); Public(b); Company(b);
        b.Property(x=>x.ClientOperationId).HasColumnName("client_operation_id");
        b.Property(x=>x.WarehouseId).HasColumnName("warehouse_id");
        b.Property(x=>x.OperationType).HasColumnName("operation_type").HasMaxLength(64).IsRequired();
        b.Property(x=>x.WorkPublicId).HasColumnName("work_public_id");
        b.Property(x=>x.ExpectedVersion).HasColumnName("expected_version");
        b.Property(x=>x.ScanIdentity).HasColumnName("scan_identity").HasMaxLength(256).IsRequired();
        b.Property(x=>x.LocalTimestamp).HasColumnName("local_timestamp");
        Enum(b.Property(x=>x.State).HasColumnName("state"),16);
        b.Property(x=>x.ConflictCode).HasColumnName("conflict_code").HasMaxLength(128);
        b.Property(x=>x.ServerResultPublicId).HasColumnName("server_result_public_id");
        b.Property(x=>x.ActorId).HasColumnName("actor_id");
        b.Property(x=>x.CorrelationId).HasColumnName("correlation_id").HasMaxLength(128).IsRequired();
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasIndex(x=>new{x.CompanyId,x.ClientOperationId}).IsUnique();
        b.HasOne<WarehouseRecord>().WithMany().HasForeignKey(x=>new{x.WarehouseId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
    }

    private static void MasterFks<T>(Microsoft.EntityFrameworkCore.Metadata.Builders.EntityTypeBuilder<T> b,string prefix) where T:class
    {
        b.HasOne(typeof(WarehouseRecord)).WithMany().HasForeignKey("WarehouseId","CompanyId")
            .HasPrincipalKey("Id","CompanyId").OnDelete(DeleteBehavior.Restrict);
        b.HasOne(typeof(ProductRecord)).WithMany().HasForeignKey("ProductId","CompanyId")
            .HasPrincipalKey("Id","CompanyId").OnDelete(DeleteBehavior.Restrict);
        b.HasOne(typeof(ProductVariantRecord)).WithMany().HasForeignKey("VariantId","CompanyId")
            .HasPrincipalKey("Id","CompanyId").OnDelete(DeleteBehavior.Restrict);
        b.HasOne(typeof(UnitOfMeasureRecord)).WithMany().HasForeignKey("UomId","CompanyId")
            .HasPrincipalKey("Id","CompanyId").OnDelete(DeleteBehavior.Restrict);
    }

    private static void Id<T>(Microsoft.EntityFrameworkCore.Metadata.Builders.EntityTypeBuilder<T> b) where T:class =>
        b.Property<long>("Id").HasColumnName("id").UseIdentityByDefaultColumn();
    private static void Public<T>(Microsoft.EntityFrameworkCore.Metadata.Builders.EntityTypeBuilder<T> b) where T:class
    {
        b.Property<Guid>("PublicId").HasColumnName("public_id");
        b.HasIndex("PublicId").IsUnique();
    }
    private static void Company<T>(Microsoft.EntityFrameworkCore.Metadata.Builders.EntityTypeBuilder<T> b) where T:class =>
        b.Property<Guid>("CompanyId").HasColumnName("company_id");
    private static void Qty(Microsoft.EntityFrameworkCore.Metadata.Builders.PropertyBuilder<decimal> p)=>p.HasPrecision(28,9);
    private static void Qty(Microsoft.EntityFrameworkCore.Metadata.Builders.PropertyBuilder<decimal?> p)=>p.HasPrecision(28,9);
    private static void Version(Microsoft.EntityFrameworkCore.Metadata.Builders.PropertyBuilder<long> p)=>p.IsConcurrencyToken();
    private static void Enum<TEnum>(Microsoft.EntityFrameworkCore.Metadata.Builders.PropertyBuilder<TEnum> p,int max) where TEnum:struct,Enum=>
        p.HasConversion<string>().HasMaxLength(max).IsRequired();
}
