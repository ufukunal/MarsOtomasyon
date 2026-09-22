using Microsoft.EntityFrameworkCore;

namespace Mars.Infrastructure.Persistence;

public sealed class MarsDbContext(DbContextOptions<MarsDbContext> options)
    : DbContext(options)
{
}
