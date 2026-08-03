<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'content',
    'level',
    'location',
    'position',
    'audience_scope',
    'show_on_all_pages',
    'show_on_routes',
    'dismissable',
    'enabled',
])]
class Notice extends Model
{
    public const LEVEL_INFO = 'info';

    public const LEVEL_WARNING = 'warning';

    public const LEVEL_CRITICAL = 'critical';

    public const LOCATION_ABOVE_NAV = 'above_nav';

    public const LOCATION_BELOW_NAV = 'below_nav';

    public const LOCATION_BELOW_HEADER = 'below_header';

    public const AUDIENCE_ALL_USERS = 'all_users';

    public const AUDIENCE_ADMINS_ONLY = 'admins_only';

    /**
     * @return array<int, string>
     */
    public static function levels(): array
    {
        return [
            self::LEVEL_INFO,
            self::LEVEL_WARNING,
            self::LEVEL_CRITICAL,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function locations(): array
    {
        return [
            self::LOCATION_ABOVE_NAV,
            self::LOCATION_BELOW_NAV,
            self::LOCATION_BELOW_HEADER,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function audienceScopes(): array
    {
        return [
            self::AUDIENCE_ALL_USERS,
            self::AUDIENCE_ADMINS_ONLY,
        ];
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'show_on_all_pages' => 'boolean',
            'show_on_routes' => 'array',
            'dismissable' => 'boolean',
            'enabled' => 'boolean',
        ];
    }

    public function dismissals(): HasMany
    {
        return $this->hasMany(NoticeDismissal::class);
    }
}
