using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Parties.AddPartyTaxIdentity;
using Microsoft.EntityFrameworkCore;
using Npgsql;

namespace Mars.Infrastructure.Persistence.Parties;

public sealed class EfPartyTaxIdentityAddPersistence(
    MarsDbContext dbContext,
    IAuditWriter auditWriter,
    IIdempotencyStore idempotencyStore) : IPartyTaxIdentityAddPersistence
{
    public async Task<AddPartyTaxIdentityPersistenceOutcome> PersistAsync(
        AddPartyTaxIdentityWrite write,
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
                    x.PublicId == write.TaxIdentity.PartyPublicId &&
                    x.CompanyId == write.TaxIdentity.CompanyId)
                .Select(x => (long?)x.Id)
                .SingleOrDefaultAsync(cancellationToken);

            if (partyId is null)
            {
                await transaction.RollbackAsync(cancellationToken);
                return AddPartyTaxIdentityPersistenceOutcome.PartyNotFound;
            }

            dbContext.Add(new PartyTaxIdentityRecord
            {
                PublicId = write.TaxIdentity.PublicId,
                PartyId = partyId.Value,
                CompanyId = write.TaxIdentity.CompanyId,
                Jurisdiction = write.TaxIdentity.Jurisdiction,
                Scheme = write.TaxIdentity.Scheme,
                Value = write.TaxIdentity.Value,
                State = write.TaxIdentity.State,
                Version = write.TaxIdentity.Version,
                CreatedAt = write.TaxIdentity.CreatedAt
            });

            idempotencyStore.Add(write.Idempotency);
            auditWriter.Append(write.Audit);

            await dbContext.SaveChangesAsync(cancellationToken);

            var markedSucceeded = await idempotencyStore.MarkSucceededAsync(
                write.Idempotency.Scope,
                write.Idempotency.OperationKey,
                "parties.tax_identity.add.completed",
                write.Audit.OccurredAt,
                cancellationToken);

            if (!markedSucceeded)
            {
                throw new InvalidOperationException(
                    "Party tax identity idempotency state could not be completed.");
            }

            await transaction.CommitAsync(cancellationToken);
            return AddPartyTaxIdentityPersistenceOutcome.Added;
        }
        catch (DbUpdateException exception) when (IsConstraint(exception, "ux_idempotency_scope_key"))
        {
            await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return AddPartyTaxIdentityPersistenceOutcome.DuplicateOperation;
        }
        catch (DbUpdateException exception) when (IsConstraint(exception, "ux_tax_identities_active_company_identity"))
        {
            await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return AddPartyTaxIdentityPersistenceOutcome.DuplicateTaxIdentity;
        }
    }

    private static bool IsConstraint(DbUpdateException exception, string constraintName) =>
        exception.InnerException is PostgresException postgres &&
        postgres.SqlState == PostgresErrorCodes.UniqueViolation &&
        string.Equals(postgres.ConstraintName, constraintName, StringComparison.Ordinal);
}
