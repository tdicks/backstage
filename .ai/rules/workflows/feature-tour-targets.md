# Feature Tour Target Rules

## Stability Rules
- Define tour targets in template markup, not transient JS-generated nodes.
- Keep `data-tour` anchors stable across rerenders and view variants (mobile/desktop).
- Avoid changing target names unless tour config is updated in the same change.

## Naming Rules
- Use clear, area-specific names (for example, `jam-standards-select-parts`).
- Reuse existing target names where semantics are unchanged.

## Validation
After changing any tour target or related rendering path:
- Run `php artisan app:validate-feature-tours-config`.
- Run feature tests for the affected surface.

## Dynamic UI Guidance
- If a view rerenders via Alpine state updates, keep required tour anchors inside the `x-for`/`x-if` markup path.
- Avoid relying on manual DOM insertion for tour-critical elements unless no template alternative exists.
