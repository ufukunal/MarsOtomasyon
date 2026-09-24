using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;

namespace Mars.Application.Inventory;

public sealed record WarehouseAccessGrantReceipt(
    Guid PublicId,
    Guid ActorId,
    Guid WarehousePublicId,
    string State,
    string CorrelationId);

public interface IWarehouseAccessGrantAuthority
{
    Task<Result<WarehouseAccessGrantReceipt>> GrantAsync(
        Guid actorId,
        Guid warehousePublicId,
        string operationKey,
        IExecutionContext context,
        CancellationToken cancellationToken);

    Task<Result<WarehouseAccessGrantReceipt>> RevokeAsync(
        Guid actorId,
        Guid warehousePublicId,
        string operationKey,
        IExecutionContext context,
        CancellationToken cancellationToken);
}
