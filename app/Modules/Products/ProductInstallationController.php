<?php

namespace App\Modules\Products;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Products\Documents\ProductInstallationDocumentService;
use App\Modules\Products\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use LogicException;

final readonly class ProductInstallationController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private ProductInstallationDocumentService $documents,
    ) {}

    public function edit(int $product): View
    {
        $productModel = $this->product($product);

        return view('products.installation.edit', [
            'product' => $productModel,
            'guide' => $this->documents->guide($product),
            'images' => $this->documents->availableImages($product),
            'documents' => $this->documents->documents($product),
        ]);
    }

    public function update(Request $request, int $product): RedirectResponse
    {
        $this->product($product);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'intro' => ['nullable', 'string', 'max:10000'],
            'steps_text' => ['nullable', 'string', 'max:30000'],
            'warnings_text' => ['nullable', 'string', 'max:20000'],
            'tools_text' => ['nullable', 'string', 'max:20000'],
            'parts_text' => ['nullable', 'string', 'max:20000'],
            'image_ids' => ['nullable', 'array', 'max:50'],
            'image_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);
        $imageIds = array_values(array_map(
            static fn (mixed $id): int => (int) $id,
            is_array($validated['image_ids'] ?? null) ? $validated['image_ids'] : [],
        ));

        $this->documents->saveDraft(
            $product,
            (string) $validated['title'],
            isset($validated['intro']) ? (string) $validated['intro'] : null,
            $this->lines((string) ($validated['steps_text'] ?? ''), 'steps_text'),
            $this->lines((string) ($validated['warnings_text'] ?? ''), 'warnings_text'),
            $this->lines((string) ($validated['tools_text'] ?? ''), 'tools_text'),
            $this->lines((string) ($validated['parts_text'] ?? ''), 'parts_text'),
            $imageIds,
        );

        return redirect()->route('inventory.products.installation.edit', $product)
            ->with('status', 'Kurulum rehberi taslağı kaydedildi.');
    }

    public function preview(int $product): View
    {
        $this->product($product);
        $payload = $this->documents->previewPayload($product);

        return view('products.installation.document', [
            ...$payload,
            'version' => null,
            'rendererVersion' => ProductInstallationDocumentService::RENDERER_VERSION,
            'sourceFingerprint' => null,
            'isPreview' => true,
        ]);
    }

    public function publish(int $product): RedirectResponse
    {
        $this->product($product);
        $document = $this->documents->publish($product);

        return redirect()->route('inventory.products.installation.edit', $product)
            ->with('status', 'Kurulum PDF v'.(int) $document->version.' yayınlandı.');
    }

    public function download(int $product, int $version): Response
    {
        $this->product($product);
        $document = $this->documents->document($product, $version);
        $bytes = $this->documents->verifiedBytes($document);
        $name = $document->fileAsset?->original_name;

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.addcslashes(is_string($name) ? $name : 'installation.pdf', '"\\').'"',
            'Content-Length' => (string) strlen($bytes),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return list<string> */
    private function lines(string $value, string $field): array
    {
        $lines = preg_split('/\r\n|\r|\n/u', $value);
        if (! is_array($lines)) {
            throw ValidationException::withMessages([$field => 'Satır listesi çözümlenemedi.']);
        }
        $lines = array_values(array_filter(array_map(static fn (string $line): string => trim($line), $lines), static fn (string $line): bool => $line !== ''));
        foreach ($lines as $line) {
            if (mb_strlen($line) > 2000) {
                throw ValidationException::withMessages([$field => 'Her satır en fazla 2000 karakter olabilir.']);
            }
        }

        return $lines;
    }

    private function product(int $id): Product
    {
        return Product::query()->where('company_id', $this->companyId())->findOrFail($id);
    }

    private function companyId(): int
    {
        $id = $this->companyContext->requireCompany()->getKey();

        return is_int($id) ? $id : throw new LogicException('Installation PDF builder requires a persisted company.');
    }
}
