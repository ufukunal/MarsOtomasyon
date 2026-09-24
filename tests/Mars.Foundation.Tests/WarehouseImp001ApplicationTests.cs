using System.Reflection;
using Mars.Application.Foundation.Approvals;
using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Application.Inventory;
using Mars.Application.Warehouse;
using Mars.Domain.Inventory;
using Mars.Domain.Warehouse;
using MarsExecutionContext = Mars.Application.Foundation.Context.ExecutionContext;

internal static class WarehouseImp001ApplicationTests
{
    public static IReadOnlyList<(string Name, Action Test)> Cases { get; } =
    [
        ("WAREHOUSE-IMP-001 put-away delegates one net-zero Inventory movement", PutAwayDelegatesInternalMovement),
        ("WAREHOUSE-IMP-001 pick requires Warehouse scope", PickRequiresWarehouseScope),
        ("WAREHOUSE-IMP-001 pick binds Sales source without physical Inventory posting", PickHasNoPhysicalPosting),
        ("WAREHOUSE-IMP-001 Transfer ISSUE composes AVAILABLE to TRANSIT movement", TransferIssueComposesTransit),
        ("WAREHOUSE-IMP-001 positive Count adjustment fails closed without valuation", PositiveCountFailsClosed),
        ("WAREHOUSE-IMP-001 damage permission cannot target AVAILABLE", DamageCannotTargetAvailable)
    ];

    private static void PutAwayDelegatesInternalMovement()
    {
        var wh=Guid.NewGuid();var srcLoc=Guid.NewGuid();var dstLoc=Guid.NewGuid();
        var physical=new FakePhysicalAuthority();
        var persistence=Proxy<IWarehousePersistence>((method,args)=>{
            if(method.Name==nameof(IWarehousePersistence.CompleteInternalMoveAsync))
                return Task.FromResult(Result<WarehouseMutationReceipt>.Success(
                    new WarehouseMutationReceipt(Guid.NewGuid(),"COMPLETED",1,"corr-wh")));
            throw new InvalidOperationException("Unexpected persistence call: "+method.Name);
        });
        var handler=Handler(new FakePermissionEvaluator(WarehousePermissions.PutAwayExecute),
            new FakeWarehouseAccessEvaluator(true),physical,new FakeSalesDispatchAuthority(),persistence);

        var result=handler.ExecuteInternalMoveAsync(new InternalMoveCommand(
            InternalMoveKind.PutAway,Guid.NewGuid(),null,Guid.NewGuid(),2m,1m,
            new InventoryPosition(wh,srcLoc,InventoryDispositionCode.Available,null,null),
            new InventoryPosition(wh,dstLoc,InventoryDispositionCode.Available,null,null),
            null,null,"putaway-1"),NewContext(),CancellationToken.None).GetAwaiter().GetResult();

        AssertTrue(result.IsSuccess);
        AssertEqual(1,physical.PostCalls);
        AssertEqual(wh,physical.LastCommand!.Source!.WarehousePublicId);
        AssertEqual(wh,physical.LastCommand.Target!.WarehousePublicId);
        AssertEqual(physical.LastCommand.Source.Disposition,physical.LastCommand.Target.Disposition);
    }

    private static void PickRequiresWarehouseScope()
    {
        var persistenceCalls=0;
        var persistence=Proxy<IWarehousePersistence>((method,args)=>{
            persistenceCalls++;
            throw new InvalidOperationException("Persistence must not be reached: "+method.Name);
        });
        var handler=Handler(new FakePermissionEvaluator(WarehousePermissions.PickExecute),
            new FakeWarehouseAccessEvaluator(false),new FakePhysicalAuthority(),new FakeSalesDispatchAuthority(),persistence);

        var result=handler.RecordPickAsync(new RecordPickCommand(
            Guid.NewGuid(),1,Guid.NewGuid(),Guid.NewGuid(),1m,Guid.NewGuid(),null,null,false,null,"pick-scope"),
            NewContext(),CancellationToken.None).GetAwaiter().GetResult();

        AssertTrue(result.IsFailure);
        AssertEqual(ErrorCategory.Authorization,result.Error!.Category);
        AssertEqual(0,persistenceCalls);
    }

