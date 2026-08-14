<?php

namespace App\Support\DashboardWidgets;

interface DashboardWidget
{
    public function id(): string;

    public function label(): string;

    public function isVisible(DashboardWidgetContext $context): bool;
}
