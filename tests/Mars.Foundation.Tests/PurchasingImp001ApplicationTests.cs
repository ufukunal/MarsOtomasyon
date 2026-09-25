using System.Reflection;
using Mars.Application.Finance;
using Mars.Application.Foundation.Approvals;
using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Application.Inventory;
using Mars.Application.Purchasing;
using Mars.Domain.Inventory;
using MarsExecutionContext = Mars.Application.Foundation.Context.ExecutionContext;

internal static class PurchasingImp001ApplicationTests
{
    public static IReadOnlyList<(string Name, Action Test)> Cases { get; } =
    [
        ("PURCHASING-IMP-001 receipt create requires Warehouse scope", ReceiptCreateRequiresWarehouseScope),
        ("PURCHASING-IMP-001 stockable receipt POST delegates QUARANTINE movement", StockableReceiptPostsQuarantine),
        ("PURCHASING-IMP-001 service receipt POST has no physical stock movement", ServiceReceiptHasNoPhysicalMovement),
        ("PURCHASING-IMP-001 receipt POST stops completion when Inventory fails", ReceiptPostStopsOnInventoryFailure),
        ("PURCHASING-IMP-001 receipt reversal delegates compensating Inventory movement", ReceiptReversalDelegatesCompensation),
        ("PURCHASING-IMP-001 direct Supplier Invoice requires direct-create permission", DirectInvoiceRequiresExplicitPermission)
    ];

    private static void ReceiptCreateRequiresWarehouseScope()
    {
        var calls=0;
        var persistence=Proxy<IPurchasingPersistence>((method,_)=>{
            calls++;
            throw new InvalidOperationException("Persistence must not be reached: "+method.Name);
        });
        var handler=Handler(
            new FakePermissionEvaluator(PurchasingPermissions.ReceiptCreate),
            persistence,
            new FakePhysicalAuthority(),
            new FakeWarehouseAccessEvaluator(false));

        var result=handler.CreateReceiptAsync(
            new CreateGoodsReceiptCommand(
                "GR-1",Guid.NewGuid(),1,Guid.NewGuid(),
                [new CreateGoodsReceiptLineInput(1,Guid.NewGuid(),1m,null,null,null)],
                "receipt-create"),
            NewContext(),CancellationToken.None).GetAwaiter().GetResult();

        AssertTrue(result.IsFailure);
        AssertEqual(ErrorCategory.Authorization,result.Error!.Category);
        AssertEqual("purchasing.warehouse.scope_denied",result.Error.Code);
        AssertEqual(0,calls);
    }

    private static void StockableReceiptPostsQuarantine()
    {
        var receiptId=Guid.NewGuid();
        var warehouseId=Guid.NewGuid();
        var lineId=Guid.NewGuid();
        var completed=0;
        var plan=new GoodsReceiptPostPlan(
            receiptId,warehouseId,
            [new GoodsReceiptPostLinePlan(
                lineId,Guid.NewGuid(),null,Guid.NewGuid(),2m,3m,
                warehouseId,null,null,null,100m,true)]);

        var persistence=Proxy<IPurchasingPersistence>((method,args)=>{
            if(method.Name==nameof(IPurchasingPersistence.GetReceiptWarehousePublicIdAsync))
                return Task.FromResult<Guid?>(warehouseId);
            if(method.Name==nameof(IPurchasingPersistence.PrepareReceiptPostAsync))
                return Task.FromResult(Result<GoodsReceiptPostPlan>.Success(plan));
            if(method.Name==nameof(IPurchasingPersistence.CompleteReceiptPostAsync)){
                completed++;
                var effects=(IReadOnlyList<GoodsReceiptInventoryEffect>)args![1]!;
                AssertEqual(1,effects.Count);
                return Task.FromResult(Result<PurchasingMutationReceipt>.Success(
                    new PurchasingMutationReceipt(receiptId,"POSTED",2,"corr-purchasing-app")));
            }
            throw new InvalidOperationException("Unexpected call: "+method.Name);
        });
        var physical=new FakePhysicalAuthority();
        var handler=Handler(
            new FakePermissionEvaluator(PurchasingPermissions.ReceiptPost),
            persistence,physical,new FakeWarehouseAccessEvaluator(true));

        var result=handler.PostReceiptAsync(receiptId,"receipt-post",NewContext(),CancellationToken.None)
            .GetAwaiter().GetResult();

        AssertTrue(result.IsSuccess);
        AssertEqual(1,physical.PostCalls);
        AssertTrue(physical.LastCommand?.Target?.Disposition==InventoryDispositionCode.Quarantine);
        AssertEqual(1,completed);
    }

