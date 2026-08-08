# Testing and Validation Workflow

## Core Requirement
Every change must be programmatically validated with the smallest relevant scope.

## Baseline Commands
- PHP tests (targeted): `php artisan test --compact <target>`
- Frontend build: `npm run build`
- Pint (when PHP changed): `vendor/bin/pint --dirty --format agent`

## Tour-Aware Changes
If selectors, `data-tour` attributes, or tour flows are touched:
- Run: `php artisan app:validate-feature-tours-config`

## Suggested Area Test Matrix
- Jam Standards UI/behavior:
  - `php artisan test --compact tests/Feature/JamStandardsTest.php`
- Session cards/menus/visibility:
  - `php artisan test --compact tests/Feature/SessionCardMenusTest.php`
  - `php artisan test --compact tests/Feature/SessionSetsLoadingTest.php`
- Planned Sets rendering/workflow:
  - `php artisan test --compact tests/Feature/PlannedSetsTest.php`
- Slot Finder rendering/workflow:
  - `php artisan test --compact tests/Feature/SlotFinderTest.php`

## Refactor Safety
- Prefer incremental edits with test/build checkpoints after each step.
- Preserve existing behavior unless the task explicitly changes behavior.
