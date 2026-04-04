<?php

namespace Modules\Core\Services;

/**
 * Hook System - Similar to WordPress actions and filters.
 */
class HookService
{
    protected array $actions = [];
    protected array $filters = [];

    /**
     * Add an action hook.
     */
    public function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        if (!isset($this->actions[$hook])) {
            $this->actions[$hook] = [];
        }

        $this->actions[$hook][$priority][] = $callback;
    }

    /**
     * Remove an action hook.
     */
    public function removeAction(string $hook, callable $callback, int $priority = 10): void
    {
        if (isset($this->actions[$hook][$priority])) {
            $this->actions[$hook][$priority] = array_filter(
                $this->actions[$hook][$priority],
                fn ($cb) => $cb !== $callback
            );
        }
    }

    /**
     * Execute an action hook.
     */
    public function doAction(string $hook, ...$args): void
    {
        if (!isset($this->actions[$hook])) {
            return;
        }

        ksort($this->actions[$hook]);

        foreach ($this->actions[$hook] as $callbacks) {
            foreach ($callbacks as $callback) {
                call_user_func_array($callback, $args);
            }
        }
    }

    /**
     * Check if an action hook exists.
     */
    public function hasAction(string $hook): bool
    {
        return isset($this->actions[$hook]) && !empty($this->actions[$hook]);
    }

    /**
     * Add a filter hook.
     */
    public function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        if (!isset($this->filters[$hook])) {
            $this->filters[$hook] = [];
        }

        $this->filters[$hook][$priority][] = $callback;
    }

    /**
     * Remove a filter hook.
     */
    public function removeFilter(string $hook, callable $callback, int $priority = 10): void
    {
        if (isset($this->filters[$hook][$priority])) {
            $this->filters[$hook][$priority] = array_filter(
                $this->filters[$hook][$priority],
                fn ($cb) => $cb !== $callback
            );
        }
    }

    /**
     * Apply a filter hook.
     */
    public function applyFilters(string $hook, mixed $value, ...$args): mixed
    {
        if (!isset($this->filters[$hook])) {
            return $value;
        }

        ksort($this->filters[$hook]);

        foreach ($this->filters[$hook] as $callbacks) {
            foreach ($callbacks as $callback) {
                $value = call_user_func_array($callback, [$value, ...$args]);
            }
        }

        return $value;
    }

    /**
     * Check if a filter hook exists.
     */
    public function hasFilter(string $hook): bool
    {
        return isset($this->filters[$hook]) && !empty($this->filters[$hook]);
    }

    /**
     * Get all registered actions.
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * Get all registered filters.
     */
    public function getFilters(): array
    {
        return $this->filters;
    }
}
