<?php

namespace App\Actions\Sales;

use App\Actions\Documents\PostDocument;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Support\Auth\MutationAuthorizer;
use DomainException;

final class PostDispatch
{
    public function __construct(private readonly PostDocument $postDocument) {}

    public function handle(Document $dispatch, string $idempotencyKey): Document
    {
        MutationAuthorizer::authorize('dispatches.update');

        if ($dispatch->document_type !== DocumentType::Dispatch) {
            throw new DomainException('Yalnız irsaliye PostDispatch ile kesinleştirilebilir.');
        }

        return $this->postDocument->handle($dispatch, $idempotencyKey);
    }
}
