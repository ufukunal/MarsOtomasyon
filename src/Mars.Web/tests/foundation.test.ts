import test from "node:test";
import assert from "node:assert/strict";
import { Window } from "happy-dom";

installDom();

const { ApiClient, ApiClientError } = await import("../src/api-client.ts");
const { MarsRouter, createAppShell } = await import("../src/app.ts");
const {
  createButton,
  createDialog,
  createField,
  createGrid,
  createLookup,
  createTabs
} = await import("../src/ui/components.ts");

test("API client keeps cookie/session transport and returns correlation id", async () => {
  let captured: RequestInit | undefined;
  globalThis.fetch = async (_input, init) => {
    captured = init;
    return new Response(JSON.stringify({ ok: true }), {
      status: 200,
      headers: {
        "Content-Type": "application/json",
        "X-Correlation-ID": "corr-web"
      }
    });
  };

  const client = new ApiClient();
  const result = await client.get<{ ok: boolean }>("/foundation/context");

  assert.equal(result.data.ok, true);
  assert.equal(result.correlationId, "corr-web");
  assert.equal(captured?.credentials, "same-origin");
  assert.equal(new Headers(captured?.headers).has("Authorization"), false);
});

test("API client maps non-success responses deterministically", async () => {
  globalThis.fetch = async () => new Response(JSON.stringify({
    code: "authorization.denied",
    message: "Denied",
    category: "Authorization",
    correlationId: "corr-denied"
  }), {
    status: 403,
    headers: { "Content-Type": "application/json" }
  });

  await assert.rejects(
    new ApiClient().get("/protected"),
    (error: unknown) => {
      assert.ok(error instanceof ApiClientError);
      assert.equal(error.status, 403);
      assert.equal(error.code, "authorization.denied");
      assert.equal(error.correlationId, "corr-denied");
      return true;
    });
});

test("API client propagates AbortSignal", async () => {
  const controller = new AbortController();
  controller.abort();

  globalThis.fetch = async (_input, init) => {
    assert.equal(init?.signal, controller.signal);
    throw new DOMException("Aborted", "AbortError");
  };

  await assert.rejects(
    new ApiClient().get("/cancelled", controller.signal),
    (error: unknown) => error instanceof DOMException && error.name === "AbortError");
});

test("router renders routes and handles navigation without authorization semantics", () => {
  document.body.replaceChildren();
  history.replaceState(null, "", "/");

  const shell = createAppShell();
  document.body.append(shell.element);

  const home = document.createElement("div");
  home.textContent = "Home";
  const next = document.createElement("div");
  next.textContent = "Next";

  const router = new MarsRouter(shell.outlet, [
    { path: "/", title: "Home", render: () => home },
    { path: "/components", title: "Components", render: () => next }
  ]);

  router.start();
  assert.equal(shell.outlet.textContent, "Home");

  router.navigate("/components");
  assert.equal(shell.outlet.textContent, "Next");
  assert.equal(location.pathname, "/components");

  router.stop();
});

test("button preserves native disabled and busy semantics", () => {
  const button = createButton({ label: "Save", loading: true, variant: "primary" });
  assert.equal(button.tagName, "BUTTON");
  assert.equal(button.disabled, true);
  assert.equal(button.getAttribute("aria-busy"), "true");
});

test("field associates label, help and error with the native input", () => {
  const field = createField({
    id: "code",
    label: "Code",
    required: true,
    help: "Help",
    error: "Invalid"
  });

  const label = field.element.querySelector("label");
  assert.equal(label?.htmlFor, "code");
  assert.equal(field.input.required, true);
  assert.equal(field.input.getAttribute("aria-invalid"), "true");
  assert.match(field.input.getAttribute("aria-describedby") ?? "", /code-help/);
  assert.match(field.input.getAttribute("aria-describedby") ?? "", /code-error/);
});

