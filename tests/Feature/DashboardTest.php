<?php

use App\Models\JamSession;
use App\Models\JamSessionAttendance;
use App\Models\Set;
use App\Models\Slot;
use App\Models\Song;
use App\Models\SongRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests see the Backstage welcome page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Make the next jam happen.')
        ->assertSee(route('login'))
        ->assertSee(route('register'));
});

test('authenticated users visiting the homepage are redirected to their dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect(route('dashboard'));
});

test('guests visiting the dashboard are redirected to login', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('authenticated users see their next jam session slots on the dashboard', function () {
    $user = User::factory()->create(['name' => 'Alex Player']);
    $otherUser = User::factory()->create();

    $earlierSession = JamSession::create([
        'name' => 'Friday Jam',
        'date' => now()->addDays(3),
        'description' => null,
    ]);
    $laterSession = JamSession::create([
        'name' => 'Saturday Jam',
        'date' => now()->addDays(10),
        'description' => null,
    ]);

    $featuredSet = Set::create([
        'name' => 'Opening Set',
        'owner_id' => $otherUser->id,
        'jam_session_id' => $earlierSession->id,
        'position' => 1,
    ]);
    $laterSet = Set::create([
        'name' => 'Later Set',
        'owner_id' => $otherUser->id,
        'jam_session_id' => $laterSession->id,
        'position' => 1,
    ]);

    $featuredSong = Song::create([
        'set_id' => $featuredSet->id,
        'artist' => 'The Band',
        'title' => 'Opening Song',
        'position' => 1,
    ]);
    $laterSong = Song::create([
        'set_id' => $laterSet->id,
        'artist' => 'The Band',
        'title' => 'Later Song',
        'position' => 1,
    ]);

    Slot::create(['song_id' => $featuredSong->id, 'name' => 'guitar', 'position' => 1, 'user_id' => $user->id]);
    Slot::create(['song_id' => $laterSong->id, 'name' => 'bass', 'position' => 1, 'user_id' => $user->id]);

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertSee('Welcome back, Alex Player')
        ->assertSee(route('dashboard'))
        ->assertSee('Friday Jam')
        ->assertSee('Opening Set')
        ->assertSee('Guitar on The Band - Opening Song')
        ->assertSee(route('sessions.show', $earlierSession).'#set-'.$featuredSet->id);

    expect($response->getContent())
        ->toContain('<ul class="mt-1 list-none space-y-1 text-sm text-slate-600">')
        ->toContain('<li>Guitar on The Band - Opening Song</li>')
        ->toContain('<h3 data-next-session-name class="mt-2 text-xl font-semibold text-slate-900">Friday Jam</h3>')
        ->not->toContain('<h3 data-next-session-name class="mt-2 text-xl font-semibold text-slate-900">Saturday Jam</h3>');
});

test('authenticated users without upcoming slots see the empty dashboard state', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('No upcoming slots yet')
        ->assertSee(route('sessions.index'));
});

test('dashboard shows a dismissible get started quest for new users', function () {
    $user = User::factory()->create([
        'bio' => null,
        'onboarding_dismissed_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Get started')
        ->assertSee('Add something to your bio')
        ->assertSee('Sign up for a song')
        ->assertSee('Create your own set');

    $this->actingAs($user)
        ->post(route('dashboard.get-started.dismiss'))
        ->assertRedirect();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Get started');
});

test('dashboard keeps the get started quest visible until dismissed when all items are complete', function () {
    $user = User::factory()->create([
        'bio' => 'I play drums and love late-night jams.',
        'onboarding_dismissed_at' => null,
    ]);

    $jamSession = JamSession::create([
        'name' => 'Friday Jam',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'My first set',
        'owner_id' => $user->id,
        'jam_session_id' => $jamSession->id,
        'position' => 1,
    ]);
    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'The Band',
        'title' => 'First Song',
        'position' => 1,
    ]);
    Slot::create(['song_id' => $song->id, 'name' => 'drums', 'position' => 1, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Get started')
        ->assertSee('all set')
        ->assertSee('Happy jamming!');
});

test('dashboard dismissing the get started quest over ajax returns no content', function () {
    $user = User::factory()->create([
        'onboarding_dismissed_at' => null,
    ]);

    $this->actingAs($user)
        ->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->post(route('dashboard.get-started.dismiss'))
        ->assertNoContent();
});

test('dashboard shows quick links', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Quick links')
        ->assertSee(route('sessions.index'))
        ->assertSee(route('my-sets.index'))
        ->assertSee(route('directory.index'))
        ->assertSee(route('profile.edit'));
});

