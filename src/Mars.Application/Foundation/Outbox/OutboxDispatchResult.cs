namespace Mars.Application.Foundation.Outbox;

public sealed record OutboxDispatchResult
{
    private OutboxDispatchResult(bool succeeded, bool retry, DateTimeOffset? retryAt, string? errorCode)
    {
        Succeeded = succeeded;
        Retry = retry;
        RetryAt = retryAt;
        ErrorCode = errorCode;
    }

    public bool Succeeded { get; }
    public bool Retry { get; }
    public DateTimeOffset? RetryAt { get; }
    public string? ErrorCode { get; }

    public static OutboxDispatchResult Success() => new(true, false, null, null);

    public static OutboxDispatchResult RetryLater(DateTimeOffset retryAt, string errorCode) =>
        new(false, true, retryAt, RequireErrorCode(errorCode));

    public static OutboxDispatchResult Failed(string errorCode) =>
        new(false, false, null, RequireErrorCode(errorCode));

    private static string RequireErrorCode(string errorCode) =>
        string.IsNullOrWhiteSpace(errorCode)
            ? throw new ArgumentException("Error code is required.", nameof(errorCode))
            : errorCode.Trim();
}
