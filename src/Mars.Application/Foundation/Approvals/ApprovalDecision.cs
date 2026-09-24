using System.Text.Json;
using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Foundation.Outbox;
using Mars.Application.Foundation.Results;

namespace Mars.Application.Foundation.Approvals;

public enum ApprovalDecisionKind
{
    Approved = 1,
    Rejected = 2
}

public enum ApprovalDecisionPersistenceOutcome
{
    Succeeded = 1,
    DuplicateOperation = 2,
    DeterministicConflict = 3
}

public sealed record ApprovalDecisionCommand(
    string Module,
    string EntityType,
    Guid EntityPublicId,
    long SnapshotVersion,
    Guid CreatorActorId,
    ApprovalDecisionKind Decision,
    string? Reason,
    string OperationKey);

public sealed record ApprovalDecisionReceipt(
    Guid PublicId,
    ApprovalDecisionKind Decision,
    long SnapshotVersion,
    string CorrelationId);

public sealed record ApprovalDecisionWrite(
    Guid PublicId,
    ApprovalDecisionCommand Command,
    Guid CompanyId,
    Guid ActorId,
    DateTimeOffset OccurredAt,
    AuditEntry Audit,
    IdempotencyOperation Idempotency,
    OutboxMessage Outbox);

public sealed record ApprovalDecisionPersistenceResult(
    ApprovalDecisionPersistenceOutcome Outcome,
    Guid? PublicId = null);

public interface IApprovalDecisionPersistence
{
    Task<ApprovalDecisionPersistenceResult> RecordAsync(
        ApprovalDecisionWrite write,
        CancellationToken cancellationToken);

    Task<bool> IsApprovedAsync(
        Guid companyId,
        string module,
        string entityType,
        Guid entityPublicId,
        long snapshotVersion,
        CancellationToken cancellationToken);
}

public interface IApprovalDecisionAuthority
{
    Task<Result<ApprovalDecisionReceipt>> DecideAsync(
        ApprovalDecisionCommand command,
        IExecutionContext context,
        CancellationToken cancellationToken);

    Task<bool> IsApprovedAsync(
        Guid companyId,
        string module,
        string entityType,
        Guid entityPublicId,
        long snapshotVersion,
        CancellationToken cancellationToken);
}

public sealed class ApprovalDecisionAuthority(IApprovalDecisionPersistence persistence)
    : IApprovalDecisionAuthority
{
    public async Task<Result<ApprovalDecisionReceipt>> DecideAsync(
        ApprovalDecisionCommand command,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(command);
        ArgumentNullException.ThrowIfNull(context);

        if (string.IsNullOrWhiteSpace(command.Module) ||
            string.IsNullOrWhiteSpace(command.EntityType) ||
            command.EntityPublicId == Guid.Empty ||
            command.SnapshotVersion <= 0 ||
            command.CreatorActorId == Guid.Empty ||
            !Enum.IsDefined(command.Decision) ||
            string.IsNullOrWhiteSpace(command.OperationKey))
        {
            return Result<ApprovalDecisionReceipt>.Failure(
                new ApplicationError(
                    ErrorCategory.Validation,
                    "approval.decision.invalid",
                    "Module, entity, exact snapshot, creator and operation key are required."));
        }

        if (command.Decision == ApprovalDecisionKind.Approved &&
            command.CreatorActorId == context.ActorId)
        {
            return Result<ApprovalDecisionReceipt>.Failure(
                new ApplicationError(
                    ErrorCategory.Authorization,
                    "approval.sod.creator_cannot_approve",
                    "The creator cannot approve the same approval-required snapshot."));
        }

        var publicId = Guid.NewGuid();
        var now = DateTimeOffset.UtcNow;
        var module = command.Module.Trim();
        var entityType = command.EntityType.Trim();
        var reason = string.IsNullOrWhiteSpace(command.Reason) ? null : command.Reason.Trim();
        var action = command.Decision == ApprovalDecisionKind.Approved
            ? "ApprovalApproved"
            : "ApprovalRejected";
        var eventType = command.Decision == ApprovalDecisionKind.Approved
            ? "Foundation.ApprovalApproved"
            : "Foundation.ApprovalRejected";

        var write = new ApprovalDecisionWrite(
            publicId,
            command with { Module = module, EntityType = entityType, Reason = reason },
            context.CompanyId,
            context.ActorId,
            now,
            new AuditEntry(
                context.ActorId,
                context.CompanyId,
                context.BranchId,
                context.CorrelationId.Value,
                "Foundation",
                action,
                entityType,
                command.EntityPublicId,
                reason,
                now),
            new IdempotencyOperation(
                "foundation.approval.decision",
                command.OperationKey,
                null,
                now),
            new OutboxMessage(
                Guid.NewGuid(),
                eventType,
                "Foundation",
                command.EntityPublicId,
                1,
                JsonSerializer.Serialize(new
                {
                    module,
                    entityType,
                    entityPublicId = command.EntityPublicId,
                    snapshotVersion = command.SnapshotVersion,
                    decision = command.Decision.ToString()
                }),
                now,
                now));

        var result = await persistence.RecordAsync(write, cancellationToken);
        return result.Outcome switch
        {
            ApprovalDecisionPersistenceOutcome.Succeeded =>
                Result<ApprovalDecisionReceipt>.Success(
                    new ApprovalDecisionReceipt(
                        result.PublicId ?? publicId,
                        command.Decision,
                        command.SnapshotVersion,
                        context.CorrelationId.Value)),
            ApprovalDecisionPersistenceOutcome.DuplicateOperation =>
                Failure(ErrorCategory.Conflict, "approval.operation.duplicate", "Approval operation key already exists."),
            _ =>
                Failure(ErrorCategory.Conflict, "approval.decision.conflict", "An approval decision already exists for this exact snapshot.")
        };
    }

    public Task<bool> IsApprovedAsync(
        Guid companyId,
        string module,
        string entityType,
        Guid entityPublicId,
        long snapshotVersion,
        CancellationToken cancellationToken) =>
        persistence.IsApprovedAsync(
            companyId,
            module,
            entityType,
            entityPublicId,
            snapshotVersion,
            cancellationToken);

    private static Result<ApprovalDecisionReceipt> Failure(
        ErrorCategory category,
        string code,
        string message) =>
        Result<ApprovalDecisionReceipt>.Failure(new ApplicationError(category, code, message));
}
