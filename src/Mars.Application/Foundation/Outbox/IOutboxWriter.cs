namespace Mars.Application.Foundation.Outbox;

public interface IOutboxWriter
{
    void Enqueue(OutboxMessage message);
}
