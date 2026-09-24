using System.Reflection;
using Mars.Application.Foundation.Approvals;
using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Application.Inventory;
using Mars.Application.Sales;
using Mars.Domain.Inventory;
using MarsExecutionContext = Mars.Application.Foundation.Context.ExecutionContext;

internal static class SalesImp001ApplicationTests
{
    public static IReadOnlyList<(string Name, Action Test)> Cases { get; } =
    [
        ("SALES-IMP-001 direct Order confirmation fails closed without approval", DirectOrderConfirmationFailsClosed),
        ("SALES-IMP-001 inherited Quote approval avoids duplicate Order approval", InheritedApprovalConfirmsWithoutDuplicateDecision),
        ("SALES-IMP-001 Dispatch create requires Warehouse scope", DispatchCreateRequiresWarehouseScope),
        ("SALES-IMP-001 Dispatch POST delegates physical effect and Reservation consume to Inventory", DispatchPostDelegatesInventoryAuthority),
        ("WAREHOUSE-IMP-001 multi-source pick drives multiple Sales physical effects", MultiSourceDispatchPostDelegatesEachAllocation),
        ("SALES-IMP-001 Dispatch POST stops atomically before consume/completion when Inventory fails", DispatchPostStopsOnInventoryFailure)
    ];

    private static void DirectOrderConfirmationFailsClosed()
    {
        var target = new SalesApprovalTarget(
            Guid.NewGuid(), 1, Guid.NewGuid(), "SalesOrder");
        var persistence = Proxy<ISalesPersistence>((method, _) =>
            method.Name == nameof(ISalesPersistence.GetOrderApprovalTargetAsync)
                ? Task.FromResult(Result<SalesApprovalTarget>.Success(target))
                : throw new InvalidOperationException("Unexpected persistence call: " + method.Name));
        var approvals = new FakeApprovalAuthority(false);
        var inventory = new FakePhysicalAuthority();
        var reservations = new FakeReservationAuthority();

        var handler = Handler(
            new FakePermissionEvaluator(SalesPermissions.OrderConfirm),
            persistence,
            approvals,
            reservations,
            inventory,
            new FakeWarehouseAccessEvaluator(true));

        var result = handler.ConfirmOrderAsync(
                target.EntityPublicId, 1, "confirm-direct", NewContext(), CancellationToken.None)
            .GetAwaiter().GetResult();

        AssertTrue(result.IsFailure);
        AssertEqual(ErrorCategory.BusinessRule, result.Error!.Category);
        AssertEqual("sales.order.approval.required", result.Error.Code);
        AssertEqual(1, approvals.IsApprovedCalls);
        AssertEqual(0, inventory.PostCalls);
        AssertEqual(0, reservations.TotalCalls);
    }

    private static void InheritedApprovalConfirmsWithoutDuplicateDecision()
    {
        var orderId = Guid.NewGuid();
        var target = new SalesApprovalTarget(
            orderId, 1, Guid.NewGuid(), "SalesOrderInheritedApproval");
        var confirmCalls = 0;
        var persistence = Proxy<ISalesPersistence>((method, _) =>
        {
            if (method.Name == nameof(ISalesPersistence.GetOrderApprovalTargetAsync))
                return Task.FromResult(Result<SalesApprovalTarget>.Success(target));
            if (method.Name == nameof(ISalesPersistence.ConfirmOrderAsync))
            {
                confirmCalls++;
                return Task.FromResult(Result<SalesMutationReceipt>.Success(
                    new SalesMutationReceipt(orderId, "CONFIRMED", 2, "corr-sales-app")));
            }

            throw new InvalidOperationException("Unexpected persistence call: " + method.Name);
        });
        var approvals = new FakeApprovalAuthority(false);
        var inventory = new FakePhysicalAuthority();
        var reservations = new FakeReservationAuthority();

        var handler = Handler(
            new FakePermissionEvaluator(SalesPermissions.OrderConfirm),
            persistence,
            approvals,
            reservations,
            inventory,
            new FakeWarehouseAccessEvaluator(true));

        var result = handler.ConfirmOrderAsync(
                orderId, 1, "confirm-inherited", NewContext(), CancellationToken.None)
            .GetAwaiter().GetResult();

        AssertTrue(result.IsSuccess);
        AssertEqual(1, confirmCalls);
        AssertEqual(0, approvals.IsApprovedCalls);
        AssertEqual(0, inventory.PostCalls);
        AssertEqual(0, reservations.TotalCalls);
    }

