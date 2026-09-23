using Mars.Api.Foundation.Context;
using Mars.Application.Foundation.Authorization;
using Microsoft.AspNetCore.Authorization;

namespace Mars.Api.Foundation.Authorization;

public sealed class MarsPermissionRequirement(string permissionCode) : IAuthorizationRequirement
{
    public string PermissionCode { get; } =
        string.IsNullOrWhiteSpace(permissionCode)
            ? throw new ArgumentException("Permission code is required.", nameof(permissionCode))
            : permissionCode;
}

public sealed class MarsPermissionAuthorizationHandler(
    IHttpContextAccessor httpContextAccessor,
    IPermissionEvaluator permissionEvaluator)
    : AuthorizationHandler<MarsPermissionRequirement>
{
    protected override async Task HandleRequirementAsync(
        AuthorizationHandlerContext context,
        MarsPermissionRequirement requirement)
    {
        if (context.User.Identity?.IsAuthenticated != true)
        {
            return;
        }

        var httpContext = httpContextAccessor.HttpContext;
        if (httpContext is null ||
            !httpContext.Items.TryGetValue(HttpExecutionContextAccessor.ItemKey, out var value) ||
            value is not Mars.Application.Foundation.Context.IExecutionContext executionContext)
        {
            return;
        }

        if (await permissionEvaluator.IsGrantedAsync(
                executionContext.ActorId,
                executionContext.CompanyId,
                requirement.PermissionCode,
                httpContext.RequestAborted))
        {
            context.Succeed(requirement);
        }
    }
}
