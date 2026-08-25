import './repeat-fields';

document.documentElement.dataset.marsFoundation = 'ready';

const body = document.body;

if (body?.classList.contains('app-body')) {
    const tabStorageKey = 'mars.workspace.tabs.v1';
    const currentUrl = `${window.location.pathname}${window.location.search}`;
    const currentTitle = body.dataset.workspaceTitle || document.title.split('·')[0].trim() || 'MarsOtomasyon';
    const tabsContainer = document.querySelector('[data-workspace-tabs]');

    const loadTabs = () => {
        try {
            const stored = JSON.parse(window.sessionStorage.getItem(tabStorageKey) || '[]');
            return Array.isArray(stored) ? stored.filter((tab) => tab && typeof tab.url === 'string' && typeof tab.title === 'string') : [];
        } catch {
            return [];
        }
    };

    let tabs = loadTabs();

    const saveTabs = () => {
        window.sessionStorage.setItem(tabStorageKey, JSON.stringify(tabs.slice(-12)));
    };

    const upsertTab = (url, title, dirty = false) => {
        const existing = tabs.find((tab) => tab.url === url);
        if (existing) {
            existing.title = title;
            existing.dirty = Boolean(existing.dirty || dirty);
        } else {
            tabs.push({ url, title, dirty: Boolean(dirty) });
        }
        tabs = tabs.slice(-12);
        saveTabs();
    };

    const setDirty = (url, dirty) => {
        const tab = tabs.find((candidate) => candidate.url === url);
        if (!tab) {
            return;
        }
        tab.dirty = Boolean(dirty);
        saveTabs();
        renderTabs();
    };

    const renderTabs = () => {
        if (!(tabsContainer instanceof HTMLElement)) {
            return;
        }

        tabsContainer.replaceChildren();

        tabs.forEach((tab, index) => {
            const wrapper = document.createElement('div');
            wrapper.className = `workspace-tab${tab.url === currentUrl ? ' is-active' : ''}`;

            const link = document.createElement('a');
            link.href = tab.url;
            link.textContent = tab.title;
            link.dataset.workspaceLink = '';
            wrapper.appendChild(link);

            if (tab.dirty) {
                const dirty = document.createElement('span');
                dirty.className = 'workspace-dirty-dot';
                dirty.title = 'Kaydedilmemiş değişiklik';
                dirty.setAttribute('aria-label', 'Kaydedilmemiş değişiklik');
                wrapper.appendChild(dirty);
            }

            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'workspace-tab-close';
            close.textContent = '×';
            close.setAttribute('aria-label', `${tab.title} sekmesini kapat`);
            close.addEventListener('click', () => {
                if (tab.dirty && !window.confirm('Kaydedilmemiş değişiklikler var. Sekmeyi kaydetmeden kapatmak istiyor musunuz?')) {
                    return;
                }

                const wasCurrent = tab.url === currentUrl;
                tabs.splice(index, 1);
                saveTabs();

                if (wasCurrent) {
                    const fallback = tabs.at(-1)?.url || '/workspace';
                    window.location.assign(fallback);
                    return;
                }

                renderTabs();
            });
            wrapper.appendChild(close);
            tabsContainer.appendChild(wrapper);
        });
    };

    upsertTab(currentUrl, currentTitle);
    renderTabs();

    document.addEventListener('mars:workspace-dirty', () => setDirty(currentUrl, true));

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target.closest('[data-workspace-link]') : null;
        if (!(target instanceof HTMLAnchorElement)) {
            return;
        }
        const url = new URL(target.href, window.location.origin);
        if (url.origin !== window.location.origin) {
            return;
        }
        upsertTab(`${url.pathname}${url.search}`, target.textContent?.trim() || 'MarsOtomasyon');
    });

    document.querySelectorAll('form').forEach((form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const editableControls = [...form.querySelectorAll('input, select, textarea')].filter((control) => {
            if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement)) {
                return false;
            }
            if (control.hasAttribute('data-dirty-ignore') || control.name === '_token' || control.name === '_method') {
                return false;
            }
            return !(control instanceof HTMLInputElement && ['hidden', 'submit', 'button', 'search'].includes(control.type));
        });

        if (editableControls.length === 0) {
            return;
        }

        const markDirty = () => setDirty(currentUrl, true);
        editableControls.forEach((control) => {
            control.addEventListener('input', markDirty);
            control.addEventListener('change', markDirty);
        });
        form.addEventListener('submit', () => setDirty(currentUrl, false));
    });

    const branchSelector = document.querySelector('[data-branch-selector]');
    if (branchSelector instanceof HTMLSelectElement) {
        branchSelector.addEventListener('change', () => {
            if (branchSelector.value === '') {
                return;
            }
            const form = branchSelector.closest('form');
            if (form instanceof HTMLFormElement) {
                form.requestSubmit();
            }
        });
    }

    const sidebar = document.querySelector('[data-app-sidebar]');
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    if (sidebar instanceof HTMLElement && sidebarToggle instanceof HTMLButtonElement) {
        sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('is-open'));
    }

    const palette = document.querySelector('[data-command-palette]');
    const paletteOpen = document.querySelector('[data-command-open]');
    const paletteClose = document.querySelector('[data-command-close]');
    const paletteSearch = document.querySelector('[data-command-search]');
    const options = [...document.querySelectorAll('[data-command-option]')];

    const openPalette = () => {
        if (!(palette instanceof HTMLDialogElement)) {
            return;
        }
        palette.showModal();
        if (paletteSearch instanceof HTMLInputElement) {
            paletteSearch.value = '';
            options.forEach((option) => option.removeAttribute('hidden'));
            paletteSearch.focus();
        }
    };

    if (paletteOpen instanceof HTMLButtonElement) {
        paletteOpen.addEventListener('click', openPalette);
    }
    if (paletteClose instanceof HTMLButtonElement && palette instanceof HTMLDialogElement) {
        paletteClose.addEventListener('click', () => palette.close());
    }
    if (paletteSearch instanceof HTMLInputElement) {
        paletteSearch.addEventListener('input', () => {
            const query = paletteSearch.value.trim().toLocaleLowerCase('tr-TR');
            options.forEach((option) => {
                const text = option.getAttribute('data-command-text') || '';
                option.toggleAttribute('hidden', query !== '' && !text.includes(query));
            });
        });
    }

    document.addEventListener('keydown', (event) => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            openPalette();
        }
    });
}
