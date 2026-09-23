using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Application.Parties;
using Mars.Application.Parties.AddPartyTaxIdentity;
using Mars.Domain.Parties;
using Mars.Infrastructure.Persistence;
using Microsoft.EntityFrameworkCore;
using MarsExecutionContext = Mars.Application.Foundation.Context.ExecutionContext;

internal static class PartyImp003Tests
{
    public static IReadOnlyList<(string Name, Action Test)> Cases { get; } =
        new (string Name, Action Test)[]
        {
            ("Turkish Tax Identity enforces frozen VKN TCKN structural invariants", TaxIdentityEnforcesStructuralInvariants),
            ("Add Tax Identity denies missing party.tax_identity.manage before persistence", AddTaxIdentityDeniesMissingPermission),
            ("Add Tax Identity produces trusted company audit and idempotency without raw value", AddTaxIdentityProducesScopedWriteWithoutRawAuditValue),
            ("Add Tax Identity maps not-found and duplicate outcomes", AddTaxIdentityMapsPersistenceOutcomes),
            ("PARTY-IMP-003 model contains Tax Identity company collision and concurrency contracts", ModelContainsTaxIdentityContracts)
        };

    private static void TaxIdentityEnforcesStructuralInvariants()
    {
        var partyId = Guid.NewGuid();
        var companyId = Guid.NewGuid();
        var vkn = PartyTaxIdentity.Create(
            Guid.NewGuid(),
            partyId,
            companyId,
            "TR",
            PartyTaxIdentityScheme.Vkn,
            "1234567890",
            DateTimeOffset.Parse("2026-09-23T17:00:00+00:00"));

        AssertEqual("TR", vkn.Jurisdiction);
        AssertEqual(PartyTaxIdentityScheme.Vkn, vkn.Scheme);
        AssertEqual("1234567890", vkn.Value);
        AssertEqual(PartyTaxIdentityState.Active, vkn.State);
        AssertEqual(1L, vkn.Version);

        _ = PartyTaxIdentity.Create(
            Guid.NewGuid(),
            partyId,
            companyId,
            "TR",
            PartyTaxIdentityScheme.Tckn,
            "12345678901",
            DateTimeOffset.UtcNow);

        AssertThrows<ArgumentException>(() => PartyTaxIdentity.Create(
            Guid.NewGuid(), partyId, companyId, "DE", PartyTaxIdentityScheme.Vkn, "1234567890", DateTimeOffset.UtcNow));
        AssertThrows<ArgumentException>(() => PartyTaxIdentity.Create(
            Guid.NewGuid(), partyId, companyId, "TR", PartyTaxIdentityScheme.Vkn, "123456789", DateTimeOffset.UtcNow));
        AssertThrows<ArgumentException>(() => PartyTaxIdentity.Create(
            Guid.NewGuid(), partyId, companyId, "TR", PartyTaxIdentityScheme.Tckn, "1234567890A", DateTimeOffset.UtcNow));
    }

