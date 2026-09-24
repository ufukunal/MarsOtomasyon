import { ApiClient, ApiClientError } from "./api-client";
import { createButton, createField, createGrid, createTabs } from "./ui/components";

interface WarehouseView {
  publicId: string;
  code: string;
  name: string;
  state: "ACTIVE" | "INACTIVE";
  version: number;
}

interface LocationView {
  publicId: string;
  warehousePublicId: string;
  parentLocationPublicId: string | null;
  code: string;
  name: string;
  stockBearing: boolean;
  state: "ACTIVE" | "INACTIVE";
  version: number;
}

interface StockView {
  productPublicId: string;
  variantPublicId: string | null;
  warehousePublicId: string;
  onHand: number;
  availableOnHand: number;
  reserved: number;
  availableToReserve: number;
}

interface PositionView {
  productPublicId: string;
  variantPublicId: string | null;
  warehousePublicId: string;
  locationPublicId: string | null;
  disposition: string;
  lotPublicId: string | null;
  serialPublicId: string | null;
  quantity: number;
}

interface MovementView {
  publicId: string;
  productPublicId: string;
  variantPublicId: string | null;
  baseQuantity: number;
  sourceWarehousePublicId: string | null;
  sourceDisposition: string | null;
  targetWarehousePublicId: string | null;
  targetDisposition: string | null;
  sourceModule: string;
  sourceEntityType: string;
  postedAt: string;
}

interface ReservationView {
  publicId: string;
  salesOrderPublicId: string;
  salesOrderVersion: number;
  salesOrderLinePublicId: string;
  productPublicId: string;
  variantPublicId: string | null;
  warehousePublicId: string;
  currentBaseQuantity: number;
  createdAt: string;
}

