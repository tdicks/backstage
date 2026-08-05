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

The application is designed for a non-technical audience, and the UI should be simple, clear, and consistent.

When making design choices, favour simplicity and good UI/UX over busy or flashy designs. Avoid unnecessary complexity, and prioritize clarity and ease of use.

Follow these guidelines:

- For new pages, place the primary action button in the page header, right aligned, with an appropriate hero icon.
- Primary actions should use the primary-button Blade component.
- Secondary actions should use the secondary-button Blade component.
- Modals should use the modal Blade component.
- Panels should use a light slate treatment: `bg-slate-50/95` with `border-slate-200`.
- Prefer the prompt modal component over native browser `prompt()`.

## Dynamic Content

Where possible, page refreshes to show new or updated content should be avoided. Use Alpine.JS to update the page dynamically without a full refresh. This includes updating lists, tables, and other content areas when new data is available.

Any form submissions should be handled via AJAX requests, and the page should be updated dynamically to reflect the changes without a full page reload.

Any interactive elements that require user input should be handled via modals or inline forms, rather than redirecting the user to a new page.

## Quality Gates

- Every code change must be tested with the smallest relevant Pest/Laravel test scope.
- If PHP files change, run `vendor/bin/pint --dirty --format agent`.
- If frontend behavior changes, run `npm run build` (or `npm run dev` when iterating locally).

## Safety Constraints

- Do not change dependencies or introduce new top-level directories without explicit approval.
- Do not create documentation files unless explicitly requested.
- Do not run destructive database migrations or Artisan commands without explicit approval.

## Help Page

There is a help view at resources/views/static/help.blade.php. This view should be updated with any new features or changes to existing features. The page does not need to be comprehensive, and definitely not written with technical language. Instead, it should give a user-friendly overview of the features and how to use them in the app.