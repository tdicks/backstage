<?php

namespace App\Support;

final class DashboardWidgetCatalog
{
    /**
     * @var list<array{
     *     id: string,
     *     label: string,
     *     component: string,
     *     defaults: array{order: int, size: int, height: int, column: int, row: int}
     * }>
     */
    private const PREVIEW_WIDGETS = [
        [
            'id' => 'getting-started',
            'label' => 'Getting started',
            'component' => 'dashboard.widgets.getting-started',
            'defaults' => ['order' => 0, 'size' => 2, 'height' => 2, 'column' => 1, 'row' => 1],
        ],
        [
            'id' => 'action-inbox',
            'label' => 'Action inbox',
            'component' => 'dashboard.widgets.action-inbox',
            'defaults' => ['order' => 1, 'size' => 2, 'height' => 2, 'column' => 1, 'row' => 3],
        ],
        [
            'id' => 'right-now',
            'label' => 'Right now',
            'component' => 'dashboard.widgets.right-now',
            'defaults' => ['order' => 2, 'size' => 2, 'height' => 3, 'column' => 1, 'row' => 5],
        ],
        [
            'id' => 'coming-up',
            'label' => 'Coming up',
            'component' => 'dashboard.widgets.coming-up',
            'defaults' => ['order' => 3, 'size' => 3, 'height' => 2, 'column' => 1, 'row' => 8],
        ],
        [
            'id' => 'quick-moves',
            'label' => 'Quick moves',
            'component' => 'dashboard.widgets.quick-moves',
            'defaults' => ['order' => 4, 'size' => 1, 'height' => 2, 'column' => 3, 'row' => 1],
        ],
        [
            'id' => 'looking-around',
            'label' => 'Looking around',
            'component' => 'dashboard.widgets.looking-around',
            'defaults' => ['order' => 5, 'size' => 1, 'height' => 2, 'column' => 3, 'row' => 3],
        ],
    ];

    /**
     * @return list<array{
     *     id: string,
     *     label: string,
     *     component: string,
     *     defaults: array{order: int, size: int, height: int, column: int, row: int}
     * }>
     */
    public static function previewDefinitions(): array
    {
        return self::PREVIEW_WIDGETS;
    }

    /**
     * @return list<string>
     */
    public static function previewWidgetIds(): array
    {
        /** @var list<string> $widgetIds */
        $widgetIds = array_column(self::PREVIEW_WIDGETS, 'id');

        return $widgetIds;
    }

    /**
     * @return list<int>
     */
    public static function previewWidgetSizeOptions(): array
    {
        return [1, 2, 3];
    }

    /**
     * @return array<string, int>
     */
    public static function previewDefaultOrderByWidgetId(): array
    {
        return self::previewDefaultsByKey('order');
    }

    /**
     * @return array<string, int>
     */
    public static function previewDefaultSizes(): array
    {
        return self::previewDefaultsByKey('size');
    }

    /**
     * @return array<string, int>
     */
    public static function previewDefaultHeights(): array
    {
        return self::previewDefaultsByKey('height');
    }

    /**
     * @return array<string, int>
     */
    public static function previewDefaultColumns(): array
    {
        return self::previewDefaultsByKey('column');
    }

    /**
     * @return array<string, int>
     */
    public static function previewDefaultRows(): array
    {
        return self::previewDefaultsByKey('row');
    }

    /**
     * @return array<string, int>
     */
    private static function previewDefaultsByKey(string $key): array
    {
        $values = [];

        foreach (self::PREVIEW_WIDGETS as $widget) {
            $values[$widget['id']] = $widget['defaults'][$key];
        }

        return $values;
    }
}
