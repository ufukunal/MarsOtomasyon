using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Configuration;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Foundation.Outbox;
using Mars.Application.Foundation.Results;
using Mars.Infrastructure.Persistence;
using Mars.Worker.Foundation.Outbox;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata;
using MarsExecutionContext = Mars.Application.Foundation.Context.ExecutionContext;

var tests = new (string Name, Action Test)[]
{
    ("CorrelationId rejects empty values", CorrelationIdRejectsEmptyValues),
    ("ExecutionContext preserves immutable scope", ExecutionContextPreservesScope),
    ("ExecutionContext rejects empty identities", ExecutionContextRejectsEmptyIdentities),
    ("Result enforces success/failure shape", ResultEnforcesShape),
    ("Result<T> carries success value", GenericResultCarriesValue),
    ("Error categories match Foundation contract", ErrorCategoriesMatchContract),
    ("Startup configuration validation aggregates issues", StartupValidationAggregatesIssues),
    ("Startup configuration exception does not include option secret", StartupExceptionDoesNotIncludeSecret),
    ("PostgreSQL runtime options require a connection string", PostgreSqlRuntimeOptionsRequireConnectionString),
    ("Foundation model still contains audit idempotency outbox", FoundationModelContainsOnlyAllowedEntities),
    ("Idempotency scope and key are uniquely constrained", IdempotencyScopeAndKeyAreUnique),
    ("Outbox event id is uniquely constrained", OutboxEventIdIsUnique),
    ("Outbox payload uses PostgreSQL jsonb", OutboxPayloadUsesJsonb),
    ("Audit entry rejects missing scope identities", AuditEntryRejectsMissingScope),
    ("Idempotency operation rejects missing logical identity", IdempotencyOperationRejectsMissingIdentity),
    ("Outbox message rejects invalid schema version", OutboxMessageRejectsInvalidSchemaVersion),
    ("Migration factory requires migration-specific configuration", MigrationFactoryRequiresMigrationConfiguration),
    ("Outbox processor records success retry and terminal failure", OutboxProcessorRecordsOutcomes),
    ("Outbox processor honors cancellation", OutboxProcessorHonorsCancellation)
}.Concat(FwImp005Tests.Cases)
    .Concat(FwImp008Tests.Cases)
    .Concat(PartyImp001Tests.Cases)
    .Concat(PartyImp002Tests.Cases)
    .Concat(PartyImp003Tests.Cases)
    .Concat(PartyImp004Tests.Cases)
    .Concat(PartyImp005Tests.Cases)
    .Concat(PartyImp006Tests.Cases)
    .ToArray();

var failures = new List<string>();

foreach (var (name, test) in tests)
{
    try
    {
        test();
        Console.WriteLine($"PASS: {name}");
    }
    catch (Exception exception)
    {
        failures.Add($"{name}: {exception.GetType().Name}: {exception.Message}");
        Console.Error.WriteLine($"FAIL: {name}");
    }
}

if (failures.Count > 0)
{
    Console.Error.WriteLine();
    foreach (var failure in failures)
    {
        Console.Error.WriteLine(failure);
    }

    return 1;
}

Console.WriteLine();
Console.WriteLine($"All {tests.Length} targeted Foundation tests passed.");
return 0;

static void CorrelationIdRejectsEmptyValues()
{
    AssertThrows<ArgumentException>(() => _ = new CorrelationId("   "));
    AssertEqual("corr-123", new CorrelationId(" corr-123 ").Value);
}

static void ExecutionContextPreservesScope()
{
    var actorId = Guid.NewGuid();
    var companyId = Guid.NewGuid();
    var branchId = Guid.NewGuid();
    var correlationId = new CorrelationId("corr-456");

    IExecutionContext context = new MarsExecutionContext(actorId, companyId, branchId, correlationId);

    AssertEqual(actorId, context.ActorId);
    AssertEqual(companyId, context.CompanyId);
    AssertEqual(branchId, context.BranchId);
    AssertEqual(correlationId, context.CorrelationId);
}

