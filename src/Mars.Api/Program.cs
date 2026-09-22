using Mars.Api.Foundation.Authentication;
using Mars.Api.Foundation.Context;
using Mars.Api.Foundation.Errors;
using Mars.Api.Foundation.Health;
using Mars.Application.Foundation.Configuration;
using Mars.Application.Foundation.Context;
using Mars.Infrastructure.Identity;
using Mars.Infrastructure.Persistence;
using Microsoft.AspNetCore.Authentication;
using Microsoft.AspNetCore.Diagnostics.HealthChecks;
using Microsoft.AspNetCore.Identity;
using OpenIddict.Validation.AspNetCore;

var builder = WebApplication.CreateBuilder(args);

var runtimeOptions = new PostgreSqlRuntimeOptions(
    builder.Configuration["PostgreSql:RuntimeConnectionString"] ?? string.Empty);

StartupConfigurationValidation.ThrowIfInvalid(
    runtimeOptions,
    new[] { new PostgreSqlRuntimeOptionsValidator() });

builder.Services.AddMarsIdentityPersistence(runtimeOptions.ConnectionString);

builder.Services.AddOpenApi("v1");

builder.Services.AddHttpContextAccessor();
builder.Services.AddScoped<HttpExecutionContextAccessor>();
builder.Services.AddScoped<IExecutionContext>(
    services => services.GetRequiredService<HttpExecutionContextAccessor>().GetRequired());

builder.Services
    .AddAuthentication(options =>
    {
        options.DefaultAuthenticateScheme = MarsAuthenticationDefaults.CompositeScheme;
        options.DefaultChallengeScheme = MarsAuthenticationDefaults.CompositeScheme;
    })
    .AddPolicyScheme(
        MarsAuthenticationDefaults.CompositeScheme,
        "Mars cookie or bearer authentication",
        options =>
        {
            options.ForwardDefaultSelector = context =>
                context.Request.Headers.Authorization.ToString()
                    .StartsWith("Bearer ", StringComparison.OrdinalIgnoreCase)
                    ? OpenIddictValidationAspNetCoreDefaults.AuthenticationScheme
                    : IdentityConstants.ApplicationScheme;
        })
    .AddCookie(
        IdentityConstants.ApplicationScheme,
        options =>
        {
            options.Cookie.HttpOnly = true;
            options.Cookie.SecurePolicy = CookieSecurePolicy.Always;
            options.Cookie.SameSite = SameSiteMode.Lax;
            options.SlidingExpiration = true;
        });

builder.Services.AddAuthorization();

if (builder.Environment.IsProduction())
{
    throw new InvalidOperationException(
        "Production OpenIddict signing/encryption credentials are not configured by FW-IMP-005.");
}

builder.Services
    .AddOpenIddict()
    .AddServer(options =>
    {
        options
            .AddEphemeralEncryptionKey()
            .AddEphemeralSigningKey();

        options.UseAspNetCore();
    })
    .AddValidation(options =>
    {
        options.UseLocalServer();
        options.UseAspNetCore();
    });

builder.Services
    .AddHealthChecks()
    .AddCheck<PostgreSqlReadinessHealthCheck>(
        "postgresql",
        tags: new[] { "ready" });

var app = builder.Build();

app.UseMiddleware<CorrelationIdMiddleware>();
app.UseMiddleware<SafeExceptionMiddleware>();

app.UseAuthentication();
app.UseMiddleware<ExecutionContextMiddleware>();
app.UseAuthorization();

app.MapOpenApi("/openapi/{documentName}.json")
    .AllowAnonymous();

app.MapHealthChecks(
        "/health/live",
        new HealthCheckOptions
        {
            Predicate = _ => false
        })
    .AllowAnonymous();

app.MapHealthChecks(
        "/health/ready",
        new HealthCheckOptions
        {
            Predicate = registration => registration.Tags.Contains("ready")
        })
    .AllowAnonymous();

app.MapGet(
        "/api/v1/foundation/context",
        (IExecutionContext executionContext) =>
            Results.Ok(new
            {
                actorId = executionContext.ActorId,
                companyId = executionContext.CompanyId,
                branchId = executionContext.BranchId,
                correlationId = executionContext.CorrelationId.Value
            }))
    .RequireAuthorization()
    .WithName("GetFoundationExecutionContext")
    .WithSummary("Returns the trusted Mars execution context for an authenticated request.");

app.Run();

public partial class Program;
