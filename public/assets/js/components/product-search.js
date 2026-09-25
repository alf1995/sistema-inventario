import {RemoteCombobox} from './remote-combobox.js';

export class ProductSearch {
    constructor(root) {
        this.combobox = new RemoteCombobox(root, {
            inputSelector: '[data-product-search-input]',
            valueSelector: '[data-product-id]',
            resultsSelector: '[data-product-search-results]',
            selectionSelector: '[data-product-search-selection]',
            statusSelector: '[data-product-search-status]',
            optionClass: 'product-search-option',
            selectionRequiredMessage: 'Selecciona un producto de la lista de resultados.',
            emptyMessage: 'No se encontraron productos activos.',
            errorMessage: 'No fue posible cargar los productos.',
            moreMessage: 'Hay más productos coincidentes. Escribe más caracteres para afinar la búsqueda.',
            getLabel: (product) => product.name,
            renderOption: (button, product) => this.renderOption(button, product),
            renderSelection: (selection, product) => this.renderSelection(selection, product),
        });
    }

    mount() {
        this.combobox.mount();
    }

    renderOption(button, product) {
        const content = document.createElement('span');
        content.className = 'product-search-option-main';

        const name = document.createElement('span');
        name.className = 'product-search-option-name';
        name.textContent = product.name;

        const presentation = document.createElement('span');
        presentation.className = 'product-search-option-detail';
        presentation.textContent = `${Number(product.units_per_package).toLocaleString()} unid. por empaque`;

        content.append(name, presentation);

        const stock = document.createElement('span');
        stock.className = 'product-search-option-stock';
        stock.textContent = `Stock: ${Number(product.stock).toLocaleString()}`;

        button.append(content, stock);
    }

    renderSelection(selection, product) {
        const label = document.createTextNode('Seleccionado: ');
        const strong = document.createElement('strong');
        strong.textContent = product.name;
        const stock = document.createTextNode(` · Stock: ${Number(product.stock).toLocaleString()}`);
        selection.append(label, strong, stock);
    }
}
