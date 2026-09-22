using Mars.Application.Foundation.Configuration;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
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
    ("Startup configuration exception does not include option secret", StartupExceptionDoesNotIncludeSecret)
};

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
Console.WriteLine($"All {tests.Length} targeted FW-IMP-002 tests passed.");
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
    if (!condition)
    {
        throw new InvalidOperationException("Assertion failed.");
    }
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
