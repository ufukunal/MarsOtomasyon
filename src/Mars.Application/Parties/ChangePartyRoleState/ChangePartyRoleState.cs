using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Foundation.Results;
using Mars.Domain.Parties;

namespace Mars.Application.Parties.ChangePartyRoleState;

public sealed record ChangePartyRoleStateCommand(
    Guid PartyPublicId,
    string Role,
    string State,
    long ExpectedVersion,
    string? Reason,
    string OperationKey);

public sealed record ChangePartyRoleStateReceipt(
    Guid PartyPublicId,
    string Role,
    string State,
    long Version,
    string CorrelationId);

public enum ChangePartyRoleStatePersistenceOutcome
{
    Changed = 1,
    PartyNotFound = 2,
    RoleNotFound = 3,
    DuplicateOperation = 4,
    StaleVersion = 5,
    AlreadyInTargetState = 6
}

public sealed record ChangePartyRoleStatePersistenceResult(
    ChangePartyRoleStatePersistenceOutcome Outcome,
    long? Version = null);

public sealed record ChangePartyRoleStateWrite(
    Guid PartyPublicId,
    Guid CompanyId,
    PartyRoleType RoleType,
    PartyRoleState TargetState,
    long ExpectedVersion,
    IdempotencyOperation Idempotency,
    AuditEntry Audit);

public interface IPartyRoleStatePersistence
{
    Task<ChangePartyRoleStatePersistenceResult> ChangeAsync(
        ChangePartyRoleStateWrite write,
        CancellationToken cancellationToken);
}

