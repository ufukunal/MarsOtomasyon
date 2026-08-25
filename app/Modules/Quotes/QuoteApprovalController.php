<?php

namespace App\Modules\Quotes;

use App\Modules\Quotes\Actions\ConvertApprovedQuoteToOrder;
use App\Modules\Quotes\Actions\DecideQuoteRevision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final readonly class QuoteApprovalController
{
    public function __construct(
        private DecideQuoteRevision $decideQuoteRevision,
        private ConvertApprovedQuoteToOrder $convertApprovedQuoteToOrder,
    ) {}

    public function approve(Request $request, int $quote, int $revision): RedirectResponse
    {
        $validated = $request->validate([
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $user = $request->user();
        if ($user === null) {
            throw ValidationException::withMessages(['actor' => 'Onay kullanıcısı bulunamadı.']);
        }

        $this->decideQuoteRevision->approve(
            $quote,
            $revision,
            (int) $user->getAuthIdentifier(),
            isset($validated['decision_note']) ? (string) $validated['decision_note'] : null,
        );

        return redirect()->route('quotes.show', $quote)->with('status', 'Teklif revizyonu ticari olarak onaylandı.');
    }

    public function reject(Request $request, int $quote, int $revision): RedirectResponse
    {
        $validated = $request->validate([
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $user = $request->user();
        if ($user === null) {
            throw ValidationException::withMessages(['actor' => 'Karar kullanıcısı bulunamadı.']);
        }

        $this->decideQuoteRevision->reject(
            $quote,
            $revision,
            (int) $user->getAuthIdentifier(),
            isset($validated['decision_note']) ? (string) $validated['decision_note'] : null,
        );

        return redirect()->route('quotes.show', $quote)->with('status', 'Teklif revizyonu reddedildi.');
    }

    public function convert(Request $request, int $quote): RedirectResponse
    {
        $validated = $request->validate([
            'series_code' => ['nullable', 'string', 'max:64'],
        ]);
        $order = $this->convertApprovedQuoteToOrder->handle(
            $quote,
            isset($validated['series_code']) && $validated['series_code'] !== ''
                ? (string) $validated['series_code']
                : 'default',
        );

        return redirect()->route('quotes.show', $quote)
            ->with('status', 'Teklif satış siparişine dönüştürüldü: '.$order->number);
    }
}
