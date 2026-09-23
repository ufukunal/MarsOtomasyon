using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Application.Parties;
using Mars.Application.Parties.ActivatePartyRole;
using Mars.Domain.Parties;
using Mars.Infrastructure.Persistence;
using Microsoft.EntityFrameworkCore;
using MarsExecutionContext = Mars.Application.Foundation.Context.ExecutionContext;

internal static class PartyImp002Tests
{
    public static IReadOnlyList<(string Name, Action Test)> Cases { get; } =
        new (string Name, Action Test)[]
        {
            ("Party Role activation enforces frozen role invariants", PartyRoleEnforcesInvariants),
            ("Activate Party Role denies missing party.role.manage before persistence", ActivateRoleDeniesMissingPermission),
            ("Activate Party Role produces trusted company audit and idempotency write", ActivateRoleProducesScopedWrite),
            ("Activate Party Role maps not-found and duplicate outcomes", ActivateRoleMapsPersistenceOutcomes),
            ("PARTY-IMP-002 model contains Party Role FK uniqueness and concurrency", ModelContainsPartyRoleContracts)
        };

    private static void PartyRoleEnforcesInvariants()
    {
        var partyPublicId = Guid.NewGuid();
        var companyId = Guid.NewGuid();
        var role = PartyRole.Create(
            partyPublicId,
            companyId,
            PartyRoleType.Customer,
            DateTimeOffset.Parse("2026-09-23T16:00:00+00:00"));

        AssertEqual(partyPublicId, role.PartyPublicId);
        AssertEqual(companyId, role.CompanyId);
        AssertEqual(PartyRoleType.Customer, role.RoleType);
        AssertEqual(PartyRoleState.Active, role.State);
        AssertEqual(1L, role.Version);

        AssertThrows<ArgumentException>(() =>
            PartyRole.Create(Guid.Empty, companyId, PartyRoleType.Customer, DateTimeOffset.UtcNow));
        AssertThrows<ArgumentException>(() =>
            PartyRole.Create(partyPublicId, Guid.Empty, PartyRoleType.Supplier, DateTimeOffset.UtcNow));
        AssertThrows<ArgumentOutOfRangeException>(() =>
            PartyRole.Create(partyPublicId, companyId, (PartyRoleType)99, DateTimeOffset.UtcNow));
    }

