using Microsoft.EntityFrameworkCore.Design;

namespace Mars.Infrastructure.Persistence;

public sealed class MarsDesignTimeDbContextFactory
    : IDesignTimeDbContextFactory<MarsDbContext>
{
    public const string MigrationConnectionStringEnvironmentVariable =
        "MARS_MIGRATION_CONNECTION_STRING";

    public MarsDbContext CreateDbContext(string[] args)
    {
        var connectionString =
            Environment.GetEnvironmentVariable(MigrationConnectionStringEnvironmentVariable);

        if (string.IsNullOrWhiteSpace(connectionString))
        {
            throw new InvalidOperationException(
                $"{MigrationConnectionStringEnvironmentVariable} must be supplied for EF Core migration tooling.");
        }

        var options = MarsDbContextOptions.CreateMigration(
            new PostgreSqlMigrationOptions(connectionString));

        return new MarsDbContext(options);
    }
}
