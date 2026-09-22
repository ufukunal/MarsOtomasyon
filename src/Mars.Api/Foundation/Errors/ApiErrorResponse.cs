namespace Mars.Api.Foundation.Errors;

public sealed record ApiErrorResponse(
    string Code,
    string Message,
    string Category,
    string CorrelationId);
