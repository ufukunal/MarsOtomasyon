using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Parties.ActivatePartyRole;
using Microsoft.EntityFrameworkCore;
using Npgsql;

namespace Mars.Infrastructure.Persistence.Parties;

public sealed class EfPartyRoleActivationPersistence(
    MarsDbContext dbContext,
    IAuditWriter auditWriter,
    IIdempotencyStore idempotencyStore) : IPartyRoleActivationPersistence
{
    public async Task<ActivatePartyRolePersistenceOutcome> PersistAsync(
        ActivatePartyRoleWrite write,
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
                    x.PublicId == write.Role.PartyPublicId &&
                    x.CompanyId == write.Role.CompanyId)
                .Select(x => (long?)x.Id)
                .SingleOrDefaultAsync(cancellationToken);

            if (partyId is null)
            {
                await transaction.RollbackAsync(cancellationToken);
                return ActivatePartyRolePersistenceOutcome.PartyNotFound;
            }

            dbContext.Add(new PartyRoleRecord
            {
                PartyId = partyId.Value,
                RoleType = write.Role.RoleType,
                State = write.Role.State,
                Version = write.Role.Version,
                CreatedAt = write.Role.CreatedAt
            });

            idempotencyStore.Add(write.Idempotency);
            auditWriter.Append(write.Audit);

            await dbContext.SaveChangesAsync(cancellationToken);

            var markedSucceeded = await idempotencyStore.MarkSucceededAsync(
                write.Idempotency.Scope,
                write.Idempotency.OperationKey,
                "parties.role.activate.completed",
                write.Audit.OccurredAt,
                cancellationToken);

            if (!markedSucceeded)
            {
                throw new InvalidOperationException(
                    "Party role activation idempotency state could not be completed.");
            }

            await transaction.CommitAsync(cancellationToken);
            return ActivatePartyRolePersistenceOutcome.Activated;
        }
        catch (DbUpdateException exception) when (IsConstraint(exception, "ux_idempotency_scope_key"))
        {
            await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return ActivatePartyRolePersistenceOutcome.DuplicateOperation;
        }
        catch (DbUpdateException exception) when (IsConstraint(exception, "ux_party_roles_party_role_type"))
        {
            await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return ActivatePartyRolePersistenceOutcome.DuplicateRole;
        }
    }

    private static bool IsConstraint(DbUpdateException exception, string constraintName) =>
        exception.InnerException is PostgresException postgres &&
        postgres.SqlState == PostgresErrorCodes.UniqueViolation &&
        string.Equals(postgres.ConstraintName, constraintName, StringComparison.Ordinal);
}
