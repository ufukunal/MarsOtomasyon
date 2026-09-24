import test from "node:test";
import assert from "node:assert/strict";
import { Window } from "happy-dom";

installDom();
const { createInventoryPage } = await import("../src/inventory.ts");

test("Inventory Web reads ledger projections and exposes no stock overwrite or valuation authority", async () => {
  const requests: Array<{ path: string; init?: RequestInit }> = [];

  const api = {
    get: async <T>(path: string) => {
      if ([
        "/inventory/warehouses",
        "/inventory/stock",
        "/inventory/positions",
        "/inventory/movements",
        "/inventory/reservations"
      ].includes(path)) {
        return { data: [] as T, correlationId: null, status: 200 };
      }
      if (path.startsWith("/inventory/locations?")) {
        return { data: [] as T, correlationId: null, status: 200 };
      }
      throw new Error("Unexpected GET " + path);
    },
    request: async <T>(path: string, init?: RequestInit) => {
      requests.push({ path, init });
      return {
        data: {
          entityPublicId: "22222222-2222-2222-2222-222222222222",
          entityType: "Warehouse",
          state: "ACTIVE",
          version: 1,
          correlationId: "corr-inventory"
        } as T,
        correlationId: "corr-inventory",
        status: 201
      };
    }
  };

  const page = createInventoryPage(api, () => "inventory-op");
  document.body.replaceChildren(page);
  await flush();

  assert.match(page.textContent ?? "", /Inventory Ledger/);
  assert.match(page.textContent ?? "", /doğrudan stok overwrite/);
  assert.equal(page.querySelector("[name='stockQuantity']"), null);
  assert.equal(page.querySelector("[name='averageCost']"), null);

  page.querySelector<HTMLInputElement>("#inventory-warehouse-code")!.value = "WH-1";
  page.querySelector<HTMLInputElement>("#inventory-warehouse-name")!.value = "Main";

  const create = Array.from(page.querySelectorAll<HTMLButtonElement>("button"))
    .find(button => button.textContent?.includes("Warehouse oluştur"));
  assert.ok(create);
  create.click();
  await flush();

  const request = requests.find(item => item.path === "/inventory/warehouses");
  assert.ok(request);
  assert.equal(request.init?.method, "POST");
  assert.equal(new Headers(request.init?.headers).get("Idempotency-Key"), "inventory-op");

  const body = JSON.parse(String(request.init?.body)) as Record<string, unknown>;
  assert.equal(body.code, "WH-1");
  assert.equal("companyId" in body, false);
  assert.equal("stockQuantity" in body, false);
});

async function flush(): Promise<void> {
  await Promise.resolve();
  await Promise.resolve();
  await new Promise(resolve => setTimeout(resolve, 0));
}

function installDom(): void {
  const window = new Window({ url: "https://mars.test/inventory" });
  const globals: Record<string, unknown> = {
    window,
    document: window.document,
    history: window.history,
    location: window.location,
    HTMLElement: window.HTMLElement,
    HTMLButtonElement: window.HTMLButtonElement,
    HTMLInputElement: window.HTMLInputElement,
    HTMLSelectElement: window.HTMLSelectElement,
    HTMLAnchorElement: window.HTMLAnchorElement,
    HTMLTableElement: window.HTMLTableElement,
    HTMLTableRowElement: window.HTMLTableRowElement,
    HTMLTableSectionElement: window.HTMLTableSectionElement,
    Element: window.Element,
    Node: window.Node,
    Event: window.Event,
    MouseEvent: window.MouseEvent,
    KeyboardEvent: window.KeyboardEvent,
    customElements: window.customElements
  };

  for (const [name, value] of Object.entries(globals)) {
    Object.defineProperty(globalThis, name, {
      configurable: true,
      writable: true,
      value
    });
  }
}
