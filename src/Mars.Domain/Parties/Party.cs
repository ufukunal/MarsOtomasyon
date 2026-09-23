namespace Mars.Domain.Parties;

public enum PartyKind
{
    Person = 1,
    Organization = 2
}

public enum PartyState
{
    Active = 1,
    Inactive = 2,
    Merged = 3
}

public sealed class Party
{
    private Party(
        Guid publicId,
        Guid companyId,
        string partyCode,
        PartyKind kind,
        string legalName,
        string? displayName,
        DateTimeOffset createdAt)
    {
        PublicId = publicId;
        CompanyId = companyId;
        PartyCode = partyCode;
        Kind = kind;
        LegalName = legalName;
        DisplayName = displayName;
        State = PartyState.Active;
        Version = 1;
        CreatedAt = createdAt;
    }

    public Guid PublicId { get; }
    public Guid CompanyId { get; }
    public string PartyCode { get; }
    public PartyKind Kind { get; }
    public string LegalName { get; }
    public string? DisplayName { get; }
    public PartyState State { get; }
    public long Version { get; }
    public DateTimeOffset CreatedAt { get; }

    public static Party Create(
        Guid publicId,
        Guid companyId,
        string partyCode,
        PartyKind kind,
        string legalName,
        string? displayName,
        DateTimeOffset createdAt)
    {
        if (publicId == Guid.Empty) throw new ArgumentException("Public id is required.", nameof(publicId));
        if (companyId == Guid.Empty) throw new ArgumentException("Company id is required.", nameof(companyId));
        if (!Enum.IsDefined(kind)) throw new ArgumentOutOfRangeException(nameof(kind));
        if (string.IsNullOrWhiteSpace(partyCode)) throw new ArgumentException("Party Code is required.", nameof(partyCode));
        if (!string.Equals(partyCode, partyCode.Trim(), StringComparison.Ordinal))
        {
            throw new ArgumentException("Party Code cannot contain leading or trailing whitespace.", nameof(partyCode));
        }

        if (string.IsNullOrWhiteSpace(legalName)) throw new ArgumentException("Legal name is required.", nameof(legalName));
        if (!string.Equals(legalName, legalName.Trim(), StringComparison.Ordinal))
        {
            throw new ArgumentException("Legal name cannot contain leading or trailing whitespace.", nameof(legalName));
        }

        if (displayName is not null)
        {
            if (string.IsNullOrWhiteSpace(displayName))
            {
                displayName = null;
            }
            else if (!string.Equals(displayName, displayName.Trim(), StringComparison.Ordinal))
            {
                throw new ArgumentException("Display name cannot contain leading or trailing whitespace.", nameof(displayName));
            }
        }

        return new Party(publicId, companyId, partyCode, kind, legalName, displayName, createdAt);
    }
}
