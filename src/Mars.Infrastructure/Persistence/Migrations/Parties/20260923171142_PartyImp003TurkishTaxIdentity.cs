using System;
using Microsoft.EntityFrameworkCore.Migrations;
using Npgsql.EntityFrameworkCore.PostgreSQL.Metadata;

#nullable disable

namespace Mars.Infrastructure.Persistence.Migrations.Parties
{
    /// <inheritdoc />
    public partial class PartyImp003TurkishTaxIdentity : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.AddUniqueConstraint(
                name: "ak_parties_id_company",
                schema: "parties",
                table: "parties",
                columns: new[] { "id", "company_id" });

            migrationBuilder.CreateTable(
                name: "tax_identities",
                schema: "parties",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    party_id = table.Column<long>(type: "bigint", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    jurisdiction = table.Column<string>(type: "character varying(8)", maxLength: 8, nullable: false),
                    scheme = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    value = table.Column<string>(type: "character varying(32)", maxLength: 32, nullable: false),
                    state = table.Column<string>(type: "character varying(16)", maxLength: 16, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_tax_identities", x => x.id);
                    table.CheckConstraint("ck_tax_identities_jurisdiction", "jurisdiction = 'TR'");
                    table.CheckConstraint("ck_tax_identities_scheme", "scheme IN ('Vkn', 'Tckn')");
                    table.CheckConstraint("ck_tax_identities_state", "state IN ('Active', 'Inactive')");
                    table.CheckConstraint("ck_tax_identities_value", "(scheme = 'Vkn' AND value ~ '^[0-9]{10}$') OR (scheme = 'Tckn' AND value ~ '^[0-9]{11}$')");
                    table.CheckConstraint("ck_tax_identities_version", "version > 0");
                    table.ForeignKey(
                        name: "fk_tax_identities_party_company",
                        columns: x => new { x.party_id, x.company_id },
                        principalSchema: "parties",
                        principalTable: "parties",
                        principalColumns: new[] { "id", "company_id" },
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateIndex(
                name: "IX_tax_identities_party_id_company_id",
                schema: "parties",
                table: "tax_identities",
                columns: new[] { "party_id", "company_id" });

            migrationBuilder.CreateIndex(
                name: "ux_tax_identities_active_company_identity",
                schema: "parties",
                table: "tax_identities",
                columns: new[] { "company_id", "jurisdiction", "scheme", "value" },
                unique: true,
                filter: "\"state\" = 'Active'");

            migrationBuilder.CreateIndex(
                name: "ux_tax_identities_public_id",
                schema: "parties",
                table: "tax_identities",
                column: "public_id",
                unique: true);
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropTable(
                name: "tax_identities",
                schema: "parties");

            migrationBuilder.DropUniqueConstraint(
                name: "ak_parties_id_company",
                schema: "parties",
                table: "parties");
        }
    }
}
