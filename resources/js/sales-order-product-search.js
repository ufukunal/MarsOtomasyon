const forms = [...document.querySelectorAll('form[data-product-search-url]')];

forms.forEach((form) => {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const endpoint = form.dataset.productSearchUrl;
    const priceField = form.dataset.productPriceField || 'sale_price_net';
    const priceLabel = form.dataset.productPriceLabel || 'Net satış fiyatı';

    if (typeof endpoint !== 'string' || endpoint === '') {
        return;
    }

    const entries = [...form.querySelectorAll('[data-product-search-entry]')];

    entries.forEach((entry) => {
        if (!(entry instanceof HTMLElement)) {
            return;
        }

        const input = entry.querySelector('[data-product-search-input]');
        const productId = entry.querySelector('[data-product-id]');
        const results = entry.querySelector('[data-product-search-results]');
        const help = entry.querySelector('[data-product-search-help]');
        const row = entry.closest('tr');
        const unitPrice = row?.querySelector('[data-product-unit-price]');

        if (!(input instanceof HTMLInputElement)
            || !(productId instanceof HTMLInputElement)
            || !(results instanceof HTMLElement)) {
            return;
        }

        let selectedLabel = productId.value !== '' ? input.value : '';
        let options = [];
        let timer = null;
        let controller = null;

        const productPrice = (product) => String(product?.[priceField] ?? '0.000000');

        const hideResults = () => {
            results.hidden = true;
            input.setAttribute('aria-expanded', 'false');
        };

        const selectProduct = (product) => {
            productId.value = String(product.id);
            selectedLabel = String(product.label);
            input.value = selectedLabel;
            input.setCustomValidity('');

            if (unitPrice instanceof HTMLInputElement) {
                unitPrice.value = productPrice(product);
                unitPrice.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (help instanceof HTMLElement) {
                help.textContent = `KDV %${product.tax_rate} · ${priceLabel} ${productPrice(product)}`;
            }

            hideResults();
            document.dispatchEvent(new CustomEvent('mars:workspace-dirty'));
        };

        const renderResults = () => {
            results.replaceChildren();

            if (options.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'product-search-empty';
                empty.textContent = 'Eşleşen aktif ürün bulunamadı.';
                results.appendChild(empty);
            } else {
                options.forEach((product) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'product-search-option';
                    button.setAttribute('role', 'option');
                    button.dataset.productResultId = String(product.id);

                    const label = document.createElement('strong');
                    label.textContent = String(product.label);
                    button.appendChild(label);

                    const meta = document.createElement('span');
                    meta.textContent = `KDV %${product.tax_rate} · ${productPrice(product)}`;
                    button.appendChild(meta);

                    button.addEventListener('click', () => selectProduct(product));
                    results.appendChild(button);
                });
            }

            results.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        };

        const search = async (selectFirst = false) => {
            const query = input.value.trim();
            if (query === '') {
                options = [];
                hideResults();
                return;
            }

            controller?.abort();
            controller = new AbortController();

            try {
                const url = new URL(endpoint, window.location.origin);
                url.searchParams.set('q', query);
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });

                if (!response.ok) {
                    options = [];
                    renderResults();
                    return;
                }

                const payload = await response.json();
                options = Array.isArray(payload?.data) ? payload.data : [];
                renderResults();

                if (selectFirst && options.length > 0) {
                    selectProduct(options[0]);
                }
            } catch (error) {
                if (error instanceof DOMException && error.name === 'AbortError') {
                    return;
                }
                options = [];
                renderResults();
            }
        };

        input.addEventListener('input', () => {
            if (input.value !== selectedLabel) {
                productId.value = '';
            }
            input.setCustomValidity('');
            if (timer !== null) {
                window.clearTimeout(timer);
            }
            timer = window.setTimeout(() => search(), 160);
        });

        input.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') {
                return;
            }
            event.preventDefault();

            if (options.length > 0 && !results.hidden) {
                selectProduct(options[0]);
                return;
            }

            void search(true);
        });

        input.addEventListener('focus', () => {
            if (options.length > 0) {
                renderResults();
            }
        });
    });

    form.addEventListener('submit', (event) => {
        const missing = entries.find((entry) => {
            const productId = entry.querySelector('[data-product-id]');
            return productId instanceof HTMLInputElement && productId.value === '';
        });

        if (!(missing instanceof HTMLElement)) {
            return;
        }

        const input = missing.querySelector('[data-product-search-input]');
        if (input instanceof HTMLInputElement) {
            event.preventDefault();
            input.setCustomValidity('Listeden geçerli bir ürün seçin.');
            input.reportValidity();
            input.focus();
        }
    });

    document.addEventListener('click', (event) => {
        entries.forEach((entry) => {
            if (!(entry instanceof HTMLElement) || entry.contains(event.target instanceof Node ? event.target : null)) {
                return;
            }
            const results = entry.querySelector('[data-product-search-results]');
            const input = entry.querySelector('[data-product-search-input]');
            if (results instanceof HTMLElement) {
                results.hidden = true;
            }
            if (input instanceof HTMLInputElement) {
                input.setAttribute('aria-expanded', 'false');
            }
        });
    });
});
