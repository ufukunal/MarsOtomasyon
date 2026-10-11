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
    tabs = [...tabs.filter((tab) => tab.path !== path), { path, title }].slice(-6);
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
            if (tab.path !== path) {
                const close = document.createElement('button');
                close.type = 'button';
                close.className = 'workspace-tab-close';
                close.setAttribute('aria-label', tab.title + ' sekmesini kapat');
                close.textContent = '×';
                close.addEventListener('click', () => {
                    tabs = tabs.filter((current) => current.path !== tab.path);
                    store();
                    render();
                });
                item.append(close);
            }
            bar.append(item);
        }
    };
    store();
    render();
});
