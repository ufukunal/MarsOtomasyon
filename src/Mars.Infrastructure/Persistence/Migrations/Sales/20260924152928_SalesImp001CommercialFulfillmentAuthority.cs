using System;
using Microsoft.EntityFrameworkCore.Migrations;
using Npgsql.EntityFrameworkCore.PostgreSQL.Metadata;

#nullable disable

namespace Mars.Infrastructure.Persistence.Migrations.Sales
{
    /// <inheritdoc />
    public partial class SalesImp001CommercialFulfillmentAuthority : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.EnsureSchema(
                name: "sales");

            migrationBuilder.CreateTable(
                name: "approval_decisions",
                schema: "foundation",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    module = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: false),
                    entity_type = table.Column<string>(type: "character varying(96)", maxLength: 96, nullable: false),
                    entity_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    snapshot_version = table.Column<long>(type: "bigint", nullable: false),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    decider_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    decision = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    reason = table.Column<string>(type: "text", nullable: true),
                    decided_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_foundation_approval_decisions", x => x.id);
                    table.CheckConstraint("ck_foundation_approval_decisions_decision", "decision IN ('Approved', 'Rejected')");
                    table.CheckConstraint("ck_foundation_approval_decisions_entity_type", "length(btrim(entity_type)) > 0 AND entity_type = btrim(entity_type)");
                    table.CheckConstraint("ck_foundation_approval_decisions_module", "length(btrim(module)) > 0 AND module = btrim(module)");
                    table.CheckConstraint("ck_foundation_approval_decisions_snapshot", "snapshot_version > 0");
                    table.CheckConstraint("ck_foundation_approval_decisions_sod", "decision <> 'Approved' OR creator_actor_id <> decider_actor_id");
                });

            migrationBuilder.CreateTable(
                name: "quotes",
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
                    state = table.Column<string>(type: "character varying(32)", maxLength: 32, nullable: false),
                    current_revision_number = table.Column<long>(type: "bigint", nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    accepted_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true),
                    expired_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true),
                    cancelled_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_quotes", x => x.id);
                    table.UniqueConstraint("ak_sales_quotes_id_company", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_sales_quotes_currency", "currency_code ~ '^[A-Z]{3}$'");
                    table.CheckConstraint("ck_sales_quotes_number", "length(btrim(number)) > 0 AND number = btrim(number)");
                    table.CheckConstraint("ck_sales_quotes_revision", "current_revision_number > 0");
                    table.CheckConstraint("ck_sales_quotes_version", "version > 0");
                    table.ForeignKey(
                        name: "fk_sales_quotes_customer_company",
                        columns: x => new { x.customer_party_id, x.company_id },
                        principalSchema: "parties",
                        principalTable: "parties",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "sales_invoices",
                schema: "sales",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    number = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: false),
                    customer_party_id = table.Column<long>(type: "bigint", nullable: false),
                    state = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    source_mode = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    document_date = table.Column<DateOnly>(type: "date", nullable: false),
                    due_date = table.Column<DateOnly>(type: "date", nullable: false),
                    currency_code = table.Column<string>(type: "character varying(3)", maxLength: 3, nullable: false),
                    document_discount_percent = table.Column<decimal>(type: "numeric(12,6)", precision: 12, scale: 6, nullable: false),
                    customer_code_snapshot = table.Column<string>(type: "text", nullable: false),
                    customer_legal_name_snapshot = table.Column<string>(type: "text", nullable: false),
                    customer_tax_scheme_snapshot = table.Column<string>(type: "text", nullable: true),
                    customer_tax_value_snapshot = table.Column<string>(type: "text", nullable: true),
                    billing_address_snapshot = table.Column<string>(type: "text", nullable: true),
                    shipping_address_snapshot = table.Column<string>(type: "text", nullable: true),
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
                    table.PrimaryKey("PK_sales_invoices", x => x.id);
                    table.UniqueConstraint("ak_sales_invoices_id_company", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_sales_invoices_currency", "currency_code ~ '^[A-Z]{3}$'");
                    table.CheckConstraint("ck_sales_invoices_dates", "due_date >= document_date");
                    table.CheckConstraint("ck_sales_invoices_discount", "document_discount_percent >= 0 AND document_discount_percent <= 100");
                    table.CheckConstraint("ck_sales_invoices_number", "length(btrim(number)) > 0 AND number = btrim(number)");
                    table.CheckConstraint("ck_sales_invoices_totals", "net_total >= 0 AND tax_total >= 0 AND gross_total >= 0 AND gross_total = net_total + tax_total");
                    table.CheckConstraint("ck_sales_invoices_version", "version > 0");
                    table.ForeignKey(
                        name: "fk_sales_invoices_customer_company",
                        columns: x => new { x.customer_party_id, x.company_id },
                        principalSchema: "parties",
                        principalTable: "parties",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "sales_orders",
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
                    state = table.Column<string>(type: "character varying(32)", maxLength: 32, nullable: false),
                    current_version_number = table.Column<long>(type: "bigint", nullable: false),
                    approval_inherited_from_accepted_quote = table.Column<bool>(type: "boolean", nullable: false),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    confirmed_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true),
                    closed_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_sales_orders", x => x.id);
                    table.UniqueConstraint("ak_sales_orders_id_company", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_sales_orders_currency", "currency_code ~ '^[A-Z]{3}$'");
                    table.CheckConstraint("ck_sales_orders_current_version", "current_version_number > 0");
                    table.CheckConstraint("ck_sales_orders_number", "length(btrim(number)) > 0 AND number = btrim(number)");
                    table.CheckConstraint("ck_sales_orders_version", "version > 0");
                    table.ForeignKey(
                        name: "fk_sales_orders_customer_company",
                        columns: x => new { x.customer_party_id, x.company_id },
                        principalSchema: "parties",
                        principalTable: "parties",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "warehouse_access_grants",
                schema: "inventory",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    warehouse_id = table.Column<long>(type: "bigint", nullable: false),
                    granted_by_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    granted_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    revoked_by_actor_id = table.Column<Guid>(type: "uuid", nullable: true),
                    revoked_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_inventory_warehouse_access_grants", x => x.id);
                    table.CheckConstraint("ck_inventory_warehouse_access_grants_revoke_evidence", "(revoked_at IS NULL AND revoked_by_actor_id IS NULL) OR (revoked_at IS NOT NULL AND revoked_by_actor_id IS NOT NULL)");
                    table.ForeignKey(
                        name: "fk_inventory_warehouse_access_grants_warehouse_company",
                        columns: x => new { x.warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "warehouses",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "quote_revisions",
                schema: "sales",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    quote_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    revision_number = table.Column<long>(type: "bigint", nullable: false),
                    document_discount_percent = table.Column<decimal>(type: "numeric(12,6)", precision: 12, scale: 6, nullable: false),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    submitted_for_approval_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true),
                    sent_to_customer_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true),
                    accepted_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_quote_revisions", x => x.id);
                    table.UniqueConstraint("ak_sales_quote_revisions_id_company", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_sales_quote_revisions_discount", "document_discount_percent >= 0 AND document_discount_percent <= 100");
                    table.CheckConstraint("ck_sales_quote_revisions_number", "revision_number > 0");
                    table.ForeignKey(
                        name: "fk_sales_quote_revisions_quote_company",
                        columns: x => new { x.quote_id, x.company_id },
                        principalSchema: "sales",
                        principalTable: "quotes",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "sales_invoice_lines",
                schema: "sales",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    sales_invoice_id = table.Column<long>(type: "bigint", nullable: false),
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
                    table.PrimaryKey("PK_sales_invoice_lines", x => x.id);
                    table.UniqueConstraint("AK_sales_invoice_lines_id_company_id", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_sales_invoice_lines_calculation", "document_discount >= 0 AND taxable_base >= 0 AND tax_amount >= 0 AND line_total = taxable_base + tax_amount");
                    table.CheckConstraint("ck_sales_invoice_lines_conversion", "conversion_factor_snapshot > 0");
                    table.CheckConstraint("ck_sales_invoice_lines_discount", "line_discount_percent >= 0 AND line_discount_percent <= 100");
                    table.CheckConstraint("ck_sales_invoice_lines_quantity", "quantity > 0");
                    table.CheckConstraint("ck_sales_invoice_lines_sequence", "sequence > 0");
                    table.CheckConstraint("ck_sales_invoice_lines_tax", "tax_percent >= 0 AND tax_percent <= 100");
                    table.CheckConstraint("ck_sales_invoice_lines_unit_price", "unit_price >= 0");
                    table.ForeignKey(
                        name: "FK_sales_invoice_lines_products_product_id_company_id",
                        columns: x => new { x.product_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "products",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_sales_invoice_lines_uoms_uom_id_company_id",
                        columns: x => new { x.uom_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "uoms",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_sales_invoice_lines_variants_variant_id_company_id",
                        columns: x => new { x.variant_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "variants",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_sales_invoice_lines_invoice_company",
                        columns: x => new { x.sales_invoice_id, x.company_id },
                        principalSchema: "sales",
                        principalTable: "sales_invoices",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "dispatches",
                schema: "sales",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    number = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: false),
                    sales_order_id = table.Column<long>(type: "bigint", nullable: false),
                    sales_order_version_number = table.Column<long>(type: "bigint", nullable: false),
                    warehouse_id = table.Column<long>(type: "bigint", nullable: false),
                    state = table.Column<string>(type: "character varying(24)", maxLength: 24, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    reversal_of_dispatch_id = table.Column<long>(type: "bigint", nullable: true),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    ready_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true),
                    posted_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true),
                    handed_over_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true),
                    delivered_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true),
                    cancelled_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_dispatches", x => x.id);
                    table.UniqueConstraint("ak_sales_dispatches_id_company", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_sales_dispatches_not_self_reversal", "reversal_of_dispatch_id IS NULL OR reversal_of_dispatch_id <> id");
                    table.CheckConstraint("ck_sales_dispatches_number", "length(btrim(number)) > 0 AND number = btrim(number)");
                    table.CheckConstraint("ck_sales_dispatches_order_version", "sales_order_version_number > 0");
                    table.CheckConstraint("ck_sales_dispatches_version", "version > 0");
                    table.ForeignKey(
                        name: "fk_sales_dispatches_order_company",
                        columns: x => new { x.sales_order_id, x.company_id },
                        principalSchema: "sales",
                        principalTable: "sales_orders",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_sales_dispatches_reversal_company",
                        columns: x => new { x.reversal_of_dispatch_id, x.company_id },
                        principalSchema: "sales",
                        principalTable: "dispatches",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_sales_dispatches_warehouse_company",
                        columns: x => new { x.warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "warehouses",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "sales_order_amendments",
                schema: "sales",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    sales_order_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    base_version_number = table.Column<long>(type: "bigint", nullable: false),
                    state = table.Column<string>(type: "character varying(24)", maxLength: 24, nullable: false),
                    requires_approval = table.Column<bool>(type: "boolean", nullable: false),
                    new_payment_terms = table.Column<string>(type: "text", nullable: true),
                    reason = table.Column<string>(type: "text", nullable: false),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    activated_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_sales_order_amendments", x => x.id);
                    table.UniqueConstraint("ak_sales_order_amendments_id_company", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_sales_order_amendments_base_version", "base_version_number > 0");
                    table.CheckConstraint("ck_sales_order_amendments_reason", "length(btrim(reason)) > 0 AND reason = btrim(reason)");
                    table.ForeignKey(
                        name: "fk_sales_order_amendments_order_company",
                        columns: x => new { x.sales_order_id, x.company_id },
                        principalSchema: "sales",
                        principalTable: "sales_orders",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "sales_order_versions",
                schema: "sales",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    sales_order_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    version_number = table.Column<long>(type: "bigint", nullable: false),
                    source_quote_revision_id = table.Column<long>(type: "bigint", nullable: true),
                    source_amendment_id = table.Column<long>(type: "bigint", nullable: true),
                    document_discount_percent = table.Column<decimal>(type: "numeric(12,6)", precision: 12, scale: 6, nullable: false),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_sales_order_versions", x => x.id);
                    table.UniqueConstraint("ak_sales_order_versions_id_company", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_sales_order_versions_discount", "document_discount_percent >= 0 AND document_discount_percent <= 100");
                    table.CheckConstraint("ck_sales_order_versions_number", "version_number > 0");
                    table.ForeignKey(
                        name: "fk_sales_order_versions_order_company",
                        columns: x => new { x.sales_order_id, x.company_id },
                        principalSchema: "sales",
                        principalTable: "sales_orders",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "quote_conversion_links",
                schema: "sales",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    quote_revision_id = table.Column<long>(type: "bigint", nullable: false),
                    quote_line_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    sales_order_id = table.Column<long>(type: "bigint", nullable: false),
                    sales_order_version_number = table.Column<long>(type: "bigint", nullable: false),
                    sales_order_line_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_quote_conversion_links", x => x.id);
                    table.CheckConstraint("ck_sales_quote_conversion_quantity", "quantity > 0");
                    table.ForeignKey(
                        name: "fk_sales_quote_conversion_revision_company",
                        columns: x => new { x.quote_revision_id, x.company_id },
                        principalSchema: "sales",
                        principalTable: "quote_revisions",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "quote_lines",
                schema: "sales",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    revision_id = table.Column<long>(type: "bigint", nullable: false),
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
                    table.PrimaryKey("PK_quote_lines", x => x.id);
                    table.CheckConstraint("ck_sales_quote_lines_conversion", "conversion_factor_snapshot > 0");
                    table.CheckConstraint("ck_sales_quote_lines_discount", "line_discount_percent >= 0 AND line_discount_percent <= 100");
                    table.CheckConstraint("ck_sales_quote_lines_quantity", "quantity > 0");
                    table.CheckConstraint("ck_sales_quote_lines_sequence", "sequence > 0");
                    table.CheckConstraint("ck_sales_quote_lines_tax", "tax_percent >= 0 AND tax_percent <= 100");
                    table.CheckConstraint("ck_sales_quote_lines_unit_price", "unit_price >= 0");
                    table.ForeignKey(
                        name: "FK_quote_lines_products_product_id_company_id",
                        columns: x => new { x.product_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "products",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_quote_lines_uoms_uom_id_company_id",
                        columns: x => new { x.uom_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "uoms",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_quote_lines_variants_variant_id_company_id",
                        columns: x => new { x.variant_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "variants",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_sales_quote_lines_revision_company",
                        columns: x => new { x.revision_id, x.company_id },
                        principalSchema: "sales",
                        principalTable: "quote_revisions",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "sales_invoice_source_links",
                schema: "sales",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    sales_invoice_line_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    source_mode = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    source_document_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    source_line_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    source_version = table.Column<long>(type: "bigint", nullable: true),
                    quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_sales_invoice_source_links", x => x.id);
                    table.CheckConstraint("ck_sales_invoice_source_links_quantity", "quantity > 0");
                    table.CheckConstraint("ck_sales_invoice_source_links_shape", "(source_mode = 'Direct' AND source_document_public_id IS NULL AND source_line_public_id IS NULL AND source_version IS NULL) OR (source_mode <> 'Direct' AND source_document_public_id IS NOT NULL AND source_line_public_id IS NOT NULL)");
                    table.ForeignKey(
                        name: "fk_sales_invoice_source_links_line_company",
                        columns: x => new { x.sales_invoice_line_id, x.company_id },
                        principalSchema: "sales",
                        principalTable: "sales_invoice_lines",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "dispatch_lines",
                schema: "sales",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    dispatch_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    sequence = table.Column<int>(type: "integer", nullable: false),
                    sales_order_line_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    product_id = table.Column<long>(type: "bigint", nullable: false),
                    variant_id = table.Column<long>(type: "bigint", nullable: true),
                    uom_id = table.Column<long>(type: "bigint", nullable: false),
                    conversion_factor_snapshot = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    warehouse_id = table.Column<long>(type: "bigint", nullable: false),
                    location_id = table.Column<long>(type: "bigint", nullable: true),
                    lot_id = table.Column<long>(type: "bigint", nullable: true),
                    serial_id = table.Column<long>(type: "bigint", nullable: true),
                    reservation_public_id = table.Column<Guid>(type: "uuid", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_dispatch_lines", x => x.id);
                    table.UniqueConstraint("AK_dispatch_lines_id_company_id", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_sales_dispatch_lines_conversion", "conversion_factor_snapshot > 0");
                    table.CheckConstraint("ck_sales_dispatch_lines_quantity", "quantity > 0");
                    table.CheckConstraint("ck_sales_dispatch_lines_sequence", "sequence > 0");
                    table.ForeignKey(
                        name: "fk_sales_dispatch_lines_dispatch_company",
                        columns: x => new { x.dispatch_id, x.company_id },
                        principalSchema: "sales",
                        principalTable: "dispatches",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_sales_dispatch_lines_location_warehouse_company",
                        columns: x => new { x.location_id, x.warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "locations",
                        principalColumns: new[] { "id", "warehouse_id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_sales_dispatch_lines_lot_company",
                        columns: x => new { x.lot_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "lots",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_sales_dispatch_lines_product_company",
                        columns: x => new { x.product_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "products",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_sales_dispatch_lines_serial_company",
                        columns: x => new { x.serial_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "serials",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_sales_dispatch_lines_uom_company",
                        columns: x => new { x.uom_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "uoms",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_sales_dispatch_lines_variant_company",
                        columns: x => new { x.variant_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "variants",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_sales_dispatch_lines_warehouse_company",
                        columns: x => new { x.warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "warehouses",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "sales_order_amendment_deltas",
                schema: "sales",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    amendment_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    sales_order_line_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    quantity_delta = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    new_unit_price = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: true),
                    new_line_discount_percent = table.Column<decimal>(type: "numeric(12,6)", precision: 12, scale: 6, nullable: true),
                    new_tax_percent = table.Column<decimal>(type: "numeric(12,6)", precision: 12, scale: 6, nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_sales_order_amendment_deltas", x => x.id);
                    table.CheckConstraint("ck_sales_order_amendment_deltas_discount", "new_line_discount_percent IS NULL OR (new_line_discount_percent >= 0 AND new_line_discount_percent <= 100)");
                    table.CheckConstraint("ck_sales_order_amendment_deltas_not_empty", "quantity_delta <> 0 OR new_unit_price IS NOT NULL OR new_line_discount_percent IS NOT NULL OR new_tax_percent IS NOT NULL");
                    table.CheckConstraint("ck_sales_order_amendment_deltas_price", "new_unit_price IS NULL OR new_unit_price >= 0");
                    table.CheckConstraint("ck_sales_order_amendment_deltas_tax", "new_tax_percent IS NULL OR (new_tax_percent >= 0 AND new_tax_percent <= 100)");
                    table.ForeignKey(
                        name: "fk_sales_order_amendment_deltas_amendment_company",
                        columns: x => new { x.amendment_id, x.company_id },
                        principalSchema: "sales",
                        principalTable: "sales_order_amendments",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "sales_order_lines",
                schema: "sales",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    sales_order_version_id = table.Column<long>(type: "bigint", nullable: false),
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
                    table.PrimaryKey("PK_sales_order_lines", x => x.id);
                    table.CheckConstraint("ck_sales_order_lines_conversion", "conversion_factor_snapshot > 0");
                    table.CheckConstraint("ck_sales_order_lines_discount", "line_discount_percent >= 0 AND line_discount_percent <= 100");
                    table.CheckConstraint("ck_sales_order_lines_quantity", "quantity > 0");
                    table.CheckConstraint("ck_sales_order_lines_sequence", "sequence > 0");
                    table.CheckConstraint("ck_sales_order_lines_tax", "tax_percent >= 0 AND tax_percent <= 100");
                    table.CheckConstraint("ck_sales_order_lines_unit_price", "unit_price >= 0");
                    table.ForeignKey(
                        name: "FK_sales_order_lines_products_product_id_company_id",
                        columns: x => new { x.product_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "products",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_sales_order_lines_uoms_uom_id_company_id",
                        columns: x => new { x.uom_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "uoms",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_sales_order_lines_variants_variant_id_company_id",
                        columns: x => new { x.variant_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "variants",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_sales_order_lines_version_company",
                        columns: x => new { x.sales_order_version_id, x.company_id },
                        principalSchema: "sales",
                        principalTable: "sales_order_versions",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "dispatch_inventory_effect_links",
                schema: "sales",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    dispatch_line_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    inventory_movement_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    original_inventory_movement_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    is_reversal = table.Column<bool>(type: "boolean", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_dispatch_inventory_effect_links", x => x.id);
                    table.ForeignKey(
                        name: "fk_sales_dispatch_effect_line_company",
                        columns: x => new { x.dispatch_line_id, x.company_id },
                        principalSchema: "sales",
                        principalTable: "dispatch_lines",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateIndex(
                name: "ix_foundation_approval_decisions_snapshot",
                schema: "foundation",
                table: "approval_decisions",
                columns: new[] { "company_id", "module", "entity_type", "entity_public_id", "snapshot_version" });

            migrationBuilder.CreateIndex(
                name: "ux_foundation_approval_decisions_approved_snapshot",
                schema: "foundation",
                table: "approval_decisions",
                columns: new[] { "company_id", "module", "entity_type", "entity_public_id", "snapshot_version", "decision" },
                unique: true,
                filter: "\"decision\" = 'Approved'");

            migrationBuilder.CreateIndex(
                name: "ux_foundation_approval_decisions_public_id",
                schema: "foundation",
                table: "approval_decisions",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_inventory_effect_links_dispatch_line_id_company_id",
                schema: "sales",
                table: "dispatch_inventory_effect_links",
                columns: new[] { "dispatch_line_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_inventory_effect_links_public_id",
                schema: "sales",
                table: "dispatch_inventory_effect_links",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ix_sales_dispatch_effect_line",
                schema: "sales",
                table: "dispatch_inventory_effect_links",
                columns: new[] { "dispatch_line_id", "is_reversal" });

            migrationBuilder.CreateIndex(
                name: "ux_sales_dispatch_effect_inventory_movement",
                schema: "sales",
                table: "dispatch_inventory_effect_links",
                column: "inventory_movement_public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_lines_dispatch_id_company_id",
                schema: "sales",
                table: "dispatch_lines",
                columns: new[] { "dispatch_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_lines_location_id_warehouse_id_company_id",
                schema: "sales",
                table: "dispatch_lines",
                columns: new[] { "location_id", "warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_lines_lot_id_company_id",
                schema: "sales",
                table: "dispatch_lines",
                columns: new[] { "lot_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_lines_product_id_company_id",
                schema: "sales",
                table: "dispatch_lines",
                columns: new[] { "product_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_lines_public_id",
                schema: "sales",
                table: "dispatch_lines",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_lines_serial_id_company_id",
                schema: "sales",
                table: "dispatch_lines",
                columns: new[] { "serial_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_lines_uom_id_company_id",
                schema: "sales",
                table: "dispatch_lines",
                columns: new[] { "uom_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_lines_variant_id_company_id",
                schema: "sales",
                table: "dispatch_lines",
                columns: new[] { "variant_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_lines_warehouse_id_company_id",
                schema: "sales",
                table: "dispatch_lines",
                columns: new[] { "warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ix_sales_dispatch_lines_order_line",
                schema: "sales",
                table: "dispatch_lines",
                columns: new[] { "company_id", "sales_order_line_public_id" });

            migrationBuilder.CreateIndex(
                name: "ux_sales_dispatch_lines_dispatch_sequence",
                schema: "sales",
                table: "dispatch_lines",
                columns: new[] { "dispatch_id", "sequence" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_dispatches_public_id",
                schema: "sales",
                table: "dispatches",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_dispatches_reversal_of_dispatch_id_company_id",
                schema: "sales",
                table: "dispatches",
                columns: new[] { "reversal_of_dispatch_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_dispatches_sales_order_id_company_id",
                schema: "sales",
                table: "dispatches",
                columns: new[] { "sales_order_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_dispatches_warehouse_id_company_id",
                schema: "sales",
                table: "dispatches",
                columns: new[] { "warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ix_sales_dispatches_work_queue",
                schema: "sales",
                table: "dispatches",
                columns: new[] { "company_id", "state", "created_at" });

            migrationBuilder.CreateIndex(
                name: "ux_sales_dispatches_company_number",
                schema: "sales",
                table: "dispatches",
                columns: new[] { "company_id", "number" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_sales_dispatches_single_reversal",
                schema: "sales",
                table: "dispatches",
                column: "reversal_of_dispatch_id",
                unique: true,
                filter: "reversal_of_dispatch_id IS NOT NULL");

            migrationBuilder.CreateIndex(
                name: "IX_quote_conversion_links_public_id",
                schema: "sales",
                table: "quote_conversion_links",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_quote_conversion_links_quote_revision_id_company_id",
                schema: "sales",
                table: "quote_conversion_links",
                columns: new[] { "quote_revision_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ix_sales_quote_conversion_source",
                schema: "sales",
                table: "quote_conversion_links",
                columns: new[] { "company_id", "quote_revision_id", "quote_line_public_id" });

            migrationBuilder.CreateIndex(
                name: "ix_sales_quote_conversion_target",
                schema: "sales",
                table: "quote_conversion_links",
                columns: new[] { "company_id", "sales_order_id", "sales_order_line_public_id" });

            migrationBuilder.CreateIndex(
                name: "IX_quote_lines_product_id_company_id",
                schema: "sales",
                table: "quote_lines",
                columns: new[] { "product_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_quote_lines_public_id",
                schema: "sales",
                table: "quote_lines",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_quote_lines_revision_id_company_id",
                schema: "sales",
                table: "quote_lines",
                columns: new[] { "revision_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_quote_lines_uom_id_company_id",
                schema: "sales",
                table: "quote_lines",
                columns: new[] { "uom_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_quote_lines_variant_id_company_id",
                schema: "sales",
                table: "quote_lines",
                columns: new[] { "variant_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_sales_quote_lines_revision_sequence",
                schema: "sales",
                table: "quote_lines",
                columns: new[] { "revision_id", "sequence" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_quote_revisions_public_id",
                schema: "sales",
                table: "quote_revisions",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_quote_revisions_quote_id_company_id",
                schema: "sales",
                table: "quote_revisions",
                columns: new[] { "quote_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_sales_quote_revisions_number",
                schema: "sales",
                table: "quote_revisions",
                columns: new[] { "quote_id", "revision_number" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_quotes_customer_party_id_company_id",
                schema: "sales",
                table: "quotes",
                columns: new[] { "customer_party_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_quotes_public_id",
                schema: "sales",
                table: "quotes",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ix_sales_quotes_work_queue",
                schema: "sales",
                table: "quotes",
                columns: new[] { "company_id", "state", "created_at" });

            migrationBuilder.CreateIndex(
                name: "ux_sales_quotes_company_number",
                schema: "sales",
                table: "quotes",
                columns: new[] { "company_id", "number" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_sales_invoice_lines_product_id_company_id",
                schema: "sales",
                table: "sales_invoice_lines",
                columns: new[] { "product_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_sales_invoice_lines_public_id",
                schema: "sales",
                table: "sales_invoice_lines",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_sales_invoice_lines_sales_invoice_id_company_id",
                schema: "sales",
                table: "sales_invoice_lines",
                columns: new[] { "sales_invoice_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_sales_invoice_lines_uom_id_company_id",
                schema: "sales",
                table: "sales_invoice_lines",
                columns: new[] { "uom_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_sales_invoice_lines_variant_id_company_id",
                schema: "sales",
                table: "sales_invoice_lines",
                columns: new[] { "variant_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_sales_invoice_lines_invoice_sequence",
                schema: "sales",
                table: "sales_invoice_lines",
                columns: new[] { "sales_invoice_id", "sequence" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_sales_invoice_source_links_public_id",
                schema: "sales",
                table: "sales_invoice_source_links",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_sales_invoice_source_links_sales_invoice_line_id_company_id",
                schema: "sales",
                table: "sales_invoice_source_links",
                columns: new[] { "sales_invoice_line_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ix_sales_invoice_source_links_source",
                schema: "sales",
                table: "sales_invoice_source_links",
                columns: new[] { "company_id", "source_mode", "source_document_public_id", "source_line_public_id" });

            migrationBuilder.CreateIndex(
                name: "IX_sales_invoices_customer_party_id_company_id",
                schema: "sales",
                table: "sales_invoices",
                columns: new[] { "customer_party_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_sales_invoices_public_id",
                schema: "sales",
                table: "sales_invoices",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ix_sales_invoices_work_queue",
                schema: "sales",
                table: "sales_invoices",
                columns: new[] { "company_id", "state", "document_date" });

            migrationBuilder.CreateIndex(
                name: "ux_sales_invoices_company_number",
                schema: "sales",
                table: "sales_invoices",
                columns: new[] { "company_id", "number" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_sales_order_amendment_deltas_amendment_id_company_id",
                schema: "sales",
                table: "sales_order_amendment_deltas",
                columns: new[] { "amendment_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_sales_order_amendment_deltas_public_id",
                schema: "sales",
                table: "sales_order_amendment_deltas",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_sales_order_amendment_deltas_line",
                schema: "sales",
                table: "sales_order_amendment_deltas",
                columns: new[] { "amendment_id", "sales_order_line_public_id" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ix_sales_order_amendments_order_state",
                schema: "sales",
                table: "sales_order_amendments",
                columns: new[] { "sales_order_id", "state" });

            migrationBuilder.CreateIndex(
                name: "IX_sales_order_amendments_public_id",
                schema: "sales",
                table: "sales_order_amendments",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_sales_order_amendments_sales_order_id_company_id",
                schema: "sales",
                table: "sales_order_amendments",
                columns: new[] { "sales_order_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ix_sales_order_lines_logical_line",
                schema: "sales",
                table: "sales_order_lines",
                columns: new[] { "company_id", "public_id" });

            migrationBuilder.CreateIndex(
                name: "IX_sales_order_lines_product_id_company_id",
                schema: "sales",
                table: "sales_order_lines",
                columns: new[] { "product_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_sales_order_lines_sales_order_version_id_company_id",
                schema: "sales",
                table: "sales_order_lines",
                columns: new[] { "sales_order_version_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_sales_order_lines_uom_id_company_id",
                schema: "sales",
                table: "sales_order_lines",
                columns: new[] { "uom_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_sales_order_lines_variant_id_company_id",
                schema: "sales",
                table: "sales_order_lines",
                columns: new[] { "variant_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_sales_order_lines_version_public_id",
                schema: "sales",
                table: "sales_order_lines",
                columns: new[] { "sales_order_version_id", "public_id" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_sales_order_lines_version_sequence",
                schema: "sales",
                table: "sales_order_lines",
                columns: new[] { "sales_order_version_id", "sequence" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_sales_order_versions_public_id",
                schema: "sales",
                table: "sales_order_versions",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_sales_order_versions_sales_order_id_company_id",
                schema: "sales",
                table: "sales_order_versions",
                columns: new[] { "sales_order_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_sales_order_versions_number",
                schema: "sales",
                table: "sales_order_versions",
                columns: new[] { "sales_order_id", "version_number" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_sales_orders_customer_party_id_company_id",
                schema: "sales",
                table: "sales_orders",
                columns: new[] { "customer_party_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_sales_orders_public_id",
                schema: "sales",
                table: "sales_orders",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ix_sales_orders_work_queue",
                schema: "sales",
                table: "sales_orders",
                columns: new[] { "company_id", "state", "created_at" });

            migrationBuilder.CreateIndex(
                name: "ux_sales_orders_company_number",
                schema: "sales",
                table: "sales_orders",
                columns: new[] { "company_id", "number" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ix_inventory_warehouse_access_grants_scope",
                schema: "inventory",
                table: "warehouse_access_grants",
                columns: new[] { "company_id", "warehouse_id", "actor_id" });

            migrationBuilder.CreateIndex(
                name: "IX_warehouse_access_grants_warehouse_id_company_id",
                schema: "inventory",
                table: "warehouse_access_grants",
                columns: new[] { "warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_inventory_warehouse_access_grants_active_scope",
                schema: "inventory",
                table: "warehouse_access_grants",
                columns: new[] { "actor_id", "company_id", "warehouse_id" },
                unique: true,
                filter: "revoked_at IS NULL");

            migrationBuilder.CreateIndex(
                name: "ux_inventory_warehouse_access_grants_public_id",
                schema: "inventory",
                table: "warehouse_access_grants",
                column: "public_id",
                unique: true);
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropTable(
                name: "approval_decisions",
                schema: "foundation");

            migrationBuilder.DropTable(
                name: "dispatch_inventory_effect_links",
                schema: "sales");

            migrationBuilder.DropTable(
                name: "quote_conversion_links",
                schema: "sales");

            migrationBuilder.DropTable(
                name: "quote_lines",
                schema: "sales");

            migrationBuilder.DropTable(
                name: "sales_invoice_source_links",
                schema: "sales");

            migrationBuilder.DropTable(
                name: "sales_order_amendment_deltas",
                schema: "sales");

            migrationBuilder.DropTable(
                name: "sales_order_lines",
                schema: "sales");

            migrationBuilder.DropTable(
                name: "warehouse_access_grants",
                schema: "inventory");

            migrationBuilder.DropTable(
                name: "dispatch_lines",
                schema: "sales");

            migrationBuilder.DropTable(
                name: "quote_revisions",
                schema: "sales");

            migrationBuilder.DropTable(
                name: "sales_invoice_lines",
                schema: "sales");

            migrationBuilder.DropTable(
                name: "sales_order_amendments",
                schema: "sales");

            migrationBuilder.DropTable(
                name: "sales_order_versions",
                schema: "sales");

            migrationBuilder.DropTable(
                name: "dispatches",
                schema: "sales");

            migrationBuilder.DropTable(
                name: "quotes",
                schema: "sales");

            migrationBuilder.DropTable(
                name: "sales_invoices",
                schema: "sales");

            migrationBuilder.DropTable(
                name: "sales_orders",
                schema: "sales");
        }
    }
}