    private static void ServiceReceiptHasNoPhysicalMovement()
    {
        var receiptId=Guid.NewGuid();
        var warehouseId=Guid.NewGuid();
        var lineId=Guid.NewGuid();
        IReadOnlyList<GoodsReceiptInventoryEffect>? effects=null;
        var plan=new GoodsReceiptPostPlan(
            receiptId,warehouseId,
            [new GoodsReceiptPostLinePlan(
                lineId,Guid.NewGuid(),null,Guid.NewGuid(),1m,1m,
                warehouseId,null,null,null,0m,false)]);

        var persistence=Proxy<IPurchasingPersistence>((method,args)=>{
            if(method.Name==nameof(IPurchasingPersistence.GetReceiptWarehousePublicIdAsync))
                return Task.FromResult<Guid?>(warehouseId);
            if(method.Name==nameof(IPurchasingPersistence.PrepareReceiptPostAsync))
                return Task.FromResult(Result<GoodsReceiptPostPlan>.Success(plan));
            if(method.Name==nameof(IPurchasingPersistence.CompleteReceiptPostAsync)){
                effects=(IReadOnlyList<GoodsReceiptInventoryEffect>)args![1]!;
                return Task.FromResult(Result<PurchasingMutationReceipt>.Success(
                    new PurchasingMutationReceipt(receiptId,"POSTED",2,"corr-purchasing-app")));
            }
            throw new InvalidOperationException("Unexpected call: "+method.Name);
        });
        var physical=new FakePhysicalAuthority();
        var handler=Handler(
            new FakePermissionEvaluator(PurchasingPermissions.ReceiptPost),
            persistence,physical,new FakeWarehouseAccessEvaluator(true));

        var result=handler.PostReceiptAsync(receiptId,"service-post",NewContext(),CancellationToken.None)
            .GetAwaiter().GetResult();

        AssertTrue(result.IsSuccess);
        AssertEqual(0,physical.PostCalls);
        AssertTrue(effects is not null);
        AssertEqual(0,effects!.Count);
    }

    private static void ReceiptPostStopsOnInventoryFailure()
    {
        var receiptId=Guid.NewGuid();
        var warehouseId=Guid.NewGuid();
        var completeCalls=0;
        var plan=new GoodsReceiptPostPlan(
            receiptId,warehouseId,
            [new GoodsReceiptPostLinePlan(
                Guid.NewGuid(),Guid.NewGuid(),null,Guid.NewGuid(),1m,1m,
                warehouseId,null,null,null,100m,true)]);

        var persistence=Proxy<IPurchasingPersistence>((method,_)=>{
            if(method.Name==nameof(IPurchasingPersistence.GetReceiptWarehousePublicIdAsync))
                return Task.FromResult<Guid?>(warehouseId);
            if(method.Name==nameof(IPurchasingPersistence.PrepareReceiptPostAsync))
                return Task.FromResult(Result<GoodsReceiptPostPlan>.Success(plan));
            if(method.Name==nameof(IPurchasingPersistence.CompleteReceiptPostAsync)){
                completeCalls++;
                throw new InvalidOperationException("Completion must not run after Inventory failure.");
            }
            throw new InvalidOperationException("Unexpected call: "+method.Name);
        });
        var physical=new FakePhysicalAuthority(fail:true);
        var handler=Handler(
            new FakePermissionEvaluator(PurchasingPermissions.ReceiptPost),
            persistence,physical,new FakeWarehouseAccessEvaluator(true));

        var result=handler.PostReceiptAsync(receiptId,"receipt-fail",NewContext(),CancellationToken.None)
            .GetAwaiter().GetResult();

        AssertTrue(result.IsFailure);
        AssertEqual(1,physical.PostCalls);
        AssertEqual(0,completeCalls);
    }