test('dashboard hides approvals panel when there are no pending approvals', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Approvals and Requests');
});

test('dashboard shows approvals panel with subtle amber styling when pending approvals exist', function () {
    $user = User::factory()->create();
    $requester = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Approval Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
    ]);

    $set = Set::create([
        'name' => 'Approval Set',
        'owner_id' => $user->id,
        'jam_session_id' => $session->id,
        'position' => 1,
    ]);

    SongRequest::create([
        'set_id' => $set->id,
        'requester_user_id' => $requester->id,
        'artist' => 'Pending Artist',
        'title' => 'Pending Song',
        'status' => SongRequest::STATUS_PENDING,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Approvals and Requests')
        ->assertSee('rounded-xl border border-amber-200 bg-white/95 p-5 shadow-sm ring-1 ring-amber-100/70 sm:p-6', false)
        ->assertSee('Open My Sets');
});

test('dashboard action queues endpoint returns all done panel when approvals are cleared', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->get(route('dashboard.action-queues'))
        ->assertOk()
        ->assertJsonPath('count', 0);

    $legacyHtml = $response->json('html');

    expect($legacyHtml)
        ->toContain('All done!')
        ->toContain('Approvals and Requests');
});

test('authenticated users can view the dashboard layout preview', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.layout-preview'))
        ->assertOk()
        ->assertSee('Dashboard preview')
        ->assertSee('Get started')
        ->assertSee('Action inbox')
        ->assertSee('Quick moves')
        ->assertSee('Looking around');
});

test('dashboard layout preview hydrates the saved widget order', function () {
    $user = User::factory()->create([
        'dashboard_widget_layouts' => [
            'layout-preview' => [
                'coming-up' => ['order' => 0, 'size' => 'full', 'height' => 'tall'],
                'action-inbox' => ['order' => 1, 'size' => 'third', 'height' => 'medium'],
                'right-now' => ['order' => 2, 'size' => 'half', 'height' => 4],
                'quick-moves' => ['order' => 3, 'size' => 2, 'height' => 'short'],
                'looking-around' => ['order' => 4, 'size' => 'third', 'height' => 5],
            ],
        ],
    ]);

    $response = $this->actingAs($user)
        ->get(route('dashboard.layout-preview'));

    $response
        ->assertOk()
        ->assertSee('Dashboard preview')
        ->assertSee('widgetOrderIds')
        ->assertSee('widgetSizes')
        ->assertSee('widgetHeights')
        ->assertSee('widgetPositions')
        ->assertViewHas('widgetSizeMap', fn (array $sizes): bool => ($sizes['action-inbox'] ?? null) === 1 && ($sizes['quick-moves'] ?? null) === 2)
        ->assertViewHas('widgetHeightMap', fn (array $heights): bool => ($heights['right-now'] ?? null) === 4 && ($heights['looking-around'] ?? null) === 5)
        ->assertViewHas('widgetPositionMap', fn (array $positions): bool => ($positions['coming-up']['column'] ?? null) === 1 && ($positions['looking-around']['row'] ?? null) === 8)
        ->assertSeeInOrder(['coming-up', 'action-inbox', 'right-now', 'quick-moves', 'looking-around']);
});

