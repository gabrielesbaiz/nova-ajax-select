import { fileURLToPath, URL } from "node:url";
import { defineConfig } from "vite";

export default defineConfig({
    resolve: {
        alias: {
            // Only Nova's running app provides this module; the bundle keeps it
            // external, so tests substitute a stub.
            "laravel-nova": fileURLToPath(
                new URL("./tests/js/stubs/laravel-nova.js", import.meta.url),
            ),
        },
    },
    test: {
        environment: "happy-dom",
        include: ["tests/js/**/*.test.js"],
    },
});
