# Dashboard Widget System

## Purpose
This document explains the current dashboard gridstack widget system used at /dashboard.
Use this guide when creating, updating, or debugging dashboard widgets.

## Current Entry Points
- Route: /dashboard
- Route name: dashboard
- Main controller method: DashboardController@gridstack
- Main page view: resources/views/dashboard/gridstack.blade.php

## High-Level Architecture
The dashboard is built from four layers.

1. Data layer
- DashboardController@gridstack prepares all widget data in one server-rendered response.
- DashboardController@actionQueues returns JSON for action queue refreshes.

2. Layout layer
- GridStack manages draggable and resizable tile positions.
- Root hooks:
  - data-dashboard-gridstack
  - data-gridstack-canvas
  - data-gridstack-toggle
  - data-gridstack-summary

3. Widget frame layer
- Shared frame component: resources/views/components/dashboard/widget-frame.blade.php
- This component standardizes panel structure and behavior:
  - header
  - scrollable body
  - optional footer

4. Widget content layer
- Individual widget partials live in resources/views/dashboard/widgets/
- Main page includes each widget partial inside a GridStack tile.

## Widget Contract
All dashboard widgets should use the shared frame component.

Required slots and structure:
- icon slot: the lead icon for the widget
- title slot: primary heading
- description slot: short supporting text below title
- body slot: default content area
- optional footer slot: actions and low-priority controls

Header policy:
- Keep header to icon, title, and description only.
- Do not place badges or action buttons in the header.
- Put actions in footer or body.

## Layout and Positioning Rules
GridStack tile placement is defined in gridstack.blade.php using gs-* attributes.
Example attributes:
- gs-id
- gs-x
- gs-y
- gs-w
- gs-h

Rules:
- gs-id must be stable and unique. Treat it as persistent layout identity.
- Do not casually rename gs-id values. Renaming resets saved local layout for that tile.
- Keep tile wrappers minimal. Avoid extra outer containers that break full-height behavior.

## Scrolling Rules
This is critical and must remain consistent.

- GridStack tile container should not scroll.
- Widget frame body should scroll.
- In CSS, tile content is overflow hidden and full-height flex.
- In frame component, body uses overflow-y-auto and dashboard-widget-scroll classes.

If whole tiles start scrolling, drag and resize handles become unreliable.

## Responsive Header and Icon Sizing
The frame uses container queries so text and icon sizing react to widget width.

Defined in resources/css/app.css:
- .dashboard-widget-frame sets container-type and container-name
- .dashboard-widget-title, .dashboard-widget-description, .dashboard-widget-icon are scaled by container width
- @container dashboard-widget rules adjust typography and icon size for narrow and wide widgets

When adding custom header elements, ensure they do not break these container-query assumptions.

## Frontend Behavior and Persistence
File: resources/js/components/dashboardGridstackPage.js

What it does:
- Initializes GridStack with move and resize disabled by default
- Unlock/lock toggles editing mode
- Applies previously saved layout from localStorage
- Saves layout changes back to localStorage key dashboard.gridstack.layout.v1

Persistence shape:
- Array of nodes with id, x, y, w, h
- Data is sanitized before use and before save

## Async Widget Refresh Pattern
Action inbox demonstrates refreshable widget content.

Files:
- resources/js/components/dashboardActionQueues.js
- DashboardController@actionQueues
- resources/views/dashboard/widgets/action-inbox.blade.php

Pattern:
1. Alpine fetches JSON from dashboard.action-queues.
2. Controller returns count plus HTML payload.
3. Widget container updates inner HTML and re-initializes Alpine subtree.
4. Approval count store is updated.

Guidance:
- Keep asynchronous logic in JS modules, not in large inline Blade expressions.
- Use x-ref target updates carefully and only for bounded widget regions.

## How to Create a New Widget
Follow this exact sequence.

1. Add data requirements
- Extend DashboardController@gridstack with the new query/data.
- Keep query scopes consistent with existing visibility and authorization behavior.

2. Create widget partial
- Add a new file in resources/views/dashboard/widgets/.
- Wrap content in x-dashboard.widget-frame.
- Provide icon, title, description.
- Place controls in footer if possible.

3. Register tile in layout
- Add a new GridStack item block in resources/views/dashboard/gridstack.blade.php.
- Choose a unique, stable gs-id.
- Set initial gs-x, gs-y, gs-w, gs-h.
- Include the widget partial and pass required data.

4. Add optional refresh endpoint logic if widget must update live
- Prefer dedicated endpoint methods on DashboardController or a focused controller.
- Return compact JSON payloads.

5. Add or update tests
- Update tests/Feature/DashboardTest.php for baseline visibility assertions.
- Add focused feature tests if behavior is conditional or data-dependent.

6. Validate
- Run targeted tests: php artisan test --compact tests/Feature/DashboardTest.php
- Run php artisan app:validate-feature-tours-config if any data-tour anchors changed
- Run Pint only if PHP files changed: vendor/bin/pint --dirty --format agent

## Naming and Design Conventions
- Keep labels concise and factual.
- Reuse Heroicons and project color semantics.
- Use amber for actionable attention states, emerald for success/completion, sky for user-specific states, rose/red for errors.
- Keep widget copy short and non-promotional.

## Common Pitfalls
- Missing widget partial file while still referenced by include in gridstack.blade.php.
- Adding extra wrappers that remove full-height layout and break scroll.
- Putting controls in header, which violates the current frame contract.
- Changing gs-id and unintentionally breaking saved local layout continuity.
- Injecting heavy inline Alpine logic into Blade instead of JS modules.

## Current Canonical Widgets
As of now, the active dashboard includes:
- getting-started
- action-inbox
- coming-up
- quick-moves
- looking-around

Treat these as reference implementations for new widgets.
