namespace Mars.Api.Parties;

public sealed record EditPartyIdentityRequest(
    long Version,
    string LegalName,
    string? DisplayName);

public sealed record CreatePartyContactRequest(
    string Name,
    string? Title,
    string? Purpose);

public sealed record ChangePartyMasterStateRequest(
    long Version,
    string State);

public sealed record CreatePartyCommunicationPointRequest(
    string Type,
    string Value,
    string? Purpose,
    bool IsPrimary);

public sealed record CreatePartyAddressRequest(
    string Purpose,
    string Country,
    string? City,
    string? District,
    string? PostalCode,
    string? Line1,
    string? Line2,
    string? Label,
    bool IsDefault);

public sealed record UpdatePartyAddressRequest(
    long Version,
    string Purpose,
    string Country,
    string? City,
    string? District,
    string? PostalCode,
    string? Line1,
    string? Line2,
    string? Label,
    bool IsDefault);

public sealed record CreatePartyExternalMappingRequest(
    string SystemCode,
    string? AccountScope,
    string ExternalIdentity);

public sealed record MergePartyRequest(
    Guid SurvivorPartyPublicId,
    long SourceVersion,
    long SurvivorVersion,
    bool UseSourceIdentity,
    bool MoveSourceRoles,
    bool MoveSourceContacts,
    bool MoveSourceAddresses,
    bool MoveSourceTaxIdentities,
    bool MoveSourceExternalMappings,
    string Reason);
