using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Parties.DeactivateParty;
using Mars.Domain.Parties;
using Microsoft.EntityFrameworkCore;
using Npgsql;

namespace Mars.Infrastructure.Persistence.Parties;

public sealed class EfPartyDeactivatePersistence(
    MarsDbContext dbContext,
    IAuditWriter auditWriter,
    IIdempotencyStore idempotencyStore) : IPartyDeactivatePersistence
{
    public async Task<DeactivatePartyPersistenceResult> DeactivateAsync(
        DeactivatePartyWrite write,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(write);

        await using var transaction =
            await dbContext.Database.BeginTransactionAsync(cancellationToken);

        try
        {
            var party = await dbContext.Set<PartyRecord>()
                .SingleOrDefaultAsync(
                    x => x.PublicId == write.PartyPublicId && x.CompanyId == write.CompanyId,
                    cancellationToken);

            if (party is null)
            {
                await transaction.RollbackAsync(cancellationToken);
                return new(DeactivatePartyPersistenceOutcome.PartyNotFound);
            }

            if (party.Version != write.ExpectedVersion)
            {
                await transaction.RollbackAsync(cancellationToken);
                dbContext.ChangeTracker.Clear();
                return new(DeactivatePartyPersistenceOutcome.StaleVersion);
            }

            if (party.State == PartyState.Inactive)
            {
                await transaction.RollbackAsync(cancellationToken);
                dbContext.ChangeTracker.Clear();
                return new(DeactivatePartyPersistenceOutcome.AlreadyInactive);
            }

            if (party.State == PartyState.Merged)
            {
                await transaction.RollbackAsync(cancellationToken);
                dbContext.ChangeTracker.Clear();
                return new(DeactivatePartyPersistenceOutcome.MergedStateConflict);
            }

            party.State = PartyState.Inactive;
            party.Version = checked(party.Version + 1);

            idempotencyStore.Add(write.Idempotency);
            auditWriter.Append(write.Audit);

            await dbContext.SaveChangesAsync(cancellationToken);

            var markedSucceeded = await idempotencyStore.MarkSucceededAsync(
                write.Idempotency.Scope,
                write.Idempotency.OperationKey,
                "parties.deactivate.completed",
                write.Audit.OccurredAt,
                cancellationToken);

            if (!markedSucceeded)
            {
                throw new InvalidOperationException(
                    "Party deactivation idempotency state could not be completed.");
            }

            await transaction.CommitAsync(cancellationToken);
            return new(
                DeactivatePartyPersistenceOutcome.Deactivated,
                party.Version);
        }
        catch (DbUpdateConcurrencyException)
        {
            await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return new(DeactivatePartyPersistenceOutcome.StaleVersion);
        }
        catch (DbUpdateException exception) when (IsConstraint(exception, "ux_idempotency_scope_key"))
        {
            await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return new(DeactivatePartyPersistenceOutcome.DuplicateOperation);
        }
    }

    private static bool IsConstraint(DbUpdateException exception, string constraintName) =>
        exception.InnerException is PostgresException postgres &&
        postgres.SqlState == PostgresErrorCodes.UniqueViolation &&
        string.Equals(postgres.ConstraintName, constraintName, StringComparison.Ordinal);
}
