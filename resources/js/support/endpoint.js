/**
 * Fill `{token}` placeholders in a legacy `->get()` URL.
 *
 * Unlike 1.x this escapes the substituted value and replaces every occurrence
 * of a token, not just the first.
 */
export function interpolate(template, replacements) {
    return Object.entries(replacements).reduce((url, [token, value]) => {
        const pattern = new RegExp(
            `\\{${token.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}\\}`,
            "g",
        );

        return url.replace(
            pattern,
            value == null ? "" : encodeURIComponent(value),
        );
    }, template);
}
