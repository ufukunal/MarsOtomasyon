using Mars.Application.Foundation.Approvals;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Mars.Infrastructure.Persistence.Foundation;

internal sealed class ApprovalDecisionConfiguration
    : IEntityTypeConfiguration<ApprovalDecisionRecord>
{
    public void Configure(EntityTypeBuilder<ApprovalDecisionRecord> b)
    {
        b.ToTable("approval_decisions", "foundation");
        b.HasKey(x => x.Id).HasName("pk_foundation_approval_decisions");
        b.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        b.Property(x => x.PublicId).HasColumnName("public_id");
        b.Property(x => x.CompanyId).HasColumnName("company_id");
        b.Property(x => x.Module).HasColumnName("module").HasMaxLength(64).IsRequired();
        b.Property(x => x.EntityType).HasColumnName("entity_type").HasMaxLength(96).IsRequired();
        b.Property(x => x.EntityPublicId).HasColumnName("entity_public_id");
        b.Property(x => x.SnapshotVersion).HasColumnName("snapshot_version");
        b.Property(x => x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x => x.DeciderActorId).HasColumnName("decider_actor_id");
        b.Property(x => x.Decision).HasColumnName("decision").HasConversion<string>().HasMaxLength(16).IsRequired();
        b.Property(x => x.Reason).HasColumnName("reason");
        b.Property(x => x.DecidedAt).HasColumnName("decided_at");

        b.HasIndex(x => x.PublicId).IsUnique().HasDatabaseName("ux_foundation_approval_decisions_public_id");
        b.HasIndex(x => new
            {
                x.CompanyId,
                x.Module,
                x.EntityType,
                x.EntityPublicId,
                x.SnapshotVersion
            })
            .HasDatabaseName("ix_foundation_approval_decisions_snapshot");
        b.HasIndex(x => new
            {
                x.CompanyId,
                x.Module,
                x.EntityType,
                x.EntityPublicId,
                x.SnapshotVersion,
                x.Decision
            })
            .IsUnique()
            .HasFilter("\"decision\" = 'Approved'")
            .HasDatabaseName("ux_foundation_approval_decisions_approved_snapshot");

        b.ToTable(t =>
        {
            t.HasCheckConstraint("ck_foundation_approval_decisions_snapshot", "snapshot_version > 0");
            t.HasCheckConstraint("ck_foundation_approval_decisions_module", "length(btrim(module)) > 0 AND module = btrim(module)");
            t.HasCheckConstraint("ck_foundation_approval_decisions_entity_type", "length(btrim(entity_type)) > 0 AND entity_type = btrim(entity_type)");
            t.HasCheckConstraint("ck_foundation_approval_decisions_decision", "decision IN ('Approved', 'Rejected')");
            t.HasCheckConstraint(
                "ck_foundation_approval_decisions_sod",
                "decision <> 'Approved' OR creator_actor_id <> decider_actor_id");
        });
    }
}
