using System;
using Microsoft.EntityFrameworkCore.Migrations;
using Npgsql.EntityFrameworkCore.PostgreSQL.Metadata;

#nullable disable

namespace Mars.Infrastructure.Persistence.Migrations.Parties
{
    /// <inheritdoc />
    public partial class PartyImp001CoreIdentity : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.EnsureSchema(
                name: "parties");

            migrationBuilder.CreateTable(
                name: "parties",
                schema: "parties",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    public_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    party_code = table.Column<string>(type: "text", nullable: false),
                    kind = table.Column<string>(type: "character varying(32)", maxLength: 32, nullable: false),
                    legal_name = table.Column<string>(type: "text", nullable: false),
                    display_name = table.Column<string>(type: "text", nullable: true),
                    state = table.Column<string>(type: "character varying(32)", maxLength: 32, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_parties", x => x.id);
                    table.CheckConstraint("ck_parties_display_name", "display_name IS NULL OR (length(btrim(display_name)) > 0 AND display_name = btrim(display_name))");
                    table.CheckConstraint("ck_parties_kind", "kind IN ('Person', 'Organization')");
                    table.CheckConstraint("ck_parties_legal_name", "length(btrim(legal_name)) > 0 AND legal_name = btrim(legal_name)");
                    table.CheckConstraint("ck_parties_party_code", "length(btrim(party_code)) > 0 AND party_code = btrim(party_code)");
                    table.CheckConstraint("ck_parties_state", "state IN ('Active', 'Inactive', 'Merged')");
                    table.CheckConstraint("ck_parties_version", "version > 0");
                });

            migrationBuilder.CreateTable(
                name: "permission_grants",
                schema: "foundation",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    permission_code = table.Column<string>(type: "character varying(200)", maxLength: 200, nullable: false),
                    granted_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    granted_by_actor_id = table.Column<Guid>(type: "uuid", nullable: true),
                    revoked_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true),
                    revoked_by_actor_id = table.Column<Guid>(type: "uuid", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_permission_grants", x => x.id);
                    table.CheckConstraint("ck_permission_grant_code", "length(btrim(permission_code)) > 0 AND permission_code = btrim(permission_code)");
                    table.CheckConstraint("ck_permission_grant_revocation", "(revoked_at IS NULL AND revoked_by_actor_id IS NULL) OR revoked_at IS NOT NULL");
                });

            migrationBuilder.CreateIndex(
                name: "ux_parties_company_code",
                schema: "parties",
                table: "parties",
                columns: new[] { "company_id", "party_code" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ux_parties_public_id",
                schema: "parties",
                table: "parties",
                column: "public_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ix_permission_grant_company_code_state",
                schema: "foundation",
                table: "permission_grants",
                columns: new[] { "company_id", "permission_code", "revoked_at" });

            migrationBuilder.CreateIndex(
                name: "ux_permission_grant_actor_company_code",
                schema: "foundation",
                table: "permission_grants",
                columns: new[] { "actor_id", "company_id", "permission_code" },
                unique: true);
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropTable(
                name: "parties",
                schema: "parties");

            migrationBuilder.DropTable(
                name: "permission_grants",
                schema: "foundation");
        }
    }
}
