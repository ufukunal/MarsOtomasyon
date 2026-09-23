import { ApiClientError, type ApiResponse } from "./api-client";
import { createButton } from "./ui/components";

export interface FoundationProofReceipt {
  eventId: string;
  operationKey: string;
  correlationId: string;
}

export interface FoundationProofApi {
  request<T>(path: string, init?: RequestInit): Promise<ApiResponse<T>>;
}

export function createFoundationProofPage(
  api: FoundationProofApi,
  createOperationKey: () => string = () => crypto.randomUUID()): HTMLElement
{
  const panel = document.createElement("section");
  panel.className = "mars-foundation-panel";

  const heading = document.createElement("h1");
  heading.textContent = "Foundation Vertical Proof";

  const explanation = document.createElement("p");
  explanation.textContent =
    "Bu teknik kanıt, kimliği doğrulanmış isteğin Application ve PostgreSQL üzerinden audit/outbox kaydı üretmesini sınar; ERP iş kuralı içermez.";

  const status = document.createElement("p");
  status.setAttribute("role", "status");
  status.setAttribute("aria-live", "polite");
  status.textContent = "Henüz çalıştırılmadı.";

  const runButton = createButton({
    label: "Proof çalıştır",
    variant: "primary",
    onClick: async () => {
      runButton.disabled = true;
      status.textContent = "Proof çalıştırılıyor.";

      try {
        const operationKey = createOperationKey();
        const response = await api.request<FoundationProofReceipt>("/foundation/proof", {
          method: "POST",
          headers: { "Idempotency-Key": operationKey }
        });

        status.textContent =
          `Proof tamamlandı. Event: ${response.data.eventId}. Correlation: ${response.data.correlationId}.`;
      } catch (error) {
        if (error instanceof ApiClientError && error.status === 401) {
          status.textContent = "Proof için kimliği doğrulanmış oturum gerekiyor.";
        } else if (error instanceof ApiClientError && error.status === 409) {
          status.textContent = "Bu proof idempotency anahtarı daha önce kullanılmış.";
        } else {
          status.textContent = "Foundation proof tamamlanamadı.";
        }
      } finally {
        runButton.disabled = false;
      }
    }
  });

  panel.append(heading, explanation, runButton, status);
  return panel;
}
