using System;
using Microsoft.EntityFrameworkCore.Migrations;
using Npgsql.EntityFrameworkCore.PostgreSQL.Metadata;

#nullable disable

namespace Mars.Infrastructure.Persistence.Migrations.Parties
{
    /// <inheritdoc />
    public partial class PartyImp002RoleActivation : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.CreateTable(
                name: "party_roles",
                schema: "parties",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    party_id = table.Column<long>(type: "bigint", nullable: false),
                    role_type = table.Column<string>(type: "character varying(32)", maxLength: 32, nullable: false),
                    state = table.Column<string>(type: "character varying(32)", maxLength: 32, nullable: false),
                    version = table.Column<long>(type: "bigint", nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_party_roles", x => x.id);
                    table.CheckConstraint("ck_party_roles_role_type", "role_type IN ('Customer', 'Supplier')");
                    table.CheckConstraint("ck_party_roles_state", "state IN ('Active', 'Inactive')");
                    table.CheckConstraint("ck_party_roles_version", "version > 0");
                    table.ForeignKey(
                        name: "fk_party_roles_party",
                        column: x => x.party_id,
                        principalSchema: "parties",
                        principalTable: "parties",
                        principalColumn: "id",
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateIndex(
                name: "ux_party_roles_party_role_type",
                schema: "parties",
                table: "party_roles",
                columns: new[] { "party_id", "role_type" },
                unique: true);
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropTable(
                name: "party_roles",
                schema: "parties");
        }
    }
}
