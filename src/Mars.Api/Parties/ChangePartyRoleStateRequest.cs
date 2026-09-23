namespace Mars.Api.Parties;

public sealed record ChangePartyRoleStateRequest(
    string State,
    long Version,
    string? Reason);
