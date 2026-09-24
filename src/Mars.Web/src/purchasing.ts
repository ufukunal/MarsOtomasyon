import type { ApiResponse } from "./api-client";
import { ApiClientError } from "./api-client";
import { createButton, createField, createGrid, createLookup, createTabs } from "./ui/components";

export interface PurchasingApi {
  get<T>(path: string, signal?: AbortSignal): Promise<ApiResponse<T>>;
  request<T>(path: string, init?: RequestInit): Promise<ApiResponse<T>>;
}

interface PartyItem {
  publicId: string;
  partyCode: string;
  legalName: string;
  state: string;
}

interface DocumentItem {
  publicId: string;
  number: string;
  state: string;
  supplierCode: string;
  supplierName: string;
  version: number;
  createdAt: string;
}

interface DocumentLine {
  publicId: string;
  sequence: number;
  productCode: string;
  productName: string;
  uomCode: string;
  quantity: number;
  processedQuantity: number;
  remainderQuantity: number;
  unitPrice: number;
  lineDiscountPercent: number;
  taxPercent: number;
}

interface MatchItem {
  publicId: string;
  kind: string;
  state: string;
  supplierInvoicePublicId: string;
  purchaseOrderPublicId: string | null;
  goodsReceiptPublicId: string | null;
  quantityVariance: number;
  priceVariance: number;
  reason: string | null;
}

interface DocumentDetail {
  publicId: string;
  number: string;
  state: string;
  supplierCode: string;
  supplierName: string;
  currencyCode: string;
  paymentTerms: string | null;
  version: number;
  effectiveVersion: number;
  lines: DocumentLine[];
}

