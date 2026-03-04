=== MCP Abilities - Formidable ===
Contributors: devenia
Tags: forms, formidable, mcp, api, automation
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Formidable Forms abilities for MCP. Inspect forms, styles, settings, usage, and CSS cache/runtime behavior via the Abilities API.

== Description ==

This add-on plugin extends MCP/Abilities API workflows with Formidable Forms inspection and configuration tools.

= Requirements =

* Abilities API / MCP Adapter stack
* Formidable Forms

= Abilities Included =

**formidable/get-settings** - Read effective Formidable runtime/style settings plus raw `frm_options` and `frmpro_options`.

**formidable/update-settings** - Update supported runtime/style settings such as `load_style`, `custom_style`, `custom_css`, and clear/rebuild CSS cache.

**formidable/list-forms** - List saved Formidable forms with IDs, keys, style linkage, and shortcode references.

**formidable/list-styles** - List saved Formidable styles plus the synthetic default style entry.

**formidable/find-form-usage** - Find where a form is referenced in post content, Elementor data, and `widget_frm_show_form`.

**formidable/clear-css-cache** - Clear Formidable CSS/settings transients and optionally rebuild generated CSS.

= Use Cases =

* Audit how Formidable CSS is loaded on a site
* Reduce frontend CSS overhead safely by changing `load_style`
* Find all content that embeds a given form
* Inspect form/style inventory before migrations or redesigns
* Clear and rebuild generated Formidable CSS after settings updates

== Installation ==

1. Install and activate the Abilities API / MCP stack
2. Install and activate Formidable Forms
3. Upload `mcp-abilities-formidable` to `/wp-content/plugins/`
4. Activate through the 'Plugins' menu
5. The abilities are now available via the MCP endpoint

== Changelog ==

= 1.0.3 =
* Changed the remaining abilities to use the core `site` category for compatibility with the current Abilities API stack

= 1.0.2 =
* Fixed missing text domains in translated strings for WordPress.org plugin checks

= 1.0.1 =
* Fixed ability registration to use the required `wp_abilities_api_init` hook
* Added registration for the `content` ability category before registering content-related abilities

= 1.0.0 =
* Initial release
* Added `formidable/get-settings`
* Added `formidable/update-settings`
* Added `formidable/list-forms`
* Added `formidable/list-styles`
* Added `formidable/find-form-usage`
* Added `formidable/clear-css-cache`
