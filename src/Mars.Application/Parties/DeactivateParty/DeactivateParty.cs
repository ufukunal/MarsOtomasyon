using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Foundation.Results;

namespace Mars.Application.Parties.DeactivateParty;

public sealed record DeactivatePartyCommand(
    Guid PartyPublicId,
    long ExpectedVersion,
    string Reason,
    string OperationKey);

public sealed record DeactivatePartyReceipt(
    Guid PartyPublicId,
    string State,
    long Version,
    string CorrelationId);

public enum DeactivatePartyPersistenceOutcome
{
    Deactivated = 1,
    PartyNotFound = 2,
    DuplicateOperation = 3,
    StaleVersion = 4,
    AlreadyInactive = 5,
    MergedStateConflict = 6
}

public sealed record DeactivatePartyPersistenceResult(
    DeactivatePartyPersistenceOutcome Outcome,
    long? Version = null);

public sealed record DeactivatePartyWrite(
    Guid PartyPublicId,
    Guid CompanyId,
    long ExpectedVersion,
    IdempotencyOperation Idempotency,
    AuditEntry Audit);

public interface IPartyDeactivatePersistence
{
    Task<DeactivatePartyPersistenceResult> DeactivateAsync(
        DeactivatePartyWrite write,
        CancellationToken cancellationToken);
}

public sealed class DeactivatePartyHandler(
    IPermissionEvaluator permissionEvaluator,
    IPartyDeactivatePersistence persistence)
{
    private const int MaxOperationKeyLength = 200;
    private const int MaxReasonLength = 1000;

    public async Task<Result<DeactivatePartyReceipt>> ExecuteAsync(
        DeactivatePartyCommand command,
        IExecutionContext executionContext,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(command);
        ArgumentNullException.ThrowIfNull(executionContext);
        cancellationToken.ThrowIfCancellationRequested();

        var authorized = await permissionEvaluator.IsGrantedAsync(
            executionContext.ActorId,
            executionContext.CompanyId,
            PartyPermissions.Deactivate,
            cancellationToken);

        if (!authorized)
        {
            return Failure(
                ErrorCategory.Authorization,
                "authorization.permission_denied",
                "The current actor is not authorized to deactivate a Party.");
        }

        if (command.PartyPublicId == Guid.Empty)
        {
            return Validation(
                "parties.deactivate.party_required",
                "Party public id is required.");
        }

        if (command.ExpectedVersion <= 0)
        {
            return Validation(
                "parties.deactivate.version_invalid",
                "Expected Party version must be positive.");
        }

        var operationKey = command.OperationKey ?? string.Empty;
        if (string.IsNullOrWhiteSpace(operationKey))
        {
            return Validation(
                "parties.deactivate.idempotency_key_required",
                "Idempotency-Key is required.");
        }

        if (!string.Equals(operationKey, operationKey.Trim(), StringComparison.Ordinal))
        {
            return Validation(
                "parties.deactivate.idempotency_key_invalid",
                "Idempotency-Key cannot contain leading or trailing whitespace.");
        }

        if (operationKey.Length > MaxOperationKeyLength)
        {
            return Validation(
                "parties.deactivate.idempotency_key_too_long",
                $"Idempotency-Key cannot exceed {MaxOperationKeyLength} characters.");
        }

        var reason = command.Reason ?? string.Empty;
        if (string.IsNullOrWhiteSpace(reason))
        {
            return Validation(
                "parties.deactivate.reason_required",
                "A reason is required to deactivate a Party.");
        }

        if (!string.Equals(reason, reason.Trim(), StringComparison.Ordinal))
        {
            reason = reason.Trim();
        }

        if (reason.Length > MaxReasonLength)
        {
            return Validation(
                "parties.deactivate.reason_too_long",
                $"Party deactivation reason cannot exceed {MaxReasonLength} characters.");
        }

        var occurredAt = DateTimeOffset.UtcNow;
        var write = new DeactivatePartyWrite(
            command.PartyPublicId,
            executionContext.CompanyId,
            command.ExpectedVersion,
            new IdempotencyOperation(
                $"parties.deactivate:{executionContext.CompanyId:D}",
                operationKey,
                requestFingerprint: null,
                occurredAt),
            new AuditEntry(
                executionContext.ActorId,
                executionContext.CompanyId,
                executionContext.BranchId,
                executionContext.CorrelationId.Value,
                "Parties",
                "PartyDeactivated",
                "Party",
                command.PartyPublicId,
                reason,
                occurredAt));

        var persistenceResult = await persistence.DeactivateAsync(write, cancellationToken);

        return persistenceResult.Outcome switch
        {
            DeactivatePartyPersistenceOutcome.PartyNotFound => Failure(
                ErrorCategory.NotFound,
                "parties.party_not_found",
                "Party was not found in the current company."),
            DeactivatePartyPersistenceOutcome.DuplicateOperation => Failure(
                ErrorCategory.Conflict,
                "parties.deactivate.duplicate_operation",
                "The idempotency key has already been used for Party deactivation in this company."),
            DeactivatePartyPersistenceOutcome.StaleVersion => Failure(
                ErrorCategory.Concurrency,
                "parties.deactivate.stale_version",
                "The Party changed after the supplied version was read."),
            DeactivatePartyPersistenceOutcome.AlreadyInactive => Failure(
                ErrorCategory.Conflict,
                "parties.deactivate.already_inactive",
                "The Party is already INACTIVE."),
            DeactivatePartyPersistenceOutcome.MergedStateConflict => Failure(
                ErrorCategory.Conflict,
                "parties.deactivate.merged_state",
                "A MERGED Party cannot be deactivated."),
            DeactivatePartyPersistenceOutcome.Deactivated => Result<DeactivatePartyReceipt>.Success(
                new DeactivatePartyReceipt(
                    command.PartyPublicId,
                    "INACTIVE",
                    persistenceResult.Version
                        ?? throw new InvalidOperationException("Deactivated Party did not return a version."),
                    executionContext.CorrelationId.Value)),
            _ => throw new ArgumentOutOfRangeException()
        };
    }

    private static Result<DeactivatePartyReceipt> Validation(string code, string message) =>
        Failure(ErrorCategory.Validation, code, message);

    private static Result<DeactivatePartyReceipt> Failure(
        ErrorCategory category,
        string code,
        string message) =>
        Result<DeactivatePartyReceipt>.Failure(new ApplicationError(category, code, message));
}
