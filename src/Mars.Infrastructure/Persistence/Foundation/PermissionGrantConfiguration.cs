using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Mars.Infrastructure.Persistence.Foundation;

internal sealed class PermissionGrantConfiguration : IEntityTypeConfiguration<PermissionGrantRecord>
{
    public void Configure(EntityTypeBuilder<PermissionGrantRecord> builder)
    {
        builder.ToTable("permission_grants", "foundation");
        builder.HasKey(x => x.Id).HasName("pk_permission_grants");

        builder.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        builder.Property(x => x.ActorId).HasColumnName("actor_id");
        builder.Property(x => x.CompanyId).HasColumnName("company_id");
        builder.Property(x => x.PermissionCode).HasColumnName("permission_code").HasMaxLength(200).IsRequired();
        builder.Property(x => x.GrantedAt).HasColumnName("granted_at");
        builder.Property(x => x.GrantedByActorId).HasColumnName("granted_by_actor_id");
        builder.Property(x => x.RevokedAt).HasColumnName("revoked_at");
        builder.Property(x => x.RevokedByActorId).HasColumnName("revoked_by_actor_id");

        builder.HasIndex(x => new { x.ActorId, x.CompanyId, x.PermissionCode })
            .IsUnique()
            .HasDatabaseName("ux_permission_grant_actor_company_code");

        builder.HasIndex(x => new { x.CompanyId, x.PermissionCode, x.RevokedAt })
            .HasDatabaseName("ix_permission_grant_company_code_state");

        builder.ToTable(table =>
        {
            table.HasCheckConstraint(
                "ck_permission_grant_code",
                "length(btrim(permission_code)) > 0 AND permission_code = btrim(permission_code)");
            table.HasCheckConstraint(
                "ck_permission_grant_revocation",
                "(revoked_at IS NULL AND revoked_by_actor_id IS NULL) OR revoked_at IS NOT NULL");
        });
    }
}
