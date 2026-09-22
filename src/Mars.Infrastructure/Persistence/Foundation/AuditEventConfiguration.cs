using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Mars.Infrastructure.Persistence.Foundation;

internal sealed class AuditEventConfiguration : IEntityTypeConfiguration<AuditEventRecord>
{
    public void Configure(EntityTypeBuilder<AuditEventRecord> builder)
    {
        builder.ToTable("audit_events", "foundation");
        builder.HasKey(x => x.Id).HasName("pk_audit_events");

        builder.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        builder.Property(x => x.ActorId).HasColumnName("actor_id");
        builder.Property(x => x.CompanyId).HasColumnName("company_id");
        builder.Property(x => x.BranchId).HasColumnName("branch_id");
        builder.Property(x => x.CorrelationId).HasColumnName("correlation_id").HasMaxLength(128).IsRequired();
        builder.Property(x => x.Module).HasColumnName("module").HasMaxLength(100).IsRequired();
        builder.Property(x => x.Action).HasColumnName("action").HasMaxLength(200).IsRequired();
        builder.Property(x => x.EntityType).HasColumnName("entity_type").HasMaxLength(100);
        builder.Property(x => x.EntityPublicId).HasColumnName("entity_public_id");
        builder.Property(x => x.Reason).HasColumnName("reason").HasMaxLength(1000);
        builder.Property(x => x.OccurredAt).HasColumnName("occurred_at");

        builder.HasIndex(x => new { x.EntityType, x.EntityPublicId, x.OccurredAt })
            .HasDatabaseName("ix_audit_entity_time");
        builder.HasIndex(x => new { x.ActorId, x.OccurredAt })
            .HasDatabaseName("ix_audit_actor_time");
        builder.HasIndex(x => x.CorrelationId)
            .HasDatabaseName("ix_audit_correlation");
    }
}
