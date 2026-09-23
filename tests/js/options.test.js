import { describe, expect, it } from "vitest";
import {
    normalizeOptions,
    sameValue,
} from "../../resources/js/support/options";
import { interpolate } from "../../resources/js/support/endpoint";

describe("normalizeOptions", () => {
    it("normalizes the v2 shape unchanged", () => {
        expect(normalizeOptions([{ value: 1, label: "Udine" }])).toEqual([
            { value: 1, label: "Udine" },
        ]);
    });

    it("promotes the legacy display key", () => {
        expect(normalizeOptions([{ value: 1, display: "Udine" }])).toEqual([
            { value: 1, label: "Udine" },
        ]);
    });

    it("prefers label over display", () => {
        expect(
            normalizeOptions([
                { value: 1, label: "Real", display: "Legacy" },
            ])[0].label,
        ).toBe("Real");
    });

    it("normalizes a value => label map with numeric casting", () => {
        expect(normalizeOptions({ 12: "Udine" })).toEqual([
            { value: 12, label: "Udine" },
        ]);
    });

    it("leaves non canonical numeric strings alone", () => {
        expect(normalizeOptions({ "007": "Bond" })[0].value).toBe("007");
    });

    it("unwraps common envelopes", () => {
        expect(normalizeOptions({ data: [{ id: 3, name: "X" }] })).toEqual([
            { value: 3, label: "X" },
        ]);
    });

    it("keeps group, subtitle and disabled", () => {
        expect(
            normalizeOptions([
                {
                    value: 1,
                    label: "A",
                    group: "G",
                    subtitle: "S",
                    disabled: true,
                },
            ]),
        ).toEqual([
            { value: 1, label: "A", group: "G", subtitle: "S", disabled: true },
        ]);
    });

    it("drops entries without a value", () => {
        expect(normalizeOptions([null, { label: "orphan" }])).toEqual([]);
    });

    it("is empty for null", () => {
        expect(normalizeOptions(null)).toEqual([]);
    });
});

describe("sameValue", () => {
    it("compares loosely across types", () => {
        expect(sameValue(1, "1")).toBe(true);
        expect(sameValue("a", "a")).toBe(true);
        expect(sameValue(1, 2)).toBe(false);
        expect(sameValue(null, null)).toBe(true);
        expect(sameValue(null, 1)).toBe(false);
    });
});

describe("interpolate", () => {
    it("replaces every occurrence of a token", () => {
        expect(interpolate("/a/{id}/b/{id}", { id: 7 })).toBe("/a/7/b/7");
    });

    it("escapes the substituted value", () => {
        expect(interpolate("/a/{q}", { q: "a b&c" })).toBe("/a/a%20b%26c");
    });

    it("empties a missing value rather than printing null", () => {
        expect(interpolate("/a/{id}", { id: null })).toBe("/a/");
    });

    it("leaves unknown tokens intact", () => {
        expect(interpolate("/a/{other}", { id: 1 })).toBe("/a/{other}");
    });
});
