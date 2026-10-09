<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Discovery;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Http\UrlNormalizer;

/**
 * Parses HTML and Gutenberg block content to extract links, images, and media tags.
 */
final class HtmlLinkExtractor
{
    /**
     * Extract links, images, and media references from an HTML string.
     *
     * @param string $html
     * @param string $sourceType
     * @param int $sourceId
     * @param string $fieldKey
     * @param bool $scanMedia
     * @return array<int, array{
     *     raw_url: string,
     *     link_type: string,
     *     source_type: string,
     *     source_id: int,
     *     field_key: string,
     *     context_path: string,
     *     anchor_text: ?string,
     *     tag_attributes: array<string, string>,
     *     raw_fragment: ?string
     * }>
     */
    public static function extract(
        string $html,
        string $sourceType,
        int $sourceId,
        string $fieldKey = 'post_content',
        bool $scanMedia = true
    ): array {
        $results = [];
        if (trim($html) === '') {
            return $results;
        }

        // 1. Extract <a> tags with href
        if (preg_match_all('/<a\b([^>]*)>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER)) {
            $index = 0;
            foreach ($matches as $match) {
                $attrsStr = $match[1];
                $anchorInner = $match[2];

                $href = self::extractAttribute($attrsStr, 'href');
                if ($href !== null && trim($href) !== '') {
                    $cleanHref = trim($href);
                    $fragment = UrlNormalizer::extractFragment($cleanHref);
                    $attrs = self::parseAttributes($attrsStr);
                    // Strip tags from anchor text for readable display
                    $plainAnchor = trim(wp_strip_all_tags($anchorInner));
                    if (mb_strlen($plainAnchor) > 250) {
                        $plainAnchor = mb_substr($plainAnchor, 0, 247) . '...';
                    }

                    $results[] = [
                        'raw_url'        => $cleanHref,
                        'link_type'      => 'link',
                        'source_type'    => $sourceType,
                        'source_id'      => $sourceId,
                        'field_key'      => $fieldKey,
                        'context_path'   => "a:{$index}",
                        'anchor_text'    => $plainAnchor !== '' ? $plainAnchor : null,
                        'tag_attributes' => $attrs,
                        'raw_fragment'   => $fragment,
                    ];
                    $index++;
                }
            }
        }

        if (!$scanMedia) {
            return $results;
        }

        // 2. Extract <img> tags with src
        if (preg_match_all('/<img\b([^>]*)\/?>/is', $html, $imgMatches, PREG_SET_ORDER)) {
            $imgIndex = 0;
            foreach ($imgMatches as $match) {
                $attrsStr = $match[1];
                $src = self::extractAttribute($attrsStr, 'src');
                if ($src !== null && trim($src) !== '') {
                    $cleanSrc = trim($src);
                    $attrs = self::parseAttributes($attrsStr);
                    $alt = $attrs['alt'] ?? null;

                    $results[] = [
                        'raw_url'        => $cleanSrc,
                        'link_type'      => 'image',
                        'source_type'    => $sourceType,
                        'source_id'      => $sourceId,
                        'field_key'      => $fieldKey,
                        'context_path'   => "img:{$imgIndex}",
                        'anchor_text'    => $alt !== null ? "Image: {$alt}" : 'Image',
                        'tag_attributes' => $attrs,
                        'raw_fragment'   => null,
                    ];
                    $imgIndex++;
                }
            }
        }

        // 3. Extract <source src="...">, <video src="...">, <audio src="...">
        if (preg_match_all('/<(video|audio|source)\b([^>]*)\/?>/is', $html, $mediaMatches, PREG_SET_ORDER)) {
            $mediaIndex = 0;
            foreach ($mediaMatches as $match) {
                $tag = strtolower($match[1]);
                $attrsStr = $match[2];
                $src = self::extractAttribute($attrsStr, 'src');
                if ($src !== null && trim($src) !== '') {
                    $cleanSrc = trim($src);
                    $attrs = self::parseAttributes($attrsStr);

                    $results[] = [
                        'raw_url'        => $cleanSrc,
                        'link_type'      => 'media',
                        'source_type'    => $sourceType,
                        'source_id'      => $sourceId,
                        'field_key'      => $fieldKey,
                        'context_path'   => "{$tag}:{$mediaIndex}",
                        'anchor_text'    => ucfirst($tag) . ' source',
                        'tag_attributes' => $attrs,
                        'raw_fragment'   => null,
                    ];
                    $mediaIndex++;
                }
            }
        }

        return $results;
    }

    /**
     * Extract a specific attribute value from an attribute string.
     */
    public static function extractAttribute(string $attrsStr, string $attrName): ?string
    {
        $pattern = '/\b' . preg_quote($attrName, '/') . '\s*=\s*(["\'])(.*?)\1/is';
        if (preg_match($pattern, $attrsStr, $match)) {
            return html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Fallback for unquoted attributes
        $patternUnquoted = '/\b' . preg_quote($attrName, '/') . '\s*=\s*([^\s>]+)/is';
        if (preg_match($patternUnquoted, $attrsStr, $match)) {
            return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
    }

    /**
     * Parse HTML attributes into key-value map.
     *
     * @return array<string, string>
     */
    public static function parseAttributes(string $attrsStr): array
    {
        $attributes = [];
        if (preg_match_all('/([a-zA-Z0-9_\-]+)\s*=\s*(["\'])(.*?)\2/is', $attrsStr, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = strtolower($match[1]);
                $attributes[$key] = html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        return $attributes;
    }
}
