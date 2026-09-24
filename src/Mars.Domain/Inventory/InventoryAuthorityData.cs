namespace Mars.Domain.Inventory;

public enum InventoryMasterState
{
    Active = 1,
    Inactive = 2
}

public enum InventoryDispositionCode
{
    Available = 1,
    Quarantine = 2,
    QualityHold = 3,
    Rework = 4,
    Damaged = 5,
    Transit = 6
}

public enum ReservationMovementKind
{
    Create = 1,
    Increase = 2,
    Release = 3,
    Consume = 4
}

public sealed record Warehouse(
    Guid PublicId,
    Guid CompanyId,
    string Code,
    string Name,
    InventoryMasterState State,
    long Version,
    DateTimeOffset CreatedAt)
{
    public static Warehouse Create(
        Guid publicId,
        Guid companyId,
        string code,
        string name,
        DateTimeOffset createdAt)
    {
        RequiredId(publicId, nameof(publicId));
        RequiredId(companyId, nameof(companyId));
        return new Warehouse(
            publicId,
            companyId,
            RequiredText(code, nameof(code)),
            RequiredText(name, nameof(name)),
            InventoryMasterState.Active,
            1,
            createdAt);
    }

    internal static void RequiredId(Guid value, string name)
    {
        if (value == Guid.Empty) throw new ArgumentException("Identifier is required.", name);
    }

    internal static string RequiredText(string value, string name)
    {
        if (string.IsNullOrWhiteSpace(value))
            throw new ArgumentException("Value is required.", name);
        if (!string.Equals(value, value.Trim(), StringComparison.Ordinal))
            throw new ArgumentException("Value cannot contain leading or trailing whitespace.", name);
        return value;
    }

    internal static string? OptionalText(string? value)
    {
        if (string.IsNullOrWhiteSpace(value)) return null;
        if (!string.Equals(value, value.Trim(), StringComparison.Ordinal))
            throw new ArgumentException("Value cannot contain leading or trailing whitespace.");
        return value;
    }
}

public sealed record Location(
    Guid PublicId,
    Guid CompanyId,
    Guid WarehousePublicId,
    Guid? ParentLocationPublicId,
    string Code,
    string Name,
    bool StockBearing,
    InventoryMasterState State,
    long Version,
    DateTimeOffset CreatedAt)
{
    public static Location Create(
        Guid publicId,
        Guid companyId,
        Guid warehousePublicId,
        Guid? parentLocationPublicId,
        string code,
        string name,
        bool stockBearing,
        DateTimeOffset createdAt)
    {
        Warehouse.RequiredId(publicId, nameof(publicId));
        Warehouse.RequiredId(companyId, nameof(companyId));
        Warehouse.RequiredId(warehousePublicId, nameof(warehousePublicId));
        if (parentLocationPublicId == Guid.Empty)
            throw new ArgumentException("Parent Location id cannot be empty.", nameof(parentLocationPublicId));
        if (parentLocationPublicId == publicId)
            throw new ArgumentException("Location cannot be its own parent.", nameof(parentLocationPublicId));

        return new Location(
            publicId,
            companyId,
            warehousePublicId,
            parentLocationPublicId,
            Warehouse.RequiredText(code, nameof(code)),
            Warehouse.RequiredText(name, nameof(name)),
            stockBearing,
            InventoryMasterState.Active,
            1,
            createdAt);
    }
}

public sealed record Lot(
    Guid PublicId,
    Guid CompanyId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    string Code,
    DateOnly? ManufactureDate,
    DateOnly? ExpiryDate,
    long Version,
    DateTimeOffset CreatedAt)
{
    public static Lot Create(
        Guid publicId,
        Guid companyId,
        Guid productPublicId,
        Guid? variantPublicId,
        string code,
        DateOnly? manufactureDate,
        DateOnly? expiryDate,
        DateTimeOffset createdAt)
    {
        Warehouse.RequiredId(publicId, nameof(publicId));
        Warehouse.RequiredId(companyId, nameof(companyId));
        Warehouse.RequiredId(productPublicId, nameof(productPublicId));
        if (variantPublicId == Guid.Empty)
            throw new ArgumentException("Variant id cannot be empty.", nameof(variantPublicId));
        if (manufactureDate.HasValue && expiryDate.HasValue && expiryDate.Value < manufactureDate.Value)
            throw new ArgumentException("Expiry date cannot be before manufacture date.", nameof(expiryDate));

        return new Lot(
            publicId,
            companyId,
            productPublicId,
            variantPublicId,
            Warehouse.RequiredText(code, nameof(code)),
            manufactureDate,
            expiryDate,
            1,
            createdAt);
    }
}

public sealed record Serial(
    Guid PublicId,
    Guid CompanyId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid? LotPublicId,
    string Value,
    long Version,
    DateTimeOffset CreatedAt)
{
    public static Serial Create(
        Guid publicId,
        Guid companyId,
        Guid productPublicId,
        Guid? variantPublicId,
        Guid? lotPublicId,
        string value,
        DateTimeOffset createdAt)
    {
        Warehouse.RequiredId(publicId, nameof(publicId));
        Warehouse.RequiredId(companyId, nameof(companyId));
        Warehouse.RequiredId(productPublicId, nameof(productPublicId));
        if (variantPublicId == Guid.Empty)
            throw new ArgumentException("Variant id cannot be empty.", nameof(variantPublicId));
        if (lotPublicId == Guid.Empty)
            throw new ArgumentException("Lot id cannot be empty.", nameof(lotPublicId));

        return new Serial(
            publicId,
            companyId,
            productPublicId,
            variantPublicId,
            lotPublicId,
            Warehouse.RequiredText(value, nameof(value)),
            1,
            createdAt);
    }
}

public sealed record InventoryPosition(
    Guid WarehousePublicId,
    Guid? LocationPublicId,
    InventoryDispositionCode Disposition,
    Guid? LotPublicId,
    Guid? SerialPublicId)
{
    public static InventoryPosition Create(
        Guid warehousePublicId,
        Guid? locationPublicId,
        InventoryDispositionCode disposition,
        Guid? lotPublicId,
        Guid? serialPublicId)
    {
        Warehouse.RequiredId(warehousePublicId, nameof(warehousePublicId));
        if (locationPublicId == Guid.Empty)
            throw new ArgumentException("Location id cannot be empty.", nameof(locationPublicId));
        if (lotPublicId == Guid.Empty)
            throw new ArgumentException("Lot id cannot be empty.", nameof(lotPublicId));
        if (serialPublicId == Guid.Empty)
            throw new ArgumentException("Serial id cannot be empty.", nameof(serialPublicId));
        if (!Enum.IsDefined(disposition))
            throw new ArgumentOutOfRangeException(nameof(disposition));
        return new InventoryPosition(
            warehousePublicId,
            locationPublicId,
            disposition,
            lotPublicId,
            serialPublicId);
    }
}

public sealed record InventorySourceIdentity(
    string Module,
    string EntityType,
    Guid DocumentPublicId,
    Guid? LinePublicId)
{
    public static InventorySourceIdentity Create(
        string module,
        string entityType,
        Guid documentPublicId,
        Guid? linePublicId)
    {
        Warehouse.RequiredId(documentPublicId, nameof(documentPublicId));
        if (linePublicId == Guid.Empty)
            throw new ArgumentException("Line id cannot be empty.", nameof(linePublicId));
        return new InventorySourceIdentity(
            Warehouse.RequiredText(module, nameof(module)),
            Warehouse.RequiredText(entityType, nameof(entityType)),
            documentPublicId,
            linePublicId);
    }
}
