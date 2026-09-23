import { isCancellation } from "../nova";
import debounce from "../support/debounce";
import { interpolate } from "../support/endpoint";
import { normalizeOptions, sameValue } from "../support/options";

/**
 * Loading options, watching parents, and keeping the selection coherent.
 *
 * Two request paths exist:
 *
 *  - options mode (the default) rides Nova's own field-sync request, so the
 *    package owns no route and inherits Nova's authorization;
 *  - endpoint mode fetches the application's own URL, preserving the 1.x
 *    `->get('/api/...')` contract.
 */
export default {
    data: () => ({
        options: [],
        selectedOption: null,
        parentValues: {},
        search: "",
        loading: false,
        loaded: false,
        errored: false,
        abortController: null,
        parentListeners: {},
    }),

    created() {
        // Collapse the burst of events a cascading reset produces into one request.
        this.cascadeDebouncer = debounce((callback) => callback(), 50);
        this.searchDebouncer = debounce(
            (callback) => callback(),
            this.debounceMs,
        );
    },

    computed: {
        /** Always read through currentField so a dependsOn() sync is honoured. */
        ajaxSelect() {
            return this.currentField.ajaxSelect ?? {};
        },

        parentAttributes() {
            const parents =
                this.ajaxSelect.parents ??
                // 1.x serialized a single attribute under this key.
                (this.currentField.parent_attribute
                    ? [this.currentField.parent_attribute]
                    : []);

            return Array.isArray(parents) ? parents : [parents];
        },

        hasParents() {
            return this.parentAttributes.length > 0;
        },

        parentIsResolved() {
            return this.parentAttributes.every((attribute) => {
                const value = this.parentValues[attribute];

                return value !== null && value !== undefined && value !== "";
            });
        },

        usesEndpoint() {
            return (
                this.ajaxSelect.mode === "endpoint" &&
                !!this.ajaxSelect.endpoint
            );
        },

        isAsyncSearchable() {
            return this.ajaxSelect.asyncSearchable === true;
        },

        minSearchLength() {
            return this.ajaxSelect.minSearchLength ?? 0;
        },

        debounceMs() {
            return this.currentField.debounce ?? 500;
        },

        endpointUrl() {
            return interpolate(this.ajaxSelect.endpoint, {
                "resource-name": this.resourceName ?? "",
                "resource-id": this.resourceId ?? "",
                ...this.parentValues,
            });
        },

        /**
         * The list handed to the control. The stored option is always present, so
         * an edit form shows its label even when the server returned another page.
         */
        displayOptions() {
            if (this.selectedOption == null) return this.options;

            const present = this.options.some((option) =>
                sameValue(option.value, this.selectedOption.value),
            );

            return present
                ? this.options
                : [this.selectedOption, ...this.options];
        },

        searchIsLongEnough() {
            return (
                this.minSearchLength === 0 ||
                this.search.length >= this.minSearchLength
            );
        },
    },

    methods: {
        seedFromField() {
            this.selectedOption = this.ajaxSelect.selectedOption ?? null;
            this.options = normalizeOptions(this.currentField.options ?? []);
            this.loaded = this.options.length > 0;

            // Parents never emit a change event on mount, so the server hands us
            // their current values; without this an edit form could not load.
            const seeded = this.ajaxSelect.parentValues ?? {};

            this.parentAttributes.forEach((attribute) => {
                this.parentValues[attribute] = this.normalizeParentValue(
                    seeded[attribute] ?? null,
                );
            });
        },

        /**
         * Only endpoint mode needs its own listeners.
         *
         * In options mode the server registers the same attributes through
         * dependsOn(), so DependentFormField is already listening and will call
         * syncField() itself - subscribing here too would fire two requests per
         * parent change.
         */
        registerParentListeners() {
            if (!this.usesEndpoint) return;

            this.parentAttributes.forEach((attribute) => {
                // Namespaced by formUniqueId, which is what makes this work inside
                // action modals, panels and relation modals alike.
                const eventName =
                    this.getFieldAttributeChangeEventName(attribute);

                const handler = (value) => {
                    const next = this.normalizeParentValue(value);

                    if (this.parentValues[attribute] === next) return;

                    this.parentValues[attribute] = next;
                    this.cascadeDebouncer(() => this.handleParentChanged());
                };

                this.parentListeners[eventName] = handler;
                Nova.$on(eventName, handler);
            });
        },

        unregisterParentListeners() {
            Object.entries(this.parentListeners).forEach(([name, handler]) =>
                Nova.$off(name, handler),
            );

            this.parentListeners = {};
        },

        /** BelongsTo emits an object; a select emits a scalar. */
        normalizeParentValue(value) {
            if (value && typeof value === "object") {
                return value.value ?? value.id ?? null;
            }

            return value === "" ? null : value;
        },

        handleParentChanged() {
            this.search = "";
            this.options = [];
            this.loaded = false;

            // Clearing our own value is what cascades the change to our children.
            this.clearSelection();

            if (!this.hasParents || this.parentIsResolved) {
                this.reloadOptions();
            }
        },

        /**
         * In options mode the server answers through Nova's own sync request,
         * which DependentFormField already knows how to make.
         */
        reloadOptions() {
            return this.usesEndpoint
                ? this.fetchFromEndpoint()
                : this.syncField();
        },

        /** Re-read parent values from a field the server just re-serialized. */
        seedParentValuesFromField() {
            const seeded = this.ajaxSelect.parentValues ?? {};

            this.parentAttributes.forEach((attribute) => {
                this.parentValues[attribute] = this.normalizeParentValue(
                    seeded[attribute] ?? null,
                );
            });
        },

        abortInFlight() {
            if (this.abortController !== null) {
                this.abortController.abort();
                this.abortController = null;
            }
        },

        fetchFromEndpoint() {
            if (this.hasParents && !this.parentIsResolved)
                return Promise.resolve();

            this.abortInFlight();

            const controller = new AbortController();

            this.abortController = controller;
            this.loading = true;
            this.errored = false;

            return Nova.request()
                .get(this.endpointUrl, { signal: controller.signal })
                .then(({ data }) => {
                    // A superseded request must never overwrite fresher state.
                    if (this.abortController !== controller) return;

                    this.options = normalizeOptions(data);
                    this.loaded = true;
                    this.reconcileSelection();
                })
                .catch((error) => {
                    if (
                        isCancellation(error) ||
                        this.abortController !== controller
                    ) {
                        return;
                    }

                    this.errored = true;
                    this.options = [];
                    Nova.error(
                        this.__("Could not load the available options."),
                    );
                })
                .finally(() => {
                    if (this.abortController === controller) {
                        this.abortController = null;
                        this.loading = false;
                    }
                });
        },

        /**
         * Drop a selection the server no longer offers, but never because a search
         * simply did not include it.
         */
        reconcileSelection() {
            if (
                this.value === null ||
                this.value === "" ||
                this.value === undefined
            ) {
                return;
            }

            const match = this.options.find((option) =>
                sameValue(option.value, this.value),
            );

            if (match) {
                this.selectedOption = match;

                return;
            }

            if (this.search) return;

            this.clearSelection();
        },

        performSearch(search) {
            this.search = (search ?? "").trim();

            if (!this.searchIsLongEnough) return;

            this.searchDebouncer(() => this.reloadOptions());
        },
    },
};
