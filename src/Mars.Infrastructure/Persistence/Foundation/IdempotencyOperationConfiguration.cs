using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Mars.Infrastructure.Persistence.Foundation;

internal sealed class IdempotencyOperationConfiguration : IEntityTypeConfiguration<IdempotencyOperationRecord>
{
    public void Configure(EntityTypeBuilder<IdempotencyOperationRecord> builder)
    {
        builder.ToTable("idempotency_operations", "foundation");
        builder.HasKey(x => x.Id).HasName("pk_idempotency_operations");

        builder.Property(x => x.Id).HasColumnName("id").UseIdentityByDefaultColumn();
        builder.Property(x => x.Scope).HasColumnName("scope").HasMaxLength(200).IsRequired();
        builder.Property(x => x.OperationKey).HasColumnName("operation_key").HasMaxLength(200).IsRequired();
        builder.Property(x => x.RequestFingerprint).HasColumnName("request_fingerprint").HasMaxLength(128);
        builder.Property(x => x.Status).HasColumnName("status").HasConversion<string>().HasMaxLength(32).IsRequired();
        builder.Property(x => x.ResultCode).HasColumnName("result_code").HasMaxLength(200);
        builder.Property(x => x.CreatedAt).HasColumnName("created_at");
        builder.Property(x => x.CompletedAt).HasColumnName("completed_at");

        builder.HasIndex(x => new { x.Scope, x.OperationKey })
            .IsUnique()
            .HasDatabaseName("ux_idempotency_scope_key");

        builder.ToTable(table => table.HasCheckConstraint(
            "ck_idempotency_completed_state",
            "completed_at IS NULL OR status IN ('Succeeded', 'Failed')"));
    }
}
