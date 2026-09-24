namespace Mars.Infrastructure.Persistence.Inventory;

internal sealed class WarehouseAccessGrantRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid ActorId { get; set; }
    public Guid CompanyId { get; set; }
    public long WarehouseId { get; set; }
    public Guid GrantedByActorId { get; set; }
    public DateTimeOffset GrantedAt { get; set; }
    public Guid? RevokedByActorId { get; set; }
    public DateTimeOffset? RevokedAt { get; set; }
}
