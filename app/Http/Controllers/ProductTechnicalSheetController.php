<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Period\Product;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductTechnicalSheetController extends Controller
{
    public function __invoke(Product $product, Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $product);

        // Only serve a PDF linked to this exact product, never arbitrary storage paths.
        abort_unless(
            $product->attachments()
                ->whereKey($attachment->id)
                ->where('collection', 'Teknik Föy')
                ->where('mime', 'application/pdf')
                ->exists(),
            404
        );

        $disk = Storage::disk((string) $attachment->disk);
        abort_unless($disk->exists((string) $attachment->path), 404);

        return $disk->response(
            (string) $attachment->path,
            (string) $attachment->original_name,
            [
                'Content-Type' => 'application/pdf',
                'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
            'inline'
        );
    }
}
