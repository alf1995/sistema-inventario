import {whenReady, mountAll} from './core/bootstrap.js';
import {ConfirmationModal} from './components/confirmation-modal.js';
import {LocalTableSearch} from './components/local-table-search.js';
import {MovementForm} from './components/movement-form.js';
import {ProductSearch} from './components/product-search.js';
import {ResponsiveSidebar} from './components/responsive-sidebar.js';
import {ResponsiveTables} from './components/responsive-tables.js';

whenReady(function () {
    const confirmationModal = document.querySelector('[data-confirm-modal]');
    if (confirmationModal instanceof HTMLElement) {
        new ConfirmationModal(confirmationModal).mount();
    }

    mountAll('[data-movement-form]', MovementForm);
    mountAll('[data-product-search]', ProductSearch);
    mountAll('[data-local-table-search]', LocalTableSearch);

    const sidebar = document.querySelector('[data-sidebar]');
    if (sidebar instanceof HTMLElement) {
        new ResponsiveSidebar(sidebar).mount();
    }

    new ResponsiveTables(document).mount();
});
