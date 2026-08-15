<?php

namespace App\Support;

use App\Models\User;
use App\Support\DashboardWidgets\DashboardWidget;
use App\Support\DashboardWidgets\DashboardWidgetContext;
use App\Support\DashboardWidgets\GetStartedDashboardWidget;
use App\Support\DashboardWidgets\LiveSessionDashboardWidget;

final class DashboardWidgetCatalog
{
    /**
     * @return list<array{id: string, label: string}>
     */
    public function forContext(DashboardWidgetContext $context): array
    {
        $widgets = [];

        foreach ($this->definitions() as $definition) {
            if (! $definition->isVisible($context)) {
                continue;
            }

            $widgets[] = [
                'id' => $definition->id(),
                'label' => $definition->label(),
            ];
        }

        return $widgets;
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public function forUser(User $user): array
    {
        return $this->forContext(new DashboardWidgetContext($user));
    }

    /**
     * @return list<DashboardWidget>
     */
    private function definitions(): array
    {
        return [
            new GetStartedDashboardWidget,
            new LiveSessionDashboardWidget,
            $this->alwaysVisible('action-inbox', 'Approvals and Requests'),
            $this->alwaysVisible('coming-up', 'Next jam prep'),
            $this->alwaysVisible('quick-moves', 'Shortcuts'),
            $this->alwaysVisible('looking-around', 'Sets that need players'),
        ];
    }

    private function alwaysVisible(string $id, string $label): DashboardWidget
    {
        return new class($id, $label) implements DashboardWidget
        {
            public function __construct(
                private readonly string $id,
                private readonly string $label,
            ) {}

            public function id(): string
            {
                return $this->id;
            }

            public function label(): string
            {
                return $this->label;
            }

            public function isVisible(DashboardWidgetContext $context): bool
            {
                return true;
            }
        };
    }
}
