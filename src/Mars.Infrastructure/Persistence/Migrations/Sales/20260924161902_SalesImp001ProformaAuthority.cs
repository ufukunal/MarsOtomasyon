using System;
using Microsoft.EntityFrameworkCore.Migrations;
using Npgsql.EntityFrameworkCore.PostgreSQL.Metadata;

#nullable disable

namespace Mars.Infrastructure.Persistence.Migrations.Sales
{
    /// <inheritdoc />
    public partial class SalesImp001ProformaAuthority : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.CreateTable(
                name: "proformas",
                schema: "sales",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    number = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: false),
                    customer_party_id = table.Column<long>(type: "bigint", nullable: false),
                    customer_code_snapshot = table.Column<string>(type: "text", nullable: false),
                    customer_name_snapshot = table.Column<string>(type: "text", nullable: false),
                    currency_code = table.Column<string>(type: "character varying(3)", maxLength: 3, nullable: false),
                    payment_terms = table.Column<string>(type: "text", nullable: true),
                    document_discount_percent = table.Column<decimal>(type: "numeric(12,6)", precision: 12, scale: 6, nullable: false),
                    source_mode = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    source_document_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    source_version = table.Column<long>(type: "bigint", nullable: false),
                    state = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    cancelled_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_sales_proformas", x => x.id);
                    table.UniqueConstraint("ak_sales_proformas_id_company", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_sales_proformas_currency", "currency_code ~ '^[A-Z]{3}$'");
                    table.CheckConstraint("ck_sales_proformas_discount", "document_discount_percent >= 0 AND document_discount_percent <= 100");
                    table.CheckConstraint("ck_sales_proformas_number", "length(btrim(number)) > 0 AND number = btrim(number)");
                    table.CheckConstraint("ck_sales_proformas_source_version", "source_version > 0");
                    table.CheckConstraint("ck_sales_proformas_version", "version > 0");
                    table.ForeignKey(
                        name: "fk_sales_proformas_customer_company",
                        columns: x => new { x.customer_party_id, x.company_id },
                        principalSchema: "parties",
                        principalTable: "parties",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "proforma_lines",
                schema: "sales",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    sales_proforma_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    sequence = table.Column<int>(type: "integer", nullable: false),
                    source_line_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    product_id = table.Column<long>(type: "bigint", nullable: false),
                    variant_id = table.Column<long>(type: "bigint", nullable: true),
                    uom_id = table.Column<long>(type: "bigint", nullable: false),
                    conversion_factor_snapshot = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    product_code_snapshot = table.Column<string>(type: "text", nullable: false),
                    product_name_snapshot = table.Column<string>(type: "text", nullable: false),
                    variant_code_snapshot = table.Column<string>(type: "text", nullable: true),
                    variant_name_snapshot = table.Column<string>(type: "text", nullable: true),
                    uom_code_snapshot = table.Column<string>(type: "text", nullable: false),
                    uom_name_snapshot = table.Column<string>(type: "text", nullable: false),
                    quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    unit_price = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    line_discount_percent = table.Column<decimal>(type: "numeric(12,6)", precision: 12, scale: 6, nullable: false),
                    tax_percent = table.Column<decimal>(type: "numeric(12,6)", precision: 12, scale: 6, nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_sales_proforma_lines", x => x.id);
                    table.CheckConstraint("ck_sales_proforma_lines_conversion", "conversion_factor_snapshot > 0");
                    table.CheckConstraint("ck_sales_proforma_lines_discount", "line_discount_percent >= 0 AND line_discount_percent <= 100");
                    table.CheckConstraint("ck_sales_proforma_lines_quantity", "quantity > 0");
                    table.CheckConstraint("ck_sales_proforma_lines_sequence", "sequence > 0");
                    table.CheckConstraint("ck_sales_proforma_lines_tax", "tax_percent >= 0 AND tax_percent <= 100");
                    table.CheckConstraint("ck_sales_proforma_lines_unit_price", "unit_price >= 0");
                    table.ForeignKey(
                        name: "FK_proforma_lines_products_product_id_company_id",
                        columns: x => new { x.product_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "products",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_proforma_lines_uoms_uom_id_company_id",
                        columns: x => new { x.uom_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "uoms",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_proforma_lines_variants_variant_id_company_id",
                        columns: x => new { x.variant_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "variants",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_sales_proforma_lines_document_company",
                        columns: x => new { x.sales_proforma_id, x.company_id },
                        principalSchema: "sales",
                        principalTable: "proformas",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateIndex(
                name: "IX_proforma_lines_product_id_company_id",
                schema: "sales",
                table: "proforma_lines",
                columns: new[] { "product_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_proforma_lines_sales_proforma_id_company_id",
                schema: "sales",
                table: "proforma_lines",
                columns: new[] { "sales_proforma_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_proforma_lines_uom_id_company_id",
                schema: "sales",
                table: "proforma_lines",
                columns: new[] { "uom_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_proforma_lines_variant_id_company_id",
                schema: "sales",
                table: "proforma_lines",
                columns: new[] { "variant_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_sales_proforma_lines_document_sequence",
                schema: "sales",
                table: "proforma_lines",
                columns: new[] { "sales_proforma_id", "sequence" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_sales_proforma_lines_public_id",
                schema: "sales",
                table: "proforma_lines",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_proformas_customer_party_id_company_id",
                schema: "sales",
                table: "proformas",
                columns: new[] { "customer_party_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ix_sales_proformas_source",
                schema: "sales",
                table: "proformas",
                columns: new[] { "company_id", "source_mode", "source_document_public_id" });

            migrationBuilder.CreateIndex(
                name: "ux_sales_proformas_company_number",
                schema: "sales",
                table: "proformas",
                columns: new[] { "company_id", "number" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_sales_proformas_public_id",
                schema: "sales",
                table: "proformas",
                column: "public_id",
                unique: true);
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropTable(
                name: "proforma_lines",
                schema: "sales");

            migrationBuilder.DropTable(
                name: "proformas",
                schema: "sales");
        }
    }
}
