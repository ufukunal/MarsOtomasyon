
// Keep current route authoritative when the browser restores old <details> state.
const syncV65Navigation = () => {
    document.querySelectorAll('[data-v65-nav-group]').forEach((group) => {
        if (!(group instanceof HTMLDetailsElement)) return;
        group.open = group.dataset.currentGroup === 'true';
    });
};
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', syncV65Navigation, { once: true });
} else {
    syncV65Navigation();
}
document.addEventListener('livewire:navigated', syncV65Navigation);

document.documentElement.classList.add('js');

document.addEventListener('blur', (event) => {
    const input = event.target;

    if (!(input instanceof HTMLInputElement) || !input.matches('[data-tr-decimal]')) {
        return;
    }

    const raw = input.value.trim();

    if (raw === '') {
        return;
    }

    const normalized = raw.includes(',')
        ? raw.replace(/\./g, '').replace(',', '.')
        : raw;

    if (normalized !== raw) {
        input.value = normalized;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }
}, true);

document.addEventListener('livewire:init', () => {
    Livewire.on('lookup-selected', () => {
        requestAnimationFrame(() => {
            const active = document.activeElement;

            if (!(active instanceof HTMLElement)) {
                return;
            }

            const scope = active.closest('form, .panel, .modal-body') ?? document;
            const focusables = Array.from(scope.querySelectorAll(
                'input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), a[href]'
            ));

            const index = focusables.indexOf(active);
            const next = focusables[index + 1];

            if (next instanceof HTMLElement) {
                next.focus();
            }
        });
    });
});


let draggedImageCard = null;

document.addEventListener('dragstart', (event) => {
    const card = event.target instanceof Element
        ? event.target.closest('[data-product-image-sorter] [data-attachment-id]')
        : null;

    if (!(card instanceof HTMLElement) || card.getAttribute('draggable') !== 'true') {
        return;
    }

    draggedImageCard = card;
    card.classList.add('is-dragging');

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
});

document.addEventListener('dragover', (event) => {
    if (!draggedImageCard) {
        return;
    }

    const card = event.target instanceof Element
        ? event.target.closest('[data-product-image-sorter] [data-attachment-id]')
        : null;

    if (!(card instanceof HTMLElement) || card === draggedImageCard) {
        return;
    }

    event.preventDefault();

    const rect = card.getBoundingClientRect();
    const before = event.clientY < rect.top + (rect.height / 2);
    const parent = card.parentElement;

    if (parent) {
        parent.insertBefore(draggedImageCard, before ? card : card.nextSibling);
    }
});

document.addEventListener('drop', (event) => {
    if (!draggedImageCard) {
        return;
    }

    event.preventDefault();

    const sorter = draggedImageCard.closest('[data-product-image-sorter]');
    const component = draggedImageCard.closest('[wire\\:id]');

    if (sorter instanceof HTMLElement && component instanceof HTMLElement) {
        const ids = Array.from(sorter.querySelectorAll('[data-attachment-id]'))
            .map((node) => Number(node.getAttribute('data-attachment-id')))
            .filter(Number.isInteger);

        const componentId = component.getAttribute('wire:id');

        if (componentId) {
            Livewire.find(componentId)?.call('reorder', ids);
        }
    }

    draggedImageCard.classList.remove('is-dragging');
    draggedImageCard = null;
});

document.addEventListener('dragend', () => {
    if (draggedImageCard) {
        draggedImageCard.classList.remove('is-dragging');
        draggedImageCard = null;
    }
});


