using System.Security.Claims;
using System.Text;
using Mars.Api.Foundation.Authentication;
using Mars.Api.Foundation.Context;
using Mars.Api.Foundation.Errors;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Infrastructure.Persistence;
using Microsoft.AspNetCore.Http;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Logging.Abstractions;
using OpenIddict.Abstractions;

internal static class FwImp005Tests
{
    public static readonly (string Name, Action Test)[] Cases =
    {
        ("Trusted principal maps into Mars execution context", TrustedPrincipalMapsExecutionContext),
        ("Execution context rejects ambiguous company scope", ExecutionContextRejectsAmbiguousCompany),
        ("Execution context ignores untrusted request scope headers", ExecutionContextIgnoresRequestScopeHeaders),
        ("Application errors map deterministically to HTTP status", ApplicationErrorsMapDeterministically),
        ("Unhandled exception response hides internal details", UnhandledExceptionHidesInternalDetails),
        ("Identity protocol model stays in Foundation-owned schemas", IdentityModelHasOnlyAllowedSchemas),
        ("Identity user key is UUID", IdentityUserKeyIsUuid)
    };

    private static void TrustedPrincipalMapsExecutionContext()
    {
        var actorId = Guid.NewGuid();
        var companyId = Guid.NewGuid();
        var branchId = Guid.NewGuid();
        var principal = AuthenticatedPrincipal(actorId, companyId, branchId);

        var executionContext = TrustedExecutionContextFactory.Create(
            principal,
            new CorrelationId("corr-fwimp005"));

        AssertEqual(actorId, executionContext.ActorId);
        AssertEqual(companyId, executionContext.CompanyId);
        AssertEqual<Guid?>(branchId, executionContext.BranchId);
        AssertEqual("corr-fwimp005", executionContext.CorrelationId.Value);
    }

    private static void ExecutionContextRejectsAmbiguousCompany()
    {
        var actorId = Guid.NewGuid();
        var identity = new ClaimsIdentity("test");
        identity.AddClaim(new Claim(OpenIddictConstants.Claims.Subject, actorId.ToString()));
        identity.AddClaim(new Claim(MarsAuthenticationDefaults.CompanyIdClaim, Guid.NewGuid().ToString()));
        identity.AddClaim(new Claim(MarsAuthenticationDefaults.CompanyIdClaim, Guid.NewGuid().ToString()));

        AssertThrows<TrustedExecutionContextException>(() =>
            TrustedExecutionContextFactory.Create(
                new ClaimsPrincipal(identity),
                new CorrelationId("corr-ambiguous")));
    }

    private static void ExecutionContextIgnoresRequestScopeHeaders()
    {
        var trustedCompanyId = Guid.NewGuid();
        var principal = AuthenticatedPrincipal(Guid.NewGuid(), trustedCompanyId, null);

        var httpContext = new DefaultHttpContext();
        httpContext.Request.Headers["X-Company-ID"] = Guid.NewGuid().ToString();
        httpContext.User = principal;

        var executionContext = TrustedExecutionContextFactory.Create(
            httpContext.User,
            new CorrelationId("corr-header"));

        AssertEqual(trustedCompanyId, executionContext.CompanyId);
    }

    private static void ApplicationErrorsMapDeterministically()
    {
        var expected = new Dictionary<ErrorCategory, int>
        {
            [ErrorCategory.Validation] = StatusCodes.Status400BadRequest,
            [ErrorCategory.Authentication] = StatusCodes.Status401Unauthorized,
            [ErrorCategory.Authorization] = StatusCodes.Status403Forbidden,
            [ErrorCategory.NotFound] = StatusCodes.Status404NotFound,
            [ErrorCategory.Conflict] = StatusCodes.Status409Conflict,
            [ErrorCategory.Concurrency] = StatusCodes.Status409Conflict,
            [ErrorCategory.BusinessRule] = StatusCodes.Status422UnprocessableEntity,
            [ErrorCategory.RateLimit] = StatusCodes.Status429TooManyRequests,
            [ErrorCategory.Infrastructure] = StatusCodes.Status503ServiceUnavailable,
            [ErrorCategory.ProviderFailure] = StatusCodes.Status503ServiceUnavailable
        };

        foreach (var pair in expected)
        {
            AssertEqual(pair.Value, ApplicationErrorHttpMapper.GetStatusCode(pair.Key));
        }
    }

