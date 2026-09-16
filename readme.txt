=== MCP Abilities - Formidable ===
Contributors: basicus
Tags: forms, formidable, mcp, api, automation
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.2.11
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Inspect Formidable forms, copy them with native field and action handling, and update selected settings through authenticated MCP abilities.

== Description ==

Fifteen abilities connect Formidable forms, fields, styles, settings and supported usage locations to the WordPress Abilities API. Inspect a form before changing it and read back its resulting IDs, keys and options.

Tested with WordPress 7.1-RC3.

= Requirements =

* WordPress 6.9 or newer with its built-in Abilities API; PHP 8.0 or newer.
* WordPress MCP Adapter for MCP transport.
* [MCP Expose Abilities](https://devenia.com/plugins/mcp-expose-abilities/) for the Devenia exposure workflow.
* [Formidable Forms](https://wordpress.org/plugins/formidable/) with the native APIs used by the requested operation.
* An authenticated user with the appropriate WordPress capabilities.

Premium fields and actions retain their Formidable edition or add-on requirements.

= Abilities =

* formidable/get-settings: Effective settings plus raw Formidable and Pro options.
* formidable/update-settings: Supported native style/runtime settings and optional CSS rebuild.
* formidable/list-forms: Form IDs, keys, style references and shortcodes.
* formidable/get-form: One form with options and optional fields.
* formidable/clone-form: Native form, field and action duplication; new IDs and key mappings.
* formidable/update-form: Name, description, key and merged form options.
* formidable/list-fields: Normalized fields belonging to a form.
* formidable/get-field: One normalized field by ID.
* formidable/create-field: A new field on an existing form.
* formidable/update-field: Field properties, required status, choices and options.
* formidable/update-action: A Formidable action post's title, type or JSON settings.
* formidable/update-post-meta: Permitted metadata on an explicit WordPress post.
* formidable/list-styles: Saved styles and an optional virtual default entry.
* formidable/find-form-usage: Supported post-content, Elementor and widget references.
* formidable/clear-css-cache: Clear runtime/style transients and optionally regenerate CSS.

= Boundaries =

Reading forms, fields, styles and usage requires edit_posts. Global settings and CSS cache operations require manage_options. Form, field and action writes require frm_edit_forms or manage_options. Post metadata writes also check the exact post and all requested keys before writing; they are not restricted to form-linked posts.

Formidable owns duplication, field references and registered action hooks. Copies do not include submitted entries or translated labels. Use the returned form key because Formidable ensures uniqueness. Option updates preserve the form's publication status. File upload size aliases use MB.

Global settings use the native settings object. Legacy properties are rejected when unavailable. Usage search covers supported post content, Elementor data and Formidable widgets within the supplied limits; it is not an exhaustive dependency map. CSS regeneration depends on the installed style classes.

The add-on has no general undo or separate confirmation step. A successful stored change does not verify email delivery or frontend submission behaviour. Inspect and test the rendered form after configuration changes.

== Installation ==

For update notifications in WordPress, install [Devenia MCP Updater](https://downloads.devenia.com/devenia-mcp-updater.zip). The updater is optional. You choose which plugins update automatically through WordPress.

1. Activate Formidable and configure the required MCP stack.
2. Download and upload the ZIP through Plugins > Add New > Upload Plugin.
3. Activate the add-on and confirm ability discovery.
4. List forms, inspect the selected ID, and then request the intended change.

== Changelog ==

= 1.2.11 =
* Add one dismissible Plugins-screen reminder when Devenia MCP Updater is missing or inactive, with persistent install or activate links. Automatic updates remain your choice in WordPress.

= 1.2.10 =
* Use native models for form and field reads, updates and duplication.
* Preserve native field references and actions during copying, with cleanup after failed follow-up updates.
* Store required fields through their native property.
* Save settings through the shared Formidable settings object and verify persistence.
* Preserve metadata backslashes, check all requested key permissions first, and report storage failures.

= 1.2.9 =
* Add merged form-option updates and clear caches after form changes.

== Links ==

* [Plugin page](https://devenia.com/plugins/mcp-abilities-formidable/)
* [Download](https://downloads.devenia.com/mcp-abilities-formidable.zip)
* [MCP Expose Abilities](https://devenia.com/plugins/mcp-expose-abilities/)
