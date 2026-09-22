using Mars.Application.Foundation.Idempotency;

namespace Mars.Infrastructure.Persistence.Foundation;

internal sealed class IdempotencyOperationRecord
{
    public long Id { get; set; }
    public string Scope { get; set; } = string.Empty;
    public string OperationKey { get; set; } = string.Empty;
    public string? RequestFingerprint { get; set; }
    public IdempotencyOperationStatus Status { get; set; }
    public string? ResultCode { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
    public DateTimeOffset? CompletedAt { get; set; }
}
