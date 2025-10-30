/**
 * Vite Build Config for FormaPress CRM
 *
 * Usage:
 *   npm run dev   - Watch mode for development
 *   npm run build - Production build
 *
 * Outputs:
 *   SCSS → ../assets/css/formapress-crm-admin-styles.css
 *   JS   → ../assets/js/formapress-crm-admin.js
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
        rollupOptions: {
            input: {
                "formapress-crm-admin": resolve(__dirname, "js/admin/formapress-crm-admin.js"),
                "formapress-crm-admin-styles": resolve(__dirname, "scss/formapress-crm-admin.scss"),
            },
            output: {
                entryFileNames: (chunkInfo) => {
                    // JS files go to js/
                    if (chunkInfo.name.endsWith("-admin")) {
                        return "js/[name].js";
                    }
                    return "js/[name].js";
                },
                assetFileNames: (assetInfo) => {
                    // CSS files go to css/
                    if (assetInfo.name && assetInfo.name.endsWith(".css")) {
                        return "css/[name][extname]";
                    }
                    return "assets/[name][extname]";
                },
            },
        },
        minify: "terser",
        sourcemap: true,
        terserOptions: {
            compress: {
                drop_console: false,
            },
            mangle: false, // Keep function names readable for WordPress debugging
        },
    },
});
