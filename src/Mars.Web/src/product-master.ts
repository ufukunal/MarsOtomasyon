import { ApiClient, ApiClientError } from "./api-client";
import { createButton, createField, createGrid } from "./ui/components";

export interface ProductListItem {
  publicId: string;
  productCode: string;
  name: string;
  kind: string;
  sellable: boolean;
  purchasable: boolean;
  stockable: boolean;
  trackingStrategy: string;
  baseUomCode: string;
  primaryCategory: string | null;
  state: "ACTIVE" | "INACTIVE";
  version: number;
}

interface UomView {
  publicId: string; code: string; name: string; state: "ACTIVE" | "INACTIVE"; version: number;
}
interface VariantView {
  publicId: string; variantCode: string | null; name: string;
  trackingStrategy: string | null; state: "ACTIVE" | "INACTIVE"; version: number;
}
interface ProductUomView {
  publicId: string; uomPublicId: string; uomCode: string; uomName: string;
  variantPublicId: string | null; role: string; conversionFactor: number;
  state: "ACTIVE" | "INACTIVE"; version: number;
}
interface BarcodeView {
  publicId: string; namespace: string; value: string; variantPublicId: string | null;
  productUomPublicId: string | null; state: "ACTIVE" | "INACTIVE"; version: number;
}
interface CategoryView {
  publicId: string; code: string; name: string; parentCategoryPublicId: string | null;
  isPrimary: boolean; version: number;
}
interface CategoryLookup {
  publicId: string; code: string; name: string; parentCategoryPublicId: string | null; version: number;
}
interface ExternalView {
  publicId: string; systemCode: string; accountScope: string | null; externalIdentity: string;
  variantPublicId: string | null; state: "ACTIVE" | "INACTIVE"; version: number;
}
interface ProductDetail extends ProductListItem {
  description: string | null;
  variants: VariantView[];
  uoms: ProductUomView[];
  barcodes: BarcodeView[];
  categories: CategoryView[];
  externalMappings: ExternalView[];
}

type ProductApi = Pick<ApiClient, "get" | "request">;