    private static void AddTaxIdentityDeniesMissingPermission()
    {
        var permissions = new FakePermissionEvaluator(false);
        var persistence = new FakePersistence(AddPartyTaxIdentityPersistenceOutcome.Added);
        var handler = new AddPartyTaxIdentityHandler(permissions, persistence);

        var result = handler.ExecuteAsync(
                new AddPartyTaxIdentityCommand(Guid.NewGuid(), "TR", "VKN", "1234567890", "tax-op-1"),
                NewContext(),
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(result.IsFailure);
        AssertEqual(ErrorCategory.Authorization, result.Error!.Category);
        AssertEqual(PartyPermissions.TaxIdentityManage, permissions.LastPermissionCode);
        AssertTrue(persistence.Write is null);
    }

    private static void AddTaxIdentityProducesScopedWriteWithoutRawAuditValue()
    {
        const string sensitiveValue = "12345678901";
        var context = NewContext();
        var partyPublicId = Guid.NewGuid();
        var persistence = new FakePersistence(AddPartyTaxIdentityPersistenceOutcome.Added);
        var handler = new AddPartyTaxIdentityHandler(new FakePermissionEvaluator(true), persistence);

        var result = handler.ExecuteAsync(
                new AddPartyTaxIdentityCommand(
                    partyPublicId,
                    "TR",
                    "TCKN",
                    sensitiveValue,
                    "tax-op-2"),
                context,
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(result.IsSuccess);
        AssertTrue(persistence.Write is not null);
        var write = persistence.Write!;

        AssertEqual(context.CompanyId, write.TaxIdentity.CompanyId);
        AssertEqual(partyPublicId, write.TaxIdentity.PartyPublicId);
        AssertEqual($"parties.tax_identity.add:{context.CompanyId:D}", write.Idempotency.Scope);
        AssertEqual("tax-op-2", write.Idempotency.OperationKey);
        AssertEqual(context.ActorId, write.Audit.ActorId);
        AssertEqual(context.CompanyId, write.Audit.CompanyId);
        AssertEqual("PartyTaxIdentityAdded.TCKN", write.Audit.Action);
        AssertEqual("Party", write.Audit.EntityType);
        AssertEqual(partyPublicId, write.Audit.EntityPublicId);
        AssertTrue(!write.Audit.Action.Contains(sensitiveValue, StringComparison.Ordinal));
        AssertTrue(write.Audit.Reason is null || !write.Audit.Reason.Contains(sensitiveValue, StringComparison.Ordinal));
        AssertEqual("TCKN", result.Value!.Scheme);
        AssertEqual("ACTIVE", result.Value.State);
        AssertTrue(typeof(AddPartyTaxIdentityReceipt).GetProperty("Value") is null);
    }

    private static void AddTaxIdentityMapsPersistenceOutcomes()
    {
        var context = NewContext();
        var partyPublicId = Guid.NewGuid();

        var cases = new[]
        {
            (AddPartyTaxIdentityPersistenceOutcome.PartyNotFound, ErrorCategory.NotFound, "parties.party_not_found"),
            (AddPartyTaxIdentityPersistenceOutcome.DuplicateOperation, ErrorCategory.Conflict, "parties.tax_identity.duplicate_operation"),
            (AddPartyTaxIdentityPersistenceOutcome.DuplicateTaxIdentity, ErrorCategory.Conflict, "parties.tax_identity.conflict")
        };

        foreach (var (outcome, category, code) in cases)
        {
            var result = new AddPartyTaxIdentityHandler(
                    new FakePermissionEvaluator(true),
                    new FakePersistence(outcome))
                .ExecuteAsync(
                    new AddPartyTaxIdentityCommand(
                        partyPublicId,
                        "TR",
                        "VKN",
                        "1234567890",
                        Guid.NewGuid().ToString("N")),
                    context,
                    CancellationToken.None)
                .GetAwaiter()
                .GetResult();

            AssertTrue(result.IsFailure);
            AssertEqual(category, result.Error!.Category);
            AssertEqual(code, result.Error.Code);
        }
    }

    private static void ModelContainsTaxIdentityContracts()
    {
        using var context = CreateModelContext();

        var party = context.Model.GetEntityTypes()
            .Single(entity => entity.GetTableName() == "parties");
        AssertTrue(party.GetKeys().Any(key =>
            key.Properties.Select(property => property.Name).SequenceEqual(new[] { "Id", "CompanyId" })));

        var tax = context.Model.GetEntityTypes()
            .Single(entity => entity.GetTableName() == "tax_identities");

        AssertEqual("parties", tax.GetSchema());

        AssertTrue(tax.GetIndexes().Any(index =>
            index.IsUnique &&
            index.GetDatabaseName() == "ux_tax_identities_public_id"));

        var collision = tax.GetIndexes().Single(index =>
            index.GetDatabaseName() == "ux_tax_identities_active_company_identity");
        AssertTrue(collision.IsUnique);
        AssertTrue(!string.IsNullOrWhiteSpace(collision.GetFilter()));

        var foreignKey = tax.GetForeignKeys().Single();
        AssertEqual(DeleteBehavior.Restrict, foreignKey.DeleteBehavior);
        AssertSequenceEqual(
            new[] { "PartyId", "CompanyId" },
            foreignKey.Properties.Select(property => property.Name).ToArray());

        var version = tax.FindProperty("Version")
            ?? throw new InvalidOperationException("Tax Identity version property was not found.");
        AssertTrue(version.IsConcurrencyToken);
    }

    private static IExecutionContext NewContext() =>
        new MarsExecutionContext(
            Guid.Parse("aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"),
            Guid.Parse("bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb"),
            null,
            new CorrelationId("corr-party-imp-003"));

    private static MarsDbContext CreateModelContext()
    {
        var options = MarsDbContextOptions.CreateRuntime(
            new PostgreSqlRuntimeOptions("Host=localhost;Database=mars_party_tax_model_probe"));
        return new MarsDbContext(options);
    }

    private static TException AssertThrows<TException>(Action action)
        where TException : Exception
    {
        try { action(); }
        catch (TException exception) { return exception; }

        throw new InvalidOperationException(
            $"Expected exception {typeof(TException).Name} was not thrown.");
    }

    private static void AssertTrue(bool condition)
    {
        if (!condition) throw new InvalidOperationException("Assertion failed.");
    }

    private static void AssertEqual<T>(T expected, T actual)
    {
        if (!EqualityComparer<T>.Default.Equals(expected, actual))
        {
            throw new InvalidOperationException(
                $"Expected '{expected}', actual '{actual}'.");
        }
    }

    private static void AssertSequenceEqual<T>(IReadOnlyList<T> expected, IReadOnlyList<T> actual)
    {
        if (expected.Count != actual.Count)
        {
            throw new InvalidOperationException($"Expected {expected.Count} items, actual {actual.Count}.");
        }

        for (var index = 0; index < expected.Count; index++)
        {
            if (!EqualityComparer<T>.Default.Equals(expected[index], actual[index]))
            {
                throw new InvalidOperationException(
                    $"Sequence differs at index {index}. Expected '{expected[index]}', actual '{actual[index]}'.");
            }
        }
    }

    private sealed class FakePermissionEvaluator(bool granted) : IPermissionEvaluator
    {
        public string? LastPermissionCode { get; private set; }

        public Task<bool> IsGrantedAsync(
            Guid actorId,
            Guid companyId,
            string permissionCode,
            CancellationToken cancellationToken)
        {
            cancellationToken.ThrowIfCancellationRequested();
            LastPermissionCode = permissionCode;
            return Task.FromResult(granted);
        }
    }

    private sealed class FakePersistence(
        AddPartyTaxIdentityPersistenceOutcome outcome) : IPartyTaxIdentityAddPersistence
    {
        public AddPartyTaxIdentityWrite? Write { get; private set; }

        public Task<AddPartyTaxIdentityPersistenceOutcome> PersistAsync(
            AddPartyTaxIdentityWrite write,
            CancellationToken cancellationToken)
        {
            cancellationToken.ThrowIfCancellationRequested();
            Write = write;
            return Task.FromResult(outcome);
        }
    }
}
