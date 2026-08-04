# Backstage Agent Instructions

## Instruction Layering

- Treat [AGENTS.md](../AGENTS.md) as the Laravel baseline and always follow it.
- Treat this file as additive project-specific guidance, not a replacement.
- If guidance appears to conflict, prioritize [AGENTS.md](../AGENTS.md) and ask for clarification before proceeding.

## Project Priorities

- Follow existing code patterns in nearby files before introducing new structure.
- Reuse existing components/services/patterns where possible.
- Keep UI copy concise and direct.

## Alpine.js Placement Policy

- Keep Blade Alpine attributes declarative: use `x-data` for config only, plus UI bindings like `x-show`, `x-bind`, and `x-on`.
- Place non-trivial Alpine logic in JavaScript modules under `resources/js/components` and register components in `resources/js/app.js`.
- Non-trivial logic includes branching flows, async requests, payload construction, status/label mapping, filtering/grouping, and reusable state transitions.
- Avoid embedding multi-line objects/functions directly in Blade `x-data` attributes.
- Small inline expressions are allowed only when they are simple UI toggles and do not perform async/network work.

## Design Guidelines

- For new pages, place the primary action in the header, right aligned, using an appropriate hero icon.
- Primary actions should use the primary-button Blade component.
- Panels should use a light slate treatment: `bg-slate-50/95` with `border-slate-200`.
- Prefer the prompt modal component over native browser `prompt()`.

## Quality Gates

- Every code change must be tested with the smallest relevant Pest/Laravel test scope.
- If PHP files change, run `vendor/bin/pint --dirty --format agent`.
- If frontend behavior changes, run `npm run build` (or `npm run dev` when iterating locally).

## Safety Constraints

- Do not change dependencies or introduce new top-level directories without explicit approval.
- Do not create documentation files unless explicitly requested.