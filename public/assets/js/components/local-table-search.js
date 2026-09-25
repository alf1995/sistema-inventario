import {normalizeSearchText} from '../core/text.js';

export class LocalTableSearch {
    constructor(input) {
        this.input = input;
        this.table = null;
        this.rows = [];
    }

    mount() {
        const tableId = this.input.dataset.localTableSearch || '';
        const table = document.getElementById(tableId);
        if (!(this.input instanceof HTMLInputElement)
            || !(table instanceof HTMLTableElement)
            || ! table.tBodies[0]) {
            return;
        }

        this.table = table;
        this.rows = Array.from(table.tBodies[0].rows).filter((row) => ! (row.cells.length === 1 && row.cells[0].colSpan > 1));
        this.input.addEventListener('input', () => this.filter());
    }

    filter() {
        const query = normalizeSearchText(this.input.value);
        let visible = 0;

        this.rows.forEach((row) => {
            const matches = query === '' || normalizeSearchText(row.textContent || '').includes(query);
            row.hidden = ! matches;
            if (matches) {
                visible++;
            }
        });

        const tbody = this.table.tBodies[0];
        let emptyRow = tbody.querySelector('[data-local-filter-empty]');

        if (visible === 0 && this.rows.length > 0) {
            if (!(emptyRow instanceof HTMLTableRowElement)) {
                emptyRow = document.createElement('tr');
                emptyRow.dataset.localFilterEmpty = '1';
                const cell = document.createElement('td');
                cell.colSpan = this.table.tHead?.rows[0]?.cells.length || 1;
                cell.className = 'empty-state';
                cell.textContent = 'No hay registros que coincidan con la búsqueda.';
                emptyRow.appendChild(cell);
                tbody.appendChild(emptyRow);
            }
            emptyRow.hidden = false;
        } else if (emptyRow instanceof HTMLTableRowElement) {
            emptyRow.hidden = true;
        }
    }
}
