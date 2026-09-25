import "./ui/base.css";
import { createAppShell, MarsRouter } from "./app";
import { ApiClient } from "./api-client";
import { createFoundationProofPage } from "./foundation-proof";
import { createPartyCreatePage } from "./party-create";
import { createPartyMasterPage } from "./party-master";
import { createProductMasterPage } from "./product-master";
import { createInventoryPage } from "./inventory";
import { createSalesPage } from "./sales";
import { createPurchasingPage } from "./purchasing";
import { createWarehousePage } from "./warehouse";
import { createV38ScreenMapPage } from "./v38-navigation";
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
    title: "Kişi/Firma Listesi",
    render: () => createPartyMasterPage(api)
  },
  {
    path: "/parties/new",
    title: "Yeni Kişi/Firma",
    render: () => createPartyCreatePage(api)
  },
  {
    path: "/products",
    title: "Ürün Listesi",
    render: () => createProductMasterPage(api)
  },
  {
    path: "/products/new",
    title: "Yeni Ürün",
    render: () => createProductMasterPage(api)
  },
  {
    path: "/inventory",
    title: "Stok ve Depo",
    render: () => createInventoryPage(api)
  },
  {
    path: "/inventory/stock",
    title: "Stok Durumu",
    render: () => createInventoryPage(api, undefined, "inventory-stock")
  },
  {
    path: "/inventory/movements",
    title: "Stok Hareketleri",
    render: () => createInventoryPage(api, undefined, "inventory-trace")
  },
  {
    path: "/inventory/warehouses",
    title: "Depolar",
    render: () => createInventoryPage(api, undefined, "inventory-master")
  },
  {
    path: "/inventory/locations",
    title: "Lokasyonlar",
    render: () => createInventoryPage(api, undefined, "inventory-master")
  },
  {
    path: "/inventory/reservations",
    title: "Rezervasyonlar",
    render: () => createInventoryPage(api, undefined, "inventory-trace")
  },
  {
    path: "/sales",
    title: "Satış Yönetimi",
    render: () => createSalesPage(api)
  },
  { path: "/sales/quotes", title: "Teklifler", render: () => createSalesPage(api, undefined, "sales-quotes") },
  { path: "/sales/quotes/new", title: "Yeni Teklif", render: () => createSalesPage(api, undefined, "sales-quotes") },
  { path: "/sales/orders", title: "Satış Siparişleri", render: () => createSalesPage(api, undefined, "sales-orders") },
  { path: "/sales/orders/new", title: "Yeni Satış Siparişi", render: () => createSalesPage(api, undefined, "sales-orders") },
  { path: "/sales/dispatches", title: "Sevkiyat / İrsaliye", render: () => createSalesPage(api, undefined, "sales-dispatches") },
  { path: "/sales/dispatches/new", title: "Yeni Sevkiyat", render: () => createSalesPage(api, undefined, "sales-dispatches") },
  { path: "/sales/invoices", title: "Satış Faturaları", render: () => createSalesPage(api, undefined, "sales-invoices") },
  { path: "/sales/invoices/new", title: "Yeni Satış Faturası", render: () => createSalesPage(api, undefined, "sales-invoices") },
  { path: "/sales/proformas", title: "Proforma Faturalar", render: () => createSalesPage(api, undefined, "sales-proformas") },
  {
    path: "/purchasing",
    title: "Satınalma Yönetimi",
    render: () => createPurchasingPage(api)
  },
  { path: "/purchasing/orders", title: "Satınalma Siparişleri", render: () => createPurchasingPage(api, undefined, "purchasing-orders") },
  { path: "/purchasing/orders/new", title: "Yeni Satınalma Siparişi", render: () => createPurchasingPage(api, undefined, "purchasing-orders") },
  { path: "/purchasing/receipts", title: "Mal Kabul", render: () => createPurchasingPage(api, undefined, "purchasing-receipts") },
  { path: "/purchasing/receipts/new", title: "Yeni Mal Kabul", render: () => createPurchasingPage(api, undefined, "purchasing-receipts") },
  { path: "/purchasing/invoices", title: "Alış Faturaları", render: () => createPurchasingPage(api, undefined, "purchasing-invoices") },
  { path: "/purchasing/invoices/new", title: "Yeni Alış Faturası", render: () => createPurchasingPage(api, undefined, "purchasing-invoices") },
  { path: "/purchasing/match", title: "3-Way Match", render: () => createPurchasingPage(api, undefined, "purchasing-invoices") },
  {
    path: "/warehouse",
    title: "Stok ve Depo · Operasyon",
    render: () => createWarehousePage(api)
  },
  { path: "/warehouse/transfers", title: "Depo Transferleri", render: () => createWarehousePage(api, undefined, "warehouse-transfer") },
  { path: "/warehouse/transfers/new", title: "Yeni Depo Transferi", render: () => createWarehousePage(api, undefined, "warehouse-transfer") },
  { path: "/warehouse/counts", title: "Stok Sayımları", render: () => createWarehousePage(api, undefined, "warehouse-count") },
  { path: "/warehouse/counts/new", title: "Yeni Stok Sayımı", render: () => createWarehousePage(api, undefined, "warehouse-count") },
  { path: "/warehouse/quarantine", title: "Karantina / Bloke", render: () => createWarehousePage(api, undefined, "warehouse-receiving") },
  { path: "/warehouse/scan", title: "Barkod / Scan Console", render: () => createWarehousePage(api, undefined, "warehouse-offline") },
  {
    path: "/screen-map",
    title: "Ekran Haritası / UI Onay",
    render: createV38ScreenMapPage
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
], shell.setActiveRoute);

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
