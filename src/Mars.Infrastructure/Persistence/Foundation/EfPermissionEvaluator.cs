using Mars.Application.Foundation.Authorization;
using Microsoft.EntityFrameworkCore;

namespace Mars.Infrastructure.Persistence.Foundation;

public sealed class EfPermissionEvaluator(MarsDbContext dbContext) : IPermissionEvaluator
{
    private readonly Dictionary<(Guid ActorId, Guid CompanyId, string PermissionCode), bool> cache = [];

    public async Task<bool> IsGrantedAsync(
        Guid actorId,
        Guid companyId,
        string permissionCode,
        CancellationToken cancellationToken)
    {
        if (actorId == Guid.Empty) throw new ArgumentException("Actor id is required.", nameof(actorId));
        if (companyId == Guid.Empty) throw new ArgumentException("Company id is required.", nameof(companyId));
        if (string.IsNullOrWhiteSpace(permissionCode))
        {
            throw new ArgumentException("Permission code is required.", nameof(permissionCode));
        }

        if (!string.Equals(permissionCode, permissionCode.Trim(), StringComparison.Ordinal))
        {
            throw new ArgumentException(
                "Permission code cannot contain leading or trailing whitespace.",
                nameof(permissionCode));
        }

        var key = (actorId, companyId, permissionCode);
        if (cache.TryGetValue(key, out var cached))
        {
            return cached;
        }

        var granted = await dbContext.Set<PermissionGrantRecord>()
            .AsNoTracking()
            .AnyAsync(
                x =>
                    x.ActorId == actorId &&
                    x.CompanyId == companyId &&
                    x.PermissionCode == permissionCode &&
                    x.RevokedAt == null,
                cancellationToken);

        cache[key] = granted;
        return granted;
    }
}
