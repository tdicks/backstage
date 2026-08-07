# .ai Rules Index

This file maps repository paths and task types to rule documents under `.ai/rules`.
Read this file first, then load every matching rule file before planning or editing.

## Always Load
- .ai/rules/copilot-instructions.md
- .ai/rules/core/project-baseline.md
- .ai/rules/workflows/testing-and-validation.md

## Path-Based Rule Map
- app/**
  - .ai/rules/core/project-baseline.md
  - .ai/rules/domain/authorization-and-approval-model.md
  - .ai/rules/domain/set-state-and-visibility-matrix.md
  - .ai/rules/workflows/testing-and-validation.md

- routes/**
  - .ai/rules/core/project-baseline.md
  - .ai/rules/domain/authorization-and-approval-model.md
  - .ai/rules/workflows/testing-and-validation.md

- resources/views/**
  - .ai/rules/core/project-baseline.md
  - .ai/rules/frontend/blade-alpine-templates.md
  - .ai/rules/frontend/ui-copy-icons-and-colors.md
  - .ai/rules/workflows/feature-tour-targets.md
  - .ai/rules/workflows/testing-and-validation.md

- resources/js/**
  - .ai/rules/core/project-baseline.md
  - .ai/rules/frontend/blade-alpine-templates.md
  - .ai/rules/workflows/feature-tour-targets.md
  - .ai/rules/workflows/testing-and-validation.md

- tests/**
  - .ai/rules/core/project-baseline.md
  - .ai/rules/workflows/testing-and-validation.md

- resources/tours/**
  - .ai/rules/workflows/feature-tour-targets.md
  - .ai/rules/workflows/testing-and-validation.md

## Task-Based Rule Map
- Permissions, approvals, or role behavior:
  - .ai/rules/domain/authorization-and-approval-model.md
  - .ai/rules/domain/set-state-and-visibility-matrix.md

- Menus, action visibility, status badges, or icon semantics:
  - .ai/rules/frontend/ui-copy-icons-and-colors.md
  - .ai/rules/domain/set-state-and-visibility-matrix.md

- Dynamic UI rendering, filtering, or pagination:
  - .ai/rules/frontend/blade-alpine-templates.md
  - .ai/rules/workflows/feature-tour-targets.md

- Larger refactors:
  - .ai/roadmap/alpine-template-rendering-migration.md

## Notes
- If guidance conflicts, prioritize AGENTS.md, then copilot-instructions.md, then path-specific files.
- Keep rule docs short and actionable. Link to roadmap docs for long-term initiatives.
