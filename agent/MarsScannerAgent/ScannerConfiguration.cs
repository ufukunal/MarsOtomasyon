using System.Text.Json;

namespace MarsScannerAgent;

internal sealed class ScannerConfiguration
{
    public int Port { get; set; } = 17864;
    public string Token { get; set; } = "";
    public string[] AllowedOrigins { get; set; } = [];

    public static ScannerConfiguration Load(string[] args)
    {
        var index = Array.IndexOf(args, "--config");
        var file = index >= 0 && index + 1 < args.Length
            ? args[index + 1]
            : Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData),
                "MarsProject", "ScannerAgent", "agent.json");

        if (!File.Exists(file))
        {
            throw new InvalidOperationException(
                "Agent yapılandırması bulunamadı. Yönetici olarak agent/install.ps1 çalıştırın: " + file);
        }

        var config = JsonSerializer.Deserialize<ScannerConfiguration>(
            File.ReadAllText(file),
            new JsonSerializerOptions { PropertyNameCaseInsensitive = true })
            ?? throw new InvalidOperationException("Agent yapılandırması okunamadı.");

        if (config.Port is < 1024 or > 65535)
            throw new InvalidOperationException("Geçersiz yerel port.");
        if (string.IsNullOrWhiteSpace(config.Token) || config.Token.Length < 32)
            throw new InvalidOperationException("En az 32 karakterlik güvenli erişim anahtarı gerekli.");

        if (config.AllowedOrigins is null)
            throw new InvalidOperationException("İzin verilen kaynaklar listesi eksik.");

        foreach (var origin in config.AllowedOrigins)
        {
            if (!Uri.TryCreate(origin, UriKind.Absolute, out var uri) ||
                !string.IsNullOrEmpty(uri.AbsolutePath.Trim('/')) ||
                !string.IsNullOrEmpty(uri.Query) ||
                !string.IsNullOrEmpty(uri.Fragment) ||
                !(uri.Scheme == Uri.UriSchemeHttps || (uri.Scheme == Uri.UriSchemeHttp && uri.IsLoopback)))
                throw new InvalidOperationException("İzin verilen kaynak HTTPS ya da yerel adres olmalıdır: " + origin);
        }

        return config;
    }

    public HashSet<string> TrustedOrigins() =>
        new(AllowedOrigins.Concat([
            $"http://127.0.0.1:{Port}",
            $"http://localhost:{Port}"
        ]), StringComparer.Ordinal);
}
