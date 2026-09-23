using System;
using Microsoft.EntityFrameworkCore.Migrations;
using Npgsql.EntityFrameworkCore.PostgreSQL.Metadata;

#nullable disable

namespace Mars.Infrastructure.Persistence.Migrations.Parties
{
    /// <inheritdoc />
    public partial class PartyImp006PartyMasterCompletion : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.CreateTable(
                name: "addresses",
                schema: "parties",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    party_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    purpose = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    country = table.Column<string>(type: "text", nullable: false),
                    city = table.Column<string>(type: "text", nullable: true),
                    district = table.Column<string>(type: "text", nullable: true),
                    postal_code = table.Column<string>(type: "text", nullable: true),
                    line1 = table.Column<string>(type: "text", nullable: true),
                    line2 = table.Column<string>(type: "text", nullable: true),
                    label = table.Column<string>(type: "text", nullable: true),
                    is_default = table.Column<bool>(type: "boolean", nullable: false),
                    state = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_party_addresses", x => x.id);
                    table.CheckConstraint("ck_party_addresses_country", "length(btrim(country)) > 0 AND country = btrim(country)");
                    table.CheckConstraint("ck_party_addresses_purpose", "purpose IN ('Billing', 'Shipping', 'General')");
                    table.CheckConstraint("ck_party_addresses_state", "state IN ('Active', 'Inactive')");
                    table.CheckConstraint("ck_party_addresses_version", "version > 0");
                    table.ForeignKey(
                        name: "fk_party_addresses_party_company",
                        columns: x => new { x.party_id, x.company_id },
                        principalSchema: "parties",
                        principalTable: "parties",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "contacts",
                schema: "parties",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    party_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    name = table.Column<string>(type: "text", nullable: false),
                    title = table.Column<string>(type: "text", nullable: true),
                    purpose = table.Column<string>(type: "text", nullable: true),
                    state = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_party_contacts", x => x.id);
                    table.CheckConstraint("ck_party_contacts_name", "length(btrim(name)) > 0 AND name = btrim(name)");
                    table.CheckConstraint("ck_party_contacts_state", "state IN ('Active', 'Inactive')");
                    table.CheckConstraint("ck_party_contacts_version", "version > 0");
                    table.ForeignKey(
                        name: "fk_party_contacts_party_company",
                        columns: x => new { x.party_id, x.company_id },
                        principalSchema: "parties",
                        principalTable: "parties",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "external_mappings",
                schema: "parties",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    party_id = table.Column<long>(type: "bigint", nullable: false),
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
                    table.PrimaryKey("pk_party_external_mappings", x => x.id);
                    table.CheckConstraint("ck_party_external_mappings_external_identity", "length(btrim(external_identity)) > 0 AND external_identity = btrim(external_identity)");
                    table.CheckConstraint("ck_party_external_mappings_state", "state IN ('Active', 'Inactive')");
                    table.CheckConstraint("ck_party_external_mappings_system", "length(btrim(system_code)) > 0 AND system_code = btrim(system_code)");
                    table.CheckConstraint("ck_party_external_mappings_version", "version > 0");
                    table.ForeignKey(
                        name: "fk_party_external_mappings_party_company",
                        columns: x => new { x.party_id, x.company_id },
                        principalSchema: "parties",
                        principalTable: "parties",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "merge_lineage",
                schema: "parties",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    source_party_id = table.Column<long>(type: "bigint", nullable: false),
                    survivor_party_id = table.Column<long>(type: "bigint", nullable: false),
                    actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    reason = table.Column<string>(type: "text", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_party_merge_lineage", x => x.id);
                    table.CheckConstraint("ck_party_merge_lineage_distinct", "source_party_id <> survivor_party_id");
                    table.CheckConstraint("ck_party_merge_lineage_reason", "length(btrim(reason)) > 0 AND reason = btrim(reason)");
                    table.ForeignKey(
                        name: "fk_party_merge_lineage_source_company",
                        columns: x => new { x.source_party_id, x.company_id },
                        principalSchema: "parties",
                        principalTable: "parties",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                    table.ForeignKey(
                        name: "fk_party_merge_lineage_survivor_company",
                        columns: x => new { x.survivor_party_id, x.company_id },
                        principalSchema: "parties",
                        principalTable: "parties",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateTable(
                name: "communication_points",
                schema: "parties",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    contact_id = table.Column<long>(type: "bigint", nullable: false),
                    type = table.Column<string>(type: "text", nullable: false),
                    value = table.Column<string>(type: "text", nullable: false),
                    purpose = table.Column<string>(type: "text", nullable: true),
                    is_primary = table.Column<bool>(type: "boolean", nullable: false),
                    state = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_party_communication_points", x => x.id);
                    table.CheckConstraint("ck_party_communication_points_state", "state IN ('Active', 'Inactive')");
                    table.CheckConstraint("ck_party_communication_points_type", "length(btrim(type)) > 0 AND type = btrim(type)");
                    table.CheckConstraint("ck_party_communication_points_value", "length(btrim(value)) > 0 AND value = btrim(value)");
                    table.CheckConstraint("ck_party_communication_points_version", "version > 0");
                    table.ForeignKey(
                        name: "fk_party_communication_points_contact",
                        column: x => x.contact_id,
                        principalSchema: "parties",
                        principalTable: "contacts",
                        principalColumn: "id",
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateIndex(
                name: "IX_addresses_party_id_company_id",
                schema: "parties",
                table: "addresses",
                columns: new[] { "party_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_party_addresses_active_default_purpose",
                schema: "parties",
                table: "addresses",
                columns: new[] { "party_id", "purpose" },
                unique: true,
                filter: "\"state\" = 'Active' AND \"is_default\" = TRUE");

            migrationBuilder.CreateIndex(
                name: "ux_party_addresses_public_id",
                schema: "parties",
                table: "addresses",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_communication_points_contact_id",
                schema: "parties",
                table: "communication_points",
                column: "contact_id");

            migrationBuilder.CreateIndex(
                name: "ux_party_communication_points_public_id",
                schema: "parties",
                table: "communication_points",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_contacts_party_id_company_id",
                schema: "parties",
                table: "contacts",
                columns: new[] { "party_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_party_contacts_public_id",
                schema: "parties",
                table: "contacts",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_external_mappings_party_id_company_id",
                schema: "parties",
                table: "external_mappings",
                columns: new[] { "party_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_party_external_mappings_active_scope",
                schema: "parties",
                table: "external_mappings",
                columns: new[] { "company_id", "system_code", "account_scope", "external_identity" },
                unique: true,
                filter: "\"state\" = 'Active'");

            migrationBuilder.CreateIndex(
                name: "ux_party_external_mappings_public_id",
                schema: "parties",
                table: "external_mappings",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_merge_lineage_source_party_id_company_id",
                schema: "parties",
                table: "merge_lineage",
                columns: new[] { "source_party_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "IX_merge_lineage_survivor_party_id_company_id",
                schema: "parties",
                table: "merge_lineage",
                columns: new[] { "survivor_party_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_party_merge_lineage_public_id",
                schema: "parties",
                table: "merge_lineage",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_party_merge_lineage_source",
                schema: "parties",
                table: "merge_lineage",
                column: "source_party_id",
                unique: true);
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropTable(
                name: "addresses",
                schema: "parties");

            migrationBuilder.DropTable(
                name: "communication_points",
                schema: "parties");

            migrationBuilder.DropTable(
                name: "external_mappings",
                schema: "parties");

            migrationBuilder.DropTable(
                name: "merge_lineage",
                schema: "parties");

            migrationBuilder.DropTable(
                name: "contacts",
                schema: "parties");
        }
    }
}
