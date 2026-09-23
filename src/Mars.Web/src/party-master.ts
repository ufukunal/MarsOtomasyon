import { ApiClientError, type ApiResponse } from "./api-client";
import { createButton, createField, createGrid } from "./ui/components";

export interface PartyMasterApi {
  get<T>(path: string, signal?: AbortSignal): Promise<ApiResponse<T>>;
  request<T>(path: string, init?: RequestInit): Promise<ApiResponse<T>>;
}

interface PartyListItem {
  publicId: string;
  partyCode: string;
  kind: "PERSON" | "ORGANIZATION";
  legalName: string;
  displayName: string | null;
  state: "ACTIVE" | "INACTIVE" | "MERGED";
  version: number;
}

interface PartyContactView {
  publicId: string;
  name: string;
  title: string | null;
  purpose: string | null;
  state: "ACTIVE" | "INACTIVE";
  version: number;
  communicationPoints: Array<{
    publicId: string;
    type: string;
    value: string;
    purpose: string | null;
    isPrimary: boolean;
    state: "ACTIVE" | "INACTIVE";
    version: number;
  }>;
}

interface PartyAddressView {
  publicId: string;
  purpose: "BILLING" | "SHIPPING" | "GENERAL";
  country: string;
  city: string | null;
  district: string | null;
  isDefault: boolean;
  state: "ACTIVE" | "INACTIVE";
  version: number;
}

interface PartyTaxIdentityView {
  publicId: string;
  jurisdiction: "TR";
  scheme: "VKN" | "TCKN";
  value: string;
  isMasked: boolean;
  state: "ACTIVE" | "INACTIVE";
  version: number;
}

interface PartyExternalMappingView {
  publicId: string;
  systemCode: string;
  accountScope: string | null;
  externalIdentity: string;
  state: "ACTIVE" | "INACTIVE";
  version: number;
}

interface PartyDetail extends PartyListItem {
  mergeSurvivorPublicId: string | null;
  contacts: PartyContactView[];
  addresses: PartyAddressView[];
  taxIdentities: PartyTaxIdentityView[];
  externalMappings: PartyExternalMappingView[];
}

interface MutationReceipt {
  entityPublicId: string;
  state: string;
  version: number;
  correlationId: string;
}

interface MergeReceipt {
  sourcePartyPublicId: string;
  survivorPartyPublicId: string;
  sourceState: "MERGED";
  sourceVersion: number;
  survivorVersion: number;
  correlationId: string;
}

