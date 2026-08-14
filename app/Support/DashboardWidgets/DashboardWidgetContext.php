<?php

namespace App\Support\DashboardWidgets;

use App\Models\User;

final class DashboardWidgetContext
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(
        public readonly ?User $user = null,
        public readonly array $values = [],
    ) {}

    public function withUser(?User $user): self
    {
        return new self($user, $this->values);
    }

    public function withValue(string $key, mixed $value): self
    {
        return new self($this->user, [
            ...$this->values,
            $key => $value,
        ]);
    }

    public function value(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }
}
