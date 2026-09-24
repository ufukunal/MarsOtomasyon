using System;
using Microsoft.EntityFrameworkCore.Migrations;
using Npgsql.EntityFrameworkCore.PostgreSQL.Metadata;

#nullable disable

namespace Mars.Infrastructure.Persistence.Migrations.Finance
{
    /// <inheritdoc />
    public partial class FinanceImp001LedgerSettlementValuationAuthority : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.EnsureSchema(
                name: "finance");

            migrationBuilder.CreateTable(
                name: "bank_accounts",
                schema: "finance",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    branch_id = table.Column<Guid>(type: "uuid", nullable: true),
                    code = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: false),
                    name = table.Column<string>(type: "character varying(200)", maxLength: 200, nullable: false),
                    currency_code = table.Column<string>(type: "character varying(3)", maxLength: 3, nullable: false),
                    iban = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: true),
                    state = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_bank_accounts", x => x.id);
                    table.UniqueConstraint("ak_finance_bank_accounts_id_company", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_finance_bank_accounts_code", "length(btrim(code)) > 0 AND code = btrim(code)");
                    table.CheckConstraint("ck_finance_bank_accounts_name", "length(btrim(name)) > 0 AND name = btrim(name)");
                    table.CheckConstraint("ck_finance_bank_accounts_version", "version > 0");
                });

            migrationBuilder.CreateTable(
                name: "cash_accounts",
                schema: "finance",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    branch_id = table.Column<Guid>(type: "uuid", nullable: true),
                    code = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: false),
                    name = table.Column<string>(type: "character varying(200)", maxLength: 200, nullable: false),
                    currency_code = table.Column<string>(type: "character varying(3)", maxLength: 3, nullable: false),
                    state = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_cash_accounts", x => x.id);
                    table.UniqueConstraint("ak_finance_cash_accounts_id_company", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_finance_cash_accounts_code", "length(btrim(code)) > 0 AND code = btrim(code)");
                    table.CheckConstraint("ck_finance_cash_accounts_name", "length(btrim(name)) > 0 AND name = btrim(name)");
                    table.CheckConstraint("ck_finance_cash_accounts_version", "version > 0");
                });

            migrationBuilder.CreateTable(
                name: "customer_risk_controls",
                schema: "finance",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    party_id = table.Column<long>(type: "bigint", nullable: false),
                    credit_limit = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: true),
                    manual_hold = table.Column<bool>(type: "boolean", nullable: false),
                    hold_reason = table.Column<string>(type: "character varying(1024)", maxLength: 1024, nullable: true),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    updated_by_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    updated_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_customer_risk_controls", x => x.id);
                    table.CheckConstraint("ck_finance_risk_limit", "credit_limit IS NULL OR credit_limit >= 0");
                    table.ForeignKey(
                        name: "FK_customer_risk_controls_parties_party_id_company_id",
                        columns: x => new { x.party_id, x.company_id },
                        principalSchema: "parties",
                        principalTable: "parties",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "inventory_valuation_pools",
                schema: "finance",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    product_id = table.Column<long>(type: "bigint", nullable: false),
                    variant_id = table.Column<long>(type: "bigint", nullable: true),
                    base_uom_id = table.Column<long>(type: "bigint", nullable: false),
                    currency_code = table.Column<string>(type: "character varying(3)", maxLength: 3, nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_inventory_valuation_pools", x => x.id);
                    table.UniqueConstraint("ak_finance_valuation_pools_id_company", x => new { x.id, x.company_id });
                    table.ForeignKey(
                        name: "FK_inventory_valuation_pools_products_product_id_company_id",
                        columns: x => new { x.product_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "products",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_inventory_valuation_pools_uoms_base_uom_id_company_id",
                        columns: x => new { x.base_uom_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "uoms",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_inventory_valuation_pools_variants_variant_id_company_id",
                        columns: x => new { x.variant_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "variants",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "posting_periods",
                schema: "finance",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    start_date = table.Column<DateOnly>(type: "date", nullable: false),
                    end_date = table.Column<DateOnly>(type: "date", nullable: false),
                    state = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    changed_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_posting_periods", x => x.id);
                    table.CheckConstraint("ck_finance_period_range", "end_date >= start_date");
                });

            migrationBuilder.CreateTable(
                name: "transactions",
                schema: "finance",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    kind = table.Column<string>(type: "character varying(40)", maxLength: 40, nullable: false),
                    state = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    currency_code = table.Column<string>(type: "character varying(3)", maxLength: 3, nullable: false),
                    base_currency_code = table.Column<string>(type: "character varying(3)", maxLength: 3, nullable: false),
                    amount = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    base_amount = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    party_id = table.Column<long>(type: "bigint", nullable: true),
                    party_role = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: true),
                    document_date = table.Column<DateOnly>(type: "date", nullable: false),
                    posting_date = table.Column<DateOnly>(type: "date", nullable: false),
                    source_module = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: true),
                    source_entity_type = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: true),
                    source_document_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    reversal_of_transaction_id = table.Column<long>(type: "bigint", nullable: true),
                    reason = table.Column<string>(type: "character varying(1024)", maxLength: 1024, nullable: true),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    posted_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    reversed_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true),
                    version = table.Column<long>(type: "bigint", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_transactions", x => x.id);
                    table.UniqueConstraint("ak_finance_transactions_id_company", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_finance_transactions_amount", "amount >= 0 AND base_amount >= 0");
                    table.CheckConstraint("ck_finance_transactions_currency", "currency_code ~ '^[A-Z]{3}$' AND base_currency_code ~ '^[A-Z]{3}$'");
                    table.CheckConstraint("ck_finance_transactions_version", "version > 0");
                    table.ForeignKey(
                        name: "FK_transactions_parties_party_id_company_id",
                        columns: x => new { x.party_id, x.company_id },
                        principalSchema: "parties",
                        principalTable: "parties",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_transactions_transactions_reversal_of_transaction_id_compan~",
                        columns: x => new { x.reversal_of_transaction_id, x.company_id },
                        principalSchema: "finance",
                        principalTable: "transactions",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "statement_import_batches",
                schema: "finance",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    bank_account_id = table.Column<long>(type: "bigint", nullable: false),
                    source_name = table.Column<string>(type: "character varying(200)", maxLength: 200, nullable: false),
                    source_reference = table.Column<string>(type: "character varying(256)", maxLength: 256, nullable: true),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    imported_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_statement_import_batches", x => x.id);
                    table.UniqueConstraint("ak_finance_statement_batches_id_company", x => new { x.id, x.company_id });
                    table.ForeignKey(
                        name: "FK_statement_import_batches_bank_accounts_bank_account_id_comp~",
                        columns: x => new { x.bank_account_id, x.company_id },
                        principalSchema: "finance",
                        principalTable: "bank_accounts",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "account_ledger_entries",
                schema: "finance",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    transaction_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    party_id = table.Column<long>(type: "bigint", nullable: false),
                    party_role = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    direction = table.Column<string>(type: "character varying(8)", maxLength: 8, nullable: false),
                    currency_code = table.Column<string>(type: "character varying(3)", maxLength: 3, nullable: false),
                    amount = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    base_amount = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    due_date = table.Column<DateOnly>(type: "date", nullable: true),
                    posted_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_account_ledger_entries", x => x.id);
                    table.CheckConstraint("ck_finance_account_ledger_amount", "amount > 0 AND base_amount > 0");
                    table.ForeignKey(
                        name: "FK_account_ledger_entries_parties_party_id_company_id",
                        columns: x => new { x.party_id, x.company_id },
                        principalSchema: "parties",
                        principalTable: "parties",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_account_ledger_entries_transactions_transaction_id_company_~",
                        columns: x => new { x.transaction_id, x.company_id },
                        principalSchema: "finance",
                        principalTable: "transactions",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "bank_ledger_entries",
                schema: "finance",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    transaction_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    bank_account_id = table.Column<long>(type: "bigint", nullable: false),
                    direction = table.Column<string>(type: "character varying(8)", maxLength: 8, nullable: false),
                    currency_code = table.Column<string>(type: "character varying(3)", maxLength: 3, nullable: false),
                    amount = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    base_amount = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    posted_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_bank_ledger_entries", x => x.id);
                    table.UniqueConstraint("ak_finance_bank_ledger_id_company", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_finance_bank_ledger_amount", "amount > 0 AND base_amount > 0");
                    table.ForeignKey(
                        name: "FK_bank_ledger_entries_bank_accounts_bank_account_id_company_id",
                        columns: x => new { x.bank_account_id, x.company_id },
                        principalSchema: "finance",
                        principalTable: "bank_accounts",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_bank_ledger_entries_transactions_transaction_id_company_id",
                        columns: x => new { x.transaction_id, x.company_id },
                        principalSchema: "finance",
                        principalTable: "transactions",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "cash_counts",
                schema: "finance",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    cash_account_id = table.Column<long>(type: "bigint", nullable: false),
                    book_balance_snapshot = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    counted_balance = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    difference = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    counter_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    approval_decision_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    adjustment_transaction_id = table.Column<long>(type: "bigint", nullable: true),
                    counted_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_cash_counts", x => x.id);
                    table.ForeignKey(
                        name: "FK_cash_counts_cash_accounts_cash_account_id_company_id",
                        columns: x => new { x.cash_account_id, x.company_id },
                        principalSchema: "finance",
                        principalTable: "cash_accounts",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_cash_counts_transactions_adjustment_transaction_id_company_~",
                        columns: x => new { x.adjustment_transaction_id, x.company_id },
                        principalSchema: "finance",
                        principalTable: "transactions",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "cash_ledger_entries",
                schema: "finance",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    transaction_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    cash_account_id = table.Column<long>(type: "bigint", nullable: false),
                    direction = table.Column<string>(type: "character varying(8)", maxLength: 8, nullable: false),
                    currency_code = table.Column<string>(type: "character varying(3)", maxLength: 3, nullable: false),
                    amount = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    base_amount = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    posted_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_cash_ledger_entries", x => x.id);
                    table.CheckConstraint("ck_finance_cash_ledger_amount", "amount > 0 AND base_amount > 0");
                    table.ForeignKey(
                        name: "FK_cash_ledger_entries_cash_accounts_cash_account_id_company_id",
                        columns: x => new { x.cash_account_id, x.company_id },
                        principalSchema: "finance",
                        principalTable: "cash_accounts",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_cash_ledger_entries_transactions_transaction_id_company_id",
                        columns: x => new { x.transaction_id, x.company_id },
                        principalSchema: "finance",
                        principalTable: "transactions",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "dispatch_cost_bridge_entries",
                schema: "finance",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    transaction_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    pool_id = table.Column<long>(type: "bigint", nullable: false),
                    dispatch_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    dispatch_line_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    physical_source_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    inventory_movement_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    base_quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    base_value = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    reversal_of_bridge_entry_id = table.Column<long>(type: "bigint", nullable: true),
                    posted_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_dispatch_cost_bridge_entries", x => x.id);
                    table.UniqueConstraint("AK_dispatch_cost_bridge_entries_id_company_id", x => new { x.id, x.company_id });
                    table.ForeignKey(
                        name: "FK_dispatch_cost_bridge_entries_dispatch_cost_bridge_entries_r~",
                        columns: x => new { x.reversal_of_bridge_entry_id, x.company_id },
                        principalSchema: "finance",
                        principalTable: "dispatch_cost_bridge_entries",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_dispatch_cost_bridge_entries_inventory_valuation_pools_pool~",
                        columns: x => new { x.pool_id, x.company_id },
                        principalSchema: "finance",
                        principalTable: "inventory_valuation_pools",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_dispatch_cost_bridge_entries_transactions_transaction_id_co~",
                        columns: x => new { x.transaction_id, x.company_id },
                        principalSchema: "finance",
                        principalTable: "transactions",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "inventory_valuation_entries",
                schema: "finance",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    transaction_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    pool_id = table.Column<long>(type: "bigint", nullable: false),
                    kind = table.Column<string>(type: "character varying(32)", maxLength: 32, nullable: false),
                    inventory_quantity_effect = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    inventory_base_value_effect = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    expense_base_value_effect = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    unit_base_value = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: true),
                    inventory_movement_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    source_module = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: false),
                    source_entity_type = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: false),
                    source_document_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    source_line_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    reversal_of_valuation_entry_id = table.Column<long>(type: "bigint", nullable: true),
                    posted_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_inventory_valuation_entries", x => x.id);
                    table.UniqueConstraint("AK_inventory_valuation_entries_id_company_id", x => new { x.id, x.company_id });
                    table.ForeignKey(
                        name: "FK_inventory_valuation_entries_inventory_valuation_entries_rev~",
                        columns: x => new { x.reversal_of_valuation_entry_id, x.company_id },
                        principalSchema: "finance",
                        principalTable: "inventory_valuation_entries",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_inventory_valuation_entries_inventory_valuation_pools_pool_~",
                        columns: x => new { x.pool_id, x.company_id },
                        principalSchema: "finance",
                        principalTable: "inventory_valuation_pools",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_inventory_valuation_entries_transactions_transaction_id_com~",
                        columns: x => new { x.transaction_id, x.company_id },
                        principalSchema: "finance",
                        principalTable: "transactions",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "statement_lines",
                schema: "finance",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    batch_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    bank_account_id = table.Column<long>(type: "bigint", nullable: false),
                    booking_date = table.Column<DateOnly>(type: "date", nullable: false),
                    value_date = table.Column<DateOnly>(type: "date", nullable: true),
                    amount = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    currency_code = table.Column<string>(type: "character varying(3)", maxLength: 3, nullable: false),
                    external_id = table.Column<string>(type: "character varying(256)", maxLength: 256, nullable: true),
                    fingerprint = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: false),
                    description = table.Column<string>(type: "character varying(1024)", maxLength: 1024, nullable: true),
                    state = table.Column<string>(type: "character varying(24)", maxLength: 24, nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_statement_lines", x => x.id);
                    table.UniqueConstraint("ak_finance_statement_lines_id_company", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_finance_statement_amount", "amount <> 0");
                    table.ForeignKey(
                        name: "FK_statement_lines_bank_accounts_bank_account_id_company_id",
                        columns: x => new { x.bank_account_id, x.company_id },
                        principalSchema: "finance",
                        principalTable: "bank_accounts",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_statement_lines_statement_import_batches_batch_id_company_id",
                        columns: x => new { x.batch_id, x.company_id },
                        principalSchema: "finance",
                        principalTable: "statement_import_batches",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "dispatch_cost_bridge_consumptions",
                schema: "finance",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    transaction_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    bridge_entry_id = table.Column<long>(type: "bigint", nullable: false),
                    sales_invoice_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    sales_invoice_line_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    base_quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    base_value = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    reversal_of_consumption_id = table.Column<long>(type: "bigint", nullable: true),
                    posted_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_dispatch_cost_bridge_consumptions", x => x.id);
                    table.UniqueConstraint("AK_dispatch_cost_bridge_consumptions_id_company_id", x => new { x.id, x.company_id });
                    table.ForeignKey(
                        name: "FK_dispatch_cost_bridge_consumptions_dispatch_cost_bridge_cons~",
                        columns: x => new { x.reversal_of_consumption_id, x.company_id },
                        principalSchema: "finance",
                        principalTable: "dispatch_cost_bridge_consumptions",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_dispatch_cost_bridge_consumptions_dispatch_cost_bridge_entr~",
                        columns: x => new { x.bridge_entry_id, x.company_id },
                        principalSchema: "finance",
                        principalTable: "dispatch_cost_bridge_entries",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_dispatch_cost_bridge_consumptions_transactions_transaction_~",
                        columns: x => new { x.transaction_id, x.company_id },
                        principalSchema: "finance",
                        principalTable: "transactions",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "reconciliation_matches",
                schema: "finance",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    statement_line_id = table.Column<long>(type: "bigint", nullable: false),
                    bank_ledger_entry_id = table.Column<long>(type: "bigint", nullable: false),
                    matched_amount = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    state = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    reversed_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_reconciliation_matches", x => x.id);
                    table.CheckConstraint("ck_finance_reconciliation_amount", "matched_amount > 0");
                    table.ForeignKey(
                        name: "FK_reconciliation_matches_bank_ledger_entries_bank_ledger_entr~",
                        columns: x => new { x.bank_ledger_entry_id, x.company_id },
                        principalSchema: "finance",
                        principalTable: "bank_ledger_entries",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_reconciliation_matches_statement_lines_statement_line_id_co~",
                        columns: x => new { x.statement_line_id, x.company_id },
                        principalSchema: "finance",
                        principalTable: "statement_lines",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateIndex(
                name: "IX_account_ledger_entries_party_id_company_id",
                schema: "finance",
                table: "account_ledger_entries",
                columns: new[] { "party_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_account_ledger_entries_public_id",
                schema: "finance",
                table: "account_ledger_entries",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_account_ledger_entries_transaction_id_company_id",
                schema: "finance",
                table: "account_ledger_entries",
                columns: new[] { "transaction_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ix_finance_account_ledger_balance",
                schema: "finance",
                table: "account_ledger_entries",
                columns: new[] { "company_id", "party_id", "party_role", "currency_code", "id" });

            migrationBuilder.CreateIndex(
                name: "IX_bank_accounts_public_id",
                schema: "finance",
                table: "bank_accounts",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_finance_bank_accounts_company_code",
                schema: "finance",
                table: "bank_accounts",
                columns: new[] { "company_id", "code" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_finance_bank_accounts_company_iban",
                schema: "finance",
                table: "bank_accounts",
                columns: new[] { "company_id", "iban" },
                unique: true,
                filter: "iban IS NOT NULL");

            migrationBuilder.CreateIndex(
                name: "IX_bank_ledger_entries_bank_account_id_company_id",
                schema: "finance",
                table: "bank_ledger_entries",
                columns: new[] { "bank_account_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_bank_ledger_entries_public_id",
                schema: "finance",
                table: "bank_ledger_entries",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_bank_ledger_entries_transaction_id_company_id",
                schema: "finance",
                table: "bank_ledger_entries",
                columns: new[] { "transaction_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ix_finance_bank_ledger_balance",
                schema: "finance",
                table: "bank_ledger_entries",
                columns: new[] { "company_id", "bank_account_id", "id" });

            migrationBuilder.CreateIndex(
                name: "IX_cash_accounts_public_id",
                schema: "finance",
                table: "cash_accounts",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_finance_cash_accounts_company_code",
                schema: "finance",
                table: "cash_accounts",
                columns: new[] { "company_id", "code" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_cash_counts_adjustment_transaction_id_company_id",
                schema: "finance",
                table: "cash_counts",
                columns: new[] { "adjustment_transaction_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_cash_counts_cash_account_id_company_id",
                schema: "finance",
                table: "cash_counts",
                columns: new[] { "cash_account_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_cash_counts_public_id",
                schema: "finance",
                table: "cash_counts",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_cash_ledger_entries_cash_account_id_company_id",
                schema: "finance",
                table: "cash_ledger_entries",
                columns: new[] { "cash_account_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_cash_ledger_entries_public_id",
                schema: "finance",
                table: "cash_ledger_entries",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_cash_ledger_entries_transaction_id_company_id",
                schema: "finance",
                table: "cash_ledger_entries",
                columns: new[] { "transaction_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ix_finance_cash_ledger_balance",
                schema: "finance",
                table: "cash_ledger_entries",
                columns: new[] { "company_id", "cash_account_id", "id" });

            migrationBuilder.CreateIndex(
                name: "IX_customer_risk_controls_party_id_company_id",
                schema: "finance",
                table: "customer_risk_controls",
                columns: new[] { "party_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_customer_risk_controls_public_id",
                schema: "finance",
                table: "customer_risk_controls",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_finance_customer_risk_party",
                schema: "finance",
                table: "customer_risk_controls",
                columns: new[] { "company_id", "party_id" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_cost_bridge_consumptions_bridge_entry_id_company_id",
                schema: "finance",
                table: "dispatch_cost_bridge_consumptions",
                columns: new[] { "bridge_entry_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_cost_bridge_consumptions_public_id",
                schema: "finance",
                table: "dispatch_cost_bridge_consumptions",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_cost_bridge_consumptions_reversal_of_consumption_i~",
                schema: "finance",
                table: "dispatch_cost_bridge_consumptions",
                columns: new[] { "reversal_of_consumption_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_cost_bridge_consumptions_transaction_id_company_id",
                schema: "finance",
                table: "dispatch_cost_bridge_consumptions",
                columns: new[] { "transaction_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ix_finance_bridge_consumption_invoice",
                schema: "finance",
                table: "dispatch_cost_bridge_consumptions",
                columns: new[] { "company_id", "sales_invoice_public_id", "sales_invoice_line_public_id" });

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_cost_bridge_entries_pool_id_company_id",
                schema: "finance",
                table: "dispatch_cost_bridge_entries",
                columns: new[] { "pool_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_cost_bridge_entries_public_id",
                schema: "finance",
                table: "dispatch_cost_bridge_entries",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_cost_bridge_entries_reversal_of_bridge_entry_id_co~",
                schema: "finance",
                table: "dispatch_cost_bridge_entries",
                columns: new[] { "reversal_of_bridge_entry_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_cost_bridge_entries_transaction_id_company_id",
                schema: "finance",
                table: "dispatch_cost_bridge_entries",
                columns: new[] { "transaction_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ix_finance_dispatch_bridge_source",
                schema: "finance",
                table: "dispatch_cost_bridge_entries",
                columns: new[] { "company_id", "dispatch_public_id", "dispatch_line_public_id", "physical_source_public_id" });

            migrationBuilder.CreateIndex(
                name: "ux_finance_dispatch_bridge_inventory_movement",
                schema: "finance",
                table: "dispatch_cost_bridge_entries",
                column: "inventory_movement_public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ix_finance_valuation_pool_entries",
                schema: "finance",
                table: "inventory_valuation_entries",
                columns: new[] { "company_id", "pool_id", "id" });

            migrationBuilder.CreateIndex(
                name: "ix_finance_valuation_source",
                schema: "finance",
                table: "inventory_valuation_entries",
                columns: new[] { "company_id", "source_module", "source_entity_type", "source_document_public_id", "source_line_public_id" });

            migrationBuilder.CreateIndex(
                name: "IX_inventory_valuation_entries_pool_id_company_id",
                schema: "finance",
                table: "inventory_valuation_entries",
                columns: new[] { "pool_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_inventory_valuation_entries_public_id",
                schema: "finance",
                table: "inventory_valuation_entries",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_inventory_valuation_entries_reversal_of_valuation_entry_id_~",
                schema: "finance",
                table: "inventory_valuation_entries",
                columns: new[] { "reversal_of_valuation_entry_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_inventory_valuation_entries_transaction_id_company_id",
                schema: "finance",
                table: "inventory_valuation_entries",
                columns: new[] { "transaction_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_inventory_valuation_pools_base_uom_id_company_id",
                schema: "finance",
                table: "inventory_valuation_pools",
                columns: new[] { "base_uom_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_inventory_valuation_pools_product_id_company_id",
                schema: "finance",
                table: "inventory_valuation_pools",
                columns: new[] { "product_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_inventory_valuation_pools_public_id",
                schema: "finance",
                table: "inventory_valuation_pools",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_inventory_valuation_pools_variant_id_company_id",
                schema: "finance",
                table: "inventory_valuation_pools",
                columns: new[] { "variant_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_finance_valuation_pool_grain",
                schema: "finance",
                table: "inventory_valuation_pools",
                columns: new[] { "company_id", "product_id", "variant_id", "currency_code" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_posting_periods_public_id",
                schema: "finance",
                table: "posting_periods",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_finance_period_range",
                schema: "finance",
                table: "posting_periods",
                columns: new[] { "company_id", "start_date", "end_date" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ix_finance_reconciliation_pair",
                schema: "finance",
                table: "reconciliation_matches",
                columns: new[] { "company_id", "statement_line_id", "bank_ledger_entry_id", "state" });

            migrationBuilder.CreateIndex(
                name: "IX_reconciliation_matches_bank_ledger_entry_id_company_id",
                schema: "finance",
                table: "reconciliation_matches",
                columns: new[] { "bank_ledger_entry_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_reconciliation_matches_public_id",
                schema: "finance",
                table: "reconciliation_matches",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_reconciliation_matches_statement_line_id_company_id",
                schema: "finance",
                table: "reconciliation_matches",
                columns: new[] { "statement_line_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_statement_import_batches_bank_account_id_company_id",
                schema: "finance",
                table: "statement_import_batches",
                columns: new[] { "bank_account_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_statement_import_batches_public_id",
                schema: "finance",
                table: "statement_import_batches",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_statement_lines_bank_account_id_company_id",
                schema: "finance",
                table: "statement_lines",
                columns: new[] { "bank_account_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_statement_lines_batch_id_company_id",
                schema: "finance",
                table: "statement_lines",
                columns: new[] { "batch_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_statement_lines_public_id",
                schema: "finance",
                table: "statement_lines",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_finance_statement_external",
                schema: "finance",
                table: "statement_lines",
                columns: new[] { "company_id", "bank_account_id", "external_id" },
                unique: true,
                filter: "external_id IS NOT NULL");

            migrationBuilder.CreateIndex(
                name: "ux_finance_statement_fingerprint",
                schema: "finance",
                table: "statement_lines",
                columns: new[] { "company_id", "bank_account_id", "fingerprint" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ix_finance_transactions_posting",
                schema: "finance",
                table: "transactions",
                columns: new[] { "company_id", "posting_date", "id" });

            migrationBuilder.CreateIndex(
                name: "ix_finance_transactions_source",
                schema: "finance",
                table: "transactions",
                columns: new[] { "company_id", "source_module", "source_entity_type", "source_document_public_id", "kind" });

            migrationBuilder.CreateIndex(
                name: "IX_transactions_party_id_company_id",
                schema: "finance",
                table: "transactions",
                columns: new[] { "party_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_transactions_public_id",
                schema: "finance",
                table: "transactions",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_transactions_reversal_of_transaction_id_company_id",
                schema: "finance",
                table: "transactions",
                columns: new[] { "reversal_of_transaction_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_finance_transactions_single_reversal",
                schema: "finance",
                table: "transactions",
                column: "reversal_of_transaction_id",
                unique: true,
                filter: "reversal_of_transaction_id IS NOT NULL");
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropTable(
                name: "account_ledger_entries",
                schema: "finance");

            migrationBuilder.DropTable(
                name: "cash_counts",
                schema: "finance");

            migrationBuilder.DropTable(
                name: "cash_ledger_entries",
                schema: "finance");

            migrationBuilder.DropTable(
                name: "customer_risk_controls",
                schema: "finance");

            migrationBuilder.DropTable(
                name: "dispatch_cost_bridge_consumptions",
                schema: "finance");

            migrationBuilder.DropTable(
                name: "inventory_valuation_entries",
                schema: "finance");

            migrationBuilder.DropTable(
                name: "posting_periods",
                schema: "finance");

            migrationBuilder.DropTable(
                name: "reconciliation_matches",
                schema: "finance");

            migrationBuilder.DropTable(
                name: "cash_accounts",
                schema: "finance");

            migrationBuilder.DropTable(
                name: "dispatch_cost_bridge_entries",
                schema: "finance");

            migrationBuilder.DropTable(
                name: "bank_ledger_entries",
                schema: "finance");

            migrationBuilder.DropTable(
                name: "statement_lines",
                schema: "finance");

            migrationBuilder.DropTable(
                name: "inventory_valuation_pools",
                schema: "finance");

            migrationBuilder.DropTable(
                name: "transactions",
                schema: "finance");

            migrationBuilder.DropTable(
                name: "statement_import_batches",
                schema: "finance");

            migrationBuilder.DropTable(
                name: "bank_accounts",
                schema: "finance");
        }
    }
}
