using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Application.Parties;
using Mars.Application.Parties.ChangePartyRoleState;
using Mars.Domain.Parties;
using MarsExecutionContext = Mars.Application.Foundation.Context.ExecutionContext;

internal static class PartyImp004Tests
{
    public static IReadOnlyList<(string Name, Action Test)> Cases { get; } =
        new (string Name, Action Test)[]
        {
            ("Change Party Role State denies missing party.role.manage before persistence", ChangeRoleStateDeniesMissingPermission),
            ("Change Party Role State validates state version and deactivation reason", ChangeRoleStateValidatesInput),
            ("Change Party Role State produces scoped deactivation audit and idempotency write", ChangeRoleStateProducesScopedDeactivationWrite),
            ("Change Party Role State allows reactivation without mandatory reason", ChangeRoleStateAllowsReactivationWithoutReason),
            ("Change Party Role State maps not-found duplicate stale and same-state outcomes", ChangeRoleStateMapsPersistenceOutcomes)
        };

    private static void ChangeRoleStateDeniesMissingPermission()
    {
        var permissions = new FakePermissionEvaluator(false);
        var persistence = new FakePersistence(
            new(ChangePartyRoleStatePersistenceOutcome.Changed, 2));
        var handler = new ChangePartyRoleStateHandler(permissions, persistence);

        var result = handler.ExecuteAsync(
                new ChangePartyRoleStateCommand(
                    Guid.NewGuid(),
                    "CUSTOMER",
                    "INACTIVE",
                    1,
                    "Temporary stop",
                    "role-state-op-1"),
                NewContext(),
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(result.IsFailure);
        AssertEqual(ErrorCategory.Authorization, result.Error!.Category);
        AssertEqual(PartyPermissions.RoleManage, permissions.LastPermissionCode);
        AssertTrue(persistence.Write is null);
    }

    private static void ChangeRoleStateValidatesInput()
    {
        var context = NewContext();
        var partyPublicId = Guid.NewGuid();

        var invalidState = Execute(
            new ChangePartyRoleStateCommand(
                partyPublicId,
                "CUSTOMER",
                "PAUSED",
                1,
                null,
                "role-state-invalid"),
            context);

        AssertEqual(ErrorCategory.Validation, invalidState.Error!.Category);
        AssertEqual("parties.role.state_invalid", invalidState.Error.Code);

        var invalidVersion = Execute(
            new ChangePartyRoleStateCommand(
                partyPublicId,
                "CUSTOMER",
                "ACTIVE",
                0,
                null,
                "role-state-version"),
            context);

        AssertEqual("parties.role.version_invalid", invalidVersion.Error!.Code);

        var missingReason = Execute(
            new ChangePartyRoleStateCommand(
                partyPublicId,
                "SUPPLIER",
                "INACTIVE",
                1,
                " ",
                "role-state-reason"),
            context);

        AssertEqual("parties.role.deactivation_reason_required", missingReason.Error!.Code);

        Result<ChangePartyRoleStateReceipt> Execute(
            ChangePartyRoleStateCommand command,
            IExecutionContext executionContext)
        {
            var persistence = new FakePersistence(
                new(ChangePartyRoleStatePersistenceOutcome.Changed, 2));
            return new ChangePartyRoleStateHandler(
                    new FakePermissionEvaluator(true),
                    persistence)
                .ExecuteAsync(command, executionContext, CancellationToken.None)
                .GetAwaiter()
                .GetResult();
        }
    }

    private static void ChangeRoleStateProducesScopedDeactivationWrite()
    {
        var context = NewContext();
        var partyPublicId = Guid.NewGuid();
        var persistence = new FakePersistence(
            new(ChangePartyRoleStatePersistenceOutcome.Changed, 2));
        var handler = new ChangePartyRoleStateHandler(
            new FakePermissionEvaluator(true),
            persistence);

        var result = handler.ExecuteAsync(
                new ChangePartyRoleStateCommand(
                    partyPublicId,
                    "SUPPLIER",
                    "INACTIVE",
                    1,
                    " Seasonal stop ",
                    "role-state-op-2"),
                context,
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(result.IsSuccess);
        AssertTrue(persistence.Write is not null);
        var write = persistence.Write!;

        AssertEqual(partyPublicId, write.PartyPublicId);
        AssertEqual(context.CompanyId, write.CompanyId);
        AssertEqual(PartyRoleType.Supplier, write.RoleType);
        AssertEqual(PartyRoleState.Inactive, write.TargetState);
        AssertEqual(1L, write.ExpectedVersion);
        AssertEqual($"parties.role.state:{context.CompanyId:D}", write.Idempotency.Scope);
        AssertEqual("role-state-op-2", write.Idempotency.OperationKey);
        AssertEqual(context.ActorId, write.Audit.ActorId);
        AssertEqual(context.CompanyId, write.Audit.CompanyId);
        AssertEqual("PartyRoleDeactivated.SUPPLIER", write.Audit.Action);
        AssertEqual("Seasonal stop", write.Audit.Reason);
        AssertEqual("INACTIVE", result.Value!.State);
        AssertEqual(2L, result.Value.Version);
    }

    private static void ChangeRoleStateAllowsReactivationWithoutReason()
    {
        var persistence = new FakePersistence(
            new(ChangePartyRoleStatePersistenceOutcome.Changed, 3));
        var handler = new ChangePartyRoleStateHandler(
            new FakePermissionEvaluator(true),
            persistence);

        var result = handler.ExecuteAsync(
                new ChangePartyRoleStateCommand(
                    Guid.NewGuid(),
                    "CUSTOMER",
                    "ACTIVE",
                    2,
                    null,
                    "role-state-op-3"),
                NewContext(),
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(result.IsSuccess);
        AssertTrue(persistence.Write is not null);
        AssertEqual(PartyRoleState.Active, persistence.Write!.TargetState);
        AssertEqual("PartyRoleReactivated.CUSTOMER", persistence.Write.Audit.Action);
        AssertTrue(persistence.Write.Audit.Reason is null);
        AssertEqual(3L, result.Value!.Version);
    }

    private static void ChangeRoleStateMapsPersistenceOutcomes()
    {
        var context = NewContext();
        var partyPublicId = Guid.NewGuid();
        var cases = new[]
        {
            (
                ChangePartyRoleStatePersistenceOutcome.PartyNotFound,
                ErrorCategory.NotFound,
                "parties.party_not_found"),
            (
                ChangePartyRoleStatePersistenceOutcome.RoleNotFound,
                ErrorCategory.NotFound,
                "parties.role_not_found"),
            (
                ChangePartyRoleStatePersistenceOutcome.DuplicateOperation,
                ErrorCategory.Conflict,
                "parties.role.duplicate_operation"),
            (
                ChangePartyRoleStatePersistenceOutcome.StaleVersion,
                ErrorCategory.Concurrency,
                "parties.role.stale_version"),
            (
                ChangePartyRoleStatePersistenceOutcome.AlreadyInTargetState,
                ErrorCategory.Conflict,
                "parties.role.state_conflict")
        };

        foreach (var (outcome, category, code) in cases)
        {
            var handler = new ChangePartyRoleStateHandler(
                new FakePermissionEvaluator(true),
                new FakePersistence(new(outcome)));

            var result = handler.ExecuteAsync(
                    new ChangePartyRoleStateCommand(
                        partyPublicId,
                        "CUSTOMER",
                        "INACTIVE",
                        1,
                        "Stop",
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

    private static IExecutionContext NewContext() =>
        new MarsExecutionContext(
            Guid.Parse("aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"),
            Guid.Parse("bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb"),
            null,
            new CorrelationId("corr-party-imp-004"));

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

    private sealed class FakePersistence(
        ChangePartyRoleStatePersistenceResult result) : IPartyRoleStatePersistence
    {
        public ChangePartyRoleStateWrite? Write { get; private set; }

        public Task<ChangePartyRoleStatePersistenceResult> ChangeAsync(
            ChangePartyRoleStateWrite write,
            CancellationToken cancellationToken)
        {
            cancellationToken.ThrowIfCancellationRequested();
            Write = write;
            return Task.FromResult(result);
        }
    }
}
