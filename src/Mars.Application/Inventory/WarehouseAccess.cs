namespace Mars.Application.Inventory;

public interface IWarehouseAccessEvaluator
{
    Task<bool> IsGrantedAsync(
        Guid actorId,
        Guid companyId,
        Guid warehousePublicId,
        CancellationToken cancellationToken);
}
