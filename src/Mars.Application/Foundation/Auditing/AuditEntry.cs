namespace Mars.Application.Foundation.Auditing;

public sealed record AuditEntry
{
    public AuditEntry(
        Guid actorId,
        Guid companyId,
        Guid? branchId,
        string correlationId,
        string module,
        string action,
        string? entityType,
        Guid? entityPublicId,
        string? reason,
        DateTimeOffset occurredAt)
    {
        if (actorId == Guid.Empty) throw new ArgumentException("Actor id is required.", nameof(actorId));
        if (companyId == Guid.Empty) throw new ArgumentException("Company id is required.", nameof(companyId));
        if (branchId == Guid.Empty) throw new ArgumentException("Branch id cannot be empty.", nameof(branchId));
        if (string.IsNullOrWhiteSpace(correlationId)) throw new ArgumentException("Correlation id is required.", nameof(correlationId));
        if (string.IsNullOrWhiteSpace(module)) throw new ArgumentException("Module is required.", nameof(module));
        if (string.IsNullOrWhiteSpace(action)) throw new ArgumentException("Action is required.", nameof(action));

        ActorId = actorId;
        CompanyId = companyId;
        BranchId = branchId;
        CorrelationId = correlationId.Trim();
        Module = module.Trim();
        Action = action.Trim();
        EntityType = Normalize(entityType);
        EntityPublicId = entityPublicId;
        Reason = Normalize(reason);
        OccurredAt = occurredAt;
    }

    public Guid ActorId { get; }
    public Guid CompanyId { get; }
    public Guid? BranchId { get; }
    public string CorrelationId { get; }
    public string Module { get; }
    public string Action { get; }
    public string? EntityType { get; }
    public Guid? EntityPublicId { get; }
    public string? Reason { get; }
    public DateTimeOffset OccurredAt { get; }

    private static string? Normalize(string? value) =>
        string.IsNullOrWhiteSpace(value) ? null : value.Trim();
}