test('dashboard layout preview persists the widget order', function () {
    $user = User::factory()->create();
    $widgetOrder = ['coming-up', 'getting-started', 'action-inbox', 'right-now', 'quick-moves', 'looking-around'];
    $widgetSizes = [
        'getting-started' => 2,
        'action-inbox' => 1,
        'right-now' => 2,
        'coming-up' => 3,
        'quick-moves' => 2,
        'looking-around' => 1,
    ];
    $widgetHeights = [
        'getting-started' => 2,
        'action-inbox' => 2,
        'right-now' => 4,
        'coming-up' => 3,
        'quick-moves' => 1,
        'looking-around' => 5,
    ];
    $widgetPositions = [
        'coming-up' => ['column' => 1, 'row' => 1],
        'getting-started' => ['column' => 2, 'row' => 4],
        'action-inbox' => ['column' => 1, 'row' => 4],
        'right-now' => ['column' => 1, 'row' => 6],
        'quick-moves' => ['column' => 1, 'row' => 10],
        'looking-around' => ['column' => 3, 'row' => 8],
    ];

    $this->actingAs($user)
        ->postJson(route('dashboard.layout-preview.widget-order.update'), [
            'widget_order' => $widgetOrder,
            'widget_sizes' => $widgetSizes,
            'widget_heights' => $widgetHeights,
            'widget_positions' => $widgetPositions,
        ])
        ->assertOk()
        ->assertJson([
            'widget_order' => $widgetOrder,
            'widget_sizes' => $widgetSizes,
            'widget_heights' => $widgetHeights,
            'widget_positions' => $widgetPositions,
            'widget_layout' => [
                'coming-up' => ['order' => 0, 'size' => 3, 'height' => 3, 'column' => 1, 'row' => 1],
                'getting-started' => ['order' => 1, 'size' => 2, 'height' => 2, 'column' => 2, 'row' => 4],
                'action-inbox' => ['order' => 2, 'size' => 1, 'height' => 2, 'column' => 1, 'row' => 4],
                'right-now' => ['order' => 3, 'size' => 2, 'height' => 4, 'column' => 1, 'row' => 6],
                'quick-moves' => ['order' => 4, 'size' => 2, 'height' => 1, 'column' => 1, 'row' => 10],
                'looking-around' => ['order' => 5, 'size' => 1, 'height' => 5, 'column' => 3, 'row' => 8],
            ],
        ]);

    expect($user->fresh()->dashboard_widget_order)->toBe($widgetOrder);
    expect($user->fresh()->dashboard_widget_sizes)->toBe($widgetSizes);
    expect($user->fresh()->dashboard_widget_layouts)->toMatchArray([
        'layout-preview' => [
            'coming-up' => ['order' => 0, 'size' => 3, 'height' => 3, 'column' => 1, 'row' => 1],
            'getting-started' => ['order' => 1, 'size' => 2, 'height' => 2, 'column' => 2, 'row' => 4],
            'action-inbox' => ['order' => 2, 'size' => 1, 'height' => 2, 'column' => 1, 'row' => 4],
            'right-now' => ['order' => 3, 'size' => 2, 'height' => 4, 'column' => 1, 'row' => 6],
            'quick-moves' => ['order' => 4, 'size' => 2, 'height' => 1, 'column' => 1, 'row' => 10],
            'looking-around' => ['order' => 5, 'size' => 1, 'height' => 5, 'column' => 3, 'row' => 8],
        ],
    ]);
});

test('dashboard layout preview hides the getting started widget when the quest is dismissed', function () {
    $user = User::factory()->create([
        'onboarding_dismissed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.layout-preview'))
        ->assertOk()
        ->assertDontSee('Get started')
        ->assertDontSee('Add something to your bio');
});

test('dashboard layout preview uses the completed get started shell style when all items are done', function () {
    $user = User::factory()->create([
        'bio' => 'I play drums and love late-night jams.',
        'onboarding_dismissed_at' => null,
    ]);

    $jamSession = JamSession::create([
        'name' => 'Friday Jam',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'My first set',
        'owner_id' => $user->id,
        'jam_session_id' => $jamSession->id,
        'position' => 1,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'The Band',
        'title' => 'First Song',
        'position' => 1,
    ]);

    Slot::create([
        'song_id' => $song->id,
        'name' => 'drums',
        'position' => 1,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.layout-preview'))
        ->assertOk()
        ->assertSee('Get started')
        ->assertSee('Happy jamming!')
        ->assertSee('border-2 border-emerald-200 bg-white/95', false);
});

test('dashboard layout preview exposes widget layout controls', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.layout-preview'))
        ->assertOk()
        ->assertSee('Drag to move')
        ->assertSee('Drag edges to resize')
        ->assertSee('1 column')
        ->assertSee('2 columns')
        ->assertSee('3 columns')
        ->assertSee('Rows grow as needed');
});

test('dashboard layout preview shows a loading placeholder before the widget layout is ready', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.layout-preview'))
        ->assertOk()
        ->assertSee('Preparing your widget layout')
        ->assertSee('Loading your dashboard preview');
});

