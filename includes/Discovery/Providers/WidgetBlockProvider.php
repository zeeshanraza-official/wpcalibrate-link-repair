<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Discovery\Providers;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Discovery\HtmlLinkExtractor;
use WPCalibrate\LinkRepair\Services\SettingsService;

/**
 * Discovers links inside WordPress widgets and block widgets.
 */
final class WidgetBlockProvider implements ContentProviderInterface
{
    public function getId(): string
    {
        return 'widgets';
    }

    public function getTitle(): string
    {
        return __('Widgets & Block Widgets', 'wpcalibrate-link-repair');
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function countItems(array $options = []): int
    {
        $items = $this->getWidgetOptions();
        return count($items);
    }

    public function getItems(int $offset, int $limit, array $options = []): array
    {
        $items = $this->getWidgetOptions();
        return array_slice($items, $offset, $limit);
    }

    /**
     * Gather supported widget options.
     *
     * @return array<int, array{option_name: string, content: string, widget_id: string}>
     */
    private function getWidgetOptions(): array
    {
        $widgetData = [];
        $supportedWidgetKeys = ['widget_block', 'widget_custom_html', 'widget_text'];

        foreach ($supportedWidgetKeys as $optionKey) {
            $opt = get_option($optionKey);
            if (!is_array($opt)) {
                continue;
            }

            foreach ($opt as $id => $val) {
                if (!is_numeric($id) || !is_array($val)) {
                    continue;
                }

                $content = $val['content'] ?? $val['text'] ?? '';
                if (is_string($content) && trim($content) !== '') {
                    $widgetData[] = [
                        'option_name' => $optionKey,
                        'widget_id'   => "{$optionKey}:{$id}",
                        'content'     => $content,
                        'id'          => (int) $id,
                    ];
                }
            }
        }

        return $widgetData;
    }

    public function extractUrls(mixed $item): array
    {
        if (!is_array($item) || empty($item['content'])) {
            return [];
        }

        $scanMedia = (bool) SettingsService::get('scan_media', true);
        return HtmlLinkExtractor::extract(
            (string) $item['content'],
            'widget',
            (int) ($item['id'] ?? 0),
            (string) ($item['option_name'] ?? 'widget_block'),
            $scanMedia
        );
    }
}
