namespace Mars.Application.Warehouse;

public static class WarehousePermissions
{
    public const string WarehouseRead = "warehouse.read";
    public const string WarehouseManage = "warehouse.manage";
    public const string WarehouseDeactivate = "warehouse.deactivate";
    public const string LocationRead = "warehouse.location.read";
    public const string LocationManage = "warehouse.location.manage";
    public const string LocationDeactivate = "warehouse.location.deactivate";

    public const string ReceivingRead = "warehouse.receiving.read";
    public const string PutAwayExecute = "warehouse.putaway.execute";
    public const string DispositionRead = "warehouse.disposition.read";
    public const string DispositionRelease = "warehouse.disposition.release";
    public const string DispositionChange = "warehouse.disposition.change";

    public const string PickRead = "warehouse.pick.read";
    public const string PickExecute = "warehouse.pick.execute";
    public const string PickStrategyOverride = "warehouse.pick.strategy_override";
    public const string PackExecute = "warehouse.pack.execute";
    public const string StageExecute = "warehouse.stage.execute";
    public const string LoadExecute = "warehouse.load.execute";

    public const string TransferRead = "warehouse.transfer.read";
    public const string TransferCreate = "warehouse.transfer.create";
    public const string TransferIssue = "warehouse.transfer.issue";
    public const string TransferReceive = "warehouse.transfer.receive";
    public const string TransferReconcile = "warehouse.transfer.reconcile";
    public const string TransferLossAdjust = "warehouse.transfer.loss_adjust";
    public const string TransferReverse = "warehouse.transfer.reverse";

    public const string CountRead = "warehouse.count.read";
    public const string CountCreate = "warehouse.count.create";
    public const string CountExecute = "warehouse.count.execute";
    public const string CountReview = "warehouse.count.review";
    public const string CountApprove = "warehouse.count.approve";
    public const string CountPost = "warehouse.count.post";
    public const string CountReverse = "warehouse.count.reverse";

    public const string DamageRecord = "warehouse.damage.record";
    public const string ScrapRequest = "warehouse.scrap.request";
    public const string ScrapApprove = "warehouse.scrap.approve";
    public const string ScrapPost = "warehouse.scrap.post";
    public const string TraceRead = "warehouse.trace.read";

    public static IReadOnlyList<string> All { get; } =
    [
        WarehouseRead, WarehouseManage, WarehouseDeactivate,
        LocationRead, LocationManage, LocationDeactivate,
        ReceivingRead, PutAwayExecute, DispositionRead, DispositionRelease, DispositionChange,
        PickRead, PickExecute, PickStrategyOverride, PackExecute, StageExecute, LoadExecute,
        TransferRead, TransferCreate, TransferIssue, TransferReceive, TransferReconcile,
        TransferLossAdjust, TransferReverse,
        CountRead, CountCreate, CountExecute, CountReview, CountApprove, CountPost, CountReverse,
        DamageRecord, ScrapRequest, ScrapApprove, ScrapPost, TraceRead
    ];
}