// v65 sidebar menu search: operates only on authorized, server-rendered links.
document.addEventListener('input', (event) => {
    if (!(event.target instanceof HTMLInputElement) || event.target.id !== 'menu-search') {
        return;
    }

    const query = event.target.value.trim().toLocaleLowerCase('tr');
    const nav = event.target.closest('.sidebar')?.querySelector('.navigation');

    if (!nav) {
        return;
    }

    for (const group of nav.querySelectorAll('.nav-group')) {
        const label = group.querySelector('.nav-group-title')?.textContent?.toLocaleLowerCase('tr') ?? '';
        const groupMatches = label.includes(query);
        let visible = false;

        for (const link of group.querySelectorAll('.nav-link')) {
            const matches = !query || groupMatches || link.textContent.toLocaleLowerCase('tr').includes(query);
            link.hidden = !matches;
            visible = visible || matches;
        }

        for (const subgroup of group.querySelectorAll('.nav-subgroup')) {
            const subgroupLinks = Array.from(subgroup.querySelectorAll('.nav-link'));
            const hasVisible = subgroupLinks.some((link) => !link.hidden);
            subgroup.hidden = !hasVisible;
            if (query && hasVisible) subgroup.open = true;
            if (!query) subgroup.open = subgroupLinks.some((link) => link.hasAttribute('aria-current'))
                || subgroup === group.querySelector('.nav-subgroup');
        }
        group.hidden = !visible;
        if (query) {
            group.open = visible;
        } else {
            group.open = group.querySelector('[aria-current="page"]') !== null;
        }
    }
});


// Search only links already authorized and rendered by Laravel.
document.addEventListener('keydown', (event) => {
    if (!(event.ctrlKey || event.metaKey) || event.key.toLowerCase() !== 'k') {
        return;
    }
    const search = document.getElementById('global-screen-search')
        ?? document.getElementById('menu-search');
    if (search instanceof HTMLInputElement) {
        event.preventDefault();
        search.focus();
        search.select();
    }
});
document.addEventListener('input', (event) => {
    if (!(event.target instanceof HTMLInputElement) || event.target.id !== 'global-screen-search') {
        return;
    }
    const sidebarSearch = document.getElementById('menu-search');
    if (sidebarSearch instanceof HTMLInputElement) {
        sidebarSearch.value = event.target.value;
        sidebarSearch.dispatchEvent(new Event('input', { bubbles: true }));
    }
});
document.addEventListener('keydown', (event) => {
    const input = event.target;
    if (event.key !== 'Enter' || !(input instanceof HTMLInputElement)
        || !['menu-search', 'global-screen-search'].includes(input.id)) {
        return;
    }
    const matches = Array.from(document.querySelectorAll('.navigation .nav-link'))
        .filter((link) => !link.hidden && !link.closest('.nav-group[hidden]'));
    if (matches.length === 1 && matches[0] instanceof HTMLAnchorElement) {
        event.preventDefault();
        matches[0].click();
    }
});


// Keep page action dropdowns mutually exclusive, including teleported Cari Detay menus.
// Event delegation survives Livewire morphing and browser back/forward navigation.
const v65ActionMenuSelector = 'details.page-action-menu, details.topbar-create, details.user-menu, details.v65-products-export-menu, details.v65-contact-action-menu, details[data-v65-action-menu]';
const closeV65ActionMenus = (except = null) => {
    for (const opened of document.querySelectorAll(v65ActionMenuSelector)) {
        if (opened.open && opened !== except) opened.open = false;
    }
};
document.addEventListener('pointerdown', (event) => {
    if (!(event.target instanceof Element)) return;
    const current = event.target.closest(v65ActionMenuSelector);
    // A click outside any managed menu closes them; don't interfere with sidebar details.
    closeV65ActionMenus(current);
}, true);
// Native summary keyboard activation can change <details> without pointer events.
document.addEventListener('toggle', (event) => {
    const current = event.target;
    if (current instanceof HTMLDetailsElement && current.matches(v65ActionMenuSelector) && current.open) {
        closeV65ActionMenus(current);
    }
}, true);
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const opened = document.querySelector(v65ActionMenuSelector.split(', ').map((s) => s + '[open]').join(', '));
    if (!(opened instanceof HTMLDetailsElement)) return;
    closeV65ActionMenus();
    opened.querySelector('summary')?.focus();
    event.preventDefault();
});
document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) return;
    // Close only actual action menus after activation. Simple filter drawers also
    // use the managed <details> lifecycle, but their controls must remain usable.
    const action = event.target.closest('details.page-action-menu a[href], details.page-action-menu button, details.v65-contact-action-menu a[href], details.v65-contact-action-menu button, details.v65-products-export-menu a[href], details.v65-products-export-menu button');
    if (action) action.closest('details').open = false;
});
document.addEventListener('livewire:navigating', () => closeV65ActionMenus());

