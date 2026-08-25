const markWorkspaceDirty = () => {
    document.dispatchEvent(new CustomEvent('mars:workspace-dirty'));
};

document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) {
        return;
    }

    const addButton = target.closest('[data-repeat-add]');
    if (addButton instanceof HTMLButtonElement) {
        const key = addButton.dataset.repeatAdd;
        if (!key) {
            return;
        }

        const list = document.querySelector(`[data-repeat-list="${CSS.escape(key)}"]`);
        const template = document.querySelector(`template[data-repeat-template="${CSS.escape(key)}"]`);
        if (!(list instanceof HTMLElement) || !(template instanceof HTMLTemplateElement)) {
            return;
        }

        const index = Number.parseInt(list.dataset.nextIndex || '0', 10);
        const wrapper = document.createElement('template');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(Number.isNaN(index) ? 0 : index)).trim();
        list.appendChild(wrapper.content.cloneNode(true));
        list.dataset.nextIndex = String((Number.isNaN(index) ? 0 : index) + 1);
        markWorkspaceDirty();
        return;
    }

    const removeButton = target.closest('[data-repeat-remove]');
    if (!(removeButton instanceof HTMLButtonElement)) {
        return;
    }

    const row = removeButton.closest('[data-repeat-row]');
    if (row instanceof HTMLElement) {
        row.remove();
        markWorkspaceDirty();
    }
});
