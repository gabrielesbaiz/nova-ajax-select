/**
 * A minimal trailing-edge debounce.
 *
 * Deliberately hand-rolled: lodash is provided as a webpack global by Nova's
 * own build, not by ours, and pulling it in would bundle it a second time.
 */
export default function debounce(callback, wait = 50) {
    let timeout = null;

    const debounced = (...args) => {
        if (timeout !== null) clearTimeout(timeout);

        timeout = setTimeout(() => {
            timeout = null;
            callback(...args);
        }, wait);
    };

    debounced.cancel = () => {
        if (timeout !== null) clearTimeout(timeout);
        timeout = null;
    };

    return debounced;
}
