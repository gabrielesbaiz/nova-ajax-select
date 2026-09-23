<template>
    <PanelItem :index="index" :field="field" />
</template>

<script>
// PanelItem already honours displayedAs, copyable and asHtml.
export default {
    props: ["index", "resource", "resourceName", "resourceId", "field"],
};
</script>
