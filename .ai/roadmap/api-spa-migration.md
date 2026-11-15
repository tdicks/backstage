# API + SPA Migration

## Context
The Blade + Alpine UI has outgrown its design. Several surfaces poll the server and swap in
server-rendered HTML fragments (`SessionCardFragment`, `resources/js/utils/sessionFragments.js`,
`summaryPollId` in `sessionCards.js`), which makes concurrent editing awkward and lets UI state drift
between pages. The UI also has visual inconsistencies that are cheaper to fix with a rebuild than
piecemeal.

Goal: move to a versioned JSON API in Laravel plus a React single-page app, migrating one surface at a
time while the existing UI keeps working. The Eloquent models stay as they are; relationships are added
only where the API needs them.

Progress is tracked in the "Backstage SPA Migration" project plan artifact. The AI rule changes this
work needs are listed in `.ai/roadmap/api-spa-migration-ai-rules-gap-analysis.md`.

## Target Architecture

### Hosting and auth
- The SPA is served by this Laravel app on the same origin, under `/app/*`: one catch-all web route
  returns a minimal Blade shell that mounts React.
- Built by the existing Vite setup as a second entry point. Source lives under `resources/js/spa/`
  (no new top-level directory).
- Authentication uses Laravel Sanctum in SPA (stateful cookie) mode. Users log in once (password or
  Socialite) and the session is shared by both UIs. No API tokens for the browser.

### API
- Routes in `routes/api.php` under `/api/v1`, protected by `auth:sanctum`.
- Controllers in `App\Http\Controllers\Api\V1\*`, thin: authorize, validate, call a domain action,
  return a resource.
