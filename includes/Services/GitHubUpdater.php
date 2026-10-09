<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles automated plugin updates directly from GitHub releases in WordPress admin.
 */
final class GitHubUpdater
{
    private string $owner;
    private string $repo;
    private string $file;
    private string $slug;
    private string $basename;
    private string $version;

    public function __construct(string $owner, string $repo, string $file, string $version)
    {
        $this->owner    = $owner;
        $this->repo     = $repo;
        $this->file     = $file;
        $this->slug     = dirname(plugin_basename($file));
        $this->basename = plugin_basename($file);
        $this->version  = $version;
    }

    /**
     * Initialize update hooks.
     */
    public function init(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'checkUpdate']);
        add_filter('plugins_api', [$this, 'pluginInfo'], 20, 3);
        add_filter('upgrader_post_install', [$this, 'postInstall'], 10, 3);
    }

    /**
     * Check GitHub for new release tag and inject update package if available.
     *
     * @param mixed $transient
     * @return mixed
     */
    public function checkUpdate(mixed $transient): mixed
    {
        if (!is_object($transient)) {
            $transient = new \stdClass();
        }

        $release = $this->getLatestRelease();
        if (!$release) {
            return $transient;
        }

        $newVersion = ltrim((string) ($release->tag_name ?? ''), 'v');
        if (version_compare($newVersion, $this->version, '>')) {
            $package = $this->findDownloadPackage($release);

            $obj = new \stdClass();
            $obj->id           = "github.com/{$this->owner}/{$this->repo}";
            $obj->slug         = $this->slug;
            $obj->plugin       = $this->basename;
            $obj->new_version  = $newVersion;
            $obj->url          = (string) ($release->html_url ?? "https://github.com/{$this->owner}/{$this->repo}");
            $obj->package      = $package;
            $obj->icons        = [
                '1x'  => WPCALIBRATE_LINK_REPAIR_URL . 'branding/icon-menu.png',
                '2x'  => WPCALIBRATE_LINK_REPAIR_URL . 'branding/icon-menu-2x.png',
                'svg' => WPCALIBRATE_LINK_REPAIR_URL . 'branding/icon-menu.svg',
            ];
            $obj->banners      = [];
            $obj->tested       = '7.1.3';
            $obj->requires_php = '8.2';

            if (!isset($transient->response)) {
                $transient->response = [];
            }
            $transient->response[$this->basename] = $obj;
        }

        return $transient;
    }

    /**
     * Provide details modal info when user clicks "View version details".
     *
     * @param mixed $res
     * @param string $action
     * @param mixed $args
     * @return mixed
     */
    public function pluginInfo(mixed $res, string $action, mixed $args): mixed
    {
        if ($action !== 'plugin_information' || !is_object($args) || ($args->slug ?? '') !== $this->slug) {
            return $res;
        }

        $release = $this->getLatestRelease();
        if (!$release) {
            return $res;
        }

        $newVersion = ltrim((string) ($release->tag_name ?? ''), 'v');

        $info = new \stdClass();
        $info->name          = 'WPCalibrate Link Repair';
        $info->slug          = $this->slug;
        $info->version       = $newVersion;
        $info->author        = '<a href="https://wpcalibrate.com">WPCalibrate</a>';
        $info->homepage      = "https://github.com/{$this->owner}/{$this->repo}";
        $info->requires      = '6.2';
        $info->tested        = '7.1.3';
        $info->requires_php  = '8.2';
        $info->download_link = $this->findDownloadPackage($release);
        $info->last_updated  = (string) ($release->published_at ?? gmdate('Y-m-d'));
        $info->sections      = [
            'description' => __('Scans WordPress content for broken internal and external links, redirects, and missing media, providing safe administrator tools to review and repair them.', 'wpcalibrate-link-repair'),
            'changelog'   => nl2br(esc_html((string) ($release->body ?? 'Bug fixes and performance improvements.'))),
        ];

        return $info;
    }

    /**
     * Ensure correct folder name after unzipping from GitHub.
     *
     * @param bool $true
     * @param array<string, mixed> $hookExtra
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function postInstall(bool $true, array $hookExtra, array $result): array
    {
        global $wp_filesystem;

        if (!$wp_filesystem || !isset($result['destination'])) {
            return $result;
        }

        $pluginDir = WP_PLUGIN_DIR . '/' . $this->slug;
        if ($wp_filesystem->exists($result['destination']) && $result['destination'] !== $pluginDir) {
            $wp_filesystem->move($result['destination'], $pluginDir);
            $result['destination'] = $pluginDir;
        }

        return $result;
    }

    /**
     * Fetch latest release from GitHub API with transient caching.
     *
     * @return object|null
     */
    private function getLatestRelease(): ?object
    {
        $cacheKey = 'wpcalibrate_lr_gh_release';
        $cached = get_site_transient($cacheKey);
        if ($cached !== false && is_object($cached)) {
            return $cached;
        }

        $url = "https://api.github.com/repos/{$this->owner}/{$this->repo}/releases/latest";
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'WPCalibrate-Link-Repair/' . $this->version,
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);
        if (!is_object($data)) {
            return null;
        }

        set_site_transient($cacheKey, $data, 12 * HOUR_IN_SECONDS);
        return $data;
    }

    /**
     * Find zip package URL from release assets or zipball.
     *
     * @param object $release
     * @return string
     */
    private function findDownloadPackage(object $release): string
    {
        if (!empty($release->assets) && is_array($release->assets)) {
            foreach ($release->assets as $asset) {
                if (isset($asset->name, $asset->browser_download_url) && str_ends_with((string) $asset->name, '.zip')) {
                    return (string) $asset->browser_download_url;
                }
            }
        }

        return (string) ($release->zipball_url ?? '');
    }
}
