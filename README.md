# MCP Abilities - Formidable

Formidable Forms abilities for WordPress via MCP.

[![GitHub release](https://img.shields.io/github/v/release/bjornfix/mcp-abilities-formidable)](https://github.com/bjornfix/mcp-abilities-formidable/releases)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

**Tested up to:** 6.9
**Stable tag:** 1.0.3
**Requires PHP:** 8.0
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

## What It Does

This add-on plugin exposes the Formidable Forms surface that is most useful for automation and site cleanup. Your AI assistant can inspect effective settings, update safe runtime and style options, find where forms are used, and clear or rebuild generated CSS.

**Part of the [MCP Expose Abilities](https://devenia.com/plugins/mcp-expose-abilities/) ecosystem.**

## Requirements

- WordPress 6.9+
- PHP 8.0+
- [Abilities API](https://github.com/WordPress/abilities-api) plugin
- [MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin
- [MCP Expose Abilities](https://github.com/bjornfix/mcp-expose-abilities) core plugin
- [Formidable Forms](https://wordpress.org/plugins/formidable/) plugin

## Installation

1. Install the required plugins (Abilities API, MCP Adapter, MCP Expose Abilities, Formidable Forms)
2. Download the latest release from [Releases](https://github.com/bjornfix/mcp-abilities-formidable/releases)
3. Upload via WordPress Admin > Plugins > Add New > Upload Plugin
4. Activate the plugin

## Abilities (6)

| Ability | Description |
|---------|-------------|
| `formidable/get-settings` | Get effective and raw Formidable settings |
| `formidable/update-settings` | Update supported runtime and style settings |
| `formidable/list-forms` | List forms with IDs, keys, styles, and shortcode refs |
| `formidable/list-styles` | List saved Formidable styles |
| `formidable/find-form-usage` | Find post, widget, and Elementor usage of a form |
| `formidable/clear-css-cache` | Clear and optionally rebuild generated CSS |

## Notes

- `load_style` supports `all`, `dynamic`, and `none`
- `find-form-usage` currently inspects:
  - post content
  - Elementor `_elementor_data`
  - `widget_frm_show_form`
- `clear-css-cache` clears the common Formidable CSS/settings transients and can rebuild generated CSS when Formidable style classes are available

## Changelog

### 1.0.3
- Moved the remaining abilities onto the core `site` category for compatibility with the current Abilities API registry behavior

### 1.0.2
- Fixed missing text domains in translated strings for plugin-check compliance

### 1.0.1
- Fixed registration to run on `wp_abilities_api_init`
- Added the `content` ability category before registering content-related abilities

### 1.0.0
- Initial release
- Added `formidable/get-settings`
- Added `formidable/update-settings`
- Added `formidable/list-forms`
- Added `formidable/list-styles`
- Added `formidable/find-form-usage`
- Added `formidable/clear-css-cache`

## License

GPL-2.0+

## Author

[Devenia](https://devenia.com) - We've been doing SEO and web development since 1993.

## Links

- [Plugin Page](https://devenia.com/plugins/mcp-expose-abilities/)
- [Core Plugin (MCP Expose Abilities)](https://github.com/bjornfix/mcp-expose-abilities)
- [All Add-on Plugins](https://devenia.com/plugins/mcp-expose-abilities/#add-ons)
