using Mars.Domain.Parties;

namespace Mars.Infrastructure.Persistence.Parties;

internal sealed class PartyRoleRecord
{
    public long Id { get; set; }
    public long PartyId { get; set; }
    public PartyRoleType RoleType { get; set; }
    public PartyRoleState State { get; set; }
    public long Version { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}