    private static void ReceiptReversalDelegatesCompensation()
    {
        var receiptId=Guid.NewGuid();
        var warehouseId=Guid.NewGuid();
        var lineId=Guid.NewGuid();
        var originalMovementId=Guid.NewGuid();
        var completed=0;
        var plan=new GoodsReceiptReversePlan(
            receiptId,warehouseId,
            [new GoodsReceiptReverseLinePlan(
                lineId,Guid.NewGuid(),null,Guid.NewGuid(),1m,1m,
                warehouseId,null,null,null,originalMovementId,true)]);

        var persistence=Proxy<IPurchasingPersistence>((method,args)=>{
            if(method.Name==nameof(IPurchasingPersistence.GetReceiptWarehousePublicIdAsync))
                return Task.FromResult<Guid?>(warehouseId);
            if(method.Name==nameof(IPurchasingPersistence.PrepareReceiptReverseAsync))
                return Task.FromResult(Result<GoodsReceiptReversePlan>.Success(plan));
            if(method.Name==nameof(IPurchasingPersistence.CompleteReceiptReverseAsync)){
                completed++;
                var effects=(IReadOnlyList<GoodsReceiptInventoryEffect>)args![1]!;
                AssertEqual(1,effects.Count);
                return Task.FromResult(Result<PurchasingMutationReceipt>.Success(
                    new PurchasingMutationReceipt(receiptId,"REVERSED",3,"corr-purchasing-app")));
            }
            throw new InvalidOperationException("Unexpected call: "+method.Name);
        });
        var physical=new FakePhysicalAuthority();
        var handler=Handler(
            new FakePermissionEvaluator(PurchasingPermissions.ReceiptReverse),
            persistence,physical,new FakeWarehouseAccessEvaluator(true));

        var result=handler.ReverseReceiptAsync(receiptId,"receipt-reverse",NewContext(),CancellationToken.None)
            .GetAwaiter().GetResult();

        AssertTrue(result.IsSuccess);
        AssertEqual(1,physical.PostCalls);
        AssertTrue(physical.LastCommand?.Source?.Disposition==InventoryDispositionCode.Quarantine);
        AssertTrue(physical.LastCommand?.Target is null);
        AssertEqual(originalMovementId,physical.LastCommand!.ReversalOfMovementPublicId);
        AssertEqual(1,completed);
    }

    private static void DirectInvoiceRequiresExplicitPermission()
    {
        var calls=0;
        var persistence=Proxy<IPurchasingPersistence>((method,_)=>{
            calls++;
            throw new InvalidOperationException("Persistence must not be reached: "+method.Name);
        });
        var handler=Handler(
            new FakePermissionEvaluator(PurchasingPermissions.InvoiceCreate),
            persistence,new FakePhysicalAuthority(),new FakeWarehouseAccessEvaluator(true));

        var result=handler.CreateInvoiceDraftAsync(
            new CreateSupplierInvoiceDraftCommand(
                "PI-1",Guid.NewGuid(),Mars.Domain.Purchasing.SupplierInvoiceSourceMode.Direct,
                new DateOnly(2026,9,24),new DateOnly(2026,9,24),"TRY",0m,
                [new CreateSupplierInvoiceDraftLineInput(
                    1,Guid.NewGuid(),null,Guid.NewGuid(),1m,100m,0m,20m,null,null,null)],
                "Controlled direct financial-only invoice","direct-invoice"),
            NewContext(),CancellationToken.None).GetAwaiter().GetResult();

        AssertTrue(result.IsFailure);
        AssertEqual(ErrorCategory.Authorization,result.Error!.Category);
        AssertEqual(0,calls);
    }

