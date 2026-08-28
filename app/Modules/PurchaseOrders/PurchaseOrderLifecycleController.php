<?php

namespace App\Modules\PurchaseOrders;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\PurchaseOrders\Actions\PurchaseOrderLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class PurchaseOrderLifecycleController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private PurchaseOrderLifecycle $lifecycle,
    ) {}

    public function open(Request $request, int $purchaseOrder): RedirectResponse
    {
        $this->lifecycle->open($this->companyId(), $purchaseOrder, $this->userId($request));

        return back()->with('status', 'Satınalma siparişi açıldı; mal kabul ve alış faturası işlemlerine hazır.');
    }

    public function close(Request $request, int $purchaseOrder): RedirectResponse
    {
        $this->lifecycle->close($this->companyId(), $purchaseOrder, $this->userId($request));

        return back()->with('status', 'Satınalma siparişi kapatıldı.');
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }

    private function userId(Request $request): int
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($id), 401);

        return (int) $id;
    }
}
