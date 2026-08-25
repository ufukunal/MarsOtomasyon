(() => {
    const firstList = document.querySelector('[data-repeat-list]');
    const form = firstList instanceof HTMLElement ? firstList.closest('form') : null;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const tabStorageKey = 'mars.workspace.tabs.v1';
    const currentUrl = `${window.location.pathname}${window.location.search}`;

    const setWorkspaceDirty = (dirty) => {
        try {
            const tabs = JSON.parse(window.sessionStorage.getItem(tabStorageKey) || '[]');
            if (Array.isArray(tabs)) {
                const current = tabs.find((tab) => tab && tab.url === currentUrl);
                if (current) {
                    current.dirty = Boolean(dirty);
                    window.sessionStorage.setItem(tabStorageKey, JSON.stringify(tabs));
                }
            }
        } catch {
            // Workspace state is optional; form behavior must continue even if storage is unavailable.
        }

        const activeTab = document.querySelector('.workspace-tab.is-active');
        if (!(activeTab instanceof HTMLElement)) {
            return;
        }

        const existingDot = activeTab.querySelector('.workspace-dirty-dot');
        if (!dirty) {
            existingDot?.remove();
            return;
        }

        if (existingDot) {
            return;
        }

        const dot = document.createElement('span');
        dot.className = 'workspace-dirty-dot';
        dot.title = 'Kaydedilmemiş değişiklik';
        dot.setAttribute('aria-label', 'Kaydedilmemiş değişiklik');
        activeTab.insertBefore(dot, activeTab.querySelector('.workspace-tab-close'));
    };

    const bindRemove = (row) => {
        const remove = row.querySelector('[data-repeat-remove]');
        if (!(remove instanceof HTMLButtonElement)) {
            return;
        }
        remove.addEventListener('click', () => {
            row.remove();
            setWorkspaceDirty(true);
        });
    };

    form.querySelectorAll('[data-repeat-row]').forEach((row) => {
        if (row instanceof HTMLElement) {
            bindRemove(row);
        }
    });

    form.querySelectorAll('[data-repeat-add]').forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        button.addEventListener('click', () => {
            const key = button.dataset.repeatAdd;
            if (!key) {
                return;
            }

            const list = form.querySelector(`[data-repeat-list="${key}"]`);
            const template = form.querySelector(`template[data-repeat-template="${key}"]`);
            if (!(list instanceof HTMLElement) || !(template instanceof HTMLTemplateElement)) {
                return;
            }

            const index = Number.parseInt(list.dataset.nextIndex || '0', 10);
            const wrapper = document.createElement('template');
            wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index)).trim();
            const row = wrapper.content.firstElementChild;
            if (!(row instanceof HTMLElement)) {
                return;
            }

            list.appendChild(row);
            list.dataset.nextIndex = String(index + 1);
            bindRemove(row);
            setWorkspaceDirty(true);
            row.querySelector('input, select, textarea')?.focus();
        });
    });

    form.addEventListener('input', () => setWorkspaceDirty(true));
    form.addEventListener('change', () => setWorkspaceDirty(true));
    form.addEventListener('submit', () => setWorkspaceDirty(false));
})();