    private static PurchasingCommandHandler Handler(
        IPermissionEvaluator permissions,
        IPurchasingPersistence persistence,
        IInventoryPhysicalAuthority physical,
        IWarehouseAccessEvaluator warehouse) =>
        new(
            permissions,
            persistence,
            new FakeApprovalAuthority(),
            physical,
            new FakeFinanceValuationAuthority(),
            warehouse,
            new PassthroughTransactions());

    private static T Proxy<T>(Func<MethodInfo,object?[]?,object?> handler) where T:class
    {
        var proxy=DispatchProxy.Create<T,TestDispatchProxy>();
        ((TestDispatchProxy)(object)proxy).Handler=handler;
        return proxy;
    }

    private static IExecutionContext NewContext() =>
        new MarsExecutionContext(
            Guid.Parse("aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"),
            Guid.Parse("bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb"),
            null,
            new CorrelationId("corr-purchasing-app"));

    private sealed class FakePermissionEvaluator(params string[] permissions):IPermissionEvaluator
    {
        private readonly HashSet<string> granted=new(permissions,StringComparer.Ordinal);
        public Task<bool> IsGrantedAsync(Guid actorId,Guid companyId,string permissionCode,CancellationToken cancellationToken)=>
            Task.FromResult(granted.Contains(permissionCode));
    }

    private sealed class FakeWarehouseAccessEvaluator(bool granted):IWarehouseAccessEvaluator
    {
        public Task<bool> IsGrantedAsync(Guid actorId,Guid companyId,Guid warehousePublicId,CancellationToken cancellationToken)=>
            Task.FromResult(granted);
    }

    private sealed class FakePhysicalAuthority(bool fail=false):IInventoryPhysicalAuthority
    {
        public int PostCalls { get; private set; }
        public InventoryMovementCommand? LastCommand { get; private set; }

        public Task<Result<InventoryMovementReceipt>> PostAsync(
            InventoryMovementCommand command,IExecutionContext context,CancellationToken cancellationToken)
        {
            PostCalls++;
            LastCommand=command;
            return Task.FromResult(
                fail
                    ? Result<InventoryMovementReceipt>.Failure(
                        new ApplicationError(ErrorCategory.BusinessRule,"inventory.test.failure","Test Inventory failure."))
                    : Result<InventoryMovementReceipt>.Success(
                        new InventoryMovementReceipt(Guid.NewGuid(),command.EnteredQuantity*command.ConversionFactorSnapshot,context.CorrelationId.Value)));
        }
    }

    private sealed class FakeApprovalAuthority:IApprovalDecisionAuthority
    {
        public Task<Result<ApprovalDecisionReceipt>> DecideAsync(
            ApprovalDecisionCommand command,IExecutionContext context,CancellationToken cancellationToken)=>
            throw new InvalidOperationException("Approval is not used by this test.");

        public Task<bool> IsApprovedAsync(
            Guid companyId,string module,string entityType,Guid entityPublicId,long snapshotVersion,CancellationToken cancellationToken)=>
            Task.FromResult(false);
    }

    private sealed class PassthroughTransactions:IPurchasingTransactionCoordinator
    {
        public Task<Result<T>> ExecuteAsync<T>(
            Func<CancellationToken,Task<Result<T>>> operation,CancellationToken cancellationToken)=>
            operation(cancellationToken);
    }

    public class TestDispatchProxy:DispatchProxy
    {
        public Func<MethodInfo,object?[]?,object?> Handler { get; set; }=
            (_,_)=>throw new InvalidOperationException("Proxy handler not configured.");
        protected override object? Invoke(MethodInfo? targetMethod,object?[]? args)=>
            Handler(targetMethod??throw new InvalidOperationException("Missing target method."),args);
    }

    private static void AssertTrue(bool value)
    {
        if(!value)throw new InvalidOperationException("Assertion failed.");
    }

    private static void AssertEqual<T>(T expected,T actual)
    {
        if(!EqualityComparer<T>.Default.Equals(expected,actual))
            throw new InvalidOperationException($"Expected '{expected}', actual '{actual}'.");
    }
}
