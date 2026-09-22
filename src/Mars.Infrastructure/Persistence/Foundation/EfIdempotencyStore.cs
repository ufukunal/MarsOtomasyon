using Mars.Application.Foundation.Idempotency;
using Microsoft.EntityFrameworkCore;

namespace Mars.Infrastructure.Persistence.Foundation;

public sealed class EfIdempotencyStore(MarsDbContext dbContext) : IIdempotencyStore
{
    public void Add(IdempotencyOperation operation)
    {
        ArgumentNullException.ThrowIfNull(operation);

        dbContext.Add(new IdempotencyOperationRecord
        {
            Scope = operation.Scope,
            OperationKey = operation.OperationKey,
            RequestFingerprint = operation.RequestFingerprint,
            Status = IdempotencyOperationStatus.InProgress,
            CreatedAt = operation.CreatedAt
        });
    }

    public Task<bool> MarkSucceededAsync(
        string scope,
        string operationKey,
        string? resultCode,
        DateTimeOffset completedAt,
        CancellationToken cancellationToken) =>
        MarkCompletedAsync(
            scope,
            operationKey,
            IdempotencyOperationStatus.Succeeded,
            resultCode,
            completedAt,
            cancellationToken);

    public Task<bool> MarkFailedAsync(
        string scope,
        string operationKey,
        string? resultCode,
        DateTimeOffset completedAt,
        CancellationToken cancellationToken) =>
        MarkCompletedAsync(
            scope,
            operationKey,
            IdempotencyOperationStatus.Failed,
            resultCode,
            completedAt,
            cancellationToken);

    private async Task<bool> MarkCompletedAsync(
        string scope,
        string operationKey,
        IdempotencyOperationStatus status,
        string? resultCode,
        DateTimeOffset completedAt,
        CancellationToken cancellationToken)
    {
        if (string.IsNullOrWhiteSpace(scope)) throw new ArgumentException("Scope is required.", nameof(scope));
        if (string.IsNullOrWhiteSpace(operationKey)) throw new ArgumentException("Operation key is required.", nameof(operationKey));

        var affected = await dbContext.Set<IdempotencyOperationRecord>()
            .Where(x =>
                x.Scope == scope &&
                x.OperationKey == operationKey &&
                x.Status == IdempotencyOperationStatus.InProgress)
            .ExecuteUpdateAsync(
                setters => setters
                    .SetProperty(x => x.Status, status)
                    .SetProperty(x => x.ResultCode, Normalize(resultCode))
                    .SetProperty(x => x.CompletedAt, completedAt),
                cancellationToken);

        return affected == 1;
    }

    private static string? Normalize(string? value) =>
        string.IsNullOrWhiteSpace(value) ? null : value.Trim();
}