static void ExecutionContextRejectsEmptyIdentities()
{
    var valid = Guid.NewGuid();
    var correlationId = new CorrelationId("corr-789");

    AssertThrows<ArgumentException>(() => _ = new MarsExecutionContext(Guid.Empty, valid, null, correlationId));
    AssertThrows<ArgumentException>(() => _ = new MarsExecutionContext(valid, Guid.Empty, null, correlationId));
    AssertThrows<ArgumentException>(() => _ = new MarsExecutionContext(valid, valid, Guid.Empty, correlationId));
}

static void ResultEnforcesShape()
{
    var success = Result.Success();
    AssertTrue(success.IsSuccess);
    AssertTrue(!success.IsFailure);
    AssertTrue(success.Error is null);

    var error = new ApplicationError(ErrorCategory.Validation, "validation.failed", "Validation failed.");
    var failure = Result.Failure(error);
    AssertTrue(failure.IsFailure);
    AssertEqual(error, failure.Error);
}

static void GenericResultCarriesValue()
{
    var success = Result<int>.Success(42);
    AssertTrue(success.IsSuccess);
    AssertEqual(42, success.Value);
}

static void ErrorCategoriesMatchContract()
{
    var expected = new[]
    {
        nameof(ErrorCategory.Validation),
        nameof(ErrorCategory.Authentication),
        nameof(ErrorCategory.Authorization),
        nameof(ErrorCategory.NotFound),
        nameof(ErrorCategory.Conflict),
        nameof(ErrorCategory.Concurrency),
        nameof(ErrorCategory.BusinessRule),
        nameof(ErrorCategory.RateLimit),
        nameof(ErrorCategory.Infrastructure),
        nameof(ErrorCategory.ProviderFailure)
    };

    AssertSequenceEqual(expected, Enum.GetNames<ErrorCategory>());
}

static void StartupValidationAggregatesIssues()
{
    var options = new SampleOptions("value", "secret-value");
    var validators = new IStartupConfigurationValidator<SampleOptions>[]
    {
        new PassingValidator(),
        new FailingValidator()
    };

    var exception = AssertThrows<StartupConfigurationException>(
        () => StartupConfigurationValidation.ThrowIfInvalid(options, validators));

    AssertEqual(2, exception.Issues.Count);
    AssertTrue(exception.Message.Contains("Sample:missing", StringComparison.Ordinal));
    AssertTrue(exception.Message.Contains("Other:invalid", StringComparison.Ordinal));
}

static void StartupExceptionDoesNotIncludeSecret()
{
    const string secret = "do-not-leak-this-secret";
    var options = new SampleOptions("value", secret);

    var exception = AssertThrows<StartupConfigurationException>(
        () => StartupConfigurationValidation.ThrowIfInvalid(
            options,
            new IStartupConfigurationValidator<SampleOptions>[] { new FailingValidator() }));

    AssertTrue(!exception.Message.Contains(secret, StringComparison.Ordinal));
}

static void PostgreSqlRuntimeOptionsRequireConnectionString()
{
    var validator = new PostgreSqlRuntimeOptionsValidator();
    var issues = validator.Validate(new PostgreSqlRuntimeOptions("   "));

    AssertEqual(1, issues.Count);
    AssertEqual("PostgreSql:RuntimeConnectionString", issues[0].Key);
    AssertEqual("required", issues[0].Code);
}

static MarsDbContext CreateModelContext()
{
    var options = MarsDbContextOptions.CreateRuntime(
        new PostgreSqlRuntimeOptions("Host=localhost;Database=mars_model_probe"));
    return new MarsDbContext(options);
}

static void FoundationModelContainsOnlyAllowedEntities()
{
    using var context = CreateModelContext();
    var tables = context.Model.GetEntityTypes()
        .Select(x => $"{x.GetSchema()}.{x.GetTableName()}")
        .ToHashSet(StringComparer.Ordinal);

    AssertTrue(tables.Contains("foundation.audit_events"));
    AssertTrue(tables.Contains("foundation.idempotency_operations"));
    AssertTrue(tables.Contains("foundation.outbox_messages"));
}

static void IdempotencyScopeAndKeyAreUnique()
{
    using var context = CreateModelContext();
    var entity = FindTable(context.Model, "idempotency_operations");
    AssertTrue(entity.GetIndexes().Any(index =>
        index.IsUnique &&
        index.Properties.Select(property => property.GetColumnName()).SequenceEqual(
            new[] { "scope", "operation_key" })));
}

