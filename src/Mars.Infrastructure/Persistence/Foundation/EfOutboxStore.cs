using Mars.Application.Foundation.Outbox;
using Microsoft.EntityFrameworkCore;

namespace Mars.Infrastructure.Persistence.Foundation;

public sealed class EfOutboxStore(MarsDbContext dbContext) : IOutboxWriter, IOutboxWorkStore
{
    public void Enqueue(OutboxMessage message)
    {
        ArgumentNullException.ThrowIfNull(message);

        dbContext.Add(new OutboxMessageRecord
        {
            EventId = message.EventId,
            EventType = message.EventType,
            Module = message.Module,
            AggregatePublicId = message.AggregatePublicId,
            PayloadSchemaVersion = message.PayloadSchemaVersion,
            Payload = message.Payload,
            State = OutboxMessageState.Pending,
            CreatedAt = message.CreatedAt,
            AvailableAt = message.AvailableAt,
            AttemptCount = 0
        });
    }

    public async Task<IReadOnlyList<OutboxWorkItem>> ClaimAsync(
        int maxBatchSize,
        DateTimeOffset now,
        TimeSpan leaseDuration,
        CancellationToken cancellationToken)
    {
        if (maxBatchSize <= 0) throw new ArgumentOutOfRangeException(nameof(maxBatchSize));
        if (leaseDuration <= TimeSpan.Zero) throw new ArgumentOutOfRangeException(nameof(leaseDuration));

        var claimToken = Guid.NewGuid();
        var claimedUntil = now.Add(leaseDuration);

        var candidateIds = await dbContext.Set<OutboxMessageRecord>()
            .Where(x =>
                (x.State == OutboxMessageState.Pending && x.AvailableAt <= now) ||
                (x.State == OutboxMessageState.Processing && x.ClaimedUntil <= now))
            .OrderBy(x => x.AvailableAt)
            .ThenBy(x => x.Id)
            .Select(x => x.Id)
            .Take(maxBatchSize)
            .ToArrayAsync(cancellationToken);

        var claimedIds = new List<long>(candidateIds.Length);

        foreach (var id in candidateIds)
        {
            var affected = await dbContext.Set<OutboxMessageRecord>()
                .Where(x =>
                    x.Id == id &&
                    ((x.State == OutboxMessageState.Pending && x.AvailableAt <= now) ||
                     (x.State == OutboxMessageState.Processing && x.ClaimedUntil <= now)))
                .ExecuteUpdateAsync(
                    setters => setters
                        .SetProperty(x => x.State, OutboxMessageState.Processing)
                        .SetProperty(x => x.ClaimToken, claimToken)
                        .SetProperty(x => x.ClaimedUntil, claimedUntil)
                        .SetProperty(x => x.AttemptCount, x => x.AttemptCount + 1),
                    cancellationToken);

            if (affected == 1)
            {
                claimedIds.Add(id);
            }
        }

        if (claimedIds.Count == 0)
        {
            return Array.Empty<OutboxWorkItem>();
        }

        return await dbContext.Set<OutboxMessageRecord>()
            .Where(x => claimedIds.Contains(x.Id) && x.ClaimToken == claimToken)
            .OrderBy(x => x.Id)
            .Select(x => new OutboxWorkItem(
                x.EventId,
                claimToken,
                x.EventType,
                x.Module,
                x.AggregatePublicId,
                x.PayloadSchemaVersion,
                x.Payload,
                x.AttemptCount))
            .ToArrayAsync(cancellationToken);
    }

    public async Task<bool> MarkProcessedAsync(
        Guid eventId,
        Guid claimToken,
        DateTimeOffset processedAt,
        CancellationToken cancellationToken)
    {
        var affected = await OwnedClaim(eventId, claimToken)
            .ExecuteUpdateAsync(
                setters => setters
                    .SetProperty(x => x.State, OutboxMessageState.Processed)
                    .SetProperty(x => x.ProcessedAt, processedAt)
                    .SetProperty(x => x.LastError, (string?)null)
                    .SetProperty(x => x.ClaimToken, (Guid?)null)
                    .SetProperty(x => x.ClaimedUntil, (DateTimeOffset?)null),
                cancellationToken);

        return affected == 1;
    }

    public async Task<bool> MarkRetryAsync(
        Guid eventId,
        Guid claimToken,
        DateTimeOffset availableAt,
        string errorCode,
        CancellationToken cancellationToken)
    {
        var normalizedError = RequireErrorCode(errorCode);

        var affected = await OwnedClaim(eventId, claimToken)
            .ExecuteUpdateAsync(
                setters => setters
                    .SetProperty(x => x.State, OutboxMessageState.Pending)
                    .SetProperty(x => x.AvailableAt, availableAt)
                    .SetProperty(x => x.LastError, normalizedError)
                    .SetProperty(x => x.ClaimToken, (Guid?)null)
                    .SetProperty(x => x.ClaimedUntil, (DateTimeOffset?)null),
                cancellationToken);

        return affected == 1;
    }

    public async Task<bool> MarkFailedAsync(
        Guid eventId,
        Guid claimToken,
        string errorCode,
        CancellationToken cancellationToken)
    {
        var normalizedError = RequireErrorCode(errorCode);

        var affected = await OwnedClaim(eventId, claimToken)
            .ExecuteUpdateAsync(
                setters => setters
                    .SetProperty(x => x.State, OutboxMessageState.Failed)
                    .SetProperty(x => x.LastError, normalizedError)
                    .SetProperty(x => x.ClaimToken, (Guid?)null)
                    .SetProperty(x => x.ClaimedUntil, (DateTimeOffset?)null),
                cancellationToken);

        return affected == 1;
    }

    private IQueryable<OutboxMessageRecord> OwnedClaim(Guid eventId, Guid claimToken)
    {
        if (eventId == Guid.Empty) throw new ArgumentException("Event id is required.", nameof(eventId));
        if (claimToken == Guid.Empty) throw new ArgumentException("Claim token is required.", nameof(claimToken));

        return dbContext.Set<OutboxMessageRecord>()
            .Where(x =>
                x.EventId == eventId &&
                x.State == OutboxMessageState.Processing &&
                x.ClaimToken == claimToken);
    }

    private static string RequireErrorCode(string errorCode) =>
        string.IsNullOrWhiteSpace(errorCode)
            ? throw new ArgumentException("Error code is required.", nameof(errorCode))
            : errorCode.Trim();
}
