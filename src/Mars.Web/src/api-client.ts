export interface ApiResponse<T> {
  data: T;
  correlationId: string | null;
  status: number;
}

export interface ApiErrorPayload {
  code?: string;
  message?: string;
  category?: string;
  correlationId?: string;
}

export class ApiClientError extends Error {
  public readonly status: number;
  public readonly code: string;
  public readonly category: string | null;
  public readonly correlationId: string | null;

  public constructor(
    status: number,
    code: string,
    message: string,
    category: string | null,
    correlationId: string | null)
  {
    super(message);
    this.name = "ApiClientError";
    this.status = status;
    this.code = code;
    this.category = category;
    this.correlationId = correlationId;
  }
}

export class ApiClient {
  public constructor(private readonly baseUrl = "/api/v1") {}

  public get<T>(path: string, signal?: AbortSignal): Promise<ApiResponse<T>> {
    const init: RequestInit = { method: "GET" };
    if (signal) init.signal = signal;
    return this.request<T>(path, init);
  }

  public sendJson<T>(
    path: string,
    method: "POST" | "PUT" | "PATCH" | "DELETE",
    value: unknown,
    signal?: AbortSignal): Promise<ApiResponse<T>>
  {
    const init: RequestInit = {
      method,
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(value)
    };
    if (signal) init.signal = signal;
    return this.request<T>(path, init);
  }

  public async request<T>(
    path: string,
    init: RequestInit = {}): Promise<ApiResponse<T>>
  {
    const headers = new Headers(init.headers);
    headers.set("Accept", "application/json");

    const response = await fetch(this.resolve(path), {
      ...init,
      headers,
      credentials: "same-origin"
    });

    const correlationId = response.headers.get("X-Correlation-ID");
    const data = await this.readBody(response);

    if (!response.ok) {
      const payload = isObject(data) ? data as ApiErrorPayload : {};
      throw new ApiClientError(
        response.status,
        typeof payload.code === "string" ? payload.code : "http.request_failed",
        typeof payload.message === "string" ? payload.message : `Request failed with HTTP ${response.status}.`,
        typeof payload.category === "string" ? payload.category : null,
        typeof payload.correlationId === "string" ? payload.correlationId : correlationId);
    }

    return {
      data: data as T,
      correlationId,
      status: response.status
    };
  }

  private resolve(path: string): string {
    const cleanPath = path.replace(/^\/+/, "");

    if (/^https?:\/\//i.test(this.baseUrl)) {
      const prefix = this.baseUrl.endsWith("/") ? this.baseUrl : `${this.baseUrl}/`;
      return new URL(cleanPath, prefix).toString();
    }

    const prefix = this.baseUrl === "/" ? "" : this.baseUrl.replace(/\/+$/, "");
    return `${prefix}/${cleanPath}`;
  }

  private async readBody(response: Response): Promise<unknown> {
    if (response.status === 204) {
      return undefined;
    }

    const contentType = response.headers.get("Content-Type") ?? "";
    if (contentType.includes("application/json")) {
      return response.json();
    }

    const text = await response.text();
    return text.length === 0 ? undefined : text;
  }
}

function isObject(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}
