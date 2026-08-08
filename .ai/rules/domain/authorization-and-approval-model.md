# Authorization and Approval Model

## Role Model
- Owner: full authority for the set and child objects.
- Collaborator: deputy authority for child objects (songs, slots, requests, recommendations), not set identity/settings ownership.
- Admin: implicit global authority, but not treated as set manager unless owner/collaborator.

## Approval Workflow Expectations
- Avoid redundant approval hops when actor has implicit authority.
- Keep backend guard rules and menu visibility in sync.
- Keep approval messaging clear and role-accurate (for example, "set managers").

## Song Requests and Recommendations
- Non-managers may recommend when signups are open and session is not closed.
- Collaborators should process requests on collaborated sets, not submit song requests on those sets.
- Closed/performed restrictions must be enforced server-side and reflected in UI controls.

## Implementation Rule
Any permission change should update:
1. Controller/service guard behavior.
2. Menu/action visibility conditions.
3. Relevant feature tests.
