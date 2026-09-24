using Mars.Infrastructure.Persistence.Parties;
using Mars.Infrastructure.Persistence.Products;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Mars.Infrastructure.Persistence.Finance;

internal static class FinanceModelConfiguration
{
    public static void Configure(ModelBuilder m)
    {
        Transaction(m);
        AccountLedger(m);
        CashAccount(m);
        CashLedger(m);
        BankAccount(m);
        BankLedger(m);
        Period(m);
        ValuationPool(m);
        ValuationEntry(m);
        BridgeEntry(m);
        BridgeConsumption(m);
        StatementBatch(m);
        StatementLine(m);
        Reconciliation(m);
        Risk(m);
        CashCount(m);
    }

    private static void Transaction(ModelBuilder m)
    {
        var b=m.Entity<FinanceTransactionRecord>();
        b.ToTable("transactions","finance"); Id(b); Public(b); Company(b);
        Enum(b.Property(x=>x.Kind).HasColumnName("kind"),40);
        Enum(b.Property(x=>x.State).HasColumnName("state"),16);
        Currency(b.Property(x=>x.CurrencyCode).HasColumnName("currency_code"));
        Currency(b.Property(x=>x.BaseCurrencyCode).HasColumnName("base_currency_code"));
        Money(b.Property(x=>x.Amount).HasColumnName("amount"));
        Money(b.Property(x=>x.BaseAmount).HasColumnName("base_amount"));
        b.Property(x=>x.PartyId).HasColumnName("party_id");
        Enum(b.Property(x=>x.PartyRole).HasColumnName("party_role"),16);
        b.Property(x=>x.DocumentDate).HasColumnName("document_date");
        b.Property(x=>x.PostingDate).HasColumnName("posting_date");
        b.Property(x=>x.SourceModule).HasColumnName("source_module").HasMaxLength(64);
        b.Property(x=>x.SourceEntityType).HasColumnName("source_entity_type").HasMaxLength(64);
        b.Property(x=>x.SourceDocumentPublicId).HasColumnName("source_document_public_id");
        b.Property(x=>x.ReversalOfTransactionId).HasColumnName("reversal_of_transaction_id");
        b.Property(x=>x.Reason).HasColumnName("reason").HasMaxLength(1024);
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x=>x.PostedAt).HasColumnName("posted_at");
        b.Property(x=>x.ReversedAt).HasColumnName("reversed_at");
        Version(b.Property(x=>x.Version).HasColumnName("version"));
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId}).HasName("ak_finance_transactions_id_company");
        b.HasIndex(x=>new{x.CompanyId,x.PostingDate,x.Id}).HasDatabaseName("ix_finance_transactions_posting");
        b.HasIndex(x=>new{x.CompanyId,x.SourceModule,x.SourceEntityType,x.SourceDocumentPublicId,x.Kind})
            .HasDatabaseName("ix_finance_transactions_source");
        b.HasIndex(x=>x.ReversalOfTransactionId).IsUnique().HasFilter("reversal_of_transaction_id IS NOT NULL")
            .HasDatabaseName("ux_finance_transactions_single_reversal");
        b.HasOne<FinanceTransactionRecord>().WithMany().HasForeignKey(x=>new{x.ReversalOfTransactionId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.HasOne<PartyRecord>().WithMany().HasForeignKey(x=>new{x.PartyId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.ToTable(t=>{
            t.HasCheckConstraint("ck_finance_transactions_amount","amount >= 0 AND base_amount >= 0");
            t.HasCheckConstraint("ck_finance_transactions_currency","currency_code ~ '^[A-Z]{3}$' AND base_currency_code ~ '^[A-Z]{3}$'");
            t.HasCheckConstraint("ck_finance_transactions_version","version > 0");
        });
    }

    private static void AccountLedger(ModelBuilder m)
    {
        var b=m.Entity<AccountLedgerEntryRecord>();
        b.ToTable("account_ledger_entries","finance"); Id(b); Public(b); Company(b);
        b.Property(x=>x.TransactionId).HasColumnName("transaction_id");
        b.Property(x=>x.PartyId).HasColumnName("party_id");
        Enum(b.Property(x=>x.PartyRole).HasColumnName("party_role"),16);
        Enum(b.Property(x=>x.Direction).HasColumnName("direction"),8);
        Currency(b.Property(x=>x.CurrencyCode).HasColumnName("currency_code"));
        Money(b.Property(x=>x.Amount).HasColumnName("amount"));
        Money(b.Property(x=>x.BaseAmount).HasColumnName("base_amount"));
        b.Property(x=>x.DueDate).HasColumnName("due_date");
        b.Property(x=>x.PostedAt).HasColumnName("posted_at");
        b.HasIndex(x=>new{x.CompanyId,x.PartyId,x.PartyRole,x.CurrencyCode,x.Id}).HasDatabaseName("ix_finance_account_ledger_balance");
        TxFk(b,x=>new{x.TransactionId,x.CompanyId});
        b.HasOne<PartyRecord>().WithMany().HasForeignKey(x=>new{x.PartyId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.ToTable(t=>t.HasCheckConstraint("ck_finance_account_ledger_amount","amount > 0 AND base_amount > 0"));
    }

    private static void CashAccount(ModelBuilder m)
    {
        var b=m.Entity<CashAccountRecord>();
        b.ToTable("cash_accounts","finance"); Id(b); Public(b); Company(b);
        b.Property(x=>x.BranchId).HasColumnName("branch_id");
        b.Property(x=>x.Code).HasColumnName("code").HasMaxLength(64).IsRequired();
        b.Property(x=>x.Name).HasColumnName("name").HasMaxLength(200).IsRequired();
        Currency(b.Property(x=>x.CurrencyCode).HasColumnName("currency_code"));
        Enum(b.Property(x=>x.State).HasColumnName("state"),16);
        Version(b.Property(x=>x.Version).HasColumnName("version"));
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId}).HasName("ak_finance_cash_accounts_id_company");
        b.HasIndex(x=>new{x.CompanyId,x.Code}).IsUnique().HasDatabaseName("ux_finance_cash_accounts_company_code");
        RequiredTextChecks(b,"cash_accounts");
    }

    private static void CashLedger(ModelBuilder m)
    {
        var b=m.Entity<CashLedgerEntryRecord>();
        b.ToTable("cash_ledger_entries","finance"); Id(b); Public(b); Company(b);
        b.Property(x=>x.TransactionId).HasColumnName("transaction_id");
        b.Property(x=>x.CashAccountId).HasColumnName("cash_account_id");
        Enum(b.Property(x=>x.Direction).HasColumnName("direction"),8);
        Currency(b.Property(x=>x.CurrencyCode).HasColumnName("currency_code"));
        Money(b.Property(x=>x.Amount).HasColumnName("amount"));
        Money(b.Property(x=>x.BaseAmount).HasColumnName("base_amount"));
        b.Property(x=>x.PostedAt).HasColumnName("posted_at");
        b.HasIndex(x=>new{x.CompanyId,x.CashAccountId,x.Id}).HasDatabaseName("ix_finance_cash_ledger_balance");
        TxFk(b,x=>new{x.TransactionId,x.CompanyId});
        b.HasOne<CashAccountRecord>().WithMany().HasForeignKey(x=>new{x.CashAccountId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.ToTable(t=>t.HasCheckConstraint("ck_finance_cash_ledger_amount","amount > 0 AND base_amount > 0"));
    }

    private static void BankAccount(ModelBuilder m)
    {
        var b=m.Entity<BankAccountRecord>();
        b.ToTable("bank_accounts","finance"); Id(b); Public(b); Company(b);
        b.Property(x=>x.BranchId).HasColumnName("branch_id");
        b.Property(x=>x.Code).HasColumnName("code").HasMaxLength(64).IsRequired();
        b.Property(x=>x.Name).HasColumnName("name").HasMaxLength(200).IsRequired();
        Currency(b.Property(x=>x.CurrencyCode).HasColumnName("currency_code"));
        b.Property(x=>x.Iban).HasColumnName("iban").HasMaxLength(64);
        Enum(b.Property(x=>x.State).HasColumnName("state"),16);
        Version(b.Property(x=>x.Version).HasColumnName("version"));
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId}).HasName("ak_finance_bank_accounts_id_company");
        b.HasIndex(x=>new{x.CompanyId,x.Code}).IsUnique().HasDatabaseName("ux_finance_bank_accounts_company_code");
        b.HasIndex(x=>new{x.CompanyId,x.Iban}).IsUnique().HasFilter("iban IS NOT NULL").HasDatabaseName("ux_finance_bank_accounts_company_iban");
        RequiredTextChecks(b,"bank_accounts");
    }

    private static void BankLedger(ModelBuilder m)
    {
        var b=m.Entity<BankLedgerEntryRecord>();
        b.ToTable("bank_ledger_entries","finance"); Id(b); Public(b); Company(b);
        b.Property(x=>x.TransactionId).HasColumnName("transaction_id");
        b.Property(x=>x.BankAccountId).HasColumnName("bank_account_id");
        Enum(b.Property(x=>x.Direction).HasColumnName("direction"),8);
        Currency(b.Property(x=>x.CurrencyCode).HasColumnName("currency_code"));
        Money(b.Property(x=>x.Amount).HasColumnName("amount"));
        Money(b.Property(x=>x.BaseAmount).HasColumnName("base_amount"));
        b.Property(x=>x.PostedAt).HasColumnName("posted_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId}).HasName("ak_finance_bank_ledger_id_company");
        b.HasIndex(x=>new{x.CompanyId,x.BankAccountId,x.Id}).HasDatabaseName("ix_finance_bank_ledger_balance");
        TxFk(b,x=>new{x.TransactionId,x.CompanyId});
        b.HasOne<BankAccountRecord>().WithMany().HasForeignKey(x=>new{x.BankAccountId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.ToTable(t=>t.HasCheckConstraint("ck_finance_bank_ledger_amount","amount > 0 AND base_amount > 0"));
    }

    private static void Period(ModelBuilder m)
    {
        var b=m.Entity<FinancePostingPeriodRecord>();
        b.ToTable("posting_periods","finance"); Id(b); Public(b); Company(b);
        b.Property(x=>x.StartDate).HasColumnName("start_date");
        b.Property(x=>x.EndDate).HasColumnName("end_date");
        Enum(b.Property(x=>x.State).HasColumnName("state"),16);
        Version(b.Property(x=>x.Version).HasColumnName("version"));
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.Property(x=>x.ChangedAt).HasColumnName("changed_at");
        b.HasIndex(x=>new{x.CompanyId,x.StartDate,x.EndDate}).IsUnique().HasDatabaseName("ux_finance_period_range");
        b.ToTable(t=>t.HasCheckConstraint("ck_finance_period_range","end_date >= start_date"));
    }

    private static void ValuationPool(ModelBuilder m)
    {
        var b=m.Entity<InventoryValuationPoolRecord>();
        b.ToTable("inventory_valuation_pools","finance"); Id(b); Public(b); Company(b);
        b.Property(x=>x.ProductId).HasColumnName("product_id");
        b.Property(x=>x.VariantId).HasColumnName("variant_id");
        b.Property(x=>x.BaseUomId).HasColumnName("base_uom_id");
        Currency(b.Property(x=>x.CurrencyCode).HasColumnName("currency_code"));
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId}).HasName("ak_finance_valuation_pools_id_company");
        b.HasIndex(x=>new{x.CompanyId,x.ProductId,x.VariantId,x.CurrencyCode}).IsUnique()
            .HasDatabaseName("ux_finance_valuation_pool_grain");
        b.HasOne<ProductRecord>().WithMany().HasForeignKey(x=>new{x.ProductId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.HasOne<ProductVariantRecord>().WithMany().HasForeignKey(x=>new{x.VariantId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.HasOne<UnitOfMeasureRecord>().WithMany().HasForeignKey(x=>new{x.BaseUomId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
    }

    private static void ValuationEntry(ModelBuilder m)
    {
        var b=m.Entity<InventoryValuationEntryRecord>();
        b.ToTable("inventory_valuation_entries","finance"); Id(b); Public(b); Company(b);
        b.Property(x=>x.TransactionId).HasColumnName("transaction_id");
        b.Property(x=>x.PoolId).HasColumnName("pool_id");
        Enum(b.Property(x=>x.Kind).HasColumnName("kind"),32);
        Value(b.Property(x=>x.InventoryQuantityEffect).HasColumnName("inventory_quantity_effect"));
        Value(b.Property(x=>x.InventoryBaseValueEffect).HasColumnName("inventory_base_value_effect"));
        Value(b.Property(x=>x.ExpenseBaseValueEffect).HasColumnName("expense_base_value_effect"));
        Value(b.Property(x=>x.UnitBaseValue).HasColumnName("unit_base_value"));
        b.Property(x=>x.InventoryMovementPublicId).HasColumnName("inventory_movement_public_id");
        b.Property(x=>x.SourceModule).HasColumnName("source_module").HasMaxLength(64).IsRequired();
        b.Property(x=>x.SourceEntityType).HasColumnName("source_entity_type").HasMaxLength(64).IsRequired();
        b.Property(x=>x.SourceDocumentPublicId).HasColumnName("source_document_public_id");
        b.Property(x=>x.SourceLinePublicId).HasColumnName("source_line_public_id");
        b.Property(x=>x.ReversalOfValuationEntryId).HasColumnName("reversal_of_valuation_entry_id");
        b.Property(x=>x.PostedAt).HasColumnName("posted_at");
        b.HasIndex(x=>new{x.CompanyId,x.PoolId,x.Id}).HasDatabaseName("ix_finance_valuation_pool_entries");
        b.HasIndex(x=>new{x.CompanyId,x.SourceModule,x.SourceEntityType,x.SourceDocumentPublicId,x.SourceLinePublicId})
            .HasDatabaseName("ix_finance_valuation_source");
        TxFk(b,x=>new{x.TransactionId,x.CompanyId});
        b.HasOne<InventoryValuationPoolRecord>().WithMany().HasForeignKey(x=>new{x.PoolId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.HasOne<InventoryValuationEntryRecord>().WithMany().HasForeignKey(x=>new{x.ReversalOfValuationEntryId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
    }

    private static void BridgeEntry(ModelBuilder m)
    {
        var b=m.Entity<DispatchCostBridgeEntryRecord>();
        b.ToTable("dispatch_cost_bridge_entries","finance"); Id(b); Public(b); Company(b);
        b.Property(x=>x.TransactionId).HasColumnName("transaction_id");
        b.Property(x=>x.PoolId).HasColumnName("pool_id");
        b.Property(x=>x.DispatchPublicId).HasColumnName("dispatch_public_id");
        b.Property(x=>x.DispatchLinePublicId).HasColumnName("dispatch_line_public_id");
        b.Property(x=>x.PhysicalSourcePublicId).HasColumnName("physical_source_public_id");
        b.Property(x=>x.InventoryMovementPublicId).HasColumnName("inventory_movement_public_id");
        Value(b.Property(x=>x.BaseQuantity).HasColumnName("base_quantity"));
        Value(b.Property(x=>x.BaseValue).HasColumnName("base_value"));
        b.Property(x=>x.ReversalOfBridgeEntryId).HasColumnName("reversal_of_bridge_entry_id");
        b.Property(x=>x.PostedAt).HasColumnName("posted_at");
        b.HasIndex(x=>new{x.CompanyId,x.DispatchPublicId,x.DispatchLinePublicId,x.PhysicalSourcePublicId})
            .HasDatabaseName("ix_finance_dispatch_bridge_source");
        b.HasIndex(x=>x.InventoryMovementPublicId).IsUnique().HasDatabaseName("ux_finance_dispatch_bridge_inventory_movement");
        TxFk(b,x=>new{x.TransactionId,x.CompanyId});
        b.HasOne<InventoryValuationPoolRecord>().WithMany().HasForeignKey(x=>new{x.PoolId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.HasOne<DispatchCostBridgeEntryRecord>().WithMany().HasForeignKey(x=>new{x.ReversalOfBridgeEntryId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
    }

    private static void BridgeConsumption(ModelBuilder m)
    {
        var b=m.Entity<DispatchCostBridgeConsumptionRecord>();
        b.ToTable("dispatch_cost_bridge_consumptions","finance"); Id(b); Public(b); Company(b);
        b.Property(x=>x.TransactionId).HasColumnName("transaction_id");
        b.Property(x=>x.BridgeEntryId).HasColumnName("bridge_entry_id");
        b.Property(x=>x.SalesInvoicePublicId).HasColumnName("sales_invoice_public_id");
        b.Property(x=>x.SalesInvoiceLinePublicId).HasColumnName("sales_invoice_line_public_id");
        Value(b.Property(x=>x.BaseQuantity).HasColumnName("base_quantity"));
        Value(b.Property(x=>x.BaseValue).HasColumnName("base_value"));
        b.Property(x=>x.ReversalOfConsumptionId).HasColumnName("reversal_of_consumption_id");
        b.Property(x=>x.PostedAt).HasColumnName("posted_at");
        b.HasIndex(x=>new{x.CompanyId,x.SalesInvoicePublicId,x.SalesInvoiceLinePublicId}).HasDatabaseName("ix_finance_bridge_consumption_invoice");
        TxFk(b,x=>new{x.TransactionId,x.CompanyId});
        b.HasOne<DispatchCostBridgeEntryRecord>().WithMany().HasForeignKey(x=>new{x.BridgeEntryId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.HasOne<DispatchCostBridgeConsumptionRecord>().WithMany().HasForeignKey(x=>new{x.ReversalOfConsumptionId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
    }

    private static void StatementBatch(ModelBuilder m)
    {
        var b=m.Entity<StatementImportBatchRecord>();
        b.ToTable("statement_import_batches","finance"); Id(b); Public(b); Company(b);
        b.Property(x=>x.BankAccountId).HasColumnName("bank_account_id");
        b.Property(x=>x.SourceName).HasColumnName("source_name").HasMaxLength(200).IsRequired();
        b.Property(x=>x.SourceReference).HasColumnName("source_reference").HasMaxLength(256);
        b.Property(x=>x.CreatorActorId).HasColumnName("creator_actor_id");
        b.Property(x=>x.ImportedAt).HasColumnName("imported_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId}).HasName("ak_finance_statement_batches_id_company");
        b.HasOne<BankAccountRecord>().WithMany().HasForeignKey(x=>new{x.BankAccountId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
    }

    private static void StatementLine(ModelBuilder m)
    {
        var b=m.Entity<StatementLineRecord>();
        b.ToTable("statement_lines","finance"); Id(b); Public(b); Company(b);
        b.Property(x=>x.BatchId).HasColumnName("batch_id");
        b.Property(x=>x.BankAccountId).HasColumnName("bank_account_id");
        b.Property(x=>x.BookingDate).HasColumnName("booking_date");
        b.Property(x=>x.ValueDate).HasColumnName("value_date");
        Money(b.Property(x=>x.Amount).HasColumnName("amount"));
        Currency(b.Property(x=>x.CurrencyCode).HasColumnName("currency_code"));
        b.Property(x=>x.ExternalId).HasColumnName("external_id").HasMaxLength(256);
        b.Property(x=>x.Fingerprint).HasColumnName("fingerprint").HasMaxLength(64).IsRequired();
        b.Property(x=>x.Description).HasColumnName("description").HasMaxLength(1024);
        Enum(b.Property(x=>x.State).HasColumnName("state"),24);
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.HasAlternateKey(x=>new{x.Id,x.CompanyId}).HasName("ak_finance_statement_lines_id_company");
        b.HasIndex(x=>new{x.CompanyId,x.BankAccountId,x.ExternalId}).IsUnique().HasFilter("external_id IS NOT NULL")
            .HasDatabaseName("ux_finance_statement_external");
        b.HasIndex(x=>new{x.CompanyId,x.BankAccountId,x.Fingerprint}).IsUnique().HasDatabaseName("ux_finance_statement_fingerprint");
        b.HasOne<StatementImportBatchRecord>().WithMany().HasForeignKey(x=>new{x.BatchId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.HasOne<BankAccountRecord>().WithMany().HasForeignKey(x=>new{x.BankAccountId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.ToTable(t=>t.HasCheckConstraint("ck_finance_statement_amount","amount <> 0"));
    }

    private static void Reconciliation(ModelBuilder m)
    {
        var b=m.Entity<ReconciliationMatchRecord>();
        b.ToTable("reconciliation_matches","finance"); Id(b); Public(b); Company(b);
        b.Property(x=>x.StatementLineId).HasColumnName("statement_line_id");
        b.Property(x=>x.BankLedgerEntryId).HasColumnName("bank_ledger_entry_id");
        Money(b.Property(x=>x.MatchedAmount).HasColumnName("matched_amount"));
        Enum(b.Property(x=>x.State).HasColumnName("state"),16);
        b.Property(x=>x.ActorId).HasColumnName("actor_id");
        b.Property(x=>x.CreatedAt).HasColumnName("created_at");
        b.Property(x=>x.ReversedAt).HasColumnName("reversed_at");
        b.HasIndex(x=>new{x.CompanyId,x.StatementLineId,x.BankLedgerEntryId,x.State})
            .HasDatabaseName("ix_finance_reconciliation_pair");
        b.HasOne<StatementLineRecord>().WithMany().HasForeignKey(x=>new{x.StatementLineId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.HasOne<BankLedgerEntryRecord>().WithMany().HasForeignKey(x=>new{x.BankLedgerEntryId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.ToTable(t=>t.HasCheckConstraint("ck_finance_reconciliation_amount","matched_amount > 0"));
    }

    private static void Risk(ModelBuilder m)
    {
        var b=m.Entity<CustomerRiskControlRecord>();
        b.ToTable("customer_risk_controls","finance"); Id(b); Public(b); Company(b);
        b.Property(x=>x.PartyId).HasColumnName("party_id");
        Money(b.Property(x=>x.CreditLimit).HasColumnName("credit_limit"));
        b.Property(x=>x.ManualHold).HasColumnName("manual_hold");
        b.Property(x=>x.HoldReason).HasColumnName("hold_reason").HasMaxLength(1024);
        Version(b.Property(x=>x.Version).HasColumnName("version"));
        b.Property(x=>x.UpdatedByActorId).HasColumnName("updated_by_actor_id");
        b.Property(x=>x.UpdatedAt).HasColumnName("updated_at");
        b.HasIndex(x=>new{x.CompanyId,x.PartyId}).IsUnique().HasDatabaseName("ux_finance_customer_risk_party");
        b.HasOne<PartyRecord>().WithMany().HasForeignKey(x=>new{x.PartyId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.ToTable(t=>t.HasCheckConstraint("ck_finance_risk_limit","credit_limit IS NULL OR credit_limit >= 0"));
    }

    private static void CashCount(ModelBuilder m)
    {
        var b=m.Entity<CashCountRecord>();
        b.ToTable("cash_counts","finance"); Id(b); Public(b); Company(b);
        b.Property(x=>x.CashAccountId).HasColumnName("cash_account_id");
        Money(b.Property(x=>x.BookBalanceSnapshot).HasColumnName("book_balance_snapshot"));
        Money(b.Property(x=>x.CountedBalance).HasColumnName("counted_balance"));
        Money(b.Property(x=>x.Difference).HasColumnName("difference"));
        b.Property(x=>x.CounterActorId).HasColumnName("counter_actor_id");
        b.Property(x=>x.ApprovalDecisionPublicId).HasColumnName("approval_decision_public_id");
        b.Property(x=>x.AdjustmentTransactionId).HasColumnName("adjustment_transaction_id");
        b.Property(x=>x.CountedAt).HasColumnName("counted_at");
        b.HasOne<CashAccountRecord>().WithMany().HasForeignKey(x=>new{x.CashAccountId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
        b.HasOne<FinanceTransactionRecord>().WithMany().HasForeignKey(x=>new{x.AdjustmentTransactionId,x.CompanyId})
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);
    }

    private static void Id<T>(EntityTypeBuilder<T> b) where T:class => b.Property<long>("Id").HasColumnName("id").ValueGeneratedOnAdd();
    private static void Public<T>(EntityTypeBuilder<T> b) where T:class
    {
        b.Property<Guid>("PublicId").HasColumnName("public_id");
        b.HasIndex("PublicId").IsUnique();
    }
    private static void Company<T>(EntityTypeBuilder<T> b) where T:class => b.Property<Guid>("CompanyId").HasColumnName("company_id");
    private static PropertyBuilder<T> Enum<T>(PropertyBuilder<T> p,int length) where T:struct,Enum =>
        p.HasConversion<string>().HasMaxLength(length).IsRequired();
    private static PropertyBuilder<string> Currency(PropertyBuilder<string> p) => p.HasMaxLength(3).IsRequired();
    private static PropertyBuilder<decimal> Money(PropertyBuilder<decimal> p) => p.HasPrecision(28,9);
    private static PropertyBuilder<decimal?> Money(PropertyBuilder<decimal?> p) => p.HasPrecision(28,9);
    private static PropertyBuilder<decimal> Value(PropertyBuilder<decimal> p) => p.HasPrecision(28,9);
    private static PropertyBuilder<decimal?> Value(PropertyBuilder<decimal?> p) => p.HasPrecision(28,9);
    private static PropertyBuilder<long> Version(PropertyBuilder<long> p) => p.IsConcurrencyToken();

    private static void TxFk<T>(EntityTypeBuilder<T> b,System.Linq.Expressions.Expression<Func<T,object?>> fk) where T:class =>
        b.HasOne<FinanceTransactionRecord>().WithMany().HasForeignKey(fk)
            .HasPrincipalKey(x=>new{x.Id,x.CompanyId}).OnDelete(DeleteBehavior.Restrict);

    private static void RequiredTextChecks<T>(EntityTypeBuilder<T> b,string table) where T:class =>
        b.ToTable(t=>{
            t.HasCheckConstraint($"ck_finance_{table}_code","length(btrim(code)) > 0 AND code = btrim(code)");
            t.HasCheckConstraint($"ck_finance_{table}_name","length(btrim(name)) > 0 AND name = btrim(name)");
            t.HasCheckConstraint($"ck_finance_{table}_version","version > 0");
        });
}
