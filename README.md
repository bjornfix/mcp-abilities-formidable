# MCP Abilities - Formidable

Inspect a Formidable form before you change it. Find its fields and known page references, copy it with Formidable's own duplication process, or adjust one field through an authenticated MCP connection.

[![Release 1.3.1](https://img.shields.io/badge/release-1.3.1-blue.svg)](https://downloads.devenia.com/mcp-abilities-formidable.zip)
[![License GPLv2 or later](https://img.shields.io/badge/license-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress 6.9+](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org/download/)
[![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://www.php.net/downloads.php)

**Stable tag:** 1.3.1 · **Tested up to:** 7.1 (7.1-RC3) · **License:** GPLv2 or later

**Tags:** forms, formidable, mcp, api, automation

## What It Does

Nineteen WordPress abilities expose Formidable forms, fields, styles, settings and known usage locations. Write operations can copy a form, change its properties or options, create and update fields, edit a form action, save permitted post metadata, and clear generated CSS caches.

Formidable owns form storage, field validation, duplication hooks and action behaviour. This add-on connects those features to the WordPress Abilities API. It does not provide an entry inbox, submission export or a replacement form builder.

## The Real Workflow

1. Discover forms and select the exact form ID or key.
2. Inspect its options, fields and known content references.
3. Specify the change, such as making one field required or copying a form for another page.
4. Read back the result and test the rendered form, including a submission when appropriate.

A successful configuration update confirms a stored change. It does not establish that an email was delivered or that every frontend integration works.

## Why This Feels Different

The agent can inspect the same form it will change. Field responses include IDs, keys, required status, options and file-upload limits. Copy responses include the new form ID, field ID and key mappings, action count and shortcode, so the next step has concrete references.

## Before vs After

| Task | Manual work | With this add-on |
|---|---|---|
| Check an upload field | Open the form and inspect its field settings | Read the field and its size/count limits |
| Reuse a form | Duplicate it and collect the new references | Use native duplication and receive the new IDs and keys |
| Find a form before editing | Search pages and widget settings | Inspect the supported content, Elementor and widget matches |
| Change CSS loading | Find the global setting and refresh generated CSS | Update a supported setting and request a CSS rebuild |

## Who It Is For

WordPress administrators, form builders and agencies that already use Formidable Forms and authenticated MCP. Premium field types and actions still require the corresponding Formidable edition or add-on.

## Requirements

- [WordPress 6.9 or newer](https://wordpress.org/download/) with its built-in [Abilities API](https://developer.wordpress.org/apis/abilities-api/).
- [PHP 8.0 or newer](https://www.php.net/downloads.php).
- [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) for MCP transport.
- [MCP Expose Abilities](https://devenia.com/plugins/mcp-expose-abilities/) for the Devenia exposure workflow.
- [Formidable Forms](https://wordpress.org/plugins/formidable/) with the native APIs needed by the requested operation.
- An authenticated WordPress user with the required capabilities below.

These are the runtime dependencies for the documented MCP workflow. Features supplied by Formidable Pro or another Formidable add-on retain their own requirements.

## Documentation

Read the [Formidable add-on page](https://devenia.com/plugins/mcp-abilities-formidable/) and the [MCP Expose Abilities setup overview](https://devenia.com/plugins/mcp-expose-abilities/).

## Start Here

Configure the MCP stack and confirm discovery. Start with `formidable/list-forms`, then inspect one returned ID with `formidable/get-form` and `include_fields: true`. Select the exact object before requesting a write.

## Abilities (19)

| Ability | Result or change |
|---|---|
| `formidable/get-settings` | Effective settings plus raw Formidable and Pro options |
| `formidable/update-settings` | Supported native style/runtime settings and optional CSS rebuild |
| `formidable/list-forms` | Form IDs, keys, style references and shortcodes |
| `formidable/get-form` | One form with options and optional fields |
| `formidable/clone-form` | Native form, field and verified action duplication; new IDs and key mappings |
| `formidable/copy-form-actions` | Complete native actions on an empty, unedited copy with an exact field map |
| `formidable/update-form` | Name, description, key and merged form options |
| `formidable/list-fields` | Normalized fields belonging to a form |
| `formidable/get-field` | One normalized field by ID |
| `formidable/create-field` | A new field on an existing form |
| `formidable/update-field` | Field properties, required status, choices and options |
| `formidable/update-action` | A Formidable action post's title, type or JSON settings |
| `formidable/update-post-meta` | Permitted metadata on an explicit WordPress post |
| `formidable/list-styles` | Saved styles and an optional virtual default entry |
| `formidable/get-style` | Stored and effective settings, native setting keys and a review hash for one existing style |
| `formidable/update-style` | Merge selected native style settings, verify persistence and optionally regenerate CSS |
| `formidable/restore-style` | Preview and restore reviewed style metadata while preserving existing native settings |
| `formidable/find-form-usage` | Supported post-content, Elementor and widget references |
| `formidable/clear-css-cache` | Clear runtime/style transients and optionally regenerate CSS |

## Examples

Inspect the selected form; replace example IDs with IDs returned by discovery:

```json
{"form_id":42,"include_fields":true}
```

Use `formidable/clone-form` to create an independent copy. Copying does not translate its labels or insert it into a page:

```json
{"source_form_id":42,"name":"Course enquiry copy","form_key":"course-enquiry-copy"}
```

Use `formidable/update-field` to set limits on an existing file-upload field. File sizes use MB:

```json
{"field_id":105,"required":true,"max_file_size_mb":5,"max_files_per_entry":2}
```

Use `formidable/update-settings` to load styles dynamically and request regeneration:

```json
{"load_style":"dynamic","rebuild_css":true}
```

## Safety and Ownership Boundaries

Reading forms, fields, styles and usage requires `edit_posts`. Reading or changing global settings and clearing CSS caches requires `manage_options`. Form, field and action writes require `frm_edit_forms` or `manage_options`.

Post metadata updates require `edit_posts`, permission for the exact post, and permission for every supplied key before writing begins. A storage failure returns the keys already updated. This operation is not restricted to posts linked to a form.

Formidable-backed calls return an inactive result when Formidable is absent. Missing native write APIs or unavailable setting properties return an error. Legacy `jquery_css`, `old_css` and `accordion_js` inputs are usable only when the installed settings object provides them. `load_style` supports `all`, `dynamic` and `none`; global custom CSS is a string.

Copies use Formidable's native action handlers even outside the form builder. Action counts are verified against the source; an incomplete new copy is removed. `formidable/copy-form-actions` can complete an existing copy only when it has no entries or actions and retains matching form options and unedited fields. Supply the exact field map returned during copying. The add-on does not copy submitted entries. Form keys may be adjusted by Formidable for uniqueness; use the returned key. Changing form options preserves the current publication status. Editing action settings can change future email or integration behaviour, so inspect the exact action first.

Usage search checks post content, Elementor `_elementor_data` and `widget_frm_show_form`. Its result is not an exhaustive site dependency map. The configured search limits and unsupported embedding methods can leave references undiscovered. CSS rebuilding depends on the installed Formidable style classes; inspect the returned rebuild result.

Writes take effect when called. The add-on has no separate confirmation step or general undo feature. Raw settings can contain private configuration; restrict administrator access and handle responses accordingly.

### Inspect and update a style

Read `formidable/get-style` with `{"style_id":42}`. The response distinguishes stored settings from effective defaults and lists the setting keys supplied by the installed Formidable version. Use its `style_hash` in `formidable/update-style`:

```json
{"style_id":42,"expected_style_hash":"<hash from get-style>","settings":{"font":"\"Lexend Deca\", sans-serif","field_font_size":"18px"},"rebuild_css":true}
```

Only selected native settings change. The style title, slug, default selection, publication status and other stored settings are preserved. Native sanitization, font quotation, style CSS scoping and base-font conversion apply when relevant. Style access requires `frm_edit_forms` or `manage_options`. CSS generation uses Formidable and still needs a rendered check.

Use `formidable/restore-style` to preview selected native metadata from a known style backup. It preserves settings and requires native administrator and selected-object editing permissions. Confirmation requires the returned current-object hash. A regular page or arbitrary post cannot be converted to a style. The only accepted non-style object is an empty draft containing a complete recognized native style settings object. Existing style slugs and another style’s default selection are protected.

## Installation


For update notifications in WordPress, install [Devenia MCP Updater](https://downloads.devenia.com/devenia-mcp-updater.zip). The updater is optional. You choose which plugins update automatically through WordPress.

1. Activate Formidable and configure the required MCP stack.
2. [Download the plugin ZIP](https://downloads.devenia.com/mcp-abilities-formidable.zip).
3. Upload it through **Plugins → Add New → Upload Plugin** and activate it.
4. Confirm discovery and read a form before changing one.

## Recent Changes


### 1.3.1

Preserve the complete native WordPress post when saving style settings, including its type, title, author, dates and default selection. Preserve literal backslashes through native JSON storage. Add reviewed restoration of style metadata without replacing style settings.

### 1.3.0

Read and update every setting supported by the installed native style model. Distinguish stored values from defaults, reject stale reads, preserve unedited values and style identity, and optionally regenerate native CSS.

### 1.2.12

Copy notification and submit actions through native handlers outside the form builder, verify their count, and remove incomplete new copies. Add a guarded operation to complete actions on an existing empty, unedited form copy.

### 1.2.11

Add one dismissible Plugins-screen reminder when Devenia MCP Updater is missing or inactive, with persistent install or activate links. Automatic updates remain your choice in WordPress.

### 1.2.10

- Use Formidable's native models for form and field reads, updates and duplication.
- Preserve native field references and actions when copying a form, with cleanup after a failed follow-up update.
- Store and report required fields through the native required property.
- Save settings through Formidable's shared settings object and check persistence.
- Preserve metadata backslashes, check all requested key permissions before writing, and report failed storage.

### 1.2.9

Added merged form-option updates and cache clearing after form changes.

## Contributing

Keep changes focused on the public abilities. Verify input schemas, permissions, native model behaviour and returned results. Include a small reproduction for a bug report.

## License

[GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

## Author

[basicus](https://profiles.wordpress.org/basicus/)

## Links

- [Plugin page](https://devenia.com/plugins/mcp-abilities-formidable/)
- [Download](https://downloads.devenia.com/mcp-abilities-formidable.zip)
- [MCP Expose Abilities](https://devenia.com/plugins/mcp-expose-abilities/)
- [Formidable Forms](https://wordpress.org/plugins/formidable/)
