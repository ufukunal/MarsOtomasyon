export interface V38NavigationItem { key:string; label:string; path:string|null; }
export interface V38NavigationGroup { key:string; label:string; icon:string; items:readonly V38NavigationItem[]; }
export const V38_REFERENCE_SCREEN_COUNT=260;
export const V38_MENU_ITEM_COUNT=178;
export const V38_MENU:readonly V38NavigationGroup[]=[
  {
    "key": "home",
    "label": "Ana Sayfa",
    "icon": "⌂",
    "items": [
      {
        "key": "dashboard",
        "label": "Ana Sayfa",
        "path": "/"
      },
      {
        "key": "work_queue",
        "label": "İş Kuyruğu",
        "path": null
      },
      {
        "key": "calendar",
        "label": "Takvim",
        "path": null
      },
      {
        "key": "screen_map",
        "label": "Ekran Haritası / Onay",
        "path": "/screen-map"
      }
    ]
  },
  {
    "key": "accounts",
    "label": "Kişiler / Firmalar",
    "icon": "◉",
    "items": [
      {
        "key": "contact_list",
        "label": "Kişi/Firma Listesi",
        "path": "/parties"
      },
      {
        "key": "contact_new",
        "label": "Yeni Kişi/Firma",
        "path": "/parties/new"
      },
      {
        "key": "balances",
        "label": "Bakiye Listesi",
        "path": null
      },
      {
        "key": "statement",
        "label": "Detaylı Cari Ekstre",
        "path": null
      },
      {
        "key": "open_items",
        "label": "Açık Kalemler",
        "path": null
      },
      {
        "key": "settlement_workspace",
        "label": "Mahsup / Bakiye Hareketi",
        "path": null
      },
      {
        "key": "risk_limits",
        "label": "Risk / Kredi Limitleri",
        "path": null
      },
      {
        "key": "projects",
        "label": "Projeler",
        "path": null
      }
    ]
  },
  {
    "key": "catalog",
    "label": "Ürünler ve Hizmetler",
    "icon": "◈",
    "items": [
      {
        "key": "product_list",
        "label": "Ürün Listesi",
        "path": "/products"
      },
      {
        "key": "product_new",
        "label": "Yeni Ürün",
        "path": "/products/new"
      },
      {
        "key": "categories",
        "label": "Kategoriler",
        "path": "/products"
      },
      {
        "key": "brands",
        "label": "Markalar",
        "path": "/products"
      },
      {
        "key": "variants",
        "label": "Varyantlar",
        "path": "/products"
      },
      {
        "key": "units",
        "label": "Birimler / Dönüşümler",
        "path": "/products"
      },
      {
        "key": "barcodes",
        "label": "Barkodlar",
        "path": "/products"
      },
      {
        "key": "packages",
        "label": "Paketler",
        "path": "/products"
      },
      {
        "key": "price_lists",
        "label": "Fiyat Listeleri",
        "path": null
      },
      {
        "key": "pricing_rules",
        "label": "Fiyatlandırma Kuralları",
        "path": null
      },
      {
        "key": "bulk_pricing",
        "label": "Toplu Fiyat Sihirbazı",
        "path": null
      },
      {
        "key": "config_defs",
        "label": "Konfigürasyon Tanımları",
        "path": null
      },
      {
        "key": "configurator",
        "label": "Ürün Konfigüratörü",
        "path": null
      }
    ]
  },
  {
    "key": "sales",
    "label": "Satış Yönetimi",
    "icon": "◴",
    "items": [
      {
        "key": "quote_list",
        "label": "Teklifler",
        "path": "/sales/quotes"
      },
      {
        "key": "quote_new",
        "label": "Yeni Teklif",
        "path": "/sales/quotes/new"
      },
      {
        "key": "sales_order_list",
        "label": "Satış Siparişleri",
        "path": "/sales/orders"
      },
      {
        "key": "sales_order_new",
        "label": "Yeni Satış Siparişi",
        "path": "/sales/orders/new"
      },
      {
        "key": "dispatch_list",
        "label": "Sevkiyat / İrsaliye",
        "path": "/sales/dispatches"
      },
      {
        "key": "dispatch_new",
        "label": "Yeni Sevkiyat",
        "path": "/sales/dispatches/new"
      },
      {
        "key": "sales_invoice_list",
        "label": "Satış Faturaları",
        "path": "/sales/invoices"
      },
      {
        "key": "sales_invoice_new",
        "label": "Yeni Satış Faturası",
        "path": "/sales/invoices/new"
      },
      {
        "key": "proforma_list",
        "label": "Proforma Faturalar",
        "path": "/sales/proformas"
      },
      {
        "key": "sales_returns",
        "label": "Satış İadeleri",
        "path": null
      },
      {
        "key": "sales_report",
        "label": "Ürün Satış Raporu",
        "path": null
      }
    ]
  },
  {
    "key": "purchase",
    "label": "Satınalma Yönetimi",
    "icon": "◉",
    "items": [
      {
        "key": "purchase_order_list",
        "label": "Satınalma Siparişleri",
        "path": "/purchasing/orders"
      },
      {
        "key": "purchase_order_new",
        "label": "Yeni Satınalma Siparişi",
        "path": "/purchasing/orders/new"
      },
      {
        "key": "goods_receipt_list",
        "label": "Mal Kabul",
        "path": "/purchasing/receipts"
      },
      {
        "key": "goods_receipt_new",
        "label": "Yeni Mal Kabul",
        "path": "/purchasing/receipts/new"
      },
      {
        "key": "supplier_invoice_list",
        "label": "Alış Faturaları",
        "path": "/purchasing/invoices"
      },
      {
        "key": "supplier_invoice_new",
        "label": "Yeni Alış Faturası",
        "path": "/purchasing/invoices/new"
      },
      {
        "key": "three_way_match",
        "label": "3-Way Match",
        "path": "/purchasing/match"
      },
      {
        "key": "purchase_returns",
        "label": "Alış İadeleri",
        "path": null
      },
      {
        "key": "supplier_performance",
        "label": "Tedarikçi Performansı",
        "path": null
      },
      {
        "key": "purchase_report",
        "label": "Ürün Satınalma Raporu",
        "path": null
      }
    ]
  },
  {
    "key": "inventory",
    "label": "Stok ve Depo",
    "icon": "▦",
    "items": [
      {
        "key": "stock_status",
        "label": "Stok Durumu",
        "path": "/inventory/stock"
      },
      {
        "key": "stock_movements",
        "label": "Stok Hareketleri",
        "path": "/inventory/movements"
      },
      {
        "key": "warehouses",
        "label": "Depolar",
        "path": "/inventory/warehouses"
      },
      {
        "key": "locations",
        "label": "Lokasyonlar",
        "path": "/inventory/locations"
      },
      {
        "key": "reservations",
        "label": "Rezervasyonlar",
        "path": "/inventory/reservations"
      },
      {
        "key": "transfer_list",
        "label": "Depo Transferleri",
        "path": "/warehouse/transfers"
      },
      {
        "key": "transfer_new",
        "label": "Yeni Depo Transferi",
        "path": "/warehouse/transfers/new"
      },
      {
        "key": "count_list",
        "label": "Stok Sayımları",
        "path": "/warehouse/counts"
      },
      {
        "key": "count_new",
        "label": "Yeni Stok Sayımı",
        "path": "/warehouse/counts/new"
      },
      {
        "key": "lots_serials",
        "label": "Lot / Seri",
        "path": "/inventory/movements"
      },
      {
        "key": "quarantine",
        "label": "Karantina / Bloke",
        "path": "/warehouse/quarantine"
      },
      {
        "key": "inventory_cost",
        "label": "Stok Maliyeti",
        "path": null
      },
      {
        "key": "scan_console",
        "label": "Barkod / Scan Console",
        "path": "/warehouse/scan"
      }
    ]
  },
  {
    "key": "quality",
    "label": "Kalite Yönetimi",
    "icon": "✓",
    "items": [
      {
        "key": "qcp_list",
        "label": "Kontrol Planları",
        "path": null
      },
      {
        "key": "qcp_new",
        "label": "Yeni Kontrol Planı",
        "path": null
      },
      {
        "key": "qc_queue",
        "label": "QC Bekleyenler",
        "path": null
      },
      {
        "key": "inspection_detail",
        "label": "Kalite Kontrolü",
        "path": null
      },
      {
        "key": "qc_disposition",
        "label": "QC Disposition",
        "path": null
      },
      {
        "key": "nonconformity",
        "label": "Uygunsuzluklar",
        "path": null
      },
      {
        "key": "capa",
        "label": "DÖF / CAPA",
        "path": null
      },
      {
        "key": "root_cause",
        "label": "8D / Kök Neden",
        "path": null
      },
      {
        "key": "calibration",
        "label": "Kalibrasyon",
        "path": null
      },
      {
        "key": "supplier_quality",
        "label": "Tedarikçi Kalitesi",
        "path": null
      },
      {
        "key": "spc",
        "label": "SPC",
        "path": null
      }
    ]
  },
  {
    "key": "finance",
    "label": "Finans İşlemleri",
    "icon": "₺",
    "items": [
      {
        "key": "collection",
        "label": "Alacak / Tahsilat",
        "path": null
      },
      {
        "key": "payment",
        "label": "Borç / Ödeme",
        "path": null
      },
      {
        "key": "refunds",
        "label": "Refund / Para İadesi",
        "path": null
      },
      {
        "key": "cash_accounts",
        "label": "Kasalar",
        "path": null
      },
      {
        "key": "cash_movements",
        "label": "Kasa Hareketleri",
        "path": null
      },
      {
        "key": "cash_count",
        "label": "Kasa Sayımı",
        "path": null
      },
      {
        "key": "expenses",
        "label": "Giderler",
        "path": null
      },
      {
        "key": "advances",
        "label": "Avanslar",
        "path": null
      },
      {
        "key": "bank_accounts",
        "label": "Banka Hesapları",
        "path": null
      },
      {
        "key": "bank_movements",
        "label": "Banka Hareketleri",
        "path": null
      },
      {
        "key": "bank_transfer",
        "label": "Virman / FX Transfer",
        "path": null
      },
      {
        "key": "statement_import",
        "label": "Ekstre İçe Aktar",
        "path": null
      },
      {
        "key": "reconciliation",
        "label": "Banka Mutabakatı",
        "path": null
      },
      {
        "key": "incoming_checks",
        "label": "Alınan Çekler",
        "path": null
      },
      {
        "key": "outgoing_checks",
        "label": "Verilen Çekler",
        "path": null
      },
      {
        "key": "incoming_notes",
        "label": "Alınan Senetler",
        "path": null
      },
      {
        "key": "outgoing_notes",
        "label": "Verilen Senetler",
        "path": null
      }
    ]
  },
  {
    "key": "returns",
    "label": "İade / RMA",
    "icon": "↩",
    "items": [
      {
        "key": "rma_list",
        "label": "RMA Listesi",
        "path": null
      },
      {
        "key": "rma_new",
        "label": "Yeni RMA",
        "path": null
      },
      {
        "key": "rma_receipt",
        "label": "RMA Mal Kabul",
        "path": null
      },
      {
        "key": "rma_inspection",
        "label": "RMA İnceleme",
        "path": null
      },
      {
        "key": "return_financial",
        "label": "İade Finansal İşlemleri",
        "path": null
      }
    ]
  },
  {
    "key": "production",
    "label": "Üretim Yönetimi",
    "icon": "⚙",
    "items": [
      {
        "key": "bom_list",
        "label": "BOM / Reçeteler",
        "path": null
      },
      {
        "key": "routing_list",
        "label": "Rotalar",
        "path": null
      },
      {
        "key": "work_centers",
        "label": "İş Merkezleri",
        "path": null
      },
      {
        "key": "eco_list",
        "label": "ECO",
        "path": null
      },
      {
        "key": "production_orders",
        "label": "Üretim Emirleri",
        "path": null
      },
      {
        "key": "shopfloor",
        "label": "Shop Floor",
        "path": null
      },
      {
        "key": "production_output",
        "label": "Üretim Çıktıları",
        "path": null
      },
      {
        "key": "downtime",
        "label": "Duruşlar",
        "path": null
      },
      {
        "key": "wip_cost",
        "label": "WIP / Üretim Maliyeti",
        "path": null
      },
      {
        "key": "production_report",
        "label": "Üretim Raporu",
        "path": null
      },
      {
        "key": "oee",
        "label": "OEE",
        "path": null
      }
    ]
  },
  {
    "key": "outsourcing",
    "label": "Fason Yönetimi",
    "icon": "⇄",
    "items": [
      {
        "key": "outsourcing_orders",
        "label": "Fason Emirleri",
        "path": null
      },
      {
        "key": "outsourcing_new",
        "label": "Yeni Fason Emri",
        "path": null
      },
      {
        "key": "outsourcing_send",
        "label": "Malzeme Gönder",
        "path": null
      },
      {
        "key": "outsourcing_receipt",
        "label": "Fason Çıktı Kabul",
        "path": null
      },
      {
        "key": "outsourcing_return",
        "label": "Malzeme İade",
        "path": null
      },
      {
        "key": "outsourcing_reconcile",
        "label": "Fason Mutabakatı",
        "path": null
      }
    ]
  },
  {
    "key": "imports",
    "label": "İthalat Yönetimi",
    "icon": "▧",
    "items": [
      {
        "key": "import_shipments",
        "label": "İthalat Dosyaları",
        "path": null
      },
      {
        "key": "import_new",
        "label": "Yeni İthalat Dosyası",
        "path": null
      },
      {
        "key": "containers",
        "label": "Konteynerler",
        "path": null
      },
      {
        "key": "package_map",
        "label": "Koli / Komponent Eşleştirme",
        "path": null
      },
      {
        "key": "landed_cost",
        "label": "Landed Cost",
        "path": null
      },
      {
        "key": "container_simulator",
        "label": "Konteyner Simülatörü",
        "path": null
      },
      {
        "key": "technical_product_file",
        "label": "Teknik Ürün Dosyası",
        "path": null
      }
    ]
  },
  {
    "key": "external",
    "label": "E-Ticaret / B2B / API",
    "icon": "⇆",
    "items": [
      {
        "key": "marketplace_dashboard",
        "label": "Pazaryeri Merkezi",
        "path": null
      },
      {
        "key": "provider_accounts",
        "label": "Provider Hesapları",
        "path": null
      },
      {
        "key": "woo_orders",
        "label": "WooCommerce Siparişleri",
        "path": null
      },
      {
        "key": "trendyol_orders",
        "label": "Trendyol Siparişleri",
        "path": null
      },
      {
        "key": "channel_mapping",
        "label": "Ürün / Kanal Eşleştirme",
        "path": null
      },
      {
        "key": "marketplace_settlements",
        "label": "Pazaryeri Hakedişleri",
        "path": null
      },
      {
        "key": "sync_errors",
        "label": "Sync Hataları",
        "path": null
      },
      {
        "key": "inbox",
        "label": "Inbound Inbox",
        "path": null
      },
      {
        "key": "external_mapping",
        "label": "External Entity Mapping",
        "path": null
      },
      {
        "key": "kill_switch",
        "label": "Kill Switch",
        "path": null
      },
      {
        "key": "api_clients",
        "label": "API İstemcileri",
        "path": null
      },
      {
        "key": "api_idempotency",
        "label": "API Idempotency",
        "path": null
      },
      {
        "key": "b2b_users",
        "label": "B2B Kullanıcıları",
        "path": null
      },
      {
        "key": "b2b_orders",
        "label": "B2B Siparişleri",
        "path": null
      },
      {
        "key": "portal_quote",
        "label": "Müşteri Teklif Portalı",
        "path": null
      }
    ]
  },
  {
    "key": "communications",
    "label": "İletişim / Dosyalar",
    "icon": "✉",
    "items": [
      {
        "key": "files",
        "label": "Dosyalar",
        "path": null
      },
      {
        "key": "file_security",
        "label": "Dosya Güvenliği",
        "path": null
      },
      {
        "key": "templates",
        "label": "Mesaj Şablonları",
        "path": null
      },
      {
        "key": "deliveries",
        "label": "Teslimatlar",
        "path": null
      },
      {
        "key": "provider_configs",
        "label": "İletişim Providerları",
        "path": null
      },
      {
        "key": "webhooks",
        "label": "Webhooklar",
        "path": null
      }
    ]
  },
  {
    "key": "reports",
    "label": "Raporlar / Tasarım",
    "icon": "▥",
    "items": [
      {
        "key": "sales_report",
        "label": "Ürün Satış Raporu",
        "path": null
      },
      {
        "key": "purchase_report",
        "label": "Ürün Satınalma Raporu",
        "path": null
      },
      {
        "key": "stock_aging",
        "label": "Stok Yaşlandırma",
        "path": null
      },
      {
        "key": "cashflow_report",
        "label": "Nakit Akış",
        "path": null
      },
      {
        "key": "profitability",
        "label": "Kârlılık",
        "path": null
      },
      {
        "key": "production_analytics",
        "label": "Üretim Analitiği",
        "path": null
      },
      {
        "key": "quality_report",
        "label": "Kalite Analitiği",
        "path": null
      },
      {
        "key": "report_catalog",
        "label": "Rapor Kataloğu",
        "path": null
      },
      {
        "key": "report_designer",
        "label": "Rapor Tasarımcısı",
        "path": null
      },
      {
        "key": "print_center",
        "label": "Print Center",
        "path": null
      },
      {
        "key": "scheduled_reports",
        "label": "Planlı Raporlar",
        "path": null
      }
    ]
  },
  {
    "key": "system",
    "label": "Ayarlar / Sistem",
    "icon": "⚙",
    "items": [
      {
        "key": "company_settings",
        "label": "Firma Ayarları",
        "path": null
      },
      {
        "key": "branches",
        "label": "Şubeler",
        "path": null
      },
      {
        "key": "users",
        "label": "Kullanıcılar",
        "path": null
      },
      {
        "key": "roles",
        "label": "Roller / İzinler",
        "path": null
      },
      {
        "key": "approvals",
        "label": "Onaylar",
        "path": null
      },
      {
        "key": "numbering",
        "label": "Numaralandırma",
        "path": null
      },
      {
        "key": "tax_currency",
        "label": "Vergi / Döviz",
        "path": null
      },
      {
        "key": "posting_periods",
        "label": "Dönemler",
        "path": null
      },
      {
        "key": "audit",
        "label": "Audit",
        "path": null
      },
      {
        "key": "outbox",
        "label": "Outbox",
        "path": null
      },
      {
        "key": "system_health",
        "label": "Sistem Sağlığı",
        "path": null
      },
      {
        "key": "jobs",
        "label": "Queue / Jobs",
        "path": null
      },
      {
        "key": "backup",
        "label": "Yedekleme",
        "path": null
      },
      {
        "key": "restore",
        "label": "Restore",
        "path": null
      },
      {
        "key": "recovery",
        "label": "Recovery Mode",
        "path": null
      },
      {
        "key": "bulk_import",
        "label": "Bulk Import",
        "path": null
      },
      {
        "key": "bulk_export",
        "label": "Bulk Export",
        "path": null
      },
      {
        "key": "migration",
        "label": "Veri Migrasyonu",
        "path": null
      },
      {
        "key": "retention",
        "label": "Retention / Archive",
        "path": null
      }
    ]
  },
  {
    "key": "later",
    "label": "LATER Modüller",
    "icon": "◇",
    "items": [
      {
        "key": "mrp",
        "label": "MRP / Planlama",
        "path": null
      },
      {
        "key": "capacity",
        "label": "Finite Capacity",
        "path": null
      },
      {
        "key": "maintenance",
        "label": "Bakım Yönetimi",
        "path": null
      },
      {
        "key": "service_warranty",
        "label": "Servis / Garanti",
        "path": null
      },
      {
        "key": "samples",
        "label": "Numune / Konsinye",
        "path": null
      },
      {
        "key": "contracts",
        "label": "Sözleşme / Periyodik",
        "path": null
      },
      {
        "key": "fixed_assets",
        "label": "Sabit Kıymet",
        "path": null
      },
      {
        "key": "crm",
        "label": "CRM-lite",
        "path": null
      },
      {
        "key": "advanced_communications",
        "label": "Gelişmiş İletişim",
        "path": null
      },
      {
        "key": "carrier_performance",
        "label": "Taşıyıcı Performansı",
        "path": null
      },
      {
        "key": "sod",
        "label": "SoD",
        "path": null
      }
    ]
  }
] as const;
export function createV38ScreenMapPage():HTMLElement{const root=document.createElement("section");root.className="mars-page mars-component-stack";const head=document.createElement("div");head.className="mars-page-head";const tw=document.createElement("div"),h=document.createElement("h1"),p=document.createElement("p");h.textContent="Ekran Haritası / UI Onay";p.textContent=`Canonical V38 yüzeyi: ${V38_MENU.length} ana grup, ${V38_MENU_ITEM_COUNT} menü öğesi, ${V38_REFERENCE_SCREEN_COUNT} referans ekran. Uygulanmamış yüzeyler gizlenmez ve uygulanmış gibi gösterilmez.`;tw.append(h,p);head.append(tw);const summary=document.createElement("div");summary.className="mars-kpi-strip";const implemented=V38_MENU.flatMap(g=>g.items).filter(i=>i.path!==null).length;summary.append(metric("V38 ekran tanımı",String(V38_REFERENCE_SCREEN_COUNT)),metric("Menü öğesi",String(V38_MENU_ITEM_COUNT)),metric("Bağlı production yüzeyi",String(implemented)),metric("Durum","Parity aktif"));const wrap=document.createElement("div");wrap.className="mars-grid";const table=document.createElement("table");table.className="mars-grid__table";const thead=document.createElement("thead"),hr=document.createElement("tr");for(const label of ["Grup","V38 ekran","Production route","Durum"]){const th=document.createElement("th");th.scope="col";th.textContent=label;hr.append(th);}thead.append(hr);const tbody=document.createElement("tbody");for(const group of V38_MENU)for(const item of group.items){const tr=document.createElement("tr");for(const value of [group.label,item.label,item.path??"—"]){const td=document.createElement("td");td.textContent=value;tr.append(td);}const td=document.createElement("td"),badge=document.createElement("span");badge.className=item.path?"mars-status mars-status--ok":"mars-status mars-status--planned";badge.textContent=item.path?"Bağlı":"Planlı";td.append(badge);tr.append(td);tbody.append(tr);}table.append(thead,tbody);wrap.append(table);root.append(head,summary,wrap);return root;}
function metric(label:string,value:string):HTMLElement{const c=document.createElement("div");c.className="mars-kpi";const s=document.createElement("small"),b=document.createElement("strong");s.textContent=label;b.textContent=value;c.append(s,b);return c;}