test("dialog traps focus, closes with Escape and returns focus", async () => {
  document.body.replaceChildren();

  const opener = document.createElement("button");
  opener.textContent = "Open";
  document.body.append(opener);
  opener.focus();

  const content = document.createElement("div");
  const first = document.createElement("input");
  const last = document.createElement("button");
  last.textContent = "Last";
  content.append(first, last);

  const dialog = createDialog({ title: "Foundation dialog", content });
  document.body.append(dialog.element);

  dialog.open();
  await Promise.resolve();
  assert.equal(dialog.isOpen(), true);
  assert.equal(document.activeElement, first);

  first.dispatchEvent(new KeyboardEvent("keydown", { key: "Tab", shiftKey: true, bubbles: true }));
  assert.equal(document.activeElement, last);

  last.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape", bubbles: true }));
  assert.equal(dialog.isOpen(), false);
  assert.equal(document.activeElement, opener);
});

test("tabs use tab roles and keyboard activation", () => {
  const one = document.createElement("div");
  const two = document.createElement("div");
  const tabs = createTabs([
    { id: "one", label: "One", panel: one },
    { id: "two", label: "Two", panel: two }
  ]);
  document.body.append(tabs.element);

  const buttons = tabs.element.querySelectorAll<HTMLButtonElement>("[role='tab']");
  assert.equal(buttons.length, 2);
  assert.equal(buttons[0]?.getAttribute("aria-selected"), "true");

  buttons[0]?.dispatchEvent(new KeyboardEvent("keydown", { key: "ArrowRight", bubbles: true }));
  assert.equal(tabs.activeId(), "two");
  assert.equal(buttons[1]?.getAttribute("aria-selected"), "true");
});

test("lookup remains generic, async and identity-aware", async () => {
  const lookup = createLookup({
    label: "Lookup",
    search: async ({ query, page, pageSize, signal }) => {
      assert.equal(page, 1);
      assert.equal(pageSize, 20);
      assert.equal(signal.aborted, false);
      return {
        items: [{ key: "entity-1", label: `Result ${query}` }],
        hasMore: false
      };
    },
    getKey: (item) => item.key,
    getLabel: (item) => item.label
  });
  document.body.append(lookup.element);

  lookup.input.value = "A";
  await lookup.searchNow();

  const option = lookup.element.querySelector<HTMLButtonElement>("[role='option']");
  assert.ok(option);
  option.click();

  assert.equal(lookup.getSelected()?.key, "entity-1");
  assert.equal(lookup.input.value, "Result A");

  lookup.element.dispatchEvent(new KeyboardEvent("keydown", { key: "F2", bubbles: true }));
  assert.equal(document.activeElement, lookup.input);
});

test("grid renders semantic headers/rows and supports keyboard row movement", () => {
  const activated: string[] = [];
  const grid = createGrid({
    caption: "Grid",
    columns: [
      { key: "name", header: "Name", value: (row: { name: string }) => row.name },
      { key: "qty", header: "Qty", value: (row: { qty: number }) => row.qty, align: "end" }
    ],
    rows: [
      { name: "A", qty: 1 },
      { name: "B", qty: 2 }
    ],
    onRowActivate: (row) => activated.push(row.name)
  });
  document.body.append(grid.element);

  assert.equal(grid.table.querySelectorAll("th[scope='col']").length, 2);
  assert.equal(grid.table.tBodies[0]?.rows.length, 2);

  grid.element.dispatchEvent(new KeyboardEvent("keydown", { key: "End", bubbles: true }));
  grid.element.dispatchEvent(new KeyboardEvent("keydown", { key: "Enter", bubbles: true }));
  assert.deepEqual(activated, ["B"]);

  grid.setRows([], { kind: "empty", message: "No rows" });
  assert.match(grid.element.textContent ?? "", /No rows/);
});

function installDom(): void {
  const window = new Window({ url: "https://mars.test/" });

  Object.assign(globalThis, {
    window,
    document: window.document,
    history: window.history,
    location: window.location,
    navigator: window.navigator,
    HTMLElement: window.HTMLElement,
    HTMLButtonElement: window.HTMLButtonElement,
    HTMLInputElement: window.HTMLInputElement,
    HTMLAnchorElement: window.HTMLAnchorElement,
    HTMLTableElement: window.HTMLTableElement,
    HTMLTableSectionElement: window.HTMLTableSectionElement,
    Element: window.Element,
    Node: window.Node,
    Event: window.Event,
    MouseEvent: window.MouseEvent,
    KeyboardEvent: window.KeyboardEvent,
    DOMException: window.DOMException,
    Headers: window.Headers,
    Response: window.Response,
    Request: window.Request,
    customElements: window.customElements
  });
}
