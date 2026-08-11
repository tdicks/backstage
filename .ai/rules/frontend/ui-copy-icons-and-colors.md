# UI Copy, Icons, and Color Semantics

## Copy Style
- Use direct, non-promotional wording.
- Use concise action labels.
- Add helper text only when it materially reduces ambiguity.

## Icon Set
- Use Heroicons consistently.
- Reuse the same icon for the same action/state across pages.

## Color Semantics
- Amber: action needed or actionable state.
- Emerald: success/completed state.
- Dark emerald: live jam context.
- Sky blue: current-user-specific state.
- Red/Rose: error or important warning.
- Purple: informational/optional cue.

## Shared UI Semantics
- Slot badges and set/session state indicators should match established semantics.
- For menu/status changes, ensure icon/title/sr-only text remain aligned.
- Keep state labels consistent between tooltip text and screen-reader text.

## Set Card Spacing Consistency
- Keep set-card spacing consistent across Find a Slot, Planned Sets, and Practice Plan.
- Standard set-card outer padding is `p-4`.
- Standard inner section/card padding is `p-3` unless a specific flow requires denser or looser spacing.
- Avoid introducing page-specific responsive padding variants on set cards unless there is a clear UX need.
