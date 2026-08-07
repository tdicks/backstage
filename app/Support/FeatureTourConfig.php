<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Throwable;

class FeatureTourConfig
{
    public const MERGED_CONFIG_CACHE_KEY_PREFIX = 'feature-tours:merged-config:';

    public function path(): string
    {
        return resource_path('tours/feature-tours.yaml');
    }

    /**
     * @return array<int, string>
     */
    public function paths(): array
    {
        $directory = resource_path('tours');

        if (! File::isDirectory($directory)) {
            return [];
        }

        $paths = [];

        foreach (File::files($directory) as $file) {
            $extension = strtolower($file->getExtension());

            if (! in_array($extension, ['yaml', 'yml'], true)) {
                continue;
            }

            $paths[] = $file->getPathname();
        }

        usort($paths, static fn (string $first, string $second): int => strnatcasecmp(basename($first), basename($second)));

        return $paths;
    }

    /**
     * @return array{
     *     version:int,
     *     current_route:string|null,
     *     authenticated:bool,
     *     is_admin:bool,
     *     state_update_url:string|null,
     *     state:array{completed: array<string, bool>, prompt_dismissed: array<string, bool>, opted_out: array<string, bool>},
     *     anchors:array<string, array{selector: string, view: string}>,
     *     actions:array<string, array<string, mixed>>,
     *     tours:array<string, array<string, mixed>>
     * }
     */
    public function payloadForRequest(?User $user): array
    {
        $config = $this->mergedConfig();

        return [
            'version' => (int) ($config['version'] ?? 1),
            'current_route' => request()->route()?->getName(),
            'authenticated' => $user !== null,
            'is_admin' => (bool) ($user?->is_admin ?? false),
            'state_update_url' => $user !== null ? route('feature-tours.state.update') : null,
            'state' => $this->normalizeState($user?->feature_tour_state),
            'anchors' => $this->normalizeAnchors($config['anchors'] ?? []),
            'actions' => $this->normalizeActions($config['actions'] ?? []),
            'tours' => $this->normalizeTours($config['tours'] ?? []),
        ];
    }

