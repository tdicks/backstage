# Blade + Alpine Template Rules

## Alpine Placement
- Keep Blade Alpine attributes declarative (`x-data`, `x-show`, `x-bind`, `x-on`).
- Keep non-trivial state and async logic in `resources/js/components` modules.

## Rendering Policy (Default)
- Render interactive UI from Alpine state using template markup (`x-for`, `x-if`, `x-show`).
- Avoid manual DOM construction (`createElement`, `replaceChildren`, `innerHTML`) unless there is a proven technical reason.

## Acceptable Imperative Exceptions
- Low-level platform APIs (selection/range, clipboard fallback).
- SVG/path drawing or geometry-heavy overlays.
- Third-party integration boundaries where template rendering is not practical.

## Practical Rules
- Keep one source of truth for displayed collections in Alpine state.
- When async fetches return data, mutate state and let templates rerender.
- Preserve stable attributes (accessibility, test hooks, tour hooks) in template markup.
- Avoid embedding multi-line functions/objects directly in Blade `x-data`.

## Refactor Guidance
- Prefer phased migrations (one surface at a time) with tests after each phase.
- If converting imperative rendering, move behavior helpers into JS methods and keep markup in Blade templates.
