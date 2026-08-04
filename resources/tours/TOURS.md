# Feature Tours Guide

This document explains how the feature tour system works in this app and how to create new tours from scratch using YAML.

Audience: you already know HTML and YAML, but you are new to this codebase.

## What the system does

Feature tours are guided, in-app walkthroughs that highlight UI targets and show step-by-step text.

The system supports:

- Route-aware tour activation
- Desktop/mobile variants via media queries
- Trigger modes:
  - Automatic on page load
  - Prompt on page load
  - Button trigger
  - Info-icon trigger
  - Info-icon-always trigger
  - Navigation info-icon resume trigger
- Optional pre-step actions (for example: open a menu before targeting an item inside it)
- Per-user completion and prompt-dismissal tracking in the database
- Per-user full opt-out state
- Validation via an Artisan command

## Where things live

- YAML config sources:
  - resources/tours/*.yaml
- Load order:
  - Files are read in filename order
  - Later files override earlier keys when the same key appears more than once
- Server-side loader and normalizer:
  - app/Support/FeatureTourConfig.php
- Frontend runtime:
  - resources/js/components/featureTour.js
- State update endpoint:
  - app/Http/Controllers/FeatureTourStateController.php
- Validator command:
  - app/Console/Commands/ValidateFeatureToursConfig.php

## High-level flow

1. YAML files are loaded in filename order, merged, and normalized on the server.
2. Normalized config is embedded into page HTML as JSON.
3. Frontend runtime reads JSON and determines which tours are eligible for the current route/device/user state.
4. Trigger logic decides whether to start automatically, show a prompt, or wait for a button event.
5. A runner renders spotlight UI and panel text step-by-step.
6. Completion/prompt-dismissal state is persisted to the authenticated user record via API.

UI behavior highlights:

- The pointer line/arrow points to the edge of the highlighted rectangle, not the center of the target element.
- The highlighted rectangle interior is not dimmed; only the surrounding area is dimmed.

## YAML schema reference

The root structure is:

```yaml
version: 1
anchors: {}
actions: {}
tours: {}
```

## Top-level keys

### version

- Type: integer (recommended)
- Purpose: config version marker
- Default in payload when missing: 1
- Validator behavior: warns if not numeric

### anchors

- Type: map<string, string>
- Purpose: reusable selector aliases
- Value should be a CSS selector string

Example:

```yaml
anchors:
  profile-menu-trigger: '[data-tour="profile-menu-trigger"]'
  profile-link-desktop: '[data-tour="profile-link-desktop"]'
```

How anchors are resolved:

- Any target-like field may use either:
  - An anchor key (recommended), or
  - A raw CSS selector
- A value is treated as a raw selector only if it starts with one of:
  - [
  - .
  - #
- Otherwise it is treated as an anchor key and looked up in anchors.
- When the runtime looks up a selector in the DOM, it uses the first matching element in document order.
- If the same `data-tour` appears on multiple elements, the first match is the one the tour will target.

## actions

- Type: map<string, action>
- Purpose: reusable pre-step behaviors, typically for revealing hidden targets

Supported action types:

### ensure-visible

Tries to make an element visible by clicking a trigger repeatedly until a target becomes visible or attempts are exhausted.

```yaml
actions:
  open-mobile-menu:
    type: ensure-visible
    target: mobile-menu-toggle
    until_visible: jam-sessions-mobile
    wait_ms: 180
    max_attempts: 3
    click_count: 1
```

Fields:

- type: required, must be ensure-visible
- target: selector or anchor key to click
- until_visible: selector or anchor key that must become visible
- wait_ms: delay between attempts (default 120)
- max_attempts: number of attempts (default 1, minimum 1)
- click_count: clicks per attempt (default 1, minimum 1)

### click

Clicks a target a fixed number of times, then waits.

```yaml
actions:
  open-profile-menu:
    type: click
    target: profile-menu-trigger
    click_count: 1
    wait_ms: 120
```

Fields:

- type: required, must be click
- target: selector or anchor key to click
- click_count: clicks to perform (default 1, minimum 1)
- wait_ms: wait after clicking (default 120)

### set-checked

Ensures a checkbox-like input is in a specific checked state without blindly toggling it.

```yaml
actions:
  select-first-song:
    type: set-checked
    target: '[data-tour="jam-standards-songs-list"] > tbody > tr:first-child > td > input[type="checkbox"]'
    checked: true
    wait_ms: 120
```

Fields:

- type: required, must be set-checked
- target: selector or anchor key that resolves to an `input[type="checkbox"]` or other checkable input
- checked: optional boolean, defaults to true
- wait_ms: wait after changing the checked state (default 120)

Notes:

- This action only clicks when the input is not already in the requested state.
- Setting a radio input to `checked: false` is ignored because radios cannot be directly unchecked with a simple click.

Notes:

- Unsupported action type values are validation errors.
- Missing or non-resolvable action targets cause no runtime action.

## tours

- Type: map<string, tour>
- Key is tourId
- Each tour must have at least one variant containing at least one valid step

Minimal shape:

```yaml
tours:
  onboarding:
    once_key: onboarding-v1
    authenticated: true
    priority: 100
    trigger:
      mode: auto
    routes:
      - dashboard
    variants:
      desktop:
        media_query: '(min-width: 640px)'
        steps:
          - title: 'Welcome'
            body: 'Tour intro text.'
            target: some-anchor
```

When multiple YAML files define the same key, the value from the later filename wins.

### tour fields

### once_key

- Type: string
- Default: tourId
- Purpose: stable per-tour state key in `feature_tour_state`
- Allows versioning/reset behavior by changing key

### authenticated

- Type: boolean
- Default: true
- If true, tour requires authenticated user

### priority

- Type: integer
- Default: 100
- Lower number = higher priority
- Used when selecting first eligible auto/prompt/button tour bindings

### routes

- Type: list<string>
- Optional
- Empty list means all routes
- Supports wildcard with *

Examples:

- dashboard matches exactly dashboard
- sessions.* matches sessions.index, sessions.show, etc.

### trigger

- Type: object
- Optional; defaults to mode auto

Supported modes:

#### mode: auto

Starts immediately on page load (first eligible auto tour by priority).

```yaml
trigger:
  mode: auto
```

#### mode: prompt

Shows a prompt modal on page load before starting.

```yaml
trigger:
  mode: prompt
  show_info_icon: true
  prompt:
    title: 'Take a quick tour?'
    question: 'Would you like a quick walkthrough of key navigation features?'
    confirm_label: 'Start tour'
    cancel_label: 'Not now'
```

Prompt field defaults:

- title: Take a quick tour?
- question: Would you like a quick feature tour?
- resume_hint: No problem, click the info icon in the navigation bar when you are ready to start the tour.
- confirm_label: Start tour
- cancel_label: Not now
- opt_out_label: Not interested

Behavior details:

- If user declines prompt, prompt-dismissed state is persisted to the user record.
- If user selects Not interested, opted-out state is persisted and automatic/prompt starts are blocked.
- Prompt is not shown again unless:
  - prompt-dismissal state is cleared, or
  - tour is started manually and prompt-dismissal state is cleared by start.
- You can keep the navigation info icon visible while a prompt tour is still eligible by setting:
  - trigger.show_info_icon: true
  - Useful when a later step targets the info icon itself.

#### mode: button

Waits for a configured DOM event on a target element.

```yaml
trigger:
  mode: button
  button:
    target: start-tour-button
    event: click
```

Button fields:

- target: required; selector or anchor key
- event: optional; default click

Notes:

- Event handling is delegated at document level.
- Route and auth eligibility are still enforced when the event fires.

### variants

- Type: map<string, variant>
- Required

Variant fields:

- media_query: CSS media query string
  - Default if omitted: (min-width: 0px)
- steps: list<step>

Variant keys are just labels. If a tour only needs one version, you can define a single variant such as `default` or `desktop` and omit a separate mobile block.

Example with one variant:

```yaml
variants:
  default:
    steps:
      - title: 'Welcome'
        body: 'This tour uses one layout on all screen sizes.'
        target: some-anchor
```

Variant selection behavior:

- Variants are evaluated in YAML order.
- First variant with matching media_query and at least one valid step is used.

### steps

Each step supports:

- title: required, non-empty string
- body: required, non-empty string
- target: optional selector or anchor key (omit for untargeted modal-only steps)
- before: optional list of action names
- after: optional list of action names
- next: optional list of action names
- back: optional list of action names

### Inline icons in title/body text

You can render inline icons in step `title` and `body` strings using icon tokens:

- Syntax: `[icon:name]`
- Unknown names are shown as plain text and are not rendered as icons.
- This is safely tokenized; HTML is not interpreted.

Supported icon names:

- `free-for-all` → fire icon (`heroicon-m-fire`)
- `hidden` → eye-slash icon (`heroicon-m-eye-slash`)
- `public` → globe icon (`heroicon-m-globe-alt`)
- `private` → lock-closed icon (`heroicon-m-lock-closed`)
- `locked` → lock-closed icon (`heroicon-m-lock-closed`)
- `visible` → eye icon (`heroicon-m-eye`)
- `info` → information-circle icon (`heroicon-m-information-circle`)
- `warning` → exclamation-triangle icon (`heroicon-m-exclamation-triangle`)

Example:

```yaml
steps:
  - title: '[icon:info] About This Section'
    body: 'This session is [icon:free-for-all] by default, but can be [icon:hidden] from non-members.'
```

Example:

```yaml
steps:
  - title: 'Complete Your Profile'
    body: 'Open your profile and fill out the missing details.'
    before:
      - open-desktop-profile-menu
    target: profile-link-desktop
```

Runtime behavior:

- All before actions run in sequence before each step renders.
- All after actions run in sequence after the step has finished its initial scroll/panel animation.
- After actions run once per visit to that step, then the step re-renders so highlights and positioning can refresh against any DOM changes.
- All next actions run when the user presses Next, and also run for the final step when the Done button is pressed.
- All back actions run when the user presses Back, before the reverse transition logic executes.
- If a step target is missing or not visible after actions, the runner retries a few times, then skips to the next step.
- A missing target does not stop the tour from starting; it only affects that step.
- If no more valid/visible steps remain, tour finishes.

## Triggering tours from HTML

There are two button-trigger paths.

### 1) YAML-managed button trigger

Use trigger.mode: button in YAML and a button.target selector/anchor.

This is best when you want all trigger wiring centralized in the YAML file.

### 2) Generic data attributes (no YAML trigger wiring required)

You can start tours directly from any clickable element:

```html
<button type="button" data-feature-tour-start="onboarding">
  Start tour
</button>
```

Optional force replay:

```html
<button type="button" data-feature-tour-start="onboarding" data-feature-tour-force="1">
  Replay tour
</button>
```

This is best for ad-hoc entry points (help menus, profile pages, onboarding cards).

### 3) Feature tour launcher (used by nav info icon)

Start the highest-priority eligible info-icon tour directly. If no info-icon tour is eligible, the launcher falls back to the highest-priority eligible prompt tour:

```html
<button type="button" data-feature-tour-start-first-prompt>
  Start feature tour
</button>
```

Optional force replay:

```html
<button type="button" data-feature-tour-start-first-prompt data-feature-tour-force="1">
  Replay feature tour
</button>
```

### mode: info-icon

Uses the nav info icon as the trigger target and shows that icon by default when an eligible info-icon tour exists.

```yaml
trigger:
  mode: info-icon
```

This is best for optional page walkthroughs where you want to offer the tour without auto-starting it or attaching it to a page-specific button.

### mode: info-icon-always

Uses the nav info icon as the trigger target and keeps that icon available for the tour even after completion or opt-out state has been saved.

```yaml
trigger:
  mode: info-icon-always
```

## JavaScript API

Global API is exposed as window.featureTour.

Methods:

- window.featureTour.start(tourId)
  - Starts tour if eligible and not completed
- window.featureTour.replay(tourId)
  - Starts tour with force=true (ignores completion state)
- window.featureTour.startFirstInfoIcon(options?)
  - Starts the first eligible info-icon tour, honoring priority
- window.featureTour.startFirstPrompt(options?)
  - Starts the first eligible prompt tour, honoring priority

Runtime note:

- Frontend updates UI state immediately in memory and persists state to the backend asynchronously.

## Database-backed user state

State is stored on the authenticated user in `users.feature_tour_state`.

Shape:

```json
{
  "completed": {
    "onboarding-v1": true
  },
  "prompt_dismissed": {
    "onboarding-v1": true
  },
  "opted_out": {
    "onboarding-v1": true
  }
}
```

`once_key` is the map key for both buckets.

Tip: changing `once_key` effectively creates a new state identity and resets tour completion/prompt-dismissal behavior.

## State update endpoint

Route:

- `POST /feature-tours/state`
- Route name: `feature-tours.state.update`
- Auth required: yes

Request payload:

```json
{
  "once_key": "onboarding-v1",
  "action": "complete"
}
```

Supported `action` values:

- `complete`
- `dismiss_prompt`
- `clear_prompt_dismissal`
- `opt_out`
- `clear_opt_out`

Response:

- `200 OK` JSON with normalized `state` object.

## Validator command

Command:

```bash
php artisan app:validate-feature-tours-config
```

Optional merged-config dump:

```bash
php artisan app:validate-feature-tours-config --dump-config
```

Strict mode:

```bash
php artisan app:validate-feature-tours-config --strict
```

What it checks:

- All YAML files under resources/tours, read in filename order
- Merge precedence for duplicate keys, where later files win
- Action entries are keyed objects
- Action type is one of ensure-visible or click
- Action target/until_visible anchor references exist
- Tours are keyed objects and contain valid variants/steps
- Trigger mode is one of auto, prompt, button, info-icon, info-icon-always
- Prompt trigger includes non-empty prompt.question
- Button trigger includes button.target and valid anchor reference
- Info-icon and info-icon-always triggers use the nav info icon and do not require a button.target
- Step target anchor references exist
- Step before/after/next/back references known action names

Exit behavior:

- Non-zero exit when errors are found
- With --strict, warnings also fail the command
- With --dump-config, the merged config that the app loads is printed to the console

CI recommendation:

- Add this command to CI before build/test steps.

## Reset state command (admin/debug)

Clear one tour key for one user:

```bash
php artisan app:reset-feature-tour-state <user-id-or-email> <once_key>
```

Clear all tour state for one user:

```bash
php artisan app:reset-feature-tour-state <user-id-or-email> --all
```

## Build and test after config changes

Recommended local workflow after editing YAML:

1. Run validator:

```bash
php artisan app:validate-feature-tours-config
```

2. Build frontend:

```bash
npm run build
```

3. Run relevant tests:

```bash
php artisan test --compact
```

## Authoring checklist

Use this checklist when adding a new tour.

1. Add or confirm stable HTML selectors (prefer data-tour attributes).
2. Define anchors for those selectors.
3. Add reusable actions for any hidden/collapsed UI that needs opening.
4. Create tour with:
   - once_key
   - authenticated
   - priority
   - routes
   - trigger mode and trigger options
   - variants and steps
5. Validate with Artisan command.
6. Verify manually on desktop/mobile variants.
7. Build and run tests.

## Example: complete tour definition

```yaml
version: 1

anchors:
  help-menu-button: '[data-tour="help-menu-button"]'
  help-menu-item: '[data-tour="help-menu-item"]'
  nav-sessions: '[data-tour="jam-sessions-desktop"]'

actions:
  open-help-menu:
    type: ensure-visible
    target: help-menu-button
    until_visible: help-menu-item
    wait_ms: 150
    max_attempts: 3

tours:
  help-tour:
    once_key: help-tour-v1
    authenticated: true
    priority: 20
    trigger:
      mode: button
      button:
        target: help-menu-item
        event: click
    routes:
      - dashboard
      - sessions.*
    variants:
      desktop:
        media_query: '(min-width: 640px)'
        steps:
          - title: 'Sessions'
            body: 'This is where upcoming sessions are listed.'
            target: nav-sessions
          - title: 'Help'
            body: 'Open help any time from here.'
            before:
              - open-help-menu
            target: help-menu-item
      mobile:
        media_query: '(max-width: 639px)'
        steps:
          - title: 'Sessions'
            body: 'Use this area to browse upcoming sessions.'
            target: nav-sessions
```

## Practical guidance

- Prefer anchor keys over raw selectors for maintainability.
- Keep selectors stable using dedicated data-tour attributes.
- Use actions for hidden targets instead of brittle timing hacks.
- Use priority to control which tour gets first chance on shared routes.
- Use prompt mode for optional onboarding and auto mode for required guidance.
- Use data-feature-tour-start for easy manual entry points.