test('dashboard layout preview uses drag and drop reordering for the widget grid', function () {
    $previewView = file_get_contents(resource_path('views/dashboard/layout-preview.blade.php'));
    $widgetCanvas = file_get_contents(resource_path('views/components/dashboard/widget-canvas.blade.php'));
    $widgetShell = file_get_contents(resource_path('views/components/dashboard/widget-shell.blade.php'));
    $widgetCard = file_get_contents(resource_path('views/components/dashboard/widget-card.blade.php'));
    $gettingStartedWidgetComponent = file_get_contents(resource_path('views/components/dashboard/widgets/getting-started.blade.php'));
    $rightNowWidgetComponent = file_get_contents(resource_path('views/components/dashboard/widgets/right-now.blade.php'));
    $previewComponent = file_get_contents(resource_path('js/components/dashboardLayoutPreviewPage.js'));
    $widgetLayoutState = file_get_contents(resource_path('js/components/widgetState/dashboardWidgetLayoutState.js'));
    $liveState = file_get_contents(resource_path('js/components/widgetState/dashboardLiveState.js'));
    $attachmentState = file_get_contents(resource_path('js/components/widgetState/dashboardAttachmentState.js'));
    $appCss = file_get_contents(resource_path('css/app.css'));
    $actionInboxWidget = file_get_contents(resource_path('views/components/dashboard/widgets/action-inbox.blade.php'));
    $navigationView = file_get_contents(resource_path('views/layouts/navigation.blade.php'));

    expect($previewView)
        ->toContain('<x-dashboard.widget-canvas')
        ->toContain('x-sort.ghost="reorderWidget($item, $position)"')
        ->toContain('x-sort:config="widgetSortConfig()"')
        ->toContain('x-bind:class="widgetGridClasses()"')
        ->toContain('widgetDefinitions: @js($widgetDefinitionState)')
        ->toContain('widgetPositions: @js($widgetPositionMap)')
        ->toContain('widgetCanvasPlaceholderStyle()')
        ->toContain('<x-dynamic-component')
        ->toContain(':component="$widgetDefinition[\'component\']"')
        ->toContain(':context="$widgetContext"')
        ->not->toContain('@dragstart=')
        ->not->toContain('@dragover.prevent=')
        ->not->toContain('Drop here');

    expect($widgetCanvas)
        ->toContain('grid gap-6 transition-all duration-300 ease-out')
        ->toContain('{{ $slot }}');

    expect($widgetShell)
        ->toContain('data-widget-card')
        ->toContain('@pointerdown.capture="startWidgetMove(\'{{ $widgetId }}\', $event)"')
        ->toContain('x-sort:handle')
        ->toContain('dashboard-widget-displacement-readout')
        ->toContain('x-text="widgetDisplacementSummary(\'{{ $widgetId }}\')"')
        ->toContain('dashboard-widget-resize-readout')
        ->toContain('x-text="widgetResizeSummary(\'{{ $widgetId }}\')"')
        ->toContain('data-widget-resize-handle="x"')
        ->toContain('data-widget-resize-handle="y"')
        ->toContain('@pointerdown="startWidgetResize(\'{{ $widgetId }}\', \'xy\', $event)"')
        ->toContain('@pointerdown.capture="guardWidgetDragFromScrollbar(\'{{ $widgetId }}\', $event)"')
        ->toContain('data-widget-id="{{ $widgetId }}"');

    expect($widgetCard)
        ->toContain('<x-dashboard.widget-shell')
        ->toContain('x-sort:item="\'{{ $widgetId }}\'"')
        ->toContain('widgetContainerClasses')
        ->toContain('widgetStackClasses')
        ->toContain('widgetDragClasses')
        ->toContain('widgetOrderStyle');

    expect($gettingStartedWidgetComponent)
        ->toContain('allGetStartedItemsCompleted')
        ->toContain('border-2 border-emerald-200 bg-white/95')
        ->toContain('border-2 border-amber-200 bg-white/95');

    expect($rightNowWidgetComponent)
        ->toContain('$context[\'liveSession\']')
        ->toContain('@if ($context[\'liveSession\'] ?? null)');

    expect($actionInboxWidget)
        ->toContain("dashboardActionQueues({ refreshUrl: @js(route('dashboard.action-queues')), htmlKey: 'widget_html' })")
        ->toContain('@target-consent-processed.window="refresh(false)"')
        ->toContain('@pending-approval-processed.window="refresh(false)"')
        ->toContain('x-ref="actionQueuesContent"');

    expect($previewComponent)
        ->not->toContain("import Sortable from 'sortablejs'")
        ->toContain("import { createDashboardWidgetLayoutState } from './widgetState/dashboardWidgetLayoutState'")
        ->toContain("import { createDashboardLiveState } from './widgetState/dashboardLiveState'")
        ->toContain("import { createDashboardAttachmentState } from './widgetState/dashboardAttachmentState'")
        ->toContain('export function dashboardPage(config = {})')
        ->toContain('export function dashboardLayoutPreviewPage(config = {})')
        ->toContain('...createDashboardWidgetLayoutState(config)')
        ->toContain('...createDashboardLiveState(config)')
        ->toContain('...createDashboardAttachmentState(config)')
        ->toContain('this.initWidgetLayoutState()')
        ->toContain('this.initLiveDashboardState()')
        ->toContain('this.disposeWidgetLayoutState()')
        ->toContain('this.disposeLiveDashboardState()');

    expect($widgetLayoutState)
        ->toContain('reorderWidget(widgetId, position)')
        ->toContain('widget_layout: widgetLayout')
        ->toContain('widget_heights: this.widgetHeights')
        ->toContain('widget_sizes: this.widgetSizes')
        ->toContain('widget_positions: this.widgetPositions')
        ->toContain('widgetSortConfig()')
        ->toContain('buildWidgetPlacementMap(overrides = {})')
        ->toContain('widgetIdsByCanvasOrder(positions)')
        ->toContain('resolveCanvasCellFromPointer(clientX, clientY, columnSpan, rowSpan)')
        ->toContain('placeWidgetOnCanvas(widgetId, targetColumn, targetRow')
        ->toContain('layoutForResizedWidget(widgetId, targetSize, targetHeight, baseState = null)')
        ->toContain('measureResizeAvailability(widgetId)')
        ->toContain('clampWidgetSizeToAvailability(targetSize, targetHeight, resizeState)')
        ->toContain('clampWidgetHeightToAvailability(targetSize, targetHeight, resizeState)')
        ->toContain('widgetGridClasses()')
        ->toContain('activePreviewPositions()')
        ->toContain('isWidgetDisplaced(widgetId)')
        ->toContain('widgetDisplacementSummary(widgetId)')
        ->toContain('isWidgetResizing(widgetId)')
        ->toContain('widgetResizeSummary(widgetId)')
        ->toContain('guardWidgetDragFromScrollbar(widgetId, event)')
        ->toContain('startWidgetMove(widgetId, event)')
        ->toContain('startWidgetResize(widgetId, axis, event)')
        ->toContain('setWidgetSizeLocal(widgetId, size)')
        ->toContain('setWidgetHeightLocal(widgetId, height)')
        ->toContain('this.widgetOrderIds = nextOrder');

    expect($liveState)
        ->toContain("return 'Playing now'")
        ->toContain("return 'Coming up'")
        ->toContain("return 'Up later'")
        ->toContain("return 'Finished'")
        ->toContain("return 'Postponed'")
        ->toContain('async refreshLiveParts()')
        ->toContain('extractLiveParts(payload)');

    expect($attachmentState)
        ->toContain('openAttachmentsForEntity(type, id, contextLabel, key, fallbackCount = null)')
        ->toContain('async openAttachmentsModal()')
        ->toContain('async loadAttachments()')
        ->toContain('async submitAttachmentForm()')
        ->toContain('async removeAttachment(attachmentId)');

    expect($appCss)
        ->toContain('.widget-drop-placeholder::after')
        ->toContain("content: 'Drop here';")
        ->toContain('.dashboard-widget-grid.dashboard-widget-grid-guides::before')
        ->toContain('--dashboard-widget-column-size: calc((100% - (var(--dashboard-widget-gap, 1.5rem) * 2)) / 3)')
        ->toContain('calc((100% + var(--dashboard-widget-gap, 0px)) / var(--dashboard-widget-columns, 1))')
        ->toContain('.dashboard-widget-displacement-readout')
        ->toContain('.dashboard-widget-displaced-card')
        ->toContain('.jam-sessions-nav-scroll');

    expect($navigationView)
        ->toContain('jam-sessions-nav-scroll max-h-80 overflow-y-auto py-1')
        ->toContain('aria-label="View pending Dashboard approvals"')
        ->toContain('<span>{{ __(\'Dashboard\') }}</span>')
        ->toContain('x-responsive-nav-link :href="route(\'dashboard\')" :active="request()->routeIs(\'dashboard\')"')
        ->not->toContain('aria-label="View pending My Sets approvals"');
});

