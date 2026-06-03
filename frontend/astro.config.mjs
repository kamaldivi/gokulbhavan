import { defineConfig } from "astro/config";
import tailwind from "@astrojs/tailwind";
import react from "@astrojs/react";

export default defineConfig({
  integrations: [
    tailwind({ applyBaseStyles: false }),
    react(),
  ],

  // Static output for IONOS Deploy Now
  output: "static",

  devToolbar: { enabled: false },

  // API base URL injected at build time via env var
  // VITE_API_BASE_URL=https://api.gokulbhavan.com
  vite: {
    define: {
      "import.meta.env.VITE_API_BASE_URL": JSON.stringify(
        process.env.VITE_API_BASE_URL ?? ""
      ),
    },
    server: {
      proxy: {
        // Forward all /api/* requests to the local PHP server during dev
        "/api": "http://localhost:8000",
      },
    },
  },
});
