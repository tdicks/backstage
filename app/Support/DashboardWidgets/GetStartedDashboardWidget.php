<?php

namespace App\Support\DashboardWidgets;

final class GetStartedDashboardWidget implements DashboardWidget
{
    public function id(): string
    {
        return 'getting-started';
    }

    public function label(): string
    {
        return 'Get started';
    }

    public function isVisible(DashboardWidgetContext $context): bool
    {
        return $context->user?->onboarding_dismissed_at === null;
    }
}
