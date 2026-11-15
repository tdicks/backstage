# AI Rules Gap Analysis: API + SPA Migration

## Purpose
Compares the current rules in `AGENTS.md` and `.ai/rules/**` with what agents will need to build the
API + React SPA described in `.ai/roadmap/api-spa-migration.md`. The current rules are written almost
entirely for Blade + Alpine. Without changes, agents will either apply the wrong conventions to SPA
code or have no guidance at all.

Status key:
- **Keep**: applies unchanged.
- **Adapt**: still valid in intent; wording or scope must change.
- **Conflict**: contradicts the target architecture; must change before SPA work starts.
- **Retire at cutover**: valid for legacy code only; delete when the legacy UI is removed.

## 1. Existing Rules Assessed

### AGENTS.md (Laravel Boost guidelines)
| Rule | Status | Notes |
| --- | --- | --- |
| "Do not change dependencies without approval" | Keep | Phase 0 approval needed for the full dependency list; record the approved list in a rule so agents don't ask per package. |
| "Don't create new base folders without approval" | Keep | Satisfied by `resources/js/spa/`. If `app/Actions` is wanted, approve it explicitly. |
| "APIs: default to Eloquent API Resources and API versioning" | Keep | Now directly relevant; no API exists yet, so this becomes the convention. |
| Frontend bundling note (`npm run build`) | Adapt | Add type-check (`tsc --noEmit`) and Vitest commands. |
| Pest test enforcement | Adapt | Still mandatory for PHP; JS changes need Vitest/Playwright equivalents. |
| Boost package guidelines | Adapt | Re-run `php artisan boost:install` after adding Sanctum/Reverb so package-specific guidance is generated. |

### .ai/rules/copilot-instructions.md
| Section | Status | Notes |
| --- | --- | --- |
| Instruction layering | Keep | |
| Project priorities | Keep | |
| Alpine.js Placement Policy | Retire at cutover; scope now | Must be scoped to legacy paths (`resources/views/**`, `resources/js/components/**`) so it is not applied to `resources/js/spa/**`. |
| Design guidelines: primary/secondary-button, modal Blade components | Conflict | The SPA needs the equivalent React components named here. Keep the intent (one component per role) and list both sets during migration. |
| Panel treatment `bg-slate-50/95 border-slate-200` | Adapt | Move into design tokens; the rebuild is meant to fix inconsistencies, so tokens become the source of truth. |
| Prompt modal instead of native `prompt()` | Keep | Map to the React dialog component. |
| Iconography and colour usage | Keep (Adapt wording) | Semantics carry over unchanged. Change `heroicon-m-*` Blade names to the React import names and the token names. |
| Style guide for slots, sets, sessions, attachments | Keep (Adapt wording) | Same semantics; express as component props/variants (e.g. `<SlotBadge state="open">`). |
| Dynamic Content: "Use Alpine.JS to update the page" | Conflict | Replace with: server state via TanStack Query, mutations via API, realtime invalidation. The underlying rule (no full page reloads, modals/inline forms) stays. |
| Quality gates | Adapt | Add `tsc`, Vitest, Playwright, and API test commands. |
| Safety constraints | Keep | |
| Help page at `resources/views/static/help.blade.php` | Adapt | Phase 5 moves help into the SPA; until then, document new SPA features in the existing help page. |

### .ai/rules/core/project-baseline.md
| Rule | Status | Notes |
| --- | --- | --- |
| "Reuse existing components, Blade partials, and JS modules" | Adapt | SPA must not reuse Blade partials or Alpine modules; reuse SPA components and shared domain services instead. |
| "Prefer small, incremental changes over broad rewrites" | Adapt | Still the default, but the migration is a sanctioned rewrite. Clarify that the rewrite happens slice by slice per the roadmap. |
| Laravel + Blade section | Adapt | Add API counterparts: thin controllers, Form Requests, Resources. |
| Remaining rules | Keep | |

### .ai/rules/frontend/blade-alpine-templates.md
Status: **Retire at cutover**. Valid for legacy Blade only. Scope its globs to legacy paths now.
Its useful principles (single source of truth for displayed collections, stable test/tour attributes
across rerenders, phased refactors) must be restated in the new React rules.

### .ai/rules/frontend/ui-copy-icons-and-colors.md
Status: **Keep**, with globs extended to `resources/js/spa/**`. The set-card padding rule (`p-4` /
`p-3`) should become a component default rather than a per-page instruction.

### .ai/rules/components/dashboard-widget-system.md
Status: **Retire at cutover**. It documents the gridstack Blade implementation. Phase 4 needs a new
SPA widget contract (registry, frame component, layout persistence API, data loading per widget).
Note: this file is not referenced from `index.md` today, so agents may never load it.

### .ai/rules/domain/authorization-and-approval-model.md
Status: **Adapt**. The role model is unchanged. "Implementation Rule" must change from
"update controller guard + menu visibility" to:
1. Policy / domain action guard.
2. The `abilities` field in the relevant API resource.
3. API feature tests (and legacy menu visibility while it exists).

### .ai/rules/domain/set-state-and-visibility-matrix.md
Status: **Adapt**. "UI must reflect eligibility" becomes "API exposes eligibility via `abilities`; UI
renders only from `abilities`". Regression coverage moves to API tests asserting the abilities payload
for each state/role combination.

### .ai/rules/domain/notifications.md
Status: **Keep**, plus an addition: notifications that should appear live in the SPA must also
broadcast on the user's private channel. Popup delivery moves from Blade/Alpine store to the SPA.

