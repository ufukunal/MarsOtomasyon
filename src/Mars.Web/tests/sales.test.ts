import test from "node:test";
import assert from "node:assert/strict";
import { Window } from "happy-dom";

installDom();
const { createSalesPage } = await import("../src/sales.ts");

test("Sales Web exposes broad pre-post authority without Finance or stock overwrite semantics", async () => {
  const gets: string[] = [];
  const api = {
    get: async <T>(path: string) => {
      gets.push(path);
      return { data: [] as T, correlationId: "corr-sales", status: 200 };
    },
    request: async <T>() => ({
      data: {} as T,
      correlationId: "corr-sales",
      status: 200
    })
  };

  const page = createSalesPage(api, () => "sales-op");
  document.body.replaceChildren(page);
  await flush();

  assert.match(page.textContent ?? "", /Quote → Order → explicit Reservation → Dispatch/);
  assert.match(page.textContent ?? "", /Invoice DRAFT/);
  assert.match(page.textContent ?? "", /Inventory authority/);
  assert.equal((page.textContent ?? "").includes("paid amount"), false);
  assert.equal((page.textContent ?? "").includes("open amount"), false);
  assert.equal(page.querySelector("[name='stockQuantity']"), null);
  assert.equal(page.querySelector("[name='averageCost']"), null);

  for (const path of [
    "/sales/quotes",
    "/sales/orders",
    "/sales/dispatches",
    "/sales/invoices"
  ]) {
    assert.ok(gets.includes(path), "Expected GET " + path);
  }
});

async function flush(): Promise<void> {
  await Promise.resolve();
  await Promise.resolve();
  await new Promise(resolve => setTimeout(resolve, 0));
}

function installDom(): void {
  const window = new Window({ url: "https://mars.test/sales" });
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
    AbortController,
    DOMException,
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
