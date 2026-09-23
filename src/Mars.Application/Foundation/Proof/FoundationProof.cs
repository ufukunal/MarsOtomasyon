using System.Text.Json;
using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Foundation.Outbox;
using Mars.Application.Foundation.Results;

namespace Mars.Application.Foundation.Proof;

public sealed record FoundationProofCommand(string OperationKey);

public sealed record FoundationProofReceipt(
    Guid EventId,
    string OperationKey,
    string CorrelationId);

public enum FoundationProofPersistenceOutcome
{
    Created = 1,
    Duplicate = 2
}

public sealed record FoundationProofWrite(
    IdempotencyOperation Idempotency,
    AuditEntry Audit,
    OutboxMessage Outbox);

public interface IFoundationProofPersistence
{
    Task<FoundationProofPersistenceOutcome> PersistAsync(
        FoundationProofWrite write,
        CancellationToken cancellationToken);
}

public sealed class FoundationProofHandler(IFoundationProofPersistence persistence)
{
    private const int MaxOperationKeyLength = 200;
    private const string ModuleName = "Foundation";
    private const string EventType = "Foundation.ProofExecuted";

    public async Task<Result<FoundationProofReceipt>> ExecuteAsync(
        FoundationProofCommand command,
        IExecutionContext executionContext,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(command);
        ArgumentNullException.ThrowIfNull(executionContext);
        cancellationToken.ThrowIfCancellationRequested();

        var operationKey = command.OperationKey?.Trim() ?? string.Empty;
        if (operationKey.Length == 0)
        {
            return Result<FoundationProofReceipt>.Failure(new ApplicationError(
                ErrorCategory.Validation,
                "foundation.proof.idempotency_key_required",
                "Idempotency-Key is required."));
        }

        if (operationKey.Length > MaxOperationKeyLength)
        {
            return Result<FoundationProofReceipt>.Failure(new ApplicationError(
                ErrorCategory.Validation,
                "foundation.proof.idempotency_key_too_long",
                $"Idempotency-Key cannot exceed {MaxOperationKeyLength} characters."));
        }

        var occurredAt = DateTimeOffset.UtcNow;
        var eventId = Guid.NewGuid();
        var scope = $"foundation.proof:{executionContext.CompanyId:D}";
        var payload = JsonSerializer.Serialize(new
        {
            eventId,
            companyId = executionContext.CompanyId,
            branchId = executionContext.BranchId,
            correlationId = executionContext.CorrelationId.Value
        });

        var write = new FoundationProofWrite(
            new IdempotencyOperation(
                scope,
                operationKey,
                requestFingerprint: null,
                occurredAt),
            new AuditEntry(
                executionContext.ActorId,
                executionContext.CompanyId,
                executionContext.BranchId,
                executionContext.CorrelationId.Value,
                ModuleName,
                "VerticalProofExecuted",
                "FoundationProof",
                eventId,
                reason: null,
                occurredAt),
            new OutboxMessage(
                eventId,
                EventType,
                ModuleName,
                eventId,
                payloadSchemaVersion: 1,
                payload,
                occurredAt,
                occurredAt));

        var outcome = await persistence.PersistAsync(write, cancellationToken);
        if (outcome == FoundationProofPersistenceOutcome.Duplicate)
        {
            return Result<FoundationProofReceipt>.Failure(new ApplicationError(
                ErrorCategory.Conflict,
                "foundation.proof.duplicate",
                "The idempotency key has already been used for this company."));
        }

        return Result<FoundationProofReceipt>.Success(new FoundationProofReceipt(
            eventId,
            operationKey,
            executionContext.CorrelationId.Value));
    }
}
