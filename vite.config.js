import { defineConfig } from "vite";
import { resolve, dirname } from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

export default defineConfig({
  server: {
    host: true,
    cors: true,
    strictPort: true,
    port: 5174,
    hmr: {
      protocol: "ws",
      host: "localhost",
    },
  },
  build: {
    outDir: "build",
    emptyOutDir: true,
    manifest: true,
    cssCodeSplit: true,
    rollupOptions: {
      input: {
        app: resolve(__dirname, "src/js/app.js"),
        blockEditor: resolve(__dirname, "src/js/admin/block-editor.js"),
        ppfxAdmin: resolve(__dirname, "src/js/admin/ppfx-admin.js"),
        ppfxAdminCss: resolve(__dirname, "src/css/ppfx-admin.css"),
      },
      output: {
        entryFileNames: "js/[name]-[hash].js",
        chunkFileNames: "js/[name]-[hash].js",
        assetFileNames: ({ name }) => {
          if (/\.(gif|jpe?g|png|svg)$/.test(name ?? "")) {
            return "images/[name]-[hash][extname]";
          }
          if (/\.css$/.test(name ?? "")) {
            return "css/[name]-[hash][extname]";
          }
          return "assets/[name]-[hash][extname]";
        },
        globals: {
          underscore: "_",
          jquery: "jQuery",
        },
      },
      external: ["underscore", "jquery"],
    },
  },
});
