using System;
using Microsoft.EntityFrameworkCore.Migrations;
using Npgsql.EntityFrameworkCore.PostgreSQL.Metadata;

#nullable disable

namespace Mars.Infrastructure.Persistence.Migrations.Sales
{
    /// <inheritdoc />
    public partial class WarehouseImp001SalesDispatchSourceAllocations : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.CreateTable(
                name: "dispatch_source_allocations",
                schema: "sales",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    dispatch_line_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    warehouse_id = table.Column<long>(type: "bigint", nullable: false),
                    location_id = table.Column<long>(type: "bigint", nullable: false),
                    lot_id = table.Column<long>(type: "bigint", nullable: true),
                    serial_id = table.Column<long>(type: "bigint", nullable: true),
                    quantity = table.Column<decimal>(type: "numeric(28,9)", precision: 28, scale: 9, nullable: false),
                    creator_actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_dispatch_source_allocations", x => x.id);
                    table.CheckConstraint("ck_sales_dispatch_allocations_quantity", "quantity > 0");
                    table.ForeignKey(
                        name: "fk_sales_dispatch_allocations_line_company",
                        columns: x => new { x.dispatch_line_id, x.company_id },
                        principalSchema: "sales",
                        principalTable: "dispatch_lines",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_sales_dispatch_allocations_location_company",
                        columns: x => new { x.location_id, x.warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "locations",
                        principalColumns: new[] { "id", "warehouse_id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_sales_dispatch_allocations_lot_company",
                        columns: x => new { x.lot_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "lots",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_sales_dispatch_allocations_serial_company",
                        columns: x => new { x.serial_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "serials",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_sales_dispatch_allocations_warehouse_company",
                        columns: x => new { x.warehouse_id, x.company_id },
                        principalSchema: "inventory",
                        principalTable: "warehouses",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_source_allocations_dispatch_line_id_company_id",
                schema: "sales",
                table: "dispatch_source_allocations",
                columns: new[] { "dispatch_line_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_source_allocations_location_id_warehouse_id_compan~",
                schema: "sales",
                table: "dispatch_source_allocations",
                columns: new[] { "location_id", "warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_source_allocations_lot_id_company_id",
                schema: "sales",
                table: "dispatch_source_allocations",
                columns: new[] { "lot_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_source_allocations_public_id",
                schema: "sales",
                table: "dispatch_source_allocations",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_source_allocations_serial_id_company_id",
                schema: "sales",
                table: "dispatch_source_allocations",
                columns: new[] { "serial_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_dispatch_source_allocations_warehouse_id_company_id",
                schema: "sales",
                table: "dispatch_source_allocations",
                columns: new[] { "warehouse_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ix_sales_dispatch_allocations_line",
                schema: "sales",
                table: "dispatch_source_allocations",
                columns: new[] { "dispatch_line_id", "created_at" });
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropTable(
                name: "dispatch_source_allocations",
                schema: "sales");
        }
    }
}
