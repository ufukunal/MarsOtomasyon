namespace Mars.Domain.Parties;

public enum PartyMasterRecordState
{
    Active = 1,
    Inactive = 2
}

public enum PartyAddressPurpose
{
    Billing = 1,
    Shipping = 2,
    General = 3
}

public sealed class PartyContact
{
    private PartyContact(
        Guid publicId,
        Guid partyPublicId,
        Guid companyId,
        string name,
        string? title,
        string? purpose,
        DateTimeOffset createdAt)
    {
        PublicId = publicId;
        PartyPublicId = partyPublicId;
        CompanyId = companyId;
        Name = name;
        Title = title;
        Purpose = purpose;
        State = PartyMasterRecordState.Active;
        Version = 1;
        CreatedAt = createdAt;
    }

    public Guid PublicId { get; }
    public Guid PartyPublicId { get; }
    public Guid CompanyId { get; }
    public string Name { get; }
    public string? Title { get; }
    public string? Purpose { get; }
    public PartyMasterRecordState State { get; }
    public long Version { get; }
    public DateTimeOffset CreatedAt { get; }

    public static PartyContact Create(
        Guid publicId,
        Guid partyPublicId,
        Guid companyId,
        string name,
        string? title,
        string? purpose,
        DateTimeOffset createdAt) =>
        new(
            RequiredId(publicId, nameof(publicId)),
            RequiredId(partyPublicId, nameof(partyPublicId)),
            RequiredId(companyId, nameof(companyId)),
            RequiredText(name, nameof(name)),
            OptionalText(title),
            OptionalText(purpose),
            createdAt);

    private static Guid RequiredId(Guid value, string name) =>
        value == Guid.Empty ? throw new ArgumentException("Identifier is required.", name) : value;

    private static string RequiredText(string value, string name)
    {
        if (string.IsNullOrWhiteSpace(value)) throw new ArgumentException("Value is required.", name);
        if (!string.Equals(value, value.Trim(), StringComparison.Ordinal))
        {
            throw new ArgumentException("Value cannot contain leading or trailing whitespace.", name);
        }

        return value;
    }

    private static string? OptionalText(string? value)
    {
        if (string.IsNullOrWhiteSpace(value)) return null;
        if (!string.Equals(value, value.Trim(), StringComparison.Ordinal))
        {
            throw new ArgumentException("Value cannot contain leading or trailing whitespace.");
        }

        return value;
    }
}

public sealed class PartyCommunicationPoint
{
    private PartyCommunicationPoint(
        Guid publicId,
        Guid partyPublicId,
        Guid contactPublicId,
        Guid companyId,
        string type,
        string value,
        string? purpose,
        bool isPrimary,
        DateTimeOffset createdAt)
    {
        PublicId = publicId;
        PartyPublicId = partyPublicId;
        ContactPublicId = contactPublicId;
        CompanyId = companyId;
        Type = type;
        Value = value;
        Purpose = purpose;
        IsPrimary = isPrimary;
        State = PartyMasterRecordState.Active;
        Version = 1;
        CreatedAt = createdAt;
    }

    public Guid PublicId { get; }
    public Guid PartyPublicId { get; }
    public Guid ContactPublicId { get; }
    public Guid CompanyId { get; }
    public string Type { get; }
    public string Value { get; }
    public string? Purpose { get; }
    public bool IsPrimary { get; }
    public PartyMasterRecordState State { get; }
    public long Version { get; }
    public DateTimeOffset CreatedAt { get; }

