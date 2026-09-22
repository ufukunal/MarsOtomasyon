namespace Mars.Application.Foundation.Configuration;

public sealed record ConfigurationValidationIssue(
    string Key,
    string Code,
    string Message)
{
    public ConfigurationValidationIssue
    {
        if (string.IsNullOrWhiteSpace(Key))
        {
            throw new ArgumentException("Configuration key is required.", nameof(Key));
        }

        if (string.IsNullOrWhiteSpace(Code))
        {
            throw new ArgumentException("Configuration validation code is required.", nameof(Code));
        }

        if (string.IsNullOrWhiteSpace(Message))
        {
            throw new ArgumentException("Configuration validation message is required.", nameof(Message));
        }
    }
}
