<?php

namespace App\Support\DashboardWidgets;

final class LiveSessionDashboardWidget implements DashboardWidget
{
    public function id(): string
    {
        return 'live-session';
    }

    public function label(): string
    {
        return 'Live session';
    }

    public function isVisible(DashboardWidgetContext $context): bool
    {
        return $context->value('live_session') !== null;
    }
}
