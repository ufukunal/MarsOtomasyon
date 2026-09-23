using Mars.Domain.Parties;

namespace Mars.Infrastructure.Persistence.Parties;

internal sealed class PartyTaxIdentityRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long PartyId { get; set; }
    public Guid CompanyId { get; set; }
    public string Jurisdiction { get; set; } = string.Empty;
    public PartyTaxIdentityScheme Scheme { get; set; }
    public string Value { get; set; } = string.Empty;
    public PartyTaxIdentityState State { get; set; }
    public long Version { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}
