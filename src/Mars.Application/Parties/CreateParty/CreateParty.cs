using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Foundation.Results;
using Mars.Domain.Parties;

namespace Mars.Application.Parties.CreateParty;

public sealed record CreatePartyCommand(
    string PartyCode,
    string Kind,
    string LegalName,
    string? DisplayName,
    string OperationKey);

public sealed record CreatePartyReceipt(
    Guid PublicId,
    string PartyCode,
    string Kind,
    string LegalName,
    string? DisplayName,
    string State,
    long Version,
    string CorrelationId);

public enum CreatePartyPersistenceOutcome
{
    Created = 1,
    DuplicateOperation = 2,
    DuplicatePartyCode = 3
}

public sealed record CreatePartyWrite(
    Party Party,
    IdempotencyOperation Idempotency,
    AuditEntry Audit);

public interface IPartyCreatePersistence
{
    Task<CreatePartyPersistenceOutcome> PersistAsync(
        CreatePartyWrite write,
        CancellationToken cancellationToken);
}

public sealed class CreatePartyHandler(
    IPermissionEvaluator permissionEvaluator,
    IPartyCreatePersistence persistence)
{
    private const int MaxOperationKeyLength = 200;

    public async Task<Result<CreatePartyReceipt>> ExecuteAsync(
        CreatePartyCommand command,
        IExecutionContext executionContext,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(command);
        ArgumentNullException.ThrowIfNull(executionContext);
        cancellationToken.ThrowIfCancellationRequested();

        var authorized = await permissionEvaluator.IsGrantedAsync(
            executionContext.ActorId,
            executionContext.CompanyId,
            PartyPermissions.Create,
            cancellationToken);

        if (!authorized)
        {
            return Result<CreatePartyReceipt>.Failure(new ApplicationError(
                ErrorCategory.Authorization,
                "authorization.permission_denied",
                "The current actor is not authorized to create a Party."));
        }

        var operationKey = command.OperationKey ?? string.Empty;
        if (string.IsNullOrWhiteSpace(operationKey))
        {
            return Validation("parties.create.idempotency_key_required", "Idempotency-Key is required.");
        }

        if (!string.Equals(operationKey, operationKey.Trim(), StringComparison.Ordinal))
        {
            return Validation("parties.create.idempotency_key_invalid", "Idempotency-Key cannot contain leading or trailing whitespace.");
        }

        if (operationKey.Length > MaxOperationKeyLength)
        {
            return Validation(
                "parties.create.idempotency_key_too_long",
                $"Idempotency-Key cannot exceed {MaxOperationKeyLength} characters.");
        }

        if (string.IsNullOrWhiteSpace(command.PartyCode))
        {
            return Validation("parties.party_code_required", "Party Code is required.");
        }

        if (!string.Equals(command.PartyCode, command.PartyCode.Trim(), StringComparison.Ordinal))
        {
            return Validation("parties.party_code_whitespace", "Party Code cannot contain leading or trailing whitespace.");
        }

        if (string.IsNullOrWhiteSpace(command.LegalName))
        {
            return Validation("parties.legal_name_required", "Legal name is required.");
        }

        if (!string.Equals(command.LegalName, command.LegalName.Trim(), StringComparison.Ordinal))
        {
            return Validation("parties.legal_name_whitespace", "Legal name cannot contain leading or trailing whitespace.");
        }

        string? displayName = command.DisplayName;
        if (displayName is not null && string.IsNullOrWhiteSpace(displayName))
        {
            displayName = null;
        }
        else if (displayName is not null &&
                 !string.Equals(displayName, displayName.Trim(), StringComparison.Ordinal))
        {
            return Validation("parties.display_name_whitespace", "Display name cannot contain leading or trailing whitespace.");
        }

        var kind = command.Kind switch
        {
            "PERSON" => PartyKind.Person,
            "ORGANIZATION" => PartyKind.Organization,
            _ => (PartyKind?)null
        };

        if (kind is null)
        {
            return Validation("parties.kind_invalid", "Party kind must be PERSON or ORGANIZATION.");
        }

        var occurredAt = DateTimeOffset.UtcNow;
        var publicId = Guid.NewGuid();
        var party = Party.Create(
            publicId,
            executionContext.CompanyId,
            command.PartyCode,
            kind.Value,
            command.LegalName,
            displayName,
            occurredAt);

        var write = new CreatePartyWrite(
            party,
            new IdempotencyOperation(
                $"parties.create:{executionContext.CompanyId:D}",
                operationKey,
                requestFingerprint: null,
                occurredAt),
            new AuditEntry(
                executionContext.ActorId,
                executionContext.CompanyId,
                executionContext.BranchId,
                executionContext.CorrelationId.Value,
                "Parties",
                "PartyCreated",
                "Party",
                publicId,
                reason: null,
                occurredAt));

        var outcome = await persistence.PersistAsync(write, cancellationToken);
        if (outcome == CreatePartyPersistenceOutcome.DuplicateOperation)
        {
            return Result<CreatePartyReceipt>.Failure(new ApplicationError(
                ErrorCategory.Conflict,
                "parties.create.duplicate_operation",
                "The idempotency key has already been used for Party creation in this company."));
        }

        if (outcome == CreatePartyPersistenceOutcome.DuplicatePartyCode)
        {
            return Result<CreatePartyReceipt>.Failure(new ApplicationError(
                ErrorCategory.Conflict,
                "parties.party_code_conflict",
                "Party Code already exists in this company."));
        }

        return Result<CreatePartyReceipt>.Success(new CreatePartyReceipt(
            party.PublicId,
            party.PartyCode,
            party.Kind == PartyKind.Person ? "PERSON" : "ORGANIZATION",
            party.LegalName,
            party.DisplayName,
            "ACTIVE",
            party.Version,
            executionContext.CorrelationId.Value));
    }

    private static Result<CreatePartyReceipt> Validation(string code, string message) =>
        Result<CreatePartyReceipt>.Failure(new ApplicationError(ErrorCategory.Validation, code, message));
}