    public static PartyCommunicationPoint Create(
        Guid publicId,
        Guid partyPublicId,
        Guid contactPublicId,
        Guid companyId,
        string type,
        string value,
        string? purpose,
        bool isPrimary,
        DateTimeOffset createdAt)
    {
        if (publicId == Guid.Empty) throw new ArgumentException("Public id is required.", nameof(publicId));
        if (partyPublicId == Guid.Empty) throw new ArgumentException("Party public id is required.", nameof(partyPublicId));
        if (contactPublicId == Guid.Empty) throw new ArgumentException("Contact public id is required.", nameof(contactPublicId));
        if (companyId == Guid.Empty) throw new ArgumentException("Company id is required.", nameof(companyId));

        return new PartyCommunicationPoint(
            publicId,
            partyPublicId,
            contactPublicId,
            companyId,
            RequiredText(type, nameof(type)),
            RequiredText(value, nameof(value)),
            OptionalText(purpose),
            isPrimary,
            createdAt);
    }

    private static string RequiredText(string value, string name)
    {
        if (string.IsNullOrWhiteSpace(value)) throw new ArgumentException("Value is required.", name);
        if (!string.Equals(value, value.Trim(), StringComparison.Ordinal))
        {
            throw new ArgumentException("Value cannot contain leading or trailing whitespace.", name);
        }

        return value;
    }

    private static string? OptionalText(string? value) =>
        string.IsNullOrWhiteSpace(value) ? null : value.Trim();
}

public sealed class PartyAddress
{
    private PartyAddress(
        Guid publicId,
        Guid partyPublicId,
        Guid companyId,
        PartyAddressPurpose purpose,
        string country,
        string? city,
        string? district,
        string? postalCode,
        string? line1,
        string? line2,
        string? label,
        bool isDefault,
        DateTimeOffset createdAt)
    {
        PublicId = publicId;
        PartyPublicId = partyPublicId;
        CompanyId = companyId;
        Purpose = purpose;
        Country = country;
        City = city;
        District = district;
        PostalCode = postalCode;
        Line1 = line1;
        Line2 = line2;
        Label = label;
        IsDefault = isDefault;
        State = PartyMasterRecordState.Active;
        Version = 1;
        CreatedAt = createdAt;
    }

    public Guid PublicId { get; }
    public Guid PartyPublicId { get; }
    public Guid CompanyId { get; }
    public PartyAddressPurpose Purpose { get; }
    public string Country { get; }
    public string? City { get; }
    public string? District { get; }
    public string? PostalCode { get; }
    public string? Line1 { get; }
    public string? Line2 { get; }
    public string? Label { get; }
    public bool IsDefault { get; }
    public PartyMasterRecordState State { get; }
    public long Version { get; }
    public DateTimeOffset CreatedAt { get; }

    public static PartyAddress Create(
        Guid publicId,
        Guid partyPublicId,
        Guid companyId,
        PartyAddressPurpose purpose,
        string country,
        string? city,
        string? district,
        string? postalCode,
        string? line1,
        string? line2,
        string? label,
        bool isDefault,
        DateTimeOffset createdAt)
    {
        if (publicId == Guid.Empty) throw new ArgumentException("Public id is required.", nameof(publicId));
        if (partyPublicId == Guid.Empty) throw new ArgumentException("Party public id is required.", nameof(partyPublicId));
        if (companyId == Guid.Empty) throw new ArgumentException("Company id is required.", nameof(companyId));
        if (!Enum.IsDefined(purpose)) throw new ArgumentOutOfRangeException(nameof(purpose));

        return new PartyAddress(
            publicId,
            partyPublicId,
            companyId,
            purpose,
            RequiredText(country, nameof(country)),
            OptionalText(city),
            OptionalText(district),
            OptionalText(postalCode),
            OptionalText(line1),
            OptionalText(line2),
            OptionalText(label),
            isDefault,
            createdAt);
    }

    private static string RequiredText(string value, string name)
    {
        if (string.IsNullOrWhiteSpace(value)) throw new ArgumentException("Value is required.", name);
        if (!string.Equals(value, value.Trim(), StringComparison.Ordinal))
        {
            throw new ArgumentException("Value cannot contain leading or trailing whitespace.", name);
        }

        return value;
    }

