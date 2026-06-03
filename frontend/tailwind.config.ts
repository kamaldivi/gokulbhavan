import type { Config } from "tailwindcss";
import typography from "@tailwindcss/typography";

export default {
  content: ["./src/**/*.{astro,html,js,jsx,ts,tsx}"],
  theme: {
    extend: {
      // ── Brand Colors ─────────────────────────────────────────────
      // Devotional palette: golden cream / deep navy / magenta / gold
      colors: {
        brand: {
          parchment: "#FFF7DF", // page background — golden cream
          linen:     "#FFFDF6", // card / surface — soft ivory
          tan:       "#EADDB7", // borders, dividers — warm gold
          slate:     "#2A506A", // muted text / nav bar / footer background
          navy:      "#082A4A", // headings, active states — deep navy
          lotus:     "#C94277", // primary CTA — magenta
          gold:      "#E8A207", // secondary accent — gold
        },
      },

      // ── Typography ───────────────────────────────────────────────
      fontFamily: {
        sans:        ["Inter",              "system-ui", "sans-serif"],
        display:     ["Manrope", "Inter",   "system-ui", "sans-serif"],
        devanagari:  ["Noto Sans Devanagari", "sans-serif"],
        tamil:       ["Noto Sans Tamil",    "sans-serif"],
      },

      // ── Spacing extras ───────────────────────────────────────────
      spacing: {
        "18": "4.5rem",
        "22": "5.5rem",
      },
    },
  },
  plugins: [typography],
} satisfies Config;
