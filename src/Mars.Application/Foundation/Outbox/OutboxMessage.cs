namespace Mars.Application.Foundation.Outbox;

public sealed record OutboxMessage
{
    public OutboxMessage(
        Guid eventId,
        string eventType,
        string module,
        Guid? aggregatePublicId,
        int payloadSchemaVersion,
        string payload,
        DateTimeOffset createdAt,
        DateTimeOffset availableAt)
    {
        if (eventId == Guid.Empty) throw new ArgumentException("Event id is required.", nameof(eventId));
        if (string.IsNullOrWhiteSpace(eventType)) throw new ArgumentException("Event type is required.", nameof(eventType));
        if (string.IsNullOrWhiteSpace(module)) throw new ArgumentException("Module is required.", nameof(module));
        if (payloadSchemaVersion <= 0) throw new ArgumentOutOfRangeException(nameof(payloadSchemaVersion));
        if (string.IsNullOrWhiteSpace(payload)) throw new ArgumentException("Payload is required.", nameof(payload));

        EventId = eventId;
        EventType = eventType.Trim();
        Module = module.Trim();
        AggregatePublicId = aggregatePublicId;
        PayloadSchemaVersion = payloadSchemaVersion;
        Payload = payload;
        CreatedAt = createdAt;
        AvailableAt = availableAt;
    }

    public Guid EventId { get; }
    public string EventType { get; }
    public string Module { get; }
    public Guid? AggregatePublicId { get; }
    public int PayloadSchemaVersion { get; }
    public string Payload { get; }
    public DateTimeOffset CreatedAt { get; }
    public DateTimeOffset AvailableAt { get; }
}
