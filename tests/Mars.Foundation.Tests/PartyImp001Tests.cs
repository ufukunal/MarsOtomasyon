using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Application.Parties;
using Mars.Application.Parties.CreateParty;
using Mars.Domain.Parties;
using Mars.Infrastructure.Persistence;
using MarsExecutionContext = Mars.Application.Foundation.Context.ExecutionContext;

internal static class PartyImp001Tests
{
    public static IReadOnlyList<(string Name, Action Test)> Cases { get; } =
        new (string Name, Action Test)[]
        {
            ("Party core identity enforces first-slice invariants", PartyCoreIdentityEnforcesInvariants),
            ("Create Party denies missing party.create before persistence", CreatePartyDeniesMissingPermission),
            ("Create Party produces trusted company audit and idempotency write", CreatePartyProducesScopedWrite),
            ("Create Party maps duplicate operation and Party Code to conflict", CreatePartyMapsDuplicates),
            ("PARTY-IMP-001 model contains permission grant and Party constraints", ModelContainsPermissionAndPartyContracts)
        };

    private static void PartyCoreIdentityEnforcesInvariants()
    {
        var companyId = Guid.NewGuid();
        var party = Party.Create(
            Guid.NewGuid(),
            companyId,
            "P-100",
            PartyKind.Organization,
            "Mars Test Organization",
            "Mars Test",
            DateTimeOffset.Parse("2026-09-23T12:00:00+00:00"));

        AssertEqual(companyId, party.CompanyId);
        AssertEqual("P-100", party.PartyCode);
        AssertEqual(PartyKind.Organization, party.Kind);
        AssertEqual(PartyState.Active, party.State);
        AssertEqual(1L, party.Version);

        AssertThrows<ArgumentException>(() => Party.Create(
            Guid.NewGuid(), companyId, " P-100", PartyKind.Person, "Name", null, DateTimeOffset.UtcNow));
        AssertThrows<ArgumentException>(() => Party.Create(
            Guid.NewGuid(), companyId, "P-100", PartyKind.Person, " Name", null, DateTimeOffset.UtcNow));
    }

    private static void CreatePartyDeniesMissingPermission()
    {
        var permissions = new FakePermissionEvaluator(false);
        var persistence = new FakePartyCreatePersistence(CreatePartyPersistenceOutcome.Created);
        var handler = new CreatePartyHandler(permissions, persistence);

        var result = handler.ExecuteAsync(
                new CreatePartyCommand("P-101", "PERSON", "Ada Example", null, "op-101"),
                NewContext(),
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(result.IsFailure);
        AssertEqual(ErrorCategory.Authorization, result.Error!.Category);
        AssertEqual("authorization.permission_denied", result.Error.Code);
        AssertTrue(persistence.Write is null);
        AssertEqual(PartyPermissions.Create, permissions.LastPermissionCode);
    }

    private static void CreatePartyProducesScopedWrite()
    {
        var context = NewContext();
        var persistence = new FakePartyCreatePersistence(CreatePartyPersistenceOutcome.Created);
        var handler = new CreatePartyHandler(new FakePermissionEvaluator(true), persistence);

        var result = handler.ExecuteAsync(
                new CreatePartyCommand(
                    "P-102",
                    "ORGANIZATION",
                    "Example Organization",
                    "Example",
                    "op-102"),
                context,
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(result.IsSuccess);
        AssertTrue(persistence.Write is not null);
        var write = persistence.Write!;

        AssertEqual(context.CompanyId, write.Party.CompanyId);
        AssertEqual("P-102", write.Party.PartyCode);
        AssertEqual($"parties.create:{context.CompanyId:D}", write.Idempotency.Scope);
        AssertEqual("op-102", write.Idempotency.OperationKey);
        AssertEqual(context.ActorId, write.Audit.ActorId);
        AssertEqual(context.CompanyId, write.Audit.CompanyId);
        AssertEqual("Parties", write.Audit.Module);
        AssertEqual("PartyCreated", write.Audit.Action);
        AssertEqual("Party", write.Audit.EntityType);
        AssertEqual(write.Party.PublicId, write.Audit.EntityPublicId);
        AssertEqual(write.Party.PublicId, result.Value!.PublicId);
        AssertEqual("ACTIVE", result.Value.State);
        AssertEqual("ORGANIZATION", result.Value.Kind);
    }

    private static void CreatePartyMapsDuplicates()
    {
        var context = NewContext();

        var duplicateOperation = new CreatePartyHandler(
            new FakePermissionEvaluator(true),
            new FakePartyCreatePersistence(CreatePartyPersistenceOutcome.DuplicateOperation))
            .ExecuteAsync(
                new CreatePartyCommand("P-103", "PERSON", "Person One", null, "op-dup"),
                context,
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(duplicateOperation.IsFailure);
        AssertEqual(ErrorCategory.Conflict, duplicateOperation.Error!.Category);
        AssertEqual("parties.create.duplicate_operation", duplicateOperation.Error.Code);

        var duplicateCode = new CreatePartyHandler(
            new FakePermissionEvaluator(true),
            new FakePartyCreatePersistence(CreatePartyPersistenceOutcome.DuplicatePartyCode))
            .ExecuteAsync(
                new CreatePartyCommand("P-103", "PERSON", "Person One", null, "op-code"),
                context,
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(duplicateCode.IsFailure);
        AssertEqual(ErrorCategory.Conflict, duplicateCode.Error!.Category);
        AssertEqual("parties.party_code_conflict", duplicateCode.Error.Code);
    }

    private static void ModelContainsPermissionAndPartyContracts()
    {
        using var context = CreateModelContext();

        var permission = context.Model.GetEntityTypes()
            .Single(entity => entity.GetTableName() == "permission_grants");
        AssertEqual("foundation", permission.GetSchema());
        AssertTrue(permission.GetIndexes().Any(index =>
            index.IsUnique &&
            index.GetDatabaseName() == "ux_permission_grant_actor_company_code"));

        var party = context.Model.GetEntityTypes()
            .Single(entity => entity.GetTableName() == "parties");
        AssertEqual("parties", party.GetSchema());
        AssertTrue(party.GetIndexes().Any(index =>
            index.IsUnique &&
            index.GetDatabaseName() == "ux_parties_company_code"));
        AssertTrue(party.GetIndexes().Any(index =>
            index.IsUnique &&
            index.GetDatabaseName() == "ux_parties_public_id"));

        var version = party.FindProperty("Version")
            ?? throw new InvalidOperationException("Party version property was not found.");
        AssertTrue(version.IsConcurrencyToken);
        AssertEqual(0, party.GetForeignKeys().Count());
    }

    private static IExecutionContext NewContext() =>
        new MarsExecutionContext(
            Guid.Parse("aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"),
            Guid.Parse("bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb"),
            null,
            new CorrelationId("corr-party-imp-001"));

    private static MarsDbContext CreateModelContext()
    {
        var options = MarsDbContextOptions.CreateRuntime(
            new PostgreSqlRuntimeOptions("Host=localhost;Database=mars_party_model_probe"));
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

    private sealed class FakePartyCreatePersistence(
        CreatePartyPersistenceOutcome outcome) : IPartyCreatePersistence
    {
        public CreatePartyWrite? Write { get; private set; }

        public Task<CreatePartyPersistenceOutcome> PersistAsync(
            CreatePartyWrite write,
            CancellationToken cancellationToken)
        {
            cancellationToken.ThrowIfCancellationRequested();
            Write = write;
            return Task.FromResult(outcome);
        }
    }
}