static void OutboxEventIdIsUnique()
{
    using var context = CreateModelContext();
    var entity = FindTable(context.Model, "outbox_messages");
    AssertTrue(entity.GetIndexes().Any(index =>
        index.IsUnique &&
        index.Properties.Count == 1 &&
        index.Properties[0].GetColumnName() == "event_id"));
}

static void OutboxPayloadUsesJsonb()
{
    using var context = CreateModelContext();
    var entity = FindTable(context.Model, "outbox_messages");
    AssertEqual("jsonb", entity.FindProperty("Payload")?.GetColumnType());
}

static void AuditEntryRejectsMissingScope()
{
    var now = DateTimeOffset.UtcNow;
    var valid = Guid.NewGuid();

    AssertThrows<ArgumentException>(() =>
        _ = new AuditEntry(Guid.Empty, valid, null, "c", "Foundation", "Action", null, null, null, now));
    AssertThrows<ArgumentException>(() =>
        _ = new AuditEntry(valid, Guid.Empty, null, "c", "Foundation", "Action", null, null, null, now));
}

static void IdempotencyOperationRejectsMissingIdentity()
{
    AssertThrows<ArgumentException>(() =>
        _ = new IdempotencyOperation(" ", "key", null, DateTimeOffset.UtcNow));
    AssertThrows<ArgumentException>(() =>
        _ = new IdempotencyOperation("scope", " ", null, DateTimeOffset.UtcNow));
}

static void OutboxMessageRejectsInvalidSchemaVersion()
{
    AssertThrows<ArgumentOutOfRangeException>(() =>
        _ = new OutboxMessage(
            Guid.NewGuid(),
            "Foundation.Sample",
            "Foundation",
            null,
            0,
            "{}",
            DateTimeOffset.UtcNow,
            DateTimeOffset.UtcNow));
}

static void MigrationFactoryRequiresMigrationConfiguration()
{
    var existing = Environment.GetEnvironmentVariable(
        MarsDesignTimeDbContextFactory.MigrationConnectionStringEnvironmentVariable);

    try
    {
        Environment.SetEnvironmentVariable(
            MarsDesignTimeDbContextFactory.MigrationConnectionStringEnvironmentVariable,
            null);

        var factory = new MarsDesignTimeDbContextFactory();
        var exception = AssertThrows<InvalidOperationException>(
            () => factory.CreateDbContext(Array.Empty<string>()));

        AssertTrue(
            exception.Message.Contains(
                MarsDesignTimeDbContextFactory.MigrationConnectionStringEnvironmentVariable,
                StringComparison.Ordinal));
    }
    finally
    {
        Environment.SetEnvironmentVariable(
            MarsDesignTimeDbContextFactory.MigrationConnectionStringEnvironmentVariable,
            existing);
    }
}

static void OutboxProcessorRecordsOutcomes()
{
    var now = DateTimeOffset.Parse("2026-09-22T09:00:00+00:00");
    var retryAt = now.AddMinutes(5);
    var items = new[]
    {
        NewWorkItem("Success"),
        NewWorkItem("Retry"),
        NewWorkItem("Fail")
    };

    var store = new FakeOutboxWorkStore(items);
    var dispatcher = new FakeOutboxDispatcher(retryAt);
    var processor = new OutboxBatchProcessor(store, dispatcher, 10, TimeSpan.FromMinutes(1));

    var count = processor.ProcessOnceAsync(now, CancellationToken.None).GetAwaiter().GetResult();

    AssertEqual(3, count);
    AssertEqual(1, store.Processed.Count);
    AssertEqual(1, store.Retried.Count);
    AssertEqual(1, store.Failed.Count);
    AssertEqual(retryAt, store.Retried[0].AvailableAt);
    AssertEqual("retry.test", store.Retried[0].ErrorCode);
    AssertEqual("failed.test", store.Failed[0].ErrorCode);
}

