import vue from "@vitejs/plugin-vue";
import { fileURLToPath, URL } from "node:url";
import { defineConfig } from "vite";

/**
 * Mirrors vendor/laravel/nova-devtool/nova.mix.js.
 *
 * Nova assigns these globals in its own app.js and ui.js, and package scripts
 * are loaded after them, so they are always defined by the time this bundle
 * runs. Bundling any of them instead would ship a second Vue runtime.
 */
const novaExternals = {
    vue: "Vue",
    "laravel-nova": "LaravelNova",
    "laravel-nova-ui": "LaravelNovaUi",
    "laravel-nova-util": "LaravelNovaUtil",
};

export default defineConfig({
    plugins: [vue()],

    define: {
        "process.env.NODE_ENV": JSON.stringify("production"),
        __VUE_OPTIONS_API__: "true",
        __VUE_PROD_DEVTOOLS__: "false",
        __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: "false",
    },

    build: {
        outDir: "dist",
        emptyOutDir: true,
        cssCodeSplit: false,
        sourcemap: false,
        target: "es2020",
        minify: "esbuild",
        lib: {
            entry: fileURLToPath(
                new URL("./resources/js/field.js", import.meta.url),
            ),
            // An IIFE build needs a name even though the bundle exports nothing.
            name: "GabrielesbaizNovaAjaxSelect",
            formats: ["iife"],
        },
        rollupOptions: {
            external: Object.keys(novaExternals),
            output: {
                // An IIFE with externals requires globals, or Rollup emits undefined
                // references instead of reading Nova's runtime.
                globals: novaExternals,
                // build.lib.fileName cannot reliably carry a directory prefix.
                entryFileNames: "js/field.js",
                assetFileNames: "css/field.[ext]",
            },
        },
    },
});
