export type ButtonVariant = "primary" | "secondary" | "danger" | "quiet";

export interface ButtonOptions {
  label: string;
  variant?: ButtonVariant;
  disabled?: boolean;
  loading?: boolean;
  type?: "button" | "submit" | "reset";
  onClick?: (event: MouseEvent) => void;
}

export function createButton(options: ButtonOptions): HTMLButtonElement {
  const button = document.createElement("button");
  button.type = options.type ?? "button";
  button.className = `mars-button mars-button--${options.variant ?? "secondary"}`;
  button.disabled = options.disabled === true || options.loading === true;

  if (options.loading === true) {
    button.setAttribute("aria-busy", "true");
    button.setAttribute("aria-label", options.label);
  }

  const label = document.createElement("span");
  label.className = "mars-button__label";
  label.textContent = options.loading === true ? "Yükleniyor" : options.label;
  button.append(label);

  if (options.onClick) {
    button.addEventListener("click", options.onClick);
  }

  return button;
}

export interface FieldOptions {
  id: string;
  label: string;
  value?: string;
  type?: HTMLInputElement["type"];
  required?: boolean;
  readOnly?: boolean;
  disabled?: boolean;
  help?: string;
  error?: string;
}

export interface FieldControl {
  element: HTMLElement;
  input: HTMLInputElement;
  error: HTMLElement | null;
}

export function createField(options: FieldOptions): FieldControl {
  const field = document.createElement("div");
  field.className = "mars-field";

  const label = document.createElement("label");
  label.htmlFor = options.id;
  label.className = "mars-field__label";
  label.textContent = options.label;

  if (options.required) {
    const required = document.createElement("span");
    required.className = "mars-field__required";
    required.setAttribute("aria-hidden", "true");
    required.textContent = " *";
    label.append(required);
  }

  const input = document.createElement("input");
  input.id = options.id;
  input.name = options.id;
  input.className = "mars-field__control";
  input.type = options.type ?? "text";
  input.value = options.value ?? "";
  input.required = options.required === true;
  input.readOnly = options.readOnly === true;
  input.disabled = options.disabled === true;

  const describedBy: string[] = [];
  let helpElement: HTMLElement | null = null;
  let errorElement: HTMLElement | null = null;

  if (options.help) {
    helpElement = document.createElement("p");
    helpElement.id = `${options.id}-help`;
    helpElement.className = "mars-field__help";
    helpElement.textContent = options.help;
    describedBy.push(helpElement.id);
  }

  if (options.error) {
    errorElement = document.createElement("p");
    errorElement.id = `${options.id}-error`;
    errorElement.className = "mars-field__error";
    errorElement.textContent = options.error;
    input.setAttribute("aria-invalid", "true");
    describedBy.push(errorElement.id);
  }

  if (describedBy.length > 0) {
    input.setAttribute("aria-describedby", describedBy.join(" "));
  }

  field.append(label, input);
  if (helpElement) field.append(helpElement);
  if (errorElement) field.append(errorElement);

  return { element: field, input, error: errorElement };
}

export interface DialogOptions {
  title: string;
  content: HTMLElement;
  primaryAction?: HTMLButtonElement;
  secondaryAction?: HTMLButtonElement;
  escapeCloses?: boolean;
}

export interface DialogController {
  element: HTMLElement;
  dialog: HTMLElement;
  open: () => void;
  close: () => void;
  isOpen: () => boolean;
}

