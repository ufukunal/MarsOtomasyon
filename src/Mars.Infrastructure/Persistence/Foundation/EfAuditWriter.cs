using Mars.Application.Foundation.Auditing;

namespace Mars.Infrastructure.Persistence.Foundation;

public sealed class EfAuditWriter(MarsDbContext dbContext) : IAuditWriter
{
    public void Append(AuditEntry entry)
    {
        ArgumentNullException.ThrowIfNull(entry);

        dbContext.Add(new AuditEventRecord
        {
            ActorId = entry.ActorId,
            CompanyId = entry.CompanyId,
            BranchId = entry.BranchId,
            CorrelationId = entry.CorrelationId,
            Module = entry.Module,
            Action = entry.Action,
            EntityType = entry.EntityType,
            EntityPublicId = entry.EntityPublicId,
            Reason = entry.Reason,
            OccurredAt = entry.OccurredAt
        });
    }
}
