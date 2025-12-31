# Copilot Instructions - FormaPress CRM

## Core Principles

1. **No cheap workarounds** - Always choose the future-proof, less technical debt solution
2. **Assume dev watchers are running** - Vite watch mode is active during development
3. **No markdown logorrhea** - Essential info goes in code comments, not proliferating .md files
4. **Dynamic theming** - Use CSS variables from parent zformations plugin, never hardcode colors
5. **Migration-first architecture** - Always assume v1→v2 migration has completed successfully. Never write fallback code for unmigrated data. If unmigrated data is discovered, fix the migration tools, not the consuming code.

## Project Context

-   **Type:** WordPress CRM plugin for French training organizations
-   **Parent:** Extends zformations suite (inherits branding/colors)
-   **Goal:** Simple CRM (vs "too complex" competitor) + automated Bilan Pédagogique et Financier

## Tech Stack

-   **Build:** Vite (modern, fast) - `npm run dev` for watch mode
-   **Styles:** SCSS with modern `@use` syntax (no deprecated `@import`)
-   **Colors:** CSS variables injected by zformations: `var(--zform_color_orange)`, etc.
-   **JS:** Vanilla + jQuery (WordPress standard)

## Code Standards

### SCSS

```scss
// ✅ Good - Use CSS variables
$color_orange: var(--zform_color_orange);

// ❌ Bad - Hardcoded colors
$color_orange: #ff5e00;

// ✅ Good - Modern @use
@use "components/kanban";

// ❌ Bad - Deprecated @import
@import "components/kanban";
```

### PHP

-   Follow WordPress coding standards (WPCS)
-   Use proper escaping: `esc_html()`, `esc_attr()`, `wp_kses()`
-   Nonce verification for forms
-   Tabs for indentation (WordPress convention)
-   **Fix lint errors:** When making changes to a file, proactively fix any lint errors (both new and legacy) according to WordPress coding standards. Don't leave code quality worse than you found it.

### File Organization

```
_dev/
  scss/
    _variables.scss          ← Shared variables
    formapress-crm-admin.scss ← Main entry
    components/
      _kanban.scss           ← Partials with @use
  js/
    admin/
      formapress-crm-admin.js ← Main entry
```

## WordPress Integration

-   **Hook priority:** CRM hooks after zformations (priority 101+)
-   **Assets:** Enqueue with version constant for cache busting
-   **AJAX:** Use `formapressCrmAdmin` global object
-   **CPTs:** Prefix with `crm_` (crm_person, crm_opportunity, crm_activity)

## What NOT to Do

-   ❌ Don't silence deprecation warnings - fix the root cause
-   ❌ Don't create .md files for every feature - use code comments
-   ❌ Don't hardcode UI colors - they're configurable per customer
-   ❌ Don't use outdated Sass syntax - use modern `@use/@forward`
-   ❌ Don't break existing shortcodes - customers have 500-5000 per site
-   ❌ Don't ignore lint errors - fix them as you go, even legacy ones
-   ❌ Don't write v1/v2 coexistence code - migration tools exist for a reason

## Development Workflow

1. `cd _dev && npm run dev` (starts Vite watcher)
2. Edit SCSS/JS files
3. Auto-rebuilds in ~100ms
4. Refresh WordPress admin to see changes

## When Making Changes

-   Always consider: "Will this work in 3 years?"
-   Always consider: "Does this add technical debt?"
-   When in doubt, ask before using shortcuts
