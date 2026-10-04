<?php

namespace App\Actions\Purchases;

use App\Actions\Documents\PostDocument;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Support\Auth\MutationAuthorizer;
use DomainException;

final class PostGoodsReceipt
{
    public function __construct(private readonly PostDocument $postDocument) {}

    public function handle(Document $receipt, string $idempotencyKey): Document
    {
        MutationAuthorizer::authorize('goods_receipts.update');

        if ($receipt->document_type !== DocumentType::GoodsReceipt) {
            throw new DomainException('Yalnız mal kabul belgesi bu action ile kesinleştirilebilir.');
        }

        return $this->postDocument->handle($receipt, $idempotencyKey);
    }
}