export function createDialog(options: DialogOptions): DialogController {
  const overlay = document.createElement("div");
  overlay.className = "mars-dialog-overlay";
  overlay.hidden = true;

  const dialog = document.createElement("section");
  dialog.className = "mars-dialog";
  dialog.setAttribute("role", "dialog");
  dialog.setAttribute("aria-modal", "true");
  dialog.tabIndex = -1;

  const titleId = `mars-dialog-${nextId()}`;
  const heading = document.createElement("h2");
  heading.id = titleId;
  heading.className = "mars-dialog__title";
  heading.textContent = options.title;
  dialog.setAttribute("aria-labelledby", titleId);

  const body = document.createElement("div");
  body.className = "mars-dialog__body";
  body.append(options.content);

  const footer = document.createElement("footer");
  footer.className = "mars-dialog__footer";
  if (options.secondaryAction) footer.append(options.secondaryAction);
  if (options.primaryAction) footer.append(options.primaryAction);

  dialog.append(heading, body, footer);
  overlay.append(dialog);

  let returnFocus: HTMLElement | null = null;
  const inerted = new Map<HTMLElement, boolean>();

  const close = (): void => {
    if (overlay.hidden) return;
    overlay.hidden = true;
    for (const [element, wasInert] of inerted) {
      element.inert = wasInert;
    }
    inerted.clear();
    returnFocus?.focus();
    returnFocus = null;
  };

  const open = (): void => {
    if (!overlay.hidden) return;
    returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;

    if (overlay.parentElement !== document.body) {
      document.body.append(overlay);
    }
    overlay.hidden = false;

    for (const child of Array.from(document.body.children)) {
      if (child instanceof HTMLElement && child !== overlay) {
        inerted.set(child, child.inert);
        child.inert = true;
      }
    }

    queueMicrotask(() => {
      (focusableElements(dialog)[0] ?? dialog).focus();
    });
  };

  dialog.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && options.escapeCloses !== false) {
      event.preventDefault();
      close();
      return;
    }

    if (event.key !== "Tab") return;
    const focusables = focusableElements(dialog);
    if (focusables.length === 0) {
      event.preventDefault();
      dialog.focus();
      return;
    }

    const first = focusables[0]!;
    const last = focusables[focusables.length - 1]!;
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  return {
    element: overlay,
    dialog,
    open,
    close,
    isOpen: () => !overlay.hidden
  };
}

export interface TabDefinition {
  id: string;
  label: string;
  panel: HTMLElement;
}

export interface TabsController {
  element: HTMLElement;
  activate: (id: string, focus?: boolean) => void;
  activeId: () => string;
}

export function createTabs(definitions: readonly TabDefinition[]): TabsController {
  if (definitions.length === 0) {
    throw new Error("Mars.Tabs requires at least one tab.");
  }

  const root = document.createElement("div");
  root.className = "mars-tabs";

  const tablist = document.createElement("div");
  tablist.className = "mars-tabs__list";
  tablist.setAttribute("role", "tablist");

  const panels = document.createElement("div");
  panels.className = "mars-tabs__panels";

  const buttons: HTMLButtonElement[] = [];
  let activeIndex = 0;

  definitions.forEach((definition, index) => {
    const button = document.createElement("button");
    button.type = "button";
    button.id = `mars-tab-${definition.id}`;
    button.className = "mars-tabs__tab";
    button.setAttribute("role", "tab");
    button.setAttribute("aria-controls", `mars-panel-${definition.id}`);
    button.textContent = definition.label;

    const panel = definition.panel;
    panel.id = `mars-panel-${definition.id}`;
    panel.classList.add("mars-tabs__panel");
    panel.setAttribute("role", "tabpanel");
    panel.setAttribute("aria-labelledby", button.id);

    button.addEventListener("click", () => activateIndex(index, true));
    button.addEventListener("keydown", (event) => {
      let next = index;
      if (event.key === "ArrowRight") next = (index + 1) % definitions.length;
      else if (event.key === "ArrowLeft") next = (index - 1 + definitions.length) % definitions.length;
      else if (event.key === "Home") next = 0;
      else if (event.key === "End") next = definitions.length - 1;
      else return;

      event.preventDefault();
      activateIndex(next, true);
    });

    buttons.push(button);
    tablist.append(button);
    panels.append(panel);
  });

  const activateIndex = (index: number, focus: boolean): void => {
    activeIndex = index;
    buttons.forEach((button, buttonIndex) => {
      const selected = buttonIndex === activeIndex;
      button.setAttribute("aria-selected", selected ? "true" : "false");
      button.tabIndex = selected ? 0 : -1;
      definitions[buttonIndex]!.panel.hidden = !selected;
    });

    if (focus) buttons[activeIndex]!.focus();
  };

  const activate = (id: string, focus = false): void => {
    const index = definitions.findIndex((definition) => definition.id === id);
    if (index < 0) throw new Error(`Unknown tab '${id}'.`);
    activateIndex(index, focus);
  };

  root.append(tablist, panels);
  activateIndex(0, false);

  return {
    element: root,
    activate,
    activeId: () => definitions[activeIndex]!.id
  };
}

export interface LookupSearchRequest {
  query: string;
  page: number;
  pageSize: number;
  signal: AbortSignal;
}

export interface LookupSearchResult<T> {
  items: readonly T[];
  hasMore: boolean;
}

export interface LookupOptions<T> {
  label: string;
  placeholder?: string;
  pageSize?: number;
  search: (request: LookupSearchRequest) => Promise<LookupSearchResult<T>>;
  getKey: (item: T) => string;
  getLabel: (item: T) => string;
  onSelect?: (item: T) => void;
}

