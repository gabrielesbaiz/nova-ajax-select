<template>
    <DefaultField
        :field="field"
        :errors="errors"
        :show-help-text="showHelpText"
        :full-width-content="fullWidthContent"
    >
        <template #field>
            <!-- Search Input -->
            <SearchInput
                v-if="!currentlyIsReadonly && isSearchable"
                v-model="value"
                @selected="selectOption"
                @input="performSearch"
                @clear="clearSelection"
                :options="filteredOptions"
                :disabled="currentlyIsReadonly"
                :has-error="hasError"
                :clearable="currentField.nullable"
                trackBy="value"
                :mode="mode"
                class="w-full"
                :dusk="`${field.attribute}-search-input`"
                :autocomplete="currentField.autocomplete"
            >
                <template #default>
                    <!-- The Selected Option Slot -->
                    <div v-if="selectedOption" class="flex items-center">
                        {{ selectedOption.label }}
                    </div>
                </template>

                <template #option="{ selected, option }">
                    <!-- Options List Slot -->
                    <div
                        class="flex items-center text-sm font-semibold leading-5"
                        :class="{ 'text-white': selected }"
                    >
                        {{ option.label }}
                    </div>
                </template>
            </SearchInput>

            <SelectControl
                v-else
                v-model="value"
                @selected="selectOption"
                :options="currentField.options"
                :has-error="hasError"
                :disabled="currentlyIsReadonly"
                :id="field.attribute"
                class="w-full"
                :dusk="field.attribute"
            >
                <option value="" selected :disabled="!currentField.nullable">
                    {{ placeholder }}
                </option>

                <option :value="null" v-if="loaded && options.length">
                    {{ __("Choose an option") }}
                </option>
                <option :value="null" v-if="loaded && options.length == 0">
                    {{ __("No Results") }}
                </option>
                <option
                    :key="option.value"
                    :value="option.value"
                    v-for="option in options"
                >
                    {{ option.display }}
                </option>
            </SelectControl>
        </template>
    </DefaultField>
</template>

<script>
import { FormField, HandlesValidationErrors } from "laravel-nova";
import { walk } from "../utils";

export default {
    mixins: [FormField, HandlesValidationErrors],

    props: ["resourceName", "resourceId", "field"],

    data() {
        return {
            options: [],
            loaded: false,
            parentValue: null,
        };
    },

    mounted() {
        if (!this.parent_attribute) {
            this.updateOptions();
        }

        this.watchedComponents.forEach((component) => {
            let attribute = "value";

            if (component.field.component === "belongs-to-field") {
                attribute = "selectedResource";
            }

            component.$watch(
                attribute,
                (value) => {
                    this.parentValue =
                        value && attribute == "selectedResource"
                            ? value.value
                            : value;

                    this.updateOptions();
                },
                { immediate: true }
            );
        });
    },

    computed: {
        watchedComponents() {
            if (!this.field.parent_attribute) {
                return [];
            }

            const children = [];
            walk(this.$parent.$.subTree, (item) => {
                children.push(item);
            });

            return children.filter((component) => {
                return this.isWatchingComponent(component);
            });
        },
        endpoint() {
            return this.field.endpoint
                .replace("{resource-name}", this.resourceName)
                .replace(
                    "{resource-id}",
                    this.resourceId ? this.resourceId : ""
                )
                .replace(
                    "{" + this.field.parent_attribute + "}",
                    this.parentValue ? this.parentValue : ""
                );
        },
        empty() {
            return this.loaded && this.options.length == 0;
        },

        disabled() {
            return (
                (this.loaded == false &&
                    this.field.parent_attribute != undefined &&
                    this.parentValue == null) ||
                this.options.length == 0
            );
        },
    },

    methods: {
        /*
         * Set the initial, internal value for the field.
         */
        setInitialValue() {
            this.value = this.field.value || "";
        },

        /**
         * Fill the given FormData object with the field's internal value.
         */
        fill(formData) {
            formData.append(this.fieldAttribute, this.value || "");
        },

        updateOptions() {
            this.options = [];
            this.loaded = false;

            if (
                this.notWatching() ||
                (this.parentValue != null && this.parentValue != "")
            ) {
                Nova.request()
                    .get(this.endpoint)
                    .then((response) => {
                        this.loaded = true;
                        this.options = response.data;
                        let optionValueExists = false;
                        this.options.forEach((option) => {
                            if (option.value == this.value) {
                                optionValueExists = true;
                            }
                        });

                        if (optionValueExists == false) {
                            this.value = null;
                        }
                    });
            }
        },

        notWatching() {
            return this.field.parent_attribute == undefined;
        },

        isWatchingComponent(component) {
            return (
                component.field !== undefined &&
                component.field.attribute == this.field.parent_attribute
            );
        },
    },
};
</script>
