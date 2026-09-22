using Mars.Infrastructure.Persistence;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Diagnostics.HealthChecks;

namespace Mars.Api.Foundation.Health;

public sealed class PostgreSqlReadinessHealthCheck(
    IServiceScopeFactory scopeFactory) : IHealthCheck
{
    public async Task<HealthCheckResult> CheckHealthAsync(
        HealthCheckContext context,
        CancellationToken cancellationToken = default)
    {
        try
        {
            await using var scope = scopeFactory.CreateAsyncScope();
            var dbContext = scope.ServiceProvider.GetRequiredService<MarsDbContext>();

            if (!await dbContext.Database.CanConnectAsync(cancellationToken))
            {
                return HealthCheckResult.Unhealthy("PostgreSQL is not reachable.");
            }

            var pendingMigrations =
                await dbContext.Database.GetPendingMigrationsAsync(cancellationToken);

            if (pendingMigrations.Any())
            {
                return HealthCheckResult.Unhealthy(
                    "PostgreSQL schema is not at the application migration level.");
            }

            return HealthCheckResult.Healthy(
                "PostgreSQL is reachable and the required migrations are applied.");
        }
        catch
        {
            return HealthCheckResult.Unhealthy(
                "PostgreSQL readiness check failed.");
        }
    }
}
