using Mars.Application.Foundation.Context;

namespace Mars.Api.Foundation.Context;

public sealed class HttpExecutionContextAccessor(IHttpContextAccessor httpContextAccessor)
{
    public const string ItemKey = "Mars.ExecutionContext";

    public IExecutionContext GetRequired()
    {
        var httpContext = httpContextAccessor.HttpContext
            ?? throw new InvalidOperationException("No active HTTP context is available.");

        return httpContext.Items.TryGetValue(ItemKey, out var value) &&
               value is IExecutionContext executionContext
            ? executionContext
            : throw new InvalidOperationException(
                "No trusted Mars execution context is available for this request.");
    }
}
