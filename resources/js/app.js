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
