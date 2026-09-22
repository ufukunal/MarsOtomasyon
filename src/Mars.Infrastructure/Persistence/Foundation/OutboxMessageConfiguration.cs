using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Mars.Infrastructure.Persistence.Foundation;

internal sealed class OutboxMessageConfiguration : IEntityTypeConfiguration<OutboxMessageRecord>
{
    public void Configure(EntityTypeBuilder<OutboxMessageRecord> builder)
    {
        builder.ToTable("outbox_messages", "foundation");
        builder.HasKey(x => x.Id).HasName("pk_outbox_messages");

        builder.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        builder.Property(x => x.EventId).HasColumnName("event_id").IsRequired();
        builder.Property(x => x.EventType).HasColumnName("event_type").HasMaxLength(300).IsRequired();
        builder.Property(x => x.Module).HasColumnName("module").HasMaxLength(100).IsRequired();
        builder.Property(x => x.AggregatePublicId).HasColumnName("aggregate_public_id");
        builder.Property(x => x.PayloadSchemaVersion).HasColumnName("payload_schema_version").IsRequired();
        builder.Property(x => x.Payload).HasColumnName("payload").HasColumnType("jsonb").IsRequired();
        builder.Property(x => x.State).HasColumnName("state").HasConversion<string>().HasMaxLength(32).IsRequired();
        builder.Property(x => x.CreatedAt).HasColumnName("created_at");
        builder.Property(x => x.AvailableAt).HasColumnName("available_at");
        builder.Property(x => x.ProcessedAt).HasColumnName("processed_at");
        builder.Property(x => x.AttemptCount).HasColumnName("attempt_count");
        builder.Property(x => x.LastError).HasColumnName("last_error").HasMaxLength(512);
        builder.Property(x => x.ClaimToken).HasColumnName("claim_token");
        builder.Property(x => x.ClaimedUntil).HasColumnName("claimed_until");

        builder.HasIndex(x => x.EventId)
            .IsUnique()
            .HasDatabaseName("ux_outbox_event_id");
        builder.HasIndex(x => new { x.State, x.AvailableAt, x.Id })
            .HasDatabaseName("ix_outbox_pending_delivery");

        builder.ToTable(table =>
        {
            table.HasCheckConstraint("ck_outbox_payload_schema_version", "payload_schema_version > 0");
            table.HasCheckConstraint("ck_outbox_attempt_count", "attempt_count >= 0");
        });
    }
}
