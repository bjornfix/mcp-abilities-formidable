# MCP Abilities - Formidable

Formidable Forms abilities for WordPress via MCP.

**Tested up to:** 6.9  
**Stable tag:** 1.0.2  
**Requires PHP:** 8.0  
**License:** GPLv2 or later

## What It Does

This add-on plugin exposes the Formidable Forms surface that is most useful for automation and site cleanup:

- inspect effective Formidable settings
- update safe runtime/style settings
- list forms and styles
- find where forms are used
- clear and rebuild generated CSS cache

## Requirements

- WordPress 6.9+
- PHP 8.0+
- Abilities API / MCP Adapter stack
- Formidable Forms

## Abilities

| Ability | Description |
|---------|-------------|
| `formidable/get-settings` | Get effective and raw Formidable settings |
| `formidable/update-settings` | Update supported runtime/style settings |
| `formidable/list-forms` | List forms with IDs, keys, styles, and shortcode refs |
| `formidable/list-styles` | List saved Formidable styles |
| `formidable/find-form-usage` | Find content/widget usage of a form |
| `formidable/clear-css-cache` | Clear and optionally rebuild generated CSS |

## Notes

- `load_style` supports `all`, `dynamic`, and `none`
- `find-form-usage` currently inspects:
  - post content
  - Elementor `_elementor_data`
  - `widget_frm_show_form`
- `clear-css-cache` clears the common Formidable CSS/settings transients and can rebuild generated CSS when Formidable style classes are available

## Changelog

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
