<?php

namespace App\Http\Controllers;

use App\Models\Period\TransportSlip;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportTransportSlipController
{
    public function __invoke(?int $id = null): StreamedResponse
    {
        abort_unless(auth()->user()?->can('warehouse_slips.view'), 403);

        if ($id !== null) {
            TransportSlip::query()->findOrFail($id);
        }

        $filename = $id !== null ? 'ambar-fisi-'.$id.'.csv' : 'ambar-fisleri-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($id): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new \RuntimeException('Dışa aktarım açılamadı.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Fiş No', 'Tarih', 'Firma', 'Cari Kodu', 'Gönderim Tipi',
                'Taşıyıcı / Ambar', 'Gideceği Ambar', 'Koli', 'Cam', 'Kırılabilir',
                'Durum', 'Teslim Edilecek Firma', 'Yetkili', 'Telefon', 'İl', 'İlçe',
                'Teslimat Adresi', 'Takip No', 'Sipariş Ref.', 'İrsaliye Ref.', 'Ürünler',
            ], ';', '"', '');

            $query = TransportSlip::query()->with(['contact', 'items.product']);
            if ($id !== null) {
                $query->whereKey($id);
            }

            $query->chunkById(200, function ($slips) use ($output): void {
                foreach ($slips as $slip) {
                    $status = match ($slip->status) {
                        'draft' => 'Taslak',
                        'ready' => 'Hazır',
                        'handed_over' => 'Ambara Teslim',
                        'completed' => 'Tamamlandı',
                        'cancelled' => 'İptal',
                        default => $slip->status,
                    };
                    $row = [
                        $slip->number, \Carbon\CarbonImmutable::parse((string) $slip->slip_date)->toDateString(),
                        $slip->contact?->title, $slip->contact?->code,
                        $slip->shipping_type, $slip->carrier, $slip->destination_warehouse,
                        $slip->package_count, $slip->contains_glass ? 'Evet' : 'Hayır',
                        $slip->fragile ? 'Evet' : 'Hayır', $status,
                        $slip->delivery_company, $slip->delivery_person, $slip->delivery_phone,
                        $slip->delivery_city, $slip->delivery_district, $slip->delivery_address,
                        $slip->tracking_number, $slip->order_reference, $slip->dispatch_reference,
                        $slip->items->map(fn ($item): string => $item->product->code.' x '.$item->quantity)->implode(' | '),
                    ];

                    fputcsv($output, array_map(self::safeCell(...), $row), ';', '"', '');
                }
            });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function safeCell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return preg_match('/^\s*[=+@-]/u', $text) === 1 ? "'".$text : $text;
    }
}
