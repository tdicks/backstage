# Rewrite Blueprint

## Purpose

This repository now acts as a validated proof of concept for the Backstage product.
It contains working domain behavior, edge-case handling, and interaction semantics that should be treated as the reference implementation during any future rewrite.

This document describes a pragmatic greenfield rewrite strategy that reduces architectural drag while preserving the hard-won behavior already established here.

## Goals

1. Build a new codebase with a cleaner interactive architecture.
2. Preserve current domain behavior and UX rules that are already working.
3. Avoid a risky big-bang migration.
4. Use this repository as the parity reference until the new system is proven.

## Non-Goals

1. Reproduce every implementation detail from this repository.
2. Preserve the current frontend event model.
3. Switch all users and all features in one cutover.

## Why Rewrite Instead Of Refactor

The current codebase has already validated important product behavior, but interactive state is spread across Blade, Alpine, server-rendered partials, custom browser events, and targeted refresh logic. That is workable, but it raises the cost of further growth.

A rewrite is justified if the team wants:

1. A more explicit component/state model.
2. Fewer DOM replacement edge cases.
3. Cleaner boundaries between domain logic and UI behavior.
4. Better long-term ergonomics for rich interactions.

## Recommended Product Reference Rule

Until the new system reaches parity, this repository is the behavioral source of truth.

If the new system disagrees with this repository, assume the current app is correct unless a product decision explicitly changes the behavior.

## Architecture Options

### Option A: Livewire-First

This document is not written as a human narrative plan. It is written as an implementation brief for an AI coding agent that will perform most or all of the rewrite work, with humans acting primarily as reviewers, product decision-makers, and release approvers.

An AI agent using this document should treat it as:

1. a product-behavior specification,
2. an architecture decision frame,
3. a migration playbook,
4. a parity checklist,
5. a set of delivery constraints.

Humans are expected to oversee, validate, and redirect. They are not expected to hand-author most implementation code.
Best for:

1. Teams that want to stay strongly server-driven.
2. Faster reuse of Laravel conventions.
3. Lower frontend build complexity.

Pros:

1. Close alignment with Laravel authorization, validation, and Blade.
2. Fewer hand-written fetch/event flows.
3. Easier incremental adoption for Laravel-heavy teams.

Cons:

1. Complex interactive UIs can still become stateful and subtle.
2. Requires careful keying/morphing discipline.
3. Real-time rich interactions may still need client-side escape hatches.

### Option B: Vue-First

Use Laravel as backend/API and Vue as the primary client interaction layer.

Best for:

1. Teams that want explicit client state management.
2. Rich, nested, interactive UI surfaces.
3. More predictable component lifecycle behavior.

Pros:

1. Clear state ownership.
2. Better component reuse across complex screens.
3. Easier to reason about updates than mixed partial rerenders.

Cons:

1. More frontend engineering overhead.
2. Requires deliberate API boundary design.
3. Larger rewrite cost up front.

### Option C: Hybrid Livewire + Vue

Use Livewire for admin/forms/list management and Vue for high-interaction surfaces like sessions, sets, songs, and slot assignment.

Best for:

1. Teams that want Laravel speed for standard CRUD.
2. Teams willing to keep strict boundaries.

Pros:

1. Good fit for mixed UI complexity.
2. Lets the most complex screens get the strongest client model.

Cons:

1. Can become confusing if ownership is not strict.
2. Requires discipline around what lives in Livewire vs Vue.

## Recommendation

If the rewrite is serious and long-lived, prefer Vue-first for the sessions/sets/slots experience, with Laravel remaining the domain and API backend.

If the team wants lower disruption and stronger Laravel continuity, prefer a hybrid model:

1. Vue for session boards, slot interactions, live set state, and approvals.
2. Livewire or Blade for simpler CRUD/admin screens.

Avoid mixing Livewire and Vue within the same highly interactive surface unless ownership is extremely clear.

## Domain Behaviors That Must Be Preserved

The rewrite must reproduce at least these behaviors before cutover:

1. Slot conflict rules are enforced in both UI and backend.
2. Song request approval supports multiple requester slots when conflict rules allow it.
3. Requested slots are limited to slots the requester said they can cover.
4. Band template behavior is source-aware:
   manual request can choose template on approval,
   catalog request inherits template from catalog song.
5. Requested slots outside the chosen band template are clearly indicated.
6. Targeted refresh behavior does not collapse unrelated UI.
7. Role-specific action menus respect owner, collaborator, admin, and performer semantics.
8. Claimable slot behavior reflects manual claimability and dropout-derived claimability.
9. Attendance changes that affect slot availability are reflected consistently.
10. Conflict-driven reassignment updates all affected UI state.

## High-Risk Areas

These are the surfaces most likely to hide parity bugs:

1. Session board refresh behavior.
2. Slot assignment side effects across multiple slots.
3. Approval queues and counters.
4. Role-aware menu visibility and disabled states.
5. Band template selection and request-source rules.
6. Open/closed UI state persistence for sets, songs, and request panels.

## Rewrite Strategy

### Phase 1: Freeze Behavior

Before building new UI, record the current expected behavior.

