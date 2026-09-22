namespace Mars.Application.Foundation.Auditing;

public interface IAuditWriter
{
    void Append(AuditEntry entry);
}
