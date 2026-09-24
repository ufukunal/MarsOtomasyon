using Mars.Domain.Sales;
using Mars.Infrastructure.Persistence.Parties;
using Mars.Infrastructure.Persistence.Products;
using Microsoft.EntityFrameworkCore;

namespace Mars.Infrastructure.Persistence.Sales;

internal static class ProformaModelConfiguration
{
    public static void Configure(ModelBuilder modelBuilder)
    {
        var proforma = modelBuilder.Entity<SalesProformaRecord>();
        proforma.ToTable("proformas", "sales");
        proforma.HasKey(x => x.Id).HasName("pk_sales_proformas");
        proforma.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        proforma.Property(x => x.PublicId).HasColumnName("public_id");
        proforma.Property(x => x.CompanyId).HasColumnName("company_id");
        proforma.Property(x => x.Number).HasColumnName("number").HasMaxLength(64).IsRequired();
        proforma.Property(x => x.CustomerPartyId).HasColumnName("customer_party_id");
        proforma.Property(x => x.CustomerCodeSnapshot).HasColumnName("customer_code_snapshot").IsRequired();
        proforma.Property(x => x.CustomerNameSnapshot).HasColumnName("customer_name_snapshot").IsRequired();
        proforma.Property(x => x.CurrencyCode).HasColumnName("currency_code").HasMaxLength(3).IsRequired();
        proforma.Property(x => x.PaymentTerms).HasColumnName("payment_terms");
        proforma.Property(x => x.DocumentDiscountPercent).HasColumnName("document_discount_percent").HasPrecision(12, 6);
        proforma.Property(x => x.SourceMode).HasColumnName("source_mode").HasConversion<string>().HasMaxLength(16).IsRequired();
        proforma.Property(x => x.SourceDocumentPublicId).HasColumnName("source_document_public_id");
        proforma.Property(x => x.SourceVersion).HasColumnName("source_version");
        proforma.Property(x => x.State).HasColumnName("state").HasConversion<string>().HasMaxLength(16).IsRequired();
        proforma.Property(x => x.Version).HasColumnName("version").IsConcurrencyToken();
        proforma.Property(x => x.CreatorActorId).HasColumnName("creator_actor_id");
        proforma.Property(x => x.CreatedAt).HasColumnName("created_at");
        proforma.Property(x => x.CancelledAt).HasColumnName("cancelled_at");
        proforma.HasAlternateKey(x => new { x.Id, x.CompanyId }).HasName("ak_sales_proformas_id_company");
        proforma.HasIndex(x => x.PublicId).IsUnique().HasDatabaseName("ux_sales_proformas_public_id");
        proforma.HasIndex(x => new { x.CompanyId, x.Number }).IsUnique().HasDatabaseName("ux_sales_proformas_company_number");
        proforma.HasIndex(x => new { x.CompanyId, x.SourceMode, x.SourceDocumentPublicId })
            .HasDatabaseName("ix_sales_proformas_source");
        proforma.HasOne<PartyRecord>().WithMany().HasForeignKey(x => new { x.CustomerPartyId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId }).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_proformas_customer_company");
        proforma.ToTable(t =>
        {
            t.HasCheckConstraint("ck_sales_proformas_number", "length(btrim(number)) > 0 AND number = btrim(number)");
            t.HasCheckConstraint("ck_sales_proformas_currency", "currency_code ~ '^[A-Z]{3}$'");
            t.HasCheckConstraint("ck_sales_proformas_discount", "document_discount_percent >= 0 AND document_discount_percent <= 100");
            t.HasCheckConstraint("ck_sales_proformas_source_version", "source_version > 0");
            t.HasCheckConstraint("ck_sales_proformas_version", "version > 0");
        });

        var line = modelBuilder.Entity<SalesProformaLineRecord>();
        line.ToTable("proforma_lines", "sales");
        line.HasKey(x => x.Id).HasName("pk_sales_proforma_lines");
        line.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        line.Property(x => x.PublicId).HasColumnName("public_id");
        line.Property(x => x.SalesProformaId).HasColumnName("sales_proforma_id");
        line.Property(x => x.CompanyId).HasColumnName("company_id");
        line.Property(x => x.Sequence).HasColumnName("sequence");
        line.Property(x => x.SourceLinePublicId).HasColumnName("source_line_public_id");
        line.Property(x => x.ProductId).HasColumnName("product_id");
        line.Property(x => x.VariantId).HasColumnName("variant_id");
        line.Property(x => x.UomId).HasColumnName("uom_id");
        line.Property(x => x.ConversionFactorSnapshot).HasColumnName("conversion_factor_snapshot").HasPrecision(28, 9);
        line.Property(x => x.ProductCodeSnapshot).HasColumnName("product_code_snapshot").IsRequired();
        line.Property(x => x.ProductNameSnapshot).HasColumnName("product_name_snapshot").IsRequired();
        line.Property(x => x.VariantCodeSnapshot).HasColumnName("variant_code_snapshot");
        line.Property(x => x.VariantNameSnapshot).HasColumnName("variant_name_snapshot");
        line.Property(x => x.UomCodeSnapshot).HasColumnName("uom_code_snapshot").IsRequired();
        line.Property(x => x.UomNameSnapshot).HasColumnName("uom_name_snapshot").IsRequired();
        line.Property(x => x.Quantity).HasColumnName("quantity").HasPrecision(28, 9);
        line.Property(x => x.UnitPrice).HasColumnName("unit_price").HasPrecision(28, 9);
        line.Property(x => x.LineDiscountPercent).HasColumnName("line_discount_percent").HasPrecision(12, 6);
        line.Property(x => x.TaxPercent).HasColumnName("tax_percent").HasPrecision(12, 6);
        line.HasIndex(x => x.PublicId).IsUnique().HasDatabaseName("ux_sales_proforma_lines_public_id");
        line.HasIndex(x => new { x.SalesProformaId, x.Sequence }).IsUnique().HasDatabaseName("ux_sales_proforma_lines_document_sequence");
        line.HasOne<SalesProformaRecord>().WithMany().HasForeignKey(x => new { x.SalesProformaId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId }).OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_sales_proforma_lines_document_company");
        line.HasOne<ProductRecord>().WithMany().HasForeignKey(x => new { x.ProductId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId }).OnDelete(DeleteBehavior.Restrict);
        line.HasOne<ProductVariantRecord>().WithMany().HasForeignKey(x => new { x.VariantId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId }).OnDelete(DeleteBehavior.Restrict);
        line.HasOne<UnitOfMeasureRecord>().WithMany().HasForeignKey(x => new { x.UomId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId }).OnDelete(DeleteBehavior.Restrict);
        line.ToTable(t =>
        {
            t.HasCheckConstraint("ck_sales_proforma_lines_sequence", "sequence > 0");
            t.HasCheckConstraint("ck_sales_proforma_lines_quantity", "quantity > 0");
            t.HasCheckConstraint("ck_sales_proforma_lines_conversion", "conversion_factor_snapshot > 0");
            t.HasCheckConstraint("ck_sales_proforma_lines_unit_price", "unit_price >= 0");
            t.HasCheckConstraint("ck_sales_proforma_lines_discount", "line_discount_percent >= 0 AND line_discount_percent <= 100");
            t.HasCheckConstraint("ck_sales_proforma_lines_tax", "tax_percent >= 0 AND tax_percent <= 100");
        });
    }
}
