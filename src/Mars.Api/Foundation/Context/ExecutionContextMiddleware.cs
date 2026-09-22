using Mars.Api.Foundation.Authentication;

namespace Mars.Api.Foundation.Context;

public sealed class ExecutionContextMiddleware(RequestDelegate next)
{
    public async Task InvokeAsync(HttpContext context)
    {
        if (context.User.Identity?.IsAuthenticated == true)
        {
            var correlationId = CorrelationIdMiddleware.GetRequired(context);
            var executionContext = TrustedExecutionContextFactory.Create(
                context.User,
                correlationId);

            context.Items[HttpExecutionContextAccessor.ItemKey] = executionContext;
        }

        await next(context);
    }
}
