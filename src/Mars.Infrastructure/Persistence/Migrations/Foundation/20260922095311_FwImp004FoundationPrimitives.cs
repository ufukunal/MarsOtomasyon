using System;
using Microsoft.EntityFrameworkCore.Migrations;
using Npgsql.EntityFrameworkCore.PostgreSQL.Metadata;

#nullable disable

namespace Mars.Infrastructure.Persistence.Migrations.Foundation
{
    /// <inheritdoc />
    public partial class FwImp004FoundationPrimitives : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.EnsureSchema(
                name: "foundation");

            migrationBuilder.CreateTable(
                name: "audit_events",
                schema: "foundation",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    actor_id = table.Column<Guid>(type: "uuid", nullable: false),
                    company_id = table.Column<Guid>(type: "uuid", nullable: false),
                    branch_id = table.Column<Guid>(type: "uuid", nullable: true),
                    correlation_id = table.Column<string>(type: "character varying(128)", maxLength: 128, nullable: false),
                    module = table.Column<string>(type: "character varying(100)", maxLength: 100, nullable: false),
                    action = table.Column<string>(type: "character varying(200)", maxLength: 200, nullable: false),
                    entity_type = table.Column<string>(type: "character varying(100)", maxLength: 100, nullable: true),
                    entity_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    reason = table.Column<string>(type: "character varying(1000)", maxLength: 1000, nullable: true),
                    occurred_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_audit_events", x => x.id);
                });

            migrationBuilder.CreateTable(
                name: "idempotency_operations",
                schema: "foundation",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    scope = table.Column<string>(type: "character varying(200)", maxLength: 200, nullable: false),
                    operation_key = table.Column<string>(type: "character varying(200)", maxLength: 200, nullable: false),
                    request_fingerprint = table.Column<string>(type: "character varying(128)", maxLength: 128, nullable: true),
                    status = table.Column<string>(type: "character varying(32)", maxLength: 32, nullable: false),
                    result_code = table.Column<string>(type: "character varying(200)", maxLength: 200, nullable: true),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    completed_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_idempotency_operations", x => x.id);
                    table.CheckConstraint("ck_idempotency_completed_state", "completed_at IS NULL OR status IN ('Succeeded', 'Failed')");
                });

            migrationBuilder.CreateTable(
                name: "outbox_messages",
                schema: "foundation",
                columns: table => new
                {
                    id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("Npgsql:ValueGenerationStrategy", NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                    event_id = table.Column<Guid>(type: "uuid", nullable: false),
                    event_type = table.Column<string>(type: "character varying(300)", maxLength: 300, nullable: false),
                    module = table.Column<string>(type: "character varying(100)", maxLength: 100, nullable: false),
                    aggregate_public_id = table.Column<Guid>(type: "uuid", nullable: true),
                    payload_schema_version = table.Column<int>(type: "integer", nullable: false),
                    payload = table.Column<string>(type: "jsonb", nullable: false),
                    state = table.Column<string>(type: "character varying(32)", maxLength: 32, nullable: false),
                    created_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    available_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: false),
                    processed_at = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true),
                    attempt_count = table.Column<int>(type: "integer", nullable: false),
                    last_error = table.Column<string>(type: "character varying(512)", maxLength: 512, nullable: true),
                    claim_token = table.Column<Guid>(type: "uuid", nullable: true),
                    claimed_until = table.Column<DateTimeOffset>(type: "timestamp with time zone", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("pk_outbox_messages", x => x.id);
                    table.CheckConstraint("ck_outbox_attempt_count", "attempt_count >= 0");
                    table.CheckConstraint("ck_outbox_payload_schema_version", "payload_schema_version > 0");
                });

            migrationBuilder.CreateIndex(
                name: "ix_audit_actor_time",
                schema: "foundation",
                table: "audit_events",
                columns: new[] { "actor_id", "occurred_at" });

            migrationBuilder.CreateIndex(
                name: "ix_audit_correlation",
                schema: "foundation",
                table: "audit_events",
                column: "correlation_id");

            migrationBuilder.CreateIndex(
                name: "ix_audit_entity_time",
                schema: "foundation",
                table: "audit_events",
                columns: new[] { "entity_type", "entity_public_id", "occurred_at" });

            migrationBuilder.CreateIndex(
                name: "ux_idempotency_scope_key",
                schema: "foundation",
                table: "idempotency_operations",
                columns: new[] { "scope", "operation_key" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "ix_outbox_pending_delivery",
                schema: "foundation",
                table: "outbox_messages",
                columns: new[] { "state", "available_at", "id" });

            migrationBuilder.CreateIndex(
                name: "ux_outbox_event_id",
                schema: "foundation",
                table: "outbox_messages",
                column: "event_id",
                unique: true);
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropTable(
                name: "audit_events",
                schema: "foundation");

            migrationBuilder.DropTable(
                name: "idempotency_operations",
                schema: "foundation");

            migrationBuilder.DropTable(
                name: "outbox_messages",
                schema: "foundation");
        }
    }
}
