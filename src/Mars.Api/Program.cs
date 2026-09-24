using Mars.Api.Foundation.Authentication;
using Mars.Api.Foundation.Authorization;
using Mars.Api.Foundation.Context;
using Mars.Api.Parties;
using Mars.Api.Products;
using Mars.Api.Inventory;
using Mars.Api.Sales;
using Mars.Api.Foundation.Errors;
using Mars.Api.Foundation.Health;
using Mars.Application.Foundation.Configuration;
using Mars.Application.Foundation.Approvals;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Foundation.Outbox;
using Mars.Application.Foundation.Proof;
using Mars.Application.Parties;
using Mars.Application.Parties.CreateParty;
using Mars.Application.Parties.DeactivateParty;
using Mars.Application.Parties.ActivatePartyRole;
using Mars.Application.Parties.ChangePartyRoleState;
using Mars.Application.Parties.AddPartyTaxIdentity;
using Mars.Application.Parties.PartyMaster;
using Mars.Application.Products;
using Mars.Application.Products.ProductMaster;
using Mars.Application.Inventory;
using Mars.Application.Purchasing;
using Mars.Application.Sales;
using Mars.Infrastructure.Identity;
using Mars.Infrastructure.Persistence;
using Mars.Infrastructure.Persistence.Foundation;
using Mars.Infrastructure.Persistence.Parties;
using Mars.Infrastructure.Persistence.Products;
using Mars.Infrastructure.Persistence.Inventory;
using Mars.Infrastructure.Persistence.Sales;
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
builder.Services.AddScoped<IApprovalDecisionPersistence, EfApprovalDecisionPersistence>();
builder.Services.AddScoped<IApprovalDecisionAuthority, ApprovalDecisionAuthority>();
builder.Services.AddScoped<IFoundationProofPersistence, EfFoundationProofPersistence>();
builder.Services.AddScoped<FoundationProofHandler>();
builder.Services.AddScoped<IPermissionEvaluator, EfPermissionEvaluator>();
builder.Services.AddScoped<IPartyCreatePersistence, EfPartyCreatePersistence>();
builder.Services.AddScoped<CreatePartyHandler>();
builder.Services.AddScoped<IPartyDeactivatePersistence, EfPartyDeactivatePersistence>();
builder.Services.AddScoped<DeactivatePartyHandler>();
builder.Services.AddScoped<IPartyRoleActivationPersistence, EfPartyRoleActivationPersistence>();
builder.Services.AddScoped<ActivatePartyRoleHandler>();
builder.Services.AddScoped<IPartyRoleStatePersistence, EfPartyRoleStatePersistence>();
builder.Services.AddScoped<ChangePartyRoleStateHandler>();
builder.Services.AddScoped<IPartyTaxIdentityAddPersistence, EfPartyTaxIdentityAddPersistence>();
builder.Services.AddScoped<AddPartyTaxIdentityHandler>();
builder.Services.AddScoped<EfPartyMasterPersistence>();
builder.Services.AddScoped<IPartyMasterReadPersistence>(
    services => services.GetRequiredService<EfPartyMasterPersistence>());
builder.Services.AddScoped<IPartyMasterMutationPersistence>(
    services => services.GetRequiredService<EfPartyMasterPersistence>());
builder.Services.AddScoped<PartyMasterQueryHandler>();
builder.Services.AddScoped<PartyMasterCommandHandler>();
builder.Services.AddScoped<EfProductMasterPersistence>();
builder.Services.AddScoped<IProductMasterReadPersistence>(
    services => services.GetRequiredService<EfProductMasterPersistence>());
builder.Services.AddScoped<IProductMasterMutationPersistence>(
    services => services.GetRequiredService<EfProductMasterPersistence>());
builder.Services.AddScoped<ProductMasterQueryHandler>();
builder.Services.AddScoped<ProductMasterCommandHandler>();
builder.Services.AddScoped<EfInventoryPersistence>();
builder.Services.AddScoped<IInventoryReadPersistence>(
    services => services.GetRequiredService<EfInventoryPersistence>());
builder.Services.AddScoped<IInventoryMutationPersistence>(
    services => services.GetRequiredService<EfInventoryPersistence>());
