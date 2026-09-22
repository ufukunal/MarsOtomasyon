namespace Mars.Infrastructure.Persistence.Foundation;

internal sealed class AuditEventRecord
{
    public long Id { get; set; }
    public Guid ActorId { get; set; }
    public Guid CompanyId { get; set; }
    public Guid? BranchId { get; set; }
    public string CorrelationId { get; set; } = string.Empty;
    public string Module { get; set; } = string.Empty;
    public string Action { get; set; } = string.Empty;
    public string? EntityType { get; set; }
    public Guid? EntityPublicId { get; set; }
    public string? Reason { get; set; }
    public DateTimeOffset OccurredAt { get; set; }
}
