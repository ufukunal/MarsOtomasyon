namespace Mars.Application.Foundation.Outbox;

public sealed record OutboxWorkItem(
    Guid EventId,
    Guid ClaimToken,
    string EventType,
    string Module,
    Guid? AggregatePublicId,
    int PayloadSchemaVersion,
    string Payload,
    int AttemptCount);
