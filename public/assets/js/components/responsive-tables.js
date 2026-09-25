import {normalizeSearchText} from '../core/text.js';

export class ResponsiveTables {
    constructor(root = document) {
        this.root = root;
    }

    mount() {
        this.root.querySelectorAll('.table-wrap table:not(.permission-matrix)').forEach((table) => this.prepare(table));
    }

    prepare(table) {
        if (!(table instanceof HTMLTableElement) || table.dataset.responsiveReady === '1') {
            return;
        }

        const headerRow = table.tHead?.rows[0];
        if (!(headerRow instanceof HTMLTableRowElement)) {
            return;
        }

        const headers = Array.from(headerRow.cells).map((cell) => (cell.textContent || '').replace(/\s+/g, ' ').trim());
        table.classList.add('responsive-table');
        table.dataset.responsiveReady = '1';

        Array.from(table.tBodies).forEach((tbody) => {
            Array.from(tbody.rows).forEach((row) => {
                const cells = Array.from(row.cells);
                if (cells.length === 1 && cells[0].colSpan > 1) {
                    cells[0].classList.add('table-empty-cell');
                    return;
                }

                cells.forEach((cell, index) => {
                    const label = headers[index] || '';
                    if (! cell.dataset.label && label !== '') {
                        cell.dataset.label = label;
                    }

                    if (index === 0) {
                        cell.classList.add('table-cell-primary');
                    }

                    const normalizedLabel = normalizeSearchText(label);
                    if (normalizedLabel === 'accion' || normalizedLabel === 'acciones') {
                        cell.classList.add('table-cell-actions');
                    }
                });
            });
        });
    }
}
