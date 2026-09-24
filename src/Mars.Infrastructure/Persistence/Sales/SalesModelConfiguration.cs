using Mars.Domain.Sales;
using Mars.Infrastructure.Persistence.Inventory;
using Mars.Infrastructure.Persistence.Parties;
using Mars.Infrastructure.Persistence.Products;
using Microsoft.EntityFrameworkCore;

namespace Mars.Infrastructure.Persistence.Sales;

internal static class SalesModelConfiguration
{
    public static void Configure(ModelBuilder m)
    {
        Quote(m);
        QuoteRevision(m);
        QuoteLine(m);
        QuoteConversion(m);
        Order(m);
        OrderVersion(m);
        OrderLine(m);
        Amendment(m);
        AmendmentDelta(m);
        Dispatch(m);
        DispatchLine(m);
        DispatchSourceAllocation(m);
        DispatchEffect(m);
        Invoice(m);
        InvoiceLine(m);
        InvoiceSource(m);
    }

    private static void Quote(ModelBuilder m)
    {
        var b=m.Entity<QuoteRecord>();
        b.ToTable("quotes","sales"); Id(b); Public(b); Company(b);
        b.Property(x=>x.Number).HasColumnName("number").HasMaxLength(64).IsRequired();
        b.Property(x=>x.CustomerPartyId).HasColumnName("customer_party_id");
        b.Property(x=>x.CustomerCodeSnapshot).HasColumnName("customer_code_snapshot").IsRequired();
        b.Property(x=>x.CustomerNameSnapshot).HasColumnName("customer_name_snapshot").IsRequired();
        b.Property(x=>x.CurrencyCode).HasColumnName("currency_code").HasMaxLength(3).IsRequired();
        b.Property(x=>x.PaymentTerms).HasColumnName("payment_terms");
        Enum(b.Property(x=>x.State).HasColumnName("state"),32);
        b.Property(x=>x.CurrentRevisionNumber).HasColumnName("current_revision_number");
        Version(b.Property(x=>x.Version).HasColumnName("version"));
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.Property(x=>x.AcceptedAt).HasColumnName("accepted_at");
        b.Property(x=>x.ExpiredAt).HasColumnName("expired_at");
        b.Property(x=>x.CancelledAt).HasColumnName("cancelled_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId}).HasName("ak_sales_quotes_id_company");
        b.HasIndex(x=>new{x.CompanyId,x.Number}).IsUnique().HasDatabaseName("ux_sales_quotes_company_number");
        b.HasIndex(x=>new{x.CompanyId,x.State,x.CreatedAt}).HasDatabaseName("ix_sales_quotes_work_queue");
        b.HasOne<PartyRecord>().WithMany().HasForeignKey(x=>new{x.CustomerPartyId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_quotes_customer_company");
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_sales_quotes_number","length(btrim(number)) > 0 AND number = btrim(number)");
            t.HasCheckConstraint("ck_sales_quotes_currency","currency_code ~ '^[A-Z]{3}$'");
            t.HasCheckConstraint("ck_sales_quotes_revision","current_revision_number > 0");
            t.HasCheckConstraint("ck_sales_quotes_version","version > 0");
        });
    }

    private static void QuoteRevision(ModelBuilder m)
    {
        var b=m.Entity<QuoteRevisionRecord>();
        b.ToTable("quote_revisions","sales"); Id(b); Public(b); Company(b);
        b.Property(x=>x.QuoteId).HasColumnName("quote_id");
        b.Property(x=>x.RevisionNumber).HasColumnName("revision_number");
        Decimal(b.Property(x=>x.DocumentDiscountPercent).HasColumnName("document_discount_percent"),12,6);
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.Property(x=>x.SubmittedForApprovalAt).HasColumnName("submitted_for_approval_at");
        b.Property(x=>x.SentToCustomerAt).HasColumnName("sent_to_customer_at");
        b.Property(x=>x.AcceptedAt).HasColumnName("accepted_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId}).HasName("ak_sales_quote_revisions_id_company");
        b.HasIndex(x=>new{x.QuoteId,x.RevisionNumber}).IsUnique().HasDatabaseName("ux_sales_quote_revisions_number");
        b.HasOne<QuoteRecord>().WithMany().HasForeignKey(x=>new{x.QuoteId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_quote_revisions_quote_company");
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_sales_quote_revisions_number","revision_number > 0");
            t.HasCheckConstraint("ck_sales_quote_revisions_discount","document_discount_percent >= 0 AND document_discount_percent <= 100");
        });
    }

    private static void QuoteLine(ModelBuilder m)
    {
        var b=m.Entity<QuoteLineRecord>();
        b.ToTable("quote_lines","sales"); Id(b); Public(b); Company(b);
        b.Property(x=>x.RevisionId).HasColumnName("revision_id");
        b.Property(x=>x.Sequence).HasColumnName("sequence");
        Trade(b);
        Money(b.Property(x=>x.Quantity).HasColumnName("quantity"));
        Money(b.Property(x=>x.UnitPrice).HasColumnName("unit_price"));
        Decimal(b.Property(x=>x.LineDiscountPercent).HasColumnName("line_discount_percent"),12,6);
        Decimal(b.Property(x=>x.TaxPercent).HasColumnName("tax_percent"),12,6);
        b.HasIndex(x=>new{x.RevisionId,x.Sequence}).IsUnique().HasDatabaseName("ux_sales_quote_lines_revision_sequence");
        b.HasOne<QuoteRevisionRecord>().WithMany().HasForeignKey(x=>new{x.RevisionId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_quote_lines_revision_company");
        TradeForeignKeys(b);
        LineChecks(b,"quote_lines");
    }

    private static void QuoteConversion(ModelBuilder m)
    {
        var b=m.Entity<QuoteConversionLinkRecord>();
        b.ToTable("quote_conversion_links","sales"); Id(b); Public(b); Company(b);
        b.Property(x=>x.QuoteRevisionId).HasColumnName("quote_revision_id");
        b.Property(x=>x.QuoteLinePublicId).HasColumnName("quote_line_public_id");
        b.Property(x=>x.SalesOrderId).HasColumnName("sales_order_id");
        b.Property(x=>x.SalesOrderVersionNumber).HasColumnName("sales_order_version_number");
        b.Property(x=>x.SalesOrderLinePublicId).HasColumnName("sales_order_line_public_id");
        Money(b.Property(x=>x.Quantity).HasColumnName("quantity"));
        b.Property(x=>x.ActorId).HasColumnName("actor_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasIndex(x=>new{x.CompanyId,x.QuoteRevisionId,x.QuoteLinePublicId})
            .HasDatabaseName("ix_sales_quote_conversion_source");
        b.HasIndex(x=>new{x.CompanyId,x.SalesOrderId,x.SalesOrderLinePublicId})
            .HasDatabaseName("ix_sales_quote_conversion_target");
        b.HasOne<QuoteRevisionRecord>().WithMany().HasForeignKey(x=>new{x.QuoteRevisionId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_quote_conversion_revision_company");
        b.ToTable(t=>t.HasCheckConstraint("ck_sales_quote_conversion_quantity","quantity > 0"));
    }

    private static void Order(ModelBuilder m)
    {
        var b=m.Entity<SalesOrderRecord>();
        b.ToTable("sales_orders","sales"); Id(b); Public(b); Company(b);
        b.Property(x=>x.Number).HasColumnName("number").HasMaxLength(64).IsRequired();
        b.Property(x=>x.CustomerPartyId).HasColumnName("customer_party_id");
        b.Property(x=>x.CustomerCodeSnapshot).HasColumnName("customer_code_snapshot").IsRequired();
        b.Property(x=>x.CustomerNameSnapshot).HasColumnName("customer_name_snapshot").IsRequired();
        b.Property(x=>x.CurrencyCode).HasColumnName("currency_code").HasMaxLength(3).IsRequired();
        b.Property(x=>x.PaymentTerms).HasColumnName("payment_terms");
        Enum(b.Property(x=>x.State).HasColumnName("state"),32);
        b.Property(x=>x.CurrentVersionNumber).HasColumnName("current_version_number");
        b.Property(x=>x.ApprovalInheritedFromAcceptedQuote).HasColumnName("approval_inherited_from_accepted_quote");
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        Version(b.Property(x=>x.Version).HasColumnName("version"));
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.Property(x=>x.ConfirmedAt).HasColumnName("confirmed_at");
        b.Property(x=>x.ClosedAt).HasColumnName("closed_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId}).HasName("ak_sales_orders_id_company");
        b.HasIndex(x=>new{x.CompanyId,x.Number}).IsUnique().HasDatabaseName("ux_sales_orders_company_number");
        b.HasIndex(x=>new{x.CompanyId,x.State,x.CreatedAt}).HasDatabaseName("ix_sales_orders_work_queue");
        b.HasOne<PartyRecord>().WithMany().HasForeignKey(x=>new{x.CustomerPartyId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_orders_customer_company");
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_sales_orders_number","length(btrim(number)) > 0 AND number = btrim(number)");
            t.HasCheckConstraint("ck_sales_orders_currency","currency_code ~ '^[A-Z]{3}$'");
            t.HasCheckConstraint("ck_sales_orders_current_version","current_version_number > 0");
            t.HasCheckConstraint("ck_sales_orders_version","version > 0");
        });
    }

    private static void OrderVersion(ModelBuilder m)
    {
        var b=m.Entity<SalesOrderVersionRecord>();
        b.ToTable("sales_order_versions","sales"); Id(b); Public(b); Company(b);
        b.Property(x=>x.SalesOrderId).HasColumnName("sales_order_id");
        b.Property(x=>x.VersionNumber).HasColumnName("version_number");
        b.Property(x=>x.SourceQuoteRevisionId).HasColumnName("source_quote_revision_id");
        b.Property(x=>x.SourceAmendmentId).HasColumnName("source_amendment_id");
        Decimal(b.Property(x=>x.DocumentDiscountPercent).HasColumnName("document_discount_percent"),12,6);
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId}).HasName("ak_sales_order_versions_id_company");
        b.HasIndex(x=>new{x.SalesOrderId,x.VersionNumber}).IsUnique().HasDatabaseName("ux_sales_order_versions_number");
        b.HasOne<SalesOrderRecord>().WithMany().HasForeignKey(x=>new{x.SalesOrderId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_order_versions_order_company");
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_sales_order_versions_number","version_number > 0");
            t.HasCheckConstraint("ck_sales_order_versions_discount","document_discount_percent >= 0 AND document_discount_percent <= 100");
        });
    }

    private static void OrderLine(ModelBuilder m)
    {
        var b=m.Entity<SalesOrderLineRecord>();
        b.ToTable("sales_order_lines","sales"); Id(b); Company(b);
        b.Property(x=>x.LinePublicId).HasColumnName("public_id");
        b.Property(x=>x.SalesOrderVersionId).HasColumnName("sales_order_version_id");
        b.Property(x=>x.Sequence).HasColumnName("sequence");
        Trade(b);
        Money(b.Property(x=>x.Quantity).HasColumnName("quantity"));
        Money(b.Property(x=>x.UnitPrice).HasColumnName("unit_price"));
        Decimal(b.Property(x=>x.LineDiscountPercent).HasColumnName("line_discount_percent"),12,6);
        Decimal(b.Property(x=>x.TaxPercent).HasColumnName("tax_percent"),12,6);
        b.HasIndex(x=>new{x.SalesOrderVersionId,x.LinePublicId}).IsUnique().HasDatabaseName("ux_sales_order_lines_version_public_id");
        b.HasIndex(x=>new{x.SalesOrderVersionId,x.Sequence}).IsUnique().HasDatabaseName("ux_sales_order_lines_version_sequence");
        b.HasIndex(x=>new{x.CompanyId,x.LinePublicId}).HasDatabaseName("ix_sales_order_lines_logical_line");
        b.HasOne<SalesOrderVersionRecord>().WithMany().HasForeignKey(x=>new{x.SalesOrderVersionId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_order_lines_version_company");
        TradeForeignKeys(b);
        LineChecks(b,"order_lines");
    }

    private static void Amendment(ModelBuilder m)
    {
        var b=m.Entity<SalesOrderAmendmentRecord>();
        b.ToTable("sales_order_amendments","sales"); Id(b); Public(b); Company(b);
        b.Property(x=>x.SalesOrderId).HasColumnName("sales_order_id");
        b.Property(x=>x.BaseVersionNumber).HasColumnName("base_version_number");
        Enum(b.Property(x=>x.State).HasColumnName("state"),24);
        b.Property(x=>x.RequiresApproval).HasColumnName("requires_approval");
        b.Property(x=>x.NewPaymentTerms).HasColumnName("new_payment_terms");
        b.Property(x=>x.Reason).HasColumnName("reason").IsRequired();
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.Property(x=>x.ActivatedAt).HasColumnName("activated_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId}).HasName("ak_sales_order_amendments_id_company");
        b.HasIndex(x=>new{x.SalesOrderId,x.State}).HasDatabaseName("ix_sales_order_amendments_order_state");
        b.HasOne<SalesOrderRecord>().WithMany().HasForeignKey(x=>new{x.SalesOrderId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_order_amendments_order_company");
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_sales_order_amendments_base_version","base_version_number > 0");
            t.HasCheckConstraint("ck_sales_order_amendments_reason","length(btrim(reason)) > 0 AND reason = btrim(reason)");
        });
    }

    private static void AmendmentDelta(ModelBuilder m)
    {
        var b=m.Entity<SalesOrderAmendmentDeltaRecord>();
        b.ToTable("sales_order_amendment_deltas","sales"); Id(b); Public(b); Company(b);
        b.Property(x=>x.AmendmentId).HasColumnName("amendment_id");
        b.Property(x=>x.SalesOrderLinePublicId).HasColumnName("sales_order_line_public_id");
        Money(b.Property(x=>x.QuantityDelta).HasColumnName("quantity_delta"));
        Money(b.Property(x=>x.NewUnitPrice).HasColumnName("new_unit_price"));
        Decimal(b.Property(x=>x.NewLineDiscountPercent).HasColumnName("new_line_discount_percent"),12,6);
        Decimal(b.Property(x=>x.NewTaxPercent).HasColumnName("new_tax_percent"),12,6);
        b.HasIndex(x=>new{x.AmendmentId,x.SalesOrderLinePublicId}).IsUnique().HasDatabaseName("ux_sales_order_amendment_deltas_line");
        b.HasOne<SalesOrderAmendmentRecord>().WithMany().HasForeignKey(x=>new{x.AmendmentId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_order_amendment_deltas_amendment_company");
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_sales_order_amendment_deltas_not_empty",
                "quantity_delta <> 0 OR new_unit_price IS NOT NULL OR new_line_discount_percent IS NOT NULL OR new_tax_percent IS NOT NULL");
            t.HasCheckConstraint("ck_sales_order_amendment_deltas_price","new_unit_price IS NULL OR new_unit_price >= 0");
            t.HasCheckConstraint("ck_sales_order_amendment_deltas_discount","new_line_discount_percent IS NULL OR (new_line_discount_percent >= 0 AND new_line_discount_percent <= 100)");
            t.HasCheckConstraint("ck_sales_order_amendment_deltas_tax","new_tax_percent IS NULL OR (new_tax_percent >= 0 AND new_tax_percent <= 100)");
        });
    }

    private static void Dispatch(ModelBuilder m)
    {
        var b=m.Entity<DispatchRecord>();
        b.ToTable("dispatches","sales"); Id(b); Public(b); Company(b);
        b.Property(x=>x.Number).HasColumnName("number").HasMaxLength(64).IsRequired();
        b.Property(x=>x.SalesOrderId).HasColumnName("sales_order_id");
        b.Property(x=>x.SalesOrderVersionNumber).HasColumnName("sales_order_version_number");
        b.Property(x=>x.WarehouseId).HasColumnName("warehouse_id");
        Enum(b.Property(x=>x.State).HasColumnName("state"),24);
        Version(b.Property(x=>x.Version).HasColumnName("version"));
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x=>x.ReversalOfDispatchId).HasColumnName("reversal_of_dispatch_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.Property(x=>x.ReadyAt).HasColumnName("ready_at");
        b.Property(x=>x.PostedAt).HasColumnName("posted_at");
        b.Property(x=>x.HandedOverAt).HasColumnName("handed_over_at");
        b.Property(x=>x.DeliveredAt).HasColumnName("delivered_at");
        b.Property(x=>x.CancelledAt).HasColumnName("cancelled_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId}).HasName("ak_sales_dispatches_id_company");
        b.HasIndex(x=>new{x.CompanyId,x.Number}).IsUnique().HasDatabaseName("ux_sales_dispatches_company_number");
        b.HasIndex(x=>new{x.CompanyId,x.State,x.CreatedAt}).HasDatabaseName("ix_sales_dispatches_work_queue");
        b.HasIndex(x=>x.ReversalOfDispatchId).IsUnique().HasFilter("reversal_of_dispatch_id IS NOT NULL")
            .HasDatabaseName("ux_sales_dispatches_single_reversal");
        b.HasOne<SalesOrderRecord>().WithMany().HasForeignKey(x=>new{x.SalesOrderId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_dispatches_order_company");
        b.HasOne<WarehouseRecord>().WithMany().HasForeignKey(x=>new{x.WarehouseId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_dispatches_warehouse_company");
        b.HasOne<DispatchRecord>().WithMany().HasForeignKey(x=>new{x.ReversalOfDispatchId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_dispatches_reversal_company");
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_sales_dispatches_number","length(btrim(number)) > 0 AND number = btrim(number)");
            t.HasCheckConstraint("ck_sales_dispatches_order_version","sales_order_version_number > 0");
            t.HasCheckConstraint("ck_sales_dispatches_version","version > 0");
            t.HasCheckConstraint("ck_sales_dispatches_not_self_reversal","reversal_of_dispatch_id IS NULL OR reversal_of_dispatch_id <> id");
        });
    }

    private static void DispatchLine(ModelBuilder m)
    {
        var b=m.Entity<DispatchLineRecord>();
        b.ToTable("dispatch_lines","sales"); Id(b); Public(b); Company(b);
        b.Property(x=>x.DispatchId).HasColumnName("dispatch_id");
        b.Property(x=>x.Sequence).HasColumnName("sequence");
        b.Property(x=>x.SalesOrderLinePublicId).HasColumnName("sales_order_line_public_id");
        b.Property(x=>x.ProductId).HasColumnName("product_id");
        b.Property(x=>x.VariantId).HasColumnName("variant_id");
        b.Property(x=>x.UomId).HasColumnName("uom_id");
        Decimal(b.Property(x=>x.ConversionFactorSnapshot).HasColumnName("conversion_factor_snapshot"),28,9);
        Money(b.Property(x=>x.Quantity).HasColumnName("quantity"));
        b.Property(x=>x.WarehouseId).HasColumnName("warehouse_id");
        b.Property(x=>x.LocationId).HasColumnName("location_id");
        b.Property(x=>x.LotId).HasColumnName("lot_id");
        b.Property(x=>x.SerialId).HasColumnName("serial_id");
        b.Property(x=>x.ReservationPublicId).HasColumnName("reservation_public_id");
        b.HasIndex(x=>new{x.DispatchId,x.Sequence}).IsUnique().HasDatabaseName("ux_sales_dispatch_lines_dispatch_sequence");
        b.HasIndex(x=>new{x.CompanyId,x.SalesOrderLinePublicId}).HasDatabaseName("ix_sales_dispatch_lines_order_line");
        b.HasOne<DispatchRecord>().WithMany().HasForeignKey(x=>new{x.DispatchId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_dispatch_lines_dispatch_company");
        b.HasOne<ProductRecord>().WithMany().HasForeignKey(x=>new{x.ProductId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_dispatch_lines_product_company");
        b.HasOne<ProductVariantRecord>().WithMany().HasForeignKey(x=>new{x.VariantId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_dispatch_lines_variant_company");
        b.HasOne<UnitOfMeasureRecord>().WithMany().HasForeignKey(x=>new{x.UomId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_dispatch_lines_uom_company");
        b.HasOne<WarehouseRecord>().WithMany().HasForeignKey(x=>new{x.WarehouseId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_dispatch_lines_warehouse_company");
        b.HasOne<LocationRecord>().WithMany().HasForeignKey(x=>new{x.LocationId,x.WarehouseId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.WarehouseId,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_dispatch_lines_location_warehouse_company");
        b.HasOne<InventoryLotRecord>().WithMany().HasForeignKey(x=>new{x.LotId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_dispatch_lines_lot_company");
        b.HasOne<InventorySerialRecord>().WithMany().HasForeignKey(x=>new{x.SerialId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_dispatch_lines_serial_company");
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_sales_dispatch_lines_sequence","sequence > 0");
            t.HasCheckConstraint("ck_sales_dispatch_lines_quantity","quantity > 0");
            t.HasCheckConstraint("ck_sales_dispatch_lines_conversion","conversion_factor_snapshot > 0");
        });
    }

    private static void DispatchSourceAllocation(ModelBuilder m)
    {
        var b=m.Entity<DispatchSourceAllocationRecord>();
        b.ToTable("dispatch_source_allocations","sales"); Id(b); Public(b); Company(b);
        b.Property(x=>x.DispatchLineId).HasColumnName("dispatch_line_id");
        b.Property(x=>x.WarehouseId).HasColumnName("warehouse_id");
        b.Property(x=>x.LocationId).HasColumnName("location_id");
        b.Property(x=>x.LotId).HasColumnName("lot_id");
        b.Property(x=>x.SerialId).HasColumnName("serial_id");
        Money(b.Property(x=>x.Quantity).HasColumnName("quantity"));
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasIndex(x=>new{x.DispatchLineId,x.CreatedAt}).HasDatabaseName("ix_sales_dispatch_allocations_line");
        b.HasOne<DispatchLineRecord>().WithMany().HasForeignKey(x=>new{x.DispatchLineId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_dispatch_allocations_line_company");
        b.HasOne<WarehouseRecord>().WithMany().HasForeignKey(x=>new{x.WarehouseId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_dispatch_allocations_warehouse_company");
        b.HasOne<LocationRecord>().WithMany().HasForeignKey(x=>new{x.LocationId,x.WarehouseId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.WarehouseId,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_dispatch_allocations_location_company");
        b.HasOne<InventoryLotRecord>().WithMany().HasForeignKey(x=>new{x.LotId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_dispatch_allocations_lot_company");
        b.HasOne<InventorySerialRecord>().WithMany().HasForeignKey(x=>new{x.SerialId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId]).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_dispatch_allocations_serial_company");
        b.ToTable(t=>t.HasCheckConstraint("ck_sales_dispatch_allocations_quantity","quantity > 0"));
    }

    private static void DispatchEffect(ModelBuilder m)
    {
        var b=m.Entity<DispatchInventoryEffectLinkRecord>();
        b.ToTable("dispatch_inventory_effect_links","sales"); Id(b); Public(b); Company(b);
        b.Property(x=>x.DispatchLineId).HasColumnName("dispatch_line_id");
        b.Property(x=>x.InventoryMovementPublicId).HasColumnName("inventory_movement_public_id");
        b.Property(x=>x.OriginalInventoryMovementPublicId).HasColumnName("original_inventory_movement_public_id");
        b.Property(x=>x.IsReversal).HasColumnName("is_reversal");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasIndex(x=>x.InventoryMovementPublicId).IsUnique().HasDatabaseName("ux_sales_dispatch_effect_inventory_movement");
        b.HasIndex(x=>new{x.DispatchLineId,x.IsReversal}).HasDatabaseName("ix_sales_dispatch_effect_line");
        b.HasOne<DispatchLineRecord>().WithMany().HasForeignKey(x=>new{x.DispatchLineId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_dispatch_effect_line_company");
    }

    private static void Invoice(ModelBuilder m)
    {
        var b=m.Entity<SalesInvoiceRecord>();
        b.ToTable("sales_invoices","sales"); Id(b); Public(b); Company(b);
        b.Property(x=>x.Number).HasColumnName("number").HasMaxLength(64).IsRequired();
        b.Property(x=>x.CustomerPartyId).HasColumnName("customer_party_id");
        Enum(b.Property(x=>x.State).HasColumnName("state"),16);
        Enum(b.Property(x=>x.SourceMode).HasColumnName("source_mode"),16);
        b.Property(x=>x.DocumentDate).HasColumnName("document_date");
        b.Property(x=>x.DueDate).HasColumnName("due_date");
        b.Property(x=>x.CurrencyCode).HasColumnName("currency_code").HasMaxLength(3).IsRequired();
        Decimal(b.Property(x=>x.DocumentDiscountPercent).HasColumnName("document_discount_percent"),12,6);
        b.Property(x=>x.CustomerCodeSnapshot).HasColumnName("customer_code_snapshot").IsRequired();
        b.Property(x=>x.CustomerLegalNameSnapshot).HasColumnName("customer_legal_name_snapshot").IsRequired();
        b.Property(x=>x.CustomerTaxSchemeSnapshot).HasColumnName("customer_tax_scheme_snapshot");
        b.Property(x=>x.CustomerTaxValueSnapshot).HasColumnName("customer_tax_value_snapshot");
        b.Property(x=>x.BillingAddressSnapshot).HasColumnName("billing_address_snapshot");
        b.Property(x=>x.ShippingAddressSnapshot).HasColumnName("shipping_address_snapshot");
        Money(b.Property(x=>x.NetTotal).HasColumnName("net_total"));
        Money(b.Property(x=>x.TaxTotal).HasColumnName("tax_total"));
        Money(b.Property(x=>x.GrossTotal).HasColumnName("gross_total"));
        Version(b.Property(x=>x.Version).HasColumnName("version"));
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.Property(x=>x.CancelledAt).HasColumnName("cancelled_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId}).HasName("ak_sales_invoices_id_company");
        b.HasIndex(x=>new{x.CompanyId,x.Number}).IsUnique().HasDatabaseName("ux_sales_invoices_company_number");
        b.HasIndex(x=>new{x.CompanyId,x.State,x.DocumentDate}).HasDatabaseName("ix_sales_invoices_work_queue");
        b.HasOne<PartyRecord>().WithMany().HasForeignKey(x=>new{x.CustomerPartyId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_invoices_customer_company");
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_sales_invoices_number","length(btrim(number)) > 0 AND number = btrim(number)");
            t.HasCheckConstraint("ck_sales_invoices_dates","due_date >= document_date");
            t.HasCheckConstraint("ck_sales_invoices_currency","currency_code ~ '^[A-Z]{3}$'");
            t.HasCheckConstraint("ck_sales_invoices_discount","document_discount_percent >= 0 AND document_discount_percent <= 100");
            t.HasCheckConstraint("ck_sales_invoices_totals","net_total >= 0 AND tax_total >= 0 AND gross_total >= 0 AND gross_total = net_total + tax_total");
            t.HasCheckConstraint("ck_sales_invoices_version","version > 0");
        });
    }

    private static void InvoiceLine(ModelBuilder m)
    {
        var b=m.Entity<SalesInvoiceLineRecord>();
        b.ToTable("sales_invoice_lines","sales"); Id(b); Public(b); Company(b);
        b.Property(x=>x.SalesInvoiceId).HasColumnName("sales_invoice_id");
        b.Property(x=>x.Sequence).HasColumnName("sequence");
        Trade(b);
        Money(b.Property(x=>x.Quantity).HasColumnName("quantity"));
        Money(b.Property(x=>x.UnitPrice).HasColumnName("unit_price"));
        Decimal(b.Property(x=>x.LineDiscountPercent).HasColumnName("line_discount_percent"),12,6);
        Money(b.Property(x=>x.DocumentDiscount).HasColumnName("document_discount"));
        Decimal(b.Property(x=>x.TaxPercent).HasColumnName("tax_percent"),12,6);
        Money(b.Property(x=>x.TaxableBase).HasColumnName("taxable_base"));
        Money(b.Property(x=>x.TaxAmount).HasColumnName("tax_amount"));
        Money(b.Property(x=>x.LineTotal).HasColumnName("line_total"));
        b.HasIndex(x=>new{x.SalesInvoiceId,x.Sequence}).IsUnique().HasDatabaseName("ux_sales_invoice_lines_invoice_sequence");
        b.HasOne<SalesInvoiceRecord>().WithMany().HasForeignKey(x=>new{x.SalesInvoiceId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_invoice_lines_invoice_company");
        TradeForeignKeys(b);
        LineChecks(b,"invoice_lines");
        b.ToTable(t=>t.HasCheckConstraint("ck_sales_invoice_lines_calculation",
            "document_discount >= 0 AND taxable_base >= 0 AND tax_amount >= 0 AND line_total = taxable_base + tax_amount"));
    }

    private static void InvoiceSource(ModelBuilder m)
    {
        var b=m.Entity<SalesInvoiceSourceLinkRecord>();
        b.ToTable("sales_invoice_source_links","sales"); Id(b); Public(b); Company(b);
        b.Property(x=>x.SalesInvoiceLineId).HasColumnName("sales_invoice_line_id");
        Enum(b.Property(x=>x.SourceMode).HasColumnName("source_mode"),16);
        b.Property(x=>x.SourceDocumentPublicId).HasColumnName("source_document_public_id");
        b.Property(x=>x.SourceLinePublicId).HasColumnName("source_line_public_id");
        b.Property(x=>x.SourceVersion).HasColumnName("source_version");
        Money(b.Property(x=>x.Quantity).HasColumnName("quantity"));
        b.HasIndex(x=>new{x.CompanyId,x.SourceMode,x.SourceDocumentPublicId,x.SourceLinePublicId})
            .HasDatabaseName("ix_sales_invoice_source_links_source");
        b.HasOne<SalesInvoiceLineRecord>().WithMany().HasForeignKey(x=>new{x.SalesInvoiceLineId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_invoice_source_links_line_company");
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_sales_invoice_source_links_quantity","quantity > 0");
            t.HasCheckConstraint("ck_sales_invoice_source_links_shape",
                "(source_mode = 'Direct' AND source_document_public_id IS NULL AND source_line_public_id IS NULL AND source_version IS NULL) OR " +
                "(source_mode <> 'Direct' AND source_document_public_id IS NOT NULL AND source_line_public_id IS NOT NULL)");
        });
    }

    private static void Trade<T>(Microsoft.EntityFrameworkCore.Metadata.Builders.EntityTypeBuilder<T> b)
        where T:class
    {
        b.Property("ProductId").HasColumnName("product_id");
        b.Property("VariantId").HasColumnName("variant_id");
        b.Property("UomId").HasColumnName("uom_id");
        b.Property("ConversionFactorSnapshot").HasColumnName("conversion_factor_snapshot").HasPrecision(28,9);
        b.Property("ProductCodeSnapshot").HasColumnName("product_code_snapshot").IsRequired();
        b.Property("ProductNameSnapshot").HasColumnName("product_name_snapshot").IsRequired();
        b.Property("VariantCodeSnapshot").HasColumnName("variant_code_snapshot");
        b.Property("VariantNameSnapshot").HasColumnName("variant_name_snapshot");
        b.Property("UomCodeSnapshot").HasColumnName("uom_code_snapshot").IsRequired();
        b.Property("UomNameSnapshot").HasColumnName("uom_name_snapshot").IsRequired();
    }

    private static void TradeForeignKeys<T>(Microsoft.EntityFrameworkCore.Metadata.Builders.EntityTypeBuilder<T> b)
        where T:class
    {
        b.HasOne(typeof(ProductRecord)).WithMany().HasForeignKey("ProductId","CompanyId")
            .HasPrincipalKey("Id","CompanyId").OnDelete(DeleteBehavior.Restrict);
        b.HasOne(typeof(ProductVariantRecord)).WithMany().HasForeignKey("VariantId","CompanyId")
            .HasPrincipalKey("Id","CompanyId").OnDelete(DeleteBehavior.Restrict);
        b.HasOne(typeof(UnitOfMeasureRecord)).WithMany().HasForeignKey("UomId","CompanyId")
            .HasPrincipalKey("Id","CompanyId").OnDelete(DeleteBehavior.Restrict);
    }

    private static void LineChecks<T>(Microsoft.EntityFrameworkCore.Metadata.Builders.EntityTypeBuilder<T> b,string name)
        where T:class
    {
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_sales_"+name+"_sequence","sequence > 0");
            t.HasCheckConstraint("ck_sales_"+name+"_quantity","quantity > 0");
            t.HasCheckConstraint("ck_sales_"+name+"_conversion","conversion_factor_snapshot > 0");
            t.HasCheckConstraint("ck_sales_"+name+"_unit_price","unit_price >= 0");
            t.HasCheckConstraint("ck_sales_"+name+"_discount","line_discount_percent >= 0 AND line_discount_percent <= 100");
            t.HasCheckConstraint("ck_sales_"+name+"_tax","tax_percent >= 0 AND tax_percent <= 100");
        });
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
    private static void Money(Microsoft.EntityFrameworkCore.Metadata.Builders.PropertyBuilder<decimal> p)=>p.HasPrecision(28,9);
    private static void Money(Microsoft.EntityFrameworkCore.Metadata.Builders.PropertyBuilder<decimal?> p)=>p.HasPrecision(28,9);
    private static void Decimal(Microsoft.EntityFrameworkCore.Metadata.Builders.PropertyBuilder<decimal> p,int precision,int scale)=>p.HasPrecision(precision,scale);
    private static void Decimal(Microsoft.EntityFrameworkCore.Metadata.Builders.PropertyBuilder<decimal?> p,int precision,int scale)=>p.HasPrecision(precision,scale);
    private static void Version(Microsoft.EntityFrameworkCore.Metadata.Builders.PropertyBuilder<long> p)=>p.IsConcurrencyToken();
    private static void Enum<TEnum>(Microsoft.EntityFrameworkCore.Metadata.Builders.PropertyBuilder<TEnum> p,int max)
        where TEnum:struct,Enum=>p.HasConversion<string>().HasMaxLength(max).IsRequired();
}
