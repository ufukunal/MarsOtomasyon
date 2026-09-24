using System;
using Microsoft.EntityFrameworkCore.Migrations;
using Npgsql.EntityFrameworkCore.PostgreSQL.Metadata;

#nullable disable

namespace Mars.Infrastructure.Persistence.Migrations.Purchasing
{
    /// <inheritdoc />
    public partial class PurchasingImp001CommercialReceiptInvoiceAuthority : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.EnsureSchema(
                name: "purchasing");

            migrationBuilder.CreateTable(
                name: "purchase_match_results",
                schema: "purchasing",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    kind = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    state = table.Column<string>(type: "character varying(24)", maxLength: 24, nullable: false),
                    supplier_invoice_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    purchase_order_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    goods_receipt_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    quantity_variance = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    price_variance = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    block_reason = table.Column<string>(type: "text", nullable: true),
                    evaluated_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    evaluated_by_actor_id = table.Column<Guid>(type: "uuid", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_purchase_match_results", x => x.id);
                    table.UniqueConstraint("AK_purchase_match_results_id_company_id", x => new { x.id, x.company_id });
                });

            migrationBuilder.CreateTable(
                name: "purchase_orders",
                schema: "purchasing",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    number = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: false),
                    supplier_party_id = table.Column<long>(type: "bigint", nullable: false),
                    supplier_code_snapshot = table.Column<string>(type: "text", nullable: false),
                    supplier_name_snapshot = table.Column<string>(type: "text", nullable: false),
                    currency_code = table.Column<string>(type: "character varying(3)", maxLength: 3, nullable: false),
                    payment_terms = table.Column<string>(type: "text", nullable: true),
                    state = table.Column<string>(type: "character varying(32)", maxLength: 32, nullable: false),
                    current_version_number = table.Column<long>(type: "bigint", nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    confirmed_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true),
                    closed_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_purchase_orders", x => x.id);
                    table.UniqueConstraint("AK_purchase_orders_id_company_id", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_purchasing_orders_currency", "currency_code ~ '^[A-Z]{3}$'");
                    table.CheckConstraint("ck_purchasing_orders_number", "length(btrim(number)) > 0 AND number = btrim(number)");
                    table.CheckConstraint("ck_purchasing_orders_version", "version > 0 AND current_version_number > 0");
                    table.ForeignKey(
                        name: "FK_purchase_orders_parties_supplier_party_id_company_id",
                        columns: x => new { x.supplier_party_id, x.company_id },
                        principalSchema: "parties",
                        principalTable: "parties",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "supplier_invoices",
                schema: "purchasing",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    number = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: false),
                    supplier_party_id = table.Column<long>(type: "bigint", nullable: false),
                    state = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    source_mode = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    document_date = table.Column<DateOnly>(type: "date", nullable: false),
                    due_date = table.Column<DateOnly>(type: "date", nullable: false),
                    currency_code = table.Column<string>(type: "character varying(3)", maxLength: 3, nullable: false),
                    document_discount_percent = table.Column<decimal>(type: "numeric(12,6)", precision: 12, scale: 6, nullable: false),
                    supplier_code_snapshot = table.Column<string>(type: "text", nullable: false),
                    supplier_legal_name_snapshot = table.Column<string>(type: "text", nullable: false),
                    net_total = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    tax_total = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    gross_total = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    cancelled_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_supplier_invoices", x => x.id);
                    table.UniqueConstraint("AK_supplier_invoices_id_company_id", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_purchasing_supplier_invoices_currency", "currency_code ~ '^[A-Z]{3}$'");
                    table.CheckConstraint("ck_purchasing_supplier_invoices_dates", "due_date >= document_date");
                    table.CheckConstraint("ck_purchasing_supplier_invoices_discount", "document_discount_percent >= 0 AND document_discount_percent <= 100");
                    table.CheckConstraint("ck_purchasing_supplier_invoices_totals", "net_total >= 0 AND tax_total >= 0 AND gross_total = net_total + tax_total");
                    table.CheckConstraint("ck_purchasing_supplier_invoices_version", "version > 0");
                    table.ForeignKey(
                        name: "FK_supplier_invoices_parties_supplier_party_id_company_id",
                        columns: x => new { x.supplier_party_id, x.company_id },
                        principalSchema: "parties",
                        principalTable: "parties",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "purchase_match_exceptions",
                schema: "purchasing",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    purchase_match_result_id = table.Column<long>(type: "bigint", nullable: false),
                    reason = table.Column<string>(type: "character varying(512)", maxLength: 512, nullable: false),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    approval_decision_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    decided_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_purchase_match_exceptions", x => x.id);
                    table.CheckConstraint("ck_purchasing_match_exception_reason", "length(btrim(reason)) > 0 AND reason = btrim(reason)");
                    table.ForeignKey(
                        name: "FK_purchase_match_exceptions_purchase_match_results_purchase_m~",
                        columns: x => new { x.purchase_match_result_id, x.company_id },
                        principalSchema: "purchasing",
                        principalTable: "purchase_match_results",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "goods_receipts",
                schema: "purchasing",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    number = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: false),
                    purchase_order_id = table.Column<long>(type: "bigint", nullable: false),
                    purchase_order_version_number = table.Column<long>(type: "bigint", nullable: false),
                    warehouse_id = table.Column<long>(type: "bigint", nullable: false),
                    state = table.Column<string>(type: "character varying(24)", maxLength: 24, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    reversal_of_goods_receipt_id = table.Column<long>(type: "bigint", nullable: true),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    posted_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true),
                    cancelled_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_goods_receipts", x => x.id);
                    table.UniqueConstraint("AK_goods_receipts_id_company_id", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_purchasing_receipts_not_self_reversal", "reversal_of_goods_receipt_id IS NULL OR reversal_of_goods_receipt_id <> id");
                    table.CheckConstraint("ck_purchasing_receipts_number", "length(btrim(number)) > 0 AND number = btrim(number)");
                    table.CheckConstraint("ck_purchasing_receipts_order_version", "purchase_order_version_number > 0");
                    table.CheckConstraint("ck_purchasing_receipts_version", "version > 0");
                    table.ForeignKey(
                        name: "FK_goods_receipts_goods_receipts_reversal_of_goods_receipt_id_~",
                        columns: x => new { x.reversal_of_goods_receipt_id, x.company_id },
                        principalSchema: "purchasing",
                        principalTable: "goods_receipts",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_goods_receipts_purchase_orders_purchase_order_id_company_id",
                        columns: x => new { x.purchase_order_id, x.company_id },
                        principalSchema: "purchasing",
                        principalTable: "purchase_orders",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_goods_receipts_warehouses_warehouse_id_company_id",
                        columns: x => new { x.warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "warehouses",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "purchase_order_amendments",
                schema: "purchasing",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    purchase_order_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    base_version_number = table.Column<long>(type: "bigint", nullable: false),
                    result_version_number = table.Column<long>(type: "bigint", nullable: false),
                    state = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    reason = table.Column<string>(type: "character varying(512)", maxLength: 512, nullable: false),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    activated_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_purchase_order_amendments", x => x.id);
                    table.UniqueConstraint("AK_purchase_order_amendments_id_company_id", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_purchasing_order_amendment_reason", "length(btrim(reason)) > 0 AND reason = btrim(reason)");
                    table.CheckConstraint("ck_purchasing_order_amendment_versions", "base_version_number > 0 AND result_version_number > base_version_number");
                    table.ForeignKey(
                        name: "FK_purchase_order_amendments_purchase_orders_purchase_order_id~",
                        columns: x => new { x.purchase_order_id, x.company_id },
                        principalSchema: "purchasing",
                        principalTable: "purchase_orders",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "purchase_order_versions",
                schema: "purchasing",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    purchase_order_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    version_number = table.Column<long>(type: "bigint", nullable: false),
                    document_discount_percent = table.Column<decimal>(type: "numeric(12,6)", precision: 12, scale: 6, nullable: false),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_purchase_order_versions", x => x.id);
                    table.UniqueConstraint("AK_purchase_order_versions_id_company_id", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_purchasing_order_versions_discount", "document_discount_percent >= 0 AND document_discount_percent <= 100");
                    table.CheckConstraint("ck_purchasing_order_versions_number", "version_number > 0");
                    table.ForeignKey(
                        name: "FK_purchase_order_versions_purchase_orders_purchase_order_id_c~",
                        columns: x => new { x.purchase_order_id, x.company_id },
                        principalSchema: "purchasing",
                        principalTable: "purchase_orders",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "supplier_invoice_lines",
                schema: "purchasing",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    supplier_invoice_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    sequence = table.Column<int>(type: "integer", nullable: false),
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
                    document_discount = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    tax_percent = table.Column<decimal>(type: "numeric(12,6)", precision: 12, scale: 6, nullable: false),
                    taxable_base = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    tax_amount = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    line_total = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_supplier_invoice_lines", x => x.id);
                    table.UniqueConstraint("AK_supplier_invoice_lines_id_company_id", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_purchasing_invoice_lines_conversion", "conversion_factor_snapshot > 0");
                    table.CheckConstraint("ck_purchasing_invoice_lines_discount", "line_discount_percent >= 0 AND line_discount_percent <= 100");
                    table.CheckConstraint("ck_purchasing_invoice_lines_quantity", "quantity > 0");
                    table.CheckConstraint("ck_purchasing_invoice_lines_sequence", "sequence > 0");
                    table.CheckConstraint("ck_purchasing_invoice_lines_tax", "tax_percent >= 0 AND tax_percent <= 100");
                    table.CheckConstraint("ck_purchasing_invoice_lines_unit_price", "unit_price >= 0");
                    table.ForeignKey(
                        name: "FK_supplier_invoice_lines_products_product_id_company_id",
                        columns: x => new { x.product_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "products",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_supplier_invoice_lines_supplier_invoices_supplier_invoice_i~",
                        columns: x => new { x.supplier_invoice_id, x.company_id },
                        principalSchema: "purchasing",
                        principalTable: "supplier_invoices",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_supplier_invoice_lines_uoms_uom_id_company_id",
                        columns: x => new { x.uom_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "uoms",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_supplier_invoice_lines_variants_variant_id_company_id",
                        columns: x => new { x.variant_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "variants",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "goods_receipt_lines",
                schema: "purchasing",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    goods_receipt_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    sequence = table.Column<int>(type: "integer", nullable: false),
                    purchase_order_line_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    product_id = table.Column<long>(type: "bigint", nullable: false),
                    variant_id = table.Column<long>(type: "bigint", nullable: true),
                    uom_id = table.Column<long>(type: "bigint", nullable: false),
                    conversion_factor_snapshot = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    warehouse_id = table.Column<long>(type: "bigint", nullable: false),
                    location_id = table.Column<long>(type: "bigint", nullable: true),
                    lot_id = table.Column<long>(type: "bigint", nullable: true),
                    serial_id = table.Column<long>(type: "bigint", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_goods_receipt_lines", x => x.id);
                    table.UniqueConstraint("AK_goods_receipt_lines_id_company_id", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_purchasing_receipt_lines_conversion", "conversion_factor_snapshot > 0");
                    table.CheckConstraint("ck_purchasing_receipt_lines_quantity", "quantity > 0");
                    table.CheckConstraint("ck_purchasing_receipt_lines_sequence", "sequence > 0");
                    table.ForeignKey(
                        name: "FK_goods_receipt_lines_goods_receipts_goods_receipt_id_company~",
                        columns: x => new { x.goods_receipt_id, x.company_id },
                        principalSchema: "purchasing",
                        principalTable: "goods_receipts",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_goods_receipt_lines_locations_location_id_warehouse_id_comp~",
                        columns: x => new { x.location_id, x.warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "locations",
                        principalColumns: new[] { "id", "warehouse_id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_goods_receipt_lines_lots_lot_id_company_id",
                        columns: x => new { x.lot_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "lots",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_goods_receipt_lines_products_product_id_company_id",
                        columns: x => new { x.product_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "products",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_goods_receipt_lines_serials_serial_id_company_id",
                        columns: x => new { x.serial_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "serials",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_goods_receipt_lines_uoms_uom_id_company_id",
                        columns: x => new { x.uom_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "uoms",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_goods_receipt_lines_variants_variant_id_company_id",
                        columns: x => new { x.variant_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "variants",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_goods_receipt_lines_warehouses_warehouse_id_company_id",
                        columns: x => new { x.warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "warehouses",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "purchase_order_amendment_deltas",
                schema: "purchasing",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    amendment_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    purchase_order_line_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    quantity_delta = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_purchase_order_amendment_deltas", x => x.id);
                    table.CheckConstraint("ck_purchasing_order_amendment_delta_decrease", "quantity_delta < 0");
                    table.ForeignKey(
                        name: "FK_purchase_order_amendment_deltas_purchase_order_amendments_a~",
                        columns: x => new { x.amendment_id, x.company_id },
                        principalSchema: "purchasing",
                        principalTable: "purchase_order_amendments",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "purchase_order_lines",
                schema: "purchasing",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    purchase_order_version_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    sequence = table.Column<int>(type: "integer", nullable: false),
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
                    table.PrimaryKey("PK_purchase_order_lines", x => x.id);
                    table.CheckConstraint("ck_purchasing_order_lines_conversion", "conversion_factor_snapshot > 0");
                    table.CheckConstraint("ck_purchasing_order_lines_discount", "line_discount_percent >= 0 AND line_discount_percent <= 100");
                    table.CheckConstraint("ck_purchasing_order_lines_quantity", "quantity > 0");
                    table.CheckConstraint("ck_purchasing_order_lines_sequence", "sequence > 0");
                    table.CheckConstraint("ck_purchasing_order_lines_tax", "tax_percent >= 0 AND tax_percent <= 100");
                    table.CheckConstraint("ck_purchasing_order_lines_unit_price", "unit_price >= 0");
                    table.ForeignKey(
                        name: "FK_purchase_order_lines_products_product_id_company_id",
                        columns: x => new { x.product_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "products",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_purchase_order_lines_purchase_order_versions_purchase_order~",
                        columns: x => new { x.purchase_order_version_id, x.company_id },
                        principalSchema: "purchasing",
                        principalTable: "purchase_order_versions",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_purchase_order_lines_uoms_uom_id_company_id",
                        columns: x => new { x.uom_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "uoms",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_purchase_order_lines_variants_variant_id_company_id",
                        columns: x => new { x.variant_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "variants",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "supplier_invoice_source_links",
                schema: "purchasing",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    supplier_invoice_line_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    source_mode = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    source_document_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    source_line_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    source_version = table.Column<long>(type: "bigint", nullable: true),
                    quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_supplier_invoice_source_links", x => x.id);
                    table.CheckConstraint("ck_purchasing_invoice_source_quantity", "quantity > 0");
                    table.CheckConstraint("ck_purchasing_invoice_source_shape", "(source_mode = 'Direct' AND source_document_public_id IS NULL AND source_line_public_id IS NULL AND source_version IS NULL) OR (source_mode <> 'Direct' AND source_document_public_id IS NOT NULL AND source_line_public_id IS NOT NULL)");
                    table.ForeignKey(
                        name: "FK_supplier_invoice_source_links_supplier_invoice_lines_suppli~",
                        columns: x => new { x.supplier_invoice_line_id, x.company_id },
                        principalSchema: "purchasing",
                        principalTable: "supplier_invoice_lines",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "goods_receipt_inventory_effect_links",
                schema: "purchasing",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    goods_receipt_line_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    inventory_movement_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    original_inventory_movement_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    is_reversal = table.Column<bool>(type: "boolean", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_goods_receipt_inventory_effect_links", x => x.id);
                    table.ForeignKey(
                        name: "FK_goods_receipt_inventory_effect_links_goods_receipt_lines_go~",
                        columns: x => new { x.goods_receipt_line_id, x.company_id },
                        principalSchema: "purchasing",
                        principalTable: "goods_receipt_lines",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipt_inventory_effect_links_goods_receipt_line_id_~",
                schema: "purchasing",
                table: "goods_receipt_inventory_effect_links",
                columns: new[] { "goods_receipt_line_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipt_inventory_effect_links_inventory_movement_pub~",
                schema: "purchasing",
                table: "goods_receipt_inventory_effect_links",
                column: "inventory_movement_public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipt_inventory_effect_links_public_id",
                schema: "purchasing",
                table: "goods_receipt_inventory_effect_links",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipt_lines_company_id_purchase_order_line_public_id",
                schema: "purchasing",
                table: "goods_receipt_lines",
                columns: new[] { "company_id", "purchase_order_line_public_id" });

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipt_lines_goods_receipt_id_company_id",
                schema: "purchasing",
                table: "goods_receipt_lines",
                columns: new[] { "goods_receipt_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipt_lines_goods_receipt_id_sequence",
                schema: "purchasing",
                table: "goods_receipt_lines",
                columns: new[] { "goods_receipt_id", "sequence" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipt_lines_location_id_warehouse_id_company_id",
                schema: "purchasing",
                table: "goods_receipt_lines",
                columns: new[] { "location_id", "warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipt_lines_lot_id_company_id",
                schema: "purchasing",
                table: "goods_receipt_lines",
                columns: new[] { "lot_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipt_lines_product_id_company_id",
                schema: "purchasing",
                table: "goods_receipt_lines",
                columns: new[] { "product_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipt_lines_public_id",
                schema: "purchasing",
                table: "goods_receipt_lines",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipt_lines_serial_id_company_id",
                schema: "purchasing",
                table: "goods_receipt_lines",
                columns: new[] { "serial_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipt_lines_uom_id_company_id",
                schema: "purchasing",
                table: "goods_receipt_lines",
                columns: new[] { "uom_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipt_lines_variant_id_company_id",
                schema: "purchasing",
                table: "goods_receipt_lines",
                columns: new[] { "variant_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipt_lines_warehouse_id_company_id",
                schema: "purchasing",
                table: "goods_receipt_lines",
                columns: new[] { "warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipts_company_id_number",
                schema: "purchasing",
                table: "goods_receipts",
                columns: new[] { "company_id", "number" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipts_public_id",
                schema: "purchasing",
                table: "goods_receipts",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipts_purchase_order_id_company_id",
                schema: "purchasing",
                table: "goods_receipts",
                columns: new[] { "purchase_order_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipts_reversal_of_goods_receipt_id",
                schema: "purchasing",
                table: "goods_receipts",
                column: "reversal_of_goods_receipt_id",
                unique: true,
                filter: "reversal_of_goods_receipt_id IS NOT NULL");

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipts_reversal_of_goods_receipt_id_company_id",
                schema: "purchasing",
                table: "goods_receipts",
                columns: new[] { "reversal_of_goods_receipt_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipts_warehouse_id_company_id",
                schema: "purchasing",
                table: "goods_receipts",
                columns: new[] { "warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_purchase_match_exceptions_public_id",
                schema: "purchasing",
                table: "purchase_match_exceptions",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_purchase_match_exceptions_purchase_match_result_id",
                schema: "purchasing",
                table: "purchase_match_exceptions",
                column: "purchase_match_result_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_purchase_match_exceptions_purchase_match_result_id_company_~",
                schema: "purchasing",
                table: "purchase_match_exceptions",
                columns: new[] { "purchase_match_result_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_purchase_match_results_company_id_supplier_invoice_public_i~",
                schema: "purchasing",
                table: "purchase_match_results",
                columns: new[] { "company_id", "supplier_invoice_public_id", "kind" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_purchase_match_results_public_id",
                schema: "purchasing",
                table: "purchase_match_results",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_purchase_order_amendment_deltas_amendment_id_company_id",
                schema: "purchasing",
                table: "purchase_order_amendment_deltas",
                columns: new[] { "amendment_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_purchase_order_amendment_deltas_amendment_id_purchase_order~",
                schema: "purchasing",
                table: "purchase_order_amendment_deltas",
                columns: new[] { "amendment_id", "purchase_order_line_public_id" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_purchase_order_amendment_deltas_public_id",
                schema: "purchasing",
                table: "purchase_order_amendment_deltas",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_purchase_order_amendments_public_id",
                schema: "purchasing",
                table: "purchase_order_amendments",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_purchase_order_amendments_purchase_order_id_company_id",
                schema: "purchasing",
                table: "purchase_order_amendments",
                columns: new[] { "purchase_order_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_purchase_order_amendments_purchase_order_id_result_version_~",
                schema: "purchasing",
                table: "purchase_order_amendments",
                columns: new[] { "purchase_order_id", "result_version_number" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_purchase_order_lines_product_id_company_id",
                schema: "purchasing",
                table: "purchase_order_lines",
                columns: new[] { "product_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_purchase_order_lines_purchase_order_version_id_company_id",
                schema: "purchasing",
                table: "purchase_order_lines",
                columns: new[] { "purchase_order_version_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_purchase_order_lines_purchase_order_version_id_public_id",
                schema: "purchasing",
                table: "purchase_order_lines",
                columns: new[] { "purchase_order_version_id", "public_id" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_purchase_order_lines_purchase_order_version_id_sequence",
                schema: "purchasing",
                table: "purchase_order_lines",
                columns: new[] { "purchase_order_version_id", "sequence" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_purchase_order_lines_uom_id_company_id",
                schema: "purchasing",
                table: "purchase_order_lines",
                columns: new[] { "uom_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_purchase_order_lines_variant_id_company_id",
                schema: "purchasing",
                table: "purchase_order_lines",
                columns: new[] { "variant_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_purchase_order_versions_public_id",
                schema: "purchasing",
                table: "purchase_order_versions",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_purchase_order_versions_purchase_order_id_company_id",
                schema: "purchasing",
                table: "purchase_order_versions",
                columns: new[] { "purchase_order_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_purchase_order_versions_purchase_order_id_version_number",
                schema: "purchasing",
                table: "purchase_order_versions",
                columns: new[] { "purchase_order_id", "version_number" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_purchase_orders_public_id",
                schema: "purchasing",
                table: "purchase_orders",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_purchase_orders_supplier_party_id_company_id",
                schema: "purchasing",
                table: "purchase_orders",
                columns: new[] { "supplier_party_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_purchasing_orders_company_number",
                schema: "purchasing",
                table: "purchase_orders",
                columns: new[] { "company_id", "number" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_supplier_invoice_lines_product_id_company_id",
                schema: "purchasing",
                table: "supplier_invoice_lines",
                columns: new[] { "product_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_supplier_invoice_lines_public_id",
                schema: "purchasing",
                table: "supplier_invoice_lines",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_supplier_invoice_lines_supplier_invoice_id_company_id",
                schema: "purchasing",
                table: "supplier_invoice_lines",
                columns: new[] { "supplier_invoice_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_supplier_invoice_lines_supplier_invoice_id_sequence",
                schema: "purchasing",
                table: "supplier_invoice_lines",
                columns: new[] { "supplier_invoice_id", "sequence" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_supplier_invoice_lines_uom_id_company_id",
                schema: "purchasing",
                table: "supplier_invoice_lines",
                columns: new[] { "uom_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_supplier_invoice_lines_variant_id_company_id",
                schema: "purchasing",
                table: "supplier_invoice_lines",
                columns: new[] { "variant_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_supplier_invoice_source_links_company_id_source_mode_source~",
                schema: "purchasing",
                table: "supplier_invoice_source_links",
                columns: new[] { "company_id", "source_mode", "source_document_public_id", "source_line_public_id" });

            migrationBuilder.CreateIndex(
                name: "IX_supplier_invoice_source_links_public_id",
                schema: "purchasing",
                table: "supplier_invoice_source_links",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_supplier_invoice_source_links_supplier_invoice_line_id_comp~",
                schema: "purchasing",
                table: "supplier_invoice_source_links",
                columns: new[] { "supplier_invoice_line_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_supplier_invoices_company_id_number",
                schema: "purchasing",
                table: "supplier_invoices",
                columns: new[] { "company_id", "number" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_supplier_invoices_public_id",
                schema: "purchasing",
                table: "supplier_invoices",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_supplier_invoices_supplier_party_id_company_id",
                schema: "purchasing",
                table: "supplier_invoices",
                columns: new[] { "supplier_party_id", "company_id" });
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropTable(
                name: "goods_receipt_inventory_effect_links",
                schema: "purchasing");

            migrationBuilder.DropTable(
                name: "purchase_match_exceptions",
                schema: "purchasing");

            migrationBuilder.DropTable(
                name: "purchase_order_amendment_deltas",
                schema: "purchasing");

            migrationBuilder.DropTable(
                name: "purchase_order_lines",
                schema: "purchasing");

            migrationBuilder.DropTable(
                name: "supplier_invoice_source_links",
                schema: "purchasing");

            migrationBuilder.DropTable(
                name: "goods_receipt_lines",
                schema: "purchasing");

            migrationBuilder.DropTable(
                name: "purchase_match_results",
                schema: "purchasing");

            migrationBuilder.DropTable(
                name: "purchase_order_amendments",
                schema: "purchasing");

            migrationBuilder.DropTable(
                name: "purchase_order_versions",
                schema: "purchasing");

            migrationBuilder.DropTable(
                name: "supplier_invoice_lines",
                schema: "purchasing");

            migrationBuilder.DropTable(
                name: "goods_receipts",
                schema: "purchasing");

            migrationBuilder.DropTable(
                name: "supplier_invoices",
                schema: "purchasing");

            migrationBuilder.DropTable(
                name: "purchase_orders",
                schema: "purchasing");
        }
    }
}
