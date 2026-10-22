# Sidebar navigation

The office sidebar follows the sequence of work rather than exposing a flat list of database modules.

1. **Overview** — company dashboard and module analytics.
2. **Project execution** — coordinator queue, projects, tasks, exceptions, packing, shipment and feedback work.
3. **Sales & catalogue** — clients, products and client invoices.
4. **Procurement** — vendors, purchases and office services.
5. **Finance** — cashflow, recurring payments, statements, document archive and fixed assets.
6. **Administration** — users, audit evidence and settings.

Frequently used operational destinations are placed before reference and governance areas. Notes remains in the persistent application topbar rather than being duplicated in the office sidebar. Employee navigation remains a short personal workspace.

Permission-aware menus remove unauthorized destinations and also remove any section heading left without a visible destination. Active-route exclusions keep the coordinator queue and exception register from incorrectly highlighting Projects, while cashflow submodules retain one active destination at a time. The active link uses `aria-current="page"`.

Sidebar rows use a compact 40 px target, restrained section labels and scrolling within the navigation rail. This keeps the complete information architecture reachable on common laptop heights without reducing labels to unreadable text or hiding destinations behind undocumented icons.
