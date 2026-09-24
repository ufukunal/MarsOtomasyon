using System.Text.Json;
using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Foundation.Outbox;
using Mars.Application.Foundation.Results;
using Mars.Application.Inventory;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Storage;
using Npgsql;

namespace Mars.Infrastructure.Persistence.Inventory;

public sealed class EfWarehouseAccessGrantAuthority(
    MarsDbContext dbContext,
    IAuditWriter auditWriter,
    IIdempotencyStore idempotencyStore,
    IOutboxWriter outboxWriter)
    : IWarehouseAccessGrantAuthority
{
    public Task<Result<WarehouseAccessGrantReceipt>> GrantAsync(
        Guid actorId,
        Guid warehousePublicId,
        string operationKey,
        IExecutionContext context,
        CancellationToken cancellationToken) =>
        ExecuteAsync(
            "inventory.warehouse_access.grant",
            operationKey,
            context,
            async ct =>
            {
                if (actorId == Guid.Empty || warehousePublicId == Guid.Empty)
                    return Validation("warehouse.access.invalid", "Actor and Warehouse identities are required.");

                var warehouse = await dbContext.Set<WarehouseRecord>()
                    .AsNoTracking()
                    .SingleOrDefaultAsync(
                        x => x.CompanyId == context.CompanyId &&
                             x.PublicId == warehousePublicId &&
                             x.State == Mars.Domain.Inventory.InventoryMasterState.Active,
                        ct);
                if (warehouse is null)
                    return NotFound("warehouse.access.warehouse_not_found", "ACTIVE Warehouse was not found in the current company.");

                var existing = await dbContext.Set<WarehouseAccessGrantRecord>()
                    .AsNoTracking()
                    .AnyAsync(
                        x => x.ActorId == actorId &&
                             x.CompanyId == context.CompanyId &&
                             x.WarehouseId == warehouse.Id &&
                             x.RevokedAt == null,
                        ct);
                if (existing)
                    return Conflict("warehouse.access.active_grant_exists", "An active Warehouse access grant already exists.");

                var now = DateTimeOffset.UtcNow;
                var publicId = Guid.NewGuid();
                dbContext.Add(new WarehouseAccessGrantRecord
                {
                    PublicId = publicId,
                    ActorId = actorId,
                    CompanyId = context.CompanyId,
                    WarehouseId = warehouse.Id,
                    GrantedByActorId = context.ActorId,
                    GrantedAt = now
                });

                return Result<WarehouseAccessGrantReceipt>.Success(
                    new(publicId, actorId, warehousePublicId, "ACTIVE", context.CorrelationId.Value));
            },
            "WarehouseAccessGranted",
            cancellationToken);

    public Task<Result<WarehouseAccessGrantReceipt>> RevokeAsync(
        Guid actorId,
        Guid warehousePublicId,
        string operationKey,
        IExecutionContext context,
        CancellationToken cancellationToken) =>
        ExecuteAsync(
            "inventory.warehouse_access.revoke",
            operationKey,
            context,
            async ct =>
            {
                if (actorId == Guid.Empty || warehousePublicId == Guid.Empty)
                    return Validation("warehouse.access.invalid", "Actor and Warehouse identities are required.");

                var grant = await (
                    from g in dbContext.Set<WarehouseAccessGrantRecord>()
                    join w in dbContext.Set<WarehouseRecord>() on g.WarehouseId equals w.Id
                    where g.ActorId == actorId &&
                          g.CompanyId == context.CompanyId &&
                          g.RevokedAt == null &&
                          w.CompanyId == context.CompanyId &&
                          w.PublicId == warehousePublicId
                    select g).SingleOrDefaultAsync(ct);

                if (grant is null)
                    return NotFound("warehouse.access.active_grant_not_found", "Active Warehouse access grant was not found.");

                var now = DateTimeOffset.UtcNow;
                grant.RevokedAt = now;
                grant.RevokedByActorId = context.ActorId;

                return Result<WarehouseAccessGrantReceipt>.Success(
                    new(grant.PublicId, actorId, warehousePublicId, "REVOKED", context.CorrelationId.Value));
            },
            "WarehouseAccessRevoked",
            cancellationToken);

    private async Task<Result<WarehouseAccessGrantReceipt>> ExecuteAsync(
        string scope,
        string operationKey,
        IExecutionContext context,
        Func<CancellationToken, Task<Result<WarehouseAccessGrantReceipt>>> mutation,
        string eventName,
        CancellationToken cancellationToken)
    {
        if (string.IsNullOrWhiteSpace(operationKey))
            return Validation("warehouse.access.operation_key_required", "Idempotency operation key is required.");

        IDbContextTransaction? transaction = null;
        if (dbContext.Database.CurrentTransaction is null)
            transaction = await dbContext.Database.BeginTransactionAsync(cancellationToken);

        try
        {
            var result = await mutation(cancellationToken);
            if (result.IsFailure)
            {
                if (transaction is not null) await transaction.RollbackAsync(cancellationToken);
                dbContext.ChangeTracker.Clear();
                return result;
            }

            var receipt = result.Value!;
            var now = DateTimeOffset.UtcNow;
            idempotencyStore.Add(new IdempotencyOperation(scope, operationKey, null, now));
            auditWriter.Append(new AuditEntry(
                context.ActorId,
                context.CompanyId,
                context.BranchId,
                context.CorrelationId.Value,
                "Inventory",
                eventName,
                "WarehouseAccessGrant",
                receipt.PublicId,
                null,
                now));
            outboxWriter.Enqueue(new OutboxMessage(
                Guid.NewGuid(),
                "Inventory." + eventName,
                "Inventory",
                receipt.PublicId,
                1,
                JsonSerializer.Serialize(new
                {
                    receipt.ActorId,
                    receipt.WarehousePublicId,
                    receipt.State
                }),
                now,
                now));

            await dbContext.SaveChangesAsync(cancellationToken);
            if (!await idempotencyStore.MarkSucceededAsync(
                    scope, operationKey, "warehouse.access.completed", now, cancellationToken))
                throw new InvalidOperationException("Warehouse access idempotency state could not be completed.");

            if (transaction is not null) await transaction.CommitAsync(cancellationToken);
            return result;
        }
        catch (DbUpdateException ex) when (
            ex.InnerException is PostgresException pg &&
            pg.SqlState == PostgresErrorCodes.UniqueViolation)
        {
            if (transaction is not null) await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return Conflict("warehouse.access.conflict", "Warehouse access grant conflicts with an existing active scope or operation.");
        }
        finally
        {
            if (transaction is not null) await transaction.DisposeAsync();
        }
    }

    private static Result<WarehouseAccessGrantReceipt> Validation(string code, string message) =>
        Result<WarehouseAccessGrantReceipt>.Failure(new ApplicationError(ErrorCategory.Validation, code, message));
    private static Result<WarehouseAccessGrantReceipt> NotFound(string code, string message) =>
        Result<WarehouseAccessGrantReceipt>.Failure(new ApplicationError(ErrorCategory.NotFound, code, message));
    private static Result<WarehouseAccessGrantReceipt> Conflict(string code, string message) =>
        Result<WarehouseAccessGrantReceipt>.Failure(new ApplicationError(ErrorCategory.Conflict, code, message));
}