public sealed class ChangePartyRoleStateHandler(
    IPermissionEvaluator permissionEvaluator,
    IPartyRoleStatePersistence persistence)
{
    private const int MaxOperationKeyLength = 200;
    private const int MaxReasonLength = 1000;

    public async Task<Result<ChangePartyRoleStateReceipt>> ExecuteAsync(
        ChangePartyRoleStateCommand command,
        IExecutionContext executionContext,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(command);
        ArgumentNullException.ThrowIfNull(executionContext);
        cancellationToken.ThrowIfCancellationRequested();

        var authorized = await permissionEvaluator.IsGrantedAsync(
            executionContext.ActorId,
            executionContext.CompanyId,
            PartyPermissions.RoleManage,
            cancellationToken);

        if (!authorized)
        {
            return Result<ChangePartyRoleStateReceipt>.Failure(new ApplicationError(
                ErrorCategory.Authorization,
                "authorization.permission_denied",
                "The current actor is not authorized to manage Party roles."));
        }

        if (command.PartyPublicId == Guid.Empty)
        {
            return Validation("parties.role.party_required", "Party public id is required.");
        }

        var operationKey = command.OperationKey ?? string.Empty;
        if (string.IsNullOrWhiteSpace(operationKey))
        {
            return Validation("parties.role.idempotency_key_required", "Idempotency-Key is required.");
        }

        if (!string.Equals(operationKey, operationKey.Trim(), StringComparison.Ordinal))
        {
            return Validation(
                "parties.role.idempotency_key_invalid",
                "Idempotency-Key cannot contain leading or trailing whitespace.");
        }

        if (operationKey.Length > MaxOperationKeyLength)
        {
            return Validation(
                "parties.role.idempotency_key_too_long",
                $"Idempotency-Key cannot exceed {MaxOperationKeyLength} characters.");
        }

        var roleType = command.Role switch
        {
            "CUSTOMER" => PartyRoleType.Customer,
            "SUPPLIER" => PartyRoleType.Supplier,
            _ => (PartyRoleType?)null
        };

        if (roleType is null)
        {
            return Validation("parties.role.invalid", "Party role must be CUSTOMER or SUPPLIER.");
        }

        var targetState = command.State switch
        {
            "ACTIVE" => PartyRoleState.Active,
            "INACTIVE" => PartyRoleState.Inactive,
            _ => (PartyRoleState?)null
        };

        if (targetState is null)
        {
            return Validation("parties.role.state_invalid", "Party role state must be ACTIVE or INACTIVE.");
        }

        if (command.ExpectedVersion <= 0)
        {
            return Validation(
                "parties.role.version_invalid",
                "Expected Party role version must be positive.");
        }

        var reason = string.IsNullOrWhiteSpace(command.Reason)
            ? null
            : command.Reason.Trim();

        if (targetState == PartyRoleState.Inactive && reason is null)
        {
            return Validation(
                "parties.role.deactivation_reason_required",
                "A reason is required to deactivate a Party role.");
        }

        if (reason is not null && reason.Length > MaxReasonLength)
        {
            return Validation(
                "parties.role.reason_too_long",
                $"Party role state reason cannot exceed {MaxReasonLength} characters.");
        }

        var occurredAt = DateTimeOffset.UtcNow;
        var roleCode = roleType == PartyRoleType.Customer ? "CUSTOMER" : "SUPPLIER";
        var targetStateCode = targetState == PartyRoleState.Active ? "ACTIVE" : "INACTIVE";
        var action = targetState == PartyRoleState.Active
            ? $"PartyRoleReactivated.{roleCode}"
            : $"PartyRoleDeactivated.{roleCode}";

        var write = new ChangePartyRoleStateWrite(
            command.PartyPublicId,
            executionContext.CompanyId,
            roleType.Value,
            targetState.Value,
            command.ExpectedVersion,
            new IdempotencyOperation(
                $"parties.role.state:{executionContext.CompanyId:D}",
                operationKey,
                requestFingerprint: null,
                occurredAt),
            new AuditEntry(
                executionContext.ActorId,
                executionContext.CompanyId,
                executionContext.BranchId,
                executionContext.CorrelationId.Value,
                "Parties",
                action,
                "Party",
                command.PartyPublicId,
                reason,
                occurredAt));

        var persistenceResult = await persistence.ChangeAsync(write, cancellationToken);

        if (persistenceResult.Outcome == ChangePartyRoleStatePersistenceOutcome.PartyNotFound)
        {
            return Failure(
                ErrorCategory.NotFound,
                "parties.party_not_found",
                "Party was not found in the current company.");
        }

        if (persistenceResult.Outcome == ChangePartyRoleStatePersistenceOutcome.RoleNotFound)
        {
            return Failure(
                ErrorCategory.NotFound,
                "parties.role_not_found",
                "The requested Party role was not found.");
        }

        if (persistenceResult.Outcome == ChangePartyRoleStatePersistenceOutcome.DuplicateOperation)
        {
            return Failure(
                ErrorCategory.Conflict,
                "parties.role.duplicate_operation",
                "The idempotency key has already been used for a Party role state change in this company.");
        }

        if (persistenceResult.Outcome == ChangePartyRoleStatePersistenceOutcome.StaleVersion)
        {
            return Failure(
                ErrorCategory.Concurrency,
                "parties.role.stale_version",
                "The Party role changed after the supplied version was read.");
        }

        if (persistenceResult.Outcome == ChangePartyRoleStatePersistenceOutcome.AlreadyInTargetState)
        {
            return Failure(
                ErrorCategory.Conflict,
                "parties.role.state_conflict",
                "The Party role is already in the requested state.");
        }

        var version = persistenceResult.Version
            ?? throw new InvalidOperationException("Changed Party role state did not return a version.");

        return Result<ChangePartyRoleStateReceipt>.Success(new ChangePartyRoleStateReceipt(
            command.PartyPublicId,
            roleCode,
            targetStateCode,
            version,
            executionContext.CorrelationId.Value));
    }

    private static Result<ChangePartyRoleStateReceipt> Validation(string code, string message) =>
        Failure(ErrorCategory.Validation, code, message);

    private static Result<ChangePartyRoleStateReceipt> Failure(
        ErrorCategory category,
        string code,
        string message) =>
        Result<ChangePartyRoleStateReceipt>.Failure(
            new ApplicationError(category, code, message));
}
