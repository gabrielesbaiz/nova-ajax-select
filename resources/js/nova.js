/**
 * The single seam between this package and Nova's internals.
 *
 * Everything imported from `laravel-nova` goes through here, so a rename in a
 * future Nova release is a one-file change rather than a scavenger hunt.
 */
import {
    DependentFormField,
    FieldValue,
    HandlesValidationErrors,
} from "laravel-nova";

export { DependentFormField, FieldValue, HandlesValidationErrors };

/**
 * Nova.hasComponent() capitalizes and camelizes the name before looking it up,
 * so this only answers truthfully for PascalCase registrations.
 */
export function hasNovaComponent(name) {
    try {
        return Nova.hasComponent(name);
    } catch (e) {
        return false;
    }
}

/**
 * Axios is not externalized by Nova, so importing it would ship a second copy.
 * Sniff the shapes an aborted request can take instead.
 */
export function isCancellation(error) {
    return (
        error?.code === "ERR_CANCELED" ||
        error?.name === "CanceledError" ||
        error?.name === "AbortError"
    );
}
