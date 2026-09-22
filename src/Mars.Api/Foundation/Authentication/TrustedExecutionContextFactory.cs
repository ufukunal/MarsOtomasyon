using System.Security.Claims;
using Mars.Application.Foundation.Context;
using OpenIddict.Abstractions;
using MarsExecutionContext = Mars.Application.Foundation.Context.ExecutionContext;

namespace Mars.Api.Foundation.Authentication;

public static class TrustedExecutionContextFactory
{
    public static IExecutionContext Create(
        ClaimsPrincipal principal,
        CorrelationId correlationId)
    {
        ArgumentNullException.ThrowIfNull(principal);

        if (principal.Identity?.IsAuthenticated != true)
        {
            throw new TrustedExecutionContextException(
                "authentication.required",
                "An authenticated principal is required.");
        }

        var subject = SingleOptionalClaim(
            principal,
            OpenIddictConstants.Claims.Subject,
            "authentication.actor_ambiguous",
            "The authenticated principal contains an ambiguous actor identity.");

        var nameIdentifier = SingleOptionalClaim(
            principal,
            ClaimTypes.NameIdentifier,
            "authentication.actor_ambiguous",
            "The authenticated principal contains an ambiguous actor identity.");

        var actorValue = subject ?? nameIdentifier;

        var actorId = ParseRequiredUuid(
            actorValue,
            "authentication.actor_invalid",
            "The authenticated principal does not contain a valid actor identity.");

        var companyId = ParseRequiredUuid(
            SingleRequiredClaim(
                principal,
                MarsAuthenticationDefaults.CompanyIdClaim,
                "authorization.company_scope_invalid",
                "The authenticated principal must contain exactly one company scope."),
            "authorization.company_scope_invalid",
            "The authenticated principal does not contain a valid company scope.");

        Guid? branchId = null;
        var branchValue = SingleOptionalClaim(
            principal,
            MarsAuthenticationDefaults.BranchIdClaim,
            "authorization.branch_scope_invalid",
            "The authenticated principal contains an ambiguous branch scope.");

        if (!string.IsNullOrWhiteSpace(branchValue))
        {
            branchId = ParseRequiredUuid(
                branchValue,
                "authorization.branch_scope_invalid",
                "The authenticated principal contains an invalid branch scope.");
        }

        return new MarsExecutionContext(actorId, companyId, branchId, correlationId);
    }

    private static string SingleRequiredClaim(
        ClaimsPrincipal principal,
        string claimType,
        string code,
        string message)
    {
        var values = principal.FindAll(claimType).Select(claim => claim.Value).ToArray();

        if (values.Length != 1 || string.IsNullOrWhiteSpace(values[0]))
        {
            throw new TrustedExecutionContextException(code, message);
        }

        return values[0];
    }

    private static string? SingleOptionalClaim(
        ClaimsPrincipal principal,
        string claimType,
        string code,
        string message)
    {
        var values = principal.FindAll(claimType).Select(claim => claim.Value).ToArray();

        if (values.Length > 1)
        {
            throw new TrustedExecutionContextException(code, message);
        }

        return values.Length == 0 ? null : values[0];
    }

    private static Guid ParseRequiredUuid(
        string? value,
        string code,
        string message)
    {
        if (!Guid.TryParse(value, out var parsed) || parsed == Guid.Empty)
        {
            throw new TrustedExecutionContextException(code, message);
        }

        return parsed;
    }
}

public sealed class TrustedExecutionContextException(
    string code,
    string safeMessage) : Exception(safeMessage)
{
    public string Code { get; } = code;
}
