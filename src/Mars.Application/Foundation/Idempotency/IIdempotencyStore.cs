namespace Mars.Application.Foundation.Idempotency;

public interface IIdempotencyStore
{
    void Add(IdempotencyOperation operation);

    Task<bool> MarkSucceededAsync(
        string scope,
        string operationKey,
        string? resultCode,
        DateTimeOffset completedAt,
        CancellationToken cancellationToken);

    Task<bool> MarkFailedAsync(
        string scope,
        string operationKey,
        string? resultCode,
        DateTimeOffset completedAt,
        CancellationToken cancellationToken);
}