// Dirty state belongs to the currently loaded page, never to another tab's saved label.
let marsWorktabDirty = false;
const marsDirtyControls = 'main form:not([method="GET"]) input:not([type="hidden"]):not([type="search"]), main form:not([method="GET"]) select, main form:not([method="GET"]) textarea, main [contenteditable="true"]';
document.addEventListener('input', (event) => {
    if (event.target instanceof Element && event.target.matches(marsDirtyControls)) marsWorktabDirty = true;
}, true);
document.addEventListener('change', (event) => {
    if (event.target instanceof Element && event.target.matches(marsDirtyControls)) marsWorktabDirty = true;
}, true);
document.addEventListener('submit', () => { marsWorktabDirty = false; }, true);
window.addEventListener('beforeunload', (event) => {
    if (!marsWorktabDirty) return;
    event.preventDefault();
    event.returnValue = '';
});

// Product-list print template / export dialogs: keep dropdown actions singular.
document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;
    const show = (selector) => {
        const dialog = document.querySelector(selector);
        if (dialog instanceof HTMLDialogElement && !dialog.open) dialog.showModal();
    };
    if (target.closest('[data-v65-products-print-open]')) show('[data-v65-products-print-dialog]');
    if (target.closest('[data-v65-products-export-open]')) show('[data-v65-products-export-dialog]');
    if (target.closest('[data-v65-products-dialog-close]')) target.closest('dialog')?.close();
    if (target.matches('dialog.v65-products-dialog')) target.close();
    if (target.closest('[data-v65-products-print-run]')) {
        const dialog = target.closest('dialog');
        const portrait = dialog?.querySelector('[data-v65-products-print-template]')?.value === 'portrait';
        document.querySelector('[data-v65-products-page-style]')?.remove();
        const pageStyle = document.createElement('style');
        pageStyle.setAttribute('data-v65-products-page-style', '');
        pageStyle.textContent = portrait
            ? '@media print{@page{size:A4 portrait;margin:12mm}}'
            : '@media print{@page{size:A4 landscape;margin:10mm}}';
        document.head.append(pageStyle);
        dialog?.close();
        window.print();
    }
});
window.addEventListener('afterprint', () => {
    document.querySelector('[data-v65-products-page-style]')?.remove();
});

// Product detail cover/gallery preview. Only authenticated image URLs rendered by Blade are used.
document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;
    const dialog = document.querySelector('[data-v65-product-image-lightbox]');
    if (!(dialog instanceof HTMLDialogElement)) return;
    const button = target.closest('[data-v65-product-preview-open]');
    if (button) {
        const source = button.querySelector('img');
        const preview = dialog.querySelector('img');
        if (!(source instanceof HTMLImageElement) || !(preview instanceof HTMLImageElement)) return;
        preview.src = source.currentSrc || source.src;
        preview.alt = source.alt || 'Ürün görseli';
        if (!dialog.open) dialog.showModal();
        return;
    }
    if (target.closest('[data-v65-product-preview-close]') || target === dialog) dialog.close();
});

