import DetailField from "./components/DetailField.vue";
import FormField from "./components/FormField.vue";
import IndexField from "./components/IndexField.vue";

Nova.booting((app) => {
    // PascalCase on purpose: Nova.hasComponent() capitalizes and camelizes the
    // name before looking it up, so a kebab registration is invisible to it,
    // while Vue still resolves <component is="form-gabrielesbaiz-ajax-select" />.
    app.component("IndexGabrielesbaizAjaxSelect", IndexField);
    app.component("DetailGabrielesbaizAjaxSelect", DetailField);
    app.component("FormGabrielesbaizAjaxSelect", FormField);
});
