using Mars.Domain.Purchasing;
using Mars.Infrastructure.Persistence.Inventory;
using Mars.Infrastructure.Persistence.Parties;
using Mars.Infrastructure.Persistence.Products;
using Microsoft.EntityFrameworkCore;

namespace Mars.Infrastructure.Persistence.Purchasing;

internal static class PurchasingModelConfiguration
{
    public static void Configure(ModelBuilder m)
    {
        PurchaseOrder(m);
        PurchaseOrderVersion(m);
        PurchaseOrderLine(m);
        GoodsReceipt(m);
        GoodsReceiptLine(m);
        GoodsReceiptEffect(m);
        SupplierInvoice(m);
        SupplierInvoiceLine(m);
        SupplierInvoiceSource(m);
        PurchaseMatch(m);
    }

    private static void PurchaseOrder(ModelBuilder m)
    {
        var b=m.Entity<PurchaseOrderRecord>();
        b.ToTable("purchase_orders","purchasing"); Id(b); Public(b); Company(b);
        b.Property(x=>x.Number).HasColumnName("number").HasMaxLength(64).IsRequired();
        b.Property(x=>x.SupplierPartyId).HasColumnName("supplier_party_id");
        b.Property(x=>x.SupplierCodeSnapshot).HasColumnName("supplier_code_snapshot").IsRequired();
        b.Property(x=>x.SupplierNameSnapshot).HasColumnName("supplier_name_snapshot").IsRequired();
        b.Property(x=>x.CurrencyCode).HasColumnName("currency_code").HasMaxLength(3).IsRequired();
        b.Property(x=>x.PaymentTerms).HasColumnName("payment_terms");
        Enum(b.Property(x=>x.State).HasColumnName("state"),32);
        b.Property(x=>x.CurrentVersionNumber).HasColumnName("current_version_number");
        Version(b.Property(x=>x.Version).HasColumnName("version"));
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.Property(x=>x.ConfirmedAt).HasColumnName("confirmed_at");
        b.Property(x=>x.ClosedAt).HasColumnName("closed_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId});
        b.HasIndex(x=>new{x.CompanyId,x.Number}).IsUnique().HasDatabaseName("ux_purchasing_orders_company_number");
        b.HasOne<PartyRecord>().WithMany().HasForeignKey(x=>new{x.SupplierPartyId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_purchasing_orders_number","length(btrim(number)) > 0 AND number = btrim(number)");
            t.HasCheckConstraint("ck_purchasing_orders_currency","currency_code ~ '^[A-Z]{3}$'");
            t.HasCheckConstraint("ck_purchasing_orders_version","version > 0 AND current_version_number > 0");
        });
    }

    private static void PurchaseOrderVersion(ModelBuilder m)
    {
        var b=m.Entity<PurchaseOrderVersionRecord>();
        b.ToTable("purchase_order_versions","purchasing"); Id(b); Public(b); Company(b);
        b.Property(x=>x.PurchaseOrderId).HasColumnName("purchase_order_id");
        b.Property(x=>x.VersionNumber).HasColumnName("version_number");
        Decimal(b.Property(x=>x.DocumentDiscountPercent).HasColumnName("document_discount_percent"),12,6);
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId});
        b.HasIndex(x=>new{x.PurchaseOrderId,x.VersionNumber}).IsUnique();
        b.HasOne<PurchaseOrderRecord>().WithMany().HasForeignKey(x=>new{x.PurchaseOrderId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_purchasing_order_versions_number","version_number > 0");
            t.HasCheckConstraint("ck_purchasing_order_versions_discount","document_discount_percent >= 0 AND document_discount_percent <= 100");
        });
    }

    private static void PurchaseOrderLine(ModelBuilder m)
    {
        var b=m.Entity<PurchaseOrderLineRecord>();
        b.ToTable("purchase_order_lines","purchasing"); Id(b);
        b.Property(x=>x.LinePublicId).HasColumnName("public_id");
        b.HasIndex(x=>new{x.PurchaseOrderVersionId,x.LinePublicId}).IsUnique();
        Company(b);
        b.Property(x=>x.PurchaseOrderVersionId).HasColumnName("purchase_order_version_id");
        b.Property(x=>x.Sequence).HasColumnName("sequence");
        Trade(b);
        Qty(b.Property(x=>x.Quantity).HasColumnName("quantity"));
        Qty(b.Property(x=>x.UnitPrice).HasColumnName("unit_price"));
        Decimal(b.Property(x=>x.LineDiscountPercent).HasColumnName("line_discount_percent"),12,6);
        Decimal(b.Property(x=>x.TaxPercent).HasColumnName("tax_percent"),12,6);
        b.HasIndex(x=>new{x.PurchaseOrderVersionId,x.Sequence}).IsUnique();
        b.HasOne<PurchaseOrderVersionRecord>().WithMany().HasForeignKey(x=>new{x.PurchaseOrderVersionId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        TradeForeignKeys(b);
        LineChecks(b,"order_lines");
    }

    private static void GoodsReceipt(ModelBuilder m)
    {
        var b=m.Entity<GoodsReceiptRecord>();
        b.ToTable("goods_receipts","purchasing"); Id(b); Public(b); Company(b);
        b.Property(x=>x.Number).HasColumnName("number").HasMaxLength(64).IsRequired();
        b.Property(x=>x.PurchaseOrderId).HasColumnName("purchase_order_id");
        b.Property(x=>x.PurchaseOrderVersionNumber).HasColumnName("purchase_order_version_number");
        b.Property(x=>x.WarehouseId).HasColumnName("warehouse_id");
        Enum(b.Property(x=>x.State).HasColumnName("state"),24);
        Version(b.Property(x=>x.Version).HasColumnName("version"));
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x=>x.ReversalOfGoodsReceiptId).HasColumnName("reversal_of_goods_receipt_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.Property(x=>x.PostedAt).HasColumnName("posted_at");
        b.Property(x=>x.CancelledAt).HasColumnName("cancelled_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId});
        b.HasIndex(x=>new{x.CompanyId,x.Number}).IsUnique();
        b.HasIndex(x=>x.ReversalOfGoodsReceiptId).IsUnique().HasFilter("reversal_of_goods_receipt_id IS NOT NULL");
        b.HasOne<PurchaseOrderRecord>().WithMany().HasForeignKey(x=>new{x.PurchaseOrderId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.HasOne<WarehouseRecord>().WithMany().HasForeignKey(x=>new{x.WarehouseId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.HasOne<GoodsReceiptRecord>().WithMany().HasForeignKey(x=>new{x.ReversalOfGoodsReceiptId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_purchasing_receipts_number","length(btrim(number)) > 0 AND number = btrim(number)");
            t.HasCheckConstraint("ck_purchasing_receipts_order_version","purchase_order_version_number > 0");
            t.HasCheckConstraint("ck_purchasing_receipts_version","version > 0");
            t.HasCheckConstraint("ck_purchasing_receipts_not_self_reversal","reversal_of_goods_receipt_id IS NULL OR reversal_of_goods_receipt_id <> id");
        });
    }

    private static void GoodsReceiptLine(ModelBuilder m)
    {
        var b=m.Entity<GoodsReceiptLineRecord>();
        b.ToTable("goods_receipt_lines","purchasing"); Id(b); Public(b); Company(b);
        b.Property(x=>x.GoodsReceiptId).HasColumnName("goods_receipt_id");
        b.Property(x=>x.Sequence).HasColumnName("sequence");
        b.Property(x=>x.PurchaseOrderLinePublicId).HasColumnName("purchase_order_line_public_id");
        b.Property(x=>x.ProductId).HasColumnName("product_id");
        b.Property(x=>x.VariantId).HasColumnName("variant_id");
        b.Property(x=>x.UomId).HasColumnName("uom_id");
        Decimal(b.Property(x=>x.ConversionFactorSnapshot).HasColumnName("conversion_factor_snapshot"),28,9);
        Qty(b.Property(x=>x.Quantity).HasColumnName("quantity"));
        b.Property(x=>x.WarehouseId).HasColumnName("warehouse_id");
        b.Property(x=>x.LocationId).HasColumnName("location_id");
        b.Property(x=>x.LotId).HasColumnName("lot_id");
        b.Property(x=>x.SerialId).HasColumnName("serial_id");
        b.HasIndex(x=>new{x.GoodsReceiptId,x.Sequence}).IsUnique();
        b.HasIndex(x=>new{x.CompanyId,x.PurchaseOrderLinePublicId});
        b.HasOne<GoodsReceiptRecord>().WithMany().HasForeignKey(x=>new{x.GoodsReceiptId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.HasOne<ProductRecord>().WithMany().HasForeignKey(x=>new{x.ProductId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.HasOne<ProductVariantRecord>().WithMany().HasForeignKey(x=>new{x.VariantId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId]).OnDelete(DeleteBehavior.Restrict);
        b.HasOne<UnitOfMeasureRecord>().WithMany().HasForeignKey(x=>new{x.UomId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId]).OnDelete(DeleteBehavior.Restrict);
        b.HasOne<WarehouseRecord>().WithMany().HasForeignKey(x=>new{x.WarehouseId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId]).OnDelete(DeleteBehavior.Restrict);
        b.HasOne<LocationRecord>().WithMany().HasForeignKey(x=>new{x.LocationId,x.WarehouseId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.WarehouseId,x.CompanyId]).OnDelete(DeleteBehavior.Restrict);
        b.HasOne<InventoryLotRecord>().WithMany().HasForeignKey(x=>new{x.LotId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId]).OnDelete(DeleteBehavior.Restrict);
        b.HasOne<InventorySerialRecord>().WithMany().HasForeignKey(x=>new{x.SerialId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId]).OnDelete(DeleteBehavior.Restrict);
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_purchasing_receipt_lines_sequence","sequence > 0");
            t.HasCheckConstraint("ck_purchasing_receipt_lines_quantity","quantity > 0");
            t.HasCheckConstraint("ck_purchasing_receipt_lines_conversion","conversion_factor_snapshot > 0");
        });
    }

    private static void GoodsReceiptEffect(ModelBuilder m)
    {
        var b=m.Entity<GoodsReceiptInventoryEffectLinkRecord>();
        b.ToTable("goods_receipt_inventory_effect_links","purchasing"); Id(b); Public(b); Company(b);
        b.Property(x=>x.GoodsReceiptLineId).HasColumnName("goods_receipt_line_id");
        b.Property(x=>x.InventoryMovementPublicId).HasColumnName("inventory_movement_public_id");
        b.Property(x=>x.OriginalInventoryMovementPublicId).HasColumnName("original_inventory_movement_public_id");
        b.Property(x=>x.IsReversal).HasColumnName("is_reversal");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasIndex(x=>x.InventoryMovementPublicId).IsUnique();
        b.HasOne<GoodsReceiptLineRecord>().WithMany().HasForeignKey(x=>new{x.GoodsReceiptLineId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId]).OnDelete(DeleteBehavior.Restrict);
    }

    private static void SupplierInvoice(ModelBuilder m)
    {
        var b=m.Entity<SupplierInvoiceRecord>();
        b.ToTable("supplier_invoices","purchasing"); Id(b); Public(b); Company(b);
        b.Property(x=>x.Number).HasColumnName("number").HasMaxLength(64).IsRequired();
        b.Property(x=>x.SupplierPartyId).HasColumnName("supplier_party_id");
        Enum(b.Property(x=>x.State).HasColumnName("state"),16);
        Enum(b.Property(x=>x.SourceMode).HasColumnName("source_mode"),16);
        b.Property(x=>x.DocumentDate).HasColumnName("document_date");
        b.Property(x=>x.DueDate).HasColumnName("due_date");
        b.Property(x=>x.CurrencyCode).HasColumnName("currency_code").HasMaxLength(3).IsRequired();
        Decimal(b.Property(x=>x.DocumentDiscountPercent).HasColumnName("document_discount_percent"),12,6);
        b.Property(x=>x.SupplierCodeSnapshot).HasColumnName("supplier_code_snapshot").IsRequired();
        b.Property(x=>x.SupplierLegalNameSnapshot).HasColumnName("supplier_legal_name_snapshot").IsRequired();
        Qty(b.Property(x=>x.NetTotal).HasColumnName("net_total"));
        Qty(b.Property(x=>x.TaxTotal).HasColumnName("tax_total"));
        Qty(b.Property(x=>x.GrossTotal).HasColumnName("gross_total"));
        Version(b.Property(x=>x.Version).HasColumnName("version"));
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.Property(x=>x.CancelledAt).HasColumnName("cancelled_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId});
        b.HasIndex(x=>new{x.CompanyId,x.Number}).IsUnique();
        b.HasOne<PartyRecord>().WithMany().HasForeignKey(x=>new{x.SupplierPartyId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId]).OnDelete(DeleteBehavior.Restrict);
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_purchasing_supplier_invoices_dates","due_date >= document_date");
            t.HasCheckConstraint("ck_purchasing_supplier_invoices_currency","currency_code ~ '^[A-Z]{3}$'");
            t.HasCheckConstraint("ck_purchasing_supplier_invoices_discount","document_discount_percent >= 0 AND document_discount_percent <= 100");
            t.HasCheckConstraint("ck_purchasing_supplier_invoices_totals","net_total >= 0 AND tax_total >= 0 AND gross_total = net_total + tax_total");
            t.HasCheckConstraint("ck_purchasing_supplier_invoices_version","version > 0");
        });
    }

    private static void SupplierInvoiceLine(ModelBuilder m)
    {
        var b=m.Entity<SupplierInvoiceLineRecord>();
        b.ToTable("supplier_invoice_lines","purchasing"); Id(b); Public(b); Company(b);
        b.Property(x=>x.SupplierInvoiceId).HasColumnName("supplier_invoice_id");
        b.Property(x=>x.Sequence).HasColumnName("sequence");
        Trade(b);
        Qty(b.Property(x=>x.Quantity).HasColumnName("quantity"));
        Qty(b.Property(x=>x.UnitPrice).HasColumnName("unit_price"));
        Decimal(b.Property(x=>x.LineDiscountPercent).HasColumnName("line_discount_percent"),12,6);
        Qty(b.Property(x=>x.DocumentDiscount).HasColumnName("document_discount"));
        Decimal(b.Property(x=>x.TaxPercent).HasColumnName("tax_percent"),12,6);
        Qty(b.Property(x=>x.TaxableBase).HasColumnName("taxable_base"));
        Qty(b.Property(x=>x.TaxAmount).HasColumnName("tax_amount"));
        Qty(b.Property(x=>x.LineTotal).HasColumnName("line_total"));
        b.HasIndex(x=>new{x.SupplierInvoiceId,x.Sequence}).IsUnique();
        b.HasOne<SupplierInvoiceRecord>().WithMany().HasForeignKey(x=>new{x.SupplierInvoiceId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId]).OnDelete(DeleteBehavior.Restrict);
        TradeForeignKeys(b);
        LineChecks(b,"invoice_lines");
    }

    private static void SupplierInvoiceSource(ModelBuilder m)
    {
        var b=m.Entity<SupplierInvoiceSourceLinkRecord>();
        b.ToTable("supplier_invoice_source_links","purchasing"); Id(b); Public(b); Company(b);
        b.Property(x=>x.SupplierInvoiceLineId).HasColumnName("supplier_invoice_line_id");
        Enum(b.Property(x=>x.SourceMode).HasColumnName("source_mode"),16);
        b.Property(x=>x.SourceDocumentPublicId).HasColumnName("source_document_public_id");
        b.Property(x=>x.SourceLinePublicId).HasColumnName("source_line_public_id");
        b.Property(x=>x.SourceVersion).HasColumnName("source_version");
        Qty(b.Property(x=>x.Quantity).HasColumnName("quantity"));
        b.HasIndex(x=>new{x.CompanyId,x.SourceMode,x.SourceDocumentPublicId,x.SourceLinePublicId});
        b.HasOne<SupplierInvoiceLineRecord>().WithMany().HasForeignKey(x=>new{x.SupplierInvoiceLineId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId]).OnDelete(DeleteBehavior.Restrict);
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_purchasing_invoice_source_quantity","quantity > 0");
            t.HasCheckConstraint("ck_purchasing_invoice_source_shape",
                "(source_mode = 'Direct' AND source_document_public_id IS NULL AND source_line_public_id IS NULL AND source_version IS NULL) OR " +
                "(source_mode <> 'Direct' AND source_document_public_id IS NOT NULL AND source_line_public_id IS NOT NULL)");
        });
    }

    private static void PurchaseMatch(ModelBuilder m)
    {
        var b=m.Entity<PurchaseMatchResultRecord>();
        b.ToTable("purchase_match_results","purchasing"); Id(b); Public(b); Company(b);
        Enum(b.Property(x=>x.Kind).HasColumnName("kind"),16);
        Enum(b.Property(x=>x.State).HasColumnName("state"),24);
        b.Property(x=>x.SupplierInvoicePublicId).HasColumnName("supplier_invoice_public_id");
        b.Property(x=>x.PurchaseOrderPublicId).HasColumnName("purchase_order_public_id");
        b.Property(x=>x.GoodsReceiptPublicId).HasColumnName("goods_receipt_public_id");
        Qty(b.Property(x=>x.QuantityVariance).HasColumnName("quantity_variance"));
        Qty(b.Property(x=>x.PriceVariance).HasColumnName("price_variance"));
        b.Property(x=>x.BlockReason).HasColumnName("block_reason");
        b.Property(x=>x.EvaluatedAt).HasColumnName("evaluated_at");
        b.Property(x=>x.EvaluatedByActorId).HasColumnName("evaluated_by_actor_id");
        b.HasIndex(x=>new{x.CompanyId,x.SupplierInvoicePublicId,x.Kind}).IsUnique();
    }

    private static void Trade<T>(Microsoft.EntityFrameworkCore.Metadata.Builders.EntityTypeBuilder<T> b) where T:class
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

    private static void TradeForeignKeys<T>(Microsoft.EntityFrameworkCore.Metadata.Builders.EntityTypeBuilder<T> b) where T:class
    {
        b.HasOne(typeof(ProductRecord)).WithMany().HasForeignKey("ProductId","CompanyId")
            .HasPrincipalKey("Id","CompanyId").OnDelete(DeleteBehavior.Restrict);
        b.HasOne(typeof(ProductVariantRecord)).WithMany().HasForeignKey("VariantId","CompanyId")
            .HasPrincipalKey("Id","CompanyId").OnDelete(DeleteBehavior.Restrict);
        b.HasOne(typeof(UnitOfMeasureRecord)).WithMany().HasForeignKey("UomId","CompanyId")
            .HasPrincipalKey("Id","CompanyId").OnDelete(DeleteBehavior.Restrict);
    }

    private static void LineChecks<T>(Microsoft.EntityFrameworkCore.Metadata.Builders.EntityTypeBuilder<T> b,string name) where T:class
    {
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_purchasing_"+name+"_sequence","sequence > 0");
            t.HasCheckConstraint("ck_purchasing_"+name+"_quantity","quantity > 0");
            t.HasCheckConstraint("ck_purchasing_"+name+"_conversion","conversion_factor_snapshot > 0");
            t.HasCheckConstraint("ck_purchasing_"+name+"_unit_price","unit_price >= 0");
            t.HasCheckConstraint("ck_purchasing_"+name+"_discount","line_discount_percent >= 0 AND line_discount_percent <= 100");
            t.HasCheckConstraint("ck_purchasing_"+name+"_tax","tax_percent >= 0 AND tax_percent <= 100");
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
    private static void Qty(Microsoft.EntityFrameworkCore.Metadata.Builders.PropertyBuilder<decimal> p)=>p.HasPrecision(28,9);
    private static void Decimal(Microsoft.EntityFrameworkCore.Metadata.Builders.PropertyBuilder<decimal> p,int precision,int scale)=>p.HasPrecision(precision,scale);
    private static void Version(Microsoft.EntityFrameworkCore.Metadata.Builders.PropertyBuilder<long> p)=>p.IsConcurrencyToken();
    private static void Enum<TEnum>(Microsoft.EntityFrameworkCore.Metadata.Builders.PropertyBuilder<TEnum> p,int max)
        where TEnum:struct,Enum=>p.HasConversion<string>().HasMaxLength(max).IsRequired();
}
