using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Application.Parties;
using Mars.Application.Parties.DeactivateParty;
using MarsExecutionContext = Mars.Application.Foundation.Context.ExecutionContext;

internal static class PartyImp005Tests
{
    public static IReadOnlyList<(string Name, Action Test)> Cases { get; } =
        new (string Name, Action Test)[]
        {
            ("Deactivate Party denies missing party.deactivate before persistence", DeactivatePartyDeniesMissingPermission),
            ("Deactivate Party validates version reason and Party identity", DeactivatePartyValidatesInput),
            ("Deactivate Party produces trusted company audit and idempotency write", DeactivatePartyProducesScopedWrite),
            ("Deactivate Party maps not-found duplicate stale inactive and merged outcomes", DeactivatePartyMapsPersistenceOutcomes),
            ("Deactivate Party returns INACTIVE receipt with incremented version", DeactivatePartyReturnsInactiveReceipt)
        };

    private static void DeactivatePartyDeniesMissingPermission()
    {
        var permissions = new FakePermissionEvaluator(false);
        var persistence = new FakePersistence(
            new(DeactivatePartyPersistenceOutcome.Deactivated, 2));
        var handler = new DeactivatePartyHandler(permissions, persistence);

        var result = handler.ExecuteAsync(
                new DeactivatePartyCommand(
                    Guid.NewGuid(),
                    1,
                    "Administrative stop",
                    "party-deactivate-op-1"),
                NewContext(),
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(result.IsFailure);
        AssertEqual(ErrorCategory.Authorization, result.Error!.Category);
        AssertEqual(PartyPermissions.Deactivate, permissions.LastPermissionCode);
        AssertTrue(persistence.Write is null);
    }

    private static void DeactivatePartyValidatesInput()
    {
        var handler = new DeactivatePartyHandler(
            new FakePermissionEvaluator(true),
            new FakePersistence(new(DeactivatePartyPersistenceOutcome.Deactivated, 2)));
        var context = NewContext();

        var emptyParty = handler.ExecuteAsync(
                new DeactivatePartyCommand(Guid.Empty, 1, "Stop", "op-a"),
                context,
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();
        AssertEqual("parties.deactivate.party_required", emptyParty.Error!.Code);

        var invalidVersion = handler.ExecuteAsync(
                new DeactivatePartyCommand(Guid.NewGuid(), 0, "Stop", "op-b"),
                context,
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();
        AssertEqual("parties.deactivate.version_invalid", invalidVersion.Error!.Code);

        var missingReason = handler.ExecuteAsync(
                new DeactivatePartyCommand(Guid.NewGuid(), 1, " ", "op-c"),
                context,
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();
        AssertEqual("parties.deactivate.reason_required", missingReason.Error!.Code);
    }

    private static void DeactivatePartyProducesScopedWrite()
    {
        var context = NewContext();
        var partyPublicId = Guid.NewGuid();
        var persistence = new FakePersistence(
            new(DeactivatePartyPersistenceOutcome.Deactivated, 4));
        var handler = new DeactivatePartyHandler(
            new FakePermissionEvaluator(true),
            persistence);

        var result = handler.ExecuteAsync(
                new DeactivatePartyCommand(
                    partyPublicId,
                    3,
                    " Temporary commercial block ",
                    "party-deactivate-op-2"),
                context,
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(result.IsSuccess);
        AssertTrue(persistence.Write is not null);
        var write = persistence.Write!;

        AssertEqual(partyPublicId, write.PartyPublicId);
        AssertEqual(context.CompanyId, write.CompanyId);
        AssertEqual(3L, write.ExpectedVersion);
        AssertEqual($"parties.deactivate:{context.CompanyId:D}", write.Idempotency.Scope);
        AssertEqual("party-deactivate-op-2", write.Idempotency.OperationKey);
        AssertEqual(context.ActorId, write.Audit.ActorId);
        AssertEqual(context.CompanyId, write.Audit.CompanyId);
        AssertEqual("PartyDeactivated", write.Audit.Action);
        AssertEqual("Party", write.Audit.EntityType);
        AssertEqual(partyPublicId, write.Audit.EntityPublicId);
        AssertEqual("Temporary commercial block", write.Audit.Reason);
    }

    private static void DeactivatePartyMapsPersistenceOutcomes()
    {
        var context = NewContext();
        var partyPublicId = Guid.NewGuid();
        var cases = new[]
        {
            (
                DeactivatePartyPersistenceOutcome.PartyNotFound,
                ErrorCategory.NotFound,
                "parties.party_not_found"),
            (
                DeactivatePartyPersistenceOutcome.DuplicateOperation,
                ErrorCategory.Conflict,
                "parties.deactivate.duplicate_operation"),
            (
                DeactivatePartyPersistenceOutcome.StaleVersion,
                ErrorCategory.Concurrency,
                "parties.deactivate.stale_version"),
            (
                DeactivatePartyPersistenceOutcome.AlreadyInactive,
                ErrorCategory.Conflict,
                "parties.deactivate.already_inactive"),
            (
                DeactivatePartyPersistenceOutcome.MergedStateConflict,
                ErrorCategory.Conflict,
                "parties.deactivate.merged_state")
        };

        foreach (var (outcome, category, code) in cases)
        {
            var result = new DeactivatePartyHandler(
                    new FakePermissionEvaluator(true),
                    new FakePersistence(new(outcome)))
                .ExecuteAsync(
                    new DeactivatePartyCommand(
                        partyPublicId,
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

    private static void DeactivatePartyReturnsInactiveReceipt()
    {
        var partyPublicId = Guid.NewGuid();
        var result = new DeactivatePartyHandler(
                new FakePermissionEvaluator(true),
                new FakePersistence(new(DeactivatePartyPersistenceOutcome.Deactivated, 8)))
            .ExecuteAsync(
                new DeactivatePartyCommand(
                    partyPublicId,
                    7,
                    "Stop",
                    "party-deactivate-op-3"),
                NewContext(),
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(result.IsSuccess);
        AssertEqual(partyPublicId, result.Value!.PartyPublicId);
        AssertEqual("INACTIVE", result.Value.State);
        AssertEqual(8L, result.Value.Version);
    }

    private static IExecutionContext NewContext() =>
        new MarsExecutionContext(
            Guid.Parse("aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"),
            Guid.Parse("bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb"),
            null,
            new CorrelationId("corr-party-imp-005"));

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
        DeactivatePartyPersistenceResult result) : IPartyDeactivatePersistence
    {
        public DeactivatePartyWrite? Write { get; private set; }

        public Task<DeactivatePartyPersistenceResult> DeactivateAsync(
            DeactivatePartyWrite write,
            CancellationToken cancellationToken)
        {
            cancellationToken.ThrowIfCancellationRequested();
            Write = write;
            return Task.FromResult(result);
        }
    }
}
