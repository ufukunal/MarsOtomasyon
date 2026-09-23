using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Mars.Infrastructure.Persistence.Parties;

internal sealed class PartyTaxIdentityConfiguration : IEntityTypeConfiguration<PartyTaxIdentityRecord>
{
    public void Configure(EntityTypeBuilder<PartyTaxIdentityRecord> builder)
    {
        builder.ToTable("tax_identities", "parties");
        builder.HasKey(x => x.Id).HasName("pk_tax_identities");

        builder.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        builder.Property(x => x.PublicId).HasColumnName("public_id");
        builder.Property(x => x.PartyId).HasColumnName("party_id");
        builder.Property(x => x.CompanyId).HasColumnName("company_id");
        builder.Property(x => x.Jurisdiction).HasColumnName("jurisdiction").HasMaxLength(8).IsRequired();
        builder.Property(x => x.Scheme).HasColumnName("scheme").HasConversion<string>().HasMaxLength(16).IsRequired();
        builder.Property(x => x.Value).HasColumnName("value").HasMaxLength(32).IsRequired();
        builder.Property(x => x.State).HasColumnName("state").HasConversion<string>().HasMaxLength(16).IsRequired();
        builder.Property(x => x.Version).HasColumnName("version").IsConcurrencyToken();
        builder.Property(x => x.CreatedAt).HasColumnName("created_at");

        builder.HasOne<PartyRecord>()
            .WithMany()
            .HasForeignKey(x => new { x.PartyId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_tax_identities_party_company");

        builder.HasIndex(x => x.PublicId)
            .IsUnique()
            .HasDatabaseName("ux_tax_identities_public_id");

        builder.HasIndex(x => new { x.CompanyId, x.Jurisdiction, x.Scheme, x.Value })
            .IsUnique()
            .HasFilter("\"state\" = 'Active'")
            .HasDatabaseName("ux_tax_identities_active_company_identity");

        builder.ToTable(table =>
        {
            table.HasCheckConstraint(
                "ck_tax_identities_jurisdiction",
                "jurisdiction = 'TR'");
            table.HasCheckConstraint(
                "ck_tax_identities_scheme",
                "scheme IN ('Vkn', 'Tckn')");
            table.HasCheckConstraint(
                "ck_tax_identities_value",
                "(scheme = 'Vkn' AND value ~ '^[0-9]{10}$') OR (scheme = 'Tckn' AND value ~ '^[0-9]{11}$')");
            table.HasCheckConstraint(
                "ck_tax_identities_state",
                "state IN ('Active', 'Inactive')");
            table.HasCheckConstraint(
                "ck_tax_identities_version",
                "version > 0");
        });
    }
}
