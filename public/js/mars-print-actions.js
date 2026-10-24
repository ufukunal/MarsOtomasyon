// Standalone printed documents do not mount the authenticated V65 application.
// Keep print actions same-origin, keyboard accessible and free of inline handlers.
document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) return;
    const action = event.target.closest('[data-mars-print]');
    if (!(action instanceof HTMLButtonElement)) return;
    window.print();
});

window.addEventListener('load', () => {
    if (document.body?.dataset.marsAutoprint === 'true') window.print();
}, { once: true });