    private static void UnhandledExceptionHidesInternalDetails()
    {
        const string secret = "sql-password-or-stack-detail";
        var middleware = new SafeExceptionMiddleware(
            _ => throw new InvalidOperationException(secret),
            NullLogger<SafeExceptionMiddleware>.Instance);

        var context = new DefaultHttpContext();
        context.Items[CorrelationIdMiddleware.ItemKey] = new CorrelationId("corr-safe");
        context.Response.Body = new MemoryStream();

        middleware.InvokeAsync(context).GetAwaiter().GetResult();

        context.Response.Body.Position = 0;
        using var reader = new StreamReader(context.Response.Body, Encoding.UTF8);
        var response = reader.ReadToEnd();

        AssertEqual(StatusCodes.Status500InternalServerError, context.Response.StatusCode);
        AssertTrue(!response.Contains(secret, StringComparison.Ordinal));
        AssertTrue(response.Contains("infrastructure.unhandled", StringComparison.Ordinal));
        AssertTrue(response.Contains("corr-safe", StringComparison.Ordinal));
    }

    private static void IdentityModelHasOnlyAllowedSchemas()
    {
        using var context = CreateModelContext();
        var unexpected = context.Model.GetEntityTypes()
            .Select(entity => entity.GetSchema())
            .Where(schema => schema is not "foundation" and not "identity")
            .Distinct()
            .ToArray();

        AssertEqual(0, unexpected.Length);

        var tables = context.Model.GetEntityTypes()
            .Select(entity => entity.GetTableName())
            .Where(name => name is not null)
            .ToArray();

        AssertTrue(tables.Contains("users", StringComparer.Ordinal));
        AssertTrue(tables.Contains("audit_events", StringComparer.Ordinal));
        AssertTrue(tables.Any(name => name!.StartsWith("OpenIddict", StringComparison.Ordinal)));
    }

    private static void IdentityUserKeyIsUuid()
    {
        using var context = CreateModelContext();
        var user = context.Model.GetEntityTypes()
            .Single(entity => entity.GetTableName() == "users");

        var keyProperty = user.FindPrimaryKey()?.Properties.Single()
            ?? throw new InvalidOperationException("Identity user primary key was not found.");

        AssertEqual(typeof(Guid), keyProperty.ClrType);
    }

    private static ClaimsPrincipal AuthenticatedPrincipal(
        Guid actorId,
        Guid companyId,
        Guid? branchId)
    {
        var identity = new ClaimsIdentity("test");
        identity.AddClaim(new Claim(OpenIddictConstants.Claims.Subject, actorId.ToString()));
        identity.AddClaim(new Claim(MarsAuthenticationDefaults.CompanyIdClaim, companyId.ToString()));

        if (branchId.HasValue)
        {
            identity.AddClaim(new Claim(MarsAuthenticationDefaults.BranchIdClaim, branchId.Value.ToString()));
        }

        return new ClaimsPrincipal(identity);
    }

    private static MarsDbContext CreateModelContext()
    {
        var options = MarsDbContextOptions.CreateRuntime(
            new PostgreSqlRuntimeOptions("Host=localhost;Database=mars_fwimp005_model_probe"));

        return new MarsDbContext(options);
    }

    private static TException AssertThrows<TException>(Action action)
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

        throw new InvalidOperationException(
            $"Expected exception {typeof(TException).Name} was not thrown.");
    }

    private static void AssertTrue(bool condition)
    {
        if (!condition)
        {
            throw new InvalidOperationException("Assertion failed.");
        }
    }

    private static void AssertEqual<T>(T expected, T actual)
    {
        if (!EqualityComparer<T>.Default.Equals(expected, actual))
        {
            throw new InvalidOperationException(
                $"Expected '{expected}', actual '{actual}'.");
        }
    }
}
