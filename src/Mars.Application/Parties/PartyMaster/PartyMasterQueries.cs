using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;

namespace Mars.Application.Parties.PartyMaster;

public sealed record PartyListItem(
    Guid PublicId,
    string PartyCode,
    string Kind,
    string LegalName,
    string? DisplayName,
    string State,
    long Version);

public sealed record PartyRoleView(
    string Role,
    string State,
    long Version);

public sealed record PartyCommunicationPointView(
    Guid PublicId,
    string Type,
    string Value,
    string? Purpose,
    bool IsPrimary,
    string State,
    long Version);

public sealed record PartyContactView(
    Guid PublicId,
    string Name,
    string? Title,
    string? Purpose,
    string State,
    long Version,
    IReadOnlyList<PartyCommunicationPointView> CommunicationPoints);

public sealed record PartyAddressView(
    Guid PublicId,
    string Purpose,
    string Country,
    string? City,
    string? District,
    string? PostalCode,
    string? Line1,
    string? Line2,
    string? Label,
    bool IsDefault,
    string State,
    long Version);

public sealed record PartyTaxIdentityView(
    Guid PublicId,
    string Jurisdiction,
    string Scheme,
    string Value,
    bool IsMasked,
    string State,
    long Version);

public sealed record PartyExternalMappingView(
    Guid PublicId,
    string SystemCode,
    string? AccountScope,
    string ExternalIdentity,
    string State,
    long Version);

public sealed record PartyDetailView(
    Guid PublicId,
    string PartyCode,
    string Kind,
    string LegalName,
    string? DisplayName,
    string State,
    long Version,
    Guid? MergeSurvivorPublicId,
    IReadOnlyList<PartyRoleView> Roles,
    IReadOnlyList<PartyContactView> Contacts,
    IReadOnlyList<PartyAddressView> Addresses,
    IReadOnlyList<PartyTaxIdentityView> TaxIdentities,
    IReadOnlyList<PartyExternalMappingView> ExternalMappings);

public sealed record PartyDetailReadOptions(
    bool IncludeContacts,
    bool IncludeAddresses,
    bool IncludeTaxIdentities,
    bool IncludeFullTaxIdentityValues,
    bool IncludeExternalMappings);

public interface IPartyMasterReadPersistence
{
    Task<IReadOnlyList<PartyListItem>> ListAsync(
        Guid companyId,
        string? search,
        CancellationToken cancellationToken);

    Task<PartyDetailView?> GetAsync(
        Guid companyId,
        Guid partyPublicId,
        PartyDetailReadOptions options,
        CancellationToken cancellationToken);
}

public sealed class PartyMasterQueryHandler(
    IPermissionEvaluator permissionEvaluator,
    IPartyMasterReadPersistence persistence)
{
    public async Task<Result<IReadOnlyList<PartyListItem>>> ListAsync(
        string? search,
        IExecutionContext executionContext,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(executionContext);
        cancellationToken.ThrowIfCancellationRequested();

        if (!await IsGrantedAsync(PartyPermissions.Read, executionContext, cancellationToken))
        {
            return Result<IReadOnlyList<PartyListItem>>.Failure(PermissionDenied("read Parties"));
        }

        var normalizedSearch = string.IsNullOrWhiteSpace(search) ? null : search.Trim();
        var items = await persistence.ListAsync(
            executionContext.CompanyId,
            normalizedSearch,
            cancellationToken);

        return Result<IReadOnlyList<PartyListItem>>.Success(items);
    }

    public async Task<Result<PartyDetailView>> GetAsync(
        Guid partyPublicId,
        IExecutionContext executionContext,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(executionContext);
        cancellationToken.ThrowIfCancellationRequested();

        if (partyPublicId == Guid.Empty)
        {
            return Result<PartyDetailView>.Failure(new ApplicationError(
                ErrorCategory.Validation,
                "parties.read.party_required",
                "Party public id is required."));
        }

        if (!await IsGrantedAsync(PartyPermissions.Read, executionContext, cancellationToken))
        {
            return Result<PartyDetailView>.Failure(PermissionDenied("read Parties"));
        }

        var canReadContacts = await IsGrantedAsync(
            PartyPermissions.ContactRead,
            executionContext,
            cancellationToken);
        var canReadAddresses = await IsGrantedAsync(
            PartyPermissions.AddressRead,
            executionContext,
            cancellationToken);
        var canReadTax = await IsGrantedAsync(
            PartyPermissions.TaxIdentityRead,
            executionContext,
            cancellationToken);
        var canReadFullTax = canReadTax && await IsGrantedAsync(
            PartyPermissions.TaxIdentityReadFull,
            executionContext,
            cancellationToken);
        var canReadExternalMappings = await IsGrantedAsync(
            PartyPermissions.ExternalMappingRead,
            executionContext,
            cancellationToken);

        var detail = await persistence.GetAsync(
            executionContext.CompanyId,
            partyPublicId,
            new PartyDetailReadOptions(
                canReadContacts,
                canReadAddresses,
                canReadTax,
                canReadFullTax,
                canReadExternalMappings),
            cancellationToken);

        if (detail is null)
        {
            return Result<PartyDetailView>.Failure(new ApplicationError(
                ErrorCategory.NotFound,
                "parties.party_not_found",
                "Party was not found in the current company."));
        }

        if (canReadTax && !canReadFullTax && detail.TaxIdentities.Count > 0)
        {
            detail = detail with
            {
                TaxIdentities = detail.TaxIdentities
                    .Select(identity => identity with
                    {
                        Value = Mask(identity.Value),
                        IsMasked = true
                    })
                    .ToArray()
            };
        }

        return Result<PartyDetailView>.Success(detail);
    }

    private Task<bool> IsGrantedAsync(
        string permission,
        IExecutionContext executionContext,
        CancellationToken cancellationToken) =>
        permissionEvaluator.IsGrantedAsync(
            executionContext.ActorId,
            executionContext.CompanyId,
            permission,
            cancellationToken);

    private static ApplicationError PermissionDenied(string action) =>
        new(
            ErrorCategory.Authorization,
            "authorization.permission_denied",
            $"The current actor is not authorized to {action}.");

    private static string Mask(string value)
    {
        if (string.IsNullOrEmpty(value)) return string.Empty;
        var visible = Math.Min(4, value.Length);
        return new string('*', value.Length - visible) + value[^visible..];
    }
}
