/**
 * Vite Build Config for FormaPress CRM
 *
 * Philosophy: ALWAYS output production-ready minified files
 *
 * Usage:
 *   npm run dev   - Watch mode (outputs minified files with source maps)
 *
 * Output:
 *   SCSS → ../assets/css/formapress-crm-admin-styles.css (minified)
 *   JS   → ../assets/js/formapress-crm-admin.js (minified)
 *   Maps → *.map files for browser DevTools debugging
 *
 * Note: No separate production build step needed - files are always production-ready
 */
import { defineConfig } from "vite";
import { resolve } from "path";

export default defineConfig({
    css: {
        preprocessorOptions: {
            scss: {
                api: "modern-compiler", // Modern Sass API with @use/@forward
            },
        },
    },
    build: {
        outDir: "../assets",
        emptyOutDir: false,
        minify: "terser", // Always minify
        sourcemap: true, // Always include source maps for debugging
        rollupOptions: {
            input: {
                "formapress-crm-admin": resolve(__dirname, "js/admin/formapress-crm-admin.js"),
                "formapress-crm-admin-styles": resolve(__dirname, "scss/formapress-crm-admin.scss"),
                "opportunity-editor": resolve(__dirname, "js/admin/opportunity-editor.js"),
                "opportunity-editor-styles": resolve(__dirname, "scss/opportunity-editor.scss"),
                "person-editor": resolve(__dirname, "js/admin/person-editor.js"),
                "person-editor-styles": resolve(__dirname, "scss/person-editor.scss"),
                "communication-modal": resolve(__dirname, "js/admin/communication-modal.js"),
                "communication-modal-styles": resolve(__dirname, "scss/communication-modal.scss"),
            },
            output: {
                entryFileNames: () => {
                    // JS files go to js/ directory
                    return "js/[name].js";
                },
                assetFileNames: (assetInfo) => {
                    // CSS files go to css/ directory
                    if (assetInfo.name && assetInfo.name.endsWith(".css")) {
                        return "css/[name][extname]";
                    }
                    return "assets/[name][extname]";
                },
            },
        },
        terserOptions: {
            compress: {
                drop_console: false, // Keep console.log for debugging
            },
            format: {
                comments: false, // Remove comments
            },
        },
    },
});