export function createPurchasingPage(
  api: PurchasingApi,
  operationKey: () => string = () => crypto.randomUUID()): HTMLElement
{
  const root = document.createElement("section");
  root.className = "mars-foundation-panel mars-component-stack";

  const heading = document.createElement("h1");
  heading.textContent = "Purchasing";

  const authority = document.createElement("p");
  authority.textContent =
    "Purchase Order ticari commitment'tır; Goods Receipt POST fiziksel STOCK IN etkisini Inventory authority üzerinden QUARANTINE'a yazar. Supplier Invoice burada yalnız DRAFT + match authority taşır; payable, Payment ve valuation bu ekranda yoktur.";

  const status = document.createElement("p");
  status.setAttribute("role", "status");
  status.setAttribute("aria-live", "polite");

  const supplier = createLookup<PartyItem>({
    label: "Supplier",
    placeholder: "Party code / yasal ad",
    search: async ({ query, signal }) => {
      const response = await api.get<PartyItem[]>("/parties?search=" + encodeURIComponent(query), signal);
      return { items: response.data.filter(x => x.state === "ACTIVE"), hasMore: false };
    },
    getKey: x => x.publicId,
    getLabel: x => x.partyCode + " — " + x.legalName
  });

  const orderNumber = createField({ id: "purchasing-order-number", label: "PO no", required: true });
  const currency = createField({ id: "purchasing-currency", label: "Currency", required: true });
  currency.input.value = "TRY";
  const productId = createField({ id: "purchasing-product-id", label: "Product Public ID", required: true });
  const uomId = createField({ id: "purchasing-uom-id", label: "UOM Public ID", required: true });
  const quantity = createField({ id: "purchasing-quantity", label: "Qty", required: true });
  quantity.input.type = "number"; quantity.input.value = "1";
  const unitPrice = createField({ id: "purchasing-price", label: "Unit price", required: true });
  unitPrice.input.type = "number"; unitPrice.input.value = "0";
  const tax = createField({ id: "purchasing-tax", label: "Tax %", required: true });
  tax.input.type = "number"; tax.input.value = "20";
  const createOrder = createButton({ label: "Purchase Order oluştur", variant: "primary", onClick: () => { void createOrderAsync(); } });

  let selectedOrder: DocumentItem | null = null;
  let selectedOrderDetail: DocumentDetail | null = null;
  let selectedReceipt: DocumentItem | null = null;
  let selectedInvoice: DocumentItem | null = null;

  const orderGrid = grid("Purchase Orders", row => { void selectOrder(row); });
  const receiptGrid = grid("Goods Receipts", row => {
    selectedReceipt = row;
    receiptVersion.input.value = String(row.version);
    status.textContent = "Goods Receipt seçildi: " + row.number;
  });
  const invoiceGrid = grid("Supplier Invoice DRAFT", row => {
    selectedInvoice = row;
    invoiceVersion.input.value = String(row.version);
    status.textContent = "Supplier Invoice DRAFT seçildi: " + row.number;
  });

  const orderDetail = document.createElement("pre");
  orderDetail.className = "mars-proof-output";

  const confirmOrder = createButton({ label: "PO Confirm", onClick: () => { void orderAction("confirm"); } });
  const amendmentLine = createField({ id: "purchasing-amend-line", label: "PO line Public ID" });
  const amendmentDelta = createField({ id: "purchasing-amend-delta", label: "Remainder decrease" });
  amendmentDelta.input.type = "number"; amendmentDelta.input.value = "-1";
  const amendmentReason = createField({ id: "purchasing-amend-reason", label: "Amendment reason" });
  const amendOrder = createButton({ label: "Remainder azalt", onClick: () => { void amendOrderAsync(); } });
  const cancelRemainderReason = createField({ id: "purchasing-cancel-remainder-reason", label: "Cancel remainder reason" });
  const cancelRemainder = createButton({ label: "Kalanı iptal et", onClick: () => { void cancelOrderRemainderAsync(); } });
  const closeOrder = createButton({ label: "PO Close", onClick: () => { void orderAction("close"); } });

  const receiptNumber = createField({ id: "purchasing-receipt-number", label: "Goods Receipt no" });
  const receiptWarehouse = createField({ id: "purchasing-receipt-warehouse", label: "Warehouse Public ID" });
  const receiptLine = createField({ id: "purchasing-receipt-line", label: "PO line Public ID" });
  const receiptQty = createField({ id: "purchasing-receipt-qty", label: "Receipt qty" });
  receiptQty.input.type = "number"; receiptQty.input.value = "1";
  const createReceipt = createButton({ label: "Goods Receipt DRAFT oluştur", onClick: () => { void createReceiptAsync(); } });
  const receiptVersion = createField({ id: "purchasing-receipt-version", label: "Receipt version" });
  receiptVersion.input.type = "number";
  const readyReceipt = createButton({ label: "READY", onClick: () => { void receiptVersionAction("ready"); } });
  const postReceipt = createButton({ label: "POST → Inventory QUARANTINE", variant: "primary", onClick: () => { void postReceiptAsync(); } });
  const receiptCancelReason = createField({ id: "purchasing-receipt-cancel-reason", label: "Cancel reason" });
  const cancelReceipt = createButton({ label: "Receipt iptal", onClick: () => { void cancelReceiptAsync(); } });
  const reverseReceipt = createButton({ label: "Receipt reversal", onClick: () => { void reverseReceiptAsync(); } });
  const returnPreview = createButton({ label: "Purchase Return source preview", onClick: () => { void previewReturnAsync(); } });

  const invoiceNumber = createField({ id: "purchasing-invoice-number", label: "Supplier Invoice DRAFT no" });
  const invoiceMode = select("purchasing-invoice-mode", "Source mode", ["GOODS_RECEIPT", "PURCHASE_ORDER", "DIRECT"]);
  const invoiceSourceDoc = createField({ id: "purchasing-invoice-source-doc", label: "Source document Public ID" });
  const invoiceSourceLine = createField({ id: "purchasing-invoice-source-line", label: "Source line Public ID" });
  const invoiceProduct = createField({ id: "purchasing-invoice-product", label: "Product Public ID" });
  const invoiceUom = createField({ id: "purchasing-invoice-uom", label: "UOM Public ID" });
  const invoiceQty = createField({ id: "purchasing-invoice-qty", label: "Qty" });
  invoiceQty.input.type = "number"; invoiceQty.input.value = "1";
  const invoicePrice = createField({ id: "purchasing-invoice-price", label: "Unit price" });
  invoicePrice.input.type = "number"; invoicePrice.input.value = "0";
  const invoiceTax = createField({ id: "purchasing-invoice-tax", label: "Tax %" });
  invoiceTax.input.type = "number"; invoiceTax.input.value = "20";
  const directReason = createField({ id: "purchasing-direct-reason", label: "Direct invoice reason" });
  const createInvoice = createButton({ label: "Supplier Invoice DRAFT oluştur", variant: "primary", onClick: () => { void createInvoiceAsync(false); } });
  const invoiceVersion = createField({ id: "purchasing-invoice-version", label: "Invoice version" });
  invoiceVersion.input.type = "number";
  const replaceInvoice = createButton({ label: "DRAFT güncelle", onClick: () => { void createInvoiceAsync(true); } });
  const invoiceCancelReason = createField({ id: "purchasing-invoice-cancel-reason", label: "Invoice cancel reason" });
  const cancelInvoice = createButton({ label: "Invoice DRAFT iptal", onClick: () => { void cancelInvoiceAsync(); } });
  const matchPreview = createButton({ label: "2-way / 3-way match preview", onClick: () => { void previewMatchAsync(); } });
  const matchEvidence = document.createElement("pre");
  matchEvidence.className = "mars-proof-output";
  const matchId = createField({ id: "purchasing-match-id", label: "Match Public ID" });
  const matchApprovalReason = createField({ id: "purchasing-match-approval-reason", label: "Match approval reason" });
  const approveMatch = createButton({ label: "Match exception approve", onClick: () => { void decideMatchAsync("APPROVED"); } });
  const rejectMatch = createButton({ label: "Match exception reject", onClick: () => { void decideMatchAsync("REJECTED"); } });

  const poPanel = panel([
    supplier.element, orderNumber.element, currency.element, productId.element, uomId.element,
    quantity.element, unitPrice.element, tax.element, createOrder, orderGrid.element, orderDetail,
    confirmOrder, amendmentLine.element, amendmentDelta.element, amendmentReason.element, amendOrder,
    cancelRemainderReason.element, cancelRemainder, closeOrder
  ]);

  const receiptPanel = panel([
    receiptGrid.element, receiptNumber.element, receiptWarehouse.element, receiptLine.element,
    receiptQty.element, createReceipt, receiptVersion.element, readyReceipt, postReceipt,
    receiptCancelReason.element, cancelReceipt, reverseReceipt, returnPreview
  ]);

  const invoicePanel = panel([
    invoiceGrid.element, invoiceNumber.element, invoiceMode.root, invoiceSourceDoc.element,
    invoiceSourceLine.element, invoiceProduct.element, invoiceUom.element, invoiceQty.element,
    invoicePrice.element, invoiceTax.element, directReason.element, createInvoice,
    invoiceVersion.element, replaceInvoice, invoiceCancelReason.element, cancelInvoice, matchPreview,
    matchEvidence, matchId.element, matchApprovalReason.element, approveMatch, rejectMatch
  ]);

  const tabs = createTabs([
    { id: "purchasing-orders", label: "Purchase Orders", panel: poPanel },
    { id: "purchasing-receipts", label: "Goods Receipt", panel: receiptPanel },
    { id: "purchasing-invoices", label: "Supplier Invoice DRAFT / Match", panel: invoicePanel }
  ]);

  root.append(heading, authority, status, tabs.element);
  queueMicrotask(() => { void refreshAll(); });
  return root;

  function grid(caption: string, selectRow: (row: DocumentItem) => void) {
    return createGrid<DocumentItem>({
      caption,
      columns: [
        { key: "number", header: "No", value: x => x.number },
        { key: "supplier", header: "Supplier", value: x => x.supplierCode + " — " + x.supplierName },
        { key: "state", header: "State", value: x => x.state },
        { key: "version", header: "Version", value: x => x.version, align: "end" }
      ],
      rows: [],
      state: { kind: "loading" },
      onRowActivate: selectRow
    });
  }

  async function refreshAll(): Promise<void> {
    await Promise.all([
      load("/purchasing/orders", orderGrid),
      load("/purchasing/receipts", receiptGrid),
      load("/purchasing/invoices", invoiceGrid),
      loadMatchEvidence()
    ]);
  }

  async function load(path: string, target: ReturnType<typeof grid>): Promise<void> {
    try {
      target.setRows([], { kind: "loading" });
      const response = await api.get<DocumentItem[]>(path);
      target.setRows(response.data, response.data.length ? { kind: "ready" } : { kind: "empty", message: "Kayıt yok." });
    } catch (error) {
      target.setRows([], { kind: "error", message: message(error) });
    }
  }

  async function selectOrder(row: DocumentItem): Promise<void> {
    selectedOrder = row;
    try {
      const response = await api.get<DocumentDetail>("/purchasing/orders/" + row.publicId);
      selectedOrderDetail = response.data;
      orderDetail.textContent = response.data.lines.map(x =>
        x.sequence + ". " + x.productCode + " " + x.quantity + " " + x.uomCode +
        " / processed " + x.processedQuantity + " / remainder " + x.remainderQuantity +
        " / line " + x.publicId).join("\n");
      const first = response.data.lines[0];
      if (first) {
        amendmentLine.input.value = first.publicId;
        receiptLine.input.value = first.publicId;
      }
    } catch (error) {
      orderDetail.textContent = message(error);
    }
  }

  async function createOrderAsync(): Promise<void> {
    const party = supplier.getSelected();
    if (!party) return setStatus("Supplier seçin.");
    await mutate("/purchasing/orders", "POST", {
      number: orderNumber.input.value.trim(),
      supplierPartyPublicId: party.publicId,
      currencyCode: currency.input.value.trim().toUpperCase(),
      paymentTerms: null,
      documentDiscountPercent: 0,
      lines: [{
        sequence: 1,
        productPublicId: productId.input.value.trim(),
        variantPublicId: null,
        uomPublicId: uomId.input.value.trim(),
        quantity: number(quantity.input.value),
        unitPrice: number(unitPrice.input.value),
        lineDiscountPercent: 0,
        taxPercent: number(tax.input.value)
      }]
    }, "Purchase Order DRAFT oluşturuldu.");
  }

  async function orderAction(action: "confirm" | "close"): Promise<void> {
    if (!selectedOrder) return setStatus("Purchase Order seçin.");
    await mutate("/purchasing/orders/" + selectedOrder.publicId + "/" + action, "POST", {
      version: selectedOrderDetail?.version ?? selectedOrder.version
    }, "Purchase Order state güncellendi.");
  }

  async function amendOrderAsync(): Promise<void> {
    if (!selectedOrder) return setStatus("Purchase Order seçin.");
    await mutate("/purchasing/orders/" + selectedOrder.publicId + "/amendments", "POST", {
      version: selectedOrderDetail?.version ?? selectedOrder.version,
      reason: amendmentReason.input.value.trim(),
      deltas: [{
        purchaseOrderLinePublicId: amendmentLine.input.value.trim(),
        quantityDelta: number(amendmentDelta.input.value)
      }]
    }, "Unprocessed remainder kontrollü version amendment ile azaltıldı.");
  }

  async function cancelOrderRemainderAsync(): Promise<void> {
    if (!selectedOrder) return setStatus("Purchase Order seçin.");
    await mutate("/purchasing/orders/" + selectedOrder.publicId + "/cancel-remainder", "POST", {
      version: selectedOrderDetail?.version ?? selectedOrder.version,
      reason: cancelRemainderReason.input.value.trim()
    }, "Purchase Order kalan miktarı iptal edildi.");
  }

  async function createReceiptAsync(): Promise<void> {
    if (!selectedOrder) return setStatus("Purchase Order seçin.");
    await mutate("/purchasing/receipts", "POST", {
      number: receiptNumber.input.value.trim(),
      purchaseOrderPublicId: selectedOrder.publicId,
      purchaseOrderVersion: selectedOrderDetail?.effectiveVersion ?? 1,
      warehousePublicId: receiptWarehouse.input.value.trim(),
      lines: [{
        sequence: 1,
        purchaseOrderLinePublicId: receiptLine.input.value.trim(),
        quantity: number(receiptQty.input.value),
        locationPublicId: null,
        lotPublicId: null,
        serialPublicId: null
      }]
    }, "Goods Receipt DRAFT oluşturuldu.");
  }

  async function receiptVersionAction(action: "ready"): Promise<void> {
    if (!selectedReceipt) return setStatus("Goods Receipt seçin.");
    await mutate("/purchasing/receipts/" + selectedReceipt.publicId + "/" + action, "POST", {
      version: number(receiptVersion.input.value)
    }, "Goods Receipt READY.");
  }

  async function postReceiptAsync(): Promise<void> {
    if (!selectedReceipt) return setStatus("Goods Receipt seçin.");
    await mutate("/purchasing/receipts/" + selectedReceipt.publicId + "/post", "POST", {},
      "Goods Receipt POST tamamlandı; yalnız STOCKABLE satırlar Inventory authority ile QUARANTINE'a işlendi.");
  }

  async function cancelReceiptAsync(): Promise<void> {
    if (!selectedReceipt) return setStatus("Goods Receipt seçin.");
    await mutate("/purchasing/receipts/" + selectedReceipt.publicId + "/cancel", "POST", {
      version: number(receiptVersion.input.value),
      reason: receiptCancelReason.input.value.trim()
    }, "Pre-POST Goods Receipt iptal edildi.");
  }

  async function reverseReceiptAsync(): Promise<void> {
    if (!selectedReceipt) return setStatus("Goods Receipt seçin.");
    await mutate("/purchasing/receipts/" + selectedReceipt.publicId + "/reverse", "POST", {},
      "Goods Receipt reversal Inventory compensating movement ile işlendi.");
  }

  async function previewReturnAsync(): Promise<void> {
    if (!selectedReceipt) return setStatus("Goods Receipt seçin.");
    try {
      const response = await api.get<unknown[]>("/purchasing/return-source-preview?goodsReceiptPublicId=" + selectedReceipt.publicId);
      setStatus("Purchase Return source preview: " + response.data.length + " eligible line.");
    } catch (error) {
      setStatus(message(error));
    }
  }

  async function createInvoiceAsync(replace: boolean): Promise<void> {
    const party = supplier.getSelected();
    if (!party) return setStatus("Supplier seçin.");
    if (replace && !selectedInvoice) return setStatus("Supplier Invoice DRAFT seçin.");
    const mode = invoiceMode.select.value;
    const sourced = mode !== "DIRECT";
    const today = new Date().toISOString().slice(0, 10);
    const body = {
      ...(replace ? { version: number(invoiceVersion.input.value) } : {}),
      number: invoiceNumber.input.value.trim(),
      supplierPartyPublicId: party.publicId,
      sourceMode: mode === "GOODS_RECEIPT" ? 1 : mode === "PURCHASE_ORDER" ? 2 : 3,
      documentDate: today,
      dueDate: today,
      currencyCode: "TRY",
      documentDiscountPercent: 0,
      lines: [{
        sequence: 1,
        productPublicId: invoiceProduct.input.value.trim(),
        variantPublicId: null,
        uomPublicId: invoiceUom.input.value.trim(),
        quantity: number(invoiceQty.input.value),
        unitPrice: number(invoicePrice.input.value),
        lineDiscountPercent: 0,
        taxPercent: number(invoiceTax.input.value),
        sourceDocumentPublicId: sourced ? invoiceSourceDoc.input.value.trim() : null,
        sourceLinePublicId: sourced ? invoiceSourceLine.input.value.trim() : null,
        sourceVersion: null
      }],
      directReason: mode === "DIRECT" ? directReason.input.value.trim() : null
    };
    await mutate(
      replace ? "/purchasing/invoices/" + selectedInvoice!.publicId : "/purchasing/invoices",
      replace ? "PUT" : "POST",
      body,
      "Supplier Invoice DRAFT kaydedildi; financial POST authority yok.");
  }

  async function cancelInvoiceAsync(): Promise<void> {
    if (!selectedInvoice) return setStatus("Supplier Invoice DRAFT seçin.");
    await mutate("/purchasing/invoices/" + selectedInvoice.publicId + "/cancel", "POST", {
      version: number(invoiceVersion.input.value),
      reason: invoiceCancelReason.input.value.trim()
    }, "Supplier Invoice DRAFT iptal edildi.");
  }

  async function loadMatchEvidence(): Promise<void> {
    try {
      const response = await api.get<MatchItem[]>("/purchasing/matches");
      matchEvidence.textContent = response.data.length
        ? response.data.map(x =>
            x.kind + " / " + x.state + " / invoice " + x.supplierInvoicePublicId +
            " / match " + x.publicId +
            (x.reason ? " / " + x.reason : "")).join("\n")
        : "Match evidence yok.";
    } catch (error) {
      matchEvidence.textContent = message(error);
    }
  }

  async function decideMatchAsync(decision: "APPROVED" | "REJECTED"): Promise<void> {
    const id = matchId.input.value.trim();
    if (!id) return setStatus("Match Public ID girin.");
    await mutate("/purchasing/matches/" + id + "/approval", "POST", {
      decision,
      reason: matchApprovalReason.input.value.trim() || null
    }, "Match exception kararı kaydedildi.");
  }

  async function previewMatchAsync(): Promise<void> {
    const mode = invoiceMode.select.value;
    if (mode === "DIRECT") return setStatus("DIRECT invoice match exception approval evidence kullanır.");
    const sourceMode = mode === "GOODS_RECEIPT" ? 1 : 2;
    try {
      const response = await api.get<Record<string, unknown>>(
        "/purchasing/match-preview?mode=" + sourceMode + "&sourceDocumentPublicId=" + encodeURIComponent(invoiceSourceDoc.input.value.trim()));
      setStatus("Match preview: " + JSON.stringify(response.data));
    } catch (error) {
      setStatus(message(error));
    }
  }

  async function mutate(path: string, method: "POST" | "PUT", body: unknown, success: string): Promise<void> {
    try {
      await api.request(path, {
        method,
        headers: { "Content-Type": "application/json", "Idempotency-Key": operationKey() },
        body: JSON.stringify(body)
      });
      setStatus(success);
      await refreshAll();
    } catch (error) {
      setStatus(message(error));
    }
  }

  function setStatus(value: string): void {
    status.textContent = value;
  }
}

function panel(children: HTMLElement[]): HTMLElement {
  const element = document.createElement("div");
  element.className = "mars-component-stack";
  element.append(...children);
  return element;
}

function select(id: string, labelText: string, options: string[]) {
  const root = document.createElement("label");
  root.htmlFor = id;
  root.textContent = labelText + " ";
  const element = document.createElement("select");
  element.id = id;
  element.name = id;
  for (const value of options) {
    const option = document.createElement("option");
    option.value = value;
    option.textContent = value;
    element.append(option);
  }
  root.append(element);
  return { root, select: element };
}

function number(value: string): number {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

function message(error: unknown): string {
  return error instanceof ApiClientError
    ? error.message + (error.correlationId ? " / " + error.correlationId : "")
    : error instanceof Error ? error.message : "Beklenmeyen hata.";
}
