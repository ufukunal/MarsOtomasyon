namespace Mars.Infrastructure.Persistence.Foundation;

internal enum OutboxMessageState
{
    Pending = 1,
    Processing = 2,
    Processed = 3,
    Failed = 4
}

internal sealed class OutboxMessageRecord
{
    public long Id { get; set; }
    public Guid EventId { get; set; }
    public string EventType { get; set; } = string.Empty;
    public string Module { get; set; } = string.Empty;
    public Guid? AggregatePublicId { get; set; }
    public int PayloadSchemaVersion { get; set; }
    public string Payload { get; set; } = string.Empty;
    public OutboxMessageState State { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
    public DateTimeOffset AvailableAt { get; set; }
    public DateTimeOffset? ProcessedAt { get; set; }
    public int AttemptCount { get; set; }
    public string? LastError { get; set; }
    public Guid? ClaimToken { get; set; }
    public DateTimeOffset? ClaimedUntil { get; set; }
}
