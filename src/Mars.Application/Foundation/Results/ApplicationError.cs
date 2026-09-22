namespace Mars.Application.Foundation.Results;

public sealed record ApplicationError
{
    public ApplicationError(
        ErrorCategory category,
        string code,
        string message)
    {
        if (string.IsNullOrWhiteSpace(code))
        {
            throw new ArgumentException("Error code is required.", nameof(code));
        }

        if (string.IsNullOrWhiteSpace(message))
        {
            throw new ArgumentException("Error message is required.", nameof(message));
        }

        Category = category;
        Code = code.Trim();
        Message = message.Trim();
    }

    public ErrorCategory Category { get; }

    public string Code { get; }

    public string Message { get; }
}
