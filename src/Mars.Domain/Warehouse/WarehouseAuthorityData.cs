namespace Mars.Domain.Warehouse;

public enum WarehouseWorkState
{
    Open = 1,
    Assigned = 2,
    InProgress = 3,
    PartiallyCompleted = 4,
    Completed = 5,
    Cancelled = 6
}

public enum PickWorkState
{
    Open = 1,
    InProgress = 2,
    PartiallyPicked = 3,
    Picked = 4,
    Closed = 5,
    Cancelled = 6
}

public enum PackageState
{
    Open = 1,
    Packed = 2,
    Staged = 3,
    Loaded = 4,
    Cancelled = 5
}

public enum TransferState
{
    Draft = 1,
    Issued = 2,
    PartiallyReceived = 3,
    Received = 4,
    ReconciliationRequired = 5,
    Closed = 6,
    Cancelled = 7,
    Reversed = 8
}

public enum StockCountState
{
    Draft = 1,
    Counting = 2,
    Review = 3,
    PendingApproval = 4,
    Approved = 5,
    Posted = 6,
    Closed = 7,
    Cancelled = 8,
    Reversed = 9
}

public enum ScrapState
{
    Requested = 1,
    PendingApproval = 2,
    Approved = 3,
    Posted = 4,
    Cancelled = 5,
    Reversed = 6
}

public enum OfflineOperationState
{
    Pending = 1,
    Succeeded = 2,
    Conflicted = 3,
    Rejected = 4
}

public enum PickStrategy
{
    Fifo = 1,
    Fefo = 2
}

public enum InternalMoveKind
{
    PutAway = 1,
    Replenishment = 2
}

public enum StageLoadKind
{
    Stage = 1,
    Load = 2
}

public enum TransferReceiptDisposition
{
    Available = 1,
    Damaged = 2,
    QualityHold = 3
}
