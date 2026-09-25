using Mars.Application.Finance;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;

internal sealed class FakeFinanceValuationAuthority(
    bool failCount = false,
    string countFailureCode = "finance.count.positive_valuation_required")
    : IFinanceValuationAuthority
{
    public int GoodsReceiptPostCalls { get; private set; }
    public int GoodsReceiptReverseCalls { get; private set; }
    public int DispatchPostCalls { get; private set; }
    public int DispatchReverseCalls { get; private set; }
    public int SalesInvoicePostCalls { get; private set; }
    public int SalesInvoiceReverseCalls { get; private set; }
    public int SupplierInvoicePostCalls { get; private set; }
    public int SupplierInvoiceReverseCalls { get; private set; }
    public int CountPostCalls { get; private set; }
    public int CountReverseCalls { get; private set; }
    public int ScrapPostCalls { get; private set; }
    public int ScrapReverseCalls { get; private set; }

    public Task<Result<FinanceMutationReceipt>> PostGoodsReceiptAsync(
        FinanceGoodsReceiptValuationCommand command,IExecutionContext context,CancellationToken ct)
    {
        GoodsReceiptPostCalls++;
        return Success(context);
    }

    public Task<Result<FinanceMutationReceipt>> ReverseGoodsReceiptAsync(
        Guid goodsReceiptPublicId,DateOnly postingDate,string operationKey,IExecutionContext context,CancellationToken ct)
    {
        GoodsReceiptReverseCalls++;
        return Success(context);
    }

    public Task<Result<FinanceMutationReceipt>> PostDispatchAsync(
        FinanceDispatchValuationCommand command,IExecutionContext context,CancellationToken ct)
    {
        DispatchPostCalls++;
        return Success(context);
    }

    public Task<Result<FinanceMutationReceipt>> ReverseDispatchAsync(
        FinanceDispatchReversalCommand command,IExecutionContext context,CancellationToken ct)
    {
        DispatchReverseCalls++;
        return Success(context);
    }

    public Task<Result<FinanceMutationReceipt>> PostSalesInvoiceAsync(
        FinanceSalesInvoicePostCommand command,IExecutionContext context,CancellationToken ct)
    {
        SalesInvoicePostCalls++;
        return Success(context);
    }

    public Task<Result<FinanceMutationReceipt>> ReverseSalesInvoiceAsync(
        Guid salesInvoicePublicId,DateOnly postingDate,string operationKey,IExecutionContext context,CancellationToken ct)
    {
        SalesInvoiceReverseCalls++;
        return Success(context);
    }

    public Task<Result<FinanceMutationReceipt>> PostSupplierInvoiceAsync(
        FinanceSupplierInvoicePostCommand command,IExecutionContext context,CancellationToken ct)
    {
        SupplierInvoicePostCalls++;
        return Success(context);
    }

    public Task<Result<FinanceMutationReceipt>> ReverseSupplierInvoiceAsync(
        Guid supplierInvoicePublicId,DateOnly postingDate,string operationKey,IExecutionContext context,CancellationToken ct)
    {
        SupplierInvoiceReverseCalls++;
        return Success(context);
    }

    public Task<Result<FinanceMutationReceipt>> PostCountAdjustmentAsync(
        FinanceCountValuationCommand command,IExecutionContext context,CancellationToken ct)
    {
        CountPostCalls++;
        return failCount
            ? Task.FromResult(Result<FinanceMutationReceipt>.Failure(
                new ApplicationError(ErrorCategory.BusinessRule,countFailureCode,
                    "Test Finance valuation basis is unavailable.")))
            : Success(context);
    }

    public Task<Result<FinanceMutationReceipt>> ReverseCountAdjustmentAsync(
        Guid countPublicId,DateOnly postingDate,string operationKey,IExecutionContext context,CancellationToken ct)
    {
        CountReverseCalls++;
        return Success(context);
    }

    public Task<Result<FinanceMutationReceipt>> PostScrapAsync(
        FinanceScrapValuationCommand command,IExecutionContext context,CancellationToken ct)
    {
        ScrapPostCalls++;
        return Success(context);
    }

    public Task<Result<FinanceMutationReceipt>> ReverseScrapAsync(
        Guid scrapPublicId,DateOnly postingDate,string operationKey,IExecutionContext context,CancellationToken ct)
    {
        ScrapReverseCalls++;
        return Success(context);
    }

    private static Task<Result<FinanceMutationReceipt>> Success(IExecutionContext context) =>
        Task.FromResult(Result<FinanceMutationReceipt>.Success(
            new FinanceMutationReceipt(Guid.NewGuid(),"POSTED",1,context.CorrelationId.Value)));
}