export interface LookupController<T> {
  element: HTMLElement;
  input: HTMLInputElement;
  searchNow: (page?: number) => Promise<void>;
  getSelected: () => T | null;
}

export function createLookup<T>(options: LookupOptions<T>): LookupController<T> {
  const root = document.createElement("div");
  root.className = "mars-lookup";

  const label = document.createElement("label");
  label.className = "mars-lookup__label";
  label.textContent = options.label;

  const input = document.createElement("input");
  input.type = "search";
  input.className = "mars-lookup__input";
  input.placeholder = options.placeholder ?? "Ara";
  input.setAttribute("role", "combobox");
  input.setAttribute("aria-autocomplete", "list");
  input.setAttribute("aria-expanded", "false");

  const listId = `mars-lookup-${nextId()}`;
  const list = document.createElement("div");
  list.id = listId;
  list.className = "mars-lookup__results";
  list.setAttribute("role", "listbox");
  list.hidden = true;
  input.setAttribute("aria-controls", listId);

  const status = document.createElement("p");
  status.className = "mars-lookup__status";
  status.setAttribute("aria-live", "polite");

  const more = createButton({ label: "Daha fazla", variant: "quiet" });
  more.hidden = true;

  let selected: T | null = null;
  let currentItems: readonly T[] = [];
  let activeIndex = -1;
  let currentPage = 1;
  let currentAbort: AbortController | null = null;

  const choose = (index: number): void => {
    const item = currentItems[index];
    if (!item) return;
    selected = item;
    input.value = options.getLabel(item);
    list.hidden = true;
    input.setAttribute("aria-expanded", "false");
    options.onSelect?.(item);
  };

  const renderItems = (result: LookupSearchResult<T>, append: boolean): void => {
    currentItems = append ? [...currentItems, ...result.items] : [...result.items];
    activeIndex = currentItems.length > 0 ? 0 : -1;
    list.replaceChildren();

    for (const [index, item] of currentItems.entries()) {
      const option = document.createElement("button");
      option.type = "button";
      option.className = "mars-lookup__option";
      option.setAttribute("role", "option");
      option.setAttribute("aria-label", options.getLabel(item));
      option.textContent = options.getLabel(item);
      option.addEventListener("focus", () => { activeIndex = index; });
      option.addEventListener("click", () => choose(index));
      list.append(option);
    }

    const hasItems = currentItems.length > 0;
    list.hidden = !hasItems;
    input.setAttribute("aria-expanded", hasItems ? "true" : "false");
    status.textContent = hasItems ? `${currentItems.length} sonuç` : "Sonuç bulunamadı";
    more.hidden = !result.hasMore;
  };

  const searchNow = async (page = 1): Promise<void> => {
    currentAbort?.abort();
    currentAbort = new AbortController();
    currentPage = page;
    status.textContent = "Aranıyor";
    more.hidden = true;

    try {
      const result = await options.search({
        query: input.value.trim(),
        page,
        pageSize: options.pageSize ?? 20,
        signal: currentAbort.signal
      });
      renderItems(result, page > 1);
    } catch (error) {
      if (error instanceof DOMException && error.name === "AbortError") return;
      list.hidden = true;
      input.setAttribute("aria-expanded", "false");
      status.textContent = "Arama tamamlanamadı";
      throw error;
    }
  };

  input.addEventListener("input", () => { void searchNow(1); });
  root.addEventListener("keydown", (event) => {
    if (event.key === "F2") {
      event.preventDefault();
      input.focus();
      return;
    }

    if (currentItems.length === 0) return;

    if (event.key === "ArrowDown") {
      event.preventDefault();
      activeIndex = Math.min(activeIndex + 1, currentItems.length - 1);
      focusLookupOption(list, activeIndex);
    } else if (event.key === "ArrowUp") {
      event.preventDefault();
      activeIndex = Math.max(activeIndex - 1, 0);
      focusLookupOption(list, activeIndex);
    } else if (event.key === "Enter" && activeIndex >= 0) {
      event.preventDefault();
      choose(activeIndex);
    } else if (event.key === "Escape") {
      list.hidden = true;
      input.setAttribute("aria-expanded", "false");
    }
  });

  more.addEventListener("click", () => { void searchNow(currentPage + 1); });

  root.append(label, input, status, list, more);
  return { element: root, input, searchNow, getSelected: () => selected };
}

export interface GridColumn<T> {
  key: string;
  header: string;
  value: (row: T) => string | number | null | undefined;
  align?: "start" | "end";
}

export type GridState =
  | { kind: "ready" }
  | { kind: "loading"; message?: string }
  | { kind: "empty"; message?: string }
  | { kind: "error"; message: string };

