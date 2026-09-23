using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Foundation.Outbox;
using Mars.Application.Foundation.Proof;
using Microsoft.EntityFrameworkCore;
using Npgsql;

namespace Mars.Infrastructure.Persistence.Foundation;

public sealed class EfFoundationProofPersistence(
    MarsDbContext dbContext,
    IAuditWriter auditWriter,
    IIdempotencyStore idempotencyStore,
    IOutboxWriter outboxWriter) : IFoundationProofPersistence
{
    public async Task<FoundationProofPersistenceOutcome> PersistAsync(
        FoundationProofWrite write,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(write);

        await using var transaction =
            await dbContext.Database.BeginTransactionAsync(cancellationToken);

        try
        {
            idempotencyStore.Add(write.Idempotency);
            auditWriter.Append(write.Audit);
            outboxWriter.Enqueue(write.Outbox);

            await dbContext.SaveChangesAsync(cancellationToken);

            var markedSucceeded = await idempotencyStore.MarkSucceededAsync(
                write.Idempotency.Scope,
                write.Idempotency.OperationKey,
                "foundation.proof.completed",
                write.Audit.OccurredAt,
                cancellationToken);

            if (!markedSucceeded)
            {
                throw new InvalidOperationException(
                    "Foundation proof idempotency state could not be completed.");
            }

            await transaction.CommitAsync(cancellationToken);
            return FoundationProofPersistenceOutcome.Created;
        }
        catch (DbUpdateException exception) when (IsDuplicateIdempotencyKey(exception))
        {
            await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return FoundationProofPersistenceOutcome.Duplicate;
        }
    }

    private static bool IsDuplicateIdempotencyKey(DbUpdateException exception) =>
        exception.InnerException is PostgresException
        {
            SqlState: PostgresErrorCodes.UniqueViolation,
            ConstraintName: "ux_idempotency_scope_key"
        };
}
