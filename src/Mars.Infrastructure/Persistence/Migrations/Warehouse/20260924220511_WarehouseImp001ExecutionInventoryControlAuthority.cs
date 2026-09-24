using System;
using Microsoft.EntityFrameworkCore.Migrations;
using Npgsql.EntityFrameworkCore.PostgreSQL.Metadata;

#nullable disable

namespace Mars.Infrastructure.Persistence.Migrations.Warehouse
{
    /// <inheritdoc />
    public partial class WarehouseImp001ExecutionInventoryControlAuthority : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.EnsureSchema(
                name: "warehouse");

            migrationBuilder.CreateTable(
                name: "disposition_effects",
                schema: "warehouse",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    warehouse_id = table.Column<long>(type: "bigint", nullable: false),
                    location_id = table.Column<long>(type: "bigint", nullable: true),
                    product_id = table.Column<long>(type: "bigint", nullable: false),
                    variant_id = table.Column<long>(type: "bigint", nullable: true),
                    uom_id = table.Column<long>(type: "bigint", nullable: false),
                    lot_id = table.Column<long>(type: "bigint", nullable: true),
                    serial_id = table.Column<long>(type: "bigint", nullable: true),
                    source_disposition = table.Column<string>(type: "character varying(24)", maxLength: 24, nullable: false),
                    target_disposition = table.Column<string>(type: "character varying(24)", maxLength: 24, nullable: false),
                    quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    reason = table.Column<string>(type: "character varying(512)", maxLength: 512, nullable: false),
                    inventory_movement_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    source_document_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    source_line_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_disposition_effects", x => x.id);
                    table.CheckConstraint("ck_warehouse_disposition_change", "source_disposition <> target_disposition");
                    table.CheckConstraint("ck_warehouse_disposition_quantity", "quantity > 0");
                    table.ForeignKey(
                        name: "FK_disposition_effects_products_product_id_company_id",
                        columns: x => new { x.product_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "products",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_disposition_effects_uoms_uom_id_company_id",
                        columns: x => new { x.uom_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "uoms",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_disposition_effects_variants_variant_id_company_id",
                        columns: x => new { x.variant_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "variants",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_disposition_effects_warehouses_warehouse_id_company_id",
                        columns: x => new { x.warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "warehouses",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "offline_operations",
                schema: "warehouse",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    client_operation_id = table.Column<Guid>(type: "uuid", nullable: false),
                    warehouse_id = table.Column<long>(type: "bigint", nullable: false),
                    operation_type = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: false),
                    work_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    expected_version = table.Column<long>(type: "bigint", nullable: true),
                    scan_identity = table.Column<string>(type: "character varying(256)", maxLength: 256, nullable: false),
                    local_timestamp = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    state = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    conflict_code = table.Column<string>(type: "character varying(128)", maxLength: 128, nullable: true),
                    server_result_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    correlation_id = table.Column<string>(type: "character varying(128)", maxLength: 128, nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_offline_operations", x => x.id);
                    table.ForeignKey(
                        name: "FK_offline_operations_warehouses_warehouse_id_company_id",
                        columns: x => new { x.warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "warehouses",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "operations",
                schema: "warehouse",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    kind = table.Column<string>(type: "character varying(20)", maxLength: 20, nullable: false),
                    warehouse_id = table.Column<long>(type: "bigint", nullable: false),
                    source_location_id = table.Column<long>(type: "bigint", nullable: true),
                    target_location_id = table.Column<long>(type: "bigint", nullable: true),
                    product_id = table.Column<long>(type: "bigint", nullable: false),
                    variant_id = table.Column<long>(type: "bigint", nullable: true),
                    uom_id = table.Column<long>(type: "bigint", nullable: false),
                    lot_id = table.Column<long>(type: "bigint", nullable: true),
                    serial_id = table.Column<long>(type: "bigint", nullable: true),
                    disposition = table.Column<string>(type: "character varying(24)", maxLength: 24, nullable: false),
                    quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    inventory_movement_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    source_document_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    source_line_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    state = table.Column<string>(type: "character varying(24)", maxLength: 24, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    completed_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_operations", x => x.id);
                    table.CheckConstraint("ck_warehouse_operations_locations", "source_location_id IS DISTINCT FROM target_location_id");
                    table.CheckConstraint("ck_warehouse_operations_quantity", "quantity > 0");
                    table.CheckConstraint("ck_warehouse_operations_version", "version > 0");
                    table.ForeignKey(
                        name: "FK_operations_products_product_id_company_id",
                        columns: x => new { x.product_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "products",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_operations_uoms_uom_id_company_id",
                        columns: x => new { x.uom_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "uoms",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_operations_variants_variant_id_company_id",
                        columns: x => new { x.variant_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "variants",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_operations_warehouses_warehouse_id_company_id",
                        columns: x => new { x.warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "warehouses",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "packages",
                schema: "warehouse",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    package_code = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: false),
                    dispatch_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    warehouse_id = table.Column<long>(type: "bigint", nullable: false),
                    state = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    tracking_reference = table.Column<string>(type: "character varying(128)", maxLength: 128, nullable: true),
                    carrier_reference = table.Column<string>(type: "character varying(128)", maxLength: 128, nullable: true),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_packages", x => x.id);
                    table.UniqueConstraint("AK_packages_id_company_id", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_warehouse_packages_code", "length(btrim(package_code)) > 0 AND package_code=btrim(package_code)");
                    table.ForeignKey(
                        name: "FK_packages_warehouses_warehouse_id_company_id",
                        columns: x => new { x.warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "warehouses",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "pick_works",
                schema: "warehouse",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    dispatch_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    dispatch_line_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    warehouse_id = table.Column<long>(type: "bigint", nullable: false),
                    location_id = table.Column<long>(type: "bigint", nullable: false),
                    product_id = table.Column<long>(type: "bigint", nullable: false),
                    variant_id = table.Column<long>(type: "bigint", nullable: true),
                    uom_id = table.Column<long>(type: "bigint", nullable: false),
                    conversion_factor_snapshot = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    lot_id = table.Column<long>(type: "bigint", nullable: true),
                    serial_id = table.Column<long>(type: "bigint", nullable: true),
                    reservation_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    strategy = table.Column<string>(type: "character varying(12)", maxLength: 12, nullable: false),
                    strategy_override = table.Column<bool>(type: "boolean", nullable: false),
                    override_reason = table.Column<string>(type: "character varying(512)", maxLength: 512, nullable: true),
                    state = table.Column<string>(type: "character varying(24)", maxLength: 24, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_pick_works", x => x.id);
                    table.CheckConstraint("ck_warehouse_pick_conversion", "conversion_factor_snapshot > 0");
                    table.CheckConstraint("ck_warehouse_pick_override_reason", "NOT strategy_override OR length(btrim(override_reason)) > 0");
                    table.CheckConstraint("ck_warehouse_pick_quantity", "quantity > 0");
                    table.CheckConstraint("ck_warehouse_pick_version", "version > 0");
                    table.ForeignKey(
                        name: "FK_pick_works_products_product_id_company_id",
                        columns: x => new { x.product_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "products",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_pick_works_uoms_uom_id_company_id",
                        columns: x => new { x.uom_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "uoms",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_pick_works_variants_variant_id_company_id",
                        columns: x => new { x.variant_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "variants",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_pick_works_warehouses_warehouse_id_company_id",
                        columns: x => new { x.warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "warehouses",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "scrap_requests",
                schema: "warehouse",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    number = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: false),
                    warehouse_id = table.Column<long>(type: "bigint", nullable: false),
                    location_id = table.Column<long>(type: "bigint", nullable: true),
                    product_id = table.Column<long>(type: "bigint", nullable: false),
                    variant_id = table.Column<long>(type: "bigint", nullable: true),
                    uom_id = table.Column<long>(type: "bigint", nullable: false),
                    conversion_factor_snapshot = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    disposition_id = table.Column<long>(type: "bigint", nullable: false),
                    lot_id = table.Column<long>(type: "bigint", nullable: true),
                    serial_id = table.Column<long>(type: "bigint", nullable: true),
                    quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    reason = table.Column<string>(type: "character varying(512)", maxLength: 512, nullable: false),
                    state = table.Column<string>(type: "character varying(24)", maxLength: 24, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    approval_decision_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    inventory_movement_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    posted_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_scrap_requests", x => x.id);
                    table.CheckConstraint("ck_warehouse_scrap_quantity", "quantity > 0");
                });

            migrationBuilder.CreateTable(
                name: "stage_load_works",
                schema: "warehouse",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    dispatch_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    warehouse_id = table.Column<long>(type: "bigint", nullable: false),
                    kind = table.Column<string>(type: "character varying(12)", maxLength: 12, nullable: false),
                    state = table.Column<string>(type: "character varying(24)", maxLength: 24, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_stage_load_works", x => x.id);
                    table.UniqueConstraint("AK_stage_load_works_id_company_id", x => new { x.id, x.company_id });
                    table.ForeignKey(
                        name: "FK_stage_load_works_warehouses_warehouse_id_company_id",
                        columns: x => new { x.warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "warehouses",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "stock_count_sessions",
                schema: "warehouse",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    number = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: false),
                    warehouse_id = table.Column<long>(type: "bigint", nullable: false),
                    state = table.Column<string>(type: "character varying(24)", maxLength: 24, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    snapshot_movement_id = table.Column<long>(type: "bigint", nullable: true),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    approval_decision_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    started_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true),
                    reviewed_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true),
                    posted_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_stock_count_sessions", x => x.id);
                    table.UniqueConstraint("AK_stock_count_sessions_id_company_id", x => new { x.id, x.company_id });
                    table.ForeignKey(
                        name: "FK_stock_count_sessions_warehouses_warehouse_id_company_id",
                        columns: x => new { x.warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "warehouses",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "transfers",
                schema: "warehouse",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    number = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: false),
                    source_warehouse_id = table.Column<long>(type: "bigint", nullable: false),
                    target_warehouse_id = table.Column<long>(type: "bigint", nullable: false),
                    state = table.Column<string>(type: "character varying(32)", maxLength: 32, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    issued_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true),
                    closed_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_transfers", x => x.id);
                    table.UniqueConstraint("AK_transfers_id_company_id", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_warehouse_transfer_distinct", "source_warehouse_id <> target_warehouse_id");
                    table.CheckConstraint("ck_warehouse_transfer_number", "length(btrim(number)) > 0 AND number=btrim(number)");
                    table.CheckConstraint("ck_warehouse_transfer_version", "version > 0");
                    table.ForeignKey(
                        name: "FK_transfers_warehouses_source_warehouse_id_company_id",
                        columns: x => new { x.source_warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "warehouses",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_transfers_warehouses_target_warehouse_id_company_id",
                        columns: x => new { x.target_warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "warehouses",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "package_items",
                schema: "warehouse",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    package_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    dispatch_line_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    lot_id = table.Column<long>(type: "bigint", nullable: true),
                    serial_id = table.Column<long>(type: "bigint", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_package_items", x => x.id);
                    table.CheckConstraint("ck_warehouse_package_items_quantity", "quantity > 0");
                    table.ForeignKey(
                        name: "FK_package_items_packages_package_id_company_id",
                        columns: x => new { x.package_id, x.company_id },
                        principalSchema: "warehouse",
                        principalTable: "packages",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "stage_load_packages",
                schema: "warehouse",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    stage_load_work_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    package_id = table.Column<long>(type: "bigint", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_stage_load_packages", x => x.id);
                    table.ForeignKey(
                        name: "FK_stage_load_packages_packages_package_id_company_id",
                        columns: x => new { x.package_id, x.company_id },
                        principalSchema: "warehouse",
                        principalTable: "packages",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "FK_stage_load_packages_stage_load_works_stage_load_work_id_com~",
                        columns: x => new { x.stage_load_work_id, x.company_id },
                        principalSchema: "warehouse",
                        principalTable: "stage_load_works",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "stock_count_lines",
                schema: "warehouse",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    count_session_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    product_id = table.Column<long>(type: "bigint", nullable: false),
                    variant_id = table.Column<long>(type: "bigint", nullable: true),
                    uom_id = table.Column<long>(type: "bigint", nullable: false),
                    conversion_factor_snapshot = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    location_id = table.Column<long>(type: "bigint", nullable: false),
                    disposition_id = table.Column<long>(type: "bigint", nullable: false),
                    lot_id = table.Column<long>(type: "bigint", nullable: true),
                    serial_id = table.Column<long>(type: "bigint", nullable: true),
                    expected_start_quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    net_intervening_quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    expected_reconciliation_quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    accepted_count_quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: true),
                    discrepancy_quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_stock_count_lines", x => x.id);
                    table.UniqueConstraint("AK_stock_count_lines_id_company_id", x => new { x.id, x.company_id });
                    table.ForeignKey(
                        name: "FK_stock_count_lines_stock_count_sessions_count_session_id_com~",
                        columns: x => new { x.count_session_id, x.company_id },
                        principalSchema: "warehouse",
                        principalTable: "stock_count_sessions",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "stock_count_scopes",
                schema: "warehouse",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    count_session_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    location_id = table.Column<long>(type: "bigint", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_stock_count_scopes", x => x.id);
                    table.ForeignKey(
                        name: "FK_stock_count_scopes_stock_count_sessions_count_session_id_co~",
                        columns: x => new { x.count_session_id, x.company_id },
                        principalSchema: "warehouse",
                        principalTable: "stock_count_sessions",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "transfer_lines",
                schema: "warehouse",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    transfer_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    sequence = table.Column<int>(type: "integer", nullable: false),
                    product_id = table.Column<long>(type: "bigint", nullable: false),
                    variant_id = table.Column<long>(type: "bigint", nullable: true),
                    uom_id = table.Column<long>(type: "bigint", nullable: false),
                    conversion_factor_snapshot = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    requested_quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    issued_quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    received_quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    damaged_received_quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    resolved_loss_quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    source_location_id = table.Column<long>(type: "bigint", nullable: true),
                    target_location_id = table.Column<long>(type: "bigint", nullable: false),
                    lot_id = table.Column<long>(type: "bigint", nullable: true),
                    serial_id = table.Column<long>(type: "bigint", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_transfer_lines", x => x.id);
                    table.UniqueConstraint("AK_transfer_lines_id_company_id", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_warehouse_transfer_line_conversion", "conversion_factor_snapshot > 0");
                    table.CheckConstraint("ck_warehouse_transfer_line_progress", "issued_quantity >= 0 AND received_quantity >= 0 AND damaged_received_quantity >= 0 AND resolved_loss_quantity >= 0 AND received_quantity + resolved_loss_quantity <= issued_quantity");
                    table.CheckConstraint("ck_warehouse_transfer_line_requested", "requested_quantity > 0");
                    table.CheckConstraint("ck_warehouse_transfer_line_sequence", "sequence > 0");
                    table.ForeignKey(
                        name: "FK_transfer_lines_transfers_transfer_id_company_id",
                        columns: x => new { x.transfer_id, x.company_id },
                        principalSchema: "warehouse",
                        principalTable: "transfers",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "stock_count_effect_links",
                schema: "warehouse",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    count_line_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    inventory_movement_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    is_reversal = table.Column<bool>(type: "boolean", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_stock_count_effect_links", x => x.id);
                    table.ForeignKey(
                        name: "FK_stock_count_effect_links_stock_count_lines_count_line_id_co~",
                        columns: x => new { x.count_line_id, x.company_id },
                        principalSchema: "warehouse",
                        principalTable: "stock_count_lines",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "stock_count_observations",
                schema: "warehouse",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    count_line_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    counted_quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    is_recount = table.Column<bool>(type: "boolean", nullable: false),
                    actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_stock_count_observations", x => x.id);
                    table.CheckConstraint("ck_warehouse_count_observation_nonnegative", "counted_quantity >= 0");
                    table.ForeignKey(
                        name: "FK_stock_count_observations_stock_count_lines_count_line_id_co~",
                        columns: x => new { x.count_line_id, x.company_id },
                        principalSchema: "warehouse",
                        principalTable: "stock_count_lines",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "transfer_effect_links",
                schema: "warehouse",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    transfer_line_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    inventory_movement_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    effect_kind = table.Column<string>(type: "character varying(24)", maxLength: 24, nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_transfer_effect_links", x => x.id);
                    table.ForeignKey(
                        name: "FK_transfer_effect_links_transfer_lines_transfer_line_id_compa~",
                        columns: x => new { x.transfer_line_id, x.company_id },
                        principalSchema: "warehouse",
                        principalTable: "transfer_lines",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateIndex(
                name: "IX_disposition_effects_inventory_movement_public_id",
                schema: "warehouse",
                table: "disposition_effects",
                column: "inventory_movement_public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_disposition_effects_product_id_company_id",
                schema: "warehouse",
                table: "disposition_effects",
                columns: new[] { "product_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_disposition_effects_public_id",
                schema: "warehouse",
                table: "disposition_effects",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_disposition_effects_uom_id_company_id",
                schema: "warehouse",
                table: "disposition_effects",
                columns: new[] { "uom_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_disposition_effects_variant_id_company_id",
                schema: "warehouse",
                table: "disposition_effects",
                columns: new[] { "variant_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_disposition_effects_warehouse_id_company_id",
                schema: "warehouse",
                table: "disposition_effects",
                columns: new[] { "warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_offline_operations_company_id_client_operation_id",
                schema: "warehouse",
                table: "offline_operations",
                columns: new[] { "company_id", "client_operation_id" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_offline_operations_public_id",
                schema: "warehouse",
                table: "offline_operations",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_offline_operations_warehouse_id_company_id",
                schema: "warehouse",
                table: "offline_operations",
                columns: new[] { "warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_operations_product_id_company_id",
                schema: "warehouse",
                table: "operations",
                columns: new[] { "product_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_operations_public_id",
                schema: "warehouse",
                table: "operations",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_operations_uom_id_company_id",
                schema: "warehouse",
                table: "operations",
                columns: new[] { "uom_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_operations_variant_id_company_id",
                schema: "warehouse",
                table: "operations",
                columns: new[] { "variant_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_operations_warehouse_id_company_id",
                schema: "warehouse",
                table: "operations",
                columns: new[] { "warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ix_warehouse_operations_work",
                schema: "warehouse",
                table: "operations",
                columns: new[] { "company_id", "warehouse_id", "state" });

            migrationBuilder.CreateIndex(
                name: "ux_warehouse_operations_inventory_movement",
                schema: "warehouse",
                table: "operations",
                column: "inventory_movement_public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_package_items_package_id_company_id",
                schema: "warehouse",
                table: "package_items",
                columns: new[] { "package_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_package_items_package_id_dispatch_line_public_id_lot_id_ser~",
                schema: "warehouse",
                table: "package_items",
                columns: new[] { "package_id", "dispatch_line_public_id", "lot_id", "serial_id" });

            migrationBuilder.CreateIndex(
                name: "IX_package_items_public_id",
                schema: "warehouse",
                table: "package_items",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_packages_company_id_dispatch_public_id",
                schema: "warehouse",
                table: "packages",
                columns: new[] { "company_id", "dispatch_public_id" });

            migrationBuilder.CreateIndex(
                name: "IX_packages_company_id_package_code",
                schema: "warehouse",
                table: "packages",
                columns: new[] { "company_id", "package_code" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_packages_public_id",
                schema: "warehouse",
                table: "packages",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_packages_warehouse_id_company_id",
                schema: "warehouse",
                table: "packages",
                columns: new[] { "warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_pick_works_product_id_company_id",
                schema: "warehouse",
                table: "pick_works",
                columns: new[] { "product_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_pick_works_public_id",
                schema: "warehouse",
                table: "pick_works",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_pick_works_uom_id_company_id",
                schema: "warehouse",
                table: "pick_works",
                columns: new[] { "uom_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_pick_works_variant_id_company_id",
                schema: "warehouse",
                table: "pick_works",
                columns: new[] { "variant_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_pick_works_warehouse_id_company_id",
                schema: "warehouse",
                table: "pick_works",
                columns: new[] { "warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ix_warehouse_pick_dispatch_line",
                schema: "warehouse",
                table: "pick_works",
                columns: new[] { "company_id", "dispatch_public_id", "dispatch_line_public_id" });

            migrationBuilder.CreateIndex(
                name: "IX_scrap_requests_company_id_number",
                schema: "warehouse",
                table: "scrap_requests",
                columns: new[] { "company_id", "number" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_scrap_requests_inventory_movement_public_id",
                schema: "warehouse",
                table: "scrap_requests",
                column: "inventory_movement_public_id",
                unique: true,
                filter: "inventory_movement_public_id IS NOT NULL");

            migrationBuilder.CreateIndex(
                name: "IX_scrap_requests_public_id",
                schema: "warehouse",
                table: "scrap_requests",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_stage_load_packages_package_id_company_id",
                schema: "warehouse",
                table: "stage_load_packages",
                columns: new[] { "package_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_stage_load_packages_stage_load_work_id_company_id",
                schema: "warehouse",
                table: "stage_load_packages",
                columns: new[] { "stage_load_work_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_stage_load_packages_stage_load_work_id_package_id",
                schema: "warehouse",
                table: "stage_load_packages",
                columns: new[] { "stage_load_work_id", "package_id" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_stage_load_works_company_id_dispatch_public_id_kind",
                schema: "warehouse",
                table: "stage_load_works",
                columns: new[] { "company_id", "dispatch_public_id", "kind" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_stage_load_works_public_id",
                schema: "warehouse",
                table: "stage_load_works",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_stage_load_works_warehouse_id_company_id",
                schema: "warehouse",
                table: "stage_load_works",
                columns: new[] { "warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_stock_count_effect_links_count_line_id_company_id",
                schema: "warehouse",
                table: "stock_count_effect_links",
                columns: new[] { "count_line_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_stock_count_effect_links_inventory_movement_public_id",
                schema: "warehouse",
                table: "stock_count_effect_links",
                column: "inventory_movement_public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_stock_count_effect_links_public_id",
                schema: "warehouse",
                table: "stock_count_effect_links",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_stock_count_lines_count_session_id_company_id",
                schema: "warehouse",
                table: "stock_count_lines",
                columns: new[] { "count_session_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_stock_count_lines_count_session_id_product_id_variant_id_lo~",
                schema: "warehouse",
                table: "stock_count_lines",
                columns: new[] { "count_session_id", "product_id", "variant_id", "location_id", "disposition_id", "lot_id", "serial_id" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_stock_count_lines_public_id",
                schema: "warehouse",
                table: "stock_count_lines",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_stock_count_observations_count_line_id_company_id",
                schema: "warehouse",
                table: "stock_count_observations",
                columns: new[] { "count_line_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_stock_count_observations_public_id",
                schema: "warehouse",
                table: "stock_count_observations",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_stock_count_scopes_count_session_id_company_id",
                schema: "warehouse",
                table: "stock_count_scopes",
                columns: new[] { "count_session_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_stock_count_scopes_count_session_id_location_id",
                schema: "warehouse",
                table: "stock_count_scopes",
                columns: new[] { "count_session_id", "location_id" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_stock_count_sessions_company_id_number",
                schema: "warehouse",
                table: "stock_count_sessions",
                columns: new[] { "company_id", "number" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_stock_count_sessions_public_id",
                schema: "warehouse",
                table: "stock_count_sessions",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_stock_count_sessions_warehouse_id_company_id",
                schema: "warehouse",
                table: "stock_count_sessions",
                columns: new[] { "warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_transfer_effect_links_inventory_movement_public_id",
                schema: "warehouse",
                table: "transfer_effect_links",
                column: "inventory_movement_public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_transfer_effect_links_public_id",
                schema: "warehouse",
                table: "transfer_effect_links",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_transfer_effect_links_transfer_line_id_company_id",
                schema: "warehouse",
                table: "transfer_effect_links",
                columns: new[] { "transfer_line_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_transfer_lines_public_id",
                schema: "warehouse",
                table: "transfer_lines",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_transfer_lines_transfer_id_company_id",
                schema: "warehouse",
                table: "transfer_lines",
                columns: new[] { "transfer_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_transfer_lines_transfer_id_sequence",
                schema: "warehouse",
                table: "transfer_lines",
                columns: new[] { "transfer_id", "sequence" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_transfers_company_id_number",
                schema: "warehouse",
                table: "transfers",
                columns: new[] { "company_id", "number" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_transfers_company_id_state_created_at",
                schema: "warehouse",
                table: "transfers",
                columns: new[] { "company_id", "state", "created_at" });

            migrationBuilder.CreateIndex(
                name: "IX_transfers_public_id",
                schema: "warehouse",
                table: "transfers",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_transfers_source_warehouse_id_company_id",
                schema: "warehouse",
                table: "transfers",
                columns: new[] { "source_warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_transfers_target_warehouse_id_company_id",
                schema: "warehouse",
                table: "transfers",
                columns: new[] { "target_warehouse_id", "company_id" });
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropTable(
                name: "disposition_effects",
                schema: "warehouse");

            migrationBuilder.DropTable(
                name: "offline_operations",
                schema: "warehouse");

            migrationBuilder.DropTable(
                name: "operations",
                schema: "warehouse");

            migrationBuilder.DropTable(
                name: "package_items",
                schema: "warehouse");

            migrationBuilder.DropTable(
                name: "pick_works",
                schema: "warehouse");

            migrationBuilder.DropTable(
                name: "scrap_requests",
                schema: "warehouse");

            migrationBuilder.DropTable(
                name: "stage_load_packages",
                schema: "warehouse");

            migrationBuilder.DropTable(
                name: "stock_count_effect_links",
                schema: "warehouse");

            migrationBuilder.DropTable(
                name: "stock_count_observations",
                schema: "warehouse");

            migrationBuilder.DropTable(
                name: "stock_count_scopes",
                schema: "warehouse");

            migrationBuilder.DropTable(
                name: "transfer_effect_links",
                schema: "warehouse");

            migrationBuilder.DropTable(
                name: "packages",
                schema: "warehouse");

            migrationBuilder.DropTable(
                name: "stage_load_works",
                schema: "warehouse");

            migrationBuilder.DropTable(
                name: "stock_count_lines",
                schema: "warehouse");

            migrationBuilder.DropTable(
                name: "transfer_lines",
                schema: "warehouse");

            migrationBuilder.DropTable(
                name: "stock_count_sessions",
                schema: "warehouse");

            migrationBuilder.DropTable(
                name: "transfers",
                schema: "warehouse");
        }
    }
}
