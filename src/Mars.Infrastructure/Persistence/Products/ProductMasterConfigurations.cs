using Mars.Domain.Products;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Mars.Infrastructure.Persistence.Products;

internal sealed class ProductConfiguration : IEntityTypeConfiguration<ProductRecord>
{
    public void Configure(EntityTypeBuilder<ProductRecord> b)
    {
        b.ToTable("products","products");
        b.HasKey(x=>x.Id).HasName("pk_products");
        b.Property(x=>x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        b.Property(x=>x.PublicId).HasColumnName("public_id");
        b.Property(x=>x.CompanyId).HasColumnName("company_id");
        b.Property(x=>x.ProductCode).HasColumnName("product_code").IsRequired();
        b.Property(x=>x.Name).HasColumnName("name").IsRequired();
        b.Property(x=>x.Description).HasColumnName("description");
        b.Property(x=>x.Kind).HasColumnName("kind").HasConversion<string>().HasMaxLength(16).IsRequired();
        b.Property(x=>x.Sellable).HasColumnName("sellable");
        b.Property(x=>x.Purchasable).HasColumnName("purchasable");
        b.Property(x=>x.Stockable).HasColumnName("stockable");
        b.Property(x=>x.TrackingStrategy).HasColumnName("tracking_strategy").HasConversion<string>().HasMaxLength(16).IsRequired();
        b.Property(x=>x.State).HasColumnName("state").HasConversion<string>().HasMaxLength(16).IsRequired();
        b.Property(x=>x.Version).HasColumnName("version").IsConcurrencyToken();
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId}).HasName("ak_products_id_company");
        b.HasIndex(x=>x.PublicId).IsUnique().HasDatabaseName("ux_products_public_id");
        b.HasIndex(x=>new{x.CompanyId,x.ProductCode}).IsUnique().HasDatabaseName("ux_products_company_code");
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_products_code","length(btrim(product_code)) > 0 AND product_code = btrim(product_code)");
            t.HasCheckConstraint("ck_products_name","length(btrim(name)) > 0 AND name = btrim(name)");
            t.HasCheckConstraint("ck_products_kind","kind IN ('Goods','Service')");
            t.HasCheckConstraint("ck_products_state","state IN ('Active','Inactive')");
            t.HasCheckConstraint("ck_products_tracking","tracking_strategy IN ('None','Lot','Serial','LotSerial')");
            t.HasCheckConstraint("ck_products_service_stockable","NOT (kind = 'Service' AND stockable = TRUE)");
            t.HasCheckConstraint("ck_products_tracking_stockable","stockable = TRUE OR tracking_strategy = 'None'");
            t.HasCheckConstraint("ck_products_version","version > 0");
        });
    }
}

internal sealed class UnitOfMeasureConfiguration : IEntityTypeConfiguration<UnitOfMeasureRecord>
{
    public void Configure(EntityTypeBuilder<UnitOfMeasureRecord> b)
    {
        b.ToTable("uoms","products");
        b.HasKey(x=>x.Id).HasName("pk_product_uoms_master");
        b.Property(x=>x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        b.Property(x=>x.PublicId).HasColumnName("public_id");
        b.Property(x=>x.CompanyId).HasColumnName("company_id");
        b.Property(x=>x.Code).HasColumnName("code").IsRequired();
        b.Property(x=>x.Name).HasColumnName("name").IsRequired();
        b.Property(x=>x.State).HasColumnName("state").HasConversion<string>().HasMaxLength(16).IsRequired();
        b.Property(x=>x.Version).HasColumnName("version").IsConcurrencyToken();
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId}).HasName("ak_product_uoms_master_id_company");
        b.HasIndex(x=>x.PublicId).IsUnique().HasDatabaseName("ux_product_uoms_master_public_id");
        b.HasIndex(x=>new{x.CompanyId,x.Code}).IsUnique().HasDatabaseName("ux_product_uoms_master_company_code");
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_product_uoms_master_code","length(btrim(code)) > 0 AND code = btrim(code)");
            t.HasCheckConstraint("ck_product_uoms_master_name","length(btrim(name)) > 0 AND name = btrim(name)");
            t.HasCheckConstraint("ck_product_uoms_master_state","state IN ('Active','Inactive')");
            t.HasCheckConstraint("ck_product_uoms_master_version","version > 0");
        });
    }
}