    private static void PickHasNoPhysicalPosting()
    {
        var dispatch=Guid.NewGuid();var line=Guid.NewGuid();var wh=Guid.NewGuid();var location=Guid.NewGuid();
        var physical=new FakePhysicalAuthority();
        var sales=new FakeSalesDispatchAuthority();
        var plan=new PickPlan(
            Guid.NewGuid(),"pick-1",dispatch,line,Guid.NewGuid(),null,Guid.NewGuid(),wh,1m,1m,
            location,null,null,null,false,null);
        var persistence=Proxy<IWarehousePersistence>((method,args)=>{
            if(method.Name==nameof(IWarehousePersistence.PreparePickAsync))
                return Task.FromResult(Result<PickPlan>.Success(plan));
            if(method.Name==nameof(IWarehousePersistence.CompletePickAsync))
                return Task.FromResult(Result<WarehouseMutationReceipt>.Success(
                    new WarehouseMutationReceipt(plan.PickWorkPublicId,"PICKED",1,"corr-wh")));
            throw new InvalidOperationException("Unexpected persistence call: "+method.Name);
        });
        var handler=Handler(new FakePermissionEvaluator(WarehousePermissions.PickExecute),
            new FakeWarehouseAccessEvaluator(true),physical,sales,persistence);

        var result=handler.RecordPickAsync(new RecordPickCommand(
            dispatch,1,line,wh,1m,location,null,null,false,null,"pick-1"),
            NewContext(),CancellationToken.None).GetAwaiter().GetResult();

        AssertTrue(result.IsSuccess);
        AssertEqual(0,physical.PostCalls);
        AssertEqual(1,sales.BindCalls);
    }

    private static void TransferIssueComposesTransit()
    {
        var transfer=Guid.NewGuid();var line=Guid.NewGuid();
        var sourceWh=Guid.NewGuid();var targetWh=Guid.NewGuid();
        var plan=new TransferIssuePlan(transfer,sourceWh,targetWh,
        [
            new TransferIssueLinePlan(
                line,Guid.NewGuid(),null,Guid.NewGuid(),1m,3m,
                new InventoryPosition(sourceWh,Guid.NewGuid(),InventoryDispositionCode.Available,null,null),
                new InventoryPosition(targetWh,null,InventoryDispositionCode.Transit,null,null))
        ]);
        var physical=new FakePhysicalAuthority();
        var persistence=Proxy<IWarehousePersistence>((method,args)=>{
            if(method.Name==nameof(IWarehousePersistence.GetTransferWarehouseAsync))
                return Task.FromResult<Guid?>((bool)args![2]!?targetWh:sourceWh);
            if(method.Name==nameof(IWarehousePersistence.PrepareTransferIssueAsync))
                return Task.FromResult(Result<TransferIssuePlan>.Success(plan));
            if(method.Name==nameof(IWarehousePersistence.CompleteTransferIssueAsync))
                return Task.FromResult(Result<WarehouseMutationReceipt>.Success(
                    new WarehouseMutationReceipt(transfer,"ISSUED",2,"corr-wh")));
            throw new InvalidOperationException("Unexpected persistence call: "+method.Name);
        });
        var handler=Handler(new FakePermissionEvaluator(WarehousePermissions.TransferIssue),
            new FakeWarehouseAccessEvaluator(true),physical,new FakeSalesDispatchAuthority(),persistence);

        var result=handler.IssueTransferAsync(transfer,1,"issue-1",NewContext(),CancellationToken.None)
            .GetAwaiter().GetResult();

        AssertTrue(result.IsSuccess);
        AssertEqual(1,physical.PostCalls);
        AssertEqual(InventoryDispositionCode.Available,physical.LastCommand!.Source!.Disposition);
        AssertEqual(InventoryDispositionCode.Transit,physical.LastCommand.Target!.Disposition);
        AssertEqual(targetWh,physical.LastCommand.Target.WarehousePublicId);
        AssertTrue(physical.LastCommand.Target.LocationPublicId is null);
    }

