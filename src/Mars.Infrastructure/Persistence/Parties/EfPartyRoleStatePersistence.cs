using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Parties.ChangePartyRoleState;
using Microsoft.EntityFrameworkCore;
using Npgsql;

namespace Mars.Infrastructure.Persistence.Parties;

public sealed class EfPartyRoleStatePersistence(
    MarsDbContext dbContext,
    IAuditWriter auditWriter,
    IIdempotencyStore idempotencyStore) : IPartyRoleStatePersistence
{
    public async Task<ChangePartyRoleStatePersistenceResult> ChangeAsync(
        ChangePartyRoleStateWrite write,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(write);

        await using var transaction =
            await dbContext.Database.BeginTransactionAsync(cancellationToken);

        try
        {
            var partyId = await dbContext.Set<PartyRecord>()
                .AsNoTracking()
                .Where(x =>
                    x.PublicId == write.PartyPublicId &&
                    x.CompanyId == write.CompanyId)
                .Select(x => (long?)x.Id)
                .SingleOrDefaultAsync(cancellationToken);

            if (partyId is null)
            {
                await transaction.RollbackAsync(cancellationToken);
                return new(ChangePartyRoleStatePersistenceOutcome.PartyNotFound);
            }

            var role = await dbContext.Set<PartyRoleRecord>()
                .SingleOrDefaultAsync(
                    x => x.PartyId == partyId.Value && x.RoleType == write.RoleType,
                    cancellationToken);

            if (role is null)
            {
                await transaction.RollbackAsync(cancellationToken);
                return new(ChangePartyRoleStatePersistenceOutcome.RoleNotFound);
            }

            if (role.Version != write.ExpectedVersion)
            {
                await transaction.RollbackAsync(cancellationToken);
                dbContext.ChangeTracker.Clear();
                return new(ChangePartyRoleStatePersistenceOutcome.StaleVersion);
            }

            if (role.State == write.TargetState)
            {
                await transaction.RollbackAsync(cancellationToken);
                dbContext.ChangeTracker.Clear();
                return new(ChangePartyRoleStatePersistenceOutcome.AlreadyInTargetState);
            }

            role.State = write.TargetState;
            role.Version = checked(role.Version + 1);

            idempotencyStore.Add(write.Idempotency);
            auditWriter.Append(write.Audit);

            await dbContext.SaveChangesAsync(cancellationToken);

            var markedSucceeded = await idempotencyStore.MarkSucceededAsync(
                write.Idempotency.Scope,
                write.Idempotency.OperationKey,
                "parties.role.state.completed",
                write.Audit.OccurredAt,
                cancellationToken);

            if (!markedSucceeded)
            {
                throw new InvalidOperationException(
                    "Party role state idempotency could not be completed.");
            }

            await transaction.CommitAsync(cancellationToken);
            return new(
                ChangePartyRoleStatePersistenceOutcome.Changed,
                role.Version);
        }
        catch (DbUpdateConcurrencyException)
        {
            await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return new(ChangePartyRoleStatePersistenceOutcome.StaleVersion);
        }
        catch (DbUpdateException exception) when (IsConstraint(exception, "ux_idempotency_scope_key"))
        {
            await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return new(ChangePartyRoleStatePersistenceOutcome.DuplicateOperation);
        }
    }

    private static bool IsConstraint(DbUpdateException exception, string constraintName) =>
        exception.InnerException is PostgresException postgres &&
        postgres.SqlState == PostgresErrorCodes.UniqueViolation &&
        string.Equals(postgres.ConstraintName, constraintName, StringComparison.Ordinal);
}
