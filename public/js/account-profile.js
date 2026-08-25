(() => {
    const form = document.querySelector('form[data-account-profile-form]');
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const dirtyAnchor = form.querySelector('[data-profile-dirty-anchor]');
    const markDirty = () => {
        if (dirtyAnchor instanceof HTMLInputElement) {
            dirtyAnchor.value = String(Date.now());
            dirtyAnchor.dispatchEvent(new Event('input', { bubbles: true }));
        }
    };

    const bindRemove = (row) => {
        const remove = row.querySelector('[data-repeat-remove]');
        if (!(remove instanceof HTMLButtonElement)) {
            return;
        }
        remove.addEventListener('click', () => {
            row.remove();
            markDirty();
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
            markDirty();
            row.querySelector('input, select, textarea')?.focus();
        });
    });

    form.addEventListener('input', (event) => {
        if (event.target !== dirtyAnchor) {
            markDirty();
        }
    });
    form.addEventListener('change', (event) => {
        if (event.target !== dirtyAnchor) {
            markDirty();
        }
    });
})();