export function createInventoryPage(
  api: Pick<ApiClient, "get" | "request">,
  operationKey: () => string = defaultOperationKey): HTMLElement
{
  const root = document.createElement("section");
  root.className = "mars-foundation-panel mars-component-stack";

  const heading = document.createElement("h1");
  heading.textContent = "Inventory";

  const authority = document.createElement("p");
  authority.textContent =
    "Fiziksel miktar Inventory Ledger hareketlerinden, rezervasyon ayrı non-physical kayıtlardan türetilir. Bu ekranda doğrudan stok overwrite veya maliyet otoritesi yoktur.";

  const status = document.createElement("p");
  status.setAttribute("aria-live", "polite");

  let warehouses: WarehouseView[] = [];
  let selectedWarehouse: WarehouseView | null = null;

  const stockGrid = createGrid<StockView>({
    caption: "Stok özeti",
    columns: [
      { key: "product", header: "Product", value: row => row.productPublicId },
      { key: "variant", header: "Variant", value: row => row.variantPublicId ?? "" },
      { key: "warehouse", header: "Warehouse", value: row => row.warehousePublicId },
      { key: "onHand", header: "On hand", value: row => row.onHand, align: "end" },
      { key: "available", header: "Available", value: row => row.availableOnHand, align: "end" },
      { key: "reserved", header: "Reserved", value: row => row.reserved, align: "end" },
      { key: "atr", header: "Available to reserve", value: row => row.availableToReserve, align: "end" }
    ],
    rows: [],
    state: { kind: "loading" }
  });

  const positionGrid = createGrid<PositionView>({
    caption: "Fiziksel pozisyonlar",
    columns: [
      { key: "product", header: "Product", value: row => row.productPublicId },
      { key: "warehouse", header: "Warehouse", value: row => row.warehousePublicId },
      { key: "location", header: "Location", value: row => row.locationPublicId ?? "" },
      { key: "disposition", header: "Disposition", value: row => row.disposition },
      { key: "lot", header: "Lot", value: row => row.lotPublicId ?? "" },
      { key: "serial", header: "Serial", value: row => row.serialPublicId ?? "" },
      { key: "qty", header: "Qty", value: row => row.quantity, align: "end" }
    ],
    rows: [],
    state: { kind: "loading" }
  });

  const stockPanel = document.createElement("div");
  const refreshStock = createButton({
    label: "Stok sorgusunu yenile",
    variant: "primary",
    onClick: () => { void loadStock(); }
  });
  stockPanel.append(refreshStock, stockGrid.element, positionGrid.element);

  const warehouseCode = createField({
    id: "inventory-warehouse-code",
    label: "Warehouse kodu",
    required: true
  });
  const warehouseName = createField({
    id: "inventory-warehouse-name",
    label: "Warehouse adı",
    required: true
  });
  const createWarehouse = createButton({
    label: "Warehouse oluştur",
    variant: "primary",
    onClick: () => { void createWarehouseAsync(); }
  });
  const toggleWarehouse = createButton({
    label: "Seçili Warehouse durumunu değiştir",
    variant: "secondary",
    disabled: true,
    onClick: () => { void toggleWarehouseAsync(); }
  });

  const warehouseGrid = createGrid<WarehouseView>({
    caption: "Warehouses",
    columns: [
      { key: "code", header: "Kod", value: row => row.code },
      { key: "name", header: "Ad", value: row => row.name },
      { key: "state", header: "Durum", value: row => row.state },
      { key: "version", header: "Version", value: row => row.version, align: "end" }
    ],
    rows: [],
    state: { kind: "loading" },
    onRowActivate: row => {
      selectedWarehouse = row;
      toggleWarehouse.disabled = false;
      void loadLocations(row.publicId);
    }
  });

  const locationCode = createField({
    id: "inventory-location-code",
    label: "Location kodu",
    required: true
  });
  const locationName = createField({
    id: "inventory-location-name",
    label: "Location adı",
    required: true
  });
  const stockBearing = document.createElement("label");
  stockBearing.className = "mars-field";
  const stockBearingInput = document.createElement("input");
  stockBearingInput.type = "checkbox";
  stockBearingInput.id = "inventory-location-stock-bearing";
  stockBearingInput.checked = true;
  const stockBearingLabel = document.createElement("span");
  stockBearingLabel.textContent = "Stock-bearing";
  stockBearing.append(stockBearingInput, stockBearingLabel);

  const createLocation = createButton({
    label: "Seçili Warehouse altında Location oluştur",
    variant: "primary",
    onClick: () => { void createLocationAsync(); }
  });

  const locationGrid = createGrid<LocationView>({
    caption: "Locations",
    columns: [
      { key: "code", header: "Kod", value: row => row.code },
      { key: "name", header: "Ad", value: row => row.name },
      { key: "stockBearing", header: "Stock-bearing", value: row => row.stockBearing ? "Evet" : "Hayır" },
      { key: "state", header: "Durum", value: row => row.state },
      { key: "version", header: "Version", value: row => row.version, align: "end" }
    ],
    rows: [],
    state: { kind: "empty", message: "Warehouse seçilmedi." }
  });

  const masterPanel = document.createElement("div");
  masterPanel.append(
    warehouseCode.element,
    warehouseName.element,
    createWarehouse,
    toggleWarehouse,
    warehouseGrid.element,
    locationCode.element,
    locationName.element,
    stockBearing,
    createLocation,
    locationGrid.element);

  const movementGrid = createGrid<MovementView>({
    caption: "Inventory movement history",
    columns: [
      { key: "time", header: "Posted", value: row => row.postedAt },
      { key: "product", header: "Product", value: row => row.productPublicId },
      { key: "qty", header: "Base qty", value: row => row.baseQuantity, align: "end" },
      { key: "from", header: "From", value: row => row.sourceDisposition ?? "EXTERNAL" },
      { key: "to", header: "To", value: row => row.targetDisposition ?? "EXTERNAL" },
      { key: "source", header: "Source", value: row => row.sourceModule + "/" + row.sourceEntityType }
    ],
    rows: [],
    state: { kind: "loading" }
  });

  const reservationGrid = createGrid<ReservationView>({
    caption: "Reservation history/current remainder",
    columns: [
      { key: "created", header: "Created", value: row => row.createdAt },
      { key: "product", header: "Product", value: row => row.productPublicId },
      { key: "warehouse", header: "Warehouse", value: row => row.warehousePublicId },
      { key: "source", header: "Sales source", value: row => row.salesOrderPublicId + " v" + row.salesOrderVersion },
      { key: "remaining", header: "Reserved", value: row => row.currentBaseQuantity, align: "end" }
    ],
    rows: [],
    state: { kind: "loading" }
  });

  const tracePanel = document.createElement("div");
  const serialId = createField({
    id: "inventory-serial-id",
    label: "Serial public ID",
    help: "Serial current position ve hareket zincirini açar."
  });
  const serialResult = document.createElement("pre");
  serialResult.className = "mars-proof-output";
  const readSerial = createButton({
    label: "Serial trace getir",
    onClick: () => { void loadSerial(); }
  });
  const refreshTrace = createButton({
    label: "Movement / Reservation yenile",
    onClick: () => { void loadTrace(); }
  });
  tracePanel.append(
    refreshTrace,
    movementGrid.element,
    reservationGrid.element,
    serialId.element,
    readSerial,
    serialResult);

  const tabs = createTabs([
    { id: "inventory-stock", label: "Stock", panel: stockPanel },
    { id: "inventory-master", label: "Warehouse / Location", panel: masterPanel },
    { id: "inventory-trace", label: "Trace / Reservation", panel: tracePanel }
  ]);

  root.append(heading, authority, status, tabs.element);

  queueMicrotask(() => {
    void Promise.all([loadWarehouses(), loadStock(), loadTrace()]);
  });

  return root;

  async function loadWarehouses(): Promise<void> {
    try {
      warehouseGrid.setRows([], { kind: "loading" });
      const response = await api.get<WarehouseView[]>("/inventory/warehouses");
      warehouses = response.data;
      warehouseGrid.setRows(
        warehouses,
        warehouses.length === 0
          ? { kind: "empty", message: "Warehouse kaydı yok." }
          : { kind: "ready" });
    } catch (error) {
      warehouseGrid.setRows([], { kind: "error", message: errorMessage(error) });
    }
  }

  async function loadLocations(warehousePublicId: string): Promise<void> {
    try {
      locationGrid.setRows([], { kind: "loading" });
      const response = await api.get<LocationView[]>(
        "/inventory/locations?warehousePublicId=" +
        encodeURIComponent(warehousePublicId));
      locationGrid.setRows(
        response.data,
        response.data.length === 0
          ? { kind: "empty", message: "Location kaydı yok." }
          : { kind: "ready" });
    } catch (error) {
      locationGrid.setRows([], { kind: "error", message: errorMessage(error) });
    }
  }

  async function loadStock(): Promise<void> {
    try {
      stockGrid.setRows([], { kind: "loading" });
      positionGrid.setRows([], { kind: "loading" });
      const [stock, positions] = await Promise.all([
        api.get<StockView[]>("/inventory/stock"),
        api.get<PositionView[]>("/inventory/positions")
      ]);
      stockGrid.setRows(
        stock.data,
        stock.data.length === 0
          ? { kind: "empty", message: "Ledger-derived stok yok." }
          : { kind: "ready" });
      positionGrid.setRows(
        positions.data,
        positions.data.length === 0
          ? { kind: "empty", message: "Fiziksel pozisyon yok." }
          : { kind: "ready" });
    } catch (error) {
      const message = errorMessage(error);
      stockGrid.setRows([], { kind: "error", message });
      positionGrid.setRows([], { kind: "error", message });
    }
  }

  async function loadTrace(): Promise<void> {
    try {
      movementGrid.setRows([], { kind: "loading" });
      reservationGrid.setRows([], { kind: "loading" });
      const [movements, reservations] = await Promise.all([
        api.get<MovementView[]>("/inventory/movements"),
        api.get<ReservationView[]>("/inventory/reservations")
      ]);
      movementGrid.setRows(
        movements.data,
        movements.data.length === 0
          ? { kind: "empty", message: "Posted movement yok." }
          : { kind: "ready" });
      reservationGrid.setRows(
        reservations.data,
        reservations.data.length === 0
          ? { kind: "empty", message: "Reservation kaydı yok." }
          : { kind: "ready" });
    } catch (error) {
      const message = errorMessage(error);
      movementGrid.setRows([], { kind: "error", message });
      reservationGrid.setRows([], { kind: "error", message });
    }
  }

  async function createWarehouseAsync(): Promise<void> {
    const code = warehouseCode.input.value.trim();
    const name = warehouseName.input.value.trim();
    if (!code || !name) {
      status.textContent = "Warehouse kodu ve adı zorunlu.";
      return;
    }

    try {
      await api.request("/inventory/warehouses", {
        method: "POST",
        headers: jsonHeaders(operationKey()),
        body: JSON.stringify({ code, name })
      });
      status.textContent = "Warehouse oluşturuldu.";
      warehouseCode.input.value = "";
      warehouseName.input.value = "";
      await loadWarehouses();
    } catch (error) {
      status.textContent = errorMessage(error);
    }
  }

  async function toggleWarehouseAsync(): Promise<void> {
    if (!selectedWarehouse) {
      status.textContent = "Önce Warehouse satırını açın.";
      return;
    }

    const target = selectedWarehouse.state === "ACTIVE" ? "INACTIVE" : "ACTIVE";
    try {
      await api.request(
        "/inventory/warehouses/" + selectedWarehouse.publicId + "/state",
        {
          method: "POST",
          headers: jsonHeaders(operationKey()),
          body: JSON.stringify({
            version: selectedWarehouse.version,
            state: target
          })
        });
      status.textContent = "Warehouse durumu değiştirildi.";
      selectedWarehouse = null;
      toggleWarehouse.disabled = true;
      await loadWarehouses();
    } catch (error) {
      status.textContent = errorMessage(error);
    }
  }

  async function createLocationAsync(): Promise<void> {
    if (!selectedWarehouse) {
      status.textContent = "Location için önce Warehouse seçin.";
      return;
    }

    const code = locationCode.input.value.trim();
    const name = locationName.input.value.trim();
    if (!code || !name) {
      status.textContent = "Location kodu ve adı zorunlu.";
      return;
    }

    try {
      await api.request("/inventory/locations", {
        method: "POST",
        headers: jsonHeaders(operationKey()),
        body: JSON.stringify({
          warehousePublicId: selectedWarehouse.publicId,
          parentLocationPublicId: null,
          code,
          name,
          stockBearing: stockBearingInput.checked
        })
      });
      status.textContent = "Location oluşturuldu.";
      locationCode.input.value = "";
      locationName.input.value = "";
      await loadLocations(selectedWarehouse.publicId);
    } catch (error) {
      status.textContent = errorMessage(error);
    }
  }

  async function loadSerial(): Promise<void> {
    const value = serialId.input.value.trim();
    if (!value) {
      serialResult.textContent = "Serial public ID gerekli.";
      return;
    }

    try {
      const response = await api.get<unknown>(
        "/inventory/serials/" + encodeURIComponent(value));
      serialResult.textContent = JSON.stringify(response.data, null, 2);
    } catch (error) {
      serialResult.textContent = errorMessage(error);
    }
  }
}

function jsonHeaders(operationKey: string): Headers {
  const headers = new Headers();
  headers.set("Content-Type", "application/json");
  headers.set("Idempotency-Key", operationKey);
  return headers;
}

function defaultOperationKey(): string {
  return "inventory-web-" + crypto.randomUUID();
}

function errorMessage(error: unknown): string {
  return error instanceof ApiClientError
    ? error.message + " (" + error.code + ")"
    : "Inventory işlemi tamamlanamadı.";
}
