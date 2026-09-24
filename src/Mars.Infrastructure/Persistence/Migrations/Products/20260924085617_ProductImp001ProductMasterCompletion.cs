using System;
using Microsoft.EntityFrameworkCore.Migrations;
using Npgsql.EntityFrameworkCore.PostgreSQL.Metadata;

#nullable disable

namespace Mars.Infrastructure.Persistence.Migrations.Products
{
    /// <inheritdoc />
    public partial class ProductImp001ProductMasterCompletion : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.EnsureSchema(
                name: "products");

            migrationBuilder.CreateTable(
                name: "categories",
                schema: "products",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    code = table.Column<string>(type: "text", nullable: false),
                    name = table.Column<string>(type: "text", nullable: false),
                    parent_category_id = table.Column<long>(type: "bigint", nullable: true),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_product_categories", x => x.id);
                    table.UniqueConstraint("ak_product_categories_id_company", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_product_categories_code", "length(btrim(code)) > 0 AND code = btrim(code)");
                    table.CheckConstraint("ck_product_categories_name", "length(btrim(name)) > 0 AND name = btrim(name)");
                    table.CheckConstraint("ck_product_categories_parent", "parent_category_id IS NULL OR parent_category_id <> id");
                    table.CheckConstraint("ck_product_categories_version", "version > 0");
                    table.ForeignKey(
                        name: "fk_product_categories_parent_company",
                        columns: x => new { x.parent_category_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "categories",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "products",
                schema: "products",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    product_code = table.Column<string>(type: "text", nullable: false),
                    name = table.Column<string>(type: "text", nullable: false),
                    description = table.Column<string>(type: "text", nullable: true),
                    kind = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    sellable = table.Column<bool>(type: "boolean", nullable: false),
                    purchasable = table.Column<bool>(type: "boolean", nullable: false),
                    stockable = table.Column<bool>(type: "boolean", nullable: false),
                    tracking_strategy = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    state = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_products", x => x.id);
                    table.UniqueConstraint("ak_products_id_company", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_products_code", "length(btrim(product_code)) > 0 AND product_code = btrim(product_code)");
                    table.CheckConstraint("ck_products_kind", "kind IN ('Goods','Service')");
                    table.CheckConstraint("ck_products_name", "length(btrim(name)) > 0 AND name = btrim(name)");
                    table.CheckConstraint("ck_products_service_stockable", "NOT (kind = 'Service' AND stockable = TRUE)");
                    table.CheckConstraint("ck_products_state", "state IN ('Active','Inactive')");
                    table.CheckConstraint("ck_products_tracking", "tracking_strategy IN ('None','Lot','Serial','LotSerial')");
                    table.CheckConstraint("ck_products_tracking_stockable", "stockable = TRUE OR tracking_strategy = 'None'");
                    table.CheckConstraint("ck_products_version", "version > 0");
                });

            migrationBuilder.CreateTable(
                name: "uoms",
                schema: "products",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    code = table.Column<string>(type: "text", nullable: false),
                    name = table.Column<string>(type: "text", nullable: false),
                    state = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_product_uoms_master", x => x.id);
                    table.UniqueConstraint("ak_product_uoms_master_id_company", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_product_uoms_master_code", "length(btrim(code)) > 0 AND code = btrim(code)");
                    table.CheckConstraint("ck_product_uoms_master_name", "length(btrim(name)) > 0 AND name = btrim(name)");
                    table.CheckConstraint("ck_product_uoms_master_state", "state IN ('Active','Inactive')");
                    table.CheckConstraint("ck_product_uoms_master_version", "version > 0");
                });

            migrationBuilder.CreateTable(
                name: "product_categories",
                schema: "products",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    product_id = table.Column<long>(type: "bigint", nullable: false),
                    category_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    is_primary = table.Column<bool>(type: "boolean", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_product_category_links", x => x.id);
                    table.ForeignKey(
                        name: "fk_product_category_links_category_company",
                        columns: x => new { x.category_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "categories",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_product_category_links_product_company",
                        columns: x => new { x.product_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "products",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "variants",
                schema: "products",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    product_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    variant_code = table.Column<string>(type: "text", nullable: true),
                    name = table.Column<string>(type: "text", nullable: false),
                    tracking_strategy = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: true),
                    state = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_product_variants", x => x.id);
                    table.UniqueConstraint("ak_product_variants_id_company", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_product_variants_code", "variant_code IS NULL OR (length(btrim(variant_code)) > 0 AND variant_code = btrim(variant_code))");
                    table.CheckConstraint("ck_product_variants_name", "length(btrim(name)) > 0 AND name = btrim(name)");
                    table.CheckConstraint("ck_product_variants_state", "state IN ('Active','Inactive')");
                    table.CheckConstraint("ck_product_variants_tracking", "tracking_strategy IS NULL OR tracking_strategy IN ('None','Lot','Serial','LotSerial')");
                    table.CheckConstraint("ck_product_variants_version", "version > 0");
                    table.ForeignKey(
                        name: "fk_product_variants_product_company",
                        columns: x => new { x.product_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "products",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "external_mappings",
                schema: "products",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    product_id = table.Column<long>(type: "bigint", nullable: false),
                    variant_id = table.Column<long>(type: "bigint", nullable: true),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    system_code = table.Column<string>(type: "text", nullable: false),
                    account_scope = table.Column<string>(type: "text", nullable: false),
                    external_identity = table.Column<string>(type: "text", nullable: false),
                    state = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_product_external_mappings", x => x.id);
                    table.CheckConstraint("ck_product_external_mappings_identity", "length(btrim(external_identity)) > 0 AND external_identity = btrim(external_identity)");
                    table.CheckConstraint("ck_product_external_mappings_state", "state IN ('Active','Inactive')");
                    table.CheckConstraint("ck_product_external_mappings_system", "length(btrim(system_code)) > 0 AND system_code = btrim(system_code)");
                    table.CheckConstraint("ck_product_external_mappings_version", "version > 0");
                    table.ForeignKey(
                        name: "fk_product_external_mappings_product_company",
                        columns: x => new { x.product_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "products",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_product_external_mappings_variant_company",
                        columns: x => new { x.variant_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "variants",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "product_uoms",
                schema: "products",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    product_id = table.Column<long>(type: "bigint", nullable: false),
                    variant_id = table.Column<long>(type: "bigint", nullable: true),
                    uom_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    role = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    conversion_factor = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    state = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_product_uoms", x => x.id);
                    table.UniqueConstraint("ak_product_uoms_id_company", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_product_uoms_base", "role <> 'Base' OR (variant_id IS NULL AND conversion_factor = 1)");
                    table.CheckConstraint("ck_product_uoms_factor", "conversion_factor > 0");
                    table.CheckConstraint("ck_product_uoms_role", "role IN ('Base','Alternate')");
                    table.CheckConstraint("ck_product_uoms_state", "state IN ('Active','Inactive')");
                    table.CheckConstraint("ck_product_uoms_version", "version > 0");
                    table.ForeignKey(
                        name: "fk_product_uoms_product_company",
                        columns: x => new { x.product_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "products",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_product_uoms_uom_company",
                        columns: x => new { x.uom_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "uoms",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_product_uoms_variant_company",
                        columns: x => new { x.variant_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "variants",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "barcodes",
                schema: "products",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    product_id = table.Column<long>(type: "bigint", nullable: false),
                    variant_id = table.Column<long>(type: "bigint", nullable: true),
                    product_uom_id = table.Column<long>(type: "bigint", nullable: true),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    @namespace = table.Column<string>(name: "namespace", type: "text", nullable: false),
                    value = table.Column<string>(type: "text", nullable: false),
                    state = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_product_barcodes", x => x.id);
                    table.CheckConstraint("ck_product_barcodes_namespace", "length(btrim(namespace)) > 0 AND namespace = btrim(namespace)");
                    table.CheckConstraint("ck_product_barcodes_state", "state IN ('Active','Inactive')");
                    table.CheckConstraint("ck_product_barcodes_value", "length(btrim(value)) > 0 AND value = btrim(value)");
                    table.CheckConstraint("ck_product_barcodes_version", "version > 0");
                    table.ForeignKey(
                        name: "fk_product_barcodes_product_company",
                        columns: x => new { x.product_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "products",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_product_barcodes_product_uom_company",
                        columns: x => new { x.product_uom_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "product_uoms",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_product_barcodes_variant_company",
                        columns: x => new { x.variant_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "variants",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateIndex(
                name: "IX_barcodes_product_id_company_id",
                schema: "products",
                table: "barcodes",
                columns: new[] { "product_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_barcodes_product_uom_id_company_id",
                schema: "products",
                table: "barcodes",
                columns: new[] { "product_uom_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_barcodes_variant_id_company_id",
                schema: "products",
                table: "barcodes",
                columns: new[] { "variant_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_product_barcodes_active_code",
                schema: "products",
                table: "barcodes",
                columns: new[] { "company_id", "namespace", "value" },
                unique: true,
                filter: "state = 'Active'");

            migrationBuilder.CreateIndex(
                name: "ux_product_barcodes_public_id",
                schema: "products",
                table: "barcodes",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_categories_parent_category_id_company_id",
                schema: "products",
                table: "categories",
                columns: new[] { "parent_category_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_product_categories_company_code",
                schema: "products",
                table: "categories",
                columns: new[] { "company_id", "code" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_product_categories_public_id",
                schema: "products",
                table: "categories",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_external_mappings_product_id_company_id",
                schema: "products",
                table: "external_mappings",
                columns: new[] { "product_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_external_mappings_variant_id_company_id",
                schema: "products",
                table: "external_mappings",
                columns: new[] { "variant_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_product_external_mappings_active_scope",
                schema: "products",
                table: "external_mappings",
                columns: new[] { "company_id", "system_code", "account_scope", "external_identity" },
                unique: true,
                filter: "state = 'Active'");

            migrationBuilder.CreateIndex(
                name: "ux_product_external_mappings_public_id",
                schema: "products",
                table: "external_mappings",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_product_categories_category_id_company_id",
                schema: "products",
                table: "product_categories",
                columns: new[] { "category_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_product_categories_product_id_company_id",
                schema: "products",
                table: "product_categories",
                columns: new[] { "product_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_product_category_links_primary",
                schema: "products",
                table: "product_categories",
                column: "product_id",
                unique: true,
                filter: "is_primary = TRUE");

            migrationBuilder.CreateIndex(
                name: "ux_product_category_links_product_category",
                schema: "products",
                table: "product_categories",
                columns: new[] { "product_id", "category_id" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_product_uoms_product_id_company_id",
                schema: "products",
                table: "product_uoms",
                columns: new[] { "product_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_product_uoms_uom_id_company_id",
                schema: "products",
                table: "product_uoms",
                columns: new[] { "uom_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_product_uoms_variant_id_company_id",
                schema: "products",
                table: "product_uoms",
                columns: new[] { "variant_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_product_uoms_active_base",
                schema: "products",
                table: "product_uoms",
                column: "product_id",
                unique: true,
                filter: "role = 'Base' AND state = 'Active'");

            migrationBuilder.CreateIndex(
                name: "ux_product_uoms_active_product",
                schema: "products",
                table: "product_uoms",
                columns: new[] { "product_id", "uom_id" },
                unique: true,
                filter: "variant_id IS NULL AND state = 'Active'");

            migrationBuilder.CreateIndex(
                name: "ux_product_uoms_active_variant",
                schema: "products",
                table: "product_uoms",
                columns: new[] { "product_id", "variant_id", "uom_id" },
                unique: true,
                filter: "variant_id IS NOT NULL AND state = 'Active'");

            migrationBuilder.CreateIndex(
                name: "ux_product_uoms_public_id",
                schema: "products",
                table: "product_uoms",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_products_company_code",
                schema: "products",
                table: "products",
                columns: new[] { "company_id", "product_code" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_products_public_id",
                schema: "products",
                table: "products",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_product_uoms_master_company_code",
                schema: "products",
                table: "uoms",
                columns: new[] { "company_id", "code" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_product_uoms_master_public_id",
                schema: "products",
                table: "uoms",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_variants_product_id_company_id",
                schema: "products",
                table: "variants",
                columns: new[] { "product_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_product_variants_company_code",
                schema: "products",
                table: "variants",
                columns: new[] { "company_id", "variant_code" },
                unique: true,
                filter: "variant_code IS NOT NULL");

            migrationBuilder.CreateIndex(
                name: "ux_product_variants_public_id",
                schema: "products",
                table: "variants",
                column: "public_id",
                unique: true);
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropTable(
                name: "barcodes",
                schema: "products");

            migrationBuilder.DropTable(
                name: "external_mappings",
                schema: "products");

            migrationBuilder.DropTable(
                name: "product_categories",
                schema: "products");

            migrationBuilder.DropTable(
                name: "product_uoms",
                schema: "products");

            migrationBuilder.DropTable(
                name: "categories",
                schema: "products");

            migrationBuilder.DropTable(
                name: "uoms",
                schema: "products");

            migrationBuilder.DropTable(
                name: "variants",
                schema: "products");

            migrationBuilder.DropTable(
                name: "products",
                schema: "products");
        }
    }
}
