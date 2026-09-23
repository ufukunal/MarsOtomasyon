namespace Mars.Api.Parties;

public sealed record DeactivatePartyRequest(
    long Version,
    string Reason);
