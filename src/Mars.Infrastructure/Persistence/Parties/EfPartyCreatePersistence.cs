using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Parties.CreateParty;
using Microsoft.EntityFrameworkCore;
using Npgsql;

namespace Mars.Infrastructure.Persistence.Parties;

public sealed class EfPartyCreatePersistence(
    MarsDbContext dbContext,
    IAuditWriter auditWriter,
    IIdempotencyStore idempotencyStore) : IPartyCreatePersistence
{
    public async Task<CreatePartyPersistenceOutcome> PersistAsync(
        CreatePartyWrite write,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(write);

        await using var transaction =
            await dbContext.Database.BeginTransactionAsync(cancellationToken);

        try
        {
            dbContext.Add(new PartyRecord
            {
                PublicId = write.Party.PublicId,
                CompanyId = write.Party.CompanyId,
                PartyCode = write.Party.PartyCode,
                Kind = write.Party.Kind,
                LegalName = write.Party.LegalName,
                DisplayName = write.Party.DisplayName,
                State = write.Party.State,
                Version = write.Party.Version,
                CreatedAt = write.Party.CreatedAt
            });

            idempotencyStore.Add(write.Idempotency);
            auditWriter.Append(write.Audit);

            await dbContext.SaveChangesAsync(cancellationToken);

            var markedSucceeded = await idempotencyStore.MarkSucceededAsync(
                write.Idempotency.Scope,
                write.Idempotency.OperationKey,
                "parties.create.completed",
                write.Audit.OccurredAt,
                cancellationToken);

            if (!markedSucceeded)
            {
                throw new InvalidOperationException(
                    "Party create idempotency state could not be completed.");
            }

            await transaction.CommitAsync(cancellationToken);
            return CreatePartyPersistenceOutcome.Created;
        }
        catch (DbUpdateException exception) when (IsConstraint(exception, "ux_idempotency_scope_key"))
        {
            await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return CreatePartyPersistenceOutcome.DuplicateOperation;
        }
        catch (DbUpdateException exception) when (IsConstraint(exception, "ux_parties_company_code"))
        {
            await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return CreatePartyPersistenceOutcome.DuplicatePartyCode;
        }
    }

    private static bool IsConstraint(DbUpdateException exception, string constraintName) =>
        exception.InnerException is PostgresException postgres &&
        postgres.SqlState == PostgresErrorCodes.UniqueViolation &&
        string.Equals(postgres.ConstraintName, constraintName, StringComparison.Ordinal);
}
