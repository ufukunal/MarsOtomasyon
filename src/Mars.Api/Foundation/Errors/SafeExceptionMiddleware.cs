using Mars.Api.Foundation.Authentication;
using Mars.Api.Foundation.Context;

namespace Mars.Api.Foundation.Errors;

public sealed class SafeExceptionMiddleware(
    RequestDelegate next,
    ILogger<SafeExceptionMiddleware> logger)
{
    public async Task InvokeAsync(HttpContext context)
    {
        try
        {
            await next(context);
        }
        catch (TrustedExecutionContextException exception)
        {
            if (context.Response.HasStarted)
            {
                throw;
            }

            logger.LogWarning(
                "Trusted execution context rejected request with code {ErrorCode} and correlation {CorrelationId}.",
                exception.Code,
                TryGetCorrelationId(context));

            context.Response.StatusCode = StatusCodes.Status403Forbidden;
            context.Response.ContentType = "application/json";

            var correlationId = TryGetCorrelationId(context);
            await context.Response.WriteAsJsonAsync(
                new ApiErrorResponse(
                    exception.Code,
                    exception.Message,
                    "Authorization",
                    correlationId),
                context.RequestAborted);
        }
        catch (Exception exception)
        {
            if (context.Response.HasStarted)
            {
                throw;
            }

            logger.LogError(
                exception,
                "Unhandled API exception for correlation {CorrelationId}.",
                TryGetCorrelationId(context));

            context.Response.StatusCode = StatusCodes.Status500InternalServerError;
            context.Response.ContentType = "application/json";

            var correlationId = TryGetCorrelationId(context);
            await context.Response.WriteAsJsonAsync(
                new ApiErrorResponse(
                    "infrastructure.unhandled",
                    "The request could not be completed.",
                    "Infrastructure",
                    correlationId),
                context.RequestAborted);
        }
    }

    private static string TryGetCorrelationId(HttpContext context) =>
        context.Items.TryGetValue(CorrelationIdMiddleware.ItemKey, out var value)
            ? value?.ToString() ?? string.Empty
            : string.Empty;
}
