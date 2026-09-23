import { ApiClientError, type ApiResponse } from "./api-client";
import { createButton, createField } from "./ui/components";

export interface CreatePartyReceipt {
  publicId: string;
  partyCode: string;
  kind: "PERSON" | "ORGANIZATION";
  legalName: string;
  displayName: string | null;
  state: "ACTIVE";
  version: number;
  correlationId: string;
}

export interface ActivatePartyRoleReceipt {
  partyPublicId: string;
  role: "CUSTOMER" | "SUPPLIER";
  state: "ACTIVE";
  version: number;
  correlationId: string;
}

export interface AddPartyTaxIdentityReceipt {
  publicId: string;
  partyPublicId: string;
  jurisdiction: "TR";
  scheme: "VKN" | "TCKN";
  state: "ACTIVE";
  version: number;
  correlationId: string;
}

export interface PartyCreateApi {
  request<T>(path: string, init?: RequestInit): Promise<ApiResponse<T>>;
}

export function createPartyCreatePage(
  api: PartyCreateApi,
  createOperationKey: () => string = () => crypto.randomUUID()): HTMLElement
{
  const panel = document.createElement("section");
  panel.className = "mars-foundation-panel mars-component-stack";

  const heading = document.createElement("h1");
  heading.textContent = "Yeni Cari / Party";

  const explanation = document.createElement("p");
  explanation.textContent =
    "Önce şirket kapsamlı Party kimliği oluşturulur; ardından aynı kimlikte rol ve Türkiye vergi kimliği eklenebilir.";

  const form = document.createElement("form");
  form.className = "mars-component-stack";

  const code = createField({
    id: "party-code",
    label: "Party Code",
    required: true,
    help: "İlk dilimde kod kullanıcı girdisidir; otomatik numaralandırma uygulanmaz."
  });
  const legalName = createField({
    id: "party-legal-name",
    label: "Yasal ad",
    required: true
  });
  const displayName = createField({
    id: "party-display-name",
    label: "Görünen / ticari ad"
  });

  const kindField = document.createElement("div");
  kindField.className = "mars-field";
  const kindLabel = document.createElement("label");
  kindLabel.className = "mars-field__label";
  kindLabel.htmlFor = "party-kind";
  kindLabel.textContent = "Tür *";
  const kind = document.createElement("select");
  kind.id = "party-kind";
  kind.name = "party-kind";
  kind.className = "mars-field__control";
  kind.required = true;
  for (const [value, label] of [["PERSON", "Kişi"], ["ORGANIZATION", "Kurum"]] as const) {
    const option = document.createElement("option");
    option.value = value;
    option.textContent = label;
    kind.append(option);
  }
  kindField.append(kindLabel, kind);

  const status = document.createElement("p");
  status.setAttribute("role", "status");
  status.setAttribute("aria-live", "polite");

  const roles = document.createElement("section");
  roles.className = "mars-component-stack";
  roles.hidden = true;

  const rolesHeading = document.createElement("h2");
  rolesHeading.textContent = "Roller";

  const rolesHelp = document.createElement("p");
  rolesHelp.textContent =
    "Oluşturulan Party aynı kimlik üzerinde CUSTOMER ve/veya SUPPLIER rolü alabilir. Rol aktivasyonu finansal hareket oluşturmaz.";

  const roleStatus = document.createElement("p");
  roleStatus.setAttribute("role", "status");
  roleStatus.setAttribute("aria-live", "polite");

  const customerRole = createButton({
    label: "CUSTOMER rolünü etkinleştir",
    type: "button"
  });
  const supplierRole = createButton({
    label: "SUPPLIER rolünü etkinleştir",
    type: "button"
  });

  roles.append(rolesHeading, rolesHelp, customerRole, supplierRole, roleStatus);

  const taxIdentities = document.createElement("section");
  taxIdentities.className = "mars-component-stack";
  taxIdentities.hidden = true;

  const taxHeading = document.createElement("h2");
  taxHeading.textContent = "Vergi Kimliği";

  const taxHelp = document.createElement("p");
  taxHelp.textContent =
    "Bu dilim yalnız TR VKN/TCKN yapısal kontrolü yapar; yerel doğruluk GİB/e-belge kayıt veya provider doğrulaması anlamına gelmez.";

  const jurisdiction = document.createElement("p");
  jurisdiction.textContent = "Ülke / jurisdiction: TR";

  const schemeField = document.createElement("div");
  schemeField.className = "mars-field";
  const schemeLabel = document.createElement("label");
  schemeLabel.className = "mars-field__label";
  schemeLabel.htmlFor = "party-tax-scheme";
  schemeLabel.textContent = "Vergi kimliği türü *";
  const scheme = document.createElement("select");
  scheme.id = "party-tax-scheme";
  scheme.className = "mars-field__control";
  for (const value of ["VKN", "TCKN"] as const) {
    const option = document.createElement("option");
    option.value = value;
    option.textContent = value;
    scheme.append(option);
  }
  schemeField.append(schemeLabel, scheme);

  const taxValue = createField({
    id: "party-tax-value",
    label: "Vergi kimliği değeri",
    required: true,
    help: "VKN 10, TCKN 11 rakam olmalıdır."
  });
  taxValue.input.inputMode = "numeric";
  taxValue.input.autocomplete = "off";
  taxValue.input.maxLength = 11;

  const taxStatus = document.createElement("p");
  taxStatus.setAttribute("role", "status");
  taxStatus.setAttribute("aria-live", "polite");

  const addTaxIdentity = createButton({
    label: "Vergi kimliği ekle",
    type: "button"
  });

  taxIdentities.append(
    taxHeading,
    taxHelp,
    jurisdiction,
    schemeField,
    taxValue.element,
    addTaxIdentity,
    taxStatus);

  let createdPartyPublicId: string | null = null;

  const activateRole = async (
    role: "CUSTOMER" | "SUPPLIER",
    button: HTMLButtonElement): Promise<void> => {
    if (!createdPartyPublicId) return;

    button.disabled = true;
    roleStatus.textContent = `${role} rolü etkinleştiriliyor.`;

    try {
      const response = await api.request<ActivatePartyRoleReceipt>(
        `/parties/${createdPartyPublicId}/roles`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Idempotency-Key": createOperationKey()
          },
          body: JSON.stringify({ role })
        });

      roleStatus.textContent =
        `${response.data.role} rolü ACTIVE. Correlation: ${response.data.correlationId}.`;
    } catch (error) {
      button.disabled = false;
      if (error instanceof ApiClientError && error.status === 401) {
        roleStatus.textContent = "Rol etkinleştirmek için kimliği doğrulanmış oturum gerekiyor.";
      } else if (error instanceof ApiClientError && error.status === 403) {
        roleStatus.textContent = "Bu işlem için party.role.manage yetkisi gerekiyor.";
      } else if (error instanceof ApiClientError && error.status === 404) {
        roleStatus.textContent = "Party mevcut şirket kapsamında bulunamadı.";
      } else if (error instanceof ApiClientError && error.status === 409) {
        roleStatus.textContent = "Rol zaten mevcut veya işlem anahtarı daha önce kullanıldı.";
      } else if (error instanceof ApiClientError) {
        roleStatus.textContent = error.message;
      } else {
        roleStatus.textContent = "Party rolü etkinleştirilemedi.";
      }
    }
  };

  customerRole.addEventListener("click", () => {
    void activateRole("CUSTOMER", customerRole);
  });
  supplierRole.addEventListener("click", () => {
    void activateRole("SUPPLIER", supplierRole);
  });

  const addTaxIdentityToParty = async (): Promise<void> => {
    if (!createdPartyPublicId) return;

    addTaxIdentity.disabled = true;
    taxStatus.textContent = "Vergi kimliği ekleniyor.";

    try {
      const response = await api.request<AddPartyTaxIdentityReceipt>(
        `/parties/${createdPartyPublicId}/tax-identities`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Idempotency-Key": createOperationKey()
          },
          body: JSON.stringify({
            jurisdiction: "TR",
            scheme: scheme.value,
            value: taxValue.input.value
          })
        });

      taxValue.input.value = "";
      taxStatus.textContent =
        `${response.data.scheme} kimliği ACTIVE. Correlation: ${response.data.correlationId}.`;
    } catch (error) {
      if (error instanceof ApiClientError && error.status === 401) {
        taxStatus.textContent = "Vergi kimliği eklemek için kimliği doğrulanmış oturum gerekiyor.";
      } else if (error instanceof ApiClientError && error.status === 403) {
        taxStatus.textContent = "Bu işlem için party.tax_identity.manage yetkisi gerekiyor.";
      } else if (error instanceof ApiClientError && error.status === 404) {
        taxStatus.textContent = "Party mevcut şirket kapsamında bulunamadı.";
      } else if (error instanceof ApiClientError && error.status === 409) {
        taxStatus.textContent = "Vergi kimliği veya işlem anahtarı mevcut kayıtla çakışıyor.";
      } else if (error instanceof ApiClientError) {
        taxStatus.textContent = error.message;
      } else {
        taxStatus.textContent = "Vergi kimliği eklenemedi.";
      }
    } finally {
      addTaxIdentity.disabled = false;
    }
  };

  addTaxIdentity.addEventListener("click", () => {
    void addTaxIdentityToParty();
  });

  const submit = createButton({
    label: "Party oluştur",
    variant: "primary",
    type: "submit"
  });

  form.append(code.element, kindField, legalName.element, displayName.element, submit, status);

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    submit.disabled = true;
    status.textContent = "Party oluşturuluyor.";

    try {
      const response = await api.request<CreatePartyReceipt>("/parties", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Idempotency-Key": createOperationKey()
        },
        body: JSON.stringify({
          partyCode: code.input.value,
          kind: kind.value,
          legalName: legalName.input.value,
          displayName: displayName.input.value.length === 0 ? null : displayName.input.value
        })
      });

      createdPartyPublicId = response.data.publicId;
      roles.hidden = false;
      taxIdentities.hidden = false;
      customerRole.disabled = false;
      supplierRole.disabled = false;
      roleStatus.textContent = "İsteğe bağlı CUSTOMER veya SUPPLIER rolünü etkinleştirebilirsiniz.";
      taxStatus.textContent = "İsteğe bağlı TR VKN veya TCKN ekleyebilirsiniz.";
      status.textContent =
        `Party oluşturuldu: ${response.data.partyCode}. Correlation: ${response.data.correlationId}.`;
    } catch (error) {
      if (error instanceof ApiClientError && error.status === 401) {
        status.textContent = "Party oluşturmak için kimliği doğrulanmış oturum gerekiyor.";
      } else if (error instanceof ApiClientError && error.status === 403) {
        status.textContent = "Bu işlem için party.create yetkisi gerekiyor.";
      } else if (error instanceof ApiClientError && error.status === 409) {
        status.textContent = "Party Code veya işlem anahtarı mevcut kayıtla çakışıyor.";
      } else if (error instanceof ApiClientError) {
        status.textContent = error.message;
      } else {
        status.textContent = "Party oluşturulamadı.";
      }
    } finally {
      submit.disabled = false;
    }
  });

  panel.append(heading, explanation, form, roles, taxIdentities);
  return panel;
}
