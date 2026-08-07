# Set State and Visibility Matrix

## Primary State Axes
- Session state: open vs closed.
- Set state: planned/scheduled/performed.
- Set settings: `signups_open`, `free_for_all`, `song_requests`.
- User role: owner, collaborator, admin, non-manager participant.

## Consistency Requirement
For any action (take/request/recommend/release/edit):
- Backend must enforce eligibility.
- UI must reflect eligibility (show, hide, or disable appropriately).
- Tooltips/labels should communicate current state plainly.

## Menu and Badge Behavior
- Action menus should always represent current role/state permissions.
- Status icons and labels should remain consistent across:
  - Jam session set cards
  - Planned set cards
  - Related filters and summaries

## Regression Coverage
When changing state-driven behavior, include tests for:
- Closed session restrictions.
- Performed set restrictions.
- Combinations of `signups_open`, `free_for_all`, `song_requests`.
- Visibility differences by role.
