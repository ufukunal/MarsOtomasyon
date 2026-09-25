import type { ApiResponse } from "./api-client";
import { ApiClientError } from "./api-client";
import { createButton, createField, createGrid, createLookup, createTabs } from "./ui/components";

export interface SalesApi {
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
  customerCode: string;
  customerName: string;
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

interface DocumentDetail {
  publicId: string;
  number: string;
  state: string;
  customerCode: string;
  customerName: string;
  currencyCode: string;
  paymentTerms: string | null;
  version: number;
  lines: DocumentLine[];
}

interface InvoiceItem extends DocumentItem {}

export function createSalesPage(
  api: SalesApi,
  operationKey: () => string = () => crypto.randomUUID(),
  initialTabId?: string): HTMLElement
{
  const root = document.createElement("section");
  root.className = "mars-foundation-panel mars-component-stack";

  const heading = document.createElement("h1");
  heading.textContent = "Satış Yönetimi";

  const authority = document.createElement("p");
  authority.textContent =
    "Quote → Order → explicit Reservation → Dispatch fiziksel akışı Inventory authority üzerinden yürür; Invoice burada yalnız DRAFT commercial/source authority taşır. Paid/open, Account Ledger, COGS ve editable stock authority bu ekranda yoktur.";

  const status = document.createElement("p");
  status.setAttribute("role", "status");
  status.setAttribute("aria-live", "polite");

  const customer = createLookup<PartyItem>({
    label: "Customer",
    placeholder: "Party code / yasal ad",
    search: async ({ query, signal }) => {
      const response = await api.get<PartyItem[]>("/parties?search=" + encodeURIComponent(query), signal);
      const items = response.data.filter(x => x.state === "ACTIVE");
      return { items, hasMore: false };
    },
    getKey: x => x.publicId,
    getLabel: x => x.partyCode + " — " + x.legalName
  });

  const quoteNumber = createField({ id: "sales-quote-number", label: "Quote no", required: true });
  const currency = createField({ id: "sales-currency", label: "Currency", required: true });
  currency.input.value = "TRY";
  const productId = createField({ id: "sales-product-id", label: "Product Public ID", required: true });
  const uomId = createField({ id: "sales-uom-id", label: "UOM Public ID", required: true });
  const quantity = createField({ id: "sales-quantity", label: "Qty", required: true });
  quantity.input.type = "number"; quantity.input.value = "1";
  const unitPrice = createField({ id: "sales-unit-price", label: "Unit price", required: true });
  unitPrice.input.type = "number"; unitPrice.input.value = "0";
  const tax = createField({ id: "sales-tax", label: "Tax %", required: true });
  tax.input.type = "number"; tax.input.value = "20";
  const createQuote = createButton({ label: "Quote oluştur", variant: "primary", onClick: () => { void createQuoteAsync(); } });

  let selectedQuote: DocumentItem | null = null;
  let selectedOrder: DocumentItem | null = null;
  let selectedOrderDetail: DocumentDetail | null = null;
  let selectedDispatch: DocumentItem | null = null;
  let selectedProforma: DocumentItem | null = null;

  const quoteGrid = grid("Quotes", row => {
    selectedQuote = row;
    status.textContent = "Quote seçildi: " + row.number;
  });
  const orderGrid = grid("Sales Orders", row => { void selectOrder(row); });
  const dispatchGrid = grid("Dispatches", row => {
    selectedDispatch = row;
    dispatchVersion.input.value = String(row.version);
    status.textContent = "Dispatch seçildi: " + row.number;
  });
  const invoiceGrid = grid("Invoice drafts", row => {
    status.textContent = "Invoice draft seçildi: " + row.number + " / " + row.state;
  });
  const proformaGrid = grid("Proformas", row => {
    selectedProforma = row;
    proformaVersion.input.value = String(row.version);
    status.textContent = "Proforma seçildi: " + row.number + " / " + row.state;
  });

  const convertOrderNumber = createField({ id: "sales-convert-order-no", label: "Yeni Order no" });
  const quoteRevision = createField({ id: "sales-quote-revision", label: "Quote revision" });
  quoteRevision.input.type = "number"; quoteRevision.input.value = "1";
  const quoteLineId = createField({ id: "sales-quote-line-id", label: "Quote line Public ID" });
  const convertQty = createField({ id: "sales-convert-qty", label: "Convert qty" });
  convertQty.input.type = "number"; convertQty.input.value = "1";
  const convertQuote = createButton({ label: "Seçili Quote → Order", onClick: () => { void convertQuoteAsync(); } });

  const reservationLine = createField({ id: "sales-reservation-line", label: "Order line Public ID" });
  const reservationWarehouse = createField({ id: "sales-reservation-warehouse", label: "Warehouse Public ID" });
  const reservationQty = createField({ id: "sales-reservation-qty", label: "Reservation qty" });
  reservationQty.input.type = "number"; reservationQty.input.value = "1";
  const reserve = createButton({ label: "Explicit Reservation oluştur", onClick: () => { void createReservationAsync(); } });

  const orderDetail = document.createElement("pre");
  orderDetail.className = "mars-proof-output";

  const dispatchNumber = createField({ id: "sales-dispatch-number", label: "Dispatch no" });
  const dispatchLine = createField({ id: "sales-dispatch-line", label: "Order line Public ID" });
  const dispatchWarehouse = createField({ id: "sales-dispatch-warehouse", label: "Warehouse Public ID" });
  const dispatchQty = createField({ id: "sales-dispatch-qty", label: "Dispatch qty" });
  dispatchQty.input.type = "number"; dispatchQty.input.value = "1";
  const createDispatch = createButton({ label: "Dispatch DRAFT oluştur", onClick: () => { void createDispatchAsync(); } });
  const dispatchVersion = createField({ id: "sales-dispatch-version", label: "Dispatch version" });
  dispatchVersion.input.type = "number";
  const readyDispatch = createButton({ label: "READY", onClick: () => { void transitionDispatch("ready"); } });
  const postDispatch = createButton({ label: "POST → Inventory STOCK OUT", variant: "primary", onClick: () => { void postDispatchAsync(); } });
  const handoffDispatch = createButton({ label: "HANDED_OVER", onClick: () => { void transitionDispatch("handoff"); } });
  const deliverDispatch = createButton({ label: "DELIVERED", onClick: () => { void transitionDispatch("deliver"); } });
  const reversalNumber = createField({ id: "sales-reversal-number", label: "Reversal no" });
  const reversalReason = createField({ id: "sales-reversal-reason", label: "Reversal reason" });
  const reverseDispatch = createButton({ label: "Explicit Dispatch reversal", onClick: () => { void reverseDispatchAsync(); } });

  const invoiceNumber = createField({ id: "sales-invoice-number", label: "Invoice draft no" });
  const invoiceSourceMode = select("sales-invoice-source-mode", "Source mode", ["DIRECT", "ORDER", "DISPATCH"]);
  const invoiceSourceDocument = createField({ id: "sales-invoice-source-document", label: "Source document Public ID" });
  const invoiceSourceLine = createField({ id: "sales-invoice-source-line", label: "Source line Public ID" });
  const invoiceProduct = createField({ id: "sales-invoice-product", label: "Product Public ID" });
  const invoiceUom = createField({ id: "sales-invoice-uom", label: "UOM Public ID" });
  const invoiceQty = createField({ id: "sales-invoice-qty", label: "Qty" });
  invoiceQty.input.type = "number"; invoiceQty.input.value = "1";
  const invoicePrice = createField({ id: "sales-invoice-price", label: "Unit price" });
  invoicePrice.input.type = "number"; invoicePrice.input.value = "0";
  const invoiceTax = createField({ id: "sales-invoice-tax", label: "Tax %" });
  invoiceTax.input.type = "number"; invoiceTax.input.value = "20";
  const createInvoice = createButton({ label: "Invoice DRAFT oluştur", variant: "primary", onClick: () => { void createInvoiceAsync(); } });

  const proformaNumber = createField({ id: "sales-proforma-number", label: "Proforma no" });
  const proformaSourceMode = select("sales-proforma-source-mode", "Source mode", ["QUOTE", "ORDER"]);
  const proformaSourceDocument = createField({ id: "sales-proforma-source-document", label: "Source document Public ID" });
  const createProforma = createButton({ label: "Proforma oluştur", onClick: () => { void createProformaAsync(); } });
  const proformaVersion = createField({ id: "sales-proforma-version", label: "Proforma version" });
  proformaVersion.input.type = "number";
  const proformaCancelReason = createField({ id: "sales-proforma-cancel-reason", label: "Cancel reason" });
  const cancelProforma = createButton({ label: "Proforma iptal", onClick: () => { void cancelProformaAsync(); } });
  const exportProforma = createButton({ label: "Proforma export", onClick: () => { void exportProformaAsync(); } });

  const quotePanel = panel([
    customer.element, quoteNumber.element, currency.element, productId.element, uomId.element,
    quantity.element, unitPrice.element, tax.element, createQuote, quoteGrid.element,
    convertOrderNumber.element, quoteRevision.element, quoteLineId.element, convertQty.element, convertQuote
  ]);
  const orderPanel = panel([
    orderGrid.element, orderDetail, reservationLine.element, reservationWarehouse.element,
    reservationQty.element, reserve
  ]);
  const dispatchPanel = panel([
    dispatchGrid.element, dispatchNumber.element, dispatchLine.element, dispatchWarehouse.element,
    dispatchQty.element, createDispatch, dispatchVersion.element, readyDispatch, postDispatch,
    handoffDispatch, deliverDispatch, reversalNumber.element, reversalReason.element, reverseDispatch
  ]);
  const invoicePanel = panel([
    invoiceGrid.element, invoiceNumber.element, invoiceSourceMode.root, invoiceSourceDocument.element,
    invoiceSourceLine.element, invoiceProduct.element, invoiceUom.element, invoiceQty.element,
    invoicePrice.element, invoiceTax.element, createInvoice
  ]);
  const proformaPanel = panel([
    proformaGrid.element, proformaNumber.element, proformaSourceMode.root,
    proformaSourceDocument.element, createProforma, proformaVersion.element,
    proformaCancelReason.element, cancelProforma, exportProforma
  ]);

  const tabs = createTabs([
    { id: "sales-quotes", label: "Teklifler", panel: quotePanel },
    { id: "sales-orders", label: "Satış Siparişleri / Rezervasyon", panel: orderPanel },
    { id: "sales-dispatches", label: "Sevkiyat / İrsaliye", panel: dispatchPanel },
    { id: "sales-invoices", label: "Satış Faturaları", panel: invoicePanel },
    { id: "sales-proformas", label: "Proforma Faturalar", panel: proformaPanel }
  ]);

  if (initialTabId) tabs.activate(initialTabId);
  root.append(heading, authority, status, tabs.element);
  queueMicrotask(() => { void refreshAll(); });
  return root;

  function grid(caption: string, selectRow: (row: DocumentItem) => void) {
    return createGrid<DocumentItem>({
      caption,
      columns: [
        { key: "number", header: "No", value: x => x.number },
        { key: "customer", header: "Customer", value: x => x.customerCode + " — " + x.customerName },
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
      load("/sales/quotes", quoteGrid),
      load("/sales/orders", orderGrid),
      load("/sales/dispatches", dispatchGrid),
      load("/sales/invoices", invoiceGrid),
      load("/sales/proformas", proformaGrid)
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
      const response = await api.get<DocumentDetail>("/sales/orders/" + row.publicId);
      selectedOrderDetail = response.data;
      orderDetail.textContent = response.data.lines.map(x =>
        x.sequence + ". " + x.productCode + " " + x.quantity + " " + x.uomCode +
        " / processed " + x.processedQuantity + " / remainder " + x.remainderQuantity +
        " / line " + x.publicId).join("\n");
      if (response.data.lines[0]) {
        reservationLine.input.value = response.data.lines[0].publicId;
        dispatchLine.input.value = response.data.lines[0].publicId;
      }
    } catch (error) {
      orderDetail.textContent = message(error);
    }
  }

  async function createQuoteAsync(): Promise<void> {
    const party = customer.getSelected();
    if (!party) return setStatus("Customer seçin.");
    await mutate("/sales/quotes", "POST", {
      number: quoteNumber.input.value.trim(),
      customerPartyPublicId: party.publicId,
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
    }, "Quote oluşturuldu.");
  }

  async function convertQuoteAsync(): Promise<void> {
    if (!selectedQuote) return setStatus("Quote seçin.");
    await mutate("/sales/quotes/" + selectedQuote.publicId + "/convert", "POST", {
      quoteRevisionNumber: number(quoteRevision.input.value),
      orderNumber: convertOrderNumber.input.value.trim(),
      lines: [{ quoteLinePublicId: quoteLineId.input.value.trim(), quantity: number(convertQty.input.value) }]
    }, "Quote kaynak bağlantısıyla Order oluşturuldu.");
  }

  async function createReservationAsync(): Promise<void> {
    if (!selectedOrder) return setStatus("Order seçin.");
    await mutate("/sales/reservations", "POST", {
      salesOrderPublicId: selectedOrder.publicId,
      salesOrderVersion: selectedOrderDetail?.version ?? selectedOrder.version,
      salesOrderLinePublicId: reservationLine.input.value.trim(),
      warehousePublicId: reservationWarehouse.input.value.trim(),
      quantity: number(reservationQty.input.value)
    }, "Inventory-owned Reservation oluşturuldu.");
  }

  async function createDispatchAsync(): Promise<void> {
    if (!selectedOrder) return setStatus("Order seçin.");
    await mutate("/sales/dispatches", "POST", {
      number: dispatchNumber.input.value.trim(),
      salesOrderPublicId: selectedOrder.publicId,
      salesOrderVersion: selectedOrderDetail?.version ?? selectedOrder.version,
      warehousePublicId: dispatchWarehouse.input.value.trim(),
      lines: [{
        sequence: 1,
        salesOrderLinePublicId: dispatchLine.input.value.trim(),
        quantity: number(dispatchQty.input.value),
        locationPublicId: null,
        lotPublicId: null,
        serialPublicId: null,
        reservationPublicId: null
      }]
    }, "Dispatch DRAFT oluşturuldu.");
  }

  async function transitionDispatch(action: "ready" | "handoff" | "deliver"): Promise<void> {
    if (!selectedDispatch) return setStatus("Dispatch seçin.");
    await mutate("/sales/dispatches/" + selectedDispatch.publicId + "/" + action, "POST", {
      version: number(dispatchVersion.input.value)
    }, "Dispatch state güncellendi.");
  }

  async function postDispatchAsync(): Promise<void> {
    if (!selectedDispatch) return setStatus("Dispatch seçin.");
    await mutate("/sales/dispatches/" + selectedDispatch.publicId + "/post", "POST", {},
      "Dispatch POST tamamlandı; fiziksel etki Inventory authority üzerinden yürütüldü.");
  }

  async function reverseDispatchAsync(): Promise<void> {
    if (!selectedDispatch) return setStatus("Dispatch seçin.");
    await mutate("/sales/dispatches/" + selectedDispatch.publicId + "/reverse", "POST", {
      reversalNumber: reversalNumber.input.value.trim(),
      reason: reversalReason.input.value.trim()
    }, "Explicit Dispatch reversal oluşturuldu; Reservation otomatik geri yaratılmadı.");
  }

  async function createInvoiceAsync(): Promise<void> {
    const party = customer.getSelected();
    if (!party) return setStatus("Invoice için Customer seçimini Quotes sekmesindeki lookup'tan yapın.");
    const mode = invoiceSourceMode.select.value;
    const sourced = mode !== "DIRECT";
    const today = new Date().toISOString().slice(0, 10);
    await mutate("/sales/invoices", "POST", {
      number: invoiceNumber.input.value.trim(),
      customerPartyPublicId: party.publicId,
      sourceMode: mode === "ORDER" ? 2 : mode === "DISPATCH" ? 1 : 3,
      documentDate: today,
      dueDate: today,
      currencyCode: currency.input.value.trim().toUpperCase() || "TRY",
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
        sourceDocumentPublicId: sourced ? invoiceSourceDocument.input.value.trim() : null,
        sourceLinePublicId: sourced ? invoiceSourceLine.input.value.trim() : null,
        sourceVersion: null
      }]
    }, "Invoice DRAFT oluşturuldu. Posting authority bu tranche'ta yok.");
  }

  async function createProformaAsync(): Promise<void> {
    const sourceMode = proformaSourceMode.select.value;
    await mutate("/sales/proformas", "POST", {
      number: proformaNumber.input.value.trim(),
      sourceMode: sourceMode === "ORDER" ? 2 : 1,
      sourceDocumentPublicId: proformaSourceDocument.input.value.trim()
    }, "Proforma oluşturuldu. Informational authority; RES/STOCK/ACCOUNT/CASH-BANK/COGS etkisi yok.");
  }

  async function cancelProformaAsync(): Promise<void> {
    if (!selectedProforma) return setStatus("Proforma seçin.");
    await mutate("/sales/proformas/" + selectedProforma.publicId + "/cancel", "POST", {
      version: number(proformaVersion.input.value),
      reason: proformaCancelReason.input.value.trim()
    }, "Proforma iptal edildi; hiçbir ledger etkisi oluşmadı.");
  }

  async function exportProformaAsync(): Promise<void> {
    if (!selectedProforma) return setStatus("Proforma seçin.");
    try {
      await api.get("/sales/proformas/" + selectedProforma.publicId + "/export");
      setStatus("Proforma export verisi hazırlandı.");
    } catch (error) {
      setStatus(message(error));
    }
  }

  async function mutate(path: string, method: "POST" | "PUT", body: unknown, success: string): Promise<void> {
    try {
      await api.request(path, {
        method,
        headers: jsonHeaders(operationKey()),
        body: JSON.stringify(body)
      });
      setStatus(success);
      await refreshAll();
    } catch (error) {
      setStatus(message(error));
    }
  }

  function setStatus(value: string): void { status.textContent = value; }
}

function panel(children: HTMLElement[]): HTMLElement {
  const element = document.createElement("div");
  element.className = "mars-component-stack";
  element.append(...children);
  return element;
}

function select(id: string, labelText: string, values: string[]) {
  const root = document.createElement("label");
  root.className = "mars-field";
  const label = document.createElement("span"); label.textContent = labelText;
  const element = document.createElement("select"); element.id = id;
  for (const value of values) {
    const option = document.createElement("option"); option.value = value; option.textContent = value; element.append(option);
  }
  root.append(label, element);
  return { root, select: element };
}

function jsonHeaders(key: string): Headers {
  const headers = new Headers();
  headers.set("Content-Type", "application/json");
  headers.set("Idempotency-Key", key);
  return headers;
}

function number(value: string): number {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

function message(error: unknown): string {
  return error instanceof ApiClientError ? error.message : error instanceof Error ? error.message : "Sales işlemi tamamlanamadı.";
}
