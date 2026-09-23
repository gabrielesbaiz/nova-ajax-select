<template>
    <DefaultField
        :field="currentField"
        :errors="errors"
        :show-help-text="showHelpText"
        :full-width-content="fullWidthContent"
    >
        <template #field>
            <div class="relative w-full">
                <SearchInput
                    v-if="usesSearchInput"
                    v-model="value"
                    :options="displayOptions"
                    track-by="value"
                    :debounce="debounceMs"
                    :disabled="controlIsDisabled"
                    :read-only="currentlyIsReadonly"
                    :error="hasError"
                    :clearable="currentField.nullable !== false"
                    :mode="mode"
                    :autocomplete="currentField.autocomplete"
                    :dusk="`${fieldAttribute}-search-input`"
                    class="w-full"
                    @selected="selectOption"
                    @input="performSearch"
                    @clear="clearSelection"
                    @shown="handleDropdownShown"
                >
                    <template #default>
                        <div v-if="selectedOption" class="flex items-center">
                            {{ selectedOption.label }}
                        </div>
                        <div v-else class="text-gray-400 dark:text-gray-400">
                            {{ emptyStateText }}
                        </div>
                    </template>

                    <template #option="{ selected, option }">
                        <div
                            class="flex items-center text-sm font-semibold leading-5"
                            :class="{
                                'text-white dark:text-gray-900': selected,
                            }"
                        >
                            {{ option.label }}
                        </div>
                    </template>
                </SearchInput>

                <SelectControl
                    v-else
                    v-model="value"
                    :options="displayOptions"
                    label="label"
                    :has-error="hasError"
                    :disabled="controlIsDisabled"
                    :id="fieldAttribute"
                    :dusk="fieldAttribute"
                    class="w-full"
                    @selected="selectOption"
                >
                    <!--
            The placeholder lives in the slot on purpose: SelectControl calls
            option.value.toString() over :options, so a null-valued option there
            would throw.
          -->
                    <option
                        value=""
                        :disabled="currentField.nullable === false"
                    >
                        {{ emptyStateText }}
                    </option>
                </SelectControl>

                <span
                    v-if="loading"
                    class="pointer-events-none absolute inset-y-0 right-8 flex items-center"
                    dusk="ajax-select-loading"
                >
                    <svg
                        class="animate-spin h-4 w-4 text-gray-400"
                        viewBox="0 0 24 24"
                        fill="none"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        />
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                        />
                    </svg>
                </span>
            </div>
        </template>
    </DefaultField>
</template>

<script>
import {
    DependentFormField,
    HandlesValidationErrors,
    hasNovaComponent,
} from "../nova";
import FetchesAjaxOptions from "../mixins/FetchesAjaxOptions";
import { sameValue } from "../support/options";

export default {
    // Options API, not <script setup>: DependentFormField is a plain options
    // object (extends FormField extends FormEvents) and cannot be consumed from
    // a setup block.
    mixins: [HandlesValidationErrors, FetchesAjaxOptions, DependentFormField],

    data: () => ({
        value: null,
    }),

    mounted() {
        this.seedFromField();
        this.registerParentListeners();

        if (!this.hasParents || this.parentIsResolved) {
            // A searchable field with a stored value already has its label from the
            // server; pulling the whole table would defeat the point.
            if (!this.isAsyncSearchable && !this.loaded && this.usesEndpoint) {
                this.fetchFromEndpoint();
            }
        }
    },

    beforeUnmount() {
        this.unregisterParentListeners();
        this.abortInFlight();
        this.cascadeDebouncer?.cancel();
        this.searchDebouncer?.cancel();
    },

    computed: {
        usesSearchInput() {
            return (
                (this.isAsyncSearchable ||
                    this.currentField.searchable === true) &&
                !this.currentlyIsReadonly &&
                hasNovaComponent("SearchInput")
            );
        },

        controlIsDisabled() {
            return (
                this.currentlyIsReadonly ||
                (this.hasParents && !this.parentIsResolved)
            );
        },

        emptyStateText() {
            if (this.hasParents && !this.parentIsResolved) {
                return this.__("Choose a :field first", {
                    field: this.parentAttributes[0],
                });
            }

            if (this.errored)
                return this.__("Could not load the available options.");
            if (this.loading) return this.__("Loading options...");

            if (this.isAsyncSearchable && !this.searchIsLongEnough) {
                return this.__("Type at least :count characters to search", {
                    count: this.minSearchLength,
                });
            }

            if (this.loaded && this.displayOptions.length === 0) {
                return this.__("No Results Found.");
            }

            return this.currentField.placeholder || this.__("Choose an option");
        },

        /**
         * Ride Nova's own sync request to ask for search results, so this package
         * needs no route and inherits Nova's authorization.
         */
        currentFieldValues() {
            const values = { [this.fieldAttribute]: this.value };

            if (this.search && this.ajaxSelect.searchKey) {
                values[this.ajaxSelect.searchKey] = this.search;
            }

            return values;
        },
    },

    methods: {
        setInitialValue() {
            this.value =
                this.currentField.value === undefined ||
                this.currentField.value === null
                    ? null
                    : this.currentField.value;
        },

        /**
         * fillIfVisible() keeps a field hidden by dependsOn() from submitting a
         * stale value. The same closure serves resource forms and action modals.
         */
        fill(formData) {
            this.fillIfVisible(formData, this.fieldAttribute, this.value ?? "");
        },

        selectOption(option) {
            if (option == null) return this.clearSelection();

            this.selectedOption = option;
            this.value = option.value;
            this.emitFieldValueChange(this.fieldAttribute, this.value);
        },

        clearSelection() {
            if (this.value === null && this.selectedOption === null) return;

            this.value = null;
            this.selectedOption = null;
            this.emitFieldValueChange(this.fieldAttribute, this.value);
        },

        handleDropdownShown() {
            if (!this.loaded && !this.loading && this.minSearchLength === 0) {
                this.reloadOptions();
            }
        },

        /** Called by DependentFormField once a sync replaced currentField. */
        onSyncedField() {
            this.seedParentValuesFromField();
            this.options = this.displayOptionsFromSyncedField();
            this.loaded = true;
            this.loading = false;

            const selected = this.ajaxSelect.selectedOption ?? null;

            if (selected !== null) {
                this.selectedOption = selected;
            } else if (
                this.selectedOption !== null &&
                !sameValue(this.selectedOption.value, this.currentField.value)
            ) {
                this.selectedOption = null;
            }
        },

        displayOptionsFromSyncedField() {
            const options = this.currentField.options ?? [];

            return Array.isArray(options) ? options : [];
        },

        syncedFieldValueHasNotChanged() {
            return sameValue(this.currentField.value, this.value);
        },
    },
};
</script>
