# Numaralandırma

`number_series` period DB'dedir; company_id yoktur.

```php
DB::connection('period')->transaction(function () use ($type, $year) {
    $series = NumberSeries::query()
        ->where('document_type', $type)
        ->where('year', $year)
        ->lockForUpdate()
        ->firstOrFail();

    $series->last_number++;
    $series->save();

    // numara burada biçimlendirilir ve aynı transaction içindeki belgeye verilir
}, attempts: 3);
```

Numara taslak oluştururken verilmez. Kesinleştirme rollback olursa seri artışı da rollback olur. Yıl/period değişince yeni seri başlayabilir. `integrity:numbers` tekrar ve beklenmeyen boşlukları raporlar.
