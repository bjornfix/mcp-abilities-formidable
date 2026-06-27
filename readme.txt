=== MCP Abilities - Formidable ===
Contributors: basicus
Tags: forms, formidable, mcp, api, automation
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.2.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Formidable Forms abilities for MCP. Inspect forms, fields, styles, settings, usage, and CSS cache/runtime behavior via the Abilities API.

== Description ==

This add-on plugin extends [MCP Expose Abilities](https://devenia.com/plugins/mcp-expose-abilities/) with Formidable Forms inspection and configuration tools.

= Requirements =

* [MCP Expose Abilities](https://github.com/bjornfix/mcp-expose-abilities) (core plugin)
* [Formidable Forms](https://wordpress.org/plugins/formidable/) plugin

= Abilities Included =

**formidable/get-settings** - Read effective Formidable runtime/style settings plus raw `frm_options` and `frmpro_options`.

**formidable/update-settings** - Update supported runtime/style settings such as `load_style`, `custom_style`, `custom_css`, and clear/rebuild CSS cache.

**formidable/list-forms** - List saved Formidable forms with IDs, keys, style linkage, and shortcode references.

**formidable/get-form** - Get one form with normalized options and optional normalized fields.

**formidable/list-fields** - List normalized fields for a form.

**formidable/get-field** - Get one normalized field by ID.

**formidable/create-field** - Create a field on a form, including file upload fields.

**formidable/update-field** - Update an existing field, including file upload settings.

**formidable/list-styles** - List saved Formidable styles plus the synthetic default style entry.

**formidable/find-form-usage** - Find where a form is referenced in post content, Elementor data, and `widget_frm_show_form`.

**formidable/clear-css-cache** - Clear Formidable CSS/settings transients and optionally rebuild generated CSS.

= Use Cases =

* Audit how Formidable CSS is loaded on a site
* Reduce frontend CSS overhead safely by changing `load_style`
* Find all content that embeds a given form
* Inspect form/style inventory before migrations or redesigns
* Create or update file upload fields from MCP
* Clear and rebuild generated Formidable CSS after settings updates

== Installation ==

1. Install the required plugins (Abilities API, MCP Adapter, MCP Expose Abilities, Formidable Forms)
2. Download the latest release
3. Upload `mcp-abilities-formidable` to `/wp-content/plugins/`
4. Activate through the 'Plugins' menu
5. The abilities are now available via the MCP endpoint

= Links =

* [Plugin Page](https://devenia.com/plugins/mcp-expose-abilities/)
* [Core Plugin (MCP Expose Abilities)](https://github.com/bjornfix/mcp-expose-abilities)
* [All Add-on Plugins](https://devenia.com/plugins/mcp-expose-abilities/#add-ons)

== Changelog ==

= 1.2.7 =
* Added explicit file upload field aliases for `max_file_size_mb`, `min_file_size_mb`, and `max_files_per_entry`.
* Added normalized file field output for the same file upload limits.

= 1.2.6 =
* Replace interpolated Formidable table SQL with prepared identifier placeholders.
* Update tested WordPress version metadata for Plugin Check.
* Align public release identity with the Basicus author/contributor rule.

= 1.2.5 =
* Added `formidable/get-form`, `formidable/list-fields`, `formidable/get-field`, `formidable/create-field`, and `formidable/update-field`
* Added form cloning, form updates, post-meta updates, and action update abilities
* Added normalized form/field helpers and Formidable-table read fallbacks for safer MCP inspection
* Expanded the plugin so MCP clients can create and update file upload fields directly

= 1.1.0 =
* Internal release superseded by 1.2.5 before public package publication

= 1.0.4 =
* Docs: expanded the WordPress-standard `readme.txt` so the published ZIP now includes fuller requirements, abilities, use cases, and Devenia ecosystem links

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
