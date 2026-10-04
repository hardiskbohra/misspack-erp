# MissPack ERP UI design guidelines

This is the shared visual contract for the ERP application shell and its modules. The current implementation is a mix of the newer `core-*` components and legacy `master-*`/module components; both should resolve to the same tokens and visual rules. New screens should use `core-*` where available. Existing screens may retain their markup while their module CSS is limited to layout and domain-specific states.

## Foundations

- **Typeface:** Inter, with the application font stack as fallback.
- **Page canvas:** `--mc-soft`.
- **Card surface:** `--mc-card` (white in light mode; the theme's dark surface in dark mode).
- **Nested/quiet surface:** `--mc-card-soft`.
- **Borders, text and shadows:** use `--mc-border`, `--mc-text`, `--mc-text-2`, `--mc-text-3`, and `--mc-shadow`; do not hard-code a light-theme color into a shared component.
- **Semantic palette:** blue, teal, green, orange, purple and red are reserved for state or meaning. Put semantic color on an icon, badge, indicator or hairline—not as a full-card pastel wash.

## Typography

| Role | Size | Weight |
| --- | ---: | ---: |
| Page title | 20–22 px | 600–700 |
| Card/section title | 15–16 px | 600 |
| Body and table values | 13–14 px | 400–500 |
| Field labels and table headings | 11–12 px | 500–600 |
| Key metric value | 18 px | 600 |
| Status/badge | 11–12 px | 600 |

**Project-wide cap: no text may exceed `font-weight: 700`.** This applies to application, public, portal, print, preview, inline, embedded, and checked-in vendor styles, as well as font files requested from external providers. Use weight and size sparingly; avoid extra-bold/black text for headings, labels, table headers, badges, and buttons.

## Surfaces and spacing

- Cards use a theme surface, a 1 px theme border, an 18 px outer radius, and the shared shadow. Nested cards use the same surface with a quieter border; do not use another pastel card fill.
- Stat cards use the same surface as ordinary cards. Their meaning is shown with a semantic icon badge and a restrained border accent.
- Tab panels may use `--mc-card-soft` to remain visibly distinct from the page canvas; cards inside the panel use `--mc-card`.
- Use an 8 px spacing rhythm: 12–16 px between related controls, 16 px between cards, and 20–24 px for outer card padding where the content permits.
- Controls and buttons should remain 40–44 px tall and retain visible keyboard focus states.

## Tables and tabs

- Tables sit inside a white/theme card with a single `--mc-border` row divider. Headers use the shared table-head token, 11 px uppercase text at weight 600; values use 13 px text at weight 400–500. Keep numeric columns right-aligned and tabular.
- Keep horizontal scrolling on the wrapper, not on the table element. On small screens, preserve the module's labelled row/card layout and touch targets.
- Use the shared `.master-tabs` / `.master-tab` treatment for record tabs. Module-specific tabs (such as Cashflow settings) should match its spacing, surface, active state, and focus treatment.

## Implementation rules

- Shared component styles belong in `core.css`, `master-*.css`, or the final `app-guidelines.css` compatibility layer—not in a module's private copy of a generic card, table, tab, or typography rule.
- Module styles may define grid columns, widths, responsive composition, and semantic states. They must use the theme tokens for surfaces and text.
- Preserve domain colors for statuses and credits/debits, but keep those colors local to the status element, icon, or border.
- `app-guidelines.css` is loaded last by `layouts.app`; `data-ui-module` scopes compatibility rules for Shipments, Clients, Cashflows and Users without changing their behavior or data.
