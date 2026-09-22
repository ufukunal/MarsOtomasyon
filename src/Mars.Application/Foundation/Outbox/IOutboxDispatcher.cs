namespace Mars.Application.Foundation.Outbox;

public interface IOutboxDispatcher
{
    Task<OutboxDispatchResult> DispatchAsync(
        OutboxWorkItem workItem,
        CancellationToken cancellationToken);
}
