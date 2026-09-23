using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Foundation.Results;
using Mars.Domain.Parties;

namespace Mars.Application.Parties.AddPartyTaxIdentity;

public sealed record AddPartyTaxIdentityCommand(
    Guid PartyPublicId,
    string Jurisdiction,
    string Scheme,
    string Value,
    string OperationKey);

public sealed record AddPartyTaxIdentityReceipt(
    Guid PublicId,
    Guid PartyPublicId,
    string Jurisdiction,
    string Scheme,
    string State,
    long Version,
    string CorrelationId);

public enum AddPartyTaxIdentityPersistenceOutcome
{
    Added = 1,
    PartyNotFound = 2,
    DuplicateOperation = 3,
    DuplicateTaxIdentity = 4
}

public sealed record AddPartyTaxIdentityWrite(
    PartyTaxIdentity TaxIdentity,
    IdempotencyOperation Idempotency,
    AuditEntry Audit);

public interface IPartyTaxIdentityAddPersistence
{
    Task<AddPartyTaxIdentityPersistenceOutcome> PersistAsync(
        AddPartyTaxIdentityWrite write,
        CancellationToken cancellationToken);
}

public sealed class AddPartyTaxIdentityHandler(
    IPermissionEvaluator permissionEvaluator,
    IPartyTaxIdentityAddPersistence persistence)
{
    private const int MaxOperationKeyLength = 200;

    public async Task<Result<AddPartyTaxIdentityReceipt>> ExecuteAsync(
        AddPartyTaxIdentityCommand command,
        IExecutionContext executionContext,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(command);
        ArgumentNullException.ThrowIfNull(executionContext);
        cancellationToken.ThrowIfCancellationRequested();

        var authorized = await permissionEvaluator.IsGrantedAsync(
            executionContext.ActorId,
            executionContext.CompanyId,
            PartyPermissions.TaxIdentityManage,
            cancellationToken);

        if (!authorized)
        {
            return Result<AddPartyTaxIdentityReceipt>.Failure(new ApplicationError(
                ErrorCategory.Authorization,
                "authorization.permission_denied",
                "The current actor is not authorized to manage Party tax identities."));
        }

        if (command.PartyPublicId == Guid.Empty)
        {
            return Validation("parties.tax_identity.party_required", "Party public id is required.");
        }

        var operationKey = command.OperationKey ?? string.Empty;
        if (string.IsNullOrWhiteSpace(operationKey))
        {
            return Validation("parties.tax_identity.idempotency_key_required", "Idempotency-Key is required.");
        }

        if (!string.Equals(operationKey, operationKey.Trim(), StringComparison.Ordinal))
        {
            return Validation(
                "parties.tax_identity.idempotency_key_invalid",
                "Idempotency-Key cannot contain leading or trailing whitespace.");
        }

        if (operationKey.Length > MaxOperationKeyLength)
        {
            return Validation(
                "parties.tax_identity.idempotency_key_too_long",
                $"Idempotency-Key cannot exceed {MaxOperationKeyLength} characters.");
        }

        if (!string.Equals(command.Jurisdiction, "TR", StringComparison.Ordinal))
        {
            return Validation(
                "parties.tax_identity.jurisdiction_invalid",
                "PARTY-IMP-003 accepts jurisdiction TR only.");
        }

        var scheme = command.Scheme switch
        {
            "VKN" => PartyTaxIdentityScheme.Vkn,
            "TCKN" => PartyTaxIdentityScheme.Tckn,
            _ => (PartyTaxIdentityScheme?)null
        };

        if (scheme is null)
        {
            return Validation(
                "parties.tax_identity.scheme_invalid",
                "Tax identity scheme must be VKN or TCKN.");
        }

        var value = command.Value ?? string.Empty;
        if (value.Length == 0 || value.Any(character => character is < '0' or > '9'))
        {
            return Validation(
                "parties.tax_identity.value_invalid",
                "Tax identity value must contain ASCII digits only.");
        }

        var expectedLength = scheme == PartyTaxIdentityScheme.Vkn ? 10 : 11;
        if (value.Length != expectedLength)
        {
            return Validation(
                "parties.tax_identity.value_length",
                $"{command.Scheme} value must contain exactly {expectedLength} digits.");
        }

        var occurredAt = DateTimeOffset.UtcNow;
        var publicId = Guid.NewGuid();
        var taxIdentity = PartyTaxIdentity.Create(
            publicId,
            command.PartyPublicId,
            executionContext.CompanyId,
            command.Jurisdiction,
            scheme.Value,
            value,
            occurredAt);

        var schemeCode = scheme == PartyTaxIdentityScheme.Vkn ? "VKN" : "TCKN";
        var write = new AddPartyTaxIdentityWrite(
            taxIdentity,
            new IdempotencyOperation(
                $"parties.tax_identity.add:{executionContext.CompanyId:D}",
                operationKey,
                requestFingerprint: null,
                occurredAt),
            new AuditEntry(
                executionContext.ActorId,
                executionContext.CompanyId,
                executionContext.BranchId,
                executionContext.CorrelationId.Value,
                "Parties",
                $"PartyTaxIdentityAdded.{schemeCode}",
                "Party",
                command.PartyPublicId,
                reason: null,
                occurredAt));

        var outcome = await persistence.PersistAsync(write, cancellationToken);

        if (outcome == AddPartyTaxIdentityPersistenceOutcome.PartyNotFound)
        {
            return Result<AddPartyTaxIdentityReceipt>.Failure(new ApplicationError(
                ErrorCategory.NotFound,
                "parties.party_not_found",
                "Party was not found in the current company."));
        }

        if (outcome == AddPartyTaxIdentityPersistenceOutcome.DuplicateOperation)
        {
            return Result<AddPartyTaxIdentityReceipt>.Failure(new ApplicationError(
                ErrorCategory.Conflict,
                "parties.tax_identity.duplicate_operation",
                "The idempotency key has already been used for Party tax identity creation in this company."));
        }

        if (outcome == AddPartyTaxIdentityPersistenceOutcome.DuplicateTaxIdentity)
        {
            return Result<AddPartyTaxIdentityReceipt>.Failure(new ApplicationError(
                ErrorCategory.Conflict,
                "parties.tax_identity.conflict",
                "The active tax identity already exists in this company."));
        }

        return Result<AddPartyTaxIdentityReceipt>.Success(new AddPartyTaxIdentityReceipt(
            taxIdentity.PublicId,
            taxIdentity.PartyPublicId,
            taxIdentity.Jurisdiction,
            schemeCode,
            "ACTIVE",
            taxIdentity.Version,
            executionContext.CorrelationId.Value));
    }

    private static Result<AddPartyTaxIdentityReceipt> Validation(string code, string message) =>
        Result<AddPartyTaxIdentityReceipt>.Failure(
            new ApplicationError(ErrorCategory.Validation, code, message));
}