export function createPartyMasterPage(
  api: PartyMasterApi,
  createOperationKey: () => string = () => crypto.randomUUID()): HTMLElement
{
  const root = document.createElement("section");
  root.className = "mars-foundation-panel mars-component-stack";

  const heading = document.createElement("h1");
  heading.textContent = "Cari / Party Master";

  const note = document.createElement("p");
  note.textContent =
    "Canlı Party master verisi şirket kapsamında yönetilir; tarihsel belge snapshotları değiştirilmez.";

  const status = document.createElement("p");
  status.setAttribute("role", "status");
  status.setAttribute("aria-live", "polite");

  const search = createField({
    id: "party-master-search",
    label: "Ara",
    help: "Party Code, yasal ad veya görünen ad"
  });
  const refresh = createButton({ label: "Listeyi yenile", variant: "primary" });

  let selected: PartyDetail | null = null;

  const grid = createGrid<PartyListItem>({
    caption: "Party listesi",
    columns: [
      { key: "code", header: "Kod", value: row => row.partyCode },
      { key: "name", header: "Yasal ad", value: row => row.legalName },
      { key: "kind", header: "Tür", value: row => row.kind },
      { key: "state", header: "Durum", value: row => row.state }
    ],
    rows: [],
    state: { kind: "loading", message: "Party listesi yükleniyor." },
    onRowActivate: row => { void loadDetail(row.publicId); }
  });

  const detail = document.createElement("section");
  detail.className = "mars-component-stack";
  detail.hidden = true;

  const summary = document.createElement("p");
  const legalName = createField({ id: "party-master-legal-name", label: "Yasal ad", required: true });
  const displayName = createField({ id: "party-master-display-name", label: "Görünen / ticari ad" });
  const saveIdentity = createButton({ label: "Kimliği kaydet", variant: "primary" });

  const contactName = createField({ id: "party-contact-name", label: "Contact adı", required: true });
  const contactTitle = createField({ id: "party-contact-title", label: "Görev / unvan" });
  const contactPurpose = createField({ id: "party-contact-purpose", label: "Amaç" });
  const addContact = createButton({ label: "Contact ekle" });
  const contacts = document.createElement("div");

  const communicationContactId = createField({
    id: "party-communication-contact-id",
    label: "Contact Public ID",
    required: true
  });
  const communicationType = createField({ id: "party-communication-type", label: "İletişim tipi", required: true });
  const communicationValue = createField({ id: "party-communication-value", label: "İletişim değeri", required: true });
  const communicationPurpose = createField({ id: "party-communication-purpose", label: "Amaç" });
  const communicationPrimary = createCheckbox("party-communication-primary", "Primary", false);
  const addCommunication = createButton({ label: "Communication Point ekle" });

  const addressPurpose = createSelect(
    "party-address-purpose",
    "Adres amacı",
    ["BILLING", "SHIPPING", "GENERAL"]);
  const addressCountry = createField({ id: "party-address-country", label: "Ülke / jurisdiction", required: true });
  const addressCity = createField({ id: "party-address-city", label: "Şehir" });
  const addressDistrict = createField({ id: "party-address-district", label: "İlçe" });
  const addressPostalCode = createField({ id: "party-address-postal", label: "Posta kodu" });
  const addressLine1 = createField({ id: "party-address-line1", label: "Adres satırı 1" });
  const addressLine2 = createField({ id: "party-address-line2", label: "Adres satırı 2" });
  const addressLabel = createField({ id: "party-address-label", label: "Etiket" });
  const addressDefault = createCheckbox("party-address-default", "Bu amaç için varsayılan", false);
  const addAddress = createButton({ label: "Adres ekle" });
  const addresses = document.createElement("div");

  const taxes = document.createElement("div");

  const externalSystem = createField({ id: "party-external-system", label: "Provider / sistem", required: true });
  const externalScope = createField({ id: "party-external-scope", label: "Account scope" });
  const externalIdentity = createField({ id: "party-external-id", label: "External identity", required: true });
  const addExternal = createButton({ label: "External mapping ekle" });
  const externalMappings = document.createElement("div");

  const survivorId = createField({
    id: "party-merge-survivor",
    label: "Survivor Party Public ID",
    required: true
  });
  const mergeReason = createField({ id: "party-merge-reason", label: "Merge nedeni", required: true });
  const useSourceIdentity = createCheckbox("party-merge-use-source-identity", "Source kimliğini survivor'a taşı", false);
  const moveRoles = createCheckbox("party-merge-roles", "Unique rolleri taşı", true);
  const moveContacts = createCheckbox("party-merge-contacts", "Contact/communication taşı", true);
  const moveAddresses = createCheckbox("party-merge-addresses", "Adresleri taşı", true);
  const moveTax = createCheckbox("party-merge-tax", "Vergi kimliklerini taşı", true);
  const moveExternal = createCheckbox("party-merge-external", "External mappingleri taşı", true);
  const merge = createButton({ label: "Party'leri merge et" });

  detail.append(
    sectionTitle("Party detayı"), summary,
    legalName.element, displayName.element, saveIdentity,
    sectionTitle("Contacts / Communication Points"), contacts,
    contactName.element, contactTitle.element, contactPurpose.element, addContact,
    communicationContactId.element, communicationType.element, communicationValue.element,
    communicationPurpose.element, communicationPrimary.root, addCommunication,
    sectionTitle("Adresler"), addresses,
    addressPurpose.root, addressCountry.element, addressCity.element, addressDistrict.element,
    addressPostalCode.element, addressLine1.element, addressLine2.element, addressLabel.element,
    addressDefault.root, addAddress,
    sectionTitle("TR Vergi Kimlikleri"), taxes,
    sectionTitle("External Mappings"), externalMappings,
    externalSystem.element, externalScope.element, externalIdentity.element, addExternal,
    sectionTitle("Party Merge"),
    info("Fuzzy eşleştirme yapılmaz; source ve survivor açıkça seçilir."),
    survivorId.element, mergeReason.element, useSourceIdentity.root,
    moveRoles.root, moveContacts.root, moveAddresses.root, moveTax.root, moveExternal.root, merge);

  refresh.addEventListener("click", () => { void loadList(); });
  search.input.addEventListener("keydown", event => {
    if (event.key === "Enter") {
      event.preventDefault();
      void loadList();
    }
  });

  saveIdentity.addEventListener("click", () => {
    if (!selected) return;
    void mutate(
      "/parties/" + selected.publicId,
      "PUT",
      {
        version: selected.version,
        legalName: legalName.input.value,
        displayName: nullable(displayName.input.value)
      },
      "Party kimliği güncellendi.");
  });

  addContact.addEventListener("click", () => {
    if (!selected) return;
    void mutate(
      "/parties/" + selected.publicId + "/contacts",
      "POST",
      {
        name: contactName.input.value,
        title: nullable(contactTitle.input.value),
        purpose: nullable(contactPurpose.input.value)
      },
      "Contact eklendi.");
  });

  addCommunication.addEventListener("click", () => {
    if (!selected) return;
    const contactId = communicationContactId.input.value.trim();
    if (contactId.length === 0) {
      status.textContent = "Communication Point için Contact Public ID zorunludur.";
      return;
    }

    void mutate(
      "/parties/" + selected.publicId + "/contacts/" + contactId + "/communications",
      "POST",
      {
        type: communicationType.input.value,
        value: communicationValue.input.value,
        purpose: nullable(communicationPurpose.input.value),
        isPrimary: communicationPrimary.input.checked
      },
      "Communication Point eklendi.");
  });

  addAddress.addEventListener("click", () => {
    if (!selected) return;
    void mutate(
      "/parties/" + selected.publicId + "/addresses",
      "POST",
      {
        purpose: addressPurpose.select.value,
        country: addressCountry.input.value,
        city: nullable(addressCity.input.value),
        district: nullable(addressDistrict.input.value),
        postalCode: nullable(addressPostalCode.input.value),
        line1: nullable(addressLine1.input.value),
        line2: nullable(addressLine2.input.value),
        label: nullable(addressLabel.input.value),
        isDefault: addressDefault.input.checked
      },
      "Adres eklendi.");
  });

  addExternal.addEventListener("click", () => {
    if (!selected) return;
    void mutate(
      "/parties/" + selected.publicId + "/external-mappings",
      "POST",
      {
        systemCode: externalSystem.input.value,
        accountScope: nullable(externalScope.input.value),
        externalIdentity: externalIdentity.input.value
      },
      "External mapping eklendi.");
  });

  merge.addEventListener("click", async () => {
    if (!selected) return;
    const survivor = survivorId.input.value.trim();
    const reason = mergeReason.input.value.trim();
    if (survivor.length === 0 || reason.length === 0) {
      status.textContent = "Merge için survivor Party ve neden zorunludur.";
      return;
    }

    merge.disabled = true;
    try {
      const survivorDetail = await api.get<PartyDetail>("/parties/" + survivor);
      const response = await api.request<MergeReceipt>(
        "/parties/" + selected.publicId + "/merge",
        {
          method: "POST",
          headers: jsonHeaders(createOperationKey()),
          body: JSON.stringify({
            survivorPartyPublicId: survivor,
            sourceVersion: selected.version,
            survivorVersion: survivorDetail.data.version,
            useSourceIdentity: useSourceIdentity.input.checked,
            moveSourceRoles: moveRoles.input.checked,
            moveSourceContacts: moveContacts.input.checked,
            moveSourceAddresses: moveAddresses.input.checked,
            moveSourceTaxIdentities: moveTax.input.checked,
            moveSourceExternalMappings: moveExternal.input.checked,
            reason
          })
        });
      status.textContent =
        "Merge tamamlandı: " + response.data.sourcePartyPublicId + " -> " + response.data.survivorPartyPublicId + ".";
      await loadList();
      await loadDetail(response.data.sourcePartyPublicId);
    } catch (error) {
      showError(error, status, "Merge tamamlanamadı.");
    } finally {
      merge.disabled = false;
    }
  });

  async function loadList(): Promise<void> {
    grid.setRows([], { kind: "loading", message: "Party listesi yükleniyor." });
    try {
      const query = search.input.value.trim();
      const path = query.length > 0
        ? "/parties?search=" + encodeURIComponent(query)
        : "/parties";
      const response = await api.get<PartyListItem[]>(path);
      grid.setRows(
        response.data,
        response.data.length === 0
          ? { kind: "empty", message: "Party bulunamadı." }
          : { kind: "ready" });
      status.textContent = response.data.length + " Party listelendi.";
    } catch (error) {
      grid.setRows([], { kind: "error", message: "Party listesi alınamadı." });
      showError(error, status, "Party listesi alınamadı.");
    }
  }

  async function loadDetail(publicId: string): Promise<void> {
    try {
      const response = await api.get<PartyDetail>("/parties/" + publicId);
      selected = response.data;
      detail.hidden = false;
      legalName.input.value = selected.legalName;
      displayName.input.value = selected.displayName ?? "";
      summary.textContent =
        selected.partyCode + " · " + selected.kind + " · " + selected.state + " · v" + selected.version +
        (selected.mergeSurvivorPublicId ? " · MERGED -> " + selected.mergeSurvivorPublicId : "");
      renderContacts(selected.contacts);
      renderAddresses(selected.addresses);
      renderTaxes(selected.taxIdentities);
      renderExternal(selected.externalMappings);
      status.textContent = "Party " + selected.partyCode + " yüklendi.";
    } catch (error) {
      showError(error, status, "Party detayı alınamadı.");
    }
  }

  async function mutate(
    path: string,
    method: "POST" | "PUT",
    body: unknown,
    successMessage: string): Promise<void>
  {
    if (!selected) return;
    try {
      const response = await api.request<MutationReceipt>(path, {
        method,
        headers: jsonHeaders(createOperationKey()),
        body: JSON.stringify(body)
      });
      status.textContent = successMessage + " Correlation: " + response.data.correlationId + ".";
      const currentId = selected.publicId;
      await loadDetail(currentId);
      await loadList();
    } catch (error) {
      showError(error, status, "Party master işlemi tamamlanamadı.");
    }
  }

  function renderContacts(items: PartyContactView[]): void {
    contacts.replaceChildren();
    if (items.length === 0) contacts.append(info("Contact kaydı yok."));
    for (const contact of items) {
      const card = document.createElement("div");
      card.append(info(contact.name + " · " + contact.state + " · v" + contact.version + " · " + contact.publicId));
      const toggle = createButton({ label: contact.state === "ACTIVE" ? "Contact pasife al" : "Contact etkinleştir" });
      toggle.addEventListener("click", () => {
        if (!selected) return;
        void mutate(
          "/parties/" + selected.publicId + "/contacts/" + contact.publicId + "/state",
          "POST",
          { version: contact.version, state: opposite(contact.state) },
          "Contact durumu değiştirildi.");
      });
      card.append(toggle);

      for (const point of contact.communicationPoints) {
        const row = document.createElement("div");
        row.append(info(point.type + ": " + point.value + " · " + point.state + " · v" + point.version));
        const pointToggle = createButton({
          label: point.state === "ACTIVE" ? "İletişimi pasife al" : "İletişimi etkinleştir"
        });
        pointToggle.addEventListener("click", () => {
          if (!selected) return;
          void mutate(
            "/parties/" + selected.publicId + "/contacts/" + contact.publicId +
              "/communications/" + point.publicId + "/state",
            "POST",
            { version: point.version, state: opposite(point.state) },
            "Communication Point durumu değiştirildi.");
        });
        row.append(pointToggle);
        card.append(row);
      }
      contacts.append(card);
    }
  }

  function renderAddresses(items: PartyAddressView[]): void {
    addresses.replaceChildren();
    if (items.length === 0) addresses.append(info("Adres kaydı yok."));
    for (const address of items) {
      const card = document.createElement("div");
      card.append(info(
        address.purpose + " · " + address.country +
        (address.city ? " / " + address.city : "") +
        " · " + address.state + " · v" + address.version +
        (address.isDefault ? " · DEFAULT" : "")));
      const toggle = createButton({ label: address.state === "ACTIVE" ? "Adresi pasife al" : "Adresi etkinleştir" });
      toggle.addEventListener("click", () => {
        if (!selected) return;
        void mutate(
          "/parties/" + selected.publicId + "/addresses/" + address.publicId + "/state",
          "POST",
          { version: address.version, state: opposite(address.state) },
          "Adres durumu değiştirildi.");
      });
      card.append(toggle);
      addresses.append(card);
    }
  }

  function renderTaxes(items: PartyTaxIdentityView[]): void {
    taxes.replaceChildren();
    if (items.length === 0) taxes.append(info("Vergi kimliği görünmüyor veya kayıt yok."));
    for (const identity of items) {
      const card = document.createElement("div");
      card.append(info(
        identity.jurisdiction + " " + identity.scheme + ": " + identity.value +
        " · " + identity.state + " · v" + identity.version +
        (identity.isMasked ? " · MASKED" : "")));
      const toggle = createButton({
        label: identity.state === "ACTIVE" ? "Vergi kimliğini pasife al" : "Vergi kimliğini etkinleştir"
      });
      toggle.addEventListener("click", () => {
        if (!selected) return;
        void mutate(
          "/parties/" + selected.publicId + "/tax-identities/" + identity.publicId + "/state",
          "POST",
          { version: identity.version, state: opposite(identity.state) },
          "Vergi kimliği durumu değiştirildi.");
      });
      card.append(toggle);
      taxes.append(card);
    }
  }

  function renderExternal(items: PartyExternalMappingView[]): void {
    externalMappings.replaceChildren();
    if (items.length === 0) externalMappings.append(info("External mapping kaydı yok veya görünmüyor."));
    for (const mapping of items) {
      const card = document.createElement("div");
      card.append(info(
        mapping.systemCode +
        (mapping.accountScope ? " / " + mapping.accountScope : "") +
        " -> " + mapping.externalIdentity + " · " + mapping.state + " · v" + mapping.version));
      const toggle = createButton({ label: mapping.state === "ACTIVE" ? "Mapping pasife al" : "Mapping etkinleştir" });
      toggle.addEventListener("click", () => {
        if (!selected) return;
        void mutate(
          "/parties/" + selected.publicId + "/external-mappings/" + mapping.publicId + "/state",
          "POST",
          { version: mapping.version, state: opposite(mapping.state) },
          "External mapping durumu değiştirildi.");
      });
      card.append(toggle);
      externalMappings.append(card);
    }
  }

  root.append(heading, note, search.element, refresh, status, grid.element, detail);
  queueMicrotask(() => { void loadList(); });
  return root;
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

function createSelect(
  id: string,
  labelText: string,
  values: readonly string[]): { root: HTMLElement; select: HTMLSelectElement }
{
  const root = document.createElement("div");
  root.className = "mars-field";
  const label = document.createElement("label");
  label.className = "mars-field__label";
  label.htmlFor = id;
  label.textContent = labelText;
  const select = document.createElement("select");
  select.id = id;
  select.className = "mars-field__control";
  for (const value of values) {
    const option = document.createElement("option");
    option.value = value;
    option.textContent = value;
    select.append(option);
  }
  root.append(label, select);
  return { root, select };
}

function jsonHeaders(operationKey: string): HeadersInit {
  return { "Content-Type": "application/json", "Idempotency-Key": operationKey };
}

function nullable(value: string): string | null {
  const trimmed = value.trim();
  return trimmed.length === 0 ? null : trimmed;
}

function opposite(value: "ACTIVE" | "INACTIVE"): "ACTIVE" | "INACTIVE" {
  return value === "ACTIVE" ? "INACTIVE" : "ACTIVE";
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

function showError(error: unknown, target: HTMLElement, fallback: string): void {
  if (error instanceof ApiClientError) {
    target.textContent = error.message + " (" + error.code + ")";
  } else {
    target.textContent = fallback;
  }
}
