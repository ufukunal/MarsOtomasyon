<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Support\Period\PeriodContext;
use App\Support\Quotes\QuoteCustomImageSource;
use Illuminate\Http\Response;

final class QuoteCustomImageController
{
    public function __invoke(int $document, int $attachment): Response
    {
        PeriodContext::ensure();
        abort_unless(auth()->user()?->can('quotes.view'), 403);
        $quote = Document::query()->where('document_type', DocumentType::Quote->value)->findOrFail($document);
        $codes = $quote->lines()->where('line_kind', 'custom')->get()
            ->map(static fn ($line): string => (string) ($line->configuration['custom_code'] ?? ''))
            ->filter()->map(static fn ($code): string => 'quote_custom_'.$code)->all();

        $photo = $quote->attachments()->whereKey($attachment)
            ->whereIn('collection', $codes)->firstOrFail();
        abort_unless(in_array($photo->mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        $file = app(QuoteCustomImageSource::class)->read($photo, request()->boolean('original'));
        abort_unless($file !== null, 404);

        return response($file['bytes'], 200, [
            'Content-Type' => $file['mime'],
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
