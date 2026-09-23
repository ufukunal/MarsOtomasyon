using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Mars.Infrastructure.Persistence.Parties;

internal sealed class PartyRoleConfiguration : IEntityTypeConfiguration<PartyRoleRecord>
{
    public void Configure(EntityTypeBuilder<PartyRoleRecord> builder)
    {
        builder.ToTable("party_roles", "parties");
        builder.HasKey(x => x.Id).HasName("pk_party_roles");

        builder.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        builder.Property(x => x.PartyId).HasColumnName("party_id");
        builder.Property(x => x.RoleType).HasColumnName("role_type").HasConversion<string>().HasMaxLength(32).IsRequired();
        builder.Property(x => x.State).HasColumnName("state").HasConversion<string>().HasMaxLength(32).IsRequired();
        builder.Property(x => x.Version).HasColumnName("version").IsConcurrencyToken();
        builder.Property(x => x.CreatedAt).HasColumnName("created_at");

        builder.HasOne<PartyRecord>()
            .WithMany()
            .HasForeignKey(x => x.PartyId)
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_party_roles_party");

        builder.HasIndex(x => new { x.PartyId, x.RoleType })
            .IsUnique()
            .HasDatabaseName("ux_party_roles_party_role_type");

        builder.ToTable(table =>
        {
            table.HasCheckConstraint(
                "ck_party_roles_role_type",
                "role_type IN ('Customer', 'Supplier')");
            table.HasCheckConstraint(
                "ck_party_roles_state",
                "state IN ('Active', 'Inactive')");
            table.HasCheckConstraint(
                "ck_party_roles_version",
                "version > 0");
        });
    }
}
