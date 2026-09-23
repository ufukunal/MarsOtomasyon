export interface RouteDefinition {
  path: string;
  title: string;
  render: () => HTMLElement;
}

export class MarsRouter {
  private readonly routes = new Map<string, RouteDefinition>();
  private started = false;

  public constructor(
    private readonly outlet: HTMLElement,
    definitions: readonly RouteDefinition[])
  {
    for (const definition of definitions) {
      this.routes.set(normalizePath(definition.path), definition);
    }
  }

  public start(): void {
    if (this.started) return;
    this.started = true;

    window.addEventListener("popstate", this.renderCurrent);
    document.addEventListener("click", this.handleDocumentClick);
    this.renderCurrent();
  }

  public stop(): void {
    if (!this.started) return;
    this.started = false;
    window.removeEventListener("popstate", this.renderCurrent);
    document.removeEventListener("click", this.handleDocumentClick);
  }

  public navigate(path: string, replace = false): void {
    const normalized = normalizePath(path);
    if (replace) {
      history.replaceState(null, "", normalized);
    } else {
      history.pushState(null, "", normalized);
    }
    this.renderCurrent();
  }

  private readonly handleDocumentClick = (event: MouseEvent): void => {
    const target = event.target;
    if (!(target instanceof Element)) return;

    const anchor = target.closest<HTMLAnchorElement>("a[data-mars-route]");
    if (!anchor || anchor.target || event.defaultPrevented || event.button !== 0 ||
        event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }

    const url = new URL(anchor.href, window.location.href);
    if (url.origin !== window.location.origin) return;

    event.preventDefault();
    this.navigate(url.pathname);
  };

  private readonly renderCurrent = (): void => {
    const path = normalizePath(window.location.pathname);
    const definition = this.routes.get(path) ?? this.routes.get("/");
    if (!definition) {
      throw new Error("MarsRouter requires a '/' fallback route.");
    }

    document.title = `${definition.title} — MarsOtomasyon`;
    this.outlet.replaceChildren(definition.render());
  };
}

export interface AppShell {
  element: HTMLElement;
  outlet: HTMLElement;
}

export function createAppShell(): AppShell {
  const shell = document.createElement("div");
  shell.className = "mars-shell";

  const side = document.createElement("aside");
  side.className = "mars-shell__side";
  side.setAttribute("aria-label", "Uygulama");

  const brand = document.createElement("div");
  brand.className = "mars-shell__brand";
  brand.textContent = "MarsOtomasyon";

  const navigation = document.createElement("nav");
  navigation.className = "mars-shell__nav";
  navigation.setAttribute("aria-label", "Uygulama gezinme");
  navigation.append(
    routeLink("/", "Foundation"),
    routeLink("/parties", "Cari / Party Master"),
    routeLink("/parties/new", "Yeni Party"),
    routeLink("/proof", "Vertical Proof"),
    routeLink("/components", "Mars.UI"));

  side.append(brand, navigation);

  const workspace = document.createElement("div");
  workspace.className = "mars-shell__workspace";

  const header = document.createElement("header");
  header.className = "mars-shell__header";

  const heading = document.createElement("strong");
  heading.textContent = "Foundation";
  const context = document.createElement("span");
  context.className = "mars-shell__context";
  context.textContent = "Web + UI baseline";
  header.append(heading, context);

  const outlet = document.createElement("main");
  outlet.className = "mars-shell__main";
  outlet.id = "mars-route-outlet";
  outlet.tabIndex = -1;

  workspace.append(header, outlet);
  shell.append(side, workspace);

  return { element: shell, outlet };
}

function routeLink(path: string, label: string): HTMLAnchorElement {
  const anchor = document.createElement("a");
  anchor.href = path;
  anchor.dataset.marsRoute = "true";
  anchor.textContent = label;
  return anchor;
}

function normalizePath(path: string): string {
  const clean = path.split(/[?#]/, 1)[0] || "/";
  if (clean === "/") return "/";
  return `/${clean.replace(/^\/+|\/+$/g, "")}`;
}
