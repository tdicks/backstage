# Project Baseline Rules

## Scope
Applies to all code changes in this repository.

## Architecture and Conventions
- Follow existing nearby code patterns before introducing new structure.
- Reuse existing components, Blade partials, and JS modules when possible.
- Prefer small, incremental changes over broad rewrites.

## Laravel + Blade
- Use Laravel conventions and existing route/controller/service patterns.
- Keep Blade templates declarative and readable.
- Avoid introducing new top-level directories without explicit approval.

## UI Copy
- Keep copy factual, concise, and direct.
- Prefer short labels over long helper text unless clarification is necessary.

## Safety
- Do not add dependencies without explicit approval.
- Do not create destructive migrations/commands without explicit approval.
- Do not create documentation files unless explicitly requested.

## Quality Gates
- Run the smallest relevant test scope.
- Run frontend build checks for frontend changes.
- Run Pint when PHP files are changed.
