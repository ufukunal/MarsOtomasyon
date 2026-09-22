using Microsoft.EntityFrameworkCore;
using OpenIddict.EntityFrameworkCore;

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

    public static void Configure(
        DbContextOptionsBuilder optionsBuilder,
        string connectionString)
    {
        ArgumentNullException.ThrowIfNull(optionsBuilder);

        if (string.IsNullOrWhiteSpace(connectionString))
        {
            throw new ArgumentException(
                "A PostgreSQL connection string is required.",
                nameof(connectionString));
        }

        optionsBuilder.UseNpgsql(
            connectionString,
            npgsql => npgsql.MigrationsAssembly(typeof(MarsDbContext).Assembly.FullName));

        optionsBuilder.UseOpenIddict();
    }

    private static DbContextOptions<MarsDbContext> Create(string connectionString)
    {
        var builder = new DbContextOptionsBuilder<MarsDbContext>();
        Configure(builder, connectionString);
        return builder.Options;
    }
}