export function createProductMasterPage(
  api: ProductApi,
  operationKey: () => string = () => crypto.randomUUID()): HTMLElement
{
  const root = document.createElement("section");
  root.className = "mars-component-stack";

  const heading = document.createElement("h1");
  heading.textContent = "Ürünler ve Hizmetler";
  const note = info(
    "Product master stok miktarı veya maliyet otoritesi değildir. Inventory Ledger ve Finance ayrı kalır.");
  const status = info("");

  const uomCode = createField({ id: "product-new-uom-code", label: "UOM kodu" });
  const uomName = createField({ id: "product-new-uom-name", label: "UOM adı" });
  const createUom = createButton({ label: "UOM oluştur" });

  const productCode = createField({ id: "product-new-code", label: "Product Code" });
  const productName = createField({ id: "product-new-name", label: "Ad" });
  const productDescription = createField({ id: "product-new-description", label: "Açıklama" });
  const kind = createSelect("product-new-kind", "Tür", [["GOODS", "GOODS"], ["SERVICE", "SERVICE"]]);
  const tracking = createSelect(
    "product-new-tracking", "Tracking",
    [["NONE", "NONE"], ["LOT", "LOT"], ["SERIAL", "SERIAL"], ["LOT_SERIAL", "LOT_SERIAL"]]);
  const baseUom = createSelect("product-new-base-uom", "Base UOM", []);
  const sellable = createCheckbox("product-new-sellable", "SELLABLE", true);
  const purchasable = createCheckbox("product-new-purchasable", "PURCHASABLE", true);
  const stockable = createCheckbox("product-new-stockable", "STOCKABLE", false);
  const createProduct = createButton({ label: "Product oluştur", variant: "primary" });

  const createPanel = document.createElement("div");
  createPanel.className = "mars-component-stack";
  createPanel.append(
    sectionTitle("Yeni UOM"), uomCode.element, uomName.element, createUom,
    sectionTitle("Yeni Product"), productCode.element, productName.element,
    productDescription.element, kind.root, tracking.root, baseUom.root,
    sellable.root, purchasable.root, stockable.root, createProduct);

  const search = createField({
    id: "product-search",
    label: "Ara",
    help: "Product/Variant kodu, ad, barkod veya kategori"
  });
  const refresh = createButton({ label: "Listeyi yenile" });
  const grid = createGrid<ProductListItem>({
    caption: "Ürün Listesi",
    columns: [
      { key: "code", header: "Kod", value: row => row.productCode },
      { key: "name", header: "Ad", value: row => row.name },
      { key: "kind", header: "Tür", value: row => row.kind },
      { key: "uom", header: "Base UOM", value: row => row.baseUomCode },
      { key: "state", header: "Durum", value: row => row.state }
    ],
    rows: [],
    onRowActivate: row => { void loadDetail(row.publicId); }
  });
  const detail = document.createElement("div");
  detail.className = "mars-component-stack";

  let selected: ProductDetail | null = null;
  let uoms: UomView[] = [];
  let categories: CategoryLookup[] = [];

  refresh.addEventListener("click", () => { void loadList(); });
  search.input.addEventListener("change", () => { void loadList(); });

  createUom.addEventListener("click", async () => {
    await mutate(
      "/products/uoms", "POST",
      { code: uomCode.input.value.trim(), name: uomName.input.value.trim() },
      "UOM oluşturuldu.");
    uomCode.input.value = "";
    uomName.input.value = "";
    await loadLookups();
  });

  createProduct.addEventListener("click", async () => {
    const selectedBaseUom =
      baseUom.select.value || uoms.find(item => item.state === "ACTIVE")?.publicId || "";
    if (!selectedBaseUom) {
      status.textContent = "Önce ACTIVE bir UOM oluşturun/seçin.";
      return;
    }
    await mutate(
      "/products", "POST",
      {
        productCode: productCode.input.value.trim(),
        name: productName.input.value.trim(),
        description: nullable(productDescription.input.value),
        kind: kind.select.value,
        sellable: sellable.input.checked,
        purchasable: purchasable.input.checked,
        stockable: stockable.input.checked,
        trackingStrategy: tracking.select.value,
        baseUomPublicId: selectedBaseUom
      },
      "Product oluşturuldu.");
    await loadList();
  });

  async function loadLookups(): Promise<void> {
    try {
      const result = await api.get<UomView[]>("/products/uoms");
      uoms = result.data;
      fillSelect(
        baseUom.select,
        uoms.filter(x => x.state === "ACTIVE").map(x => [x.publicId, x.code + " · " + x.name]));
    } catch {
      uoms = [];
      fillSelect(baseUom.select, []);
    }

    try {
      const result = await api.get<CategoryLookup[]>("/products/categories");
      categories = result.data;
    } catch {
      categories = [];
    }
  }

  async function loadList(): Promise<void> {
    try {
      const query = search.input.value.trim();
      const result = await api.get<ProductListItem[]>(
        "/products" + (query ? "?search=" + encodeURIComponent(query) : ""));
      grid.setRows(
        result.data,
        result.data.length > 0 ? { kind: "ready" } : { kind: "empty", message: "Product yok." });
      status.textContent = result.data.length + " Product.";
    } catch (error) {
      showError(error, status, "Product listesi yüklenemedi.");
      grid.setRows([], { kind: "error", message: "Liste yüklenemedi." });
    }
  }

  async function loadDetail(publicId: string): Promise<void> {
    try {
      const result = await api.get<ProductDetail>("/products/" + publicId);
      selected = result.data;
      renderDetail();
    } catch (error) {
      showError(error, status, "Product detayı yüklenemedi.");
    }
  }

  function renderDetail(): void {
    detail.replaceChildren();
    if (!selected) return;

    const product = selected;
    const title = document.createElement("h2");
    title.textContent = product.productCode + " — " + product.name;
    const meta = info(
      product.kind + " · " + product.state + " · " + product.trackingStrategy + " · v" + product.version);

    const editCode = createField({ id: "product-edit-code", label: "Product Code", value: product.productCode });
    const editName = createField({ id: "product-edit-name", label: "Ad", value: product.name });
    const editDescription = createField({
      id: "product-edit-description", label: "Açıklama", value: product.description ?? ""
    });
    const editSellable = createCheckbox("product-edit-sellable", "SELLABLE", product.sellable);
    const editPurchasable = createCheckbox("product-edit-purchasable", "PURCHASABLE", product.purchasable);
    const save = createButton({ label: "Product bilgisini kaydet", variant: "primary" });
    save.addEventListener("click", () => {
      void mutate(
        "/products/" + product.publicId,
        "PUT",
        {
          version: product.version,
          productCode: editCode.input.value.trim(),
          name: editName.input.value.trim(),
          description: nullable(editDescription.input.value),
          sellable: editSellable.input.checked,
          purchasable: editPurchasable.input.checked
        },
        "Product güncellendi.",
        true);
    });

    const lifecycle = createButton({
      label: product.state === "ACTIVE" ? "Product'ı pasife al" : "Product'ı etkinleştir",
      variant: product.state === "ACTIVE" ? "danger" : "secondary"
    });
    lifecycle.addEventListener("click", () => {
      void mutate(
        "/products/" + product.publicId +
          (product.state === "ACTIVE" ? "/deactivate" : "/reactivate"),
        "POST",
        { version: product.version },
        "Product durumu değiştirildi.",
        true);
    });

    const variantCode = createField({ id: "product-variant-code", label: "Variant/SKU Code" });
    const variantName = createField({ id: "product-variant-name", label: "Varyant adı" });
    const variantTracking = createSelect(
      "product-variant-tracking", "Tracking override",
      [["", "INHERIT"], ["NONE", "NONE"], ["LOT", "LOT"], ["SERIAL", "SERIAL"], ["LOT_SERIAL", "LOT_SERIAL"]]);
    const addVariant = createButton({ label: "Varyant ekle" });
    addVariant.addEventListener("click", () => {
      void mutate(
        "/products/" + product.publicId + "/variants",
        "POST",
        {
          variantCode: nullable(variantCode.input.value),
          name: variantName.input.value.trim(),
          trackingStrategy: variantTracking.select.value || null
        },
        "Varyant eklendi.",
        true);
    });
    const variants = document.createElement("div");
    for (const variant of product.variants) {
      variants.append(info(
        (variant.variantCode ?? "—") + " · " + variant.name + " · " +
        (variant.trackingStrategy ?? "INHERIT") + " · " + variant.state + " · v" + variant.version));
      const toggle = createButton({ label: variant.state === "ACTIVE" ? "Pasife al" : "Etkinleştir" });
      toggle.addEventListener("click", () => {
        void mutate(
          "/products/" + product.publicId + "/variants/" + variant.publicId + "/state",
          "POST",
          { version: variant.version, state: opposite(variant.state) },
          "Varyant durumu değiştirildi.",
          true);
      });
      variants.append(toggle);
    }

    const alternateUom = createSelect("product-alt-uom", "Alternate UOM", []);
    fillSelect(
      alternateUom.select,
      uoms.filter(x => x.state === "ACTIVE").map(x => [x.publicId, x.code + " · " + x.name]));
    const alternateVariant = createSelect(
      "product-alt-variant", "Varyant",
      [["", "Product seviyesi"], ...product.variants.map(
        x => [x.publicId, (x.variantCode ?? "—") + " · " + x.name] as [string, string])]);
    const factor = createField({ id: "product-alt-factor", label: "Dönüşüm faktörü", value: "1" });
    const addUom = createButton({ label: "Alternate UOM ekle" });
    addUom.addEventListener("click", () => {
      void mutate(
        "/products/" + product.publicId + "/uoms",
        "POST",
        {
          variantPublicId: alternateVariant.select.value || null,
          uomPublicId: alternateUom.select.value,
          conversionFactor: Number(factor.input.value)
        },
        "UOM mapping eklendi.",
        true);
    });
    const productUoms = document.createElement("div");
    for (const uom of product.uoms) {
      productUoms.append(info(
        uom.role + " · " + uom.uomCode + " · 1 = " + uom.conversionFactor +
        " base · " + uom.state + " · v" + uom.version));
      if (uom.role !== "BASE") {
        const toggle = createButton({ label: uom.state === "ACTIVE" ? "Pasife al" : "Etkinleştir" });
        toggle.addEventListener("click", () => {
          void mutate(
            "/products/" + product.publicId + "/uoms/" + uom.publicId + "/state",
            "POST",
            { version: uom.version, state: opposite(uom.state) },
            "UOM durumu değiştirildi.",
            true);
        });
        productUoms.append(toggle);
      }
    }

    const barcodeNamespace = createField({
      id: "product-barcode-namespace", label: "Barkod namespace", value: "INTERNAL"
    });
    const barcodeValue = createField({ id: "product-barcode-value", label: "Barkod / GTIN" });
    const addBarcode = createButton({ label: "Barkod ekle" });
    addBarcode.addEventListener("click", () => {
      void mutate(
        "/products/" + product.publicId + "/barcodes",
        "POST",
        {
          variantPublicId: null,
          productUomPublicId: null,
          namespace: barcodeNamespace.input.value.trim(),
          value: barcodeValue.input.value.trim()
        },
        "Barkod eklendi.",
        true);
    });
    const barcodes = document.createElement("div");
    for (const barcode of product.barcodes) {
      barcodes.append(info(
        barcode.namespace + " · " + barcode.value + " · " + barcode.state + " · v" + barcode.version));
      const toggle = createButton({ label: barcode.state === "ACTIVE" ? "Pasife al" : "Etkinleştir" });
      toggle.addEventListener("click", () => {
        void mutate(
          "/products/" + product.publicId + "/barcodes/" + barcode.publicId + "/state",
          "POST",
          { version: barcode.version, state: opposite(barcode.state) },
          "Barkod durumu değiştirildi.",
          true);
      });
      barcodes.append(toggle);
    }

    const category = createSelect(
      "product-category", "Kategori",
      categories.map(x => [x.publicId, x.code + " · " + x.name]));
    const primary = createCheckbox("product-category-primary", "Primary kategori", false);
    const assignCategory = createButton({ label: "Kategori ata" });
    assignCategory.addEventListener("click", () => {
      void mutate(
        "/products/" + product.publicId + "/categories",
        "POST",
        { categoryPublicId: category.select.value, isPrimary: primary.input.checked },
        "Kategori atandı.",
        true);
    });
    const categoryList = document.createElement("div");
    for (const item of product.categories) {
      categoryList.append(info(item.code + " · " + item.name + (item.isPrimary ? " · PRIMARY" : "")));
      const remove = createButton({ label: "Kategoriyi kaldır", variant: "quiet" });
      remove.addEventListener("click", () => {
        void mutate(
          "/products/" + product.publicId + "/categories/" + item.publicId + "/unassign",
          "POST",
          {},
          "Kategori kaldırıldı.",
          true);
      });
      categoryList.append(remove);
    }

    const systemCode = createField({ id: "product-external-system", label: "Sistem / provider" });
    const accountScope = createField({ id: "product-external-account", label: "Account scope" });
    const externalIdentity = createField({ id: "product-external-id", label: "External ID/SKU" });
    const addExternal = createButton({ label: "External mapping ekle" });
    addExternal.addEventListener("click", () => {
      void mutate(
        "/products/" + product.publicId + "/external-mappings",
        "POST",
        {
          variantPublicId: null,
          systemCode: systemCode.input.value.trim(),
          accountScope: nullable(accountScope.input.value),
          externalIdentity: externalIdentity.input.value.trim()
        },
        "External mapping eklendi.",
        true);
    });
    const mappings = document.createElement("div");
    for (const mapping of product.externalMappings) {
      mappings.append(info(
        mapping.systemCode + (mapping.accountScope ? " / " + mapping.accountScope : "") +
        " -> " + mapping.externalIdentity + " · " + mapping.state + " · v" + mapping.version));
      const toggle = createButton({ label: mapping.state === "ACTIVE" ? "Pasife al" : "Etkinleştir" });
      toggle.addEventListener("click", () => {
        void mutate(
          "/products/" + product.publicId + "/external-mappings/" + mapping.publicId + "/state",
          "POST",
          { version: mapping.version, state: opposite(mapping.state) },
          "Mapping durumu değiştirildi.",
          true);
      });
      mappings.append(toggle);
    }

    detail.append(
      title, meta,
      sectionTitle("Kimlik"), editCode.element, editName.element, editDescription.element,
      editSellable.root, editPurchasable.root, save, lifecycle,
      sectionTitle("Varyantlar"), variantCode.element, variantName.element,
      variantTracking.root, addVariant, variants,
      sectionTitle("UOM"), alternateUom.root, alternateVariant.root, factor.element, addUom, productUoms,
      sectionTitle("Barkod"), barcodeNamespace.element, barcodeValue.element, addBarcode, barcodes,
      sectionTitle("Kategoriler"), category.root, primary.root, assignCategory, categoryList,
      sectionTitle("External mappings"), systemCode.element, accountScope.element,
      externalIdentity.element, addExternal, mappings);
  }

  async function mutate(
    path: string,
    method: "POST" | "PUT",
    body: unknown,
    message: string,
    reload = false): Promise<void>
  {
    try {
      await api.request(path, {
        method,
        headers: {
          "Content-Type": "application/json",
          "Idempotency-Key": operationKey()
        },
        body: JSON.stringify(body)
      });
      status.textContent = message;
      if (reload && selected) await loadDetail(selected.publicId);
    } catch (error) {
      showError(error, status, "İşlem tamamlanamadı.");
    }
  }

  root.append(heading, note, status, createPanel, search.element, refresh, grid.element, detail);
  queueMicrotask(() => {
    void loadLookups();
    void loadList();
  });
  return root;
}

