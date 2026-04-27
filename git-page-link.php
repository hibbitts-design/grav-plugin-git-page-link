<?php

// Developed with the assistance of Claude Code (claude.ai)

namespace Grav\Plugin;

use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;

class GitPageLinkPlugin extends Plugin
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
        ];
    }

    public function onPluginsInitialized(): void
    {
        if ($this->isAdmin()) {
            return;
        }

        $this->enable([
            'onPageContentProcessed' => ['onPageContentProcessed', 0],
            'onTwigSiteVariables'    => ['onTwigSiteVariables', 0],
        ]);
    }

    public function onTwigSiteVariables(): void
    {
        $page = $this->grav['page'];

        if (!$this->shouldShowLink($page)) {
            return;
        }

        $config = $this->mergeConfig($page);
        $this->grav['assets']->addCss('plugin://git-page-link/assets/css/git-page-link.css');
        if ($config->get('dark_mode', false)) {
            $this->grav['assets']->addCss('plugin://git-page-link/assets/css/git-page-link-dark.css');
        }
    }

    public function onPageContentProcessed(Event $event): void
    {
        $page        = $event['page'];
        $currentPage = $this->grav['page'];

        // Only inject on the page actually being routed and displayed.
        if (!$page || !$currentPage || $page->route() !== $currentPage->route()) {
            return;
        }

        if (!$this->shouldShowLink($page)) {
            return;
        }

        $config = $this->mergeConfig($page);
        $url    = $this->buildGitUrl($page, $config);
        if (!$url) {
            return;
        }
        $linkHtml = $this->renderLink($url, $config);
        $position = $config->get('link_position', 'bottom');
        $content  = $page->getRawContent();

        switch ($position) {
            case 'top':
                $page->setRawContent($linkHtml . $content);
                break;
            case 'both':
                $page->setRawContent($linkHtml . $content . $linkHtml);
                break;
            default: // bottom
                $page->setRawContent($content . $linkHtml);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function shouldShowLink($page): bool
    {
        if (!$page || !$page->exists()) {
            return false;
        }

        // Never render inside modular sub-pages.
        if ($page->modular()) {
            return false;
        }

        $config  = $this->mergeConfig($page);
        $allowed = (array) $config->get('page_types', []);

        // The admin array field can produce nested arrays; normalise to a flat list of strings.
        $allowed = array_values(array_filter(array_map(
            static fn($v) => is_array($v) ? (string) reset($v) : (string) $v,
            $allowed
        )));

        // An empty list means all page types are permitted.
        if ($allowed !== [] && !in_array($page->template(), $allowed, true)) {
            return false;
        }

        return true;
    }

    /**
     * Build the remote Git URL.
     * Custom repository URL and branch take priority; falls back to Git Sync config.
     * Returns null silently if neither source provides a repository URL.
     */
    private function buildGitUrl($page, $config): ?string
    {
        $gitSyncConfig = $this->grav['config']->get('plugins.git-sync');

        // Determine the repository URL: custom setting takes priority, then Git Sync.
        $customUrl = trim((string) $config->get('git_repository_url', ''));
        if ($customUrl !== '') {
            $remote = rtrim(preg_replace('/\.git$/', '', $customUrl), '/');
        } elseif (!empty($gitSyncConfig['repository'])) {
            $remote = rtrim((string) $gitSyncConfig['repository'], '/');
            $remote = preg_replace('/\.git$/', '', $remote);
            // Strip any embedded credentials (e.g. https://token@github.com/...).
            $remote = preg_replace('#(https?://)([^@]+@)#', '$1', $remote);
        } else {
            return null;
        }

        // 'repo' mode — link to the repository root, no file path needed.
        if ($config->get('link_mode', 'edit') === 'repo') {
            return $remote;
        }

        // Determine the branch: custom setting takes priority, then Git Sync, then default.
        $customBranch = trim((string) $config->get('git_branch', ''));
        $branch       = $customBranch !== '' ? $customBranch : (string) (($gitSyncConfig ?? [])['branch'] ?? 'main');
        $linkMode     = $config->get('link_mode', 'edit');

        // Git Sync always syncs from user/ to the repo root.
        $filePath = $page->filePath();
        if (!$filePath) {
            return null;
        }
        $gravRoot    = rtrim(GRAV_ROOT, '/');
        $absLocal    = $gravRoot . '/user';

        // Strip the user/ prefix to get the repo-relative path.
        $repoRelPath = ltrim(str_replace($absLocal, '', $filePath), '/');

        if (str_contains($remote, 'github.com')) {
            return $linkMode === 'view'
                ? "{$remote}/blob/{$branch}/{$repoRelPath}"
                : "{$remote}/edit/{$branch}/{$repoRelPath}";
        }

        if (preg_match('/gitlab[.\-]/i', $remote) || str_contains($remote, 'gitlab.com')) {
            return $linkMode === 'view'
                ? "{$remote}/-/blob/{$branch}/{$repoRelPath}"
                : "{$remote}/-/edit/{$branch}/{$repoRelPath}";
        }

        // Gitea / Forgejo / Codeberg / self-hosted
        return $linkMode === 'view'
            ? "{$remote}/src/branch/{$branch}/{$repoRelPath}"
            : "{$remote}/_edit/{$branch}/{$repoRelPath}";
    }

    /**
     * Render the link HTML directly in PHP.
     * Avoids processTemplate() during onPageContentProcessed (Twig not fully
     * initialised at that point). Translation keys resolved via Language service.
     */
    private function renderLink(string $url, $config): string
    {
        $lang      = $this->grav['language'];
        $iconType  = $config->get('icon_type', 'pencil');
        $linkStyle = $config->get('link_style', 'plain') === 'button' ? 'button' : 'plain';

        $linkText  = $config->get('link_text', 'Edit this Page');
        // Translate the default text via the language file; custom values pass through as-is.
        if ($linkText === 'Edit this Page') {
            $linkText = $lang->translate(['PLUGIN_GIT_PAGE_LINK.LINK_TEXT']) ?: $linkText;
        }
        $linkTitle = trim((string) $config->get('link_title', ''));

        $icon = $this->buildIcon($iconType, $config);

        $eUrl      = htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $eLinkText = htmlspecialchars($linkText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $titleAttr  = $linkTitle !== '' ? ' title="' . htmlspecialchars($linkTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"' : '';
        $targetAttr = $config->get('link_new_tab', false) ? ' target="_blank" rel="noopener noreferrer"' : '';

        return '<div class="gpl-wrapper">'
             . '<a href="' . $eUrl . '" class="gpl-link gpl-link--' . $linkStyle . '"' . $titleAttr . $targetAttr . '>'
             . $icon
             . '<span class="gpl-link-text">' . $eLinkText . '</span>'
             . '</a>'
             . '</div>';
    }

    private function buildIcon(string $iconType, $config): string
    {
        if ($iconType === 'none') {
            return '';
        }

        if ($iconType === 'custom') {
            $customSvg = trim((string) $config->get('icon_custom', ''));
            if ($customSvg === '') {
                return '';
            }
            // Full SVG supplied — inject the class attribute.
            if (str_starts_with($customSvg, '<svg')) {
                return preg_replace('/<svg/', '<svg class="gpl-icon"', $customSvg, 1);
            }
            // Inner SVG content only — wrap it.
            return '<svg class="gpl-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
                 . $customSvg
                 . '</svg>';
        }

        $paths = [
            'pencil'   => '<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25z"/>'
                        . '<path d="M20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>',
            'doc'      => '<path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>',
            // Git branch — three commit nodes, vertical main line, curved feature branch
            'branch'   => '<circle cx="6" cy="18.5" r="2.5"/>'
                        . '<circle cx="6" cy="5.5" r="2.5"/>'
                        . '<circle cx="18.5" cy="9" r="2.5"/>'
                        . '<rect x="4.75" y="8" width="2.5" height="7.5" rx="1.25"/>'
                        . '<path d="M6 12 C6 9 13 9 16 9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
            'folder'   => '<path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/>',
        ];

        $path = $paths[$iconType] ?? $paths['pencil'];

        return '<svg class="gpl-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
             . $path
             . '</svg>';
    }
}
