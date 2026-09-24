<template>
    <div :class="`text-${field.textAlign}`">
        <template v-if="fieldValue">
            <div v-if="shouldDisplayAsHtml" @click.stop v-html="fieldValue" />
            <span v-else class="whitespace-nowrap">{{ fieldValue }}</span>
        </template>
        <p v-else>&mdash;</p>
    </div>
</template>

<script>
import { FieldValue } from "../nova";

// FieldValue prints field.displayedAs when the server resolved a label, so
// index rendering costs no client request at all.
export default {
    mixins: [FieldValue],

    props: ["resourceName", "field"],
};
</script>