test('dashboard layout preview wires reactive drag state into widget cards', function () {
    $previewView = file_get_contents(resource_path('views/dashboard/layout-preview.blade.php'));
    $widgetCard = file_get_contents(resource_path('views/components/dashboard/widget-card.blade.php'));
    $previewComponent = file_get_contents(resource_path('js/components/dashboardLayoutPreviewPage.js'));
    $widgetLayoutState = file_get_contents(resource_path('js/components/widgetState/dashboardWidgetLayoutState.js'));
    $liveState = file_get_contents(resource_path('js/components/widgetState/dashboardLiveState.js'));
    $attachmentState = file_get_contents(resource_path('js/components/widgetState/dashboardAttachmentState.js'));
    $appJs = file_get_contents(resource_path('js/app.js'));

    expect($previewView)
        ->toContain('<x-dynamic-component')
        ->toContain(':component="$widgetDefinition[\'component\']"')
        ->toContain(':widget-id="$widgetDefinition[\'id\']"');

    expect($widgetCard)
        ->toContain('x-sort:item="\'{{ $widgetId }}\'"')
        ->toContain('x-bind:class="[widgetContainerClasses(')
        ->toContain('x-bind:style="widgetOrderStyle(');

    expect($appJs)
        ->toContain('Alpine.plugin(sort)')
        ->toContain("import sort from '@alpinejs/sort'")
        ->toContain("Alpine.data('dashboardPage', dashboardPage)");

    expect($previewComponent)
        ->toContain('export function dashboardPage(config = {})')
        ->toContain('...createDashboardWidgetLayoutState(config)')
        ->toContain('...createDashboardLiveState(config)')
        ->toContain('...createDashboardAttachmentState(config)');

    expect($widgetLayoutState)
        ->toContain('reorderWidget(widgetId, position)')
        ->toContain('this.persistWidgetOrder();')
        ->toContain('this.widgetOrderIds = nextOrder');

    expect($liveState)
        ->toContain('initLiveDashboardState()')
        ->toContain('disposeLiveDashboardState()');

    expect($attachmentState)
        ->toContain('attachmentIconClasses(key, fallbackCount = null)')
        ->toContain('closeAttachmentsModal(force = false)');
});

