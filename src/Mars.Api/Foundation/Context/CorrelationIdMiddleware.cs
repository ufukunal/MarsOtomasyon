using Mars.Application.Foundation.Context;

namespace Mars.Api.Foundation.Context;

public sealed class CorrelationIdMiddleware(RequestDelegate next)
{
    public const string HeaderName = "X-Correlation-ID";
    public const string ItemKey = "Mars.CorrelationId";
    private const int MaxLength = 128;

    public async Task InvokeAsync(HttpContext context)
    {
        var supplied = context.Request.Headers[HeaderName].ToString().Trim();

        var value = !string.IsNullOrWhiteSpace(supplied) && supplied.Length <= MaxLength
            ? supplied
            : Guid.NewGuid().ToString("N");

        var correlationId = new CorrelationId(value);
        context.Items[ItemKey] = correlationId;
        context.Response.Headers[HeaderName] = correlationId.Value;

        await next(context);
    }

    public static CorrelationId GetRequired(HttpContext context) =>
        context.Items.TryGetValue(ItemKey, out var value) && value is CorrelationId correlationId
            ? correlationId
            : throw new InvalidOperationException("Correlation middleware has not established a correlation id.");
}
