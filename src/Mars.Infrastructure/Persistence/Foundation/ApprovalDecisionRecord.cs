using Mars.Application.Foundation.Approvals;

namespace Mars.Infrastructure.Persistence.Foundation;

internal sealed class ApprovalDecisionRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public string Module { get; set; } = string.Empty;
    public string EntityType { get; set; } = string.Empty;
    public Guid EntityPublicId { get; set; }
    public long SnapshotVersion { get; set; }
    public Guid CreatorActorId { get; set; }
    public Guid DeciderActorId { get; set; }
    public ApprovalDecisionKind Decision { get; set; }
    public string? Reason { get; set; }
    public DateTimeOffset DecidedAt { get; set; }
}