test('dashboard layout preview keeps widget cards bound to the shared layout state', function () {
    $previewView = file_get_contents(resource_path('views/dashboard/layout-preview.blade.php'));
    $widgetShell = file_get_contents(resource_path('views/components/dashboard/widget-shell.blade.php'));
    $widgetCard = file_get_contents(resource_path('views/components/dashboard/widget-card.blade.php'));
    $previewComponent = file_get_contents(resource_path('js/components/dashboardLayoutPreviewPage.js'));
    $widgetLayoutState = file_get_contents(resource_path('js/components/widgetState/dashboardWidgetLayoutState.js'));

    expect($previewView)
        ->toContain('<x-dynamic-component')
        ->toContain(':component="$widgetDefinition[\'component\']"');

    expect($widgetCard)
        ->toContain('widgetDragClasses(\'{{ $widgetId }}\')')
        ->toContain('widgetContainerClasses(\'{{ $widgetId }}\')')
        ->toContain('widgetOrderStyle(\'{{ $widgetId }}\')');

    expect($widgetShell)
        ->toContain('data-widget-id="{{ $widgetId }}"')
        ->not->toContain('handleTextClasses')
        ->not->toContain('handleChipClasses')
        ->not->toContain("'label'");

    expect($previewComponent)
        ->toContain('...createDashboardWidgetLayoutState(config)');

    expect($widgetLayoutState)
        ->toContain('widgetDragClasses(widgetId)')
        ->toContain('widgetOrderStyle(widgetId)');
});

