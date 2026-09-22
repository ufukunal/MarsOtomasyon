namespace Mars.Application.Foundation.Idempotency;

public enum IdempotencyOperationStatus
{
    InProgress = 1,
    Succeeded = 2,
    Failed = 3
}

public sealed record IdempotencyOperation
{
    public IdempotencyOperation(
        string scope,
        string operationKey,
        string? requestFingerprint,
        DateTimeOffset createdAt)
    {
        if (string.IsNullOrWhiteSpace(scope)) throw new ArgumentException("Scope is required.", nameof(scope));
        if (string.IsNullOrWhiteSpace(operationKey)) throw new ArgumentException("Operation key is required.", nameof(operationKey));

        Scope = scope.Trim();
        OperationKey = operationKey.Trim();
        RequestFingerprint = string.IsNullOrWhiteSpace(requestFingerprint) ? null : requestFingerprint.Trim();
        CreatedAt = createdAt;
    }

    public string Scope { get; }
    public string OperationKey { get; }
    public string? RequestFingerprint { get; }
    public DateTimeOffset CreatedAt { get; }
}
