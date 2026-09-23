namespace Mars.Api.Parties;

public sealed record CreatePartyRequest(
    string PartyCode,
    string Kind,
    string LegalName,
    string? DisplayName);