    private static void DispatchCreateRequiresWarehouseScope()
    {
        var persistenceCalls = 0;
        var persistence = Proxy<ISalesPersistence>((method, _) =>
        {
            persistenceCalls++;
            throw new InvalidOperationException("Persistence must not be reached when Warehouse scope is denied: " + method.Name);
        });
        var handler = Handler(
            new FakePermissionEvaluator(SalesPermissions.DispatchCreate),
            persistence,
            new FakeApprovalAuthority(false),
            new FakeReservationAuthority(),
            new FakePhysicalAuthority(),
            new FakeWarehouseAccessEvaluator(false));

        var result = handler.CreateDispatchAsync(
                new CreateDispatchCommand(
                    "DSP-1",
                    Guid.NewGuid(),
                    1,
                    Guid.NewGuid(),
                    [new CreateDispatchLineInput(1, Guid.NewGuid(), 1m, null, null, null, null)],
                    "dispatch-create"),
                NewContext(),
                CancellationToken.None)
            .GetAwaiter().GetResult();

        AssertTrue(result.IsFailure);
        AssertEqual(ErrorCategory.Authorization, result.Error!.Category);
        AssertEqual("sales.warehouse.scope_denied", result.Error.Code);
        AssertEqual(0, persistenceCalls);
    }

    private static void DispatchPostDelegatesInventoryAuthority()
    {
        var dispatchId = Guid.NewGuid();
        var lineId = Guid.NewGuid();
        var reservationId = Guid.NewGuid();
        var movementId = Guid.NewGuid();
        var warehouseId = Guid.NewGuid();
        IReadOnlyList<SalesDispatchEffect>? completedEffects = null;

        var plan = new SalesDispatchPostPlan(
            dispatchId,
            Guid.NewGuid(),
            warehouseId,
            [
                new SalesDispatchPostLinePlan(
                    lineId,
                    Guid.NewGuid(),
                    Guid.NewGuid(),
                    Guid.NewGuid(),
                    null,
                    Guid.NewGuid(),
                    1m,
                    3m,
                    InventoryPosition.Create(
                        warehouseId,
                        null,
                        InventoryDispositionCode.Available,
                        null,
                        null),
                    reservationId)
            ]);

        var persistence = Proxy<ISalesPersistence>((method, args) =>
        {
            if (method.Name == nameof(ISalesPersistence.PrepareDispatchPostAsync))
                return Task.FromResult(Result<SalesDispatchPostPlan>.Success(plan));
            if (method.Name == nameof(ISalesPersistence.CompleteDispatchPostAsync))
            {
                completedEffects = (IReadOnlyList<SalesDispatchEffect>)args![1]!;
                return Task.FromResult(Result<SalesMutationReceipt>.Success(
                    new SalesMutationReceipt(dispatchId, "POSTED", 2, "corr-sales-app")));
            }

            throw new InvalidOperationException("Unexpected persistence call: " + method.Name);
        });

        var physical = new FakePhysicalAuthority(movementId);
        var reservations = new FakeReservationAuthority();
        var handler = Handler(
            new FakePermissionEvaluator(SalesPermissions.DispatchPost),
            persistence,
            new FakeApprovalAuthority(false),
            reservations,
            physical,
            new FakeWarehouseAccessEvaluator(true));

        var result = handler.PostDispatchAsync(
                dispatchId, "dispatch-post", NewContext(), CancellationToken.None)
            .GetAwaiter().GetResult();

        AssertTrue(result.IsSuccess);
        AssertEqual(1, physical.PostCalls);
        AssertEqual(1, reservations.ConsumeCalls);
        AssertTrue(completedEffects is not null);
        AssertEqual(1, completedEffects!.Count);
        AssertEqual(lineId, completedEffects[0].DispatchLinePublicId);
        AssertEqual(movementId, completedEffects[0].InventoryMovementPublicId);
        AssertTrue(!completedEffects[0].IsReversal);
    }

