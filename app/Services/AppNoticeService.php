<?php

namespace App\Services;

use App\Models\Notice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AppNoticeService
{
    /**
     * @return array{above_nav: list<array<string, mixed>>, below_nav: list<array<string, mixed>>, below_header: list<array<string, mixed>>}
     */
    public function forRequest(Request $request, ?User $user): array
    {
        $empty = [
            Notice::LOCATION_ABOVE_NAV => [],
            Notice::LOCATION_BELOW_NAV => [],
            Notice::LOCATION_BELOW_HEADER => [],
        ];

        $routeName = $request->route()?->getName();
        if (! $routeName) {
            return $empty;
        }

        $query = Notice::query()
            ->where('enabled', true)
            ->where(function ($inner) use ($routeName): void {
                $inner->where('show_on_all_pages', true)
                    ->orWhereJsonContains('show_on_routes', $routeName);
            })
            ->where(function ($inner) use ($user): void {
                if ($user?->is_admin) {
                    $inner->whereIn('audience_scope', Notice::audienceScopes());

                    return;
                }

                $inner->where('audience_scope', Notice::AUDIENCE_ALL_USERS);
            })
            ->orderBy('position')
            ->orderBy('id');

        if ($user) {
            $query->where(function ($inner) use ($user): void {
                $inner->where('dismissable', false)
                    ->orWhereDoesntHave('dismissals', fn ($dismissals) => $dismissals->where('user_id', $user->id));
            });
        }

        $notices = $query->get();

        $grouped = $notices
            ->map(function (Notice $notice): array {
                return [
                    'id' => $notice->id,
                    'title' => $notice->title,
                    'content_html' => Str::markdown((string) ($notice->content ?? ''), [
                        'html_input' => 'strip',
                        'allow_unsafe_links' => false,
                    ]),
                    'level' => $notice->level,
                    'location' => $notice->location,
                    'dismissable' => (bool) $notice->dismissable,
                ];
            })
            ->groupBy('location');

        return [
            Notice::LOCATION_ABOVE_NAV => ($grouped->get(Notice::LOCATION_ABOVE_NAV) ?? collect())->values()->all(),
            Notice::LOCATION_BELOW_NAV => ($grouped->get(Notice::LOCATION_BELOW_NAV) ?? collect())->values()->all(),
            Notice::LOCATION_BELOW_HEADER => ($grouped->get(Notice::LOCATION_BELOW_HEADER) ?? collect())->values()->all(),
        ];
    }
}
