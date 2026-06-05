# MCP Abilities - Formidable

Formidable Forms abilities for MCP. Inspect forms, styles, settings, usage, and CSS cache/runtime behavior.

[![GitHub release](https://img.shields.io/github/v/release/bjornfix/mcp-abilities-formidable)](https://github.com/bjornfix/mcp-abilities-formidable/releases)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![WordPress](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://php.net)

**Tested up to:** 7.0
**Stable tag:** 1.2.5
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

## What It Does

Formidable Forms abilities for MCP. Inspect forms, styles, settings, usage, and CSS cache/runtime behavior.

This plugin is part of the Devenia MCP abilities ecosystem. It gives an MCP-capable agent a focused, authenticated way to work with Formidable work inside WordPress through MCP.

**Example:** "Handle this WordPress maintenance task directly." - The agent can inspect the site, call the relevant ability, and return the result without making the human click through wp-admin for every step.

## The Real Workflow

In practice, the human should not have to memorize every ability name.

The normal pattern is:

1. install the base MCP stack
2. install only the add-ons the site actually needs
3. let the agent discover the available abilities
4. give the agent a clear task with boundaries
5. verify the result in WordPress

The human's job is mostly to describe the goal.
The agent's job is to figure out the mechanics.

## Why This Feels Different

Most WordPress automation still leaves the repetitive part to the human.

This plugin is different because the agent can act inside the site through a narrow, authenticated ability surface:

- inspect current site state before changing anything
- run the specific action needed for the task
- return structured results that are easy to verify
- keep the workflow inside WordPress instead of a separate checklist

That changes the experience from:

- `Here is what you should do in wp-admin`

to:

- `Tell the agent what needs doing, and let it carry out the work`

## Before vs After

### Before

- ask the AI what to do
- copy the answer into WordPress by hand
- click through wp-admin for the repetitive bits
- postpone maintenance because the task is tedious

### After

- tell the agent what needs doing
- let it inspect the relevant WordPress state
- let it run the targeted ability
- verify the result and move on

## Who It Is For

This is a good fit for:

- agencies managing WordPress sites with AI-assisted maintenance
- operators who want agents to do real WordPress work instead of producing instructions
- teams already using MCP Expose Abilities
- sites where this WordPress area is updated often enough to deserve automation

It is especially useful when the manual version is repetitive enough that important maintenance gets delayed.

## Documentation

Start with the main plugin page and base stack documentation:

- [MCP Expose Abilities](https://devenia.com/plugins/mcp-expose-abilities/)
- [Plugin Page](https://devenia.com/plugins/mcp-expose-abilities/#add-ons)
- [Getting Started](https://github.com/bjornfix/mcp-expose-abilities/wiki/Getting-Started)
- [Install Order and Dependencies](https://github.com/bjornfix/mcp-expose-abilities/wiki/Install-Order-and-Dependencies)

If you are using an AI agent, the simplest instruction is often just:

- `Read https://github.com/bjornfix/mcp-expose-abilities and figure out the stack before making changes.`

## Start Here

If you are new to the stack, use this order:

1. Install **Abilities API**.
2. Install **MCP Adapter**.
3. Install **MCP Expose Abilities**.
4. Install **MCP Abilities - Formidable**.
5. Confirm the new abilities appear in discovery.
6. Give the agent a clear task that uses this add-on.

If you skip base-stack verification and start with add-ons immediately, troubleshooting gets harder than it needs to be.

## Abilities (11)

| Ability | Description |
|---------|-------------|
| `formidable/get-settings` | Get effective and raw Formidable settings |
| `formidable/update-settings` | Update supported runtime and style settings |
| `formidable/list-forms` | List forms with IDs, keys, styles, and shortcode refs |
| `formidable/get-form` | Get one form with normalized options and optional fields |
| `formidable/list-fields` | List normalized fields for a form |
| `formidable/get-field` | Get one normalized field by ID |
| `formidable/create-field` | Create a new field, including file upload fields |
| `formidable/update-field` | Update an existing field, including file upload settings |
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

### 1.2.5
- Added `formidable/get-form`, `formidable/list-fields`, `formidable/get-field`, `formidable/create-field`, and `formidable/update-field`
- Added form cloning, form updates, post-meta updates, and action update abilities
- Added normalized form/field helpers and Formidable-table read fallbacks for safer MCP inspection
- Expanded the plugin so MCP clients can create and update file upload fields directly

### 1.1.0
- Internal release superseded by 1.2.5 before public package publication

### 1.0.4
- Docs: expanded the WordPress-standard `readme.txt` so the published ZIP now includes fuller requirements, abilities, use cases, and Devenia ecosystem links

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

## Contributing

PRs welcome. Keep changes focused on the plugin's WordPress ability surface and preserve authenticated, explicit workflows.

## License

GPL-2.0+

## Author

[Devenia](https://devenia.com) - We've been doing SEO and web development since 1993.

## Links

- [Plugin Page](https://devenia.com/plugins/mcp-expose-abilities/#add-ons)
- [MCP Expose Abilities](https://devenia.com/plugins/mcp-expose-abilities/)
- [GitHub Releases](https://github.com/bjornfix/mcp-abilities-formidable/releases)

## Star and Share

If this plugin saves you time or makes WordPress maintenance easier to verify, please:

- star the repo
- share it with people running WordPress sites
- point them to the main plugin page so they can see what the ecosystem can actually do

Why do it?

Because agent-friendly open WordPress tooling helps more of the boring but important work get done.
