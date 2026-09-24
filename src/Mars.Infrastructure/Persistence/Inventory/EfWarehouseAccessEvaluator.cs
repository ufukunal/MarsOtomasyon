using Mars.Application.Inventory;
using Microsoft.EntityFrameworkCore;

namespace Mars.Infrastructure.Persistence.Inventory;

public sealed class EfWarehouseAccessEvaluator(MarsDbContext dbContext)
    : IWarehouseAccessEvaluator
{
    public async Task<bool> IsGrantedAsync(
        Guid actorId,
        Guid companyId,
        Guid warehousePublicId,
        CancellationToken cancellationToken)
    {
        if (actorId == Guid.Empty || companyId == Guid.Empty || warehousePublicId == Guid.Empty)
            return false;

        return await (
            from grant in dbContext.Set<WarehouseAccessGrantRecord>().AsNoTracking()
            join warehouse in dbContext.Set<WarehouseRecord>().AsNoTracking()
                on grant.WarehouseId equals warehouse.Id
            where grant.ActorId == actorId &&
                  grant.CompanyId == companyId &&
                  grant.RevokedAt == null &&
                  warehouse.PublicId == warehousePublicId &&
                  warehouse.CompanyId == companyId
            select grant.Id).AnyAsync(cancellationToken);
    }
}
