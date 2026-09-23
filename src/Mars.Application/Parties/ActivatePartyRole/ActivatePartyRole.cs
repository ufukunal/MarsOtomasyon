using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Foundation.Results;
using Mars.Domain.Parties;

namespace Mars.Application.Parties.ActivatePartyRole;

public sealed record ActivatePartyRoleCommand(
    Guid PartyPublicId,
    string Role,
    string OperationKey);

public sealed record ActivatePartyRoleReceipt(
    Guid PartyPublicId,
    string Role,
    string State,
    long Version,
    string CorrelationId);

public enum ActivatePartyRolePersistenceOutcome
{
    Activated = 1,
    PartyNotFound = 2,
    DuplicateOperation = 3,
    DuplicateRole = 4
}

public sealed record ActivatePartyRoleWrite(
    PartyRole Role,
    IdempotencyOperation Idempotency,
    AuditEntry Audit);

public interface IPartyRoleActivationPersistence
{
    Task<ActivatePartyRolePersistenceOutcome> PersistAsync(
        ActivatePartyRoleWrite write,
        CancellationToken cancellationToken);
}

public sealed class ActivatePartyRoleHandler(
    IPermissionEvaluator permissionEvaluator,
    IPartyRoleActivationPersistence persistence)
{
    private const int MaxOperationKeyLength = 200;

    public async Task<Result<ActivatePartyRoleReceipt>> ExecuteAsync(
        ActivatePartyRoleCommand command,
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
            return Result<ActivatePartyRoleReceipt>.Failure(new ApplicationError(
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
            return Validation(
                "parties.role.invalid",
                "Party role must be CUSTOMER or SUPPLIER.");
        }

        var occurredAt = DateTimeOffset.UtcNow;
        var role = PartyRole.Create(
            command.PartyPublicId,
            executionContext.CompanyId,
            roleType.Value,
            occurredAt);

        var roleCode = roleType == PartyRoleType.Customer ? "CUSTOMER" : "SUPPLIER";
        var write = new ActivatePartyRoleWrite(
            role,
            new IdempotencyOperation(
                $"parties.role.activate:{executionContext.CompanyId:D}",
                operationKey,
                requestFingerprint: null,
                occurredAt),
            new AuditEntry(
                executionContext.ActorId,
                executionContext.CompanyId,
                executionContext.BranchId,
                executionContext.CorrelationId.Value,
                "Parties",
                $"PartyRoleActivated.{roleCode}",
                "Party",
                command.PartyPublicId,
                reason: null,
                occurredAt));

        var outcome = await persistence.PersistAsync(write, cancellationToken);
        if (outcome == ActivatePartyRolePersistenceOutcome.PartyNotFound)
        {
            return Result<ActivatePartyRoleReceipt>.Failure(new ApplicationError(
                ErrorCategory.NotFound,
                "parties.party_not_found",
                "Party was not found in the current company."));
        }

        if (outcome == ActivatePartyRolePersistenceOutcome.DuplicateOperation)
        {
            return Result<ActivatePartyRoleReceipt>.Failure(new ApplicationError(
                ErrorCategory.Conflict,
                "parties.role.duplicate_operation",
                "The idempotency key has already been used for Party role activation in this company."));
        }

        if (outcome == ActivatePartyRolePersistenceOutcome.DuplicateRole)
        {
            return Result<ActivatePartyRoleReceipt>.Failure(new ApplicationError(
                ErrorCategory.Conflict,
                "parties.role.conflict",
                "This Party already has the requested role."));
        }

        return Result<ActivatePartyRoleReceipt>.Success(new ActivatePartyRoleReceipt(
            command.PartyPublicId,
            roleCode,
            "ACTIVE",
            role.Version,
            executionContext.CorrelationId.Value));
    }

    private static Result<ActivatePartyRoleReceipt> Validation(string code, string message) =>
        Result<ActivatePartyRoleReceipt>.Failure(
            new ApplicationError(ErrorCategory.Validation, code, message));
}
