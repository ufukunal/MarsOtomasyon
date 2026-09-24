using Mars.Application.Foundation.Approvals;
using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Foundation.Outbox;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Storage;
using Npgsql;

namespace Mars.Infrastructure.Persistence.Foundation;

public sealed class EfApprovalDecisionPersistence(
    MarsDbContext dbContext,
    IAuditWriter auditWriter,
    IIdempotencyStore idempotencyStore,
    IOutboxWriter outboxWriter)
    : IApprovalDecisionPersistence
{
    public async Task<ApprovalDecisionPersistenceResult> RecordAsync(
        ApprovalDecisionWrite write,
        CancellationToken cancellationToken)
    {
        var ownsTransaction = dbContext.Database.CurrentTransaction is null;
        IDbContextTransaction? transaction = null;
        if (ownsTransaction)
            transaction = await dbContext.Database.BeginTransactionAsync(cancellationToken);

        try
        {
            dbContext.Add(new ApprovalDecisionRecord
            {
                PublicId = write.PublicId,
                CompanyId = write.CompanyId,
                Module = write.Command.Module,
                EntityType = write.Command.EntityType,
                EntityPublicId = write.Command.EntityPublicId,
                SnapshotVersion = write.Command.SnapshotVersion,
                CreatorActorId = write.Command.CreatorActorId,
                DeciderActorId = write.ActorId,
                Decision = write.Command.Decision,
                Reason = write.Command.Reason,
                DecidedAt = write.OccurredAt
            });
            idempotencyStore.Add(write.Idempotency);
            auditWriter.Append(write.Audit);
            outboxWriter.Enqueue(write.Outbox);
            await dbContext.SaveChangesAsync(cancellationToken);

            var marked = await idempotencyStore.MarkSucceededAsync(
                write.Idempotency.Scope,
                write.Idempotency.OperationKey,
                "approval.decision.completed",
                write.OccurredAt,
                cancellationToken);
            if (!marked)
                throw new InvalidOperationException("Approval idempotency state could not be completed.");

            if (transaction is not null)
                await transaction.CommitAsync(cancellationToken);

            return new ApprovalDecisionPersistenceResult(
                ApprovalDecisionPersistenceOutcome.Succeeded,
                write.PublicId);
        }
        catch (DbUpdateException ex) when (Constraint(ex, "ux_idempotency_scope_key"))
        {
            if (transaction is not null) await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return new ApprovalDecisionPersistenceResult(
                ApprovalDecisionPersistenceOutcome.DuplicateOperation);
        }
        catch (DbUpdateException ex) when (
            Constraint(ex, "ux_foundation_approval_decisions_approved_snapshot"))
        {
            if (transaction is not null) await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return new ApprovalDecisionPersistenceResult(
                ApprovalDecisionPersistenceOutcome.DeterministicConflict);
        }
        finally
        {
            if (transaction is not null) await transaction.DisposeAsync();
        }
    }

    public Task<bool> IsApprovedAsync(
        Guid companyId,
        string module,
        string entityType,
        Guid entityPublicId,
        long snapshotVersion,
        CancellationToken cancellationToken) =>
        dbContext.Set<ApprovalDecisionRecord>()
            .AsNoTracking()
            .AnyAsync(
                x => x.CompanyId == companyId &&
                     x.Module == module &&
                     x.EntityType == entityType &&
                     x.EntityPublicId == entityPublicId &&
                     x.SnapshotVersion == snapshotVersion &&
                     x.Decision == ApprovalDecisionKind.Approved,
                cancellationToken);

    private static bool Constraint(DbUpdateException exception, string name) =>
        exception.InnerException is PostgresException postgres &&
        postgres.SqlState == PostgresErrorCodes.UniqueViolation &&
        string.Equals(postgres.ConstraintName, name, StringComparison.Ordinal);
}
