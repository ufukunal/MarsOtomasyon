namespace Mars.Infrastructure.Persistence.Foundation;

internal sealed class PermissionGrantRecord
{
    public long Id { get; set; }
    public Guid ActorId { get; set; }
    public Guid CompanyId { get; set; }
    public string PermissionCode { get; set; } = string.Empty;
    public DateTimeOffset GrantedAt { get; set; }
    public Guid? GrantedByActorId { get; set; }
    public DateTimeOffset? RevokedAt { get; set; }
    public Guid? RevokedByActorId { get; set; }
}
