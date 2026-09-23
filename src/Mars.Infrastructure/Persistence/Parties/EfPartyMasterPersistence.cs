using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Parties.PartyMaster;
using Mars.Domain.Parties;
using Microsoft.EntityFrameworkCore;
using Npgsql;

namespace Mars.Infrastructure.Persistence.Parties;

public sealed class EfPartyMasterPersistence(
    MarsDbContext dbContext,
    IAuditWriter auditWriter,
    IIdempotencyStore idempotencyStore)
    : IPartyMasterReadPersistence, IPartyMasterMutationPersistence
{
    public async Task<IReadOnlyList<PartyListItem>> ListAsync(
        Guid companyId,
        string? search,
        CancellationToken cancellationToken)
    {
        var query = dbContext.Set<PartyRecord>()
            .AsNoTracking()
            .Where(x => x.CompanyId == companyId);

        if (!string.IsNullOrWhiteSpace(search))
        {
            var pattern = $"%{search.Trim()}%";
            query = query.Where(x =>
                EF.Functions.ILike(x.PartyCode, pattern) ||
                EF.Functions.ILike(x.LegalName, pattern) ||
                (x.DisplayName != null && EF.Functions.ILike(x.DisplayName, pattern)));
        }

        return await query
            .OrderBy(x => x.PartyCode)
            .ThenBy(x => x.Id)
            .Take(250)
            .Select(x => new PartyListItem(
                x.PublicId,
                x.PartyCode,
                x.Kind == PartyKind.Person ? "PERSON" : "ORGANIZATION",
                x.LegalName,
                x.DisplayName,
                x.State == PartyState.Active ? "ACTIVE" :
                    x.State == PartyState.Inactive ? "INACTIVE" : "MERGED",
                x.Version))
            .ToArrayAsync(cancellationToken);
    }

    public async Task<PartyDetailView?> GetAsync(
        Guid companyId,
        Guid partyPublicId,
        PartyDetailReadOptions options,
        CancellationToken cancellationToken)
    {
        var party = await dbContext.Set<PartyRecord>()
            .AsNoTracking()
            .SingleOrDefaultAsync(
                x => x.CompanyId == companyId && x.PublicId == partyPublicId,
                cancellationToken);

        if (party is null) return null;

        var roles = await dbContext.Set<PartyRoleRecord>()
            .AsNoTracking()
            .Where(x => x.PartyId == party.Id)
            .OrderBy(x => x.RoleType)
            .Select(x => new PartyRoleView(
                x.RoleType == PartyRoleType.Customer ? "CUSTOMER" : "SUPPLIER",
                x.State == PartyRoleState.Active ? "ACTIVE" : "INACTIVE",
                x.Version))
            .ToArrayAsync(cancellationToken);

        IReadOnlyList<PartyContactView> contacts = Array.Empty<PartyContactView>();
        if (options.IncludeContacts)
        {
            var contactRows = await dbContext.Set<PartyContactRecord>()
                .AsNoTracking()
                .Where(x => x.PartyId == party.Id && x.CompanyId == companyId)
                .OrderBy(x => x.Id)
                .ToArrayAsync(cancellationToken);

            var contactIds = contactRows.Select(x => x.Id).ToArray();
            var communicationRows = contactIds.Length == 0
                ? Array.Empty<PartyCommunicationPointRecord>()
                : await dbContext.Set<PartyCommunicationPointRecord>()
                    .AsNoTracking()
                    .Where(x => contactIds.Contains(x.ContactId))
                    .OrderBy(x => x.Id)
                    .ToArrayAsync(cancellationToken);

            contacts = contactRows
                .Select(contact => new PartyContactView(
                    contact.PublicId,
                    contact.Name,
                    contact.Title,
                    contact.Purpose,
                    ToState(contact.State),
                    contact.Version,
                    communicationRows
                        .Where(point => point.ContactId == contact.Id)
                        .Select(point => new PartyCommunicationPointView(
                            point.PublicId,
                            point.Type,
                            point.Value,
                            point.Purpose,
                            point.IsPrimary,
                            ToState(point.State),
                            point.Version))
                        .ToArray()))
                .ToArray();
        }

        IReadOnlyList<PartyAddressView> addresses = Array.Empty<PartyAddressView>();
        if (options.IncludeAddresses)
        {
            addresses = await dbContext.Set<PartyAddressRecord>()
                .AsNoTracking()
                .Where(x => x.PartyId == party.Id && x.CompanyId == companyId)
                .OrderBy(x => x.Purpose)
                .ThenByDescending(x => x.IsDefault)
                .ThenBy(x => x.Id)
                .Select(x => new PartyAddressView(
                    x.PublicId,
                    x.Purpose == PartyAddressPurpose.Billing ? "BILLING" :
                        x.Purpose == PartyAddressPurpose.Shipping ? "SHIPPING" : "GENERAL",
                    x.Country,
                    x.City,
                    x.District,
                    x.PostalCode,
                    x.Line1,
                    x.Line2,
                    x.Label,
                    x.IsDefault,
                    x.State == PartyMasterRecordState.Active ? "ACTIVE" : "INACTIVE",
                    x.Version))
                .ToArrayAsync(cancellationToken);
        }

        IReadOnlyList<PartyTaxIdentityView> taxIdentities = Array.Empty<PartyTaxIdentityView>();
        if (options.IncludeTaxIdentities)
        {
            taxIdentities = await dbContext.Set<PartyTaxIdentityRecord>()
                .AsNoTracking()
                .Where(x => x.PartyId == party.Id && x.CompanyId == companyId)
                .OrderBy(x => x.Id)
                .Select(x => new PartyTaxIdentityView(
                    x.PublicId,
                    x.Jurisdiction,
                    x.Scheme == PartyTaxIdentityScheme.Vkn ? "VKN" : "TCKN",
                    x.Value,
                    false,
                    x.State == PartyTaxIdentityState.Active ? "ACTIVE" : "INACTIVE",
                    x.Version))
                .ToArrayAsync(cancellationToken);
        }

        IReadOnlyList<PartyExternalMappingView> externalMappings = Array.Empty<PartyExternalMappingView>();
        if (options.IncludeExternalMappings)
        {
            externalMappings = await dbContext.Set<PartyExternalMappingRecord>()
                .AsNoTracking()
                .Where(x => x.PartyId == party.Id && x.CompanyId == companyId)
                .OrderBy(x => x.SystemCode)
                .ThenBy(x => x.Id)
                .Select(x => new PartyExternalMappingView(
                    x.PublicId,
                    x.SystemCode,
                    x.AccountScope == string.Empty ? null : x.AccountScope,
                    x.ExternalIdentity,
                    x.State == PartyMasterRecordState.Active ? "ACTIVE" : "INACTIVE",
                    x.Version))
                .ToArrayAsync(cancellationToken);
        }

        var survivorPublicId = await (
                from lineage in dbContext.Set<PartyMergeLineageRecord>().AsNoTracking()
                join survivor in dbContext.Set<PartyRecord>().AsNoTracking()
                    on lineage.SurvivorPartyId equals survivor.Id
                where lineage.SourcePartyId == party.Id && lineage.CompanyId == companyId
                select (Guid?)survivor.PublicId)
            .SingleOrDefaultAsync(cancellationToken);

        return new PartyDetailView(
            party.PublicId,
            party.PartyCode,
            party.Kind == PartyKind.Person ? "PERSON" : "ORGANIZATION",
            party.LegalName,
            party.DisplayName,
            party.State == PartyState.Active ? "ACTIVE" :
                party.State == PartyState.Inactive ? "INACTIVE" : "MERGED",
            party.Version,
            survivorPublicId,
            roles,
            contacts,
            addresses,
            taxIdentities,
            externalMappings);
    }

    public Task<PartyMasterMutationPersistenceResult> EditIdentityAsync(
        EditPartyIdentityWrite write,
        CancellationToken cancellationToken) =>
        ExecuteMutationAsync(
            write.Context,
            async () =>
            {
                var party = await FindPartyAsync(
                    write.PartyPublicId,
                    write.Context.CompanyId,
                    cancellationToken);
                if (party is null) return Outcome(PartyMasterMutationOutcome.PartyNotFound);
                if (party.State == PartyState.Merged) return Outcome(PartyMasterMutationOutcome.StateConflict);
                if (party.Version != write.ExpectedVersion) return Outcome(PartyMasterMutationOutcome.StaleVersion);

                party.LegalName = write.LegalName;
                party.DisplayName = write.DisplayName;
                party.Version = checked(party.Version + 1);

                return Success(party.PublicId, PartyStateCode(party.State), party.Version);
            },
            "parties.edit.completed",
            cancellationToken);

    public Task<PartyMasterMutationPersistenceResult> CreateContactAsync(
        CreatePartyContactWrite write,
        CancellationToken cancellationToken) =>
        ExecuteMutationAsync(
            write.Context,
            async () =>
            {
                var party = await FindPartyAsync(
                    write.Contact.PartyPublicId,
                    write.Context.CompanyId,
                    cancellationToken);
                if (party is null) return Outcome(PartyMasterMutationOutcome.PartyNotFound);
                if (party.State == PartyState.Merged) return Outcome(PartyMasterMutationOutcome.StateConflict);

                dbContext.Add(new PartyContactRecord
                {
                    PublicId = write.Contact.PublicId,
                    PartyId = party.Id,
                    CompanyId = write.Context.CompanyId,
                    Name = write.Contact.Name,
                    Title = write.Contact.Title,
                    Purpose = write.Contact.Purpose,
                    State = write.Contact.State,
                    Version = write.Contact.Version,
                    CreatedAt = write.Contact.CreatedAt
                });

                return Success(write.Contact.PublicId, "ACTIVE", write.Contact.Version);
            },
            "parties.contact.create.completed",
            cancellationToken);

    public Task<PartyMasterMutationPersistenceResult> ChangeContactStateAsync(
        ChangePartyContactStateWrite write,
        CancellationToken cancellationToken) =>
        ExecuteMutationAsync(
            write.Context,
            async () =>
            {
                var party = await FindPartyAsync(
                    write.PartyPublicId,
                    write.Context.CompanyId,
                    cancellationToken);
                if (party is null) return Outcome(PartyMasterMutationOutcome.PartyNotFound);
                if (party.State == PartyState.Merged) return Outcome(PartyMasterMutationOutcome.StateConflict);

                var contact = await dbContext.Set<PartyContactRecord>()
                    .SingleOrDefaultAsync(
                        x => x.PublicId == write.ContactPublicId &&
                             x.PartyId == party.Id &&
                             x.CompanyId == write.Context.CompanyId,
                        cancellationToken);
                if (contact is null) return Outcome(PartyMasterMutationOutcome.ChildNotFound);
                if (contact.Version != write.ExpectedVersion) return Outcome(PartyMasterMutationOutcome.StaleVersion);
                if (contact.State == write.TargetState) return Outcome(PartyMasterMutationOutcome.StateConflict);

                contact.State = write.TargetState;
                contact.Version = checked(contact.Version + 1);
                return Success(contact.PublicId, ToState(contact.State), contact.Version);
            },
            "parties.contact.state.completed",
            cancellationToken);

    public Task<PartyMasterMutationPersistenceResult> CreateCommunicationPointAsync(
        CreatePartyCommunicationPointWrite write,
        CancellationToken cancellationToken) =>
        ExecuteMutationAsync(
            write.Context,
            async () =>
            {
                var party = await FindPartyAsync(
                    write.CommunicationPoint.PartyPublicId,
                    write.Context.CompanyId,
                    cancellationToken);
                if (party is null) return Outcome(PartyMasterMutationOutcome.PartyNotFound);
                if (party.State == PartyState.Merged) return Outcome(PartyMasterMutationOutcome.StateConflict);

                var contact = await dbContext.Set<PartyContactRecord>()
                    .SingleOrDefaultAsync(
                        x => x.PublicId == write.CommunicationPoint.ContactPublicId &&
                             x.PartyId == party.Id &&
                             x.CompanyId == write.Context.CompanyId,
                        cancellationToken);
                if (contact is null) return Outcome(PartyMasterMutationOutcome.ChildNotFound);

                if (write.CommunicationPoint.IsPrimary)
                {
                    var existingPrimary = await dbContext.Set<PartyCommunicationPointRecord>()
                        .Where(x => x.ContactId == contact.Id &&
                                    x.Type == write.CommunicationPoint.Type &&
                                    x.State == PartyMasterRecordState.Active &&
                                    x.IsPrimary)
                        .ToArrayAsync(cancellationToken);
                    foreach (var point in existingPrimary)
                    {
                        point.IsPrimary = false;
                        point.Version = checked(point.Version + 1);
                    }
                }

                dbContext.Add(new PartyCommunicationPointRecord
                {
                    PublicId = write.CommunicationPoint.PublicId,
                    ContactId = contact.Id,
                    Type = write.CommunicationPoint.Type,
                    Value = write.CommunicationPoint.Value,
                    Purpose = write.CommunicationPoint.Purpose,
                    IsPrimary = write.CommunicationPoint.IsPrimary,
                    State = write.CommunicationPoint.State,
                    Version = write.CommunicationPoint.Version,
                    CreatedAt = write.CommunicationPoint.CreatedAt
                });

                return Success(
                    write.CommunicationPoint.PublicId,
                    "ACTIVE",
                    write.CommunicationPoint.Version);
            },
            "parties.communication.create.completed",
            cancellationToken);

    public Task<PartyMasterMutationPersistenceResult> ChangeCommunicationPointStateAsync(
        ChangePartyCommunicationPointStateWrite write,
        CancellationToken cancellationToken) =>
        ExecuteMutationAsync(
            write.Context,
            async () =>
            {
                var party = await FindPartyAsync(
                    write.PartyPublicId,
                    write.Context.CompanyId,
                    cancellationToken);
                if (party is null) return Outcome(PartyMasterMutationOutcome.PartyNotFound);
                if (party.State == PartyState.Merged) return Outcome(PartyMasterMutationOutcome.StateConflict);

                var contactId = await dbContext.Set<PartyContactRecord>()
                    .AsNoTracking()
                    .Where(x => x.PublicId == write.ContactPublicId &&
                                x.PartyId == party.Id &&
                                x.CompanyId == write.Context.CompanyId)
                    .Select(x => (long?)x.Id)
                    .SingleOrDefaultAsync(cancellationToken);
                if (contactId is null) return Outcome(PartyMasterMutationOutcome.ChildNotFound);

                var point = await dbContext.Set<PartyCommunicationPointRecord>()
                    .SingleOrDefaultAsync(
                        x => x.PublicId == write.CommunicationPointPublicId &&
                             x.ContactId == contactId.Value,
                        cancellationToken);
                if (point is null) return Outcome(PartyMasterMutationOutcome.ChildNotFound);
                if (point.Version != write.ExpectedVersion) return Outcome(PartyMasterMutationOutcome.StaleVersion);
                if (point.State == write.TargetState) return Outcome(PartyMasterMutationOutcome.StateConflict);

                point.State = write.TargetState;
                if (write.TargetState == PartyMasterRecordState.Inactive) point.IsPrimary = false;
                point.Version = checked(point.Version + 1);
                return Success(point.PublicId, ToState(point.State), point.Version);
            },
            "parties.communication.state.completed",
            cancellationToken);

    public Task<PartyMasterMutationPersistenceResult> CreateAddressAsync(
        CreatePartyAddressWrite write,
        CancellationToken cancellationToken) =>
        ExecuteMutationAsync(
            write.Context,
            async () =>
            {
                var party = await FindPartyAsync(
                    write.Address.PartyPublicId,
                    write.Context.CompanyId,
                    cancellationToken);
                if (party is null) return Outcome(PartyMasterMutationOutcome.PartyNotFound);
                if (party.State == PartyState.Merged) return Outcome(PartyMasterMutationOutcome.StateConflict);

                if (write.Address.IsDefault)
                {
                    await ClearAddressDefaultAsync(
                        party.Id,
                        write.Address.Purpose,
                        exceptId: null,
                        cancellationToken);
                }

                dbContext.Add(new PartyAddressRecord
                {
                    PublicId = write.Address.PublicId,
                    PartyId = party.Id,
                    CompanyId = write.Context.CompanyId,
                    Purpose = write.Address.Purpose,
                    Country = write.Address.Country,
                    City = write.Address.City,
                    District = write.Address.District,
                    PostalCode = write.Address.PostalCode,
                    Line1 = write.Address.Line1,
                    Line2 = write.Address.Line2,
                    Label = write.Address.Label,
                    IsDefault = write.Address.IsDefault,
                    State = write.Address.State,
                    Version = write.Address.Version,
                    CreatedAt = write.Address.CreatedAt
                });

                return Success(write.Address.PublicId, "ACTIVE", write.Address.Version);
            },
            "parties.address.create.completed",
            cancellationToken);

    public Task<PartyMasterMutationPersistenceResult> UpdateAddressAsync(
        UpdatePartyAddressWrite write,
        CancellationToken cancellationToken) =>
        ExecuteMutationAsync(
            write.Context,
            async () =>
            {
                var party = await FindPartyAsync(
                    write.PartyPublicId,
                    write.Context.CompanyId,
                    cancellationToken);
                if (party is null) return Outcome(PartyMasterMutationOutcome.PartyNotFound);
                if (party.State == PartyState.Merged) return Outcome(PartyMasterMutationOutcome.StateConflict);

                var address = await dbContext.Set<PartyAddressRecord>()
                    .SingleOrDefaultAsync(
                        x => x.PublicId == write.AddressPublicId &&
                             x.PartyId == party.Id &&
                             x.CompanyId == write.Context.CompanyId,
                        cancellationToken);
                if (address is null) return Outcome(PartyMasterMutationOutcome.ChildNotFound);
                if (address.Version != write.ExpectedVersion) return Outcome(PartyMasterMutationOutcome.StaleVersion);

                if (write.IsDefault && address.State == PartyMasterRecordState.Active)
                {
                    await ClearAddressDefaultAsync(
                        party.Id,
                        write.Purpose,
                        address.Id,
                        cancellationToken);
                }

                address.Purpose = write.Purpose;
                address.Country = write.Country;
                address.City = write.City;
                address.District = write.District;
                address.PostalCode = write.PostalCode;
                address.Line1 = write.Line1;
                address.Line2 = write.Line2;
                address.Label = write.Label;
                address.IsDefault = address.State == PartyMasterRecordState.Active && write.IsDefault;
                address.Version = checked(address.Version + 1);

                return Success(address.PublicId, ToState(address.State), address.Version);
            },
            "parties.address.update.completed",
            cancellationToken);

    public Task<PartyMasterMutationPersistenceResult> ChangeAddressStateAsync(
        ChangePartyAddressStateWrite write,
        CancellationToken cancellationToken) =>
        ExecuteMutationAsync(
            write.Context,
            async () =>
            {
                var party = await FindPartyAsync(
                    write.PartyPublicId,
                    write.Context.CompanyId,
                    cancellationToken);
                if (party is null) return Outcome(PartyMasterMutationOutcome.PartyNotFound);
                if (party.State == PartyState.Merged) return Outcome(PartyMasterMutationOutcome.StateConflict);

                var address = await dbContext.Set<PartyAddressRecord>()
                    .SingleOrDefaultAsync(
                        x => x.PublicId == write.AddressPublicId &&
                             x.PartyId == party.Id &&
                             x.CompanyId == write.Context.CompanyId,
                        cancellationToken);
                if (address is null) return Outcome(PartyMasterMutationOutcome.ChildNotFound);
                if (address.Version != write.ExpectedVersion) return Outcome(PartyMasterMutationOutcome.StaleVersion);
                if (address.State == write.TargetState) return Outcome(PartyMasterMutationOutcome.StateConflict);

                address.State = write.TargetState;
                if (write.TargetState == PartyMasterRecordState.Inactive) address.IsDefault = false;
                address.Version = checked(address.Version + 1);

                return Success(address.PublicId, ToState(address.State), address.Version);
            },
            "parties.address.state.completed",
            cancellationToken);

    public Task<PartyMasterMutationPersistenceResult> ChangeTaxIdentityStateAsync(
        ChangePartyTaxIdentityStateWrite write,
        CancellationToken cancellationToken) =>
        ExecuteMutationAsync(
            write.Context,
            async () =>
            {
                var party = await FindPartyAsync(
                    write.PartyPublicId,
                    write.Context.CompanyId,
                    cancellationToken);
                if (party is null) return Outcome(PartyMasterMutationOutcome.PartyNotFound);
                if (party.State == PartyState.Merged) return Outcome(PartyMasterMutationOutcome.StateConflict);

                var identity = await dbContext.Set<PartyTaxIdentityRecord>()
                    .SingleOrDefaultAsync(
                        x => x.PublicId == write.TaxIdentityPublicId &&
                             x.PartyId == party.Id &&
                             x.CompanyId == write.Context.CompanyId,
                        cancellationToken);
                if (identity is null) return Outcome(PartyMasterMutationOutcome.ChildNotFound);
                if (identity.Version != write.ExpectedVersion) return Outcome(PartyMasterMutationOutcome.StaleVersion);
                if (identity.State == write.TargetState) return Outcome(PartyMasterMutationOutcome.StateConflict);

                identity.State = write.TargetState;
                identity.Version = checked(identity.Version + 1);

                return Success(
                    identity.PublicId,
                    identity.State == PartyTaxIdentityState.Active ? "ACTIVE" : "INACTIVE",
                    identity.Version);
            },
            "parties.tax_identity.state.completed",
            cancellationToken);

    public Task<PartyMasterMutationPersistenceResult> CreateExternalMappingAsync(
        CreatePartyExternalMappingWrite write,
        CancellationToken cancellationToken) =>
        ExecuteMutationAsync(
            write.Context,
            async () =>
            {
                var party = await FindPartyAsync(
                    write.ExternalMapping.PartyPublicId,
                    write.Context.CompanyId,
                    cancellationToken);
                if (party is null) return Outcome(PartyMasterMutationOutcome.PartyNotFound);
                if (party.State == PartyState.Merged) return Outcome(PartyMasterMutationOutcome.StateConflict);

                dbContext.Add(new PartyExternalMappingRecord
                {
                    PublicId = write.ExternalMapping.PublicId,
                    PartyId = party.Id,
                    CompanyId = write.Context.CompanyId,
                    SystemCode = write.ExternalMapping.SystemCode,
                    AccountScope = write.ExternalMapping.AccountScope,
                    ExternalIdentity = write.ExternalMapping.ExternalIdentity,
                    State = write.ExternalMapping.State,
                    Version = write.ExternalMapping.Version,
                    CreatedAt = write.ExternalMapping.CreatedAt
                });

                return Success(
                    write.ExternalMapping.PublicId,
                    "ACTIVE",
                    write.ExternalMapping.Version);
            },
            "parties.external_mapping.create.completed",
            cancellationToken);

    public Task<PartyMasterMutationPersistenceResult> ChangeExternalMappingStateAsync(
        ChangePartyExternalMappingStateWrite write,
        CancellationToken cancellationToken) =>
        ExecuteMutationAsync(
            write.Context,
            async () =>
            {
                var party = await FindPartyAsync(
                    write.PartyPublicId,
                    write.Context.CompanyId,
                    cancellationToken);
                if (party is null) return Outcome(PartyMasterMutationOutcome.PartyNotFound);
                if (party.State == PartyState.Merged) return Outcome(PartyMasterMutationOutcome.StateConflict);

                var mapping = await dbContext.Set<PartyExternalMappingRecord>()
                    .SingleOrDefaultAsync(
                        x => x.PublicId == write.ExternalMappingPublicId &&
                             x.PartyId == party.Id &&
                             x.CompanyId == write.Context.CompanyId,
                        cancellationToken);
                if (mapping is null) return Outcome(PartyMasterMutationOutcome.ChildNotFound);
                if (mapping.Version != write.ExpectedVersion) return Outcome(PartyMasterMutationOutcome.StaleVersion);
                if (mapping.State == write.TargetState) return Outcome(PartyMasterMutationOutcome.StateConflict);

                mapping.State = write.TargetState;
                mapping.Version = checked(mapping.Version + 1);

                return Success(mapping.PublicId, ToState(mapping.State), mapping.Version);
            },
            "parties.external_mapping.state.completed",
            cancellationToken);

    public Task<PartyMasterMutationPersistenceResult> MergeAsync(
        MergePartyWrite write,
        CancellationToken cancellationToken) =>
        ExecuteMutationAsync(
            write.Context,
            async () =>
            {
                var parties = await dbContext.Set<PartyRecord>()
                    .Where(x => x.CompanyId == write.Context.CompanyId &&
                                (x.PublicId == write.SourcePartyPublicId ||
                                 x.PublicId == write.SurvivorPartyPublicId))
                    .ToArrayAsync(cancellationToken);

                var source = parties.SingleOrDefault(x => x.PublicId == write.SourcePartyPublicId);
                var survivor = parties.SingleOrDefault(x => x.PublicId == write.SurvivorPartyPublicId);
                if (source is null || survivor is null) return Outcome(PartyMasterMutationOutcome.PartyNotFound);
                if (source.Id == survivor.Id) return Outcome(PartyMasterMutationOutcome.InvalidMerge);
                if (source.State == PartyState.Merged || survivor.State == PartyState.Merged)
                {
                    return Outcome(PartyMasterMutationOutcome.InvalidMerge);
                }

                if (source.Version != write.SourceExpectedVersion ||
                    survivor.Version != write.SurvivorExpectedVersion)
                {
                    return Outcome(PartyMasterMutationOutcome.StaleVersion);
                }

                if (source.Kind != survivor.Kind)
                {
                    return Outcome(PartyMasterMutationOutcome.DeterministicConflict);
                }

                var priorLineage = await dbContext.Set<PartyMergeLineageRecord>()
                    .AsNoTracking()
                    .AnyAsync(x => x.SourcePartyId == source.Id, cancellationToken);
                if (priorLineage) return Outcome(PartyMasterMutationOutcome.InvalidMerge);

                if (write.MoveSourceRoles)
                {
                    var sourceRoles = await dbContext.Set<PartyRoleRecord>()
                        .Where(x => x.PartyId == source.Id)
                        .ToArrayAsync(cancellationToken);
                    var survivorRoles = await dbContext.Set<PartyRoleRecord>()
                        .Where(x => x.PartyId == survivor.Id)
                        .ToArrayAsync(cancellationToken);

                    foreach (var role in sourceRoles)
                    {
                        var existing = survivorRoles.SingleOrDefault(x => x.RoleType == role.RoleType);
                        if (existing is not null)
                        {
                            if (existing.State != role.State)
                            {
                                return Outcome(PartyMasterMutationOutcome.DeterministicConflict);
                            }

                            continue;
                        }

                        role.PartyId = survivor.Id;
                    }
                }

                if (write.MoveSourceAddresses)
                {
                    var sourceAddresses = await dbContext.Set<PartyAddressRecord>()
                        .Where(x => x.PartyId == source.Id && x.CompanyId == write.Context.CompanyId)
                        .ToArrayAsync(cancellationToken);
                    var survivorDefaults = await dbContext.Set<PartyAddressRecord>()
                        .AsNoTracking()
                        .Where(x => x.PartyId == survivor.Id &&
                                    x.CompanyId == write.Context.CompanyId &&
                                    x.State == PartyMasterRecordState.Active &&
                                    x.IsDefault)
                        .Select(x => x.Purpose)
                        .ToArrayAsync(cancellationToken);

                    if (sourceAddresses.Any(x =>
                            x.State == PartyMasterRecordState.Active &&
                            x.IsDefault &&
                            survivorDefaults.Contains(x.Purpose)))
                    {
                        return Outcome(PartyMasterMutationOutcome.DeterministicConflict);
                    }

                    foreach (var address in sourceAddresses) address.PartyId = survivor.Id;
                }

                if (write.MoveSourceTaxIdentities)
                {
                    var sourceTax = await dbContext.Set<PartyTaxIdentityRecord>()
                        .Where(x => x.PartyId == source.Id && x.CompanyId == write.Context.CompanyId)
                        .ToArrayAsync(cancellationToken);
                    var survivorTax = await dbContext.Set<PartyTaxIdentityRecord>()
                        .AsNoTracking()
                        .Where(x => x.PartyId == survivor.Id && x.CompanyId == write.Context.CompanyId)
                        .ToArrayAsync(cancellationToken);

                    if (sourceTax.Any(sourceIdentity =>
                            survivorTax.Any(survivorIdentity =>
                                survivorIdentity.Jurisdiction == sourceIdentity.Jurisdiction &&
                                survivorIdentity.Scheme == sourceIdentity.Scheme &&
                                survivorIdentity.Value == sourceIdentity.Value)))
                    {
                        return Outcome(PartyMasterMutationOutcome.DeterministicConflict);
                    }

                    foreach (var identity in sourceTax) identity.PartyId = survivor.Id;
                }

                if (write.MoveSourceExternalMappings)
                {
                    var sourceMappings = await dbContext.Set<PartyExternalMappingRecord>()
                        .Where(x => x.PartyId == source.Id && x.CompanyId == write.Context.CompanyId)
                        .ToArrayAsync(cancellationToken);
                    var survivorMappings = await dbContext.Set<PartyExternalMappingRecord>()
                        .AsNoTracking()
                        .Where(x => x.PartyId == survivor.Id && x.CompanyId == write.Context.CompanyId)
                        .ToArrayAsync(cancellationToken);

                    if (sourceMappings.Any(sourceMapping =>
                            survivorMappings.Any(survivorMapping =>
                                survivorMapping.SystemCode == sourceMapping.SystemCode &&
                                survivorMapping.AccountScope == sourceMapping.AccountScope &&
                                survivorMapping.ExternalIdentity == sourceMapping.ExternalIdentity)))
                    {
                        return Outcome(PartyMasterMutationOutcome.DeterministicConflict);
                    }

                    foreach (var mapping in sourceMappings) mapping.PartyId = survivor.Id;
                }

                if (write.MoveSourceContacts)
                {
                    var sourceContacts = await dbContext.Set<PartyContactRecord>()
                        .Where(x => x.PartyId == source.Id && x.CompanyId == write.Context.CompanyId)
                        .ToArrayAsync(cancellationToken);
                    foreach (var contact in sourceContacts) contact.PartyId = survivor.Id;
                }

                if (write.UseSourceIdentity)
                {
                    survivor.LegalName = source.LegalName;
                    survivor.DisplayName = source.DisplayName;
                }

                source.State = PartyState.Merged;
                source.Version = checked(source.Version + 1);
                survivor.Version = checked(survivor.Version + 1);

                dbContext.Add(new PartyMergeLineageRecord
                {
                    PublicId = write.Lineage.PublicId,
                    CompanyId = write.Lineage.CompanyId,
                    SourcePartyId = source.Id,
                    SurvivorPartyId = survivor.Id,
                    ActorId = write.Lineage.ActorId,
                    Reason = write.Lineage.Reason,
                    CreatedAt = write.Lineage.CreatedAt
                });

                return new PartyMasterMutationPersistenceResult(
                    PartyMasterMutationOutcome.Succeeded,
                    source.PublicId,
                    "MERGED",
                    source.Version,
                    survivor.PublicId,
                    survivor.Version);
            },
            "parties.merge.completed",
            cancellationToken);

    private async Task<PartyMasterMutationPersistenceResult> ExecuteMutationAsync(
        PartyMasterWriteContext context,
        Func<Task<PartyMasterMutationPersistenceResult>> mutation,
        string resultCode,
        CancellationToken cancellationToken)
    {
        await using var transaction =
            await dbContext.Database.BeginTransactionAsync(cancellationToken);

        try
        {
            var result = await mutation();
            if (result.Outcome != PartyMasterMutationOutcome.Succeeded)
            {
                await transaction.RollbackAsync(cancellationToken);
                dbContext.ChangeTracker.Clear();
                return result;
            }

            idempotencyStore.Add(context.Idempotency);
            auditWriter.Append(context.Audit);

            await dbContext.SaveChangesAsync(cancellationToken);

            var markedSucceeded = await idempotencyStore.MarkSucceededAsync(
                context.Idempotency.Scope,
                context.Idempotency.OperationKey,
                resultCode,
                context.Audit.OccurredAt,
                cancellationToken);

            if (!markedSucceeded)
            {
                throw new InvalidOperationException(
                    "Party master idempotency state could not be completed.");
            }

            await transaction.CommitAsync(cancellationToken);
            return result;
        }
        catch (DbUpdateConcurrencyException)
        {
            await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return Outcome(PartyMasterMutationOutcome.StaleVersion);
        }
        catch (DbUpdateException exception) when (IsConstraint(exception, "ux_idempotency_scope_key"))
        {
            await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return Outcome(PartyMasterMutationOutcome.DuplicateOperation);
        }
        catch (DbUpdateException exception) when (IsUniqueViolation(exception))
        {
            await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return Outcome(PartyMasterMutationOutcome.DeterministicConflict);
        }
    }

    private Task<PartyRecord?> FindPartyAsync(
        Guid partyPublicId,
        Guid companyId,
        CancellationToken cancellationToken) =>
        dbContext.Set<PartyRecord>()
            .SingleOrDefaultAsync(
                x => x.PublicId == partyPublicId && x.CompanyId == companyId,
                cancellationToken);

    private async Task ClearAddressDefaultAsync(
        long partyId,
        PartyAddressPurpose purpose,
        long? exceptId,
        CancellationToken cancellationToken)
    {
        var defaults = await dbContext.Set<PartyAddressRecord>()
            .Where(x =>
                x.PartyId == partyId &&
                x.Purpose == purpose &&
                x.State == PartyMasterRecordState.Active &&
                x.IsDefault &&
                (!exceptId.HasValue || x.Id != exceptId.Value))
            .ToArrayAsync(cancellationToken);

        foreach (var address in defaults)
        {
            address.IsDefault = false;
            address.Version = checked(address.Version + 1);
        }
    }

    private static PartyMasterMutationPersistenceResult Success(
        Guid publicId,
        string state,
        long version) =>
        new(PartyMasterMutationOutcome.Succeeded, publicId, state, version);

    private static PartyMasterMutationPersistenceResult Outcome(
        PartyMasterMutationOutcome outcome) =>
        new(outcome);

    private static string ToState(PartyMasterRecordState state) =>
        state == PartyMasterRecordState.Active ? "ACTIVE" : "INACTIVE";

    private static string PartyStateCode(PartyState state) =>
        state == PartyState.Active ? "ACTIVE" :
            state == PartyState.Inactive ? "INACTIVE" : "MERGED";

    private static bool IsConstraint(DbUpdateException exception, string constraintName) =>
        exception.InnerException is PostgresException postgres &&
        postgres.SqlState == PostgresErrorCodes.UniqueViolation &&
        string.Equals(postgres.ConstraintName, constraintName, StringComparison.Ordinal);

    private static bool IsUniqueViolation(DbUpdateException exception) =>
        exception.InnerException is PostgresException postgres &&
        postgres.SqlState == PostgresErrorCodes.UniqueViolation;
}
