using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Mars.Infrastructure.Persistence.Parties;

internal sealed class PartyConfiguration : IEntityTypeConfiguration<PartyRecord>
{
    public void Configure(EntityTypeBuilder<PartyRecord> builder)
    {
        builder.ToTable("parties", "parties");
        builder.HasKey(x => x.Id).HasName("pk_parties");

        builder.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        builder.Property(x => x.PublicId).HasColumnName("public_id");
        builder.Property(x => x.CompanyId).HasColumnName("company_id");
        builder.Property(x => x.PartyCode).HasColumnName("party_code").IsRequired();
        builder.Property(x => x.Kind).HasColumnName("kind").HasConversion<string>().HasMaxLength(32).IsRequired();
        builder.Property(x => x.LegalName).HasColumnName("legal_name").IsRequired();
        builder.Property(x => x.DisplayName).HasColumnName("display_name");
        builder.Property(x => x.State).HasColumnName("state").HasConversion<string>().HasMaxLength(32).IsRequired();
        builder.Property(x => x.Version).HasColumnName("version").IsConcurrencyToken();
        builder.Property(x => x.CreatedAt).HasColumnName("created_at");

        builder.HasAlternateKey(x => new { x.Id, x.CompanyId })
            .HasName("ak_parties_id_company");

        builder.HasIndex(x => x.PublicId)
            .IsUnique()
            .HasDatabaseName("ux_parties_public_id");

        builder.HasIndex(x => new { x.CompanyId, x.PartyCode })
            .IsUnique()
            .HasDatabaseName("ux_parties_company_code");

        builder.ToTable(table =>
        {
            table.HasCheckConstraint(
                "ck_parties_party_code",
                "length(btrim(party_code)) > 0 AND party_code = btrim(party_code)");
            table.HasCheckConstraint(
                "ck_parties_legal_name",
                "length(btrim(legal_name)) > 0 AND legal_name = btrim(legal_name)");
            table.HasCheckConstraint(
                "ck_parties_display_name",
                "display_name IS NULL OR (length(btrim(display_name)) > 0 AND display_name = btrim(display_name))");
            table.HasCheckConstraint(
                "ck_parties_kind",
                "kind IN ('Person', 'Organization')");
            table.HasCheckConstraint(
                "ck_parties_state",
                "state IN ('Active', 'Inactive', 'Merged')");
            table.HasCheckConstraint(
                "ck_parties_version",
                "version > 0");
        });
    }
}
