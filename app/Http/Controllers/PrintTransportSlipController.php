<?php

namespace App\Http\Controllers;

use App\Models\Period\TransportSlip;
use App\Support\Period\PeriodContext;
use Illuminate\Contracts\View\View;

class PrintTransportSlipController
{
    public function __invoke(int $slip, string $format): View
    {
        abort_unless(auth()->user()?->can('warehouse_slips.view'), 403);
        abort_unless(in_array($format, ['slip', 'labels'], true), 404);
        // Resolve period-scoped records only after the web period middleware has run.
        // Implicit route model binding can execute before the period DB is selected.
        PeriodContext::ensure();
        $slip = TransportSlip::query()->with(['contact', 'items.product.unit'])->findOrFail($slip);
        abort_unless($slip->package_count >= 1 && $slip->package_count <= 500, 422);

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