internal sealed class ProductVariantConfiguration : IEntityTypeConfiguration<ProductVariantRecord>
{
    public void Configure(EntityTypeBuilder<ProductVariantRecord> b)
    {
        b.ToTable("variants","products");
        b.HasKey(x=>x.Id).HasName("pk_product_variants");
        b.Property(x=>x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        b.Property(x=>x.PublicId).HasColumnName("public_id");
        b.Property(x=>x.ProductId).HasColumnName("product_id");
        b.Property(x=>x.CompanyId).HasColumnName("company_id");
        b.Property(x=>x.VariantCode).HasColumnName("variant_code");
        b.Property(x=>x.Name).HasColumnName("name").IsRequired();
        b.Property(x=>x.TrackingStrategy).HasColumnName("tracking_strategy").HasConversion<string>().HasMaxLength(16);
        b.Property(x=>x.State).HasColumnName("state").HasConversion<string>().HasMaxLength(16).IsRequired();
        b.Property(x=>x.Version).HasColumnName("version").IsConcurrencyToken();
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId}).HasName("ak_product_variants_id_company");
        b.HasIndex(x=>x.PublicId).IsUnique().HasDatabaseName("ux_product_variants_public_id");
        b.HasIndex(x=>new{x.CompanyId,x.VariantCode}).IsUnique().HasDatabaseName("ux_product_variants_company_code").HasFilter(""variant_code" IS NOT NULL");
        b.HasOne<ProductRecord>().WithMany()
            .HasForeignKey(x=>new{x.ProductId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId})
            .OnDelete(DeleteBehavior.Restrict).HasConstraintName("fk_product_variants_product_company");
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_product_variants_code","variant_code IS NULL OR (length(btrim(variant_code)) > 0 AND variant_code = btrim(variant_code))");
            t.HasCheckConstraint("ck_product_variants_name","length(btrim(name)) > 0 AND name = btrim(name)");
            t.HasCheckConstraint("ck_product_variants_tracking","tracking_strategy IS NULL OR tracking_strategy IN ('None','Lot','Serial','LotSerial')");
            t.HasCheckConstraint("ck_product_variants_state","state IN ('Active','Inactive')");
            t.HasCheckConstraint("ck_product_variants_version","version > 0");
        });
    }
}

internal sealed class ProductUomConfiguration : IEntityTypeConfiguration<ProductUomRecord>
{
    public void Configure(EntityTypeBuilder<ProductUomRecord> b)
    {
        b.ToTable("product_uoms","products");
        b.HasKey(x=>x.Id).HasName("pk_product_uoms");
        b.Property(x=>x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        b.Property(x=>x.PublicId).HasColumnName("public_id");
        b.Property(x=>x.ProductId).HasColumnName("product_id");
        b.Property(x=>x.VariantId).HasColumnName("variant_id");
        b.Property(x=>x.UomId).HasColumnName("uom_id");
        b.Property(x=>x.CompanyId).HasColumnName("company_id");
        b.Property(x=>x.Role).HasColumnName("role").HasConversion<string>().HasMaxLength(16).IsRequired();
        b.Property(x=>x.ConversionFactor).HasColumnName("conversion_factor").HasPrecision(28,9);
        b.Property(x=>x.State).HasColumnName("state").HasConversion<string>().HasMaxLength(16).IsRequired();
        b.Property(x=>x.Version).HasColumnName("version").IsConcurrencyToken();
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId}).HasName("ak_product_uoms_id_company");
        b.HasIndex(x=>x.PublicId).IsUnique().HasDatabaseName("ux_product_uoms_public_id");
        b.HasIndex(x=>x.ProductId).IsUnique().HasDatabaseName("ux_product_uoms_active_base")
            .HasFilter(""role" = 'Base' AND "state" = 'Active'");
        b.HasIndex(x=>new{x.ProductId,x.UomId}).IsUnique().HasDatabaseName("ux_product_uoms_active_product")
            .HasFilter(""variant_id" IS NULL AND "state" = 'Active'");
        b.HasIndex(x=>new{x.ProductId,x.VariantId,x.UomId}).IsUnique().HasDatabaseName("ux_product_uoms_active_variant")
            .HasFilter(""variant_id" IS NOT NULL AND "state" = 'Active'");
        b.HasOne<ProductRecord>().WithMany()
            .HasForeignKey(x=>new{x.ProductId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId})
            .OnDelete(DeleteBehavior.Restrict).HasConstraintName("fk_product_uoms_product_company");
        b.HasOne<ProductVariantRecord>().WithMany()
            .HasForeignKey(x=>new{x.VariantId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId})
            .OnDelete(DeleteBehavior.Restrict).HasConstraintName("fk_product_uoms_variant_company");
        b.HasOne<UnitOfMeasureRecord>().WithMany()
            .HasForeignKey(x=>new{x.UomId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId})
            .OnDelete(DeleteBehavior.Restrict).HasConstraintName("fk_product_uoms_uom_company");
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_product_uoms_role","role IN ('Base','Alternate')");
            t.HasCheckConstraint("ck_product_uoms_factor","conversion_factor > 0");
            t.HasCheckConstraint("ck_product_uoms_base","role <> 'Base' OR (variant_id IS NULL AND conversion_factor = 1)");
            t.HasCheckConstraint("ck_product_uoms_state","state IN ('Active','Inactive')");
            t.HasCheckConstraint("ck_product_uoms_version","version > 0");
        });
    }
}