    private static string? OptionalText(string? value) =>
        string.IsNullOrWhiteSpace(value) ? null : value.Trim();
}

public sealed class PartyExternalMapping
{
    private PartyExternalMapping(
        Guid publicId,
        Guid partyPublicId,
        Guid companyId,
        string systemCode,
        string accountScope,
        string externalIdentity,
        DateTimeOffset createdAt)
    {
        PublicId = publicId;
        PartyPublicId = partyPublicId;
        CompanyId = companyId;
        SystemCode = systemCode;
        AccountScope = accountScope;
        ExternalIdentity = externalIdentity;
        State = PartyMasterRecordState.Active;
        Version = 1;
        CreatedAt = createdAt;
    }

    public Guid PublicId { get; }
    public Guid PartyPublicId { get; }
    public Guid CompanyId { get; }
    public string SystemCode { get; }
    public string AccountScope { get; }
    public string ExternalIdentity { get; }
    public PartyMasterRecordState State { get; }
    public long Version { get; }
    public DateTimeOffset CreatedAt { get; }

    public static PartyExternalMapping Create(
        Guid publicId,
        Guid partyPublicId,
        Guid companyId,
        string systemCode,
        string? accountScope,
        string externalIdentity,
        DateTimeOffset createdAt)
    {
        if (publicId == Guid.Empty) throw new ArgumentException("Public id is required.", nameof(publicId));
        if (partyPublicId == Guid.Empty) throw new ArgumentException("Party public id is required.", nameof(partyPublicId));
        if (companyId == Guid.Empty) throw new ArgumentException("Company id is required.", nameof(companyId));

        return new PartyExternalMapping(
            publicId,
            partyPublicId,
            companyId,
            RequiredText(systemCode, nameof(systemCode)),
            string.IsNullOrWhiteSpace(accountScope) ? string.Empty : accountScope.Trim(),
            RequiredText(externalIdentity, nameof(externalIdentity)),
            createdAt);
    }

    private static string RequiredText(string value, string name)
    {
        if (string.IsNullOrWhiteSpace(value)) throw new ArgumentException("Value is required.", name);
        if (!string.Equals(value, value.Trim(), StringComparison.Ordinal))
        {
            throw new ArgumentException("Value cannot contain leading or trailing whitespace.", name);
        }

        return value;
    }
}

public sealed record PartyMergeLineage(
    Guid PublicId,
    Guid CompanyId,
    Guid SourcePartyPublicId,
    Guid SurvivorPartyPublicId,
    Guid ActorId,
    string Reason,
    DateTimeOffset CreatedAt)
{
    public static PartyMergeLineage Create(
        Guid publicId,
        Guid companyId,
        Guid sourcePartyPublicId,
        Guid survivorPartyPublicId,
        Guid actorId,
        string reason,
        DateTimeOffset createdAt)
    {
        if (publicId == Guid.Empty) throw new ArgumentException("Public id is required.", nameof(publicId));
        if (companyId == Guid.Empty) throw new ArgumentException("Company id is required.", nameof(companyId));
        if (sourcePartyPublicId == Guid.Empty) throw new ArgumentException("Source Party id is required.", nameof(sourcePartyPublicId));
        if (survivorPartyPublicId == Guid.Empty) throw new ArgumentException("Survivor Party id is required.", nameof(survivorPartyPublicId));
        if (sourcePartyPublicId == survivorPartyPublicId) throw new ArgumentException("Source and survivor must differ.");
        if (actorId == Guid.Empty) throw new ArgumentException("Actor id is required.", nameof(actorId));
        if (string.IsNullOrWhiteSpace(reason)) throw new ArgumentException("Merge reason is required.", nameof(reason));

        return new PartyMergeLineage(
            publicId,
            companyId,
            sourcePartyPublicId,
            survivorPartyPublicId,
            actorId,
            reason.Trim(),
            createdAt);
    }
}
