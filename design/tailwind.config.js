/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./sites/**/*.html",
    "./components/**/*.html",
    "./components/**/*.js",
  ],
  theme: {
    extend: {
      // --- Brand Colors (devotional palette — golden cream / deep navy / magenta / gold) ---
      colors: {
        brand: {
          parchment: "#FFF7DF", // page background — golden cream
          linen:     "#FFFDF6", // card / surface background — soft ivory
          tan:       "#EADDB7", // borders, dividers — warm gold border
          slate:     "#4B6981", // muted / secondary text — slate blue-grey
          navy:      "#082A4A", // nav bar, primary headings — deep navy
          cobalt:    "#123C69", // hover, active states — medium navy
          lotus:     "#C94277", // accent — magenta lotus (primary CTA)
          gold:      "#E8A207", // accent — peacock feather gold (secondary)
          charcoal:  "#1A1A1A", // footer background
          "charcoal-light": "#252525", // footer personality strip
        },
      },

      // --- Typography ---
      fontFamily: {
        // Body text — readable, modern
        sans:    ["Inter", "system-ui", "sans-serif"],
        // Display / headings — strong, refined
        display: ["Manrope", "Inter", "system-ui", "sans-serif"],
        // Script languages
        devanagari: ["Noto Sans Devanagari", "sans-serif"],
        tamil:      ["Noto Sans Tamil", "sans-serif"],
      },

      // --- Spacing ---
      spacing: {
        "18": "4.5rem",
        "22": "5.5rem",
        "128": "32rem",
      },
    },
  },
  plugins: [
    require("@tailwindcss/typography"),
  ],
};