test('dashboard layout preview updates each widget card order when reordering completes', function () {
    $widgetLayoutState = file_get_contents(resource_path('js/components/widgetState/dashboardWidgetLayoutState.js'));

    expect($widgetLayoutState)
        ->toContain('reorderWidget(widgetId, position)')
        ->toContain('widgetOrderStyle(widgetId)');
});

test('dashboard layout preview maps live set statuses to live-dashboard style labels', function () {
    $liveState = file_get_contents(resource_path('js/components/widgetState/dashboardLiveState.js'));

    expect($liveState)
        ->toContain("return 'Playing now'")
        ->toContain("return 'Coming up'")
        ->toContain("return 'Up later'")
        ->toContain("return 'Finished'")
        ->toContain("return 'Postponed'");
});

test('dashboard layout preview action inbox widget renders approvals and pending sections', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard.layout-preview'));

    $response
        ->assertOk()
        ->assertSee('Approvals and Requests')
        ->assertSee('Pending for You')
        ->assertSee('0 waiting')
        ->assertSee('No approvals are waiting right now.')
        ->assertSee('No pending requests.');
});

test('dashboard layout preview hides the right now panel for users marked not going to the live session', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();

    $liveSession = JamSession::create([
        'name' => 'Right Now Jam',
        'date' => now()->addDay(),
        'description' => null,
        'is_live' => true,
    ]);

    $set = Set::create([
        'name' => 'Live Set',
        'owner_id' => $owner->id,
        'jam_session_id' => $liveSession->id,
        'position' => 1,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Live Band',
        'title' => 'Live Song',
        'position' => 1,
    ]);

    Slot::create(['song_id' => $song->id, 'name' => 'guitar', 'position' => 1, 'user_id' => $user->id]);

    JamSessionAttendance::create([
        'jam_session_id' => $liveSession->id,
        'user_id' => $user->id,
        'status' => JamSessionAttendance::STATUS_NOT_GOING,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.layout-preview'))
        ->assertOk()
        ->assertDontSee('Right now')
        ->assertViewHas('liveSession', null);
});

test('dashboard layout preview non-live panel ignores sessions marked not going', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();

    $notGoingSession = JamSession::create([
        'name' => 'Skip This Jam',
        'date' => now()->addDays(1),
        'description' => null,
        'is_live' => false,
    ]);
    $nextSession = JamSession::create([
        'name' => 'Prep This Jam',
        'date' => now()->addDays(2),
        'description' => null,
        'is_live' => false,
    ]);

    $notGoingSet = Set::create([
        'name' => 'Skip Set',
        'owner_id' => $owner->id,
        'jam_session_id' => $notGoingSession->id,
        'position' => 1,
    ]);
    $nextSet = Set::create([
        'name' => 'Prep Set',
        'owner_id' => $owner->id,
        'jam_session_id' => $nextSession->id,
        'position' => 1,
    ]);

    $notGoingSong = Song::create([
        'set_id' => $notGoingSet->id,
        'artist' => 'Band A',
        'title' => 'Song A',
        'position' => 1,
    ]);
    $nextSong = Song::create([
        'set_id' => $nextSet->id,
        'artist' => 'Band B',
        'title' => 'Song B',
        'position' => 1,
    ]);

    Slot::create(['song_id' => $notGoingSong->id, 'name' => 'guitar', 'position' => 1, 'user_id' => $user->id]);
    Slot::create(['song_id' => $nextSong->id, 'name' => 'bass', 'position' => 1, 'user_id' => $user->id]);

    JamSessionAttendance::create([
        'jam_session_id' => $notGoingSession->id,
        'user_id' => $user->id,
        'status' => JamSessionAttendance::STATUS_NOT_GOING,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.layout-preview'))
        ->assertOk()
        ->assertSee('Next jam prep')
        ->assertSee('Prep This Jam')
        ->assertViewHas('nextNonLiveSession', fn (JamSession $session) => $session->is($nextSession))
        ->assertViewHas('nextNonLiveSets', function ($sets) use ($nextSet, $notGoingSet): bool {
            return $sets->contains(fn ($set) => $set->is($nextSet))
                && ! $sets->contains(fn ($set) => $set->is($notGoingSet));
        });
});
