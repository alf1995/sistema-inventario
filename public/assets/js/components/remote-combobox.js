export class RemoteCombobox {
    constructor(root, options = {}) {
        this.root = root;
        this.options = options;
        this.input = root.querySelector(options.inputSelector || '[data-combobox-input]');
        this.valueInput = root.querySelector(options.valueSelector || '[data-combobox-value]');
        this.resultsBox = root.querySelector(options.resultsSelector || '[data-combobox-results]');
        this.selection = options.selectionSelector
            ? root.closest('.field')?.querySelector(options.selectionSelector) ?? null
            : null;

        this.searchUrl = root.dataset.searchUrl || '';
        this.minChars = this.readPositiveInteger(root.dataset.minChars, 2);
        this.debounceMs = this.readPositiveInteger(root.dataset.debounceMs, 300);
        this.cacheTtlMs = this.readPositiveInteger(root.dataset.cacheTtlMs, 15000);
        this.cacheLimit = 30;
        this.debounceTimer = 0;
        this.requestController = null;
        this.highlightedIndex = -1;
        this.cache = new Map();
        this.ready = false;
    }

    mount() {
        if (this.ready
            || !(this.input instanceof HTMLInputElement)
            || !(this.valueInput instanceof HTMLInputElement)
            || !(this.resultsBox instanceof HTMLElement)
            || this.searchUrl === '') {
            return;
        }

        this.ready = true;
        this.input.addEventListener('input', () => this.handleInput());
        this.input.addEventListener('focus', () => this.handleFocus());
        this.input.addEventListener('keydown', (event) => this.handleKeydown(event));
        document.addEventListener('click', (event) => this.handleDocumentClick(event));

        const form = this.root.closest('form');
        if (form instanceof HTMLFormElement) {
            form.addEventListener('submit', (event) => this.validateSelection(event));
        }
    }

    readPositiveInteger(value, fallback) {
        const parsed = Number.parseInt(String(value ?? ''), 10);
        return Number.isFinite(parsed) && parsed >= 0 ? parsed : fallback;
    }

    normalizeQuery(value) {
        return String(value ?? '').replace(/\s+/g, ' ').trim();
    }

    handleInput() {
        this.valueInput.value = '';
        this.input.setCustomValidity('');
        this.clearSelection();
        window.clearTimeout(this.debounceTimer);

        const query = this.normalizeQuery(this.input.value);
        if (query.length < this.minChars) {
            this.abortRequest();
            this.closeResults();
            return;
        }

        this.debounceTimer = window.setTimeout(() => this.performSearch(query), this.debounceMs);
    }

    handleFocus() {
        if (this.valueInput.value !== '') {
            return;
        }

        const query = this.normalizeQuery(this.input.value);
        if (query.length < this.minChars) {
            return;
        }

        window.clearTimeout(this.debounceTimer);
        this.debounceTimer = window.setTimeout(() => this.performSearch(query), 80);
    }

    handleKeydown(event) {
        const buttons = this.resultButtons();

        if (event.key === 'ArrowDown') {
            if (this.resultsBox.hidden) {
                const query = this.normalizeQuery(this.input.value);
                if (query.length >= this.minChars) {
                    this.performSearch(query);
                }
                return;
            }
            event.preventDefault();
            this.highlight(this.highlightedIndex + 1);
            return;
        }

        if (event.key === 'ArrowUp') {
            if (! this.resultsBox.hidden) {
                event.preventDefault();
                this.highlight(this.highlightedIndex <= 0 ? 0 : this.highlightedIndex - 1);
            }
            return;
        }

        if (event.key === 'Enter' && this.highlightedIndex >= 0 && buttons[this.highlightedIndex] instanceof HTMLButtonElement) {
            event.preventDefault();
            buttons[this.highlightedIndex].click();
            return;
        }

        if (event.key === 'Escape') {
            this.closeResults();
        }
    }

    handleDocumentClick(event) {
        if (event.target instanceof Node && ! this.root.contains(event.target)) {
            this.closeResults();
        }
    }

    validateSelection(event) {
        if (this.valueInput.value !== '') {
            return;
        }

        this.input.setCustomValidity(
            this.options.selectionRequiredMessage || 'Selecciona un elemento de la lista de resultados.'
        );
        this.input.reportValidity();
        event.preventDefault();
    }

    async performSearch(query) {
        const normalizedQuery = this.normalizeQuery(query);
        if (normalizedQuery.length < this.minChars) {
            return;
        }

        const cached = this.readCache(normalizedQuery);
        if (cached !== null) {
            this.renderPayload(cached);
            return;
        }

        this.abortRequest();
        this.requestController = new AbortController();
        this.renderLoading();

        const url = new URL(this.searchUrl, window.location.origin);
        url.searchParams.set('q', normalizedQuery);

        try {
            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {'Accept': 'application/json'},
                credentials: 'same-origin',
                signal: this.requestController.signal,
            });

            if (! response.ok) {
                throw new Error('No se pudo completar la búsqueda.');
            }

            const payload = await response.json();
            this.writeCache(normalizedQuery, payload);
            this.renderPayload(payload);
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }

            this.renderError();
        } finally {
            this.requestController = null;
        }
    }

    abortRequest() {
        if (this.requestController instanceof AbortController) {
            this.requestController.abort();
        }
        this.requestController = null;
    }

    readCache(query) {
        const entry = this.cache.get(query.toLocaleLowerCase());
        if (! entry) {
            return null;
        }

        if (Date.now() - entry.createdAt > this.cacheTtlMs) {
            this.cache.delete(query.toLocaleLowerCase());
            return null;
        }

        return entry.payload;
    }

    writeCache(query, payload) {
        const key = query.toLocaleLowerCase();
        this.cache.delete(key);
        this.cache.set(key, {createdAt: Date.now(), payload});

        while (this.cache.size > this.cacheLimit) {
            const oldest = this.cache.keys().next().value;
            this.cache.delete(oldest);
        }
    }

    renderLoading() {
        this.resultsBox.innerHTML = '';
        this.resultsBox.setAttribute('aria-busy', 'true');
        this.resultsBox.appendChild(this.createMessage('Buscando…', 'remote-combobox-loading'));
        this.openResults();
    }

    renderPayload(payload) {
        this.resultsBox.innerHTML = '';
        this.resultsBox.setAttribute('aria-busy', 'false');

        const items = Array.isArray(payload?.results) ? payload.results : [];
        const meta = payload?.meta && typeof payload.meta === 'object' ? payload.meta : {};

        if (items.length === 0) {
            this.resultsBox.appendChild(this.createMessage(
                this.options.emptyMessage || 'No se encontraron coincidencias.',
                'remote-combobox-empty'
            ));
        } else {
            items.forEach((item, index) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = this.options.optionClass || 'remote-combobox-option';
                button.dataset.comboboxOption = '1';
                button.setAttribute('role', 'option');
                button.setAttribute('aria-selected', 'false');
                button.id = `${this.resultsBox.id || 'combobox-results'}-option-${index}`;

                if (typeof this.options.renderOption === 'function') {
                    this.options.renderOption(button, item);
                } else {
                    button.textContent = this.itemLabel(item);
                }

                button.addEventListener('click', () => this.choose(item));
                this.resultsBox.appendChild(button);
            });

            if (meta.has_more === true) {
                const more = this.createMessage(
                    this.options.moreMessage || 'Hay más coincidencias. Escribe más caracteres para afinar la búsqueda.',
                    'remote-combobox-more'
                );
                this.resultsBox.appendChild(more);
            }
        }

        this.highlightedIndex = -1;
        this.openResults();
    }

    renderError() {
        this.resultsBox.innerHTML = '';
        this.resultsBox.setAttribute('aria-busy', 'false');
        this.resultsBox.appendChild(this.createMessage(
            this.options.errorMessage || 'No fue posible completar la búsqueda.',
            'remote-combobox-empty'
        ));
        this.openResults();
    }

    createMessage(text, className) {
        const message = document.createElement('div');
        message.className = className;
        message.textContent = text;
        return message;
    }

    choose(item) {
        this.valueInput.value = String(item.id ?? '');
        this.input.value = this.itemLabel(item);
        this.input.setCustomValidity('');

        if (this.selection instanceof HTMLElement) {
            this.selection.innerHTML = '';
            if (typeof this.options.renderSelection === 'function') {
                this.options.renderSelection(this.selection, item);
            } else {
                this.selection.textContent = this.itemLabel(item);
            }
        }

        this.closeResults();

        this.root.dispatchEvent(new CustomEvent('remote-combobox:change', {
            bubbles: true,
            detail: {item},
        }));
    }

    itemLabel(item) {
        if (typeof this.options.getLabel === 'function') {
            return String(this.options.getLabel(item) ?? '');
        }

        return String(item.label ?? item.name ?? '');
    }

    clearSelection() {
        if (this.selection instanceof HTMLElement) {
            this.selection.textContent = '';
        }
    }

    resultButtons() {
        return Array.from(this.resultsBox.querySelectorAll('[data-combobox-option]'));
    }

    highlight(index) {
        const buttons = this.resultButtons();
        if (buttons.length === 0) {
            this.highlightedIndex = -1;
            this.input.removeAttribute('aria-activedescendant');
            return;
        }

        this.highlightedIndex = Math.max(0, Math.min(index, buttons.length - 1));
        buttons.forEach((button, buttonIndex) => {
            const active = buttonIndex === this.highlightedIndex;
            button.classList.toggle('is-highlighted', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        const highlighted = buttons[this.highlightedIndex];
        this.input.setAttribute('aria-activedescendant', highlighted.id);
        highlighted.scrollIntoView({block: 'nearest'});
    }

    openResults() {
        this.resultsBox.hidden = false;
        this.input.setAttribute('aria-expanded', 'true');
    }

    closeResults() {
        this.resultsBox.hidden = true;
        this.input.setAttribute('aria-expanded', 'false');
        this.input.removeAttribute('aria-activedescendant');
        this.highlightedIndex = -1;
    }

}
