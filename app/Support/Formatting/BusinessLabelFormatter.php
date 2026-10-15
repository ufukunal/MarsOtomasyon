<?php

namespace App\Support\Formatting;

use BackedEnum;

/**
 * Only presentation strings are translated; persisted codes and API payloads remain unchanged.
 */
final class BusinessLabelFormatter
{
    /** @return array<string,string> */
    private static function dictionary(string $context): array
    {
        return match ($context) {
            'document_type' => [
                'quote'=>'Teklif', 'sales_order'=>'Satış Siparişi', 'dispatch'=>'Sevk İrsaliyesi',
                'sales_invoice'=>'Satış Faturası', 'proforma'=>'Proforma',
                'collection'=>'Tahsilat', 'contact_debit_credit'=>'Cari Borç / Alacak',
                'purchase_order'=>'Satınalma Siparişi', 'goods_receipt'=>'Mal Kabul',
                'supplier_invoice'=>'Alış Faturası', 'payment'=>'Ödeme', 'expense'=>'Gider',
                'advance'=>'Avans', 'advance_return'=>'Avans İadesi',
                'sales_return'=>'Satış İadesi', 'purchase_return'=>'Alış İadesi',
                'stock_count'=>'Stok Sayımı', 'warehouse_slip'=>'Stok Düzeltme Fişi',
                'transfer'=>'Depo Transferi', 'opening'=>'Açılış',
                'transport_slip'=>'Ambar Fişi','product'=>'Ürün',
                'product_label'=>'Ürün Etiketi','carton_label'=>'Koli Etiketi',
                'receipt'=>'Fiş','report'=>'Rapor','a4'=>'A4 Belgesi',
            ],
            'contact_transaction' => [
                'opening'=>'Açılış Bakiyesi', 'sales_invoice'=>'Satış Faturası',
                'supplier_invoice'=>'Alış Faturası', 'collection'=>'Tahsilat',
                'payment'=>'Ödeme', 'contact_debit_credit'=>'Cari Borç / Alacak',
                'sales_return'=>'Satış İadesi', 'purchase_return'=>'Alış İadesi',
                'advance'=>'Avans', 'advance_return'=>'Avans İadesi',
                'reversal'=>'Ters Kayıt', 'period_carry'=>'Dönem Devri',
                'manual'=>'Manuel Hareket', 'adjustment'=>'Düzeltme', 'expense'=>'Gider',
                'security'=>'Çek / Senet', 'security_collection'=>'Çek / Senet Tahsilatı',
                'security_payment'=>'Çek / Senet Ödemesi',
            ],
            'stock_reason' => [
                'opening'=>'Açılış Stok Girişi', 'purchase'=>'Alış', 'sale'=>'Satış',
                'transfer'=>'Depo Transferi', 'count'=>'Stok Sayımı',
                'production'=>'Üretim', 'production_consumption'=>'Üretim Tüketimi',
                'return'=>'İade', 'sales_return'=>'Satış İadesi',
                'purchase_return'=>'Alış İadesi', 'scrap'=>'Hurda',
                'adjustment'=>'Stok Düzeltmesi', 'warehouse_slip'=>'Stok Düzeltme Fişi',
                'reversal'=>'Ters Kayıt',
            ],
            'direction' => [
                'in'=>'Giriş', 'out'=>'Çıkış', 'debit'=>'Borç', 'credit'=>'Alacak',
                'incoming'=>'Alınan', 'outgoing'=>'Verilen',
                'inbound'=>'Gelen', 'outbound'=>'Giden',
            ],
            'status' => [
                'draft'=>'Taslak', 'posted'=>'Kesinleşti',
                'approved'=>'Onaylandı', 'confirmed'=>'Onaylandı',
                'cancelled'=>'İptal Edildi', 'canceled'=>'İptal Edildi',
                'reversed'=>'Ters Kayıt', 'pending'=>'Bekliyor',
                'queued'=>'Kuyrukta', 'processing'=>'İşleniyor', 'running'=>'Çalışıyor',
                'completed'=>'Tamamlandı', 'complete'=>'Tamamlandı',
                'success'=>'Başarılı', 'succeeded'=>'Başarılı', 'failed'=>'Başarısız',
                'error'=>'Hata', 'active'=>'Aktif', 'inactive'=>'Pasif',
                'released'=>'Serbest Bırakıldı', 'consumed'=>'Tüketildi',
                'review'=>'İncelemede', 'counting'=>'Sayım Yapılıyor',
                'in_transit'=>'Yolda', 'partially_received'=>'Kısmi Teslim Alındı',
                'received'=>'Teslim Alındı', 'partial'=>'Kısmi',
                'scrapped'=>'Hurdaya Ayrıldı', 'planned'=>'Planlandı',
                'ready'=>'Hazır', 'handed_over'=>'Teslim Edildi',
                'open'=>'Açık', 'closed'=>'Kapalı', 'archived'=>'Arşivlendi',
                'done'=>'Tamamlandı','verified'=>'Doğrulandı',
                'rejected'=>'Reddedildi','returned'=>'İade Edildi',
                'on_hold'=>'Bekletiliyor','hold'=>'Bekletiliyor',
                'scheduled'=>'Planlandı','waiting'=>'Bekliyor',
                'refunded'=>'İade Edildi','returned'=>'İade Edildi',
                'customs'=>'Gümrükte', 'internal_review'=>'İç İncelemede',
                'customer_review'=>'Müşteri İncelemesinde',
                'pending_approval'=>'Onay Bekliyor', 'sent'=>'Gönderildi',
                'paid'=>'Ödendi', 'unpaid'=>'Ödenmedi', 'protested'=>'Protestolu',
                'deposited'=>'Bankaya Verildi', 'endorsed'=>'Ciro Edildi',
                'validated'=>'Doğrulandı', 'skipped'=>'Atlandı',
                'done'=>'Tamamlandı', 'verified'=>'Doğrulandı', 'unknown'=>'Bilinmiyor',
                'ok'=>'Başarılı', 'fail'=>'Başarısız',
                'warning'=>'Uyarı', 'healthy'=>'Sağlıklı', 'unhealthy'=>'Sorunlu',
            ],
            'print_type'=>[
                'a4'=>'A4 Belgesi','product_label'=>'Ürün Etiketi',
                'carton_label'=>'Koli Etiketi','receipt'=>'Fiş','report'=>'Rapor',
            ],
            'security_kind'=>['check'=>'Çek','note'=>'Senet'],
            'security_action'=>[
                'endorsement'=>'Ciro Et','bank_deposit'=>'Bankaya Ver',
                'collection'=>'Tahsil Et','payment'=>'Öde','return'=>'İade',
                'protest'=>'Protesto','cancel'=>'İptal','reversal'=>'Ters Kayıt',
            ],
            'location_kind'=>[
                'warehouse'=>'Depo','branch'=>'Şube','vehicle'=>'Araç',
                'subcontractor'=>'Fason Deposu',
            ],
            'address_type'=>['invoice'=>'Fatura Adresi','shipping'=>'Sevk Adresi'],
            'production_type'=>['internal'=>'İç Üretim','subcontract'=>'Fason Üretim'],
            'sync_action'=>[
                'create'=>'Oluştur','update'=>'Güncelle','delete'=>'Sil',
                'sync'=>'Eşitle','push'=>'Gönder','pull'=>'Al',
                'retry'=>'Yeniden Dene','order'=>'Sipariş',
                'cancel'=>'İptal','return'=>'İade','stock'=>'Stok','price'=>'Fiyat',
            ],
            'sync_entity'=>[
                'order'=>'Sipariş','product'=>'Ürün','listing'=>'İlan',
                'stock'=>'Stok','price'=>'Fiyat','return'=>'İade',
                'shipment'=>'Gönderi','cancel'=>'İptal',
            ],
            'cash_movement'=>[
                'opening'=>'Açılış Bakiyesi','manual'=>'Manuel Hareket',
                'bank_statement'=>'Banka Ekstresi', 'cash'=>'Kasa', 'bank'=>'Banka',
                'collection'=>'Tahsilat','payment'=>'Ödeme','transfer'=>'Virman',
                'expense'=>'Gider','advance'=>'Avans','advance_return'=>'Avans İadesi',
                'sales_invoice'=>'Satış Faturası','supplier_invoice'=>'Alış Faturası',
                'statement_created'=>'Ekstreden Oluşturulan Hareket','reversal'=>'Ters Kayıt',
            ],
            'document_section'=>[
                'header'=>'Başlık','lines'=>'Satırlar','totals'=>'Toplamlar',
                'notes'=>'Notlar', 'custom_text'=>'Özel Metin',
                'footer'=>'Alt Bilgi','table'=>'Tablo','text'=>'Metin',
                'image'=>'Görsel','signature'=>'İmza','summary'=>'Özet',
            ],
            default => [],
        };
    }

