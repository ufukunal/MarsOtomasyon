using System.Runtime.InteropServices;

namespace MarsScannerAgent;

public sealed record ScannerDevice(string Id, string Name);
public sealed record ScanCommand(string DeviceId, int Dpi = 300);
public sealed record ScanImage(byte[] Bytes, string FileName);

/// <summary>
/// The WIA Automation COM bridge must be accessed from an STA thread.
/// Device enumeration and transfer use no WIA dialogs or interactive windows.
/// </summary>
internal sealed class WiaScanner
{
    private readonly SemaphoreSlim _busy = new(1, 1);

    public Task<IReadOnlyList<ScannerDevice>> ListAsync() =>
        Exclusive(() =>
        {
            dynamic? manager = null;
            try
            {
                manager = CreateManager();
                dynamic infos = manager.DeviceInfos;
                var count = (int)infos.Count;
                var found = new List<ScannerDevice>();
                for (var i = 1; i <= count; i++)
                {
                    dynamic info = infos.Item(i);
                    try
                    {
                        // WIA DeviceType.ScannerDeviceType = 1.
                        if (Convert.ToInt32(info.Type) != 1) continue;
                        var name = Convert.ToString(info.Properties.Item("Name").Value) ?? "WIA Scanner";
                        var id = Convert.ToString(info.DeviceID) ?? "";
                        if (!string.IsNullOrWhiteSpace(id))
                            found.Add(new ScannerDevice(id, name));
                    }
                    finally { Release(info); }
                }
                return (IReadOnlyList<ScannerDevice>)found;
            }
            finally { Release(manager); }
        });

    public Task<ScanImage> ScanAsync(ScanCommand command) =>
        Exclusive(() =>
        {
            if (command is null || string.IsNullOrWhiteSpace(command.DeviceId) ||
                command.DeviceId.Length > 1024 || command.Dpi is not (150 or 300 or 600))
                throw new ArgumentException("Cihaz ve çözünürlük (150, 300 veya 600 DPI) seçin.");

            dynamic? manager = null;
            dynamic? device = null;
            dynamic? item = null;
            dynamic? image = null;
            string? tmp = null;

            try
            {
                manager = CreateManager();
                dynamic infos = manager.DeviceInfos;
                dynamic? matchingInfo = null;
                for (var i = 1; i <= (int)infos.Count; i++)
                {
                    dynamic info = infos.Item(i);
                    if (Convert.ToInt32(info.Type) == 1 &&
                        StringComparer.Ordinal.Equals((string)info.DeviceID, command.DeviceId))
                    {
                        matchingInfo = info;
                        break;
                    }
                    Release(info);
                }
                if (matchingInfo is null)
                    throw new ArgumentException("Seçilen tarayıcı Windows WIA listesinde bulunamadı.");

                try { device = matchingInfo.Connect(); }
                finally { Release(matchingInfo); }

                item = device.Items.Item(1);
                // WIA_IPS_XRES / YRES; driver capabilities may reject a DPI.
                // A driver error is surfaced rather than silently ignoring the choice.
                item.Properties.Item("6147").Value = command.Dpi;
                item.Properties.Item("6148").Value = command.Dpi;

                const string jpegFormat = "{B96B3CAE-0728-11D3-9D7B-0000F81EF32E}";
                image = item.Transfer(jpegFormat);
                tmp = Path.Combine(Path.GetTempPath(), "mars-scan-" + Guid.NewGuid().ToString("N") + ".jpg");
                image.SaveFile(tmp);
                var bytes = File.ReadAllBytes(tmp);
                if (bytes.Length < 4 || bytes[0] != 0xff || bytes[1] != 0xd8)
                    throw new IOException("Tarayıcı JPEG biçiminde geçerli görüntü döndürmedi.");
                if (bytes.Length > 25 * 1024 * 1024)
                    throw new IOException("Tarama 25 MB sınırını aştı.");

                return new ScanImage(bytes, "mars-scan-" + DateTime.UtcNow.ToString("yyyyMMdd-HHmmss") + ".jpg");
            }
            finally
            {
                if (tmp is not null && File.Exists(tmp)) File.Delete(tmp);
                Release(image);
                Release(item);
                Release(device);
                Release(manager);
            }
        });

    private async Task<T> Exclusive<T>(Func<T> action)
    {
        if (!_busy.Wait(0))
            throw new ScannerBusyException();

        try
        {
            // Do not cancel/dispose this task on client disconnection. WIA COM
            // transfer cannot be reliably aborted, and the scan lock must remain held.
            return await OnStaThread(action).ConfigureAwait(false);
        }
        finally { _busy.Release(); }
    }

    private static Task<T> OnStaThread<T>(Func<T> action)
    {
        var pending = new TaskCompletionSource<T>(TaskCreationOptions.RunContinuationsAsynchronously);
        var thread = new Thread(() =>
        {
            try { pending.TrySetResult(action()); }
            catch (Exception ex) { pending.TrySetException(ex); }
        }) { IsBackground = true, Name = "Mars Scanner WIA STA" };
        thread.SetApartmentState(ApartmentState.STA);
        thread.Start();
        return pending.Task;
    }

    private static dynamic CreateManager()
    {
        var type = Type.GetTypeFromProgID("WIA.DeviceManager", throwOnError: false);
        if (type is null)
            throw new NotSupportedException(
                "Windows WIA bileşeni bulunamadı. Epson Scan 2 ve WIA sürücüsünü yükleyin.");
        return Activator.CreateInstance(type)!;
    }

    private static void Release(object? com)
    {
        if (com is not null && Marshal.IsComObject(com))
            Marshal.FinalReleaseComObject(com);
    }
}

internal sealed class ScannerBusyException : Exception
{
    public ScannerBusyException() : base("Tarayıcı başka bir işlem yürütüyor.") { }
}
