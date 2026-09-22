using Mars.Infrastructure.Persistence.Foundation;
using Microsoft.EntityFrameworkCore;

namespace Mars.Infrastructure.Persistence;

public sealed class MarsDbContext(DbContextOptions<MarsDbContext> options)
    : DbContext(options)
{
    protected override void OnModelCreating(ModelBuilder modelBuilder)
    {
        modelBuilder.ApplyConfiguration(new AuditEventConfiguration());
        modelBuilder.ApplyConfiguration(new IdempotencyOperationConfiguration());
        modelBuilder.ApplyConfiguration(new OutboxMessageConfiguration());
    }
}
