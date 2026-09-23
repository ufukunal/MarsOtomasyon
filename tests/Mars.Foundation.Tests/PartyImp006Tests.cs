using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Application.Parties;
using Mars.Application.Parties.PartyMaster;
using Mars.Domain.Parties;
using Mars.Infrastructure.Persistence;
using Microsoft.EntityFrameworkCore;
using MarsExecutionContext = Mars.Application.Foundation.Context.ExecutionContext;

internal static class PartyImp006Tests
{
    public static IReadOnlyList<(string Name, Action Test)> Cases { get; } =
        new (string Name, Action Test)[]
        {
            ("Party master read requires party.read", ReadRequiresPermission),
            ("Party detail masks tax identity without read_full", DetailMasksTaxIdentity),
            ("Party identity edit creates scoped audited idempotent write", EditIdentityCreatesScopedWrite),
            ("Party merge validates explicit source survivor reason and choices", MergeValidatesAndCreatesWrite),
            ("Party master domain records start ACTIVE with version one", DomainRecordsStartActive),
            ("Party master EF model contains normalized child and merge tables", ModelContainsPartyMasterTables)
        };

    private static void ReadRequiresPermission()
    {
        var permissions = new FakePermissionEvaluator();
        var persistence = new FakeReadPersistence();
        var handler = new PartyMasterQueryHandler(permissions, persistence);

        var result = handler.ListAsync(null, NewContext(), CancellationToken.None)
            .GetAwaiter().GetResult();

        AssertTrue(result.IsFailure);
        AssertEqual(ErrorCategory.Authorization, result.Error!.Category);
        AssertEqual(PartyPermissions.Read, permissions.LastPermissionCode);
        AssertEqual(0, persistence.ListCalls);
    }

    private static void DetailMasksTaxIdentity()
    {
        var permissions = new FakePermissionEvaluator(
            PartyPermissions.Read,
            PartyPermissions.TaxIdentityRead);
        var partyId = Guid.Parse("11111111-1111-1111-1111-111111111111");
        var persistence = new FakeReadPersistence
        {
            Detail = new PartyDetailView(
                partyId,
                "P-600",
                "ORGANIZATION",
                "Mars Party",
                null,
                "ACTIVE",
                3,
                null,
                Array.Empty<PartyRoleView>(),
                Array.Empty<PartyContactView>(),
                Array.Empty<PartyAddressView>(),
                new[]
                {
                    new PartyTaxIdentityView(
                        Guid.NewGuid(),
                        "TR",
                        "VKN",
                        "1234567890",
                        false,
                        "ACTIVE",
                        1)
                },
                Array.Empty<PartyExternalMappingView>())
        };

        var result = new PartyMasterQueryHandler(permissions, persistence)
            .GetAsync(partyId, NewContext(), CancellationToken.None)
            .GetAwaiter().GetResult();

        AssertTrue(result.IsSuccess);
        AssertEqual("******7890", result.Value!.TaxIdentities[0].Value);
        AssertTrue(result.Value.TaxIdentities[0].IsMasked);
        AssertTrue(persistence.LastOptions!.IncludeTaxIdentities);
        AssertTrue(!persistence.LastOptions.IncludeFullTaxIdentityValues);
    }

    private static void EditIdentityCreatesScopedWrite()
    {
        var permissions = new FakePermissionEvaluator(PartyPermissions.Edit);
        var persistence = new FakeMutationPersistence
        {
            Result = new(
                PartyMasterMutationOutcome.Succeeded,
                Guid.Parse("22222222-2222-2222-2222-222222222222"),
                "ACTIVE",
                8)
        };
        var context = NewContext();

        var result = new PartyMasterCommandHandler(permissions, persistence)
            .EditIdentityAsync(
                new EditPartyIdentityCommand(
                    Guid.Parse("22222222-2222-2222-2222-222222222222"),
                    7,
                    "Mars Legal",
                    "Mars Display",
                    "party-edit-op"),
                context,
                CancellationToken.None)
            .GetAwaiter().GetResult();

        AssertTrue(result.IsSuccess);
        AssertEqual(8L, result.Value!.Version);
        AssertTrue(persistence.EditWrite is not null);
        AssertEqual(context.CompanyId, persistence.EditWrite!.Context.CompanyId);
        AssertEqual(
            "parties.edit:" + context.CompanyId.ToString("D"),
            persistence.EditWrite.Context.Idempotency.Scope);
        AssertEqual("PartyIdentityChanged", persistence.EditWrite.Context.Audit.Action);
        AssertEqual("Mars Legal", persistence.EditWrite.LegalName);
        AssertEqual("Mars Display", persistence.EditWrite.DisplayName);
    }

