import { beforeEach, describe, expect, it, vi } from "vitest";
import FetchesAjaxOptions from "../../resources/js/mixins/FetchesAjaxOptions";

/**
 * Exercise the mixin directly. Mounting a real component would drag in Nova's
 * DependentFormField and every globally registered Nova component; the state
 * machine we actually own is all in here.
 */
function harness(ajaxSelect = {}, overrides = {}) {
    const context = {
        ...FetchesAjaxOptions.data(),
        currentField: { ajaxSelect, debounce: 0 },
        resourceName: "customers",
        resourceId: null,
        value: null,
        getFieldAttributeChangeEventName: (attribute) =>
            `form-${attribute}-change`,
        syncField: vi.fn(),
        __: (key) => key,
        ...overrides,
    };

    // Bind computed properties and methods onto one plain object.
    for (const [name, fn] of Object.entries(FetchesAjaxOptions.computed)) {
        Object.defineProperty(context, name, {
            get: fn.bind(context),
            configurable: true,
        });
    }

    for (const [name, fn] of Object.entries(FetchesAjaxOptions.methods)) {
        context[name] = fn.bind(context);
    }

    FetchesAjaxOptions.created.call(context);

    return context;
}

beforeEach(() => {
    global.Nova = {
        request: vi.fn(),
        $on: vi.fn(),
        $off: vi.fn(),
        error: vi.fn(),
    };
});

describe("parent resolution", () => {
    it("reads parents from the v2 meta block", () => {
        const vm = harness({ parents: ["region_id"] });

        expect(vm.parentAttributes).toEqual(["region_id"]);
        expect(vm.hasParents).toBe(true);
    });

    it("still reads the 1.x single parent key", () => {
        const vm = harness(
            {},
            { currentField: { parent_attribute: "region_id" } },
        );

        expect(vm.parentAttributes).toEqual(["region_id"]);
    });

    it("treats an empty parent as unresolved", () => {
        const vm = harness({ parents: ["a", "b"] });

        vm.parentValues = { a: 1, b: null };
        expect(vm.parentIsResolved).toBe(false);

        vm.parentValues = { a: 1, b: 2 };
        expect(vm.parentIsResolved).toBe(true);
    });

    it("seeds parent values from the server so an edit form can load", () => {
        const vm = harness({
            parents: ["region_id"],
            parentValues: { region_id: 3 },
        });

        vm.seedFromField();

        expect(vm.parentValues).toEqual({ region_id: 3 });
        expect(vm.parentIsResolved).toBe(true);
    });

    it("unwraps an object emitted by a belongs-to field", () => {
        const vm = harness();

        expect(vm.normalizeParentValue({ value: 4 })).toBe(4);
        expect(vm.normalizeParentValue("")).toBe(null);
        expect(vm.normalizeParentValue(5)).toBe(5);
    });
});

describe("listeners", () => {
    it("does not subscribe in options mode, where nova already syncs", () => {
        const vm = harness({ parents: ["region_id"], mode: "options" });

        vm.registerParentListeners();

        expect(global.Nova.$on).not.toHaveBeenCalled();
    });

    it("subscribes in endpoint mode", () => {
        const vm = harness({
            parents: ["region_id"],
            mode: "endpoint",
            endpoint: "/api/cities/{region_id}",
        });

        vm.registerParentListeners();

        expect(global.Nova.$on).toHaveBeenCalledWith(
            "form-region_id-change",
            expect.any(Function),
        );
    });
});

describe("endpoint mode", () => {
    const endpointField = {
        parents: ["region_id"],
        mode: "endpoint",
        endpoint: "/api/cities/{region_id}",
    };

    it("interpolates the parent value into the url", () => {
        const vm = harness(endpointField);
        vm.parentValues = { region_id: 9 };

        expect(vm.endpointUrl).toBe("/api/cities/9");
    });

    it("does not request while a parent is missing", async () => {
        const vm = harness(endpointField);
        vm.parentValues = { region_id: null };

        await vm.fetchFromEndpoint();

        expect(global.Nova.request).not.toHaveBeenCalled();
    });

    it("normalizes a legacy payload and marks itself loaded", async () => {
        global.Nova.request = () => ({
            get: () =>
                Promise.resolve({ data: [{ value: 1, display: "Udine" }] }),
        });

        const vm = harness(endpointField);
        vm.parentValues = { region_id: 1 };

        await vm.fetchFromEndpoint();

        expect(vm.options).toEqual([{ value: 1, label: "Udine" }]);
        expect(vm.loaded).toBe(true);
        expect(vm.loading).toBe(false);
    });

    it("reports a failure without leaving stale options behind", async () => {
        global.Nova.request = () => ({
            get: () => Promise.reject(new Error("boom")),
        });

        const vm = harness(endpointField);
        vm.parentValues = { region_id: 1 };
        vm.options = [{ value: 1, label: "stale" }];

        await vm.fetchFromEndpoint();

        expect(vm.errored).toBe(true);
        expect(vm.options).toEqual([]);
        expect(global.Nova.error).toHaveBeenCalled();
    });

    it("ignores a cancelled request", async () => {
        const cancelled = new Error("cancelled");
        cancelled.code = "ERR_CANCELED";

        global.Nova.request = () => ({ get: () => Promise.reject(cancelled) });

        const vm = harness(endpointField);
        vm.parentValues = { region_id: 1 };

        await vm.fetchFromEndpoint();

        expect(vm.errored).toBe(false);
        expect(global.Nova.error).not.toHaveBeenCalled();
    });

    it("aborts a request that is still in flight", () => {
        const vm = harness(endpointField);
        const abort = vi.fn();

        vm.abortController = { abort };
        vm.abortInFlight();

        expect(abort).toHaveBeenCalled();
        expect(vm.abortController).toBe(null);
    });
});

