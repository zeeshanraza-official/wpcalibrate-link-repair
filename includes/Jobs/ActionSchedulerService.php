<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Jobs;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Services\Logger;

/**
 * Action Scheduler implementation (when WooCommerce / Action Scheduler is present).
 */
final class ActionSchedulerService implements JobSchedulerInterface
{
    private const GROUP = 'wpcalibrate-link-repair';

    public function isAvailable(): bool
    {
        return function_exists('as_enqueue_async_action') && function_exists('as_schedule_recurring_action');
    }

    public function dispatchBatch(string $hook, array $args = []): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        // Check if an identical action is already pending/in-progress
        if (function_exists('as_has_scheduled_action') && as_has_scheduled_action($hook, $args, self::GROUP)) {
            return true;
        }

        try {
            as_enqueue_async_action($hook, $args, self::GROUP);
            return true;
        } catch (\Throwable $e) {
            Logger::error('Failed to enqueue Action Scheduler batch: ' . $e->getMessage());
            return false;
        }
    }

    public function scheduleRecurring(string $hook, string $frequency, int $timestamp): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $intervals = [
            'daily'   => DAY_IN_SECONDS,
            'weekly'  => WEEK_IN_SECONDS,
            'monthly' => 30 * DAY_IN_SECONDS,
        ];
        $interval = $intervals[$frequency] ?? WEEK_IN_SECONDS;

        $this->clearScheduled($hook);

        try {
            as_schedule_recurring_action($timestamp, $interval, $hook, [], self::GROUP);
            return true;
        } catch (\Throwable $e) {
            Logger::error('Failed to schedule recurring Action Scheduler job: ' . $e->getMessage());
            return false;
        }
    }

    public function clearScheduled(string $hook): void
    {
        if (function_exists('as_unschedule_all_actions')) {
            try {
                as_unschedule_all_actions($hook, null, self::GROUP);
            } catch (\Throwable $e) {
                Logger::error('Failed to unschedule Action Scheduler job: ' . $e->getMessage());
            }
        }
    }
}
