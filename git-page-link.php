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
            'onPageProcessed'        => ['onPageProcessed', 0],
            'onPageContentProcessed' => ['onPageContentProcessed', 0],
            'onTwigSiteVariables'    => ['onTwigSiteVariables', 0],
            'onOutputGenerated'      => ['onOutputGenerated', 0],
        ]);
    }

    /**
     * Grav's per-page content cache has no notion of "current route", so whichever
     * context first calls content() freezes the link decision for everyone else.
     * Disabling it here forces onPageContentProcessed to re-check every request.
     */
    public function onPageProcessed(Event $event): void
    {
        $page = $event['page'];

        if (!$this->shouldShowLink($page)) {
            return;
        }

        $page->header()->cache_enable = false;
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

        // Only inject on the page actually being routed, not teasers/related/prev-next.
        if (!$page || !$currentPage || $page->route() !== $currentPage->route()) {
            return;
        }

        if (!$this->shouldShowLink($page)) {
            return;
        }

        // Hero-content pages get the link injected into the final HTML output instead
        // (see onOutputGenerated) — mutating raw content here would still end up inside
        // the hero banner on these themes.
        if ($this->isHeroContentPage($page)) {
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

    /**
     * Hero-content pages (see isHeroContentPage()) never get the link injected into raw
     * content, so it's added here instead, directly into the rendered HTML, positioned at
     * the top/bottom of the main content section rather than inside the hero banner.
     */
    public function onOutputGenerated(Event $event): void
    {
        $page = $event['page'];

        if (!$this->shouldShowLink($page) || !$this->isHeroContentPage($page)) {
            return;
        }

        $anchors = $this->getOutputAnchors();
        if (!$anchors) {
            return;
        }

        $config = $this->mergeConfig($page);
        $url    = $this->buildGitUrl($page, $config);
        if (!$url) {
            return;
        }

        $linkHtml = $this->renderLink($url, $config);
        $position = $config->get('link_position', 'bottom');

        // Read and write the same property Grav's renderer actually echoes. The array-access
        // form ($this->grav['output']) resolves through Pimple, which memoises its result on
        // first access elsewhere in the request — it would not reflect any changes another
        // plugin's onOutputGenerated listener already made to the live output before this runs.
        $output = (string) $this->grav->output;

        // On themes that scope link colour/decoration to a content wrapper class (e.g.
        // Typhoon's Tailwind Typography "prose" styles), the plain-style link falls back to
        // an unstyled default colour outside of it — wrap it in the same class so it matches.
        if ($anchors['wrapper_class'] !== '') {
            $linkHtml = '<div class="' . $anchors['wrapper_class'] . '">' . $linkHtml . '</div>';
        }

        // If a theme update ever changes the markup these patterns rely on, none of the
        // anchors below will match — falling back to right before </body> guarantees the
        // link still appears somewhere on the page, rather than silently vanishing.
        $lastResort = '/<\/body>/i';

        if ($position === 'top' || $position === 'both') {
            // Insert inside the main content wrapper (picking up the same horizontal
            // padding/alignment as the rest of the content) and after the breadcrumb nav
            // when present, matching where it lands on interior pages.
            $output = $this->insertAfterMatch($output, $anchors['top'], $linkHtml)
                ?? $this->insertBeforeMatch($output, $lastResort, $linkHtml)
                ?? $output;
        }

        if ($position !== 'top') {
            // Insert right before pagination, matching where the link lands above the
            // Previous/Next Post nav on interior pages — falling back to just before the
            // sidebar when there's no pagination to anchor on.
            $output = $this->insertBeforeMatch($output, $anchors['bottom_primary'], $linkHtml)
                ?? $this->insertBeforeMatch($output, $anchors['bottom_fallback'], $linkHtml)
                ?? $this->insertBeforeMatch($output, $lastResort, $linkHtml)
                ?? $output;
        }

        $this->grav->output = $output;
    }

    /**
     * Inserts $linkHtml right after the first match of $pattern in $output.
     * Returns null (instead of the input unchanged) if the pattern didn't match, or if the
     * regex engine itself failed (e.g. hit its backtrack limit on a very large page) — either
     * way, the caller knows to try a different pattern rather than risk losing $output.
     */
    private function insertAfterMatch(string $output, string $pattern, string $linkHtml): ?string
    {
        $result = preg_replace_callback(
            $pattern,
            static fn($m) => $m[0] . $linkHtml,
            $output,
            1,
            $matchCount
        );

        return ($matchCount > 0 && $result !== null) ? $result : null;
    }

    /**
     * Same as insertAfterMatch(), but places $linkHtml right before the match instead of after.
     */
    private function insertBeforeMatch(string $output, string $pattern, string $linkHtml): ?string
    {
        $result = preg_replace_callback(
            $pattern,
            static fn($m) => $linkHtml . $m[0],
            $output,
            1,
            $matchCount
        );

        return ($matchCount > 0 && $result !== null) ? $result : null;
    }

    /**
     * HTML anchor patterns for the hero-content output-stage injection, one set per
     * supported theme — each theme lays out its collection/list template differently, so
     * there's no single generic set of anchors that works everywhere.
     */
    private function getOutputAnchors(): ?array
    {
        $activeTheme = $this->getActiveTheme();

        // Two known breadcrumb markups in play: Quark2's own <nav> override, and the
        // breadcrumbs plugin's default <div id="breadcrumbs"> (used by Quark v1, Typhoon,
        // and any theme without its own override). Tried as alternatives, both optional.
        $breadcrumb = '(?:\s*(?:<nav aria-label="Breadcrumb">[\s\S]*?<\/nav>|<div id="breadcrumbs"[^>]*>[\s\S]*?<\/div>))?';

        // The content column and the sidebar sit side by side as flex siblings, not stacked
        // — inserting right before the sidebar's opening tag would make the link the
        // sidebar's first child, which renders at the TOP of that column, not the bottom of
        // the page. Inserting before the content column's own closing </div> (the one
        // immediately followed by the sidebar) keeps it inside the content column instead.
        $beforeSidebarDiv  = '(?=\s*<div id="sidebar"[^>]*>)';
        $beforeSidebarNode = '(?=\s*<(?:div|aside) id="sidebar"[^>]*>)';

        return match ($activeTheme) {
            // Quark v1 nests a <section class="container ..."> where Quark2 uses a plain
            // <div class="container">; Quark v1's sidebar is a <div>, Quark2's is an <aside>.
            'quark', 'quark2' => [
                'top'             => '/<section id="body-wrapper"[^>]*>\s*<(?:div|section) class="container[^"]*">' . $breadcrumb . '/',
                'bottom_primary'  => '/<div id="listing-footer">/',
                'bottom_fallback' => '/<\/div>' . $beforeSidebarNode . '/',
                'wrapper_class'   => '',
            ],
            'typhoon' => [
                'top'             => '/<div class="pt-(?:0|16)">' . $breadcrumb . '/',
                'bottom_primary'  => '/<div class="flex justify-center w-full p-6 mx-auto">/',
                'bottom_fallback' => '/<\/div>' . $beforeSidebarDiv . '/',
                // Matches the theme's own `prose_style` variable, so link colour/hover
                // states match what the theme applies to normal page content.
                'wrapper_class'   => 'prose md:prose-md dark:prose-invert max-w-none',
            ],
            default => null,
        };
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

        // Explicitly allow-listing a template is a stronger signal than the collection-page
        // heuristic below, so it takes precedence over it — but an empty (all-types) list
        // doesn't count as explicit consent for this specific page.
        $explicitlyAllowed = $allowed !== [] && in_array($page->template(), $allowed, true);

        if (!$explicitlyAllowed && $this->isHeroContentPage($page) && !$config->get('show_on_collection_pages', false)) {
            return false;
        }

        return true;
    }

    private function getActiveTheme(): string
    {
        return (string) $this->grav['config']->get('system.pages.theme', '');
    }

    /**
     * On Quark, Quark2, and Typhoon, a collection page's list template either renders the raw
     * content directly inside the hero banner (Quark/Quark2, when a hero image is set) or
     * never renders it at all (Typhoon, which drives its hero entirely from a separate `hero:`
     * frontmatter block). Either way, an injected link would never land in the normal page
     * body. Scoped to these known-affected themes rather than applied globally, since most
     * themes render content normally.
     */
    private function isHeroContentPage($page): bool
    {
        $activeTheme = $this->getActiveTheme();

        $collectionItems = (array) ($page->header()->content ?? []);
        if (empty($collectionItems['items'])) {
            return false;
        }

        return match ($activeTheme) {
            'quark', 'quark2' => !empty($page->header()->hero_image),
            'typhoon'         => !empty(((array) ($page->header()->hero ?? []))['image']),
            default           => false,
        };
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

        if ($iconType === 'svg-icons') {
            $iconName = trim((string) $config->get('icon_name', ''));
            if ($iconName === '') {
                return $this->buildIcon('pencil', $config);
            }
            try {
                $locator = $this->grav['locator'];
                // svgicons:// stream is registered by the SVG Icons plugin; degrade silently if absent.
                if (!method_exists($locator, 'schemeExists') || !$locator->schemeExists('svgicons')) {
                    return $this->buildIcon('pencil', $config);
                }
                // Normalise: ensure .svg extension.
                $iconPath = preg_match('/\.svg$/i', $iconName) ? $iconName : $iconName . '.svg';
                $path = $locator->findResource('svgicons://' . $iconPath, true);
                if (!$path || !file_exists($path)) {
                    return $this->buildIcon('pencil', $config);
                }
                $svg = (string) file_get_contents($path);
                if ($svg === '') {
                    return $this->buildIcon('pencil', $config);
                }
                // Inject gpl-icon class; handle SVGs with or without an existing class attribute.
                if (preg_match('/(<svg[^>]*)\bclass="/', $svg)) {
                    $svg = preg_replace('/(<svg[^>]*class=")/', '$1gpl-icon ', $svg, 1);
                } else {
                    $svg = preg_replace('/<svg\b/', '<svg class="gpl-icon"', $svg, 1);
                }
                return $svg;
            } catch (\Throwable $e) {
                return $this->buildIcon('pencil', $config);
            }
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