describe("selection", () => {
    it("keeps the stored option visible when the page does not contain it", () => {
        const vm = harness();

        vm.options = [{ value: 2, label: "Trieste" }];
        vm.selectedOption = { value: 1, label: "Udine" };

        expect(vm.displayOptions).toEqual([
            { value: 1, label: "Udine" },
            { value: 2, label: "Trieste" },
        ]);
    });

    it("does not duplicate an option already present", () => {
        const vm = harness();

        vm.options = [{ value: 1, label: "Udine" }];
        vm.selectedOption = { value: 1, label: "Udine" };

        expect(vm.displayOptions).toHaveLength(1);
    });

    it("adopts the fresh option when the value is still offered", () => {
        const vm = harness();

        vm.value = 1;
        vm.options = [{ value: 1, label: "Udine" }];
        vm.clearSelection = vi.fn();

        vm.reconcileSelection();

        expect(vm.selectedOption).toEqual({ value: 1, label: "Udine" });
        expect(vm.clearSelection).not.toHaveBeenCalled();
    });

    it("drops a value the server no longer offers", () => {
        const vm = harness();

        vm.value = 99;
        vm.options = [{ value: 1, label: "Udine" }];
        vm.clearSelection = vi.fn();

        vm.reconcileSelection();

        expect(vm.clearSelection).toHaveBeenCalled();
    });

    it("never drops a value merely because a search filtered it out", () => {
        const vm = harness();

        vm.value = 99;
        vm.search = "udi";
        vm.options = [{ value: 1, label: "Udine" }];
        vm.clearSelection = vi.fn();

        vm.reconcileSelection();

        expect(vm.clearSelection).not.toHaveBeenCalled();
    });
});

describe("search", () => {
    it("waits for the minimum length before reloading", async () => {
        const vm = harness({ asyncSearchable: true, minSearchLength: 3 });
        vm.reloadOptions = vi.fn();

        vm.performSearch("ud");
        await new Promise((resolve) => setTimeout(resolve, 20));

        expect(vm.reloadOptions).not.toHaveBeenCalled();

        vm.performSearch("udi");
        await new Promise((resolve) => setTimeout(resolve, 20));

        expect(vm.reloadOptions).toHaveBeenCalled();
    });

    it("falls back to nova sync when no endpoint is configured", () => {
        const vm = harness({ mode: "options" });

        vm.reloadOptions();

        expect(vm.syncField).toHaveBeenCalled();
    });
});

describe("local filtering", () => {
    it("filters its own options when the server does not own the search", () => {
        const vm = harness({ asyncSearchable: false });
        vm.reloadOptions = vi.fn();
        vm.options = [
            { value: 1, label: "Torino" },
            { value: 2, label: "Alessandria" },
            { value: 3, label: "Alba" },
        ];

        vm.performSearch("al");

        expect(vm.reloadOptions).not.toHaveBeenCalled();
        expect(vm.filteredOptions.map((o) => o.label)).toEqual([
            "Alessandria",
            "Alba",
        ]);
    });

    it("is case insensitive and clears when the term is emptied", () => {
        const vm = harness({ asyncSearchable: false });
        vm.reloadOptions = vi.fn();
        vm.options = [
            { value: 1, label: "Torino" },
            { value: 2, label: "Alba" },
        ];

        vm.performSearch("TOR");
        expect(vm.filteredOptions.map((o) => o.label)).toEqual(["Torino"]);

        vm.performSearch("");
        expect(vm.filteredOptions).toHaveLength(2);
    });

    it("leaves an async field to the server and does not filter twice", async () => {
        const vm = harness({ asyncSearchable: true, minSearchLength: 0 });
        vm.reloadOptions = vi.fn();
        vm.options = [
            { value: 1, label: "Torino" },
            { value: 2, label: "Alba" },
        ];

        vm.performSearch("zzz");
        await new Promise((r) => setTimeout(r, 20));

        expect(vm.reloadOptions).toHaveBeenCalled();
        // the server already answered; the component must not re-filter it away
        expect(vm.filteredOptions).toHaveLength(2);
    });

    it("keeps the selected option reachable while filtering", () => {
        const vm = harness({ asyncSearchable: false });
        vm.options = [{ value: 2, label: "Alba" }];
        vm.selectedOption = { value: 1, label: "Torino" };

        expect(vm.filteredOptions.map((o) => o.label)).toEqual([
            "Torino",
            "Alba",
        ]);

        vm.performSearch("alb");
        expect(vm.filteredOptions.map((o) => o.label)).toEqual(["Alba"]);
    });
});
