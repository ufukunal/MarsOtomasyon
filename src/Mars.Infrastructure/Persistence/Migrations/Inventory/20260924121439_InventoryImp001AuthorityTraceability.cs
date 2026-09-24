using System;
using Microsoft.EntityFrameworkCore.Migrations;
using Npgsql.EntityFrameworkCore.PostgreSQL.Metadata;

#nullable disable

#pragma warning disable CA1814 // Prefer jagged arrays over multidimensional

namespace Mars.Infrastructure.Persistence.Migrations.Inventory
{
    /// <inheritdoc />
    public partial class InventoryImp001AuthorityTraceability : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.EnsureSchema(
                name: "inventory");

            migrationBuilder.CreateTable(
                name: "dispositions",
                schema: "inventory",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false),
                    code = table.Column<string>(type: "character varying(32)", maxLength: 32, nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_inventory_dispositions", x => x.id);
                    table.CheckConstraint("ck_inventory_dispositions_code", "code IN ('Available','Quarantine','QualityHold','Rework','Damaged','Transit')");
                });

            migrationBuilder.CreateTable(
                name: "lots",
                schema: "inventory",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    product_id = table.Column<long>(type: "bigint", nullable: false),
                    variant_id = table.Column<long>(type: "bigint", nullable: true),
                    code = table.Column<string>(type: "text", nullable: false),
                    manufacture_date = table.Column<DateOnly>(type: "date", nullable: true),
                    expiry_date = table.Column<DateOnly>(type: "date", nullable: true),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_inventory_lots", x => x.id);
                    table.UniqueConstraint("ak_inventory_lots_id_company", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_inventory_lots_code", "length(btrim(code)) > 0 AND code = btrim(code)");
                    table.CheckConstraint("ck_inventory_lots_dates", "manufacture_date IS NULL OR expiry_date IS NULL OR expiry_date >= manufacture_date");
                    table.CheckConstraint("ck_inventory_lots_version", "version > 0");
                    table.ForeignKey(
                        name: "fk_inventory_lots_product_company",
                        columns: x => new { x.product_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "products",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_inventory_lots_variant_company",
                        columns: x => new { x.variant_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "variants",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "warehouses",
                schema: "inventory",
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
                    table.PrimaryKey("pk_inventory_warehouses", x => x.id);
                    table.UniqueConstraint("ak_inventory_warehouses_id_company", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_inventory_warehouses_code", "length(btrim(code)) > 0 AND code = btrim(code)");
                    table.CheckConstraint("ck_inventory_warehouses_name", "length(btrim(name)) > 0 AND name = btrim(name)");
                    table.CheckConstraint("ck_inventory_warehouses_state", "state IN ('Active','Inactive')");
                    table.CheckConstraint("ck_inventory_warehouses_version", "version > 0");
                });

            migrationBuilder.CreateTable(
                name: "serials",
                schema: "inventory",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    product_id = table.Column<long>(type: "bigint", nullable: false),
                    variant_id = table.Column<long>(type: "bigint", nullable: true),
                    lot_id = table.Column<long>(type: "bigint", nullable: true),
                    value = table.Column<string>(type: "text", nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_inventory_serials", x => x.id);
                    table.UniqueConstraint("ak_inventory_serials_id_company", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_inventory_serials_value", "length(btrim(value)) > 0 AND value = btrim(value)");
                    table.CheckConstraint("ck_inventory_serials_version", "version > 0");
                    table.ForeignKey(
                        name: "fk_inventory_serials_lot_company",
                        columns: x => new { x.lot_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "lots",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_inventory_serials_product_company",
                        columns: x => new { x.product_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "products",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_inventory_serials_variant_company",
                        columns: x => new { x.variant_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "variants",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "locations",
                schema: "inventory",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    warehouse_id = table.Column<long>(type: "bigint", nullable: false),
                    parent_location_id = table.Column<long>(type: "bigint", nullable: true),
                    code = table.Column<string>(type: "text", nullable: false),
                    name = table.Column<string>(type: "text", nullable: false),
                    stock_bearing = table.Column<bool>(type: "boolean", nullable: false),
                    state = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_inventory_locations", x => x.id);
                    table.UniqueConstraint("ak_inventory_locations_id_company", x => new { x.id, x.company_id });
                    table.UniqueConstraint("ak_inventory_locations_id_warehouse_company", x => new { x.id, x.warehouse_id, x.company_id });
                    table.CheckConstraint("ck_inventory_locations_code", "length(btrim(code)) > 0 AND code = btrim(code)");
                    table.CheckConstraint("ck_inventory_locations_name", "length(btrim(name)) > 0 AND name = btrim(name)");
                    table.CheckConstraint("ck_inventory_locations_parent", "parent_location_id IS NULL OR parent_location_id <> id");
                    table.CheckConstraint("ck_inventory_locations_state", "state IN ('Active','Inactive')");
                    table.CheckConstraint("ck_inventory_locations_version", "version > 0");
                    table.ForeignKey(
                        name: "fk_inventory_locations_parent_warehouse_company",
                        columns: x => new { x.parent_location_id, x.warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "locations",
                        principalColumns: new[] { "id", "warehouse_id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_inventory_locations_warehouse_company",
                        columns: x => new { x.warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "warehouses",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "reservations",
                schema: "inventory",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    sales_order_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    sales_order_version = table.Column<long>(type: "bigint", nullable: false),
                    sales_order_line_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    product_id = table.Column<long>(type: "bigint", nullable: false),
                    variant_id = table.Column<long>(type: "bigint", nullable: true),
                    warehouse_id = table.Column<long>(type: "bigint", nullable: false),
                    uom_id = table.Column<long>(type: "bigint", nullable: false),
                    conversion_factor_snapshot = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_inventory_reservations", x => x.id);
                    table.UniqueConstraint("ak_inventory_reservations_id_company", x => new { x.id, x.company_id });
                    table.CheckConstraint("ck_inventory_reservations_conversion", "conversion_factor_snapshot > 0");
                    table.CheckConstraint("ck_inventory_reservations_sales_version", "sales_order_version > 0");
                    table.ForeignKey(
                        name: "fk_inventory_reservations_product_company",
                        columns: x => new { x.product_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "products",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_inventory_reservations_uom_company",
                        columns: x => new { x.uom_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "uoms",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_inventory_reservations_variant_company",
                        columns: x => new { x.variant_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "variants",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_inventory_reservations_warehouse_company",
                        columns: x => new { x.warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "warehouses",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "movements",
                schema: "inventory",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    product_id = table.Column<long>(type: "bigint", nullable: false),
                    variant_id = table.Column<long>(type: "bigint", nullable: true),
                    uom_id = table.Column<long>(type: "bigint", nullable: false),
                    entered_quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    conversion_factor_snapshot = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    base_quantity = table.Column<decimal>(type: "numeric(38,18)", precision: 38, scale: 18, nullable: false),
                    source_warehouse_id = table.Column<long>(type: "bigint", nullable: true),
                    source_location_id = table.Column<long>(type: "bigint", nullable: true),
                    source_disposition_id = table.Column<long>(type: "bigint", nullable: true),
                    target_warehouse_id = table.Column<long>(type: "bigint", nullable: true),
                    target_location_id = table.Column<long>(type: "bigint", nullable: true),
                    target_disposition_id = table.Column<long>(type: "bigint", nullable: true),
                    lot_id = table.Column<long>(type: "bigint", nullable: true),
                    serial_id = table.Column<long>(type: "bigint", nullable: true),
                    source_module = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: false),
                    source_entity_type = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: false),
                    source_document_public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    source_line_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    posted_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    correlation_id = table.Column<string>(type: "character varying(128)", maxLength: 128, nullable: false),
                    reversal_of_movement_id = table.Column<long>(type: "bigint", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_inventory_movements", x => x.id);
                    table.CheckConstraint("ck_inventory_movements_base_quantity", "base_quantity > 0");
                    table.CheckConstraint("ck_inventory_movements_conversion", "conversion_factor_snapshot > 0");
                    table.CheckConstraint("ck_inventory_movements_correlation", "length(btrim(correlation_id)) > 0 AND correlation_id = btrim(correlation_id)");
                    table.CheckConstraint("ck_inventory_movements_entered_quantity", "entered_quantity > 0");
                    table.CheckConstraint("ck_inventory_movements_has_side", "source_warehouse_id IS NOT NULL OR target_warehouse_id IS NOT NULL");
                    table.CheckConstraint("ck_inventory_movements_source_module", "length(btrim(source_module)) > 0 AND source_module = btrim(source_module)");
                    table.CheckConstraint("ck_inventory_movements_source_side", "(source_warehouse_id IS NULL AND source_location_id IS NULL AND source_disposition_id IS NULL) OR (source_warehouse_id IS NOT NULL AND source_disposition_id IS NOT NULL)");
                    table.CheckConstraint("ck_inventory_movements_source_type", "length(btrim(source_entity_type)) > 0 AND source_entity_type = btrim(source_entity_type)");
                    table.CheckConstraint("ck_inventory_movements_target_side", "(target_warehouse_id IS NULL AND target_location_id IS NULL AND target_disposition_id IS NULL) OR (target_warehouse_id IS NOT NULL AND target_disposition_id IS NOT NULL)");
                    table.ForeignKey(
                        name: "fk_inventory_movements_lot_company",
                        columns: x => new { x.lot_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "lots",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_inventory_movements_product_company",
                        columns: x => new { x.product_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "products",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_inventory_movements_reversal_original",
                        column: x => x.reversal_of_movement_id,
                        principalSchema: "inventory",
                        principalTable: "movements",
                        principalColumn: "id",
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_inventory_movements_serial_company",
                        columns: x => new { x.serial_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "serials",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_inventory_movements_source_disposition",
                        column: x => x.source_disposition_id,
                        principalSchema: "inventory",
                        principalTable: "dispositions",
                        principalColumn: "id",
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_inventory_movements_source_location",
                        columns: x => new { x.source_location_id, x.source_warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "locations",
                        principalColumns: new[] { "id", "warehouse_id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_inventory_movements_source_warehouse_company",
                        columns: x => new { x.source_warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "warehouses",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_inventory_movements_target_disposition",
                        column: x => x.target_disposition_id,
                        principalSchema: "inventory",
                        principalTable: "dispositions",
                        principalColumn: "id",
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_inventory_movements_target_location",
                        columns: x => new { x.target_location_id, x.target_warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "locations",
                        principalColumns: new[] { "id", "warehouse_id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_inventory_movements_target_warehouse_company",
                        columns: x => new { x.target_warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "warehouses",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_inventory_movements_uom_company",
                        columns: x => new { x.uom_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "uoms",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_inventory_movements_variant_company",
                        columns: x => new { x.variant_id, x.company_id },
                        principalSchema: "products",
                        principalTable: "variants",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "reservation_movements",
                schema: "inventory",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    reservation_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    kind = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    entered_quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    base_quantity = table.Column<decimal>(type: "numeric(38,18)", precision: 38, scale: 18, nullable: false),
                    source_module = table.Column<string>(type: "character varying(64)", maxLength: 64, nullable: true),
                    source_document_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    source_line_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    correlation_id = table.Column<string>(type: "character varying(128)", maxLength: 128, nullable: false),
                    occurred_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_inventory_reservation_movements", x => x.id);
                    table.CheckConstraint("ck_inventory_reservation_movements_base_quantity", "base_quantity > 0");
                    table.CheckConstraint("ck_inventory_reservation_movements_correlation", "length(btrim(correlation_id)) > 0 AND correlation_id = btrim(correlation_id)");
                    table.CheckConstraint("ck_inventory_reservation_movements_entered_quantity", "entered_quantity > 0");
                    table.CheckConstraint("ck_inventory_reservation_movements_kind", "kind IN ('Create','Increase','Release','Consume')");
                    table.ForeignKey(
                        name: "fk_inventory_reservation_movements_reservation_company",
                        columns: x => new { x.reservation_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "reservations",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.InsertData(
                schema: "inventory",
                table: "dispositions",
                columns: new[] { "id", "code" },
                values: new object[,]
                {
                    { 1L, "Available" },
                    { 2L, "Quarantine" },
                    { 3L, "QualityHold" },
                    { 4L, "Rework" },
                    { 5L, "Damaged" },
                    { 6L, "Transit" }
                });

            migrationBuilder.CreateIndex(
                name: "ux_inventory_dispositions_code",
                schema: "inventory",
                table: "dispositions",
                column: "code",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_locations_parent_location_id_warehouse_id_company_id",
                schema: "inventory",
                table: "locations",
                columns: new[] { "parent_location_id", "warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_locations_warehouse_id_company_id",
                schema: "inventory",
                table: "locations",
                columns: new[] { "warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_inventory_locations_public_id",
                schema: "inventory",
                table: "locations",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_inventory_locations_warehouse_code",
                schema: "inventory",
                table: "locations",
                columns: new[] { "warehouse_id", "code" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_lots_product_id_company_id",
                schema: "inventory",
                table: "lots",
                columns: new[] { "product_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_lots_variant_id_company_id",
                schema: "inventory",
                table: "lots",
                columns: new[] { "variant_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_inventory_lots_product_code",
                schema: "inventory",
                table: "lots",
                columns: new[] { "company_id", "product_id", "code" },
                unique: true,
                filter: "variant_id IS NULL");

            migrationBuilder.CreateIndex(
                name: "ux_inventory_lots_public_id",
                schema: "inventory",
                table: "lots",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_inventory_lots_variant_code",
                schema: "inventory",
                table: "lots",
                columns: new[] { "company_id", "product_id", "variant_id", "code" },
                unique: true,
                filter: "variant_id IS NOT NULL");

            migrationBuilder.CreateIndex(
                name: "ix_inventory_movements_product_posted",
                schema: "inventory",
                table: "movements",
                columns: new[] { "company_id", "product_id", "variant_id", "posted_at" });

            migrationBuilder.CreateIndex(
                name: "ix_inventory_movements_source",
                schema: "inventory",
                table: "movements",
                columns: new[] { "company_id", "source_document_public_id", "source_line_public_id" });

            migrationBuilder.CreateIndex(
                name: "IX_movements_lot_id_company_id",
                schema: "inventory",
                table: "movements",
                columns: new[] { "lot_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_movements_product_id_company_id",
                schema: "inventory",
                table: "movements",
                columns: new[] { "product_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_movements_serial_id_company_id",
                schema: "inventory",
                table: "movements",
                columns: new[] { "serial_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_movements_source_disposition_id",
                schema: "inventory",
                table: "movements",
                column: "source_disposition_id");

            migrationBuilder.CreateIndex(
                name: "IX_movements_source_location_id_source_warehouse_id_company_id",
                schema: "inventory",
                table: "movements",
                columns: new[] { "source_location_id", "source_warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_movements_source_warehouse_id_company_id",
                schema: "inventory",
                table: "movements",
                columns: new[] { "source_warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_movements_target_disposition_id",
                schema: "inventory",
                table: "movements",
                column: "target_disposition_id");

            migrationBuilder.CreateIndex(
                name: "IX_movements_target_location_id_target_warehouse_id_company_id",
                schema: "inventory",
                table: "movements",
                columns: new[] { "target_location_id", "target_warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_movements_target_warehouse_id_company_id",
                schema: "inventory",
                table: "movements",
                columns: new[] { "target_warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_movements_uom_id_company_id",
                schema: "inventory",
                table: "movements",
                columns: new[] { "uom_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_movements_variant_id_company_id",
                schema: "inventory",
                table: "movements",
                columns: new[] { "variant_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_inventory_movements_public_id",
                schema: "inventory",
                table: "movements",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_inventory_movements_reversal",
                schema: "inventory",
                table: "movements",
                column: "reversal_of_movement_id",
                unique: true,
                filter: "reversal_of_movement_id IS NOT NULL");

            migrationBuilder.CreateIndex(
                name: "ix_inventory_reservation_movements_history",
                schema: "inventory",
                table: "reservation_movements",
                columns: new[] { "reservation_id", "occurred_at" });

            migrationBuilder.CreateIndex(
                name: "IX_reservation_movements_reservation_id_company_id",
                schema: "inventory",
                table: "reservation_movements",
                columns: new[] { "reservation_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_inventory_reservation_movements_public_id",
                schema: "inventory",
                table: "reservation_movements",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ix_inventory_reservations_eligibility",
                schema: "inventory",
                table: "reservations",
                columns: new[] { "company_id", "product_id", "variant_id", "warehouse_id" });

            migrationBuilder.CreateIndex(
                name: "ix_inventory_reservations_sales_source",
                schema: "inventory",
                table: "reservations",
                columns: new[] { "company_id", "sales_order_public_id", "sales_order_version", "sales_order_line_public_id" });

            migrationBuilder.CreateIndex(
                name: "IX_reservations_product_id_company_id",
                schema: "inventory",
                table: "reservations",
                columns: new[] { "product_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_reservations_uom_id_company_id",
                schema: "inventory",
                table: "reservations",
                columns: new[] { "uom_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_reservations_variant_id_company_id",
                schema: "inventory",
                table: "reservations",
                columns: new[] { "variant_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_reservations_warehouse_id_company_id",
                schema: "inventory",
                table: "reservations",
                columns: new[] { "warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_inventory_reservations_public_id",
                schema: "inventory",
                table: "reservations",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_serials_lot_id_company_id",
                schema: "inventory",
                table: "serials",
                columns: new[] { "lot_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_serials_product_id_company_id",
                schema: "inventory",
                table: "serials",
                columns: new[] { "product_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_serials_variant_id_company_id",
                schema: "inventory",
                table: "serials",
                columns: new[] { "variant_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_inventory_serials_product_value",
                schema: "inventory",
                table: "serials",
                columns: new[] { "company_id", "product_id", "value" },
                unique: true,
                filter: "variant_id IS NULL");

            migrationBuilder.CreateIndex(
                name: "ux_inventory_serials_public_id",
                schema: "inventory",
                table: "serials",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_inventory_serials_variant_value",
                schema: "inventory",
                table: "serials",
                columns: new[] { "company_id", "product_id", "variant_id", "value" },
                unique: true,
                filter: "variant_id IS NOT NULL");

            migrationBuilder.CreateIndex(
                name: "ux_inventory_warehouses_company_code",
                schema: "inventory",
                table: "warehouses",
                columns: new[] { "company_id", "code" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_inventory_warehouses_public_id",
                schema: "inventory",
                table: "warehouses",
                column: "public_id",
                unique: true);
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropTable(
                name: "movements",
                schema: "inventory");

            migrationBuilder.DropTable(
                name: "reservation_movements",
                schema: "inventory");

            migrationBuilder.DropTable(
                name: "serials",
                schema: "inventory");

            migrationBuilder.DropTable(
                name: "dispositions",
                schema: "inventory");

            migrationBuilder.DropTable(
                name: "locations",
                schema: "inventory");

            migrationBuilder.DropTable(
                name: "reservations",
                schema: "inventory");

            migrationBuilder.DropTable(
                name: "lots",
                schema: "inventory");

            migrationBuilder.DropTable(
                name: "warehouses",
                schema: "inventory");
        }
    }
}