    private static void MergeValidatesAndCreatesWrite()
    {
        var permissions = new FakePermissionEvaluator(PartyPermissions.Merge);
        var persistence = new FakeMutationPersistence
        {
            Result = new(
                PartyMasterMutationOutcome.Succeeded,
                Guid.Parse("33333333-3333-3333-3333-333333333333"),
                "MERGED",
                5,
                Guid.Parse("44444444-4444-4444-4444-444444444444"),
                9)
        };
        var handler = new PartyMasterCommandHandler(permissions, persistence);
        var context = NewContext();

        var invalid = handler.MergeAsync(
                new MergePartyCommand(
                    Guid.Parse("33333333-3333-3333-3333-333333333333"),
                    Guid.Parse("33333333-3333-3333-3333-333333333333"),
                    4,
                    8,
                    false,
                    true,
                    true,
                    true,
                    true,
                    true,
                    "Duplicate",
                    "merge-invalid"),
                context,
                CancellationToken.None)
            .GetAwaiter().GetResult();
        AssertEqual("parties.merge.same_party", invalid.Error!.Code);

        var result = handler.MergeAsync(
                new MergePartyCommand(
                    Guid.Parse("33333333-3333-3333-3333-333333333333"),
                    Guid.Parse("44444444-4444-4444-4444-444444444444"),
                    4,
                    8,
                    true,
                    true,
                    true,
                    false,
                    true,
                    true,
                    "Confirmed duplicate",
                    "merge-op"),
                context,
                CancellationToken.None)
            .GetAwaiter().GetResult();

        AssertTrue(result.IsSuccess);
        AssertEqual("MERGED", result.Value!.SourceState);
        AssertTrue(persistence.MergeWrite is not null);
        AssertTrue(persistence.MergeWrite!.UseSourceIdentity);
        AssertTrue(persistence.MergeWrite.MoveSourceRoles);
        AssertTrue(persistence.MergeWrite.MoveSourceContacts);
        AssertTrue(!persistence.MergeWrite.MoveSourceAddresses);
        AssertTrue(persistence.MergeWrite.MoveSourceTaxIdentities);
        AssertTrue(persistence.MergeWrite.MoveSourceExternalMappings);
        AssertEqual("Confirmed duplicate", persistence.MergeWrite.Lineage.Reason);
        AssertEqual("PartyMerged", persistence.MergeWrite.Context.Audit.Action);
    }

    private static void DomainRecordsStartActive()
    {
        var now = DateTimeOffset.Parse("2026-09-24T00:00:00+00:00");
        var partyId = Guid.NewGuid();
        var companyId = Guid.NewGuid();

        var contact = PartyContact.Create(
            Guid.NewGuid(), partyId, companyId, "Ada", null, "Billing", now);
        var address = PartyAddress.Create(
            Guid.NewGuid(), partyId, companyId, PartyAddressPurpose.Billing,
            "TR", "Istanbul", null, null, null, null, "Merkez", true, now);
        var mapping = PartyExternalMapping.Create(
            Guid.NewGuid(), partyId, companyId, "ERP-X", null, "EXT-1", now);

        AssertEqual(PartyMasterRecordState.Active, contact.State);
        AssertEqual(1L, contact.Version);
        AssertEqual(PartyMasterRecordState.Active, address.State);
        AssertTrue(address.IsDefault);
        AssertEqual(PartyMasterRecordState.Active, mapping.State);
        AssertEqual(string.Empty, mapping.AccountScope);
    }

    private static void ModelContainsPartyMasterTables()
    {
        using var context = CreateModelContext();
        var tables = context.Model.GetEntityTypes()
            .Select(x => x.GetSchema() + "." + x.GetTableName())
            .ToHashSet(StringComparer.Ordinal);

        foreach (var table in new[]
                 {
                     "parties.contacts",
                     "parties.communication_points",
                     "parties.addresses",
                     "parties.external_mappings",
                     "parties.merge_lineage"
                 })
        {
            AssertTrue(tables.Contains(table));
        }

        var mapping = context.Model.GetEntityTypes()
            .Single(x => x.GetTableName() == "external_mappings");
        AssertTrue(mapping.GetIndexes().Any(x =>
            x.IsUnique &&
            x.GetDatabaseName() == "ux_party_external_mappings_active_scope"));

        var lineage = context.Model.GetEntityTypes()
            .Single(x => x.GetTableName() == "merge_lineage");
        AssertTrue(lineage.GetIndexes().Any(x =>
            x.IsUnique &&
            x.GetDatabaseName() == "ux_party_merge_lineage_source"));
    }

    private static MarsDbContext CreateModelContext()
    {
        var options = MarsDbContextOptions.CreateRuntime(
            new Mars.Application.Foundation.Configuration.PostgreSqlRuntimeOptions(
                "Host=localhost;Database=mars_party_imp_006_model_probe"));
        return new MarsDbContext(options);
    }

    private static IExecutionContext NewContext() =>
        new MarsExecutionContext(
            Guid.Parse("aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"),
            Guid.Parse("bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb"),
            null,
            new CorrelationId("corr-party-imp-006"));

