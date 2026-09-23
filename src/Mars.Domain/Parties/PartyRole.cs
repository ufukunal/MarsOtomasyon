namespace Mars.Domain.Parties;

public enum PartyRoleType
{
    Customer = 1,
    Supplier = 2
}

public enum PartyRoleState
{
    Active = 1,
    Inactive = 2
}

public sealed class PartyRole
{
    private PartyRole(
        Guid partyPublicId,
        Guid companyId,
        PartyRoleType roleType,
        DateTimeOffset createdAt)
    {
        PartyPublicId = partyPublicId;
        CompanyId = companyId;
        RoleType = roleType;
        State = PartyRoleState.Active;
        Version = 1;
        CreatedAt = createdAt;
    }

    public Guid PartyPublicId { get; }
    public Guid CompanyId { get; }
    public PartyRoleType RoleType { get; }
    public PartyRoleState State { get; }
    public long Version { get; }
    public DateTimeOffset CreatedAt { get; }

    public static PartyRole Create(
        Guid partyPublicId,
        Guid companyId,
        PartyRoleType roleType,
        DateTimeOffset createdAt)
    {
        if (partyPublicId == Guid.Empty)
        {
            throw new ArgumentException("Party public id is required.", nameof(partyPublicId));
        }

        if (companyId == Guid.Empty)
        {
            throw new ArgumentException("Company id is required.", nameof(companyId));
        }

        if (!Enum.IsDefined(roleType))
        {
            throw new ArgumentOutOfRangeException(nameof(roleType));
        }

        return new PartyRole(partyPublicId, companyId, roleType, createdAt);
    }
}
