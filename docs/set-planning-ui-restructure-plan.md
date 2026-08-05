# Set Planning + IA Restructure Plan

## Purpose

Define a practical implementation plan for:

1. Introducing visible draft/planned sets that are not yet tied to a jam session.
2. Reframing My Sets to match its label (user-owned/collaborated sets).
3. Moving approvals/requests into Dashboard.
4. Moving Practice Plan into Tools as its own page.
5. Consolidating navigation without clutter while keeping Who's Who top-level.

This plan is implementation-oriented and aligned with project guidelines:

1. Simple UI for non-technical users.
2. Dynamic-first interactions (AJAX + Alpine/JS module state updates).
3. Reuse existing patterns where possible.
4. Keep copy concise and direct.

## Product Decisions Captured

1. Draft sets remain visible to other users unless hidden.
2. Draft sets can collect collaborators, slot participation, and session availability votes.
3. A set can exist without jam_session_id until scheduled.
4. My Sets should become a real set library for the current user.
5. Practice Plan moves to a dedicated Tools page.
6. Approvals/Requests become dashboard-first content.
7. Who's Who remains top-level in navigation.

## Information Architecture Target

Top-level nav:

1. Dashboard
2. Jam Sessions
3. Sets (dropdown)
4. Tools (dropdown)
5. Who's Who

Sets menu:

1. My Sets
2. Planned Sets (or Draft Sets)

Tools menu:

1. Jam Standards
2. Find a Slot
3. Practice Plan

## Data Model Changes

### 1) Sets: Allow Unscheduled State

Migration:

1. Alter sets.jam_session_id to nullable.
2. Add sets.lifecycle_state (string/enum) with values: draft, scheduled, performed.
3. Backfill existing rows:
   - jam_session_id present + performed=false => scheduled
   - jam_session_id present + performed=true => performed
4. Keep existing performed boolean initially for compatibility, then decide later whether to fully consolidate.

Files:

1. database/migrations/new_migration_make_set_session_nullable_and_add_lifecycle_state.php
2. app/Models/Set.php

Model updates:

1. Add lifecycle_state to fillable/casts.
2. Add scopes:
   - scopeDraft()
   - scopeScheduled()
   - scopeForSetLibrary(User $user)

### 2) Availability Voting for Planned Sets

Migration:

1. Create set_session_availabilities table:
   - set_id
   - jam_session_id
   - user_id
   - availability (yes|maybe|no)
   - timestamps
2. Unique constraint on set_id + jam_session_id + user_id.

Files:

1. database/migrations/new_migration_create_set_session_availabilities_table.php
2. app/Models/SetSessionAvailability.php
3. app/Models/Set.php (relation)

Relations:

1. Set::sessionAvailabilities()
2. Optional helper for grouped vote totals.

## New Feature Surface: Planned Sets Page

### Routes

Add auth routes:

1. GET /sets/planned -> PlannedSetController@index
2. POST /sets/planned -> PlannedSetController@store
3. PATCH /sets/planned/{set} -> PlannedSetController@update
4. PUT /sets/planned/{set}/availability -> PlannedSetController@updateAvailability
5. POST /sets/planned/{set}/schedule -> PlannedSetController@schedule

Files:

1. routes/web.php

### Controller

Create controller focused on unscheduled set planning.

Files:

1. app/Http/Controllers/PlannedSetController.php

Responsibilities:

1. List draft/planned sets visible to user (respect is_hidden + ownership/collaboration/admin).
2. Create planned set (default visible unless explicitly hidden).
3. Update set metadata and collaborators.
4. Save availability votes via AJAX.
5. Schedule set into selected jam session.

Validation highlights:

1. schedule target must be visible + non-archived session.
2. non-admin restrictions for closed/past sessions should match existing set move behavior in SetController.

### View

Create dedicated page with primary action in header and lightweight, clear cards.

Files:

1. resources/views/sets/planned/index.blade.php
2. resources/views/components/sets/planned-set-card.blade.php
3. resources/views/components/sets/planned-set-availability-modal.blade.php
4. resources/views/components/sets/planned-set-schedule-modal.blade.php

UI behavior:

1. Header right button: Create Planned Set (primary-button).
2. Cards show owner, collaborators, visibility, song/slot summary, vote summary.
3. Availability and scheduling actions in modals.
4. All create/update/vote/schedule actions use fetch/AJAX and update local state without full page reload.

### JS Module

Non-trivial logic must live in JS module.

Files:

1. resources/js/components/plannedSets.js
2. resources/js/app.js (register module)

Responsibilities:

1. Card state store.
2. Modal open/close and payload handling.
3. Availability vote updates + aggregate counts.
4. Scheduling action and card transitions.

## Reframe My Sets to Set Library

### Controller

Refactor MySetsController output into explicit set library groups:

1. Owned sets
2. Collaborating sets
3. Status groups (Draft / Scheduled / Performed)

Move out current dashboard-like responsibility:

1. approval aggregations
2. pending actions queues
3. practice plan group data

Files:

1. app/Http/Controllers/MySetsController.php

### View

Rebuild page semantics to be true set list.

Files:

1. resources/views/my-sets.blade.php
2. optional components in resources/views/components/my-sets/

UX target:

1. Clear grouping by lifecycle state.
2. Lightweight filters (owner/collab/status/search).
3. Direct actions: open, edit, share, schedule (if draft).

## Move Approvals + Requests to Dashboard