### .ai/rules/workflows/testing-and-validation.md
Status: **Adapt**. Add commands and an area test matrix for `tests/Feature/Api/**`, Vitest, and
Playwright. The existing matrix stays for legacy tests until cutover.

### .ai/rules/workflows/feature-tour-targets.md
Status: **Adapt**. "Define targets in template markup" becomes "define `data-tour` attributes in the
React component that renders the element, never in effects or portals created later". The validation
command must be extended or a SPA-side check added.

### .ai/rules/index.md
Status: **Adapt**. Missing globs for every new path (see section 3). `resources/js/**` currently maps
to the Blade/Alpine rules and would match SPA code.

### .ai/roadmap/alpine-template-rendering-migration.md
Status: **Superseded**. Its remaining follow-ups (`lazySessionSets`, `sessionCards`,
`dashboardActionQueues`) are replaced by Phase 2 and Phase 4 of the SPA roadmap. Mark it superseded
so agents stop picking up that work.

## 2. New Rules Needed

| # | Proposed file | Covers | Needed by |
| --- | --- | --- | --- |
| N1 | `api/api-conventions.md` | `/api/v1` routing, thin controllers, Form Requests, Resources, naming, status codes, error and pagination shape, eager loading / N+1 rules, when to add relationships to models. | Phase 1 |
| N2 | `api/authentication.md` | Sanctum SPA mode, CSRF cookie flow, session sharing with the legacy UI, rate limits, which routes stay public. | Phase 1 |
| N3 | `domain/abilities-contract.md` | How `abilities` are computed from policies + the visibility matrix, naming of ability keys, the rule that the SPA never re-derives permissions, required tests. | Phase 1 |
| N4 | `domain/shared-domain-actions.md` | Where business logic lives during dual running; web and API controllers both call the same action; events are dispatched from actions. | Phase 1 |
| N5 | `api/concurrency-and-realtime.md` | `version` field, 409 conflict contract, which writes require it, broadcast event naming, channel naming and authorization, "invalidate, don't patch" on the client, polling fallback. | Phase 2–3 |
| N6 | `frontend/spa-architecture.md` | Folder layout in `resources/js/spa/` (routes, features, components, api, lib), TypeScript strictness, routing, code splitting, where state lives (server state in TanStack Query, UI state local, minimal global store). | Phase 1 |
| N7 | `frontend/data-fetching.md` | Query key conventions, one API client, generated types only (no hand-written response types), mutation patterns, optimistic updates with rollback, error and loading states. | Phase 1 |
| N8 | `frontend/design-system.md` | Tokens, the component catalogue (buttons, dialog, panel, badge, menu, toast, form fields), when to add a new primitive vs. compose, Heroicons React mapping, accessibility baseline (focus, labels, keyboard). Replaces the Blade component references in copilot-instructions. | Phase 1 |
| N9 | `frontend/forms-and-validation.md` | Mapping Laravel 422 errors onto fields, client-side validation limits (server is authoritative), modals vs. inline forms. | Phase 2 |
| N10 | `workflows/spa-testing.md` | Vitest + Testing Library conventions, Playwright journeys including two-user concurrency tests, test data seeding for e2e, commands and smallest-scope guidance. | Phase 1 |
| N11 | `workflows/migration-dual-running.md` | Legacy UI gets fixes only; new features SPA-only; definition of done for a slice; parity checklist; linking between UIs; redirect map maintenance; how to retire legacy code. | Phase 1 |
| N12 | `components/spa-dashboard-widgets.md` | Replacement for the gridstack widget contract. | Phase 4 |
| N13 | `workflows/spa-feature-tours.md` (or update existing) | Tour anchors in React, tour runner integration, validation. | Phase 5 |

## 3. index.md Changes Needed
Add path mappings:
- `routes/api.php`, `routes/channels.php` → N1, N2, N5, authorization model.
- `app/Http/Controllers/Api/**`, `app/Http/Requests/**`, `app/Http/Resources/**` → N1, N3, N4,
  authorization model, visibility matrix, testing.
- `app/Events/**`, `app/Broadcasting/**` → N5.
- `app/Services/**` (and `app/Actions/**` if approved) → N4.
- `resources/js/spa/**` → N6, N7, N8, N9, N10, ui-copy-icons-and-colors, feature tours.
- `tests/Feature/Api/**` → N1, N3, testing.
- SPA test paths (Vitest, Playwright) → N10.

Narrow existing mappings:
- `resources/js/**` → `resources/js/components/**`, `resources/js/stores/**`, `resources/js/utils/**`
  for the Blade/Alpine rules, so they do not apply to SPA code.

Add task mappings:
- "API endpoint or resource changes" → N1, N3, N4.
- "Live updates, polling, or concurrent editing" → N5, N7.
- "SPA page or component work" → N6, N7, N8, N11.
- Point "Larger refactors" at `.ai/roadmap/api-spa-migration.md`.

## 4. Suggested Order
1. Before Phase 1: update `index.md` scoping; add N11; mark the Alpine roadmap superseded; resolve the
   two **Conflict** items in copilot-instructions.
2. During Phase 1: write N1–N4, N6–N8, N10 alongside the first code so each rule reflects a real
   example.
3. Phase 2–3: N5, N9; adapt the authorization and visibility-matrix rules.
4. Phase 4–5: N12, N13; re-run Boost install for new packages.
5. Cutover: delete Blade/Alpine rules and the gridstack widget doc; remove legacy entries from
   `index.md` and the testing matrix.
