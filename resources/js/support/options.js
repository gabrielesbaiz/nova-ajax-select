/**
 * Normalize every payload shape this package has ever accepted into Nova's
 * canonical `{ value, label }`.
 *
 *   v2      [{ value: 1, label: 'Udine' }]
 *   v1      [{ value: 1, display: 'Udine' }]   <- legacy ->get() endpoints
 *   map     { 1: 'Udine', 2: 'Trieste' }
 *   wrapped { options: [...] } | { data: [...] } | { resources: [...] }
 */
export function normalizeOptions(payload) {
    if (payload == null) return [];

    const list = Array.isArray(payload)
        ? payload
        : (payload.options ?? payload.resources ?? payload.data ?? payload);

    if (!Array.isArray(list)) {
        return Object.entries(list).map(([value, label]) => ({
            value: castValue(value),
            label: String(label),
        }));
    }

    return list.map(normalizeOption).filter(Boolean);
}

function normalizeOption(option) {
    if (option == null) return null;

    if (typeof option !== "object") {
        return { value: option, label: String(option) };
    }

    const value = option.value ?? option.id ?? null;

    if (value === null) return null;

    return {
        value,
        // `display` is the 1.x key; `label` always wins when both are present.
        label: String(
            option.label ??
                option.display ??
                option.title ??
                option.name ??
                option.text ??
                value,
        ),
        ...(option.group ? { group: option.group } : {}),
        ...(option.subtitle ? { subtitle: option.subtitle } : {}),
        ...(option.disabled ? { disabled: true } : {}),
    };
}

/** Mirrors the server, which casts canonical numeric strings to integers. */
function castValue(value) {
    return /^-?\d+$/.test(value) && String(Number(value)) === value
        ? Number(value)
        : value;
}

/** Loose comparison, because a form value is a string and an option id is not. */
export function sameValue(a, b) {
    if (a == null || b == null) return a === b;

    return String(a) === String(b);
}
