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
    "İlk Parties dilimi yalnız şirket kapsamlı ana kimliği oluşturur. Rol, vergi, adres ve iletişim bilgileri bu adımda oluşturulmaz.";

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

  panel.append(heading, explanation, form);
  return panel;
}