- Validation in Form Requests (currently almost all validation is inline in controllers).
- Responses through Eloquent API Resources (`App\Http\Resources\V1\*`). One consistent error format
  (Laravel's default 422 shape) and one pagination envelope.
- Existing Policies remain the source of truth for authorization.
- **Shared domain logic.** Before an endpoint is built, the business rules it needs are extracted from
  the web controller into action/service classes (`app/Services` or an `app/Actions` folder, if
  approved). Both the web and API controllers call the same code while both UIs exist.
- **Abilities in responses.** Every resource the UI can act on includes an `abilities` object computed
  server-side from policies and the set-state/visibility matrix, e.g.
  `{"take": true, "request": false, "release": false, "edit": true}`. The SPA renders from these flags
  and never re-derives permission logic.

### Concurrency
- **Reads:** TanStack Query caches server state per query key. Stale data is refetched on focus, on
  interval (first step), and on realtime events (later step).
- **Realtime:** Laravel Reverb + Echo. Models broadcast small "changed" events on private channels
  (per jam session, per set, per user). The SPA invalidates the matching query keys; it does not apply
  payloads directly. Polling remains as the fallback when the socket is down.
- **Writes:** optimistic concurrency. Mutable resources expose a `version` (or `updated_at`). Clients
  send it with writes; a stale write returns `409 Conflict` with the current resource so the UI can
  show what changed. Highest priority: song/slot reordering, taking/releasing slots, set settings.
- Optimistic UI updates are allowed for low-risk actions, with rollback on error.

### Frontend stack
- React + TypeScript, TanStack Query, React Router (or TanStack Router).
- UI primitives: shadcn/ui (Radix + Tailwind 4). Icons: Heroicons for React, keeping the current
  icon-per-action mapping.
- A design-token layer that carries over the existing colour semantics (amber = action, emerald =
  success, dark emerald = live, sky = current user, red = error, purple = info).
- TypeScript types generated from the API (Scramble → OpenAPI → `openapi-typescript`).
- Tests: Vitest + Testing Library for components, Playwright for end-to-end and multi-user
  concurrency scenarios.

## Phases

### Phase 0: Decisions
- Approve new dependencies: Sanctum; React, React DOM, TypeScript, TanStack Query, router, Radix /
  shadcn, Heroicons React, Vitest, Testing Library; later Reverb, Laravel Echo, Scramble,
  openapi-typescript.
- Confirm SPA source location (`resources/js/spa/`) and URL prefix (`/app`).
- Confirm location for extracted domain logic (`app/Services` vs. new `app/Actions`).
- Apply the AI rule changes from the gap analysis before writing feature code.

### Phase 1: Foundations
- Install Sanctum (`php artisan install:api`), configure stateful domains, CSRF cookie flow.
- `api/v1` route group, rate limiting, `GET /api/v1/me` (user, roles, preferences, feature flags).
- API conventions in code: base resource, abilities helper, error/pagination shape, version check
  middleware or trait for 409 handling.
- SPA shell route + Blade view + Vite entry; router, query client, auth bootstrap, app layout.
- Design system: tokens, typography, buttons, modal/dialog, panel, badge, menu, toast, form fields,
  empty/loading/error states.
- Per-user "Try the new UI" toggle and links between the two UIs for the same entity.
- Test infrastructure: Pest API test helpers, Vitest config, Playwright project, CI wiring.
- Type generation pipeline.

### Phase 2: First slice: jam sessions and set cards
The surface with the most concurrency pain (`SessionController`, `JamSessionController`,
`SetController`, `SlotController`, `SlotAssignmentController`, `SongController`,
`SongRequestController`, `AttachmentController`, `SetCollaboratorController`, `LiveJamController`).
- Extract domain actions; add API endpoints and resources with abilities.
- Pest API tests covering the full visibility matrix (closed session, performed set,
  `signups_open` / `free_for_all` / `song_requests` combinations, each role).
- React pages: session list, session detail with set cards, slot actions, song requests,
  attachments, collaborators, reordering with conflict handling.
- Polling through TanStack Query `refetchInterval` as the interim refresh.

### Phase 3: Realtime
- Install Reverb + Echo, `routes/channels.php` authorization for session/set/user channels.
- Broadcast events from the domain actions (not controllers) so both UIs trigger them.
- SPA: subscribe per page, invalidate query keys, presence for "who's here" where useful.
- Playwright two-browser tests: change in one, visible in the other without reload.
- Optionally point the legacy Alpine polling at the same events to reduce load until cutover.

### Phase 4: Remaining surfaces
Each one is a vertical slice: extract logic → API + tests → React page → parity check.
1. My sets and planned sets (`MySetsController`, `PlannedSetController`).
2. Jam Standards, quick sets, capabilities, song requests (`JamStandard*Controller`).
3. Slot finder and practice plan (`SlotFinderController`, `PracticePlanController`).
4. Jam register and attendance (`JamRegisterController`, `JamSessionAttendanceController`).
5. Dashboard and widgets (`DashboardController`, `DashboardWidgetCatalog`): replace gridstack Blade
   widgets with a React widget contract.
6. Notifications, push subscriptions, notices (`Notification*Controller`, `NoticeDismissalController`).
7. Profile, user directory, band templates, recycle bin, Deezer lookups.
8. Admin (`Admin\*`).

### Phase 5: Cross-cutting features
- Feature tours: port the runner to React; keep tour definitions in `resources/tours/*.yaml` and the
  `app:validate-feature-tours-config` check working against SPA anchors.
- Help page content moved into the SPA.
- Web push service worker and notification popups working from the SPA.
- Decide which public pages stay server-rendered (share links, welcome, static pages, auth screens).

### Phase 6: Cutover and decommission
- Make the SPA the default for everyone; keep the legacy UI reachable for a short grace period.
- Redirect map from legacy routes to SPA routes, including links in emails and push notifications.
- Remove Blade views, Alpine components, fragment renderers and web controllers no longer used;
  retire Alpine-specific rules.
- Remove Alpine, gridstack and sortable dependencies if unused.

## Working Rules During Migration
- While both UIs exist, domain logic lives in shared actions/services, never in only one controller.
- New features go into the SPA only. The legacy UI gets bug fixes, not features.
- Each slice ships with API feature tests, component tests for non-trivial UI, and at least one
  Playwright journey.
- A slice is done when the SPA page reaches parity with the legacy page and the "Try the new UI"
  toggle routes there.

## Risks
- Logic drift between web and API controllers: mitigated by extracting actions first.
- Permission drift in the UI: mitigated by server-computed abilities.
- Scope creep from the redesign: freeze the design-system primitives in Phase 1 before building pages.
- Long dual-running period: keep slices small and cut over surfaces as they reach parity.