internal sealed class ProductBarcodeConfiguration : IEntityTypeConfiguration<ProductBarcodeRecord>
{
    public void Configure(EntityTypeBuilder<ProductBarcodeRecord> b)
    {
        b.ToTable("barcodes","products");
        b.HasKey(x=>x.Id).HasName("pk_product_barcodes");
        b.Property(x=>x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        b.Property(x=>x.PublicId).HasColumnName("public_id");
        b.Property(x=>x.ProductId).HasColumnName("product_id");
        b.Property(x=>x.VariantId).HasColumnName("variant_id");
        b.Property(x=>x.ProductUomId).HasColumnName("product_uom_id");
        b.Property(x=>x.CompanyId).HasColumnName("company_id");
        b.Property(x=>x.Namespace).HasColumnName("namespace").IsRequired();
        b.Property(x=>x.Value).HasColumnName("value").IsRequired();
        b.Property(x=>x.State).HasColumnName("state").HasConversion<string>().HasMaxLength(16).IsRequired();
        b.Property(x=>x.Version).HasColumnName("version").IsConcurrencyToken();
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasIndex(x=>x.PublicId).IsUnique().HasDatabaseName("ux_product_barcodes_public_id");
        b.HasIndex(x=>new{x.CompanyId,x.Namespace,x.Value}).IsUnique()
            .HasDatabaseName("ux_product_barcodes_active_code").HasFilter(""state" = 'Active'");
        b.HasOne<ProductRecord>().WithMany()
            .HasForeignKey(x=>new{x.ProductId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId})
            .OnDelete(DeleteBehavior.Restrict).HasConstraintName("fk_product_barcodes_product_company");
        b.HasOne<ProductVariantRecord>().WithMany()
            .HasForeignKey(x=>new{x.VariantId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId})
            .OnDelete(DeleteBehavior.Restrict).HasConstraintName("fk_product_barcodes_variant_company");
        b.HasOne<ProductUomRecord>().WithMany()
            .HasForeignKey(x=>new{x.ProductUomId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId})
            .OnDelete(DeleteBehavior.Restrict).HasConstraintName("fk_product_barcodes_product_uom_company");
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_product_barcodes_namespace","length(btrim(namespace)) > 0 AND namespace = btrim(namespace)");
            t.HasCheckConstraint("ck_product_barcodes_value","length(btrim(value)) > 0 AND value = btrim(value)");
            t.HasCheckConstraint("ck_product_barcodes_state","state IN ('Active','Inactive')");
            t.HasCheckConstraint("ck_product_barcodes_version","version > 0");
        });
    }
}

internal sealed class ProductCategoryConfiguration : IEntityTypeConfiguration<ProductCategoryRecord>
{
    public void Configure(EntityTypeBuilder<ProductCategoryRecord> b)
    {
        b.ToTable("categories","products");
        b.HasKey(x=>x.Id).HasName("pk_product_categories");
        b.Property(x=>x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        b.Property(x=>x.PublicId).HasColumnName("public_id");
        b.Property(x=>x.CompanyId).HasColumnName("company_id");
        b.Property(x=>x.Code).HasColumnName("code").IsRequired();
        b.Property(x=>x.Name).HasColumnName("name").IsRequired();
        b.Property(x=>x.ParentCategoryId).HasColumnName("parent_category_id");
        b.Property(x=>x.Version).HasColumnName("version").IsConcurrencyToken();
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId}).HasName("ak_product_categories_id_company");
        b.HasIndex(x=>x.PublicId).IsUnique().HasDatabaseName("ux_product_categories_public_id");
        b.HasIndex(x=>new{x.CompanyId,x.Code}).IsUnique().HasDatabaseName("ux_product_categories_company_code");
        b.HasOne<ProductCategoryRecord>().WithMany()
            .HasForeignKey(x=>new{x.ParentCategoryId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId})
            .OnDelete(DeleteBehavior.Restrict).HasConstraintName("fk_product_categories_parent_company");
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_product_categories_code","length(btrim(code)) > 0 AND code = btrim(code)");
            t.HasCheckConstraint("ck_product_categories_name","length(btrim(name)) > 0 AND name = btrim(name)");
            t.HasCheckConstraint("ck_product_categories_parent","parent_category_id IS NULL OR parent_category_id <> id");
            t.HasCheckConstraint("ck_product_categories_version","version > 0");
        });
    }
}

