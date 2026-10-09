<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Jobs;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Factory for instantiating the appropriate background job scheduler.
 */
final class SchedulerFactory
{
    private static ?JobSchedulerInterface $instance = null;

    public static function getScheduler(): JobSchedulerInterface
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $as = new ActionSchedulerService();
        if ($as->isAvailable()) {
            self::$instance = $as;
        } else {
            self::$instance = new WpCronScheduler();
        }

        return self::$instance;
    }

    public static function setScheduler(?JobSchedulerInterface $scheduler): void
    {
        self::$instance = $scheduler;
    }
}
