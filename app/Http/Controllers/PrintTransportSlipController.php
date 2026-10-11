<?php

namespace App\Http\Controllers;

use App\Models\Period\TransportSlip;
use Illuminate\Contracts\View\View;

class PrintTransportSlipController
{
    public function __invoke(TransportSlip $slip, string $format): View
    {
        abort_unless(auth()->user()?->can('warehouse_slips.view'), 403);
        abort_unless(in_array($format, ['slip', 'labels'], true), 404);
        abort_unless($slip->package_count >= 1 && $slip->package_count <= 500, 422);

        $slip->load(['contact', 'location', 'items.product.unit']);

        return view('printing.transport-slip', [
            'slip' => $slip,
            'format' => $format,
            'autoprint' => request()->boolean('autoprint'),
            'warnings' => collect([
                $slip->contains_glass ? 'CAM ÜRÜN' : null,
                $slip->fragile ? 'KIRILABİLİR / HASSAS' : null,
                $slip->keep_upright ? 'DİK TAŞIYIN' : null,
                $slip->do_not_stack ? 'ÜSTÜNE YÜK KOYMAYIN' : null,
            ])->filter()->implode(' · '),
        ]);
    }
}
