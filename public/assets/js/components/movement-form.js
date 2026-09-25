export class MovementForm {
    constructor(root) {
        this.root = root;
        this.directionSelect = root.querySelector('[data-movement-direction]');
        this.typeSelect = root.querySelector('[data-movement-type]');
    }

    mount() {
        if (!(this.root instanceof HTMLFormElement)
            || !(this.directionSelect instanceof HTMLSelectElement)
            || !(this.typeSelect instanceof HTMLSelectElement)) {
            return;
        }

        this.syncMovementTypes();
        this.directionSelect.addEventListener('change', () => this.syncMovementTypes());
    }

    syncMovementTypes() {
        const direction = this.directionSelect.value;
        let selectedStillValid = this.typeSelect.value === '';

        Array.from(this.typeSelect.options).forEach((option) => {
            if (option.value === '') {
                option.hidden = false;
                option.disabled = false;
                return;
            }

            const allowedDirection = option.dataset.allowedDirection || 'BOTH';
            const isCompatible = allowedDirection === 'BOTH' || allowedDirection === direction;
            option.hidden = ! isCompatible;
            option.disabled = ! isCompatible;

            if (option.selected && isCompatible) {
                selectedStillValid = true;
            }
        });

        if (! selectedStillValid) {
            this.typeSelect.value = '';
        }
    }
}
