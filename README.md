# MCP Abilities - Formidable

Inspect a Formidable form before you change it. Find its fields and known page references, copy it with Formidable's own duplication process, or adjust one field through an authenticated MCP connection.

[![Release 1.2.10](https://img.shields.io/badge/release-1.2.10-blue.svg)](https://downloads.devenia.com/mcp-abilities-formidable.zip)
[![License GPLv2 or later](https://img.shields.io/badge/license-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress 6.9+](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org/download/)
[![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://www.php.net/downloads.php)

**Stable tag:** 1.2.10 · **Tested up to:** 7.1 (7.1-RC3) · **License:** GPLv2 or later

**Tags:** forms, formidable, mcp, api, automation

## What It Does

Fifteen WordPress abilities expose Formidable forms, fields, styles, settings and known usage locations. Write operations can copy a form, change its properties or options, create and update fields, edit a form action, save permitted post metadata, and clear generated CSS caches.

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

## Abilities (15)

| Ability | Result or change |
|---|---|
| `formidable/get-settings` | Effective settings plus raw Formidable and Pro options |
| `formidable/update-settings` | Supported native style/runtime settings and optional CSS rebuild |
| `formidable/list-forms` | Form IDs, keys, style references and shortcodes |
| `formidable/get-form` | One form with options and optional fields |
| `formidable/clone-form` | Native form, field and action duplication; new IDs and key mappings |
| `formidable/update-form` | Name, description, key and merged form options |
| `formidable/list-fields` | Normalized fields belonging to a form |
| `formidable/get-field` | One normalized field by ID |
| `formidable/create-field` | A new field on an existing form |
| `formidable/update-field` | Field properties, required status, choices and options |
| `formidable/update-action` | A Formidable action post's title, type or JSON settings |
| `formidable/update-post-meta` | Permitted metadata on an explicit WordPress post |
| `formidable/list-styles` | Saved styles and an optional virtual default entry |
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

Copies use Formidable's registered duplication hooks, including available action handlers. The add-on does not copy submitted entries. Form keys may be adjusted by Formidable for uniqueness; use the returned key. Changing form options preserves the current publication status. Editing action settings can change future email or integration behaviour, so inspect the exact action first.

Usage search checks post content, Elementor `_elementor_data` and `widget_frm_show_form`. Its result is not an exhaustive site dependency map. The configured search limits and unsupported embedding methods can leave references undiscovered. CSS rebuilding depends on the installed Formidable style classes; inspect the returned rebuild result.

Writes take effect when called. The add-on has no separate confirmation step or general undo feature. Raw settings can contain private configuration; restrict administrator access and handle responses accordingly.

## Installation

1. Activate Formidable and configure the required MCP stack.
2. [Download the plugin ZIP](https://downloads.devenia.com/mcp-abilities-formidable.zip).
3. Upload it through **Plugins → Add New → Upload Plugin** and activate it.
4. Confirm discovery and read a form before changing one.

## Recent Changes

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