Deliverables:
Avoid broadly mixing Livewire and Vue inside the same dense interactive surface unless a human explicitly defines strict boundaries.
1. A parity checklist for each critical flow.
2. A list of canonical product rules extracted from current implementation.
3. Additional tests for any still-implicit behavior.

Suggested focus flows:

1. Take Slot with and without conflict side effects.
2. Request Slot and approval outcomes.
3. Song request approval with multiple slots.
4. Manual vs catalog template source handling.
5. Admin acting on another user's set.
6. Refresh behavior while sets and songs are open.

### Phase 2: Stabilize Backend Contract

Keep the existing backend domain logic or extract it behind clean service boundaries before major UI work.

Deliverables:

1. Clear service/API contract for slot actions.
2. Consistent response payloads for actions that affect multiple rows.
3. Stable authorization rules.
4. Stable validation and domain error shape.

### Phase 3: Build New Sessions Vertical Slice

Start with the highest-risk/highest-value feature area:

1. Jam session page shell.
2. Set cards.
3. Song cards.
4. Slot rows and menus.
5. Approval indicators and request flows.

Reason:

This is the area where current architecture is doing the most work and where rewrite value is highest.

### Phase 4: Parity Hardening

Before expanding scope, prove parity on the sessions vertical slice.

Exit criteria:

1. All critical slot flows behave correctly.
2. Conflict-driven side effects render correctly.
3. No set-collapse/refresh regressions.
4. Menu semantics match expected roles.

### Phase 5: Expand To Supporting Areas

Once sessions are stable, move outward:

1. My Sets approvals.
2. Jam standards / catalog integration.
3. Admin utilities.
4. Profile / slot-coverage management.
5. Notifications and attendance views.

## Suggested New-System Boundaries

### Backend Responsibilities

1. Authorization.
2. Validation.
3. Slot conflict enforcement.
4. Side-effect orchestration.
5. Canonical state transitions.
6. API responses describing all affected entities.

### Frontend Responsibilities

1. Rendering set/song/slot state.
2. Local interaction state.
3. Optimistic or staged interaction feedback.
4. Applying server-returned affected-entity updates.
5. Preserving open/closed UI state without DOM-replacement hacks.

## API Shape Guidance

Avoid returning only a single changed slot when one action can affect multiple items.

Prefer returning explicit affected state, for example:

1. updated slot
2. replaced/conflicting slot
3. affected song summary
4. pending approval counts if changed
5. set-level aggregates if changed

That will let the new frontend update surgically without broad rerender fallbacks.

## Migration Plan

### Recommended Delivery Model

Run both systems in parallel during migration.

1. Current app remains production reference.
2. New app is developed feature-by-feature.
3. Specific routes or user cohorts move over only after parity is proven.

### Cutover Options

1. Route-by-route cutover.
2. Role-based cutover for internal/admin users first.
3. Feature-flag cutover for selected session pages.

Prefer feature-flag or route-based cutover over all-at-once migration.

## Testing Strategy For Rewrite

### Keep Existing Repository As Oracle

For each critical user flow:

1. document expected behavior,
2. keep backend tests here green,
3. replicate the same behavior in the new system.

### New-System Test Layers

1. Domain/service tests for slot conflict and assignment transitions.
2. API tests for response shapes and permissions.
3. UI interaction tests for menu visibility, request/approval flows, and refresh-safe open state.
4. End-to-end tests for critical session workflows.

### Minimum Parity Checklist

The rewrite should not ship the session board until all of the following are verified:

1. Take Slot updates affected slots correctly.
2. Edit Slot reflects conflict-driven moves.
3. Request Slot visibility rules match roles and set state.
4. Song request approval supports the current template/slot rules.
5. Open sets stay open during relevant updates.
6. Admin-on-other-user-set styling and permissions are preserved.

## Data Migration Guidance

If domain tables can be reused, prefer reusing them rather than migrating business data into a new schema without clear benefit.

Only redesign persistence if the current schema materially blocks future work.

If schema changes are necessary:

1. define compatibility mapping early,
2. create repeatable migration scripts,
3. dry-run migration against realistic data,
4. keep rollback path simple.

## What To Reuse From This Repository

Reuse:

1. Domain rules.
2. Authorization semantics.
3. Test cases and scenarios.
4. Catalog/template behavior.
5. Proven wording for nuanced UI states where it is already clear.

Do not automatically reuse:

1. Frontend event plumbing.
2. Refresh orchestration patterns.
3. DOM replacement strategies.

## First Slice Recommendation

If starting now, build this first in the new system:

1. Session page shell.
2. One set card.
3. One song card.
4. Slot rows.
5. Take Slot / Release Slot / Edit Slot.
6. Conflict-aware updates.

If this slice works well, the rewrite approach is probably viable.

If it becomes difficult to model cleanly, that is useful signal before the team commits to rewriting the full app.

## Decision Summary

If the team expects the interactive sessions experience to remain the product core, a fresh implementation is defensible and likely beneficial.

The key is to treat this repository as the proven reference, not as throwaway code.

The safest path is:

1. stabilize behavior here,
2. document parity expectations,
3. rewrite by vertical slices,
4. cut over only when the new implementation is measurably better and behaviorally complete.