// v65 worktabs: open business screens persist for this user and active company
// in this browser tab only. Store paths/titles, never form state or credentials.
document.addEventListener('DOMContentLoaded', () => {
    const bar = document.querySelector('[data-worktabs]');
    if (!(bar instanceof HTMLElement)) {
        return;
    }

    const scope = bar.dataset.tabScope ?? '';
    const path = '/' + (bar.dataset.activePath ?? '').replace(/^\/+/, '');
    const title = (bar.dataset.activeTitle ?? 'Ekran').slice(0, 90);
    const key = 'mars.v65.worktabs.' + scope;
    let tabs = [];
    try {
        const loaded = JSON.parse(sessionStorage.getItem(key) ?? '[]');
        if (Array.isArray(loaded)) {
            tabs = loaded.filter((tab) =>
                tab && typeof tab.path === 'string' && tab.path.startsWith('/')
                && !tab.path.startsWith('//') && !tab.path.includes('\\')
                && typeof tab.title === 'string'
            ).slice(-5);
        }
    } catch {
        tabs = [];
    }
    // Preserve first-opened order. Revisited tabs become active without moving.
    // New tabs append to the end; reopen after closing creates a new last tab.
    const existing = tabs.find((tab) => tab.path === path);
    if (existing) {
        existing.title = title;
    } else {
        tabs.push({ path, title });
        tabs = tabs.slice(-6);
    }
    const store = () => {
        try {
            sessionStorage.setItem(key, JSON.stringify(tabs));
        } catch {
            // Private browsing may block storage. Navigation still works.
        }
    };
    const render = () => {
        bar.replaceChildren();
        for (const tab of tabs) {
            const item = document.createElement('span');
            item.className = 'workspace-tab' + (tab.path === path ? ' is-active' : '');
            const link = document.createElement('a');
            link.href = tab.path;
            link.textContent = tab.title;
            if (tab.path === path) link.setAttribute('aria-current', 'page');
            item.append(link);
            {
                const close = document.createElement('button');
                close.type = 'button';
                close.className = 'workspace-tab-close';
                close.setAttribute('aria-label', tab.title + ' sekmesini kapat');
                close.textContent = '×';
                const closeTab = () => {
                    if (tab.path === path && marsWorktabDirty
                        && !window.confirm('Kaydedilmemiş işlemler var. Sekmeyi kapatmak istiyor musunuz?')) return;
                    tabs = tabs.filter((current) => current.path !== tab.path);
                    store();
                    if (tab.path === path) {
                        marsWorktabDirty = false;
                        window.location.assign(tabs.at(-1)?.path ?? '/');
                    } else {
                        render();
                    }
                };
                close.addEventListener('click', closeTab);
                item.addEventListener('auxclick', (event) => {
                    if (event.button !== 1 || event.target.closest('button')) return;
                    event.preventDefault();
                    closeTab();
                });
                item.addEventListener('mousedown', (event) => {
                    if (event.button === 1) event.preventDefault();
                });
                item.append(close);
            }
            bar.append(item);
        }
    };
    store();
    render();
});

// Shared column-picker modal (event delegation survives Livewire re-renders).
// wire:ignore.self on the native dialog keeps it open when checkbox state morphs.
document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;

    const trigger = target.closest('[data-v65-column-picker-open]');
    if (trigger) {
        const picker = trigger.closest('[data-v65-column-picker]');
        const dialog = picker?.querySelector('[data-v65-column-picker-dialog]');
        if (dialog instanceof HTMLDialogElement && !dialog.open) {
            dialog.showModal();
        }
        return;
    }

    const close = target.closest('[data-v65-column-picker-close]');
    if (close) {
        const dialog = close.closest('[data-v65-column-picker-dialog]');
        if (dialog instanceof HTMLDialogElement && dialog.open) dialog.close();
        return;
    }

    // Click on the shaded backdrop to dismiss; Escape is handled by <dialog>.
    if (target.matches('[data-v65-column-picker-dialog]')) {
        const dialog = target;
        const bounds = dialog.getBoundingClientRect();
        if (dialog instanceof HTMLDialogElement && dialog.open
            && (event.clientX < bounds.left || event.clientX > bounds.right
                || event.clientY < bounds.top || event.clientY > bounds.bottom)) {
            dialog.close();
        }
    }
});


// V65 list drawer: native dialog manages modality and keyboard focus.
document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) return;
    const open = event.target.closest('[data-v65-filter-open]');
    if (open) {
        const dialog = open.closest('.v65-filter-control')?.querySelector('[data-v65-filter-dialog]');
        if (dialog instanceof HTMLDialogElement && !dialog.open) {
            dialog.showModal();
            document.documentElement.classList.add('v65-filter-is-open');
        }
        return;
    }

    const button = event.target.closest('[data-v65-filter-close],[data-v65-filter-apply]');
    if (button) {
        const dialog = button.closest('[data-v65-filter-dialog]');
        if (dialog instanceof HTMLDialogElement && dialog.open) dialog.close();
    }
});

