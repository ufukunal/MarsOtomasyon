<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Period\Product;
use App\Support\Period\PeriodContext;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final class ChannelAssetController extends Controller
{
    public function __invoke(int $company, int $period, int $product, int $attachment): Response
    {
        PeriodContext::useSystem($company, $period);

        try {
            $productModel = Product::query()->findOrFail($product);
            $attachmentModel = Attachment::query()->findOrFail($attachment);

            abort_unless(
                $productModel->attachments()->whereKey($attachmentModel->id)->exists(),
                404,
            );
            abort_unless(str_starts_with((string) $attachmentModel->mime, 'image/'), 404);
            abort_unless(in_array($attachmentModel->mime, config('product_images.mimes', []), true), 404);

            $contents = Storage::disk($attachmentModel->disk)->get($attachmentModel->path);

            return response($contents, 200, [
                'Content-Type' => $attachmentModel->mime,
                'Content-Disposition' => 'inline',
                'Cache-Control' => 'public, max-age=86400',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } finally {
            PeriodContext::clear();
        }
    }
}
