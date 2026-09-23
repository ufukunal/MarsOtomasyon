using Mars.Domain.Parties;

namespace Mars.Infrastructure.Persistence.Parties;

internal sealed class PartyRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public string PartyCode { get; set; } = string.Empty;
    public PartyKind Kind { get; set; }
    public string LegalName { get; set; } = string.Empty;
    public string? DisplayName { get; set; }
    public PartyState State { get; set; }
    public long Version { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}
