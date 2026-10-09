<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Jobs;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Services\Logger;

/**
 * Standard WordPress Cron scheduler fallback.
 */
final class WpCronScheduler implements JobSchedulerInterface
{
    public function __construct()
    {
        add_filter('cron_schedules', [$this, 'registerCustomIntervals']);
    }

    /**
     * Register weekly and monthly cron schedules if not present.
     *
     * @param array<string, array<string, mixed>> $schedules
     * @return array<string, array<string, mixed>>
     */
    public function registerCustomIntervals(array $schedules): array
    {
        if (!isset($schedules['weekly'])) {
            $schedules['weekly'] = [
                'interval' => WEEK_IN_SECONDS,
                'display'  => __('Once Weekly', 'wpcalibrate-link-repair'),
            ];
        }
        if (!isset($schedules['monthly'])) {
            $schedules['monthly'] = [
                'interval' => 30 * DAY_IN_SECONDS,
                'display'  => __('Once Monthly', 'wpcalibrate-link-repair'),
            ];
        }
        return $schedules;
    }

    public function dispatchBatch(string $hook, array $args = []): bool
    {
        // Prevent duplicate single event if already scheduled
        if (wp_next_scheduled($hook, $args)) {
            return true;
        }

        $scheduled = wp_schedule_single_event(time(), $hook, $args);
        if ($scheduled === false) {
            Logger::error("Failed to schedule WP-Cron single event for {$hook}");
            return false;
        }

        // Spawn cron immediately in background so the user doesn't have to wait for next page load
        if (function_exists('spawn_cron')) {
            spawn_cron();
        }

        return true;
    }

    public function scheduleRecurring(string $hook, string $frequency, int $timestamp): bool
    {
        $this->clearScheduled($hook);

        $allowedRecurrences = ['daily', 'weekly', 'monthly'];
        $recurrence = in_array($frequency, $allowedRecurrences, true) ? $frequency : 'weekly';

        $scheduled = wp_schedule_event($timestamp, $recurrence, $hook);
        if ($scheduled === false) {
            Logger::error("Failed to schedule WP-Cron recurring event for {$hook}");
            return false;
        }

        return true;
    }

    public function clearScheduled(string $hook): void
    {
        $timestamp = wp_next_scheduled($hook);
        while ($timestamp !== false) {
            wp_unschedule_event($timestamp, $hook);
            $timestamp = wp_next_scheduled($hook);
        }
    }
}
