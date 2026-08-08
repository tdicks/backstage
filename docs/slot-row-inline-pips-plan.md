# Slot Row Inline Pip + Popover Plan

## Goal

Apply the Planned Sets inline slot pip and popover pattern to Jam Session slot rows while preserving current behavior and permissions.

## Scope

- Replace current slot-row action trigger emphasis with pip-first interaction.
- Keep existing slot actions and linked modals.
- Keep existing approval/request/proposal flows.
- Keep admin operations and admin styling behavior.
- Exclude share-link behavior.

## Current References

- Planned Sets implementation:
  - resources/views/sets/planned/index.blade.php
  - resources/js/components/plannedSets.js
- Jam Session slot system:
  - resources/views/components/sessions/slot-row.blade.php
  - resources/views/components/sessions/slot-action-menu.blade.php
  - resources/views/components/sessions/slot-propose-modal.blade.php
  - resources/js/components/sessionCards.js

## UX Target

- Slot pill remains always visible and readable.
- Slot pill includes an internal assignee badge:
  - Amber: open
  - Emerald: assigned to someone else
  - Sky: assigned to current user (label: You)
- Clicking the pill opens an inline popover near the pill.
- Popover options remain context-dependent exactly as today.
- Popover copy uses Open for unassigned slots.

## Implementation Steps

1. Extract shared presentation tokens
- Add reusable class constants/helpers for assignee badge colors and pip layout.
- Keep naming aligned between plannedSets.js and sessionCards.js.

2. Introduce inline popover state in session slot row
- Add active/open popover state per slot row in sessionCards.js.
- Add outside-click and escape handling consistent with current menu behavior.

3. Move action surface into popover content
- Reuse existing condition checks from slot-action-menu.blade.php.
- Reuse existing handlers (take/request/recommend/release/claimable/edit/clear).
- Keep admin shield icon and sky variant classes for admin-managing-other-set operations.

4. Keep existing modals wired
- Continue using propose/edit/attachments modals from current components.
- Ensure popover can launch existing modals without regressions.

5. Accessibility parity
- Keyboard open/close on Enter/Space/Escape.
- Maintain aria-expanded and descriptive titles.
- Preserve focus ring behavior and disabled-state clarity.

6. Regression checks
- Verify all slot states:
  - open
  - assigned to self
  - assigned to other
  - claimable
  - assigned user not attending
- Verify set modes:
  - free for all
  - standard sign-up
  - set performed/locked
  - session closed
- Verify role modes:
  - owner
  - collaborator
  - regular user
  - admin managing other set

## Suggested Test Additions

- Feature/UI behavior assertions for context-dependent action visibility per state.
- Existing slot action endpoint tests remain source of truth for backend behavior.

## Rollout Notes

- Implement in a single pass for slot-row view + sessionCards.js to avoid mixed interaction patterns.
- Keep current action menu component until replacement is fully verified, then remove dead code.