    private static void MultiSourceDispatchPostDelegatesEachAllocation()
    {
        var dispatchId=Guid.NewGuid();
        var dispatchLineId=Guid.NewGuid();
        var reservationId=Guid.NewGuid();
        var warehouseId=Guid.NewGuid();
        var productId=Guid.NewGuid();
        var uomId=Guid.NewGuid();
        var orderLineId=Guid.NewGuid();
        IReadOnlyList<SalesDispatchEffect>? completed=null;

        var plan=new SalesDispatchPostPlan(
            dispatchId,Guid.NewGuid(),warehouseId,
            [
                new SalesDispatchPostLinePlan(
                    dispatchLineId,Guid.NewGuid(),orderLineId,productId,null,uomId,1m,1m,
                    InventoryPosition.Create(warehouseId,Guid.NewGuid(),InventoryDispositionCode.Available,null,null),
                    reservationId),
                new SalesDispatchPostLinePlan(
                    dispatchLineId,Guid.NewGuid(),orderLineId,productId,null,uomId,1m,2m,
                    InventoryPosition.Create(warehouseId,Guid.NewGuid(),InventoryDispositionCode.Available,null,null),
                    reservationId)
            ]);

        var persistence=Proxy<ISalesPersistence>((method,args)=>{
            if(method.Name==nameof(ISalesPersistence.PrepareDispatchPostAsync))
                return Task.FromResult(Result<SalesDispatchPostPlan>.Success(plan));
            if(method.Name==nameof(ISalesPersistence.CompleteDispatchPostAsync))
            {
                completed=(IReadOnlyList<SalesDispatchEffect>)args![1]!;
                return Task.FromResult(Result<SalesMutationReceipt>.Success(
                    new SalesMutationReceipt(dispatchId,"POSTED",2,"corr-sales-app")));
            }
            throw new InvalidOperationException("Unexpected persistence call: "+method.Name);
        });

        var physical=new FakePhysicalAuthority();
        var reservations=new FakeReservationAuthority();
        var handler=Handler(
            new FakePermissionEvaluator(SalesPermissions.DispatchPost),
            persistence,new FakeApprovalAuthority(false),reservations,physical,
            new FakeWarehouseAccessEvaluator(true));

        var result=handler.PostDispatchAsync(
            dispatchId,"dispatch-multi-source",NewContext(),CancellationToken.None)
            .GetAwaiter().GetResult();

        AssertTrue(result.IsSuccess);
        AssertEqual(2,physical.PostCalls);
        AssertEqual(2,reservations.ConsumeCalls);
        AssertTrue(completed is not null);
        AssertEqual(2,completed!.Count);
        AssertTrue(completed.All(x=>x.DispatchLinePublicId==dispatchLineId));
        AssertEqual(2,completed.Select(x=>x.InventoryMovementPublicId).Distinct().Count());
    }

