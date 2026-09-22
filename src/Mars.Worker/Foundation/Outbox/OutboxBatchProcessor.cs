using Mars.Application.Foundation.Outbox;

namespace Mars.Worker.Foundation.Outbox;

public sealed class OutboxBatchProcessor(
    IOutboxWorkStore workStore,
    IOutboxDispatcher dispatcher,
    int batchSize,
    TimeSpan leaseDuration)
{
    public async Task<int> ProcessOnceAsync(
        DateTimeOffset now,
        CancellationToken cancellationToken)
    {
        if (batchSize <= 0) throw new ArgumentOutOfRangeException(nameof(batchSize));
        if (leaseDuration <= TimeSpan.Zero) throw new ArgumentOutOfRangeException(nameof(leaseDuration));

        var workItems = await workStore.ClaimAsync(
            batchSize,
            now,
            leaseDuration,
            cancellationToken);

        var completed = 0;

        foreach (var item in workItems)
        {
            cancellationToken.ThrowIfCancellationRequested();

            var result = await dispatcher.DispatchAsync(item, cancellationToken);

            bool stateChanged;
            if (result.Succeeded)
            {
                stateChanged = await workStore.MarkProcessedAsync(
                    item.EventId,
                    item.ClaimToken,
                    now,
                    cancellationToken);
            }
            else if (result.Retry)
            {
                stateChanged = await workStore.MarkRetryAsync(
                    item.EventId,
                    item.ClaimToken,
                    result.RetryAt ?? throw new InvalidOperationException("Retry result requires retry time."),
                    result.ErrorCode ?? throw new InvalidOperationException("Retry result requires error code."),
                    cancellationToken);
            }
            else
            {
                stateChanged = await workStore.MarkFailedAsync(
                    item.EventId,
                    item.ClaimToken,
                    result.ErrorCode ?? throw new InvalidOperationException("Failed result requires error code."),
                    cancellationToken);
            }

            if (!stateChanged)
            {
                throw new InvalidOperationException(
                    $"Outbox claim ownership was lost for event {item.EventId}.");
            }

            completed++;
        }

        return completed;
    }
}
