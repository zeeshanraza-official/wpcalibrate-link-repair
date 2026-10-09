<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Jobs;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for background job dispatchers (Action Scheduler or WP-Cron).
 */
interface JobSchedulerInterface
{
    /**
     * Dispatch an asynchronous single batch execution.
     *
     * @param string $hook Action hook name.
     * @param array<string, mixed> $args Arguments passed to the worker hook.
     * @return bool
     */
    public function dispatchBatch(string $hook, array $args = []): bool;

    /**
     * Schedule a recurring background job.
     *
     * @param string $hook Action hook name.
     * @param string $frequency 'daily', 'weekly', 'monthly'.
     * @param int $timestamp Start timestamp.
     * @return bool
     */
    public function scheduleRecurring(string $hook, string $frequency, int $timestamp): bool;

    /**
     * Clear / unschedule a recurring or pending job.
     *
     * @param string $hook Action hook name.
     */
    public function clearScheduled(string $hook): void;
}
