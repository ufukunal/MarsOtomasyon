using Mars.Domain.Parties;

namespace Mars.Infrastructure.Persistence.Parties;

internal sealed class PartyContactRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long PartyId { get; set; }
    public Guid CompanyId { get; set; }
    public string Name { get; set; } = string.Empty;
    public string? Title { get; set; }
    public string? Purpose { get; set; }
    public PartyMasterRecordState State { get; set; }
    public long Version { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class PartyCommunicationPointRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long ContactId { get; set; }
    public string Type { get; set; } = string.Empty;
    public string Value { get; set; } = string.Empty;
    public string? Purpose { get; set; }
    public bool IsPrimary { get; set; }
    public PartyMasterRecordState State { get; set; }
    public long Version { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class PartyAddressRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long PartyId { get; set; }
    public Guid CompanyId { get; set; }
    public PartyAddressPurpose Purpose { get; set; }
    public string Country { get; set; } = string.Empty;
    public string? City { get; set; }
    public string? District { get; set; }
    public string? PostalCode { get; set; }
    public string? Line1 { get; set; }
    public string? Line2 { get; set; }
    public string? Label { get; set; }
    public bool IsDefault { get; set; }
    public PartyMasterRecordState State { get; set; }
    public long Version { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class PartyExternalMappingRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long PartyId { get; set; }
    public Guid CompanyId { get; set; }
    public string SystemCode { get; set; } = string.Empty;
    public string AccountScope { get; set; } = string.Empty;
    public string ExternalIdentity { get; set; } = string.Empty;
    public PartyMasterRecordState State { get; set; }
    public long Version { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class PartyMergeLineageRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public long SourcePartyId { get; set; }
    public long SurvivorPartyId { get; set; }
    public Guid ActorId { get; set; }
    public string Reason { get; set; } = string.Empty;
    public DateTimeOffset CreatedAt { get; set; }
}
