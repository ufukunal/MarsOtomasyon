using System.Runtime.InteropServices;
using System.Security.Cryptography;
using System.Text;
using MarsScannerAgent;

if (!OperatingSystem.IsWindows())
    throw new PlatformNotSupportedException("Mars Scanner Agent yalnızca Windows üzerinde çalışır.");

var config = ScannerConfiguration.Load(args);
var trustedOrigins = config.TrustedOrigins();

var builder = WebApplication.CreateBuilder(args);
builder.Services.AddWindowsService(options => options.ServiceName = "MarsScannerAgent");
builder.Services.AddSingleton<WiaScanner>();
builder.WebHost.UseUrls($"http://127.0.0.1:{config.Port}");
builder.WebHost.ConfigureKestrel(options =>
{
    options.Limits.MaxRequestBodySize = 4096;
    options.Limits.RequestHeadersTimeout = TimeSpan.FromSeconds(10);
    options.Limits.KeepAliveTimeout = TimeSpan.FromSeconds(30);
});

var app = builder.Build();

// No remote interfaces, no wildcard CORS, no public scanner access.
app.Use(async (context, next) =>
{
    context.Response.Headers["X-Content-Type-Options"] = "nosniff";
    context.Response.Headers["Cache-Control"] = "no-store";
    context.Response.Headers["Referrer-Policy"] = "no-referrer";
    context.Response.Headers["X-Frame-Options"] = "DENY";

    var origin = context.Request.Headers.Origin.ToString();
    if (!string.IsNullOrEmpty(origin))
    {
        if (!trustedOrigins.Contains(origin))
        {
            context.Response.StatusCode = StatusCodes.Status403Forbidden;
            return;
        }

        context.Response.Headers["Access-Control-Allow-Origin"] = origin;
        context.Response.Headers["Vary"] = "Origin";
        context.Response.Headers["Access-Control-Allow-Headers"] = "Authorization, Content-Type";
        context.Response.Headers["Access-Control-Allow-Methods"] = "GET, POST, OPTIONS";
    }

    if (HttpMethods.IsOptions(context.Request.Method))
    {
        context.Response.StatusCode = StatusCodes.Status204NoContent;
        return;
    }

    await next();
});

app.MapGet("/", () => Results.Content(LocalUi.Html, "text/html; charset=utf-8"));
app.MapGet("/health", () => Results.Ok(new { status = "ok", agent = "MarsScannerAgent", backend = "WIA" }));

app.MapGet("/api/scanners", async (HttpContext context, WiaScanner scanner) =>
{
    if (!Authorized(context, config.Token)) return Results.Unauthorized();
    try
    {
        var devices = await scanner.ListAsync();
        return Results.Ok(new { devices });
    }
    catch (ScannerBusyException)
    {
        return Results.Problem("Tarama işlemi sürüyor.", statusCode: 409);
    }
    catch (Exception error) when (error is COMException or NotSupportedException or InvalidOperationException)
    {
        app.Logger.LogWarning(error, "WIA scanner discovery failed");
        return Results.Problem("WIA tarayıcı listesi okunamadı. Epson WIA sürücüsünü kontrol edin.", statusCode: 503);
    }
});

app.MapPost("/api/scan", async (HttpContext context, WiaScanner scanner) =>
{
    if (!Authorized(context, config.Token)) return Results.Unauthorized();

    ScanCommand? command;
    try
    {
        command = await context.Request.ReadFromJsonAsync<ScanCommand>();
    }
    catch (Exception error) when (error is System.Text.Json.JsonException or BadHttpRequestException)
    {
        return Results.BadRequest(new { error = "Geçersiz tarama isteği." });
    }
    if (command is null || string.IsNullOrWhiteSpace(command.DeviceId) ||
        command.DeviceId.Length > 1024 || command.Dpi is not (150 or 300 or 600))
        return Results.BadRequest(new { error = "Tarayıcı seçin ve 150/300/600 DPI kullanın." });

    try
    {
        var result = await scanner.ScanAsync(command);
        return Results.File(result.Bytes, "image/jpeg", result.FileName);
    }
    catch (ScannerBusyException)
    {
        return Results.Problem("Tarayıcı meşgul. Geçerli taramanın bitmesini bekleyin.", statusCode: 409);
    }
    catch (ArgumentException error)
    {
        return Results.BadRequest(new { error = error.Message });
    }
    catch (Exception error) when (error is COMException or IOException or NotSupportedException or InvalidOperationException)
    {
        app.Logger.LogWarning(error, "WIA scan failed");
        return Results.Problem(
            "Tarama tamamlanamadı. Cihaz bağlantısını, WIA sürücüsünü ve Epson tarayıcı durumunu kontrol edin.",
            statusCode: 503);
    }
});

app.Run();

static bool Authorized(HttpContext context, string token)
{
    var authorization = context.Request.Headers.Authorization.ToString();
    if (!authorization.StartsWith("Bearer ", StringComparison.Ordinal))
        return false;

    var input = authorization["Bearer ".Length..].Trim();
    var actual = Encoding.UTF8.GetBytes(input);
    var expected = Encoding.UTF8.GetBytes(token);
    return actual.Length == expected.Length &&
        CryptographicOperations.FixedTimeEquals(actual, expected);
}