    public static function value(string $context, mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }
        if ($value === null || $value === '') {
            return '';
        }
        if (! is_scalar($value)) {
            return 'Tanımsız değer';
        }

        $overrides = match ($context) {
            'reservation_status'=>['released'=>'Rezervi Çözüldü','consumed'=>'Kullanıldı'],
            'quarantine_status'=>['released'=>'Satılabilir','partial'=>'Kısmi Karar'],
            'import_status'=>['received'=>'Mal Kabul Yapıldı','closed'=>'Dosya Kapandı'],
            'period_status'=>['open'=>'Açık Dönem','closed'=>'Kapalı Dönem'],
            'production_status'=>['confirmed'=>'Üretim Onaylandı','completed'=>'Üretim Tamamlandı'],
            default => [],
        };
        $labels = $overrides + self::dictionary(str_ends_with($context, '_status') ? 'status' : $context);

        return $labels[(string) $value] ?? 'Tanımsız '.match ($context) {
            'document_type'=>'belge türü', 'contact_transaction','cash_movement'=>'işlem türü',
            'stock_reason'=>'stok hareketi', 'direction'=>'hareket yönü',
            'security_kind'=>'evrak türü', 'security_action','sync_action'=>'işlem',
            'location_kind'=>'lokasyon türü', 'address_type'=>'adres türü',
            'production_type'=>'üretim türü', 'sync_entity'=>'varlık türü',
            'document_section'=>'bölüm', 'print_type'=>'baskı türü', default=>'durum',
        };
    }

    public static function status(mixed $value): string { return self::value('status', $value); }
    public static function documentType(mixed $value): string { return self::value('document_type', $value); }
    public static function contactTransaction(mixed $value): string { return self::value('contact_transaction', $value); }
    public static function stockMovement(mixed $value): string { return self::value('stock_reason', $value); }
    public static function direction(mixed $value): string { return self::value('direction', $value); }

    /** Report screen, CSV, XLSX and PDF must use identical display values. */
    public static function report(string $reportKey, string $columnKey, mixed $value): string
    {
        $context = match ($columnKey) {
            'document_type','source_document_type'=>'document_type',
            'transaction_type'=>'contact_transaction',
            'movement_type','origin'=>'cash_movement',
            'direction'=>'direction',
            'status'=>str_contains($reportKey, 'imports') ? 'import_status' : 'status',
            'kind'=>'security_kind', 'action'=>'sync_action', 'entity_type'=>'sync_entity',
            'production_type'=>'production_type', default=>null,
        };

        return $context === null ? (is_scalar($value) ? (string) $value : '') : self::value($context, $value);
    }
}
