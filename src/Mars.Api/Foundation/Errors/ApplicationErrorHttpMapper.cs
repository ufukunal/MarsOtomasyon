using Mars.Application.Foundation.Results;

namespace Mars.Api.Foundation.Errors;

public static class ApplicationErrorHttpMapper
{
    public static int GetStatusCode(ErrorCategory category) =>
        category switch
        {
            ErrorCategory.Validation => StatusCodes.Status400BadRequest,
            ErrorCategory.Authentication => StatusCodes.Status401Unauthorized,
            ErrorCategory.Authorization => StatusCodes.Status403Forbidden,
            ErrorCategory.NotFound => StatusCodes.Status404NotFound,
            ErrorCategory.Conflict => StatusCodes.Status409Conflict,
            ErrorCategory.Concurrency => StatusCodes.Status409Conflict,
            ErrorCategory.BusinessRule => StatusCodes.Status422UnprocessableEntity,
            ErrorCategory.RateLimit => StatusCodes.Status429TooManyRequests,
            ErrorCategory.Infrastructure => StatusCodes.Status503ServiceUnavailable,
            ErrorCategory.ProviderFailure => StatusCodes.Status503ServiceUnavailable,
            _ => StatusCodes.Status500InternalServerError
        };

    public static IResult ToResult(
        ApplicationError error,
        string correlationId)
    {
        ArgumentNullException.ThrowIfNull(error);

        return Results.Json(
            new ApiErrorResponse(
                error.Code,
                error.Message,
                error.Category.ToString(),
                correlationId),
            statusCode: GetStatusCode(error.Category));
    }
}
