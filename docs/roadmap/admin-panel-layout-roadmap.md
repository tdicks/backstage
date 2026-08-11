# Admin Panel Layout Roadmap

## Context and Goal
Current admin entry points are nested under an admin submenu in the profile dropdown, and several settings concerns are mixed on one page. This roadmap captures a more consistent, browser-responsive admin information architecture and UI shell.

Primary goals:
- Move admin UX into a dedicated, coherent workspace.
- Give each configurable concern its own section/page.
- Reduce cross-page button sprawl by using consistent page-level related links.
- Keep the experience responsive and usable on desktop, tablet, and mobile.

## Recommended UI Direction
Use an Admin Workspace shell rather than relying on profile-dropdown submenu links.

### Layout
- Desktop (`lg` and up): persistent left sidebar + main content region.
- Tablet/mobile (`< lg`): off-canvas sidebar drawer + sticky top bar.
- Shared page chrome:
  - Breadcrumbs
  - Page title/subtitle
  - Action bar (primary action + related links)

### Navigation Principles
- Group by admin domain, not by implementation detail.
- Keep one stable navigation pattern across all admin pages.
- Avoid embedding important admin navigation in transient UI (dropdowns/modals).

## Target Information Architecture
Suggested top-level admin sections:
- Overview
- People
- Content
- Configuration
- System (optional future)

### Suggested mapping of existing pages
- Overview
  - Admin Home (new)
- People
  - Users
  - Manual Slot Transfers (subpage or tab under Users)
- Content
  - Notices
  - Attachments
- Configuration
  - General Settings
  - Notification Settings
  - Slot Types
  - Slot Conflicts
  - Band Templates
- System (optional)
  - Audit log / diagnostics / queue health

## Target Route Map
Current routes already use `admin.` naming and `/admin` prefix. Expand and normalize to support split configuration pages.

### Existing key admin routes (kept)
- `admin.users.index`
- `admin.users.manual-slot-transfers.index`
- `admin.attachments.index`
- `admin.notices.index`
- `admin.band-templates.*`
- `admin.slot-conflicts.index`
- `admin.settings.index` (to be decomposed)

### Proposed target routes
```php
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    // People
    Route::get('/users', [UserAdministrationController::class, 'index'])->name('users.index');
    Route::get('/users/manual-slot-transfers', [UserAdministrationController::class, 'manualSlotTransfers'])
        ->name('users.manual-slot-transfers.index');

    // Content
    Route::get('/notices', [NoticeAdministrationController::class, 'index'])->name('notices.index');
    Route::get('/attachments', [AttachmentAdministrationController::class, 'index'])->name('attachments.index');

    // Configuration (split out from one mixed settings page)
    Route::get('/settings/general', [AdminGeneralSettingsController::class, 'index'])->name('settings.general.index');
    Route::get('/settings/notifications', [AdminNotificationSettingsController::class, 'index'])->name('settings.notifications.index');
    Route::get('/settings/slot-types', [AdminSlotTypeSettingsController::class, 'index'])->name('settings.slot-types.index');
    Route::get('/settings/slot-conflicts', [SlotTypeConflictController::class, 'index'])->name('settings.slot-conflicts.index');

    // Existing resource
    Route::resource('band-templates', BandTemplateController::class)->except(['show', 'create', 'edit']);
});
```

Notes:
- The current `admin.settings.index` page is a mixed concern page and should be replaced by explicit pages above.
- Route names are intentionally explicit to keep sidebar active states and breadcrumbs simple.

## Blade Layout and Component Structure
Introduce a dedicated admin shell and reusable page primitives.

### Layout files
- `resources/views/layouts/admin.blade.php`
  - Admin-specific wrapper
  - Sidebar (desktop) + drawer (mobile)
  - Sticky top bar
  - Shared breadcrumb/action-bar slots

### Shared components
- `resources/views/components/admin/sidebar.blade.php`
  - Sectioned nav links (Overview, People, Content, Configuration)
  - Active link handling via `request()->routeIs('admin.*')`

- `resources/views/components/admin/mobile-drawer.blade.php`
  - Same nav data source as sidebar
  - Toggle + focus-trap + escape handling

- `resources/views/components/admin/page-header.blade.php`
  - Title, subtitle, breadcrumb slot, action slot

- `resources/views/components/admin/related-links.blade.php`
  - Standardized list of contextual destination links

- `resources/views/components/admin/card-section.blade.php`
  - Consistent card container for filters/tables/forms

### Page composition pattern
Each admin page follows:
1. `x-admin-layout`
2. `x-admin-page-header`
3. One or more `x-admin-card-section`
4. Optional `x-admin-related-links`

Example skeleton:
```blade
<x-admin-layout>
    <x-slot name="breadcrumbs">
        {{-- breadcrumb trail --}}
    </x-slot>

    <x-admin-page-header
        title="User Administration"
        subtitle="Search users and manage account permissions."
    >
        <x-slot name="actions">
            {{-- primary/secondary actions --}}
        </x-slot>
    </x-admin-page-header>

    <x-admin-card-section>
        {{-- filters/table/forms --}}
    </x-admin-card-section>

    <x-admin-related-links :links="[
        ['label' => 'Manual Slot Transfers', 'href' => route('admin.users.manual-slot-transfers.index')],
        ['label' => 'Notification Settings', 'href' => route('admin.settings.notifications.index')],
    ]" />
</x-admin-layout>
```

## Responsive Behavior Standards
- Keep navigation mode-switch deterministic at `lg` breakpoint.
- Use sticky top action bar on narrow screens for save/create flows.
- Preserve horizontal table scrolling where needed, with optional card fallback for very dense data.
- Maintain consistent spacing and typography between admin pages.

## Consistency Rules for Cross-Links
Replace ad-hoc in-page buttons with a predictable "Related" area:
- Users -> Manual Slot Transfers
- Slot Types -> Slot Conflicts
- Notification Settings -> Notices
- Notices -> Notification Settings

## Incremental Rollout Plan
1. Add admin shell layout and sidebar/drawer components.
2. Add `admin.dashboard` as admin landing page.
3. Wire existing admin pages to the new shell.
4. Split mixed settings page into domain-specific pages/routes.
5. Standardize page headers/action bars/related links.
6. Polish mobile behaviors and verify breakpoint transitions.

## Relevant Current References
- Admin links in profile dropdown: `resources/views/layouts/navigation.blade.php`
- Admin route group: `routes/web.php`
- Mixed settings page: `resources/views/admin/settings/index.blade.php`
- Notices admin page: `resources/views/admin/notices/index.blade.php`
- Users admin page: `resources/views/admin/users/index.blade.php`
- Attachments admin page: `resources/views/admin/attachments/index.blade.php`

## Notes for Next Session
When implementation starts, begin with the shell and route-level IA before redesigning individual page internals. This will avoid rework and make active nav, breadcrumbs, and responsive behavior consistent from day one.
