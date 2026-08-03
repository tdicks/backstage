<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Route;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

class FullPageRouteCatalog
{
    /**
     * @return list<array{name: string, uri: string, label: string}>
     */
    public function options(): array
    {
        $options = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (! $name || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if (! $this->isFullPageRoute($route)) {
                continue;
            }

            $options[] = [
                'name' => $name,
                'uri' => '/'.ltrim($route->uri(), '/'),
                'label' => str($name)->replace(['-', '_', '.'], ' ')->title()->toString(),
            ];
        }

        usort($options, fn (array $a, array $b) => strcmp($a['label'], $b['label']));

        return $options;
    }

    /**
     * @return list<string>
     */
    public function routeNames(): array
    {
        return array_map(fn (array $option) => $option['name'], $this->options());
    }

    private function isFullPageRoute($route): bool
    {
        $uses = $route->getAction('uses');

        if ($uses instanceof Closure) {
            return $this->returnsView(new ReflectionFunction($uses));
        }

        $action = $route->getActionName();
        if (! is_string($action) || $action === 'Closure') {
            return false;
        }

        $class = $action;
        $method = '__invoke';

        if (str_contains($action, '@')) {
            [$class, $method] = explode('@', $action, 2);
        }

        if (! class_exists($class) || ! method_exists($class, $method)) {
            return false;
        }

        return $this->returnsView(new ReflectionMethod($class, $method));
    }

    private function returnsView(ReflectionFunction|ReflectionMethod $reflection): bool
    {
        $returnType = $reflection->getReturnType();

        if (! $returnType instanceof ReflectionType) {
            return false;
        }

        $namedTypes = $returnType instanceof ReflectionUnionType
            ? $returnType->getTypes()
            : [$returnType];

        foreach ($namedTypes as $namedType) {
            if (! $namedType instanceof ReflectionNamedType) {
                continue;
            }

            $typeName = ltrim($namedType->getName(), '\\');

            if (in_array($typeName, ['Illuminate\\View\\View', 'Illuminate\\Contracts\\View\\View'], true)) {
                return true;
            }
        }

        return false;
    }
}
