<div align="center">

# 🔗 Git Page Link Plugin

<p><em>Enable open authoring and OER workflows by linking Grav pages directly to editable Markdown source files in GitHub, Codeberg, and other Git hosts.</em></p>

[![Latest Release](https://img.shields.io/github/v/release/hibbitts-design/grav-plugin-git-page-link?style=flat-square&label=Release)](https://github.com/hibbitts-design/grav-plugin-git-page-link/releases/latest) [![License](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](https://github.com/hibbitts-design/grav-plugin-git-page-link/blob/master/LICENSE) [![PHP](https://img.shields.io/badge/PHP-%3E%3D8.0-8892BF?style=flat-square&logo=php&logoColor=white)](https://learn.getgrav.org/17/basics/requirements)

</div>

For open authoring and OER workflows – adds a link to Grav pages that connects visitors directly to the content Git repository on GitHub, Codeberg, or any other Git host, either to the page's source Markdown file or to the repository root. Pairs with the [Git Sync plugin](https://github.com/trilbymedia/grav-plugin-git-sync) for automatic repository detection, or configure the repository URL and branch manually without Git Sync. A single link serves both audiences: contributors with repository access land on the edit interface, while everyone else sees the file in the repository viewer – making it easy to invite readers to suggest edits, view page source, or access the raw Markdown files for reuse and adaptation.

This plugin is the successor to the "View/Edit Page in Git Repository" feature originally built into the [Open Publishing Space](https://github.com/hibbitts-design/grav-skeleton-open-publishing-space) skeleton – extracting that capability from the Quark Open Publishing theme into a standalone plugin compatible with a wide range of existing Grav themes, including Quark2.

## What It Does

- Link mode is configurable – edit the page file, view the page file, or browse the repository root
- Renders a link at the top, bottom, or both ends of page content
- Link label is fully customisable – use "Edit this Page", "View Source", "Open on GitHub", or any text
- Styled as a plain text link (default) or a button
- Displays a built-in pencil, document, Git branch, or folder SVG icon, any icon from the SVG Icons plugin, a custom SVG, or no icon
- Optionally opens in a new tab, or defers to the browser default or the External Links plugin
- Restricts display to specific page templates; leave the Page Types setting empty to show on all pages
- Repository URL is auto-detected from Git Sync, or set manually in the Advanced settings if Git Sync is not in use
- Silently omits the link if no repository URL is available from Git Sync or the Advanced settings

## Requirements

- Grav 1.7+
- PHP 8.0+
- [Git Sync plugin](https://github.com/trilbymedia/grav-plugin-git-sync) installed and configured with a remote repository, or the repository URL and branch set manually in the Advanced settings

## Installation

**Via the Grav Admin Panel:** Plugins → Add → search for `Git Page Link` → Install.

**Via GPM:**

```bash
bin/gpm install git-page-link
```

**Manual install:**

1. Download the plugin from [GitHub](https://github.com/hibbitts-design/grav-plugin-git-page-link)
2. Unzip and rename the folder to `git-page-link`
3. Copy the folder to `user/plugins/git-page-link`

## Plugin Settings

Any setting can be overridden on a per-page basis by adding a `git-page-link` block to the page's frontmatter:

```yaml
---
git-page-link:
  link_text: "View Source"
  link_mode: view
---
```

| Setting | Default | Description |
|---------|---------|-------------|
| Plugin Status | Enabled | Enable or disable the plugin |
| Link Mode | View page | Where the link points: edit the page file, view the page file, or view the repository root |
| Link Label | Edit this Page | Text displayed on the link |
| Link Tooltip | _(empty)_ | Tooltip shown on hover (`title` attribute); leave empty for no tooltip |
| Link Position | Bottom | Where the link appears: Top, Bottom, or Both |
| Open Link in New Tab | Enabled | Open the link in a new browser tab; disable to use default browser behaviour or defer to the External Links plugin |
| Link Style | Plain text link | Display as a plain text link or a button |
| Dark Mode Support | Disabled | Load dark mode CSS for the button style; enable only if your theme supports dark mode |
| Link Icon | Pencil | Icon shown beside the link label: Pencil, Document, Git branch, Folder, SVG Icons plugin, Custom SVG, or None |
| SVG Icons Plugin Icon Name | _(empty)_ | Icon path from the SVG Icons plugin (e.g. `tabler/pencil.svg`, `heroicons/outline/pencil-square.svg`); used only when Link Icon is set to SVG Icons plugin; falls back to the built-in pencil if the plugin is not installed or the icon is not found |
| Custom SVG | _(empty)_ | Full `<svg>` element or inner path content; used only when Link Icon is set to Custom SVG |
| Show on Page Types | _(empty)_ | Restrict the link to specific page templates; leave empty to show on all pages |
| Custom Repository URL | _(empty)_ | Override the repository URL from Git Sync, or set manually if Git Sync is not in use; leave empty to use Git Sync automatically |
| Custom Branch | _(empty)_ | Override the branch from Git Sync, or set manually if Git Sync is not in use; leave empty to use Git Sync automatically |

> **Note:** On GitHub, the edit mode URL redirects unauthenticated visitors to a fork-and-propose-changes flow — ideal for open authoring. On GitLab, Codeberg, Gitea, and Forgejo, edit URLs redirect unauthenticated users to a login page; use **View page** mode for publicly accessible links on those platforms.

## Credits

Developed by [HibbittsDesign.org](https://hibbittsdesign.org) with the assistance of [Claude Code](https://claude.ai/claude-code).

Special thanks to [tucho235](https://github.com/tucho235) for the [Copy as Markdown Button](https://github.com/tucho235/grav-plugin-copy-as-markdown-button) plugin, which served as an example of injecting content at the top or bottom of Grav pages.

## Support

- Follow [@hibbittsdesign@mastodon.social](https://mastodon.social/@hibbittsdesign) on Mastodon for updates
- Join the [Grav Discord](https://chat.getgrav.org) for community support
- Add a ⭐️ [star on GitHub](https://github.com/hibbitts-design/grav-plugin-git-page-link) to support the project
- For bugs or feature requests, [open an issue](https://github.com/hibbitts-design/grav-plugin-git-page-link/issues) on GitHub

## License

MIT – Hibbitts Design