### Controller updates

Move data assembly into Dashboard controller/service so first-login priority is visible.

Files:

1. app/Http/Controllers/DashboardController.php
2. optional app/Services/DashboardActionQueueService.php

### View updates

Add concise panels for:

1. Approvals needing user action.
2. Requests user made awaiting others.

Files:

1. resources/views/dashboard.blade.php
2. optional resources/views/components/dashboard/action-queues.blade.php

Dynamic behavior:

1. Fetch-refresh panel or targeted partial updates after action events.
2. Reuse existing event patterns where possible.

## Move Practice Plan to Tools

### New page

Create dedicated Practice Plan page in Tools.

Files:

1. app/Http/Controllers/PracticePlanController.php
2. resources/views/practice-plan/index.blade.php
3. routes/web.php

Data source:

1. Reuse practice-plan query logic currently in MySetsController.

Dashboard linkage:

1. Keep concise dashboard card linking to Practice Plan.

## Navigation Consolidation

### Main nav structure

Update top nav to include grouped menus while keeping Who's Who visible.

Files:

1. resources/views/layouts/navigation.blade.php
2. resources/views/components/nav-link.blade.php (if helper props needed)
3. resources/views/components/responsive-nav-link.blade.php (mobile parity)

Desktop behavior:

1. Sets dropdown: My Sets, Planned Sets.
2. Tools dropdown: Jam Standards, Find a Slot, Practice Plan.
3. Who's Who remains direct link.

Mobile behavior:

1. Mirror same grouping for consistency.
2. Keep labels concise.

## Authorization and Policies

### Set policy

Review SetPolicy for draft visibility + schedule actions.

Files:

1. app/Policies/SetPolicy.php

Expected policy rules:

1. view draft set: allowed if visible or owner/collaborator/admin.
2. update/schedule draft: owner/collaborator/admin as per existing semantics.
3. scheduling to session should also respect session constraints.

## Existing Session Surfaces Compatibility

Session pages should only display scheduled sets tied to that session.

Files to verify/update:

1. app/Http/Controllers/JamSessionController.php
2. resources/views/sessions/partials/set-cards.blade.php
3. resources/views/components/sessions/set-card-shell.blade.php
4. app/Http/Controllers/SetController.php (update path still supports moving between sessions and draft<->scheduled transitions as needed)

## Notifications

Introduce or adapt notifications for:

1. Planned set created (optional, likely only for collaborators).
2. Availability vote changed (usually no push needed initially).
3. Set scheduled to a session (important notify event).

Files:

1. app/Services/NotificationService.php
2. app/Support/NotificationTypeCatalog.php
3. relevant controller methods

## Help Page Update

Add user-friendly sections for:

1. Planned Sets workflow
2. Availability voting
3. Difference between My Sets, Planned Sets, and Practice Plan

File:

1. resources/views/static/help.blade.php

## Rollout Sequence

### Phase 1: Foundation

1. Add schema for lifecycle + nullable jam_session_id.
2. Add Planned Sets page skeleton and routes.
3. Add nav dropdown groups with placeholder links.

### Phase 2: Functional Planned Sets

1. Create/edit planned sets.
2. Visibility and collaborator support.
3. Schedule action into session.

### Phase 3: Availability Voting

1. Add vote model/table/controller endpoints.
2. Add planned set availability modal + live counts.

### Phase 4: IA Restructure

1. Move approvals/requests to dashboard.
2. Extract practice plan page.
3. Refactor My Sets to true set library.

### Phase 5: Content + polish

1. Help page updates.
2. Copy tightening.
3. End-to-end regression passes.

## Testing Plan

Add/adjust focused Pest tests per area.

1. Feature: planned set creation with null jam_session_id.
2. Feature: draft visibility rules for owner/collaborator/other/admin.
3. Feature: schedule planned set into valid session.
4. Feature: disallow invalid session scheduling for non-admin.
5. Feature: availability vote create/update semantics.
6. Feature: dashboard approvals/requests data appears.
7. Feature: my-sets now returns library groups, not action queues.
8. Feature: practice plan page loads and uses expected query constraints.
9. Feature: nav links and grouping visibility.

Likely files:

1. tests/Feature/PlannedSetsTest.php
2. tests/Feature/SetSchedulingTest.php
3. tests/Feature/DashboardActionQueuesTest.php
4. tests/Feature/MySetsLibraryTest.php
5. tests/Feature/PracticePlanTest.php

## Commands and Quality Gates

For each implementation slice:

1. Run targeted tests only for changed area:
   - php artisan test --compact <specific test file/filter>
2. If PHP files changed:
   - vendor/bin/pint --dirty --format agent
3. If frontend behavior changed:
   - npm run build (or npm run dev while iterating)

## Open Product Decisions

1. Label choice: Draft Sets vs Planned Sets.
2. Default visibility for newly created planned sets (recommended visible=true).
3. Availability options: yes/maybe/no vs available/unavailable.
4. Whether collaborators can schedule, or owner/admin only.
5. Whether scheduling auto-notifies all participants/collaborators by default.

## Success Criteria

1. Users can create shareable unscheduled sets.
2. Users can gather collaborators and session availability before booking.
3. My Sets becomes a true set library.
4. Dashboard becomes the first-stop action queue.
5. Practice Plan is discoverable under Tools and from Dashboard.
6. Navigation remains clear and uncluttered while preserving Who's Who as top-level.
