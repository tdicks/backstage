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
- Render interactive UI from Alpine state and template markup (`x-for`, `x-if`, `x-show`) by default; avoid manual DOM construction (`createElement`, `innerHTML`, `replaceChildren`) unless there is a proven technical need.
- Acceptable exceptions for imperative DOM are low-level platform concerns such as selection/range APIs, SVG/path drawing, clipboard fallbacks, or third-party integration boundaries where Alpine templates are not practical.
- Avoid embedding multi-line objects/functions directly in Blade `x-data` attributes.
- Small inline expressions are allowed only when they are simple UI toggles and do not perform async/network work.
- When working on the codebase, be vigilant of legacy Alpine code that may not follow these rules. If you see a violation, flag it for review and refactoring.

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

### Iconography and Colour Usage

- Icons should be used in favour of excessive labels, and must be used consistently.
- Use the heroicon set for all icons, and use the same icon for the same action throughout the application.

Colours have meaning in the application, and should also be used consistently:

- Amber means something that requires the user to take action, or something they could take action on.
- Emerald means a successful or completed state, typically a confirmation of an action a user has taken.
- Dark emerald explicitly means something related to a live jam.
- Sky blue means a state that is specific to the current user, such as a slot assigned to them.
- Red means an error or a state that requires a user to take action to resolve, or that the user should be aware of.
- Purple provides a visual cue for something that the user might like to know about a given state.

### Style Guide

There is a fairly standard icon and colour guide for certain elements throughout the application.

For slots:

- Open slots should be amber.
- Slots assigned to the current user should be sky blue.
- Slots assigned to other users should be emerald green.
- Slots that are claimable should have a purple "CLAIMABLE" badge.
- Slots which are impossible (e.g. a user is assigned to a slot but is not attending the session) should be red.

Sets:

Sets have the following standard icons, which should be included anywhere there is a set card:

- Free for all mode: heroicon-m-fire
- Hidden mode: heroicon-m-eye-slash in sky blue. The set's border should be sky blue, with a blue inset shadow.
- Accepting sign ups: heroicon-m-padlock-open in emerald green.
- Not accepting sign ups: heroicon-m-padlock-closed in amber.

Jam Sessions:

- Hidden sessions should use the heroicon-m-eye-slash icon in sky blue.
- Live sessions should use the custom animated live-status-icon component.
- Locked / closed sessions should use the heroicon-m-lock-closed icon, coloured in amber.
- Archived sessions should use the heroicon-m-archive icon, coloured in brown.

All model types:

- Attachments should use a heroicon-s-paper-clip icon. Visibility is dependent on context. If the given model has attachments, the icon should be filled. If there are no attachments, the icon should have a reduced opacity. If there are no explicit action menu items to allow attachment management, the icon should be present with a reduced opacity, and allow the user to manage attachments directly.

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