export interface GridOptions<T> {
  caption: string;
  columns: readonly GridColumn<T>[];
  rows: readonly T[];
  state?: GridState;
  onRowActivate?: (row: T) => void;
}

export interface GridController<T> {
  element: HTMLElement;
  table: HTMLTableElement;
  setRows: (rows: readonly T[], state?: GridState) => void;
}

export function createGrid<T>(options: GridOptions<T>): GridController<T> {
  const root = document.createElement("div");
  root.className = "mars-grid";
  root.tabIndex = 0;

  const table = document.createElement("table");
  table.className = "mars-grid__table";

  const caption = document.createElement("caption");
  caption.textContent = options.caption;

  const head = document.createElement("thead");
  const headRow = document.createElement("tr");
  for (const column of options.columns) {
    const cell = document.createElement("th");
    cell.scope = "col";
    cell.textContent = column.header;
    if (column.align === "end") cell.classList.add("mars-grid__cell--end");
    headRow.append(cell);
  }
  head.append(headRow);

  const body = document.createElement("tbody");
  table.append(caption, head, body);

  const status = document.createElement("p");
  status.className = "mars-grid__status";
  status.setAttribute("aria-live", "polite");

  let rows = [...options.rows];
  let activeRow = -1;

  const render = (state: GridState = { kind: "ready" }): void => {
    body.replaceChildren();
    status.hidden = false;

    if (state.kind === "loading") {
      status.textContent = state.message ?? "Yükleniyor";
      return;
    }
    if (state.kind === "error") {
      status.textContent = state.message;
      return;
    }
    if (state.kind === "empty" || rows.length === 0) {
      status.textContent = state.kind === "empty" ? state.message ?? "Kayıt yok" : "Kayıt yok";
      return;
    }

    status.hidden = true;
    status.textContent = "";
    activeRow = Math.min(Math.max(activeRow, 0), rows.length - 1);

    rows.forEach((row, rowIndex) => {
      const tr = document.createElement("tr");
      tr.tabIndex = rowIndex === activeRow ? 0 : -1;

      for (const column of options.columns) {
        const td = document.createElement("td");
        const value = column.value(row);
        td.textContent = value == null ? "" : String(value);
        if (column.align === "end") td.classList.add("mars-grid__cell--end");
        tr.append(td);
      }

      tr.addEventListener("dblclick", () => options.onRowActivate?.(row));
      tr.addEventListener("focus", () => {
        activeRow = rowIndex;
        syncRowTabStops(body, activeRow);
      });
      body.append(tr);
    });
  };

  root.addEventListener("keydown", (event) => {
    if (rows.length === 0) return;

    if (event.key === "ArrowDown") {
      event.preventDefault();
      activeRow = Math.min(activeRow + 1, rows.length - 1);
      focusGridRow(body, activeRow);
    } else if (event.key === "ArrowUp") {
      event.preventDefault();
      activeRow = Math.max(activeRow - 1, 0);
      focusGridRow(body, activeRow);
    } else if (event.key === "Home") {
      event.preventDefault();
      activeRow = 0;
      focusGridRow(body, activeRow);
    } else if (event.key === "End") {
      event.preventDefault();
      activeRow = rows.length - 1;
      focusGridRow(body, activeRow);
    } else if (event.key === "Enter" && activeRow >= 0) {
      options.onRowActivate?.(rows[activeRow]!);
    }
  });

  root.append(table, status);
  render(options.state);

  return {
    element: root,
    table,
    setRows: (newRows, state = { kind: "ready" }) => {
      rows = [...newRows];
      activeRow = rows.length > 0 ? 0 : -1;
      render(state);
    }
  };
}

let idCounter = 0;
function nextId(): number {
  idCounter += 1;
  return idCounter;
}

function focusableElements(root: HTMLElement): HTMLElement[] {
  return Array.from(root.querySelectorAll<HTMLElement>(
    "button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href], [tabindex]:not([tabindex='-1'])"))
    .filter((element) => !element.hidden);
}

function focusLookupOption(list: HTMLElement, index: number): void {
  const options = list.querySelectorAll<HTMLButtonElement>("[role='option']");
  const target = options[index];
  if (target) target.focus();
}

function focusGridRow(body: HTMLTableSectionElement, index: number): void {
  syncRowTabStops(body, index);
  const row = body.rows.item(index);
  row?.focus();
}

function syncRowTabStops(body: HTMLTableSectionElement, activeIndex: number): void {
  Array.from(body.rows).forEach((row, index) => {
    row.tabIndex = index === activeIndex ? 0 : -1;
  });
}