document.addEventListener('click', (event) => {
    const dialog = event.target;
    if (!(dialog instanceof HTMLDialogElement) || !dialog.matches('[data-v65-filter-dialog]')) return;
    // Native <dialog> receives the click when the backdrop is clicked.
    const rect = dialog.getBoundingClientRect();
    if (event.clientX >= rect.left && event.clientX <= rect.right
        && event.clientY >= rect.top && event.clientY <= rect.bottom) return;
    dialog.close();
    const id = dialog.closest('[wire\\:id]')?.getAttribute('wire:id');
    if (id && window.Livewire?.find(id)) window.Livewire.find(id).call('closeV65Filters');
});

document.addEventListener('cancel', (event) => {
    if (!(event.target instanceof HTMLDialogElement) || !event.target.matches('[data-v65-filter-dialog]')) return;
    const id = event.target.closest('[wire\\:id]')?.getAttribute('wire:id');
    if (id && window.Livewire?.find(id)) window.Livewire.find(id).call('closeV65Filters');
}, true);

document.addEventListener('close', (event) => {
    if (!(event.target instanceof HTMLDialogElement) || !event.target.matches('[data-v65-filter-dialog]')) return;
    if (!document.querySelector('[data-v65-filter-dialog][open]')) document.documentElement.classList.remove('v65-filter-is-open');
    const trigger = event.target.closest('.v65-filter-control')?.querySelector('[data-v65-filter-open]');
    if (trigger instanceof HTMLElement) trigger.focus({ preventScroll: true });
}, true);

document.addEventListener('livewire:navigating', () => {
    document.documentElement.classList.remove('v65-filter-is-open');
});


// V65 native dialog lifecycle. Controls remain Livewire-owned; JS only manages presentation.
const v65ModalFocus = new WeakMap();
document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;
    const trigger = target.closest('[data-v65-modal-open]');
    if (trigger instanceof HTMLElement) {
        const id = trigger.getAttribute('data-v65-modal-open');
        const dialog = id ? document.getElementById(id) : null;
        if (dialog instanceof HTMLDialogElement && !dialog.open) {
            v65ModalFocus.set(dialog, trigger);
            dialog.showModal();
        }
        return;
    }
    const close = target.closest('[data-v65-modal-close]');
    if (close) {
        const dialog = close.closest('dialog[data-v65-modal]');
        if (dialog instanceof HTMLDialogElement && dialog.open) dialog.close();
        return;
    }
    if (target instanceof HTMLDialogElement && target.matches('[data-v65-modal]') && target.open) {
        // Click on the backdrop, not inside the modal content.
        const rect = target.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right
            || event.clientY < rect.top || event.clientY > rect.bottom) target.close();
    }
});
document.addEventListener('close', (event) => {
    const dialog = event.target;
    if (!(dialog instanceof HTMLDialogElement) || !dialog.matches('[data-v65-modal]')) return;
    const focus = v65ModalFocus.get(dialog);
    if (focus instanceof HTMLElement && focus.isConnected) focus.focus();
    v65ModalFocus.delete(dialog);
}, true);
document.addEventListener('livewire:navigating', () => {
    document.querySelectorAll('dialog[data-v65-modal][open]').forEach((dialog) => dialog.close());
});

// Keyboard navigation between Livewire-backed V65 tabs.
document.addEventListener('keydown', (event) => {
    if (!(event.target instanceof HTMLElement) || !event.target.matches('[data-v65-tabs] [role="tab"]')) return;
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
    const tabs = Array.from(event.target.closest('[data-v65-tabs]')?.querySelectorAll('[role="tab"]:not([disabled])') ?? []);
    const index = tabs.indexOf(event.target);
    if (index < 0 || tabs.length === 0) return;
    const nextIndex = event.key === 'Home' ? 0
        : event.key === 'End' ? tabs.length - 1
        : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
    const next = tabs[nextIndex];
    if (!(next instanceof HTMLElement)) return;
    event.preventDefault();
    next.tabIndex = 0;
    next.focus();
    next.click();
});