function createSelect(
  id: string,
  labelText: string,
  items: readonly (readonly [string, string])[]):
  { root: HTMLElement; select: HTMLSelectElement }
{
  const root = document.createElement("div");
  root.className = "mars-field";
  const label = document.createElement("label");
  label.htmlFor = id;
  label.className = "mars-field__label";
  label.textContent = labelText;
  const select = document.createElement("select");
  select.id = id;
  select.className = "mars-field__control";
  fillSelect(select, items);
  root.append(label, select);
  return { root, select };
}

function fillSelect(
  select: HTMLSelectElement,
  items: readonly (readonly [string, string])[]): void
{
  const previous = select.value;
  select.replaceChildren();
  for (const [value, label] of items) {
    const option = document.createElement("option");
    option.value = value;
    option.textContent = label;
    select.append(option);
  }
  if (Array.from(select.options).some(option => option.value === previous)) {
    select.value = previous;
  }
}

function createCheckbox(
  id: string,
  labelText: string,
  checked: boolean): { root: HTMLElement; input: HTMLInputElement }
{
  const root = document.createElement("label");
  root.className = "mars-field";
  const input = document.createElement("input");
  input.type = "checkbox";
  input.id = id;
  input.checked = checked;
  const label = document.createElement("span");
  label.textContent = labelText;
  root.append(input, label);
  return { root, input };
}

function sectionTitle(value: string): HTMLHeadingElement {
  const heading = document.createElement("h3");
  heading.textContent = value;
  return heading;
}

function info(value: string): HTMLParagraphElement {
  const element = document.createElement("p");
  element.textContent = value;
  return element;
}

function nullable(value: string): string | null {
  const normalized = value.trim();
  return normalized.length > 0 ? normalized : null;
}

function opposite(value: "ACTIVE" | "INACTIVE"): "ACTIVE" | "INACTIVE" {
  return value === "ACTIVE" ? "INACTIVE" : "ACTIVE";
}

function showError(error: unknown, target: HTMLElement, fallback: string): void {
  target.textContent = error instanceof ApiClientError
    ? error.message + " (" + error.code + ")"
    : fallback;
}