    /**
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function validationReport(): array
    {
        $errors = [];
        $warnings = [];
        $config = [];
        $paths = $this->paths();

        if ($paths === []) {
            $warnings[] = 'No feature tour config files were found.';

            return [
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        foreach ($paths as $path) {
            $file = $this->readConfigFile($path);

            if ($file['error'] !== null) {
                $errors[] = $file['error'];

                continue;
            }

            $fileConfig = $file['config'];

            if (! is_array($fileConfig)) {
                continue;
            }

            if (array_key_exists('__file', $fileConfig) && ! is_array($fileConfig['__file'])) {
                $errors[] = "Feature tour config file [{$path}] __file must be an object when provided.";

                continue;
            }

            if (
                is_array($fileConfig['__file'] ?? null)
                && array_key_exists('enabled', $fileConfig['__file'])
                && ! is_bool($fileConfig['__file']['enabled'])
            ) {
                $errors[] = "Feature tour config file [{$path}] __file.enabled must be true or false.";

                continue;
            }

            if (! $this->isConfigFileEnabled($fileConfig)) {
                continue;
            }

            $config = $this->mergeConfig($config, $this->stripFileMeta($fileConfig));
        }

        if ($config === []) {
            $warnings[] = 'Feature tour config files were found, but no usable configuration data was loaded.';

            return [
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        $anchors = $this->normalizeAnchors($config['anchors'] ?? []);
        $actions = $this->normalizeActions($config['actions'] ?? []);
        $tours = $this->normalizeTours($config['tours'] ?? []);
        $availableDataTourMarkers = $this->collectDataTourMarkersFromViews();
        $referencedAnchors = [];
        $referencedActions = [];

        if (! is_numeric($config['version'] ?? null)) {
            $warnings[] = 'Top-level version should be an integer.';
        }

        foreach (($config['anchors'] ?? []) as $anchorName => $anchorDefinition) {
            if (! is_string($anchorName) || trim($anchorName) === '') {
                $errors[] = 'Every anchor must be a keyed value under anchors.';

                continue;
            }

            if (is_string($anchorDefinition)) {
                if (trim($anchorDefinition) === '') {
                    $errors[] = "Anchor [{$anchorName}] selector cannot be empty.";
                }

                continue;
            }

            if (! is_array($anchorDefinition)) {
                $errors[] = "Anchor [{$anchorName}] must be a selector string or an object with selector/view.";

                continue;
            }

            $selector = is_string($anchorDefinition['selector'] ?? null)
                ? trim((string) $anchorDefinition['selector'])
                : '';

            if ($selector === '') {
                $errors[] = "Anchor [{$anchorName}] must define a non-empty selector.";
            }

            if (array_key_exists('view', $anchorDefinition)) {
                $view = is_string($anchorDefinition['view'])
                    ? strtolower(trim((string) $anchorDefinition['view']))
                    : '';

                if (! in_array($view, ['individual', 'multiple', 'surround'], true)) {
                    $errors[] = "Anchor [{$anchorName}] has unsupported view [".(string) $anchorDefinition['view'].'].';
                }
            }
        }

        foreach (($config['actions'] ?? []) as $name => $action) {
            if (! is_string($name) || ! is_array($action)) {
                $errors[] = 'Every action must be a keyed object under actions.';

                continue;
            }

            $type = is_string($action['type'] ?? null) ? $action['type'] : null;

            if (! in_array($type, ['ensure-visible', 'click', 'set-checked'], true)) {
                $errors[] = "Action [{$name}] has unsupported type [".(string) $type.'].';
            }

            if ($type === 'set-checked' && array_key_exists('checked', $action) && ! is_bool($action['checked'])) {
                $errors[] = "Action [{$name}] field [checked] must be true or false for type [set-checked].";
            }

            foreach (['target', 'until_visible'] as $field) {
                $value = $action[$field] ?? null;

                if (! is_string($value) || trim($value) === '') {
                    continue;
                }

                if ($this->isSelectorReference($value)) {
                    continue;
                }

                if (! array_key_exists($value, $anchors)) {
                    $errors[] = "Action [{$name}] references unknown anchor [{$value}] in [{$field}].";

                    continue;
                }

                $referencedAnchors[$value] = true;

                $resolvedSelector = $this->resolveSelectorReference((string) $value, $anchors);

                if (! is_string($resolvedSelector)) {
                    continue;
                }

                $missingDataTourMarker = $this->missingDataTourMarker($resolvedSelector, $availableDataTourMarkers);

                if ($missingDataTourMarker !== null) {
                    $errors[] = "Action [{$name}] [{$field}] resolves to missing data-tour marker [{$missingDataTourMarker}] in resources/views.";
                }
            }
        }

        foreach (($config['tours'] ?? []) as $tourId => $tour) {
            if (! is_string($tourId) || ! is_array($tour)) {
                $errors[] = 'Every tour must be a keyed object under tours.';

                continue;
            }

            if (array_key_exists('enabled', $tour) && ! is_bool($tour['enabled'])) {
                $errors[] = "Tour [{$tourId}] enabled must be true or false.";

                continue;
            }

            if (! $this->isTourEnabled($tour)) {
                continue;
            }

            if (! array_key_exists($tourId, $tours)) {
                $errors[] = "Tour [{$tourId}] is invalid or has no valid variants/steps.";

                continue;
            }

            $triggerMode = (string) (($tour['trigger']['mode'] ?? 'auto'));
            $trigger = is_array($tour['trigger'] ?? null) ? $tour['trigger'] : [];

            if (! in_array($triggerMode, ['auto', 'prompt', 'button', 'info-icon', 'info-icon-always'], true)) {
                $errors[] = "Tour [{$tourId}] has unsupported trigger mode [{$triggerMode}].";
            }

            if (array_key_exists('admin_only', $tour) && ! is_bool($tour['admin_only'])) {
                $errors[] = "Tour [{$tourId}] admin_only must be true or false.";
            }

            if (array_key_exists('show_info_icon', $trigger) && ! is_bool($trigger['show_info_icon'])) {
                $errors[] = "Tour [{$tourId}] trigger.show_info_icon must be true or false.";
            }

            if (array_key_exists('modal', $trigger) && ! is_array($trigger['modal'])) {
                $errors[] = "Tour [{$tourId}] trigger.modal must be an object when provided.";
            }

            if (is_array($trigger['modal'] ?? null)) {
                $modalId = is_string($trigger['modal']['id'] ?? null)
                    ? trim((string) $trigger['modal']['id'])
                    : '';

                if ($modalId === '') {
                    $errors[] = "Tour [{$tourId}] trigger.modal.id must be a non-empty string when trigger.modal is provided.";
                }
            }

            if ($triggerMode === 'prompt') {
                $question = trim((string) (($tour['trigger']['prompt']['question'] ?? '')));

                if ($question === '') {
                    $errors[] = "Tour [{$tourId}] uses prompt trigger but has no prompt.question.";
                }
            }

            if ($triggerMode === 'button') {
                $target = trim((string) (($tour['trigger']['button']['target'] ?? '')));

                if ($target === '') {
                    $errors[] = "Tour [{$tourId}] uses button trigger but has no button.target.";
                } elseif (! $this->isSelectorReference($target) && ! array_key_exists($target, $anchors)) {
                    $errors[] = "Tour [{$tourId}] button.target references unknown anchor [{$target}].";
                } else {
                    if (! $this->isSelectorReference($target)) {
                        $referencedAnchors[$target] = true;
                    }

                    $resolvedSelector = $this->resolveSelectorReference($target, $anchors);
                    $missingDataTourMarker = is_string($resolvedSelector)
                        ? $this->missingDataTourMarker($resolvedSelector, $availableDataTourMarkers)
                        : null;

                    if ($missingDataTourMarker !== null) {
                        $errors[] = "Tour [{$tourId}] button.target resolves to missing data-tour marker [{$missingDataTourMarker}] in resources/views.";
                    }
                }
            }

            foreach (($tour['variants'] ?? []) as $variantId => $variant) {
                if (! is_string($variantId) || ! is_array($variant)) {
                    continue;
                }

                foreach (($variant['steps'] ?? []) as $stepIndex => $step) {
                    if (! is_array($step)) {
                        continue;
                    }

                    if (array_key_exists('admin_only', $step) && ! is_bool($step['admin_only'])) {
                        $errors[] = "Tour [{$tourId}] variant [{$variantId}] step [{$stepIndex}] admin_only must be true or false.";
                    }

                    $rawTarget = $step['target'] ?? null;
                    $target = is_string($rawTarget) ? trim($rawTarget) : '';

                    if ($target === '') {
                        continue;
                    }

                    if (! $this->isSelectorReference($target) && ! array_key_exists($target, $anchors)) {
                        $errors[] = "Tour [{$tourId}] variant [{$variantId}] step [{$stepIndex}] references unknown anchor [{$target}].";
                    } else {
                        if (! $this->isSelectorReference($target)) {
                            $referencedAnchors[$target] = true;
                        }

                        $resolvedSelector = $this->resolveSelectorReference($target, $anchors);
                        $missingDataTourMarker = is_string($resolvedSelector)
                            ? $this->missingDataTourMarker($resolvedSelector, $availableDataTourMarkers)
                            : null;

                        if ($missingDataTourMarker !== null) {
                            $errors[] = "Tour [{$tourId}] variant [{$variantId}] step [{$stepIndex}] target resolves to missing data-tour marker [{$missingDataTourMarker}] in resources/views.";
                        }
                    }

                    foreach (['before', 'after', 'next', 'back'] as $hookName) {
                        foreach (($step[$hookName] ?? []) as $actionName) {
                            if (! is_string($actionName) || trim($actionName) === '') {
                                continue;
                            }

                            if (! array_key_exists($actionName, $actions)) {
                                $errors[] = "Tour [{$tourId}] variant [{$variantId}] step [{$stepIndex}] references unknown action [{$actionName}] in [{$hookName}].";
                            } else {
                                $referencedActions[$actionName] = true;
                            }
                        }
                    }
                }
            }
        }

        foreach (array_keys($anchors) as $anchorName) {
            if (! array_key_exists($anchorName, $referencedAnchors)) {
                $warnings[] = "Anchor [{$anchorName}] is defined but never referenced by any tour target or action.";
            }
        }

        foreach (array_keys($actions) as $actionName) {
            if (! array_key_exists($actionName, $referencedActions)) {
                $warnings[] = "Action [{$actionName}] is defined but never referenced by any tour step or trigger.";
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mergedConfig(): array
    {
        $paths = $this->paths();

        if ($paths === []) {
            return [];
        }

        $cacheKey = $this->mergedConfigCacheKey($paths);

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($paths): array {
            $config = [];

            foreach ($paths as $path) {
                $file = $this->readConfigFile($path);

                if ($file['error'] !== null) {
                    continue;
                }

                $fileConfig = $file['config'];

                if (! is_array($fileConfig)) {
                    continue;
                }

                if (! $this->isConfigFileEnabled($fileConfig)) {
                    continue;
                }

                $config = $this->mergeConfig($config, $this->stripFileMeta($fileConfig));
            }

            return $config;
        });
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function mergedConfigCacheKey(array $paths): string
    {
        $fingerprints = [];

        foreach ($paths as $path) {
            if (! File::exists($path)) {
                continue;
            }

            $hash = md5_file($path);

            if ($hash === false) {
                continue;
            }

            $fingerprints[] = $path.'|'.$hash;
        }

        return self::MERGED_CONFIG_CACHE_KEY_PREFIX.sha1(implode(';', $fingerprints));
    }

    /**
     * @return array<string, mixed>
     */
    private function readConfigFile(string $path): array
    {
        if (! File::exists($path)) {
            return [
                'config' => [],
                'error' => "Feature tour config file [{$path}] does not exist.",
            ];
        }

        try {
            $parsed = Yaml::parseFile($path);
        } catch (Throwable $exception) {
            $message = trim($exception->getMessage());

            if ($message === '') {
                $message = 'unknown parser error';
            }

            return [
                'config' => [],
                'error' => "Feature tour config file [{$path}] could not be parsed: {$message}",
            ];
        }

        return [
            'config' => is_array($parsed) ? $parsed : [],
            'error' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function mergeConfig(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (
                array_key_exists($key, $base)
                && is_array($base[$key])
                && is_array($value)
                && $this->isAssociativeArray($base[$key])
                && $this->isAssociativeArray($value)
            ) {
                $base[$key] = $this->mergeConfig($base[$key], $value);

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @param  array<mixed>  $value
     */
    private function isAssociativeArray(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }

    /**
     * @return array<string, array{selector: string, view: string}>
     */
    private function normalizeAnchors(mixed $anchors): array
    {
        if (! is_array($anchors)) {
            return [];
        }

        $normalized = [];

        foreach ($anchors as $name => $selector) {
            if (! is_string($name)) {
                continue;
            }

            if (is_string($selector) && trim($selector) !== '') {
                $normalized[$name] = [
                    'selector' => trim($selector),
                    'view' => 'individual',
                ];

                continue;
            }

            if (! is_array($selector)) {
                continue;
            }

            $anchorSelector = is_string($selector['selector'] ?? null)
                ? trim((string) $selector['selector'])
                : '';

            if ($anchorSelector === '') {
                continue;
            }

            $view = is_string($selector['view'] ?? null)
                ? strtolower(trim((string) $selector['view']))
                : 'individual';

            if (! in_array($view, ['individual', 'multiple', 'surround'], true)) {
                $view = 'individual';
            }

            $normalized[$name] = [
                'selector' => $anchorSelector,
                'view' => $view,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function normalizeActions(mixed $actions): array
    {
        if (! is_array($actions)) {
            return [];
        }

        $normalized = [];

        foreach ($actions as $name => $action) {
            if (! is_string($name) || ! is_array($action)) {
                continue;
            }

            $type = is_string($action['type'] ?? null) ? $action['type'] : null;

            if ($type === null) {
                continue;
            }

            $normalized[$name] = [
                'type' => $type,
                'target' => is_string($action['target'] ?? null) ? $action['target'] : null,
                'until_visible' => is_string($action['until_visible'] ?? null) ? $action['until_visible'] : null,
                'checked' => is_bool($action['checked'] ?? null) ? $action['checked'] : true,
                'wait_ms' => max(0, (int) ($action['wait_ms'] ?? 120)),
                'max_attempts' => max(1, (int) ($action['max_attempts'] ?? 1)),
                'click_count' => max(1, (int) ($action['click_count'] ?? 1)),
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function normalizeTours(mixed $tours): array
    {
        if (! is_array($tours)) {
            return [];
        }

        $normalized = [];

        foreach ($tours as $tourId => $tour) {
            if (! is_string($tourId) || ! is_array($tour)) {
                continue;
            }

            if (! $this->isTourEnabled($tour)) {
                continue;
            }

            $variants = [];

            if (is_array($tour['variants'] ?? null)) {
                foreach ($tour['variants'] as $variantId => $variant) {
                    if (! is_string($variantId) || ! is_array($variant)) {
                        continue;
                    }

                    $steps = [];

                    foreach (($variant['steps'] ?? []) as $step) {
                        if (! is_array($step)) {
                            continue;
                        }

                        $title = is_string($step['title'] ?? null) ? trim($step['title']) : '';
                        $body = is_string($step['body'] ?? null) ? trim($step['body']) : '';
                        $target = is_string($step['target'] ?? null) ? trim($step['target']) : '';

                        if ($title === '' || $body === '') {
                            continue;
                        }

                        $before = [];
                        $after = [];
                        $next = [];
                        $back = [];

                        foreach (($step['before'] ?? []) as $actionName) {
                            if (is_string($actionName) && trim($actionName) !== '') {
                                $before[] = $actionName;
                            }
                        }

                        foreach (($step['after'] ?? []) as $actionName) {
                            if (is_string($actionName) && trim($actionName) !== '') {
                                $after[] = $actionName;
                            }
                        }

                        foreach (($step['next'] ?? []) as $actionName) {
                            if (is_string($actionName) && trim($actionName) !== '') {
                                $next[] = $actionName;
                            }
                        }

                        foreach (($step['back'] ?? []) as $actionName) {
                            if (is_string($actionName) && trim($actionName) !== '') {
                                $back[] = $actionName;
                            }
                        }

                        $normalizedStep = [
                            'title' => $title,
                            'body' => $body,
                            'admin_only' => (bool) ($step['admin_only'] ?? false),
                            'before' => $before,
                            'after' => $after,
                            'next' => $next,
                            'back' => $back,
                        ];

                        if ($target !== '') {
                            $normalizedStep['target'] = $target;
                        }

                        $steps[] = $normalizedStep;
                    }

                    if ($steps === []) {
                        continue;
                    }

                    $variants[$variantId] = [
                        'media_query' => is_string($variant['media_query'] ?? null)
                            ? $variant['media_query']
                            : '(min-width: 0px)',
                        'steps' => $steps,
                    ];
                }
            }

            if ($variants === []) {
                continue;
            }

            $routes = [];

            foreach (($tour['routes'] ?? []) as $routePattern) {
                if (is_string($routePattern) && trim($routePattern) !== '') {
                    $routes[] = $routePattern;
                }
            }

            $normalized[$tourId] = [
                'once_key' => is_string($tour['once_key'] ?? null) && trim((string) $tour['once_key']) !== ''
                    ? (string) $tour['once_key']
                    : $tourId,
                'authenticated' => (bool) ($tour['authenticated'] ?? true),
                'admin_only' => (bool) ($tour['admin_only'] ?? false),
                'priority' => (int) ($tour['priority'] ?? 100),
                'routes' => $routes,
                'trigger' => $this->normalizeTrigger($tour['trigger'] ?? []),
                'variants' => $variants,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeTrigger(mixed $trigger): array
    {
        if (! is_array($trigger)) {
            $trigger = [];
        }

        $mode = is_string($trigger['mode'] ?? null)
            ? strtolower(trim((string) $trigger['mode']))
            : 'auto';

        if (! in_array($mode, ['auto', 'prompt', 'button', 'info-icon', 'info-icon-always'], true)) {
            $mode = 'auto';
        }

        return [
            'mode' => $mode,
            'show_info_icon' => is_bool($trigger['show_info_icon'] ?? null)
                ? $trigger['show_info_icon']
                : false,
            'modal' => [
                'id' => is_string($trigger['modal']['id'] ?? null)
                    ? trim((string) $trigger['modal']['id'])
                    : '',
            ],
            'prompt' => [
                'title' => $this->resolveString($trigger['prompt']['title'] ?? null, 'Take a quick tour?'),
                'question' => $this->resolveString($trigger['prompt']['question'] ?? null, 'Would you like a quick feature tour?'),
                'resume_hint' => $this->resolveString($trigger['prompt']['resume_hint'] ?? null, 'No problem, click the info icon in the navigation bar when you are ready to start the tour.'),
                'confirm_label' => $this->resolveString($trigger['prompt']['confirm_label'] ?? null, 'Start tour'),
                'cancel_label' => $this->resolveString($trigger['prompt']['cancel_label'] ?? null, 'Not now'),
                'opt_out_label' => $this->resolveString($trigger['prompt']['opt_out_label'] ?? null, 'Not interested'),
            ],
            'button' => [
                'target' => is_string($trigger['button']['target'] ?? null) ? trim((string) $trigger['button']['target']) : '',
                'event' => $this->resolveString($trigger['button']['event'] ?? null, 'click'),
            ],
        ];
    }

    private function isSelectorReference(string $value): bool
    {
        $trimmed = trim($value);

        return str_starts_with($trimmed, '[')
            || str_starts_with($trimmed, '.')
            || str_starts_with($trimmed, '#');
    }

    /**
     * @param  array<string, array{selector: string, view: string}>  $anchors
     */
    private function resolveSelectorReference(string $reference, array $anchors): ?string
    {
        $trimmedReference = trim($reference);

        if ($trimmedReference === '') {
            return null;
        }

        if ($this->isSelectorReference($trimmedReference)) {
            return $trimmedReference;
        }

        return $anchors[$trimmedReference]['selector'] ?? null;
    }

    /**
     * @return array<string, bool>
     */
    private function collectDataTourMarkersFromViews(): array
    {
        $viewsDirectory = resource_path('views');

        if (! File::isDirectory($viewsDirectory)) {
            return [];
        }

        $markers = [];

        foreach (File::allFiles($viewsDirectory) as $file) {
            $contents = File::get($file->getPathname());

            if (! is_string($contents) || $contents === '') {
                continue;
            }

            if (! preg_match_all('/data-tour\s*=\s*(["\'])([^"\']+)\1/', $contents, $matches)) {
                continue;
            }

            foreach ($matches[2] as $marker) {
                if (! is_string($marker) || trim($marker) === '') {
                    continue;
                }

                $markers[$marker] = true;
            }
        }

        return $markers;
    }

    /**
     * @param  array<string, bool>  $availableDataTourMarkers
     */
    private function missingDataTourMarker(string $selector, array $availableDataTourMarkers): ?string
    {
        if (! preg_match('/\[data-tour\s*=\s*["\']([^"\']+)["\']\]/', $selector, $matches)) {
            return null;
        }

        $marker = trim((string) ($matches[1] ?? ''));

        if ($marker === '') {
            return null;
        }

        return array_key_exists($marker, $availableDataTourMarkers) ? null : $marker;
    }

    private function resolveString(mixed $value, string $fallback): string
    {
        if (! is_string($value)) {
            return $fallback;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? $fallback : $trimmed;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function isConfigFileEnabled(array $config): bool
    {
        if (! is_array($config['__file'] ?? null)) {
            return true;
        }

        if (! array_key_exists('enabled', $config['__file'])) {
            return true;
        }

        return $config['__file']['enabled'] !== false;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function stripFileMeta(array $config): array
    {
        unset($config['__file']);

        return $config;
    }

    /**
     * @param  array<string, mixed>  $tour
     */
    private function isTourEnabled(array $tour): bool
    {
        if (! array_key_exists('enabled', $tour)) {
            return true;
        }

        return $tour['enabled'] !== false;
    }

    /**
     * @return array{completed: array<string, bool>, prompt_dismissed: array<string, bool>, opted_out: array<string, bool>}
     */
    private function normalizeState(mixed $state): array
    {
        if (! is_array($state)) {
            return [
                'completed' => [],
                'prompt_dismissed' => [],
                'opted_out' => [],
            ];
        }

        return [
            'completed' => $this->normalizeStateBucket($state['completed'] ?? []),
            'prompt_dismissed' => $this->normalizeStateBucket($state['prompt_dismissed'] ?? []),
            'opted_out' => $this->normalizeStateBucket($state['opted_out'] ?? []),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function normalizeStateBucket(mixed $bucket): array
    {
        if (! is_array($bucket)) {
            return [];
        }

        $normalized = [];

        foreach ($bucket as $key => $value) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            if ((bool) $value) {
                $normalized[$key] = true;
            }
        }

        return $normalized;
    }
}
