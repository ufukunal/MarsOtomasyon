import test from "node:test";
import assert from "node:assert/strict";
import { Window } from "happy-dom";

installDom();

const { createPartyMasterPage } = await import("../src/party-master.ts");

test("Party Master loads company-scoped list/detail and edits without client company authority", async () => {
  const requests: Array<{ path: string; init?: RequestInit }> = [];
  const partyId = "11111111-1111-1111-1111-111111111111";

  const detail = {
    publicId: partyId,
    partyCode: "P-600",
    kind: "ORGANIZATION",
    legalName: "Mars Old",
    displayName: null,
    state: "ACTIVE",
    version: 4,
    mergeSurvivorPublicId: null,
    roles: [],
    contacts: [],
    addresses: [],
    taxIdentities: [],
    externalMappings: []
  };

  const api = {
    get: async <T>(path: string) => {
      if (path === "/parties") {
        return {
          data: [{
            publicId: partyId,
            partyCode: "P-600",
            kind: "ORGANIZATION",
            legalName: "Mars Old",
            displayName: null,
            state: "ACTIVE",
            version: 4
          }] as T,
          correlationId: "corr-list",
          status: 200
        };
      }

      if (path === "/parties/" + partyId) {
        return { data: detail as T, correlationId: "corr-detail", status: 200 };
      }

      throw new Error("Unexpected GET " + path);
    },
    request: async <T>(path: string, init?: RequestInit) => {
      requests.push({ path, init });
      return {
        data: {
          entityPublicId: partyId,
          entityType: "Party",
          state: "ACTIVE",
          version: 5,
          correlationId: "corr-edit"
        } as T,
        correlationId: "corr-edit",
        status: 200
      };
    }
  };

  const page = createPartyMasterPage(api, () => "party-master-op");
  document.body.replaceChildren(page);

  await flush();
  const row = page.querySelector<HTMLTableRowElement>("tbody tr");
  assert.ok(row);
  row.dispatchEvent(new MouseEvent("dblclick", { bubbles: true }));
  await flush();

  assert.match(page.textContent ?? "", /P-600/);
  const legal = page.querySelector<HTMLInputElement>("#party-master-legal-name");
  assert.ok(legal);
  assert.equal(legal.value, "Mars Old");
  legal.value = "Mars New";

  const save = Array.from(page.querySelectorAll<HTMLButtonElement>("button"))
    .find(button => button.textContent?.includes("Kimliği kaydet"));
  assert.ok(save);
  save.click();
  await flush();

  assert.equal(requests.length, 1);
  assert.equal(requests[0]?.path, "/parties/" + partyId);
  assert.equal(requests[0]?.init?.method, "PUT");
  assert.equal(
    new Headers(requests[0]?.init?.headers).get("Idempotency-Key"),
    "party-master-op");

  const body = JSON.parse(String(requests[0]?.init?.body)) as Record<string, unknown>;
  assert.equal(body.legalName, "Mars New");
  assert.equal(body.version, 4);
  assert.equal("companyId" in body, false);
});

test("Party Master merge sends explicit survivor versions choices and reason", async () => {
  const requests: Array<{ path: string; init?: RequestInit }> = [];
  const sourceId = "22222222-2222-2222-2222-222222222222";
  const survivorId = "33333333-3333-3333-3333-333333333333";

  const detail = (publicId: string, code: string, version: number) => ({
    publicId,
    partyCode: code,
    kind: "ORGANIZATION",
    legalName: code,
    displayName: null,
    state: "ACTIVE",
    version,
    mergeSurvivorPublicId: null,
    roles: [],
    contacts: [],
    addresses: [],
    taxIdentities: [],
    externalMappings: []
  });

  const api = {
    get: async <T>(path: string) => {
      if (path === "/parties") {
        return {
          data: [{
            publicId: sourceId,
            partyCode: "P-SOURCE",
            kind: "ORGANIZATION",
            legalName: "Source",
            displayName: null,
            state: "ACTIVE",
            version: 7
          }] as T,
          correlationId: null,
          status: 200
        };
      }

      if (path === "/parties/" + sourceId) {
        return { data: detail(sourceId, "P-SOURCE", 7) as T, correlationId: null, status: 200 };
      }

      if (path === "/parties/" + survivorId) {
        return { data: detail(survivorId, "P-SURVIVOR", 11) as T, correlationId: null, status: 200 };
      }

      throw new Error("Unexpected GET " + path);
    },
    request: async <T>(path: string, init?: RequestInit) => {
      requests.push({ path, init });
      return {
        data: {
          sourcePartyPublicId: sourceId,
          survivorPartyPublicId: survivorId,
          sourceState: "MERGED",
          sourceVersion: 8,
          survivorVersion: 12,
          correlationId: "corr-merge"
        } as T,
        correlationId: "corr-merge",
        status: 200
      };
    }
  };

  const page = createPartyMasterPage(api, () => "merge-op");
  document.body.replaceChildren(page);
  await flush();

  page.querySelector<HTMLTableRowElement>("tbody tr")!
    .dispatchEvent(new MouseEvent("dblclick", { bubbles: true }));
  await flush();

  page.querySelector<HTMLInputElement>("#party-merge-survivor")!.value = survivorId;
  page.querySelector<HTMLInputElement>("#party-merge-reason")!.value = "Confirmed duplicate";

  const merge = Array.from(page.querySelectorAll<HTMLButtonElement>("button"))
    .find(button => button.textContent?.includes("Party'leri merge et"));
  assert.ok(merge);
  merge.click();
  await flush();

  assert.equal(requests.length, 1);
  assert.equal(requests[0]?.path, "/parties/" + sourceId + "/merge");
  assert.equal(
    new Headers(requests[0]?.init?.headers).get("Idempotency-Key"),
    "merge-op");

  const body = JSON.parse(String(requests[0]?.init?.body)) as Record<string, unknown>;
  assert.equal(body.survivorPartyPublicId, survivorId);
  assert.equal(body.sourceVersion, 7);
  assert.equal(body.survivorVersion, 11);
  assert.equal(body.reason, "Confirmed duplicate");
  assert.equal(body.moveSourceRoles, true);
  assert.equal(body.moveSourceContacts, true);
  assert.equal(body.moveSourceAddresses, true);
  assert.equal(body.moveSourceTaxIdentities, true);
  assert.equal(body.moveSourceExternalMappings, true);
  assert.equal("companyId" in body, false);
});

async function flush(): Promise<void> {
  await Promise.resolve();
  await Promise.resolve();
  await new Promise(resolve => setTimeout(resolve, 0));
}

function installDom(): void {
  const window = new Window({ url: "https://mars.test/parties" });
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
