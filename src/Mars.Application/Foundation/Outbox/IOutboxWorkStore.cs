namespace Mars.Application.Foundation.Outbox;

public interface IOutboxWorkStore
{
    Task<IReadOnlyList<OutboxWorkItem>> ClaimAsync(
        int maxBatchSize,
        DateTimeOffset now,
        TimeSpan leaseDuration,
        CancellationToken cancellationToken);

    Task<bool> MarkProcessedAsync(
        Guid eventId,
        Guid claimToken,
        DateTimeOffset processedAt,
        CancellationToken cancellationToken);

    Task<bool> MarkRetryAsync(
        Guid eventId,
        Guid claimToken,
        DateTimeOffset availableAt,
        string errorCode,
        CancellationToken cancellationToken);

    Task<bool> MarkFailedAsync(
        Guid eventId,
        Guid claimToken,
        string errorCode,
        CancellationToken cancellationToken);
}
