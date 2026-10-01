# Para aritmetiği ve yuvarlama

**En sık sessizce bozulan yer burasıdır.** `decimal(18,4)` tipi veriyi
doğru saklar ama PHP'de hesap float ile yapılırsa kuruş farkı birikir.

## Temel kural

> **Tutar hiçbir noktada `float` olmaz.** Veritabanından string olarak
> okunur, `BCMath` ile hesaplanır, string olarak yazılır.

```php
// YANLIŞ
$total = $qty * $price;                    // float, sessizce bozulur

// DOĞRU
$total = bcmul($qty, $price, 4);           // string, tam
```

PHP'de `0.1 + 0.2 !== 0.3`. Bir tutar bir kez float'a dönüşürse,
sonraki her işlemde hata taşınır ve 1.000 satırlık bir faturada
kuruşlar birikir.

## Money nesnesi

Çıplak `bcmul` çağrıları dağınık olur. Tek bir değer nesnesi kullanılır:

```php
final readonly class Money
{
    private function __construct(
        public string $amount,          // "1234.5600"
        public string $currency,        // "TRY"
    ) {}

    public static function of(string|int $amount, string $currency = 'TRY'): self
    {
        return new self(bcadd((string) $amount, '0', 4), $currency);
    }

    public function plus(Money $o): self  { $this->assertSame($o); return new self(bcadd($this->amount, $o->amount, 4), $this->currency); }
    public function minus(Money $o): self { $this->assertSame($o); return new self(bcsub($this->amount, $o->amount, 4), $this->currency); }
    public function times(string $m): self{ return new self(bcmul($this->amount, $m, 4), $this->currency); }
    public function percent(string $r): self { return new self(bcdiv(bcmul($this->amount, $r, 6), '100', 4), $this->currency); }

    /** Yarım yukarı yuvarlama — Türkiye'de kabul gören biçim */
    public function round(int $scale = 2): self
    {
        $f = bcpow('10', (string) $scale);
        $v = bcdiv(bcadd(bcmul($this->amount, $f, 4), '0.5', 4), $f, $scale);
        return new self(bcadd($v, '0', 4), $this->currency);
    }

    public function isNegative(): bool { return bccomp($this->amount, '0', 4) < 0; }
    public function equals(Money $o): bool { return $this->currency === $o->currency && bccomp($this->amount, $o->amount, 4) === 0; }

    private function assertSame(Money $o): void
    {
        if ($this->currency !== $o->currency) {
            throw new \DomainException("Farklı para birimleri toplanamaz: {$this->currency} + {$o->currency}");
        }
    }
}
```

**Para birimi nesnenin içindedir.** TRY ile USD toplanmaya çalışılırsa
istisna fırlatılır; sessizce yanlış toplam oluşmaz.

## Model tarafı

```php
protected function casts(): array
{
    return ['grand_total' => MoneyCast::class];
}
```

`MoneyCast` veritabanından gelen string'i `Money`'ye, geri yazarken
string'e çevirir. **`float` cast kullanılmaz.**

## Yuvarlama — nerede, kaç hane

| Aşama | Hassasiyet | Yuvarlama |
|---|---|---|
| Birim fiyat | 4 hane | yok (girildiği gibi) |
| Satır toplamı | 4 hane | yok |
| Ara toplam | 4 hane | yok |
| İskonto | 4 hane | yok |
| Matrah | 4 hane | yok |
| **Satır KDV'si** | 4 hane | **yok** |
| **Belge KDV toplamı** | 2 hane | **yuvarlanır** |
| **Genel toplam** | 2 hane | **yuvarlanır** |

**Kural: yuvarlama yalnızca belge toplamında yapılır, ara adımlarda
yapılmaz.** Her satırda yuvarlamak, 100 satırlık faturada 50 kuruşa
kadar sapma üretir.

KDV, satır satır değil, **oran gruplarına göre matrah toplanıp** bir kez
hesaplanır:

```
%20'lik satırların matrahı toplanır → KDV bir kez hesaplanır → yuvarlanır
%10'luk satırların matrahı toplanır → KDV bir kez hesaplanır → yuvarlanır
```

Bu, Türkiye'de fatura dökümündeki KDV kırılımının da beklediği biçimdir.

## Yuvarlama farkı

Genel toplam yuvarlandığında matrah + KDV ile arasında 1 kuruşa kadar
fark oluşabilir. Bu fark **saklanır**, gizlenmez:

```php
$table->decimal('rounding_difference', 18, 4)->default(0);
```

Belge dökümünde gösterilmez ama bütünlük kontrolü bunu hesaba katar.

## CHECK kısıtı toleransı — düzeltme

Önceki tolerans hatalıydı. Doğrusu:

```sql
-- yuvarlama farkı dahil edilerek kontrol
ALTER TABLE documents
  ADD CONSTRAINT doc_total_matches
  CHECK (abs(grand_total - (tax_base + vat_amount + rounding_difference)) < 0.0001);
```

`< 0.01` toleransı kuruş farkını görmezden geliyordu. Yeni tolerans
`0.0001` — yani gerçek bir hesap hatası varsa yakalanır.

## Döviz

Döviz yalnız ithalat ve alışta. Kur `decimal(18,6)`.
TRY karşılığı **belge kaydedilirken hesaplanır ve saklanır**; her
görüntülemede yeniden hesaplanmaz.

```php
$tryAmount = $foreignAmount->times($rate)->round(2);
```

## Miktar

Miktar da aynı disiplinle: `decimal(18,3)`, BCMath ile hesaplanır.
Birim dönüşümünde (`1 KUTU = 6 ADET`) katsayı `decimal(18,6)`'dır ve
sonuç 3 haneye yuvarlanır.

## Test zorunluluğu

Her hesap fonksiyonu için, **elle hesaplanmış beklenen değerle** test
yazılır:

```php
it('100 satirlik faturada kurus farki olusmaz', function () {
    $doc = belgeOlustur(satirSayisi: 100, birimFiyat: '33.33', kdv: '20');

    // elle: 100 x 33.33 = 3333.00 matrah, KDV 666.60, toplam 3999.60
    expect($doc->tax_base)->toBe('3333.0000');
    expect($doc->vat_amount)->toBe('666.6000');
    expect($doc->grand_total)->toBe('3999.6000');
});
```