    private static void PositiveCountFailsClosed()
    {
        var count=Guid.NewGuid();var wh=Guid.NewGuid();
        var physical=new FakePhysicalAuthority();
        var plan=new CountAdjustmentPlan(
            count,wh,Guid.NewGuid(),[],2,
            [new CountAdjustmentPlanLine(
                Guid.NewGuid(),Guid.NewGuid(),null,Guid.NewGuid(),1m,1m,
                new InventoryPosition(wh,Guid.NewGuid(),InventoryDispositionCode.Available,null,null))]);
        var persistence=Proxy<IWarehousePersistence>((method,args)=>{
            if(method.Name==nameof(IWarehousePersistence.GetCountWarehouseAsync))
                return Task.FromResult<Guid?>(wh);
            if(method.Name==nameof(IWarehousePersistence.GetCountPostPlanAsync))
                return Task.FromResult(Result<CountAdjustmentPlan>.Success(plan));
            throw new InvalidOperationException("Unexpected persistence call: "+method.Name);
        });
        var handler=Handler(new FakePermissionEvaluator(WarehousePermissions.CountPost),
            new FakeWarehouseAccessEvaluator(true),physical,new FakeSalesDispatchAuthority(),persistence);

        var result=handler.PostCountAsync(count,"count-post",NewContext(),CancellationToken.None)
            .GetAwaiter().GetResult();

        AssertTrue(result.IsFailure);
        AssertEqual("warehouse.count.positive_valuation_required",result.Error!.Code);
        AssertEqual(0,physical.PostCalls);
    }

    private static void DamageCannotTargetAvailable()
    {
        var wh=Guid.NewGuid();var loc=Guid.NewGuid();
        var persistence=Proxy<IWarehousePersistence>((method,args)=>
            throw new InvalidOperationException("Persistence must not be reached: "+method.Name));
        var physical=new FakePhysicalAuthority();
        var handler=Handler(new FakePermissionEvaluator(WarehousePermissions.DamageRecord),
            new FakeWarehouseAccessEvaluator(true),physical,new FakeSalesDispatchAuthority(),persistence);

        var result=handler.RecordDamageAsync(new ChangeDispositionCommand(
            Guid.NewGuid(),null,Guid.NewGuid(),1m,1m,
            new InventoryPosition(wh,loc,InventoryDispositionCode.Available,null,null),
            new InventoryPosition(wh,loc,InventoryDispositionCode.Available,null,null),
            "damage",null,null,"damage-1"),NewContext(),CancellationToken.None).GetAwaiter().GetResult();

        AssertTrue(result.IsFailure);
        AssertEqual("warehouse.damage.target",result.Error!.Code);
        AssertEqual(0,physical.PostCalls);
    }

    private static WarehouseCommandHandler Handler(
        IPermissionEvaluator permissions,IWarehouseAccessEvaluator access,
        IInventoryPhysicalAuthority physical,ISalesWarehouseDispatchAuthority sales,IWarehousePersistence persistence)=>
        new(permissions,access,physical,sales,new FakeApprovalAuthority(),persistence,new PassthroughTransactions());

    private static T Proxy<T>(Func<MethodInfo,object?[]?,object?> handler) where T:class
    {
        var proxy=DispatchProxy.Create<T,TestDispatchProxy>();
        ((TestDispatchProxy)(object)proxy).Handler=handler;
        return proxy;
    }

    private static IExecutionContext NewContext()=>
        new MarsExecutionContext(
            Guid.Parse("aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"),
            Guid.Parse("bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb"),
            null,new CorrelationId("corr-wh"));

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

    private sealed class FakePhysicalAuthority:IInventoryPhysicalAuthority
    {
        public int PostCalls{get;private set;}
        public InventoryMovementCommand? LastCommand{get;private set;}
        public Task<Result<InventoryMovementReceipt>> PostAsync(
            InventoryMovementCommand command,IExecutionContext context,CancellationToken cancellationToken)
        {
            PostCalls++;LastCommand=command;
            return Task.FromResult(Result<InventoryMovementReceipt>.Success(
                new InventoryMovementReceipt(Guid.NewGuid(),command.EnteredQuantity*command.ConversionFactorSnapshot,context.CorrelationId.Value)));
        }
    }

    private sealed class FakeSalesDispatchAuthority:ISalesWarehouseDispatchAuthority
    {
        public int BindCalls{get;private set;}
        public Task<Result<WarehouseMutationReceipt>> BindSourceAsync(
            BindDispatchSourceCommand command,IExecutionContext context,CancellationToken cancellationToken)
        {
            BindCalls++;
            return Task.FromResult(Result<WarehouseMutationReceipt>.Success(
                new WarehouseMutationReceipt(command.DispatchPublicId,"DRAFT",command.ExpectedVersion+1,context.CorrelationId.Value)));
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

    private sealed class PassthroughTransactions:IWarehouseTransactionCoordinator
    {
        public Task<Result<T>> ExecuteAsync<T>(
            Func<CancellationToken,Task<Result<T>>> operation,CancellationToken cancellationToken)=>
            operation(cancellationToken);
    }

    public class TestDispatchProxy:DispatchProxy
    {
        public Func<MethodInfo,object?[]?,object?> Handler{get;set;}=
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