builder.Services.AddScoped<IInventoryAuthorityPersistence>(
    services => services.GetRequiredService<EfInventoryPersistence>());
builder.Services.AddScoped<InventoryQueryHandler>();
builder.Services.AddScoped<InventoryMasterCommandHandler>();
builder.Services.AddScoped<InventoryAuthorityService>();
builder.Services.AddScoped<IInventoryPhysicalAuthority>(
    services => services.GetRequiredService<InventoryAuthorityService>());
builder.Services.AddScoped<IInventoryReservationAuthority>(
    services => services.GetRequiredService<InventoryAuthorityService>());
builder.Services.AddScoped<IWarehouseAccessEvaluator, EfWarehouseAccessEvaluator>();
builder.Services.AddScoped<IWarehouseAccessGrantAuthority, EfWarehouseAccessGrantAuthority>();
builder.Services.AddScoped<EfSalesPersistence>();
builder.Services.AddScoped<ISalesPersistence>(
    services => services.GetRequiredService<EfSalesPersistence>());
builder.Services.AddScoped<ISalesProformaPersistence>(
    services => services.GetRequiredService<EfSalesPersistence>());
builder.Services.AddScoped<ISalesTransactionCoordinator, EfSalesTransactionCoordinator>();
builder.Services.AddScoped<SalesQueryHandler>();
builder.Services.AddScoped<SalesCommandHandler>();
builder.Services.AddScoped<SalesProformaQueryHandler>();
builder.Services.AddScoped<SalesProformaCommandHandler>();
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
    foreach (var permission in PartyPermissions.All.Concat(ProductPermissions.All).Concat(InventoryPermissions.All).Concat(SalesPermissions.All).Concat(PurchasingPermissions.All))
    {
        options.AddPolicy(
            permission,
            policy =>
            {
                policy.RequireAuthenticatedUser();
                policy.AddRequirements(new MarsPermissionRequirement(permission));
            });
    }
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
        "/api/v1/parties/{partyPublicId:guid}/deactivate",
        async (
            Guid partyPublicId,
            DeactivatePartyRequest request,
            HttpRequest httpRequest,
            IExecutionContext executionContext,
            DeactivatePartyHandler handler,
            CancellationToken cancellationToken) =>
        {
            var result = await handler.ExecuteAsync(
                new DeactivatePartyCommand(
                    partyPublicId,
                    request.Version,
                    request.Reason,
                    httpRequest.Headers["Idempotency-Key"].ToString()),
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
    .RequireAuthorization(PartyPermissions.Deactivate)
    .WithName("DeactivateParty")
    .WithSummary("Changes one ACTIVE company-scoped Party to INACTIVE with a mandatory reason.");

app.MapPost(
        "/api/v1/parties/{partyPublicId:guid}/roles",
        async (
            Guid partyPublicId,
            ActivatePartyRoleRequest request,
            HttpRequest httpRequest,
            IExecutionContext executionContext,
            ActivatePartyRoleHandler handler,
            CancellationToken cancellationToken) =>
        {
            var result = await handler.ExecuteAsync(
                new ActivatePartyRoleCommand(
                    partyPublicId,
                    request.Role,
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
                $"/api/v1/parties/{partyPublicId:D}/roles/{result.Value!.Role}",
                result.Value);
        })
    .RequireAuthorization(PartyPermissions.RoleManage)
    .WithName("ActivatePartyRole")
    .WithSummary("Activates one CUSTOMER or SUPPLIER role on an existing company-scoped Party.");

app.MapPost(
        "/api/v1/parties/{partyPublicId:guid}/roles/{role}/state",
        async (
            Guid partyPublicId,
            string role,
            ChangePartyRoleStateRequest request,
            HttpRequest httpRequest,
            IExecutionContext executionContext,
            ChangePartyRoleStateHandler handler,
            CancellationToken cancellationToken) =>
        {
            var result = await handler.ExecuteAsync(
                new ChangePartyRoleStateCommand(
                    partyPublicId,
                    role,
                    request.State,
                    request.Version,
                    request.Reason,
                    httpRequest.Headers["Idempotency-Key"].ToString()),
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
    .RequireAuthorization(PartyPermissions.RoleManage)
    .WithName("ChangePartyRoleState")
    .WithSummary("Changes an existing CUSTOMER or SUPPLIER Party role between ACTIVE and INACTIVE.");

app.MapPost(
        "/api/v1/parties/{partyPublicId:guid}/tax-identities",
        async (
            Guid partyPublicId,
            AddPartyTaxIdentityRequest request,
            HttpRequest httpRequest,
            IExecutionContext executionContext,
            AddPartyTaxIdentityHandler handler,
            CancellationToken cancellationToken) =>
        {
            var result = await handler.ExecuteAsync(
                new AddPartyTaxIdentityCommand(
                    partyPublicId,
                    request.Jurisdiction,
                    request.Scheme,
                    request.Value,
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
                $"/api/v1/parties/{partyPublicId:D}/tax-identities/{result.Value!.PublicId:D}",
                result.Value);
        })
    .RequireAuthorization(PartyPermissions.TaxIdentityManage)
    .WithName("AddPartyTaxIdentity")
    .WithSummary("Adds one ACTIVE Turkish VKN or TCKN identity to an existing company-scoped Party.");

app.MapGet(
        "/api/v1/parties",
        async (
            string? search,
            IExecutionContext executionContext,
            PartyMasterQueryHandler handler,
            CancellationToken cancellationToken) =>
        {
            var result = await handler.ListAsync(search, executionContext, cancellationToken);
            return result.IsFailure
                ? ApplicationErrorHttpMapper.ToResult(result.Error!, executionContext.CorrelationId.Value)
                : Results.Ok(result.Value);
        })
    .RequireAuthorization(PartyPermissions.Read)
    .WithName("ListParties")
    .WithSummary("Lists company-scoped Parties visible to the current actor.");

app.MapGet(
        "/api/v1/parties/{partyPublicId:guid}",
        async (
            Guid partyPublicId,
            IExecutionContext executionContext,
            PartyMasterQueryHandler handler,
            CancellationToken cancellationToken) =>
        {
            var result = await handler.GetAsync(
                partyPublicId,
                executionContext,
                cancellationToken);
            return result.IsFailure
                ? ApplicationErrorHttpMapper.ToResult(result.Error!, executionContext.CorrelationId.Value)
                : Results.Ok(result.Value);
        })
    .RequireAuthorization(PartyPermissions.Read)
    .WithName("GetParty")
    .WithSummary("Returns one company-scoped Party master detail with child sections filtered by permission.");

app.MapPut(
        "/api/v1/parties/{partyPublicId:guid}",
        async (
            Guid partyPublicId,
            EditPartyIdentityRequest request,
            HttpRequest httpRequest,
            IExecutionContext executionContext,
            PartyMasterCommandHandler handler,
            CancellationToken cancellationToken) =>
        {
            var result = await handler.EditIdentityAsync(
                new EditPartyIdentityCommand(
                    partyPublicId,
                    request.Version,
                    request.LegalName,
                    request.DisplayName,
                    httpRequest.Headers["Idempotency-Key"].ToString()),
                executionContext,
                cancellationToken);

            return result.IsFailure
                ? ApplicationErrorHttpMapper.ToResult(result.Error!, executionContext.CorrelationId.Value)
                : Results.Ok(result.Value);
        })
    .RequireAuthorization(PartyPermissions.Edit)
    .WithName("EditPartyIdentity")
    .WithSummary("Edits the live legal/display identity of an existing Party using optimistic concurrency.");

app.MapPost(
        "/api/v1/parties/{partyPublicId:guid}/contacts",
        async (
            Guid partyPublicId,
            CreatePartyContactRequest request,
            HttpRequest httpRequest,
            IExecutionContext executionContext,
            PartyMasterCommandHandler handler,
            CancellationToken cancellationToken) =>
        {
            var result = await handler.CreateContactAsync(
                new CreatePartyContactCommand(
                    partyPublicId,
                    request.Name,
                    request.Title,
                    request.Purpose,
                    httpRequest.Headers["Idempotency-Key"].ToString()),
                executionContext,
                cancellationToken);

            return result.IsFailure
                ? ApplicationErrorHttpMapper.ToResult(result.Error!, executionContext.CorrelationId.Value)
                : Results.Created(
                    $"/api/v1/parties/{partyPublicId:D}/contacts/{result.Value!.EntityPublicId:D}",
                    result.Value);
        })
    .RequireAuthorization(PartyPermissions.ContactManage)
    .WithName("CreatePartyContact")
    .WithSummary("Adds an ACTIVE Contact Person to a Party.");

app.MapPost(
        "/api/v1/parties/{partyPublicId:guid}/contacts/{contactPublicId:guid}/state",
        async (
            Guid partyPublicId,
            Guid contactPublicId,
            ChangePartyMasterStateRequest request,
            HttpRequest httpRequest,
            IExecutionContext executionContext,
            PartyMasterCommandHandler handler,
            CancellationToken cancellationToken) =>
        {
            var result = await handler.ChangeContactStateAsync(
                new ChangePartyContactStateCommand(
                    partyPublicId,
                    contactPublicId,
                    request.Version,
                    request.State,
                    httpRequest.Headers["Idempotency-Key"].ToString()),
                executionContext,
                cancellationToken);

            return result.IsFailure
                ? ApplicationErrorHttpMapper.ToResult(result.Error!, executionContext.CorrelationId.Value)
                : Results.Ok(result.Value);
        })
    .RequireAuthorization(PartyPermissions.ContactManage)
    .WithName("ChangePartyContactState")
    .WithSummary("Changes a Party Contact Person between ACTIVE and INACTIVE.");

app.MapPost(
        "/api/v1/parties/{partyPublicId:guid}/contacts/{contactPublicId:guid}/communications",
        async (
            Guid partyPublicId,
            Guid contactPublicId,
            CreatePartyCommunicationPointRequest request,
            HttpRequest httpRequest,
            IExecutionContext executionContext,
            PartyMasterCommandHandler handler,
            CancellationToken cancellationToken) =>
        {
            var result = await handler.CreateCommunicationPointAsync(
                new CreatePartyCommunicationPointCommand(
                    partyPublicId,
                    contactPublicId,
                    request.Type,
                    request.Value,
                    request.Purpose,
                    request.IsPrimary,
                    httpRequest.Headers["Idempotency-Key"].ToString()),
                executionContext,
                cancellationToken);

            return result.IsFailure
                ? ApplicationErrorHttpMapper.ToResult(result.Error!, executionContext.CorrelationId.Value)
                : Results.Created(
                    $"/api/v1/parties/{partyPublicId:D}/contacts/{contactPublicId:D}/communications/{result.Value!.EntityPublicId:D}",
                    result.Value);
        })
    .RequireAuthorization(PartyPermissions.ContactManage)
    .WithName("CreatePartyCommunicationPoint")
    .WithSummary("Adds an ACTIVE communication point to a Party contact.");

app.MapPost(
        "/api/v1/parties/{partyPublicId:guid}/contacts/{contactPublicId:guid}/communications/{communicationPublicId:guid}/state",
        async (
            Guid partyPublicId,
            Guid contactPublicId,
            Guid communicationPublicId,
            ChangePartyMasterStateRequest request,
            HttpRequest httpRequest,
            IExecutionContext executionContext,
            PartyMasterCommandHandler handler,
            CancellationToken cancellationToken) =>
        {
            var result = await handler.ChangeCommunicationPointStateAsync(
                new ChangePartyCommunicationPointStateCommand(
                    partyPublicId,
                    contactPublicId,
                    communicationPublicId,
                    request.Version,
                    request.State,
                    httpRequest.Headers["Idempotency-Key"].ToString()),
                executionContext,
                cancellationToken);

            return result.IsFailure
                ? ApplicationErrorHttpMapper.ToResult(result.Error!, executionContext.CorrelationId.Value)
                : Results.Ok(result.Value);
        })
    .RequireAuthorization(PartyPermissions.ContactManage)
    .WithName("ChangePartyCommunicationPointState")
    .WithSummary("Changes a Party communication point between ACTIVE and INACTIVE.");

app.MapPost(
        "/api/v1/parties/{partyPublicId:guid}/addresses",
        async (
            Guid partyPublicId,
            CreatePartyAddressRequest request,
            HttpRequest httpRequest,
            IExecutionContext executionContext,
            PartyMasterCommandHandler handler,
            CancellationToken cancellationToken) =>
        {
            var result = await handler.CreateAddressAsync(
                new CreatePartyAddressCommand(
                    partyPublicId,
                    request.Purpose,
                    request.Country,
                    request.City,
                    request.District,
                    request.PostalCode,
                    request.Line1,
                    request.Line2,
                    request.Label,
                    request.IsDefault,
                    httpRequest.Headers["Idempotency-Key"].ToString()),
                executionContext,
                cancellationToken);

            return result.IsFailure
                ? ApplicationErrorHttpMapper.ToResult(result.Error!, executionContext.CorrelationId.Value)
                : Results.Created(
                    $"/api/v1/parties/{partyPublicId:D}/addresses/{result.Value!.EntityPublicId:D}",
                    result.Value);
        })
    .RequireAuthorization(PartyPermissions.AddressManage)
    .WithName("CreatePartyAddress")
    .WithSummary("Adds an ACTIVE structured address to a Party.");

app.MapPut(
        "/api/v1/parties/{partyPublicId:guid}/addresses/{addressPublicId:guid}",
        async (
            Guid partyPublicId,
            Guid addressPublicId,
            UpdatePartyAddressRequest request,
            HttpRequest httpRequest,
            IExecutionContext executionContext,
            PartyMasterCommandHandler handler,
            CancellationToken cancellationToken) =>
        {
            var result = await handler.UpdateAddressAsync(
                new UpdatePartyAddressCommand(
                    partyPublicId,
                    addressPublicId,
                    request.Version,
                    request.Purpose,
                    request.Country,
                    request.City,
                    request.District,
                    request.PostalCode,
                    request.Line1,
                    request.Line2,
                    request.Label,
                    request.IsDefault,
                    httpRequest.Headers["Idempotency-Key"].ToString()),
                executionContext,
                cancellationToken);

            return result.IsFailure
                ? ApplicationErrorHttpMapper.ToResult(result.Error!, executionContext.CorrelationId.Value)
                : Results.Ok(result.Value);
        })
    .RequireAuthorization(PartyPermissions.AddressManage)
    .WithName("UpdatePartyAddress")
    .WithSummary("Edits a Party address for future use without rewriting historical document snapshots.");

app.MapPost(
        "/api/v1/parties/{partyPublicId:guid}/addresses/{addressPublicId:guid}/state",
        async (
            Guid partyPublicId,
            Guid addressPublicId,
            ChangePartyMasterStateRequest request,
            HttpRequest httpRequest,
            IExecutionContext executionContext,
            PartyMasterCommandHandler handler,
            CancellationToken cancellationToken) =>
        {
            var result = await handler.ChangeAddressStateAsync(
                new ChangePartyAddressStateCommand(
                    partyPublicId,
                    addressPublicId,
                    request.Version,
                    request.State,
                    httpRequest.Headers["Idempotency-Key"].ToString()),
                executionContext,
                cancellationToken);

            return result.IsFailure
                ? ApplicationErrorHttpMapper.ToResult(result.Error!, executionContext.CorrelationId.Value)
                : Results.Ok(result.Value);
        })
    .RequireAuthorization(PartyPermissions.AddressManage)
    .WithName("ChangePartyAddressState")
    .WithSummary("Changes a Party address between ACTIVE and INACTIVE.");

app.MapPost(
        "/api/v1/parties/{partyPublicId:guid}/tax-identities/{taxIdentityPublicId:guid}/state",
        async (
            Guid partyPublicId,
            Guid taxIdentityPublicId,
            ChangePartyMasterStateRequest request,
            HttpRequest httpRequest,
            IExecutionContext executionContext,
            PartyMasterCommandHandler handler,
            CancellationToken cancellationToken) =>
        {
            var result = await handler.ChangeTaxIdentityStateAsync(
                new ChangePartyTaxIdentityStateCommand(
                    partyPublicId,
                    taxIdentityPublicId,
                    request.Version,
                    request.State,
                    httpRequest.Headers["Idempotency-Key"].ToString()),
                executionContext,
                cancellationToken);

            return result.IsFailure
                ? ApplicationErrorHttpMapper.ToResult(result.Error!, executionContext.CorrelationId.Value)
                : Results.Ok(result.Value);
        })
    .RequireAuthorization(PartyPermissions.TaxIdentityManage)
    .WithName("ChangePartyTaxIdentityState")
    .WithSummary("Changes an existing TR VKN/TCKN Tax Identity between ACTIVE and INACTIVE.");

app.MapPost(
        "/api/v1/parties/{partyPublicId:guid}/external-mappings",
        async (
            Guid partyPublicId,
            CreatePartyExternalMappingRequest request,
            HttpRequest httpRequest,
            IExecutionContext executionContext,
            PartyMasterCommandHandler handler,
            CancellationToken cancellationToken) =>
        {
            var result = await handler.CreateExternalMappingAsync(
                new CreatePartyExternalMappingCommand(
                    partyPublicId,
                    request.SystemCode,
                    request.AccountScope,
                    request.ExternalIdentity,
                    httpRequest.Headers["Idempotency-Key"].ToString()),
                executionContext,
                cancellationToken);

            return result.IsFailure
                ? ApplicationErrorHttpMapper.ToResult(result.Error!, executionContext.CorrelationId.Value)
                : Results.Created(
                    $"/api/v1/parties/{partyPublicId:D}/external-mappings/{result.Value!.EntityPublicId:D}",
                    result.Value);
        })
    .RequireAuthorization(PartyPermissions.ExternalMappingManage)
    .WithName("CreatePartyExternalMapping")
    .WithSummary("Adds a provider/system identity mapping without changing canonical Party identity.");

app.MapPost(
        "/api/v1/parties/{partyPublicId:guid}/external-mappings/{mappingPublicId:guid}/state",
        async (
            Guid partyPublicId,
            Guid mappingPublicId,
            ChangePartyMasterStateRequest request,
            HttpRequest httpRequest,
            IExecutionContext executionContext,
            PartyMasterCommandHandler handler,
            CancellationToken cancellationToken) =>
        {
            var result = await handler.ChangeExternalMappingStateAsync(
                new ChangePartyExternalMappingStateCommand(
                    partyPublicId,
                    mappingPublicId,
                    request.Version,
                    request.State,
                    httpRequest.Headers["Idempotency-Key"].ToString()),
                executionContext,
                cancellationToken);

            return result.IsFailure
                ? ApplicationErrorHttpMapper.ToResult(result.Error!, executionContext.CorrelationId.Value)
                : Results.Ok(result.Value);
        })
    .RequireAuthorization(PartyPermissions.ExternalMappingManage)
    .WithName("ChangePartyExternalMappingState")
    .WithSummary("Changes an External Mapping between ACTIVE and INACTIVE.");

app.MapPost(
        "/api/v1/parties/{sourcePartyPublicId:guid}/merge",
        async (
            Guid sourcePartyPublicId,
            MergePartyRequest request,
            HttpRequest httpRequest,
            IExecutionContext executionContext,
            PartyMasterCommandHandler handler,
            CancellationToken cancellationToken) =>
        {
            var result = await handler.MergeAsync(
                new MergePartyCommand(
                    sourcePartyPublicId,
                    request.SurvivorPartyPublicId,
                    request.SourceVersion,
                    request.SurvivorVersion,
                    request.UseSourceIdentity,
                    request.MoveSourceRoles,
                    request.MoveSourceContacts,
                    request.MoveSourceAddresses,
                    request.MoveSourceTaxIdentities,
                    request.MoveSourceExternalMappings,
                    request.Reason,
                    httpRequest.Headers["Idempotency-Key"].ToString()),
                executionContext,
                cancellationToken);

            return result.IsFailure
                ? ApplicationErrorHttpMapper.ToResult(result.Error!, executionContext.CorrelationId.Value)
                : Results.Ok(result.Value);
        })
    .RequireAuthorization(PartyPermissions.Merge)
    .WithName("MergeParty")
    .WithSummary("Logically merges an explicitly selected same-company source Party into a survivor Party.");

app.MapProductEndpoints();
app.MapInventoryEndpoints();
app.MapSalesEndpoints();
app.MapSalesProformaEndpoints();

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
