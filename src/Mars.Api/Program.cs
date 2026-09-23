using Mars.Api.Foundation.Authentication;
using Mars.Api.Foundation.Authorization;
using Mars.Api.Foundation.Context;
using Mars.Api.Parties;
using Mars.Api.Foundation.Errors;
using Mars.Api.Foundation.Health;
using Mars.Application.Foundation.Configuration;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Foundation.Outbox;
using Mars.Application.Foundation.Proof;
using Mars.Application.Parties;
using Mars.Application.Parties.CreateParty;
using Mars.Infrastructure.Identity;
using Mars.Infrastructure.Persistence;
using Mars.Infrastructure.Persistence.Foundation;
using Mars.Infrastructure.Persistence.Parties;
using Microsoft.AspNetCore.Authentication;
using Microsoft.AspNetCore.Authorization;
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

builder.Services.AddScoped<IAuditWriter, EfAuditWriter>();
builder.Services.AddScoped<IIdempotencyStore, EfIdempotencyStore>();
builder.Services.AddScoped<IOutboxWriter, EfOutboxStore>();
builder.Services.AddScoped<IFoundationProofPersistence, EfFoundationProofPersistence>();
builder.Services.AddScoped<FoundationProofHandler>();
builder.Services.AddScoped<IPermissionEvaluator, EfPermissionEvaluator>();
builder.Services.AddScoped<IPartyCreatePersistence, EfPartyCreatePersistence>();
builder.Services.AddScoped<CreatePartyHandler>();
builder.Services.AddScoped<IAuthorizationHandler, MarsPermissionAuthorizationHandler>();

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
            options.Events.OnRedirectToLogin = context =>
            {
                context.Response.StatusCode = StatusCodes.Status401Unauthorized;
                return Task.CompletedTask;
            };
            options.Events.OnRedirectToAccessDenied = context =>
            {
                context.Response.StatusCode = StatusCodes.Status403Forbidden;
                return Task.CompletedTask;
            };
        });

builder.Services.AddAuthorization(options =>
{
    options.AddPolicy(
        PartyPermissions.Create,
        policy =>
        {
            policy.RequireAuthenticatedUser();
            policy.AddRequirements(new MarsPermissionRequirement(PartyPermissions.Create));
        });
});

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
            .SetAuthorizationEndpointUris("connect/authorize")
            .SetTokenEndpointUris("connect/token")
            .AllowAuthorizationCodeFlow()
            .RequireProofKeyForCodeExchange()
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

app.MapPost(
        "/api/v1/parties",
        async (
            CreatePartyRequest request,
            HttpRequest httpRequest,
            IExecutionContext executionContext,
            CreatePartyHandler handler,
            CancellationToken cancellationToken) =>
        {
            var result = await handler.ExecuteAsync(
                new CreatePartyCommand(
                    request.PartyCode,
                    request.Kind,
                    request.LegalName,
                    request.DisplayName,
                    httpRequest.Headers["Idempotency-Key"].ToString()),
                executionContext,
                cancellationToken);

            if (result.IsFailure)
            {
                return ApplicationErrorHttpMapper.ToResult(
                    result.Error!,
                    executionContext.CorrelationId.Value);
            }

            return Results.Created(
                $"/api/v1/parties/{result.Value!.PublicId:D}",
                result.Value);
        })
    .RequireAuthorization(PartyPermissions.Create)
    .WithName("CreateParty")
    .WithSummary("Creates one company-scoped Party core identity.");

app.MapPost(
        "/api/v1/foundation/proof",
        async (
            HttpRequest request,
            IExecutionContext executionContext,
            FoundationProofHandler handler,
            CancellationToken cancellationToken) =>
        {
            var result = await handler.ExecuteAsync(
                new FoundationProofCommand(request.Headers["Idempotency-Key"].ToString()),
                executionContext,
                cancellationToken);

            if (result.IsFailure)
            {
                return ApplicationErrorHttpMapper.ToResult(
                    result.Error!,
                    executionContext.CorrelationId.Value);
            }

            return Results.Ok(result.Value);
        })
    .RequireAuthorization()
    .WithName("ExecuteFoundationVerticalProof")
    .WithSummary("Executes the non-domain Foundation request/application/DB/audit/outbox proof.");

app.Run();

public partial class Program;