    private static void DispatchPostStopsOnInventoryFailure()
    {
        var dispatchId = Guid.NewGuid();
        var warehouseId = Guid.NewGuid();
        var plan = new SalesDispatchPostPlan(
            dispatchId,
            Guid.NewGuid(),
            warehouseId,
            [
                new SalesDispatchPostLinePlan(
                    Guid.NewGuid(),
                    Guid.NewGuid(),
                    Guid.NewGuid(),
                    Guid.NewGuid(),
                    null,
                    Guid.NewGuid(),
                    1m,
                    1m,
                    InventoryPosition.Create(
                        warehouseId,
                        null,
                        InventoryDispositionCode.Available,
                        null,
                        null),
                    Guid.NewGuid())
            ]);
        var completeCalls = 0;
        var persistence = Proxy<ISalesPersistence>((method, _) =>
        {
            if (method.Name == nameof(ISalesPersistence.PrepareDispatchPostAsync))
                return Task.FromResult(Result<SalesDispatchPostPlan>.Success(plan));
            if (method.Name == nameof(ISalesPersistence.CompleteDispatchPostAsync))
            {
                completeCalls++;
                throw new InvalidOperationException("Dispatch completion must not run after failed stock authority.");
            }

            throw new InvalidOperationException("Unexpected persistence call: " + method.Name);
        });
        var physical = new FakePhysicalAuthority(fail: true);
        var reservations = new FakeReservationAuthority();
        var handler = Handler(
            new FakePermissionEvaluator(SalesPermissions.DispatchPost),
            persistence,
            new FakeApprovalAuthority(false),
            reservations,
            physical,
            new FakeWarehouseAccessEvaluator(true));

        var result = handler.PostDispatchAsync(
                dispatchId, "dispatch-fail", NewContext(), CancellationToken.None)
            .GetAwaiter().GetResult();

        AssertTrue(result.IsFailure);
        AssertEqual(1, physical.PostCalls);
        AssertEqual(0, reservations.ConsumeCalls);
        AssertEqual(0, completeCalls);
    }

    private static SalesCommandHandler Handler(
        IPermissionEvaluator permissions,
        ISalesPersistence persistence,
        IApprovalDecisionAuthority approvals,
        IInventoryReservationAuthority reservations,
        IInventoryPhysicalAuthority physical,
        IWarehouseAccessEvaluator warehouse) =>
        new(
            permissions,
            persistence,
            approvals,
            reservations,
            physical,
            warehouse,
            new PassthroughTransactions());

    private static T Proxy<T>(Func<MethodInfo, object?[]?, object?> handler)
        where T : class
    {
        var proxy = DispatchProxy.Create<T, TestDispatchProxy>();
        ((TestDispatchProxy)(object)proxy).Handler = handler;
        return proxy;
    }

    private static IExecutionContext NewContext() =>
        new MarsExecutionContext(
            Guid.Parse("aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"),
            Guid.Parse("bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb"),
            null,
            new CorrelationId("corr-sales-app"));

    private sealed class FakePermissionEvaluator(params string[] permissions) : IPermissionEvaluator
    {
        private readonly HashSet<string> granted = new(permissions, StringComparer.Ordinal);

        public Task<bool> IsGrantedAsync(
            Guid actorId,
            Guid companyId,
            string permissionCode,
            CancellationToken cancellationToken) =>
            Task.FromResult(granted.Contains(permissionCode));
    }

    private sealed class FakeApprovalAuthority(bool approved) : IApprovalDecisionAuthority
    {
        public int IsApprovedCalls { get; private set; }

        public Task<Result<ApprovalDecisionReceipt>> DecideAsync(
            ApprovalDecisionCommand command,
            IExecutionContext context,
            CancellationToken cancellationToken) =>
            throw new InvalidOperationException("No approval decision should be created in this test.");

        public Task<bool> IsApprovedAsync(
            Guid companyId,
            string module,
            string entityType,
            Guid entityPublicId,
            long snapshotVersion,
            CancellationToken cancellationToken)
        {
            IsApprovedCalls++;
            return Task.FromResult(approved);
        }
    }

    private sealed class FakeWarehouseAccessEvaluator(bool granted) : IWarehouseAccessEvaluator
    {
        public Task<bool> IsGrantedAsync(
            Guid actorId,
            Guid companyId,
            Guid warehousePublicId,
            CancellationToken cancellationToken) =>
            Task.FromResult(granted);
    }

    private sealed class FakePhysicalAuthority : IInventoryPhysicalAuthority
    {
        private readonly Guid? fixedMovementId;
        private readonly bool fail;

