export function whenReady(callback) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback, {once: true});
        return;
    }

    callback();
}

export function mountAll(selector, ComponentClass, options = undefined) {
    document.querySelectorAll(selector).forEach(function (element) {
        if (!(element instanceof HTMLElement)) {
            return;
        }

        const instance = new ComponentClass(element, options);
        if (typeof instance.mount === 'function') {
            instance.mount();
        }
    });
}
