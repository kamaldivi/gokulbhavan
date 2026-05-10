# Gokul Bhavan — Design Prototype Monorepo

Design prototypes for the Gokul Bhavan suite of websites. Static HTML + Tailwind CSS.
Stakeholder review in browser. No framework lock-in.

## Sites

| Site | Prototype folder | Live URL |
| ---- | ---------------- | -------- |
| Gokul Bhavan (main) | `sites/gokulbhavan/` | <https://gokulbhavan.com> |
| Gokul Bhajans | `sites/gokulbhajans/` | <https://gokulbhajans.com> |
| Pure Bhakti Base | `sites/purebhakti/` | <https://purebhaktibase.com> |
| Tamil Sangha | `sites/tamil-sangha/` | TBD |

## Structure

```text
assets/          Shared brand assets (logos, images, fonts, icons)
tokens/          Design tokens — colors, typography, spacing (CSS + Tailwind config)
components/      Shared UI components (header, footer, nav, cards, etc.)
sites/           Per-site prototype pages
docs/            Design decisions and stakeholder review notes
```

## Getting started

```bash
npm install
npm run dev      # starts Tailwind watcher + local server on http://localhost:3000
```

Then open <http://localhost:3000/sites/gokulbhavan/> in your browser.

## Adding brand assets

Drop files into `assets/brand/` (logos), `assets/images/`, `assets/fonts/`, or `assets/icons/`.
Update `tailwind.config.js` and `tokens/main.css` with final color values once approved.
