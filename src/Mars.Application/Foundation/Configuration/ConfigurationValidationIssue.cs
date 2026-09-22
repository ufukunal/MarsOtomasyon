namespace Mars.Application.Foundation.Configuration;

public sealed record ConfigurationValidationIssue
{
    public ConfigurationValidationIssue(
        string key,
        string code,
        string message)
    {
        if (string.IsNullOrWhiteSpace(key))
        {
            throw new ArgumentException("Configuration key is required.", nameof(key));
        }

        if (string.IsNullOrWhiteSpace(code))
        {
            throw new ArgumentException("Configuration validation code is required.", nameof(code));
        }

        if (string.IsNullOrWhiteSpace(message))
        {
            throw new ArgumentException("Configuration validation message is required.", nameof(message));
        }

        Key = key.Trim();
        Code = code.Trim();
        Message = message.Trim();
    }

    public string Key { get; }

    public string Code { get; }

    public string Message { get; }
}
