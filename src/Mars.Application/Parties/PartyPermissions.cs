namespace Mars.Application.Parties;

public static class PartyPermissions
{
    public const string Read = "party.read";
    public const string Create = "party.create";
    public const string Edit = "party.edit";
    public const string Deactivate = "party.deactivate";
    public const string Reactivate = "party.reactivate";

    public const string RoleRead = "party.role.read";
    public const string RoleManage = "party.role.manage";

    public const string ContactRead = "party.contact.read";
    public const string ContactManage = "party.contact.manage";

    public const string AddressRead = "party.address.read";
    public const string AddressManage = "party.address.manage";

    public const string TaxIdentityRead = "party.tax_identity.read";
    public const string TaxIdentityReadFull = "party.tax_identity.read_full";
    public const string TaxIdentityManage = "party.tax_identity.manage";

    public const string DuplicateReview = "party.duplicate.review";
    public const string DuplicateKeepSeparate = "party.duplicate.keep_separate";
    public const string Merge = "party.merge";

    public const string ExternalMappingRead = "party.external_mapping.read";
    public const string ExternalMappingManage = "party.external_mapping.manage";

    public static IReadOnlyList<string> All { get; } =
    [
        Read,
        Create,
        Edit,
        Deactivate,
        Reactivate,
        RoleRead,
        RoleManage,
        ContactRead,
        ContactManage,
        AddressRead,
        AddressManage,
        TaxIdentityRead,
        TaxIdentityReadFull,
        TaxIdentityManage,
        DuplicateReview,
        DuplicateKeepSeparate,
        Merge,
        ExternalMappingRead,
        ExternalMappingManage
    ];
}
