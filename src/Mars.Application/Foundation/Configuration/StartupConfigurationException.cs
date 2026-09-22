namespace Mars.Application.Foundation.Configuration;

public sealed class StartupConfigurationException : Exception
{
    public StartupConfigurationException(IReadOnlyList<ConfigurationValidationIssue> issues)
        : base(CreateMessage(issues))
    {
        if (issues is null)
        {
            throw new ArgumentNullException(nameof(issues));
        }

        if (issues.Count == 0)
        {
            throw new ArgumentException("At least one configuration validation issue is required.", nameof(issues));
        }

        Issues = issues;
    }

    public IReadOnlyList<ConfigurationValidationIssue> Issues { get; }

    private static string CreateMessage(IReadOnlyList<ConfigurationValidationIssue>? issues)
    {
        if (issues is null || issues.Count == 0)
        {
            return "Startup configuration validation failed.";
        }

        var identifiers = issues.Select(issue => $"{issue.Key}:{issue.Code}");
        return $"Startup configuration validation failed: {string.Join(", ", identifiers)}.";
    }
}
