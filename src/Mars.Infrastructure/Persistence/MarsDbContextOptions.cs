using Microsoft.EntityFrameworkCore;

namespace Mars.Infrastructure.Persistence;

public static class MarsDbContextOptions
{
    public static DbContextOptions<MarsDbContext> CreateRuntime(
        PostgreSqlRuntimeOptions options)
    {
        ArgumentNullException.ThrowIfNull(options);

        if (string.IsNullOrWhiteSpace(options.ConnectionString))
        {
            throw new ArgumentException(
                "A PostgreSQL runtime connection string is required.",
                nameof(options));
        }

        return Create(options.ConnectionString);
    }

    public static DbContextOptions<MarsDbContext> CreateMigration(
        PostgreSqlMigrationOptions options)
    {
        ArgumentNullException.ThrowIfNull(options);

        if (string.IsNullOrWhiteSpace(options.ConnectionString))
        {
            throw new ArgumentException(
                "A PostgreSQL migration connection string is required.",
                nameof(options));
        }

        return Create(options.ConnectionString);
    }

    private static DbContextOptions<MarsDbContext> Create(string connectionString)
    {
        var builder = new DbContextOptionsBuilder<MarsDbContext>();

        builder.UseNpgsql(
            connectionString,
            npgsql => npgsql.MigrationsAssembly(typeof(MarsDbContext).Assembly.FullName));

        return builder.Options;
    }
}
