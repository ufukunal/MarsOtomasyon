<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Period\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function __invoke(Product $product, Attachment $attachment): Response
    {
        $this->authorize('view', $product);

        $owned = $product->attachments()
            ->whereKey($attachment->id)
            ->exists();

        abort_unless($owned, 404);
        abort_unless(str_starts_with((string) $attachment->mime, 'image/'), 404);

        $contents = Storage::disk($attachment->disk)->get($attachment->path);

        return response($contents, 200, [
            'Content-Type' => $attachment->mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($attachment->original_name).'"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
