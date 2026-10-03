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
