namespace Mars.Application.Products;

public static class ProductPermissions
{
    public const string Read = "product.read";
    public const string Create = "product.create";
    public const string Edit = "product.edit";
    public const string Deactivate = "product.deactivate";
    public const string Reactivate = "product.reactivate";
    public const string Export = "product.export";
    public const string VariantRead = "product.variant.read";
    public const string VariantManage = "product.variant.manage";
    public const string UomRead = "product.uom.read";
    public const string UomManage = "product.uom.manage";
    public const string UomChangeBase = "product.uom.change_base";
    public const string BarcodeRead = "product.barcode.read";
    public const string BarcodeManage = "product.barcode.manage";
    public const string CategoryRead = "product.category.read";
    public const string CategoryManage = "product.category.manage";

    public static IReadOnlyList<string> All { get; } =
    [
        Read, Create, Edit, Deactivate, Reactivate, Export,
        VariantRead, VariantManage, UomRead, UomManage, UomChangeBase,
        BarcodeRead, BarcodeManage, CategoryRead, CategoryManage
    ];
}
