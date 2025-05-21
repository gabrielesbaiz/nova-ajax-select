import IndexField from "./components/IndexField";
import DetailField from "./components/DetailField";
import FormField from "./components/FormField";
import PreviewField from "./components/PreviewField";

Nova.booting((app, store) => {
    app.component("index-nova-ajax-select", IndexField);
    app.component("detail-nova-ajax-select", DetailField);
    app.component("form-nova-ajax-select", FormField);
    // app.component('preview-nova-ajax-select', PreviewField)
});
