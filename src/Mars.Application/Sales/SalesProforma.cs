using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Domain.Sales;

namespace Mars.Application.Sales;

public sealed record CreateSalesProformaCommand(
    string Number,
    SalesProformaSourceMode SourceMode,
    Guid SourceDocumentPublicId,
    string OperationKey);

public interface ISalesProformaPersistence
{
    Task<IReadOnlyList<SalesDocumentListItem>> ListAsync(Guid companyId, CancellationToken ct);
    Task<SalesDocumentDetailView?> GetAsync(Guid companyId, Guid publicId, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> CreateAsync(
        CreateSalesProformaCommand command,
        IExecutionContext context,
        CancellationToken ct);
    Task<Result<SalesMutationReceipt>> CancelAsync(
        Guid publicId,
        long expectedVersion,
        string reason,
        string operationKey,
        IExecutionContext context,
        CancellationToken ct);
}

public sealed class SalesProformaQueryHandler(
    IPermissionEvaluator permissions,
    ISalesProformaPersistence persistence)
{
    public async Task<Result<IReadOnlyList<SalesDocumentListItem>>> ListAsync(
        IExecutionContext context,
        CancellationToken ct)
    {
        if (!await Granted(SalesPermissions.ProformaRead, context, ct))
            return Result<IReadOnlyList<SalesDocumentListItem>>.Failure(Denied());
        return Result<IReadOnlyList<SalesDocumentListItem>>.Success(
            await persistence.ListAsync(context.CompanyId, ct));
    }

    public async Task<Result<SalesDocumentDetailView?>> GetAsync(
        Guid publicId,
        IExecutionContext context,
        CancellationToken ct,
        bool export = false)
    {
        var permission = export ? SalesPermissions.ProformaExport : SalesPermissions.ProformaRead;
        if (!await Granted(permission, context, ct))
            return Result<SalesDocumentDetailView?>.Failure(Denied());
        return Result<SalesDocumentDetailView?>.Success(
            await persistence.GetAsync(context.CompanyId, publicId, ct));
    }

    private Task<bool> Granted(string permission, IExecutionContext context, CancellationToken ct) =>
        permissions.IsGrantedAsync(context.ActorId, context.CompanyId, permission, ct);

    private static ApplicationError Denied() =>
        new(ErrorCategory.Authorization, "authorization.permission_denied",
            "The current actor is not authorized for this Sales Proforma action.");
}

public sealed class SalesProformaCommandHandler(
    IPermissionEvaluator permissions,
    ISalesProformaPersistence persistence)
{
    public async Task<Result<SalesMutationReceipt>> CreateAsync(
        CreateSalesProformaCommand command,
        IExecutionContext context,
        CancellationToken ct)
    {
        if (!await Granted(SalesPermissions.ProformaCreate, context, ct))
            return Result<SalesMutationReceipt>.Failure(Denied());
        if (string.IsNullOrWhiteSpace(command.Number) || command.SourceDocumentPublicId == Guid.Empty)
            return Result<SalesMutationReceipt>.Failure(
                new ApplicationError(ErrorCategory.Validation, "sales.proforma.invalid",
                    "Proforma number and Quote/Order source identity are required."));
        return await persistence.CreateAsync(command, context, ct);
    }

    public async Task<Result<SalesMutationReceipt>> CancelAsync(
        Guid publicId,
        long expectedVersion,
        string reason,
        string operationKey,
        IExecutionContext context,
        CancellationToken ct)
    {
        if (!await Granted(SalesPermissions.ProformaCancel, context, ct))
            return Result<SalesMutationReceipt>.Failure(Denied());
        if (publicId == Guid.Empty || expectedVersion <= 0 || string.IsNullOrWhiteSpace(reason))
            return Result<SalesMutationReceipt>.Failure(
                new ApplicationError(ErrorCategory.Validation, "sales.proforma.cancel.invalid",
                    "Proforma identity, positive version and cancellation reason are required."));
        return await persistence.CancelAsync(publicId, expectedVersion, reason, operationKey, context, ct);
    }

    private Task<bool> Granted(string permission, IExecutionContext context, CancellationToken ct) =>
        permissions.IsGrantedAsync(context.ActorId, context.CompanyId, permission, ct);

    private static ApplicationError Denied() =>
        new(ErrorCategory.Authorization, "authorization.permission_denied",
            "The current actor is not authorized for this Sales Proforma action.");
}
