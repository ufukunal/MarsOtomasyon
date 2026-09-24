import "./ui/base.css";
import { createAppShell, MarsRouter } from "./app";
import { ApiClient } from "./api-client";
import { createFoundationProofPage } from "./foundation-proof";
import { createPartyCreatePage } from "./party-create";
import { createPartyMasterPage } from "./party-master";
import { createProductMasterPage } from "./product-master";
import { createInventoryPage } from "./inventory";
import { createSalesPage } from "./sales";
import {
  createButton,
  createField,
  createGrid,
  createLookup,
  createTabs
} from "./ui/components";

const host = document.querySelector<HTMLElement>("#app");
if (!host) throw new Error("Mars.Web root '#app' was not found.");

const shell = createAppShell();
host.append(shell.element);

const api = new ApiClient();

const router = new MarsRouter(shell.outlet, [
  {
    path: "/",
    title: "Foundation",
    render: renderFoundation
  },
  {
    path: "/parties",
    title: "Cari / Party Master",
    render: () => createPartyMasterPage(api)
  },
  {
    path: "/parties/new",
    title: "Yeni Party",
    render: () => createPartyCreatePage(api)
  },
  {
    path: "/products",
    title: "Ürün / Product Master",
    render: () => createProductMasterPage(api)
  },
  {
    path: "/inventory",
    title: "Inventory",
    render: () => createInventoryPage(api)
  },
  {
    path: "/sales",
    title: "Sales",
    render: () => createSalesPage(api)
  },
  {
    path: "/proof",
    title: "Vertical Proof",
    render: () => createFoundationProofPage(api)
  },
  {
    path: "/components",
    title: "Mars.UI",
    render: renderComponents
  }
]);

router.start();

function renderFoundation(): HTMLElement {
  const panel = document.createElement("section");
  panel.className = "mars-foundation-panel";

  const heading = document.createElement("h1");
  heading.textContent = "Mars.Web Foundation";

  const text = document.createElement("p");
  text.textContent = "Sunucu iş gerçeğini taşır; web katmanı yalnız güvenli etkileşim ve sunum temelini sağlar.";

  const healthButton = createButton({
    label: "API erişimini kontrol et",
    variant: "primary",
    onClick: async () => {
      healthButton.disabled = true;
      try {
        const response = await api.get<Record<string, unknown>>("/foundation/context");
        text.textContent = `API yanıtı alındı. Correlation: ${response.correlationId ?? "yok"}`;
      } catch {
        text.textContent = "Korumalı Foundation endpoint'i oturum gerektiriyor veya API erişilemiyor.";
      } finally {
        healthButton.disabled = false;
      }
    }
  });

  panel.append(heading, text, healthButton);
  return panel;
}

function renderComponents(): HTMLElement {
  const panel = document.createElement("section");
  panel.className = "mars-foundation-panel mars-component-stack";

  const heading = document.createElement("h1");
  heading.textContent = "Mars.UI Foundation";

  const field = createField({
    id: "foundation-note",
    label: "Örnek alan",
    help: "Domain doğrulaması değildir."
  });

  const firstPanel = document.createElement("p");
  firstPanel.textContent = "Birinci sekme";
  const secondPanel = document.createElement("p");
  secondPanel.textContent = "İkinci sekme";
  const tabs = createTabs([
    { id: "one", label: "Genel", panel: firstPanel },
    { id: "two", label: "Detay", panel: secondPanel }
  ]);

  const lookup = createLookup({
    label: "Genel lookup",
    search: async ({ query }) => ({
      items: query ? [{ id: "foundation", label: query }] : [],
      hasMore: false
    }),
    getKey: (item) => item.id,
    getLabel: (item) => item.label
  });

  const grid = createGrid({
    caption: "Foundation grid",
    columns: [
      { key: "name", header: "Ad", value: (row: { name: string }) => row.name }
    ],
    rows: [{ name: "Reusable UI contract" }]
  });

  panel.append(heading, field.element, tabs.element, lookup.element, grid.element);
  return panel;
}
