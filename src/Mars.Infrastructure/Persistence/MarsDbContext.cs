using Mars.Infrastructure.Identity;
using Mars.Infrastructure.Persistence.Foundation;
using Mars.Infrastructure.Persistence.Parties;
using Microsoft.AspNetCore.Identity;
using Microsoft.AspNetCore.Identity.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore;

namespace Mars.Infrastructure.Persistence;

public sealed class MarsDbContext(DbContextOptions<MarsDbContext> options)
    : IdentityUserContext<MarsIdentityUser, Guid>(options)
{
    protected override void OnModelCreating(ModelBuilder modelBuilder)
    {
        base.OnModelCreating(modelBuilder);

        modelBuilder.HasDefaultSchema("identity");

        modelBuilder.Entity<MarsIdentityUser>().ToTable("users", "identity");
        modelBuilder.Entity<IdentityUserClaim<Guid>>().ToTable("user_claims", "identity");
        modelBuilder.Entity<IdentityUserLogin<Guid>>().ToTable("user_logins", "identity");
        modelBuilder.Entity<IdentityUserToken<Guid>>().ToTable("user_tokens", "identity");

        modelBuilder.ApplyConfiguration(new AuditEventConfiguration());
        modelBuilder.ApplyConfiguration(new IdempotencyOperationConfiguration());
        modelBuilder.ApplyConfiguration(new OutboxMessageConfiguration());
        modelBuilder.ApplyConfiguration(new PermissionGrantConfiguration());
        modelBuilder.ApplyConfiguration(new PartyConfiguration());
    }
}