static void OutboxProcessorHonorsCancellation()
{
    var item = NewWorkItem("Success");
    var store = new FakeOutboxWorkStore(new[] { item });
    var dispatcher = new FakeOutboxDispatcher(DateTimeOffset.UtcNow);
    var processor = new OutboxBatchProcessor(store, dispatcher, 1, TimeSpan.FromMinutes(1));
    using var cancellation = new CancellationTokenSource();
    cancellation.Cancel();

    AssertThrows<OperationCanceledException>(() =>
        processor.ProcessOnceAsync(DateTimeOffset.UtcNow, cancellation.Token).GetAwaiter().GetResult());
}

static OutboxWorkItem NewWorkItem(string eventType) =>
    new(Guid.NewGuid(), Guid.NewGuid(), eventType, "Foundation", null, 1, "{}", 1);

static IEntityType FindTable(IModel model, string tableName) =>
    model.GetEntityTypes().Single(entity => entity.GetTableName() == tableName);

static TException AssertThrows<TException>(Action action)
    where TException : Exception
{
    try
    {
        action();
    }
    catch (TException exception)
    {
        return exception;
    }

    throw new InvalidOperationException($"Expected exception {typeof(TException).Name} was not thrown.");
}

static void AssertTrue(bool condition)
{
    if (!condition) throw new InvalidOperationException("Assertion failed.");
}

static void AssertEqual<T>(T expected, T actual)
{
    if (!EqualityComparer<T>.Default.Equals(expected, actual))
    {
        throw new InvalidOperationException($"Expected '{expected}', actual '{actual}'.");
    }
}

static void AssertSequenceEqual<T>(IReadOnlyList<T> expected, IReadOnlyList<T> actual)
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

internal sealed record SampleOptions(string Value, string Secret);

internal sealed class PassingValidator : IStartupConfigurationValidator<SampleOptions>
{
    public IReadOnlyList<ConfigurationValidationIssue> Validate(SampleOptions options) =>
        Array.Empty<ConfigurationValidationIssue>();
}

internal sealed class FailingValidator : IStartupConfigurationValidator<SampleOptions>
{
    public IReadOnlyList<ConfigurationValidationIssue> Validate(SampleOptions options) =>
        new[]
        {
            new ConfigurationValidationIssue("Sample", "missing", "A required setting is missing."),
            new ConfigurationValidationIssue("Other", "invalid", "Another setting is invalid.")
        };
}

internal sealed class FakeOutboxDispatcher(DateTimeOffset retryAt) : IOutboxDispatcher
{
    public Task<OutboxDispatchResult> DispatchAsync(
        OutboxWorkItem workItem,
        CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();

        return Task.FromResult(workItem.EventType switch
        {
            "Success" => OutboxDispatchResult.Success(),
            "Retry" => OutboxDispatchResult.RetryLater(retryAt, "retry.test"),
            _ => OutboxDispatchResult.Failed("failed.test")
        });
    }
}

internal sealed class FakeOutboxWorkStore(IReadOnlyList<OutboxWorkItem> items) : IOutboxWorkStore
{
    public List<(Guid EventId, Guid ClaimToken)> Processed { get; } = [];
    public List<(Guid EventId, Guid ClaimToken, DateTimeOffset AvailableAt, string ErrorCode)> Retried { get; } = [];
    public List<(Guid EventId, Guid ClaimToken, string ErrorCode)> Failed { get; } = [];

    public Task<IReadOnlyList<OutboxWorkItem>> ClaimAsync(
        int maxBatchSize,
        DateTimeOffset now,
        TimeSpan leaseDuration,
        CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        return Task.FromResult<IReadOnlyList<OutboxWorkItem>>(items.Take(maxBatchSize).ToArray());
    }

    public Task<bool> MarkProcessedAsync(
        Guid eventId,
        Guid claimToken,
        DateTimeOffset processedAt,
        CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        Processed.Add((eventId, claimToken));
        return Task.FromResult(true);
    }

    public Task<bool> MarkRetryAsync(
        Guid eventId,
        Guid claimToken,
        DateTimeOffset availableAt,
        string errorCode,
        CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        Retried.Add((eventId, claimToken, availableAt, errorCode));
        return Task.FromResult(true);
    }

    public Task<bool> MarkFailedAsync(
        Guid eventId,
        Guid claimToken,
        string errorCode,
        CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        Failed.Add((eventId, claimToken, errorCode));
        return Task.FromResult(true);
    }
}