internal sealed class ProductCategoryLinkConfiguration : IEntityTypeConfiguration<ProductCategoryLinkRecord>
{
    public void Configure(EntityTypeBuilder<ProductCategoryLinkRecord> b)
    {
        b.ToTable("product_categories","products");
        b.HasKey(x=>x.Id).HasName("pk_product_category_links");
        b.Property(x=>x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        b.Property(x=>x.ProductId).HasColumnName("product_id");
        b.Property(x=>x.CategoryId).HasColumnName("category_id");
        b.Property(x=>x.CompanyId).HasColumnName("company_id");
        b.Property(x=>x.IsPrimary).HasColumnName("is_primary");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasIndex(x=>new{x.ProductId,x.CategoryId}).IsUnique().HasDatabaseName("ux_product_category_links_product_category");
        b.HasIndex(x=>x.ProductId).IsUnique().HasDatabaseName("ux_product_category_links_primary").HasFilter(""is_primary" = TRUE");
        b.HasOne<ProductRecord>().WithMany()
            .HasForeignKey(x=>new{x.ProductId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId})
            .OnDelete(DeleteBehavior.Restrict).HasConstraintName("fk_product_category_links_product_company");
        b.HasOne<ProductCategoryRecord>().WithMany()
            .HasForeignKey(x=>new{x.CategoryId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId})
            .OnDelete(DeleteBehavior.Restrict).HasConstraintName("fk_product_category_links_category_company");
    }
}

internal sealed class ProductExternalMappingConfiguration : IEntityTypeConfiguration<ProductExternalMappingRecord>
{
    public void Configure(EntityTypeBuilder<ProductExternalMappingRecord> b)
    {
        b.ToTable("external_mappings","products");
        b.HasKey(x=>x.Id).HasName("pk_product_external_mappings");
        b.Property(x=>x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        b.Property(x=>x.PublicId).HasColumnName("public_id");
        b.Property(x=>x.ProductId).HasColumnName("product_id");
        b.Property(x=>x.VariantId).HasColumnName("variant_id");
        b.Property(x=>x.CompanyId).HasColumnName("company_id");
        b.Property(x=>x.SystemCode).HasColumnName("system_code").IsRequired();
        b.Property(x=>x.AccountScope).HasColumnName("account_scope").IsRequired();
        b.Property(x=>x.ExternalIdentity).HasColumnName("external_identity").IsRequired();
        b.Property(x=>x.State).HasColumnName("state").HasConversion<string>().HasMaxLength(16).IsRequired();
        b.Property(x=>x.Version).HasColumnName("version").IsConcurrencyToken();
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasIndex(x=>x.PublicId).IsUnique().HasDatabaseName("ux_product_external_mappings_public_id");
        b.HasIndex(x=>new{x.CompanyId,x.SystemCode,x.AccountScope,x.ExternalIdentity}).IsUnique()
            .HasDatabaseName("ux_product_external_mappings_active_scope").HasFilter(""state" = 'Active'");
        b.HasOne<ProductRecord>().WithMany()
            .HasForeignKey(x=>new{x.ProductId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId})
            .OnDelete(DeleteBehavior.Restrict).HasConstraintName("fk_product_external_mappings_product_company");
        b.HasOne<ProductVariantRecord>().WithMany()
            .HasForeignKey(x=>new{x.VariantId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId})
            .OnDelete(DeleteBehavior.Restrict).HasConstraintName("fk_product_external_mappings_variant_company");
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_product_external_mappings_system","length(btrim(system_code)) > 0 AND system_code = btrim(system_code)");
            t.HasCheckConstraint("ck_product_external_mappings_identity","length(btrim(external_identity)) > 0 AND external_identity = btrim(external_identity)");
            t.HasCheckConstraint("ck_product_external_mappings_state","state IN ('Active','Inactive')");
            t.HasCheckConstraint("ck_product_external_mappings_version","version > 0");
        });
    }
}
