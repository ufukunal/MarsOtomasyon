using Mars.Domain.Parties;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Mars.Infrastructure.Persistence.Parties;

internal sealed class PartyContactConfiguration : IEntityTypeConfiguration<PartyContactRecord>
{
    public void Configure(EntityTypeBuilder<PartyContactRecord> builder)
    {
        builder.ToTable("contacts", "parties");
        builder.HasKey(x => x.Id).HasName("pk_party_contacts");
        builder.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        builder.Property(x => x.PublicId).HasColumnName("public_id");
        builder.Property(x => x.PartyId).HasColumnName("party_id");
        builder.Property(x => x.CompanyId).HasColumnName("company_id");
        builder.Property(x => x.Name).HasColumnName("name").IsRequired();
        builder.Property(x => x.Title).HasColumnName("title");
        builder.Property(x => x.Purpose).HasColumnName("purpose");
        builder.Property(x => x.State).HasColumnName("state").HasConversion<string>().HasMaxLength(16).IsRequired();
        builder.Property(x => x.Version).HasColumnName("version").IsConcurrencyToken();
        builder.Property(x => x.CreatedAt).HasColumnName("created_at");

        builder.HasOne<PartyRecord>()
            .WithMany()
            .HasForeignKey(x => new { x.PartyId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_party_contacts_party_company");

        builder.HasIndex(x => x.PublicId).IsUnique().HasDatabaseName("ux_party_contacts_public_id");

        builder.ToTable(table =>
        {
            table.HasCheckConstraint("ck_party_contacts_name", "length(btrim(name)) > 0 AND name = btrim(name)");
            table.HasCheckConstraint("ck_party_contacts_state", "state IN ('Active', 'Inactive')");
            table.HasCheckConstraint("ck_party_contacts_version", "version > 0");
        });
    }
}

internal sealed class PartyCommunicationPointConfiguration : IEntityTypeConfiguration<PartyCommunicationPointRecord>
{
    public void Configure(EntityTypeBuilder<PartyCommunicationPointRecord> builder)
    {
        builder.ToTable("communication_points", "parties");
        builder.HasKey(x => x.Id).HasName("pk_party_communication_points");
        builder.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        builder.Property(x => x.PublicId).HasColumnName("public_id");
        builder.Property(x => x.ContactId).HasColumnName("contact_id");
        builder.Property(x => x.Type).HasColumnName("type").IsRequired();
        builder.Property(x => x.Value).HasColumnName("value").IsRequired();
        builder.Property(x => x.Purpose).HasColumnName("purpose");
        builder.Property(x => x.IsPrimary).HasColumnName("is_primary");
        builder.Property(x => x.State).HasColumnName("state").HasConversion<string>().HasMaxLength(16).IsRequired();
        builder.Property(x => x.Version).HasColumnName("version").IsConcurrencyToken();
        builder.Property(x => x.CreatedAt).HasColumnName("created_at");

        builder.HasOne<PartyContactRecord>()
            .WithMany()
            .HasForeignKey(x => x.ContactId)
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_party_communication_points_contact");

        builder.HasIndex(x => x.PublicId).IsUnique().HasDatabaseName("ux_party_communication_points_public_id");

        builder.ToTable(table =>
        {
            table.HasCheckConstraint("ck_party_communication_points_type", "length(btrim(type)) > 0 AND type = btrim(type)");
            table.HasCheckConstraint("ck_party_communication_points_value", "length(btrim(value)) > 0 AND value = btrim(value)");
            table.HasCheckConstraint("ck_party_communication_points_state", "state IN ('Active', 'Inactive')");
            table.HasCheckConstraint("ck_party_communication_points_version", "version > 0");
        });
    }
}

internal sealed class PartyAddressConfiguration : IEntityTypeConfiguration<PartyAddressRecord>
{
    public void Configure(EntityTypeBuilder<PartyAddressRecord> builder)
    {
        builder.ToTable("addresses", "parties");
        builder.HasKey(x => x.Id).HasName("pk_party_addresses");
        builder.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        builder.Property(x => x.PublicId).HasColumnName("public_id");
        builder.Property(x => x.PartyId).HasColumnName("party_id");
        builder.Property(x => x.CompanyId).HasColumnName("company_id");
        builder.Property(x => x.Purpose).HasColumnName("purpose").HasConversion<string>().HasMaxLength(16).IsRequired();
        builder.Property(x => x.Country).HasColumnName("country").IsRequired();
        builder.Property(x => x.City).HasColumnName("city");
        builder.Property(x => x.District).HasColumnName("district");
        builder.Property(x => x.PostalCode).HasColumnName("postal_code");
        builder.Property(x => x.Line1).HasColumnName("line1");
        builder.Property(x => x.Line2).HasColumnName("line2");
        builder.Property(x => x.Label).HasColumnName("label");
        builder.Property(x => x.IsDefault).HasColumnName("is_default");
        builder.Property(x => x.State).HasColumnName("state").HasConversion<string>().HasMaxLength(16).IsRequired();
        builder.Property(x => x.Version).HasColumnName("version").IsConcurrencyToken();
        builder.Property(x => x.CreatedAt).HasColumnName("created_at");

        builder.HasOne<PartyRecord>()
            .WithMany()
            .HasForeignKey(x => new { x.PartyId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_party_addresses_party_company");

        builder.HasIndex(x => x.PublicId).IsUnique().HasDatabaseName("ux_party_addresses_public_id");
        builder.HasIndex(x => new { x.PartyId, x.Purpose })
            .IsUnique()
            .HasFilter("\"state\" = 'Active' AND \"is_default\" = TRUE")
            .HasDatabaseName("ux_party_addresses_active_default_purpose");

        builder.ToTable(table =>
        {
            table.HasCheckConstraint("ck_party_addresses_country", "length(btrim(country)) > 0 AND country = btrim(country)");
            table.HasCheckConstraint("ck_party_addresses_purpose", "purpose IN ('Billing', 'Shipping', 'General')");
            table.HasCheckConstraint("ck_party_addresses_state", "state IN ('Active', 'Inactive')");
            table.HasCheckConstraint("ck_party_addresses_version", "version > 0");
        });
    }
}

internal sealed class PartyExternalMappingConfiguration : IEntityTypeConfiguration<PartyExternalMappingRecord>
{
    public void Configure(EntityTypeBuilder<PartyExternalMappingRecord> builder)
    {
        builder.ToTable("external_mappings", "parties");
        builder.HasKey(x => x.Id).HasName("pk_party_external_mappings");
        builder.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        builder.Property(x => x.PublicId).HasColumnName("public_id");
        builder.Property(x => x.PartyId).HasColumnName("party_id");
        builder.Property(x => x.CompanyId).HasColumnName("company_id");
        builder.Property(x => x.SystemCode).HasColumnName("system_code").IsRequired();
        builder.Property(x => x.AccountScope).HasColumnName("account_scope").IsRequired();
        builder.Property(x => x.ExternalIdentity).HasColumnName("external_identity").IsRequired();
        builder.Property(x => x.State).HasColumnName("state").HasConversion<string>().HasMaxLength(16).IsRequired();
        builder.Property(x => x.Version).HasColumnName("version").IsConcurrencyToken();
        builder.Property(x => x.CreatedAt).HasColumnName("created_at");

        builder.HasOne<PartyRecord>()
            .WithMany()
            .HasForeignKey(x => new { x.PartyId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_party_external_mappings_party_company");

        builder.HasIndex(x => x.PublicId).IsUnique().HasDatabaseName("ux_party_external_mappings_public_id");
        builder.HasIndex(x => new { x.CompanyId, x.SystemCode, x.AccountScope, x.ExternalIdentity })
            .IsUnique()
            .HasFilter("\"state\" = 'Active'")
            .HasDatabaseName("ux_party_external_mappings_active_scope");

        builder.ToTable(table =>
        {
            table.HasCheckConstraint("ck_party_external_mappings_system", "length(btrim(system_code)) > 0 AND system_code = btrim(system_code)");
            table.HasCheckConstraint("ck_party_external_mappings_external_identity", "length(btrim(external_identity)) > 0 AND external_identity = btrim(external_identity)");
            table.HasCheckConstraint("ck_party_external_mappings_state", "state IN ('Active', 'Inactive')");
            table.HasCheckConstraint("ck_party_external_mappings_version", "version > 0");
        });
    }
}

internal sealed class PartyMergeLineageConfiguration : IEntityTypeConfiguration<PartyMergeLineageRecord>
{
    public void Configure(EntityTypeBuilder<PartyMergeLineageRecord> builder)
    {
        builder.ToTable("merge_lineage", "parties");
        builder.HasKey(x => x.Id).HasName("pk_party_merge_lineage");
        builder.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        builder.Property(x => x.PublicId).HasColumnName("public_id");
        builder.Property(x => x.CompanyId).HasColumnName("company_id");
        builder.Property(x => x.SourcePartyId).HasColumnName("source_party_id");
        builder.Property(x => x.SurvivorPartyId).HasColumnName("survivor_party_id");
        builder.Property(x => x.ActorId).HasColumnName("actor_id");
        builder.Property(x => x.Reason).HasColumnName("reason").IsRequired();
        builder.Property(x => x.CreatedAt).HasColumnName("created_at");

        builder.HasOne<PartyRecord>()
            .WithMany()
            .HasForeignKey(x => new { x.SourcePartyId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_party_merge_lineage_source_company");

        builder.HasOne<PartyRecord>()
            .WithMany()
            .HasForeignKey(x => new { x.SurvivorPartyId, x.CompanyId })
            .HasPrincipalKey(x => new { x.Id, x.CompanyId })
            .OnDelete(DeleteBehavior.Restrict)
            .HasConstraintName("fk_party_merge_lineage_survivor_company");

        builder.HasIndex(x => x.PublicId).IsUnique().HasDatabaseName("ux_party_merge_lineage_public_id");
        builder.HasIndex(x => x.SourcePartyId).IsUnique().HasDatabaseName("ux_party_merge_lineage_source");

        builder.ToTable(table =>
        {
            table.HasCheckConstraint("ck_party_merge_lineage_distinct", "source_party_id <> survivor_party_id");
            table.HasCheckConstraint("ck_party_merge_lineage_reason", "length(btrim(reason)) > 0 AND reason = btrim(reason)");
        });
    }
}
