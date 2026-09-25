import {normalizeSearchText} from '../core/text.js';

export class ResponsiveSidebar {
    constructor(sidebar) {
        this.sidebar = sidebar;
        this.openButton = document.querySelector('[data-sidebar-open]');
        this.closeButton = document.querySelector('[data-sidebar-close]');
        this.overlay = document.querySelector('[data-sidebar-overlay]');
        this.searchInput = document.querySelector('[data-sidebar-search]');
        this.emptyState = document.querySelector('[data-sidebar-search-empty]');
        this.groups = Array.from(document.querySelectorAll('[data-sidebar-group]'));
        this.mobileQuery = window.matchMedia('(max-width: 1100px)');
    }

    mount() {
        if (!(this.sidebar instanceof HTMLElement)) {
            return;
        }

        this.openButton?.addEventListener('click', () => this.open());
        this.closeButton?.addEventListener('click', () => this.close());
        this.overlay?.addEventListener('click', () => this.close());

        this.sidebar.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                if (this.mobileQuery.matches) {
                    this.close();
                }
            });
        });

        document.addEventListener('keydown', (event) => this.handleKeydown(event));
        this.initializeGroups();

        if (this.searchInput instanceof HTMLInputElement) {
            this.searchInput.addEventListener('input', () => this.filter());
        }

        const viewportHandler = () => {
            if (! this.mobileQuery.matches) {
                this.close();
            }
        };

        if (typeof this.mobileQuery.addEventListener === 'function') {
            this.mobileQuery.addEventListener('change', viewportHandler);
        } else if (typeof this.mobileQuery.addListener === 'function') {
            this.mobileQuery.addListener(viewportHandler);
        }
    }

    open() {
        if (! this.mobileQuery.matches) {
            return;
        }

        document.body.classList.add('sidebar-open');
        this.openButton?.setAttribute('aria-expanded', 'true');
        window.setTimeout(() => this.searchInput?.focus({preventScroll: true}), 80);
    }

    close() {
        document.body.classList.remove('sidebar-open');
        this.openButton?.setAttribute('aria-expanded', 'false');
    }

    handleKeydown(event) {
        if (event.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
            this.close();
            this.openButton?.focus();
            return;
        }

        const target = event.target;
        const isTyping = target instanceof HTMLInputElement
            || target instanceof HTMLTextAreaElement
            || target instanceof HTMLSelectElement
            || (target instanceof HTMLElement && target.isContentEditable);

        if (event.key === '/' && ! isTyping && this.searchInput instanceof HTMLInputElement) {
            event.preventDefault();
            if (this.mobileQuery.matches) {
                this.open();
            } else {
                this.searchInput.focus();
            }
        }
    }

    initializeGroups() {
        this.groups.forEach((group) => {
            if (!(group instanceof HTMLElement)) {
                return;
            }

            const toggle = group.querySelector('[data-sidebar-group-toggle]');
            if (!(toggle instanceof HTMLButtonElement)) {
                return;
            }

            toggle.addEventListener('click', () => {
                const collapsed = group.classList.toggle('is-collapsed');
                toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            });
        });
    }

    filter() {
        if (!(this.searchInput instanceof HTMLInputElement)) {
            return;
        }

        const query = normalizeSearchText(this.searchInput.value);
        let totalVisible = 0;

        this.groups.forEach((group) => {
            if (!(group instanceof HTMLElement)) {
                return;
            }

            const links = Array.from(group.querySelectorAll('[data-sidebar-link]'));
            let groupVisible = 0;

            links.forEach((link) => {
                if (!(link instanceof HTMLElement)) {
                    return;
                }

                const matches = query === '' || normalizeSearchText(link.textContent || '').includes(query);
                link.hidden = ! matches;
                if (matches) {
                    groupVisible++;
                    totalVisible++;
                }
            });

            group.hidden = groupVisible === 0;

            if (query !== '') {
                group.classList.remove('is-collapsed');
                const toggle = group.querySelector('[data-sidebar-group-toggle]');
                toggle?.setAttribute('aria-expanded', 'true');
            }
        });

        if (this.emptyState instanceof HTMLElement) {
            this.emptyState.hidden = totalVisible > 0;
        }
    }
}
