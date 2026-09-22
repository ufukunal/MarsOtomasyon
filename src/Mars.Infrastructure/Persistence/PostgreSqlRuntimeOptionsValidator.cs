using Mars.Application.Foundation.Configuration;

namespace Mars.Infrastructure.Persistence;

public sealed class PostgreSqlRuntimeOptionsValidator
    : IStartupConfigurationValidator<PostgreSqlRuntimeOptions>
{
    private static readonly ConfigurationValidationIssue MissingConnectionString =
        new(
            "PostgreSql:RuntimeConnectionString",
            "required",
            "A PostgreSQL runtime connection string is required.");

    public IReadOnlyList<ConfigurationValidationIssue> Validate(PostgreSqlRuntimeOptions options)
    {
        ArgumentNullException.ThrowIfNull(options);

        return string.IsNullOrWhiteSpace(options.ConnectionString)
            ? new[] { MissingConnectionString }
            : Array.Empty<ConfigurationValidationIssue>();
    }
}