        public FakePhysicalAuthority(Guid? movementId = null, bool fail = false)
        {
            fixedMovementId = movementId;
            this.fail = fail;
        }

        public int PostCalls { get; private set; }

        public Task<Result<InventoryMovementReceipt>> PostAsync(
            InventoryMovementCommand command,
            IExecutionContext context,
            CancellationToken cancellationToken)
        {
            PostCalls++;
            return Task.FromResult(
                fail
                    ? Result<InventoryMovementReceipt>.Failure(
                        new ApplicationError(
                            ErrorCategory.BusinessRule,
                            "inventory.stock.insufficient",
                            "Test Inventory failure."))
                    : Result<InventoryMovementReceipt>.Success(
                        new InventoryMovementReceipt(
                            fixedMovementId ?? Guid.NewGuid(),
                            command.EnteredQuantity * command.ConversionFactorSnapshot,
                            context.CorrelationId.Value)));
        }
    }

    private sealed class FakeReservationAuthority : IInventoryReservationAuthority
    {
        public int CreateCalls { get; private set; }
        public int IncreaseCalls { get; private set; }
        public int ReleaseCalls { get; private set; }
        public int ConsumeCalls { get; private set; }
        public int TotalCalls => CreateCalls + IncreaseCalls + ReleaseCalls + ConsumeCalls;

        public Task<Result<InventoryReservationReceipt>> CreateAsync(
            CreateReservationCommand command,
            IExecutionContext context,
            CancellationToken cancellationToken)
        {
            CreateCalls++;
            return Success(Guid.NewGuid(), command.EnteredQuantity * command.ConversionFactorSnapshot, context);
        }

        public Task<Result<InventoryReservationReceipt>> IncreaseAsync(
            ChangeReservationCommand command,
            IExecutionContext context,
            CancellationToken cancellationToken)
        {
            IncreaseCalls++;
            return Success(command.ReservationPublicId, command.EnteredQuantity, context);
        }

        public Task<Result<InventoryReservationReceipt>> ReleaseAsync(
            ChangeReservationCommand command,
            IExecutionContext context,
            CancellationToken cancellationToken)
        {
            ReleaseCalls++;
            return Success(command.ReservationPublicId, 0m, context);
        }

        public Task<Result<InventoryReservationReceipt>> ConsumeAsync(
            ChangeReservationCommand command,
            IExecutionContext context,
            CancellationToken cancellationToken)
        {
            ConsumeCalls++;
            return Success(command.ReservationPublicId, 0m, context);
        }

        private static Task<Result<InventoryReservationReceipt>> Success(
            Guid id,
            decimal quantity,
            IExecutionContext context) =>
            Task.FromResult(Result<InventoryReservationReceipt>.Success(
                new InventoryReservationReceipt(id, quantity, context.CorrelationId.Value)));
    }

    private sealed class PassthroughTransactions : ISalesTransactionCoordinator
    {
        public Task<Result<T>> ExecuteAsync<T>(
            Func<CancellationToken, Task<Result<T>>> operation,
            CancellationToken cancellationToken) =>
            operation(cancellationToken);
    }

    public class TestDispatchProxy : DispatchProxy
    {
        public Func<MethodInfo, object?[]?, object?> Handler { get; set; } =
            (_, _) => throw new InvalidOperationException("Proxy handler not configured.");

        protected override object? Invoke(MethodInfo? targetMethod, object?[]? args) =>
            Handler(targetMethod ?? throw new InvalidOperationException("Missing target method."), args);
    }

    private static void AssertTrue(bool condition)
    {
        if (!condition)
            throw new InvalidOperationException("Assertion failed.");
    }

    private static void AssertEqual<T>(T expected, T actual)
    {
        if (!EqualityComparer<T>.Default.Equals(expected, actual))
            throw new InvalidOperationException(
                $"Expected '{expected}', actual '{actual}'.");
    }
}
