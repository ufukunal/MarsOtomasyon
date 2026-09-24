import test from "node:test";
import assert from "node:assert/strict";
import { Window } from "happy-dom";

installDom();
const { createProductMasterPage } = await import("../src/product-master.ts");

test("Product Master create uses trusted company context and exposes no stock/cost authority", async () => {
  const requests: Array<{ path: string; init?: RequestInit }> = [];
  const uomId = "11111111-1111-1111-1111-111111111111";

  const api = {
    get: async <T>(path: string) => {
      if (path === "/products/uoms") {
        return {
          data: [{ publicId: uomId, code: "ADET", name: "Adet", state: "ACTIVE", version: 1 }] as T,
          correlationId: null,
          status: 200
        };
      }
      if (path === "/products/categories") {
        return { data: [] as T, correlationId: null, status: 200 };
      }
      if (path === "/products") {
        return { data: [] as T, correlationId: null, status: 200 };
      }
      throw new Error("Unexpected GET " + path);
    },
    request: async <T>(path: string, init?: RequestInit) => {
      requests.push({ path, init });
      return {
        data: {
          entityPublicId: "22222222-2222-2222-2222-222222222222",
          entityType: "Product",
          state: "ACTIVE",
          version: 1,
          correlationId: "corr-product"
        } as T,
        correlationId: "corr-product",
        status: 201
      };
    }
  };

  const page = createProductMasterPage(api, () => "product-op");
  document.body.replaceChildren(page);
  await flush();

  page.querySelector<HTMLInputElement>("#product-new-code")!.value = "PRD-1";
  page.querySelector<HTMLInputElement>("#product-new-name")!.value = "Mars Product";

  const create = Array.from(page.querySelectorAll<HTMLButtonElement>("button"))
    .find(button => button.textContent?.includes("Product oluştur"));
  assert.ok(create);
  create.click();
  await flush();

  const request = requests.find(item => item.path === "/products");
  assert.ok(request);
  assert.equal(request.init?.method, "POST");
  assert.equal(new Headers(request.init?.headers).get("Idempotency-Key"), "product-op");

  const body = JSON.parse(String(request.init?.body)) as Record<string, unknown>;
  assert.equal(body.baseUomPublicId, uomId);
  assert.equal("companyId" in body, false);
  assert.equal("stockQuantity" in body, false);
  assert.equal("averageCost" in body, false);
  assert.match(page.textContent ?? "", /Inventory Ledger ve Finance ayrı kalır/);
});

async function flush(): Promise<void> {
  await Promise.resolve();
  await Promise.resolve();
  await new Promise(resolve => setTimeout(resolve, 0));
}

function installDom(): void {
  const window = new Window({ url: "https://mars.test/products" });
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
