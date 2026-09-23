using System.Text.Json;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Proof;
using Mars.Application.Foundation.Results;
using Mars.Infrastructure.Persistence.Foundation;
using MarsExecutionContext = Mars.Application.Foundation.Context.ExecutionContext;

internal static class FwImp008Tests
{
    public static IReadOnlyList<(string Name, Action Test)> Cases { get; } =
        new (string Name, Action Test)[]
        {
            ("Vertical proof handler creates company-scoped audit outbox write", HandlerCreatesScopedWrite),
            ("Vertical proof rejects invalid idempotency key before persistence", HandlerRejectsInvalidKey),
            ("Vertical proof maps duplicate idempotency key to conflict", HandlerMapsDuplicateToConflict),
            ("Vertical proof persistence uses existing Foundation transaction collaborators", PersistenceWiringUsesExistingCollaborators)
        };

    private static void HandlerCreatesScopedWrite()
    {
        var actorId = Guid.Parse("11111111-1111-1111-1111-111111111111");
        var companyId = Guid.Parse("22222222-2222-2222-2222-222222222222");
        var branchId = Guid.Parse("33333333-3333-3333-3333-333333333333");
        var context = new MarsExecutionContext(
            actorId,
            companyId,
            branchId,
            new CorrelationId("corr-fwimp008"));

        var persistence = new FakeFoundationProofPersistence(
            FoundationProofPersistenceOutcome.Created);
        var handler = new FoundationProofHandler(persistence);

        var result = handler.ExecuteAsync(
                new FoundationProofCommand("proof-op-1"),
                context,
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(result.IsSuccess);
        AssertTrue(persistence.Write is not null);

        var write = persistence.Write!;
        AssertEqual($"foundation.proof:{companyId:D}", write.Idempotency.Scope);
        AssertEqual("proof-op-1", write.Idempotency.OperationKey);
        AssertEqual(actorId, write.Audit.ActorId);
        AssertEqual(companyId, write.Audit.CompanyId);
        AssertEqual(branchId, write.Audit.BranchId);
        AssertEqual("Foundation", write.Audit.Module);
        AssertEqual("VerticalProofExecuted", write.Audit.Action);
        AssertEqual("Foundation.ProofExecuted", write.Outbox.EventType);
        AssertEqual("Foundation", write.Outbox.Module);
        AssertEqual(write.Outbox.EventId, write.Audit.EntityPublicId);
        AssertEqual(write.Outbox.EventId, write.Outbox.AggregatePublicId);
        AssertEqual(write.Outbox.EventId, result.Value!.EventId);
        AssertEqual("corr-fwimp008", result.Value.CorrelationId);

        using var payload = JsonDocument.Parse(write.Outbox.Payload);
        AssertEqual(companyId, payload.RootElement.GetProperty("companyId").GetGuid());
        AssertEqual(branchId, payload.RootElement.GetProperty("branchId").GetGuid());
        AssertEqual("corr-fwimp008", payload.RootElement.GetProperty("correlationId").GetString());
    }

    private static void HandlerRejectsInvalidKey()
    {
        var persistence = new FakeFoundationProofPersistence(
            FoundationProofPersistenceOutcome.Created);
        var handler = new FoundationProofHandler(persistence);
        var context = NewContext();

        var missing = handler.ExecuteAsync(
                new FoundationProofCommand("   "),
                context,
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(missing.IsFailure);
        AssertEqual(ErrorCategory.Validation, missing.Error!.Category);
        AssertEqual("foundation.proof.idempotency_key_required", missing.Error.Code);
        AssertTrue(persistence.Write is null);

        var tooLong = handler.ExecuteAsync(
                new FoundationProofCommand(new string('x', 201)),
                context,
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(tooLong.IsFailure);
        AssertEqual(ErrorCategory.Validation, tooLong.Error!.Category);
        AssertEqual("foundation.proof.idempotency_key_too_long", tooLong.Error.Code);
        AssertTrue(persistence.Write is null);
    }

    private static void HandlerMapsDuplicateToConflict()
    {
        var persistence = new FakeFoundationProofPersistence(
            FoundationProofPersistenceOutcome.Duplicate);
        var handler = new FoundationProofHandler(persistence);

        var result = handler.ExecuteAsync(
                new FoundationProofCommand("duplicate-op"),
                NewContext(),
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(result.IsFailure);
        AssertEqual(ErrorCategory.Conflict, result.Error!.Category);
        AssertEqual("foundation.proof.duplicate", result.Error.Code);
    }

    private static void PersistenceWiringUsesExistingCollaborators()
    {
        AssertTrue(typeof(IFoundationProofPersistence).IsAssignableFrom(
            typeof(EfFoundationProofPersistence)));

        var constructor = typeof(EfFoundationProofPersistence)
            .GetConstructors()
            .Single();

        var parameterTypes = constructor.GetParameters()
            .Select(parameter => parameter.ParameterType)
            .ToArray();

        AssertTrue(parameterTypes.Contains(typeof(Mars.Infrastructure.Persistence.MarsDbContext)));
        AssertTrue(parameterTypes.Contains(typeof(Mars.Application.Foundation.Auditing.IAuditWriter)));
        AssertTrue(parameterTypes.Contains(typeof(Mars.Application.Foundation.Idempotency.IIdempotencyStore)));
        AssertTrue(parameterTypes.Contains(typeof(Mars.Application.Foundation.Outbox.IOutboxWriter)));
    }

    private static IExecutionContext NewContext() =>
        new MarsExecutionContext(
            Guid.Parse("aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"),
            Guid.Parse("bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb"),
            null,
            new CorrelationId("corr-fwimp008-test"));

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

    private sealed class FakeFoundationProofPersistence(
        FoundationProofPersistenceOutcome outcome) : IFoundationProofPersistence
    {
        public FoundationProofWrite? Write { get; private set; }

        public Task<FoundationProofPersistenceOutcome> PersistAsync(
            FoundationProofWrite write,
            CancellationToken cancellationToken)
        {
            cancellationToken.ThrowIfCancellationRequested();
            Write = write;
            return Task.FromResult(outcome);
        }
    }
}
