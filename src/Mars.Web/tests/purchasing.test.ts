import test from "node:test";
import assert from "node:assert/strict";
import { Window } from "happy-dom";

installDom();
const { createPurchasingPage } = await import("../src/purchasing.ts");

test("Purchasing Web preserves PO receipt quarantine and Invoice DRAFT authority boundaries", async () => {
  const gets: string[] = [];
  const api = {
    get: async <T>(path: string) => {
      gets.push(path);
      return { data: [] as T, correlationId: "corr-purchasing", status: 200 };
    },
    request: async <T>() => ({
      data: {} as T,
      correlationId: "corr-purchasing",
      status: 200
    })
  };

  const page = createPurchasingPage(api, () => "purchasing-op");
  document.body.replaceChildren(page);
  await flush();

  const text = page.textContent ?? "";
  assert.match(text, /Purchase Order/);
  assert.match(text, /Goods Receipt/);
  assert.match(text, /QUARANTINE/);
  assert.match(text, /Supplier Invoice DRAFT/);
  assert.match(text, /2-way \/ 3-way match/);
  assert.equal(text.includes("Supplier Invoice POST"), false);
  assert.equal(page.querySelector("[name='payable']"), null);
  assert.equal(page.querySelector("[name='payment']"), null);
  assert.equal(page.querySelector("[name='stockQuantity']"), null);

  for (const path of [
    "/purchasing/orders",
    "/purchasing/receipts",
    "/purchasing/invoices",
    "/purchasing/matches"
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
  const window = new Window({ url: "https://mars.test/purchasing" });
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
