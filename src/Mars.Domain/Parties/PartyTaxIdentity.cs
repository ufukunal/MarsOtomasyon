namespace Mars.Domain.Parties;

public enum PartyTaxIdentityScheme
{
    Vkn = 1,
    Tckn = 2
}

public enum PartyTaxIdentityState
{
    Active = 1,
    Inactive = 2
}

public sealed class PartyTaxIdentity
{
    private PartyTaxIdentity(
        Guid publicId,
        Guid partyPublicId,
        Guid companyId,
        string jurisdiction,
        PartyTaxIdentityScheme scheme,
        string value,
        DateTimeOffset createdAt)
    {
        PublicId = publicId;
        PartyPublicId = partyPublicId;
        CompanyId = companyId;
        Jurisdiction = jurisdiction;
        Scheme = scheme;
        Value = value;
        State = PartyTaxIdentityState.Active;
        Version = 1;
        CreatedAt = createdAt;
    }

    public Guid PublicId { get; }
    public Guid PartyPublicId { get; }
    public Guid CompanyId { get; }
    public string Jurisdiction { get; }
    public PartyTaxIdentityScheme Scheme { get; }
    public string Value { get; }
    public PartyTaxIdentityState State { get; }
    public long Version { get; }
    public DateTimeOffset CreatedAt { get; }

    public static PartyTaxIdentity Create(
        Guid publicId,
        Guid partyPublicId,
        Guid companyId,
        string jurisdiction,
        PartyTaxIdentityScheme scheme,
        string value,
        DateTimeOffset createdAt)
    {
        if (publicId == Guid.Empty) throw new ArgumentException("Public id is required.", nameof(publicId));
        if (partyPublicId == Guid.Empty) throw new ArgumentException("Party public id is required.", nameof(partyPublicId));
        if (companyId == Guid.Empty) throw new ArgumentException("Company id is required.", nameof(companyId));
        if (!string.Equals(jurisdiction, "TR", StringComparison.Ordinal))
        {
            throw new ArgumentException("Jurisdiction must be TR in PARTY-IMP-003.", nameof(jurisdiction));
        }

        if (!Enum.IsDefined(scheme)) throw new ArgumentOutOfRangeException(nameof(scheme));
        if (!IsAsciiDigits(value))
        {
            throw new ArgumentException("Tax identity value must contain ASCII digits only.", nameof(value));
        }

        var expectedLength = scheme == PartyTaxIdentityScheme.Vkn ? 10 : 11;
        if (value.Length != expectedLength)
        {
            throw new ArgumentException(
                $"{scheme} value must contain exactly {expectedLength} digits.",
                nameof(value));
        }

        return new PartyTaxIdentity(
            publicId,
            partyPublicId,
            companyId,
            jurisdiction,
            scheme,
            value,
            createdAt);
    }

    private static bool IsAsciiDigits(string value) =>
        !string.IsNullOrEmpty(value) && value.All(character => character is >= '0' and <= '9');
}
