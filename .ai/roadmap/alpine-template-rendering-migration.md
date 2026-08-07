# Alpine Template Rendering Migration

## Context
We introduced an Alpine template-first rendering policy and completed the first concrete migration in Jam Standards. This note tracks what changed, what remains, and a suggested sequence for follow-up work.

## Completed Work (Jam Standards)
- Replaced imperative song-list rendering in the Jam Standards catalog with Alpine state-driven templates.
- Removed manual DOM construction for catalog songs/rows/cards/pagination in the catalog component.
- Kept search/filter/pagination asynchronous behavior, but shifted UI updates to state mutation + template rerender.
- Preserved feature-tour hooks by ensuring tour targets are defined in Blade template markup.
- Added/updated targeted regression coverage and revalidated feature-tour config.
- Updated project AI rules to prefer Alpine template rendering over imperative DOM updates unless technically necessary.

## Why This Matters
- Reduces drift between server-rendered and client-rendered markup.
- Makes accessibility/tour attributes less fragile across rerenders.
- Improves maintainability by centralizing UI structure in Blade templates.

## Follow-up Candidates
### High-priority migration candidates
- resources/js/components/lazySessionSets.js
- resources/js/components/sessionCards.js
- resources/js/components/dashboardActionQueues.js

### Likely acceptable imperative exceptions
- resources/js/components/featureTour/manager.js
- resources/js/components/featureTour/prompt.js
- resources/js/components/featureTour/runner.js
- resources/js/components/featureTour/richText.js
- resources/js/utils/clipboard.js

## Suggested Next Steps
1. Migrate lazySessionSets to Alpine template rendering in small, test-backed increments.
2. Migrate sessionCards in a similar phased approach.
3. Review dashboardActionQueues and determine whether a full template migration is worth complexity tradeoffs.
4. Leave feature-tour and clipboard internals imperative unless a clear reliability issue appears.

## Validation Checklist For Each Migration
- Run smallest relevant feature tests for affected surface.
- Run npm run build.
- Run php artisan app:validate-feature-tours-config when tour targets are affected.
- Run Pint when PHP files are touched.

## Open Questions
- Should we define a stricter allowlist of files/components where imperative DOM is permitted?
- Do we want a reusable internal pattern for Alpine template pagination/search blocks to speed up future migrations?
