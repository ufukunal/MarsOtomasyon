namespace Mars.Api.Parties;

public sealed record AddPartyTaxIdentityRequest(
    string Jurisdiction,
    string Scheme,
    string Value);
