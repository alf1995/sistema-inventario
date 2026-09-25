export class ConfirmationModal {
    constructor(root) {
        this.root = root;
        this.dialog = root?.querySelector('[data-confirm-dialog]') ?? null;
        this.title = root?.querySelector('[data-confirm-title]') ?? null;
        this.message = root?.querySelector('[data-confirm-message]') ?? null;
        this.note = root?.querySelector('[data-confirm-note]') ?? null;
        this.submitButton = root?.querySelector('[data-confirm-submit]') ?? null;
        this.cancelButton = root?.querySelector('.confirm-dialog-cancel') ?? null;
        this.confirmedForms = new WeakSet();
        this.state = null;
        this.ready = false;
    }

    mount() {
        if (this.ready
            || !(this.root instanceof HTMLElement)
            || !(this.dialog instanceof HTMLElement)
            || !(this.title instanceof HTMLElement)
            || !(this.message instanceof HTMLElement)
            || !(this.note instanceof HTMLElement)
            || !(this.submitButton instanceof HTMLButtonElement)) {
            return;
        }

        this.ready = true;

        this.root.querySelectorAll('[data-confirm-cancel]').forEach((button) => {
            button.addEventListener('click', () => this.close(true));
        });

        this.submitButton.addEventListener('click', () => this.confirm());
        document.addEventListener('submit', (event) => this.handleSubmit(event));
        document.addEventListener('keydown', (event) => this.handleKeydown(event));
    }

    handleSubmit(event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (this.confirmedForms.has(form)) {
            this.confirmedForms.delete(form);
            return;
        }

        if (! form.dataset.confirm) {
            return;
        }

        event.preventDefault();
        this.open(form, event.submitter);
    }

    open(form, submitter) {
        const variant = form.dataset.confirmVariant
            || (submitter instanceof HTMLElement && submitter.classList.contains('table-action-danger') ? 'danger' : 'warning');

        const defaultTitle = variant === 'danger' ? 'Eliminar registro' : 'Confirmar acción';
        const defaultButton = variant === 'danger' ? 'Eliminar' : 'Continuar';

        this.title.textContent = form.dataset.confirmTitle || defaultTitle;
        this.message.textContent = form.dataset.confirm || '¿Deseas continuar?';

        const noteText = form.dataset.confirmNote || '';
        this.note.textContent = noteText;
        this.note.hidden = noteText === '';

        this.submitButton.textContent = form.dataset.confirmButton || defaultButton;
        this.submitButton.disabled = false;
        this.submitButton.classList.toggle('is-danger', variant === 'danger');
        this.submitButton.classList.toggle('is-warning', variant !== 'danger');

        this.root.classList.toggle('is-danger', variant === 'danger');
        this.root.classList.toggle('is-warning', variant !== 'danger');
        this.root.hidden = false;
        this.root.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');

        this.state = {
            form,
            submitter: submitter instanceof HTMLElement ? submitter : null,
            previousFocus: document.activeElement instanceof HTMLElement ? document.activeElement : null,
        };

        window.requestAnimationFrame(() => {
            this.root.classList.add('is-visible');
            if (this.cancelButton instanceof HTMLButtonElement) {
                this.cancelButton.focus({preventScroll: true});
            } else {
                this.dialog.focus({preventScroll: true});
            }
        });
    }

    close(restoreFocus = true) {
        if (!(this.root instanceof HTMLElement) || this.root.hidden) {
            this.state = null;
            return;
        }

        const previousFocus = this.state?.previousFocus;
        this.root.classList.remove('is-visible');
        this.root.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');

        window.setTimeout(() => {
            if (! this.root.classList.contains('is-visible')) {
                this.root.hidden = true;
                this.root.classList.remove('is-danger', 'is-warning');
            }
        }, 160);

        this.state = null;

        if (restoreFocus && previousFocus instanceof HTMLElement) {
            previousFocus.focus({preventScroll: true});
        }
    }

    confirm() {
        const state = this.state;
        if (! state || !(state.form instanceof HTMLFormElement)) {
            this.close(false);
            return;
        }

        this.submitButton.disabled = true;
        this.submitButton.textContent = 'Procesando…';
        state.form.setAttribute('aria-busy', 'true');
        this.confirmedForms.add(state.form);

        if (typeof state.form.requestSubmit === 'function') {
            if (state.submitter instanceof HTMLButtonElement || state.submitter instanceof HTMLInputElement) {
                state.form.requestSubmit(state.submitter);
            } else {
                state.form.requestSubmit();
            }
            return;
        }

        state.form.submit();
    }

    handleKeydown(event) {
        if (this.root.hidden || ! this.root.classList.contains('is-visible')) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            this.close(true);
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusable = Array.from(this.dialog.querySelectorAll('button:not([disabled]):not([tabindex="-1"])'))
            .filter((element) => element instanceof HTMLButtonElement && ! element.hidden);

        if (focusable.length === 0) {
            event.preventDefault();
            this.dialog.focus();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (! event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }
}