    private static void AssertTrue(bool condition)
    {
        if (!condition) throw new InvalidOperationException("Assertion failed.");
    }

    private static void AssertEqual<T>(T expected, T actual)
    {
        if (!EqualityComparer<T>.Default.Equals(expected, actual))
        {
            throw new InvalidOperationException(
                "Expected '" + expected + "', actual '" + actual + "'.");
        }
    }

    private sealed class FakePermissionEvaluator(params string[] permissions) : IPermissionEvaluator
    {
        private readonly HashSet<string> granted = new(permissions, StringComparer.Ordinal);
        public string? LastPermissionCode { get; private set; }

        public Task<bool> IsGrantedAsync(
            Guid actorId,
            Guid companyId,
            string permissionCode,
            CancellationToken cancellationToken)
        {
            cancellationToken.ThrowIfCancellationRequested();
            LastPermissionCode = permissionCode;
            return Task.FromResult(granted.Contains(permissionCode));
        }
    }

    private sealed class FakeReadPersistence : IPartyMasterReadPersistence
    {
        public int ListCalls { get; private set; }
        public PartyDetailView? Detail { get; init; }
        public PartyDetailReadOptions? LastOptions { get; private set; }

        public Task<IReadOnlyList<PartyListItem>> ListAsync(
            Guid companyId,
            string? search,
            CancellationToken cancellationToken)
        {
            cancellationToken.ThrowIfCancellationRequested();
            ListCalls++;
            return Task.FromResult<IReadOnlyList<PartyListItem>>(Array.Empty<PartyListItem>());
        }

        public Task<PartyDetailView?> GetAsync(
            Guid companyId,
            Guid partyPublicId,
            PartyDetailReadOptions options,
            CancellationToken cancellationToken)
        {
            cancellationToken.ThrowIfCancellationRequested();
            LastOptions = options;
            return Task.FromResult(Detail);
        }
    }

    private sealed class FakeMutationPersistence : IPartyMasterMutationPersistence
    {
        public PartyMasterMutationPersistenceResult Result { get; init; } =
            new(PartyMasterMutationOutcome.Succeeded, Guid.NewGuid(), "ACTIVE", 1);

        public EditPartyIdentityWrite? EditWrite { get; private set; }
        public MergePartyWrite? MergeWrite { get; private set; }

        public Task<PartyMasterMutationPersistenceResult> EditIdentityAsync(
            EditPartyIdentityWrite write,
            CancellationToken cancellationToken)
        {
            cancellationToken.ThrowIfCancellationRequested();
            EditWrite = write;
            return Task.FromResult(Result);
        }

        public Task<PartyMasterMutationPersistenceResult> MergeAsync(
            MergePartyWrite write,
            CancellationToken cancellationToken)
        {
            cancellationToken.ThrowIfCancellationRequested();
            MergeWrite = write;
            return Task.FromResult(Result);
        }

        public Task<PartyMasterMutationPersistenceResult> CreateContactAsync(
            CreatePartyContactWrite write, CancellationToken cancellationToken) =>
            Task.FromResult(Result);

        public Task<PartyMasterMutationPersistenceResult> ChangeContactStateAsync(
            ChangePartyContactStateWrite write, CancellationToken cancellationToken) =>
            Task.FromResult(Result);

        public Task<PartyMasterMutationPersistenceResult> CreateCommunicationPointAsync(
            CreatePartyCommunicationPointWrite write, CancellationToken cancellationToken) =>
            Task.FromResult(Result);

        public Task<PartyMasterMutationPersistenceResult> ChangeCommunicationPointStateAsync(
            ChangePartyCommunicationPointStateWrite write, CancellationToken cancellationToken) =>
            Task.FromResult(Result);

        public Task<PartyMasterMutationPersistenceResult> CreateAddressAsync(
            CreatePartyAddressWrite write, CancellationToken cancellationToken) =>
            Task.FromResult(Result);

        public Task<PartyMasterMutationPersistenceResult> UpdateAddressAsync(
            UpdatePartyAddressWrite write, CancellationToken cancellationToken) =>
            Task.FromResult(Result);

        public Task<PartyMasterMutationPersistenceResult> ChangeAddressStateAsync(
            ChangePartyAddressStateWrite write, CancellationToken cancellationToken) =>
            Task.FromResult(Result);

        public Task<PartyMasterMutationPersistenceResult> ChangeTaxIdentityStateAsync(
            ChangePartyTaxIdentityStateWrite write, CancellationToken cancellationToken) =>
            Task.FromResult(Result);

        public Task<PartyMasterMutationPersistenceResult> CreateExternalMappingAsync(
            CreatePartyExternalMappingWrite write, CancellationToken cancellationToken) =>
            Task.FromResult(Result);

        public Task<PartyMasterMutationPersistenceResult> ChangeExternalMappingStateAsync(
            ChangePartyExternalMappingStateWrite write, CancellationToken cancellationToken) =>
            Task.FromResult(Result);
    }
}