    private static void ActivateRoleDeniesMissingPermission()
    {
        var permissions = new FakePermissionEvaluator(false);
        var persistence = new FakePartyRoleActivationPersistence(ActivatePartyRolePersistenceOutcome.Activated);
        var handler = new ActivatePartyRoleHandler(permissions, persistence);

        var result = handler.ExecuteAsync(
                new ActivatePartyRoleCommand(Guid.NewGuid(), "CUSTOMER", "role-op-1"),
                NewContext(),
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(result.IsFailure);
        AssertEqual(ErrorCategory.Authorization, result.Error!.Category);
        AssertEqual("authorization.permission_denied", result.Error.Code);
        AssertTrue(persistence.Write is null);
        AssertEqual(PartyPermissions.RoleManage, permissions.LastPermissionCode);
    }

    private static void ActivateRoleProducesScopedWrite()
    {
        var context = NewContext();
        var partyPublicId = Guid.NewGuid();
        var persistence = new FakePartyRoleActivationPersistence(ActivatePartyRolePersistenceOutcome.Activated);
        var handler = new ActivatePartyRoleHandler(new FakePermissionEvaluator(true), persistence);

        var result = handler.ExecuteAsync(
                new ActivatePartyRoleCommand(partyPublicId, "SUPPLIER", "role-op-2"),
                context,
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(result.IsSuccess);
        AssertTrue(persistence.Write is not null);
        var write = persistence.Write!;

        AssertEqual(context.CompanyId, write.Role.CompanyId);
        AssertEqual(partyPublicId, write.Role.PartyPublicId);
        AssertEqual(PartyRoleType.Supplier, write.Role.RoleType);
        AssertEqual(PartyRoleState.Active, write.Role.State);
        AssertEqual($"parties.role.activate:{context.CompanyId:D}", write.Idempotency.Scope);
        AssertEqual("role-op-2", write.Idempotency.OperationKey);
        AssertEqual(context.ActorId, write.Audit.ActorId);
        AssertEqual(context.CompanyId, write.Audit.CompanyId);
        AssertEqual("Parties", write.Audit.Module);
        AssertEqual("PartyRoleActivated.SUPPLIER", write.Audit.Action);
        AssertEqual("Party", write.Audit.EntityType);
        AssertEqual(partyPublicId, write.Audit.EntityPublicId);
        AssertEqual("SUPPLIER", result.Value!.Role);
        AssertEqual("ACTIVE", result.Value.State);
    }

    private static void ActivateRoleMapsPersistenceOutcomes()
    {
        var context = NewContext();
        var partyPublicId = Guid.NewGuid();

        var cases = new[]
        {
            (ActivatePartyRolePersistenceOutcome.PartyNotFound, ErrorCategory.NotFound, "parties.party_not_found"),
            (ActivatePartyRolePersistenceOutcome.DuplicateOperation, ErrorCategory.Conflict, "parties.role.duplicate_operation"),
            (ActivatePartyRolePersistenceOutcome.DuplicateRole, ErrorCategory.Conflict, "parties.role.conflict")
        };

        foreach (var (outcome, category, code) in cases)
        {
            var result = new ActivatePartyRoleHandler(
                    new FakePermissionEvaluator(true),
                    new FakePartyRoleActivationPersistence(outcome))
                .ExecuteAsync(
                    new ActivatePartyRoleCommand(partyPublicId, "CUSTOMER", Guid.NewGuid().ToString("N")),
                    context,
                    CancellationToken.None)
                .GetAwaiter()
                .GetResult();

            AssertTrue(result.IsFailure);
            AssertEqual(category, result.Error!.Category);
            AssertEqual(code, result.Error.Code);
        }

        var invalidRole = new ActivatePartyRoleHandler(
                new FakePermissionEvaluator(true),
                new FakePartyRoleActivationPersistence(ActivatePartyRolePersistenceOutcome.Activated))
            .ExecuteAsync(
                new ActivatePartyRoleCommand(partyPublicId, "ARCHITECT", "role-op-invalid"),
                context,
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(invalidRole.IsFailure);
        AssertEqual(ErrorCategory.Validation, invalidRole.Error!.Category);
        AssertEqual("parties.role.invalid", invalidRole.Error.Code);
    }

    private static void ModelContainsPartyRoleContracts()
    {
        using var context = CreateModelContext();

        var role = context.Model.GetEntityTypes()
            .Single(entity => entity.GetTableName() == "party_roles");

        AssertEqual("parties", role.GetSchema());
        AssertTrue(role.GetIndexes().Any(index =>
            index.IsUnique &&
            index.GetDatabaseName() == "ux_party_roles_party_role_type"));

        var foreignKey = role.GetForeignKeys().Single();
        AssertEqual("parties", foreignKey.PrincipalEntityType.GetTableName());
        AssertEqual(DeleteBehavior.Restrict, foreignKey.DeleteBehavior);
        AssertTrue(role.FindProperty("CompanyId") is null);

        var version = role.FindProperty("Version")
            ?? throw new InvalidOperationException("Party Role version property was not found.");
        AssertTrue(version.IsConcurrencyToken);
    }

    private static IExecutionContext NewContext() =>
        new MarsExecutionContext(
            Guid.Parse("aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"),
            Guid.Parse("bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb"),
            null,
            new CorrelationId("corr-party-imp-002"));

    private static MarsDbContext CreateModelContext()
    {
        var options = MarsDbContextOptions.CreateRuntime(
            new PostgreSqlRuntimeOptions("Host=localhost;Database=mars_party_role_model_probe"));
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

    private sealed class FakePartyRoleActivationPersistence(
        ActivatePartyRolePersistenceOutcome outcome) : IPartyRoleActivationPersistence
    {
        public ActivatePartyRoleWrite? Write { get; private set; }

        public Task<ActivatePartyRolePersistenceOutcome> PersistAsync(
            ActivatePartyRoleWrite write,
            CancellationToken cancellationToken)
        {
            cancellationToken.ThrowIfCancellationRequested();
            Write = write;
            return Task.FromResult(outcome);
        }
    }
}
