# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

WooCamers Helper is a single WordPress/WooCommerce admin plugin that generates product attributes, short descriptions, and full product descriptions with AI. There is **no build step, package manager, or test suite** — it is plain PHP plus vanilla jQuery and CSS that load directly in WordPress.

## Commands

There is no compiler or test runner. Validate changes with the language linters:

- PHP syntax check: `php -l woocamers-helper.php`
- JS syntax check: `node --check assets/js/front.js`

All logic lives in three files: `woocamers-helper.php` (everything server-side), `assets/js/front.js` (all admin/front JS), `assets/css/style.css` (all styles). Keep that structure — features are not split into modules.

## Architecture

Everything server-side is one class, `WooCamers_Helper`, instantiated once at file end. The constructor wires every WordPress hook (enqueues, admin menu, product-list column/row actions, the attributes-tab button, and all `wp_ajax_*` handlers). Read the constructor first to find an entry point.

**Settings** are a single option `wh_settings` (`self::OPTION_NAME`). Always go through `get_settings()` (which calls `normalize_loaded_settings()` to backfill defaults and remap legacy `connection_mode` values) and `sanitize_settings()` on save. Never read the raw option directly. Defaults come from `get_default_settings()` and the `get_default_*_prompt_template()` methods.

**Connection modes** (`connection_mode`): `mock` (returns canned data, no network), `endpoint` (custom JSON API), `gapgpt` (OpenAI Chat Completions), `avalai` (avalai.ir). `maybe_build_request_args()` builds the request body per mode — for `gapgpt`/`avalai` it sends Chat Completions messages (attributes task adds a strict `json_schema` response format); for `endpoint` it sends a `{mode, task, prompt, product}` envelope. Endpoint URL is chosen per mode inside each AJAX handler.

**Three AI tasks**, each a `wp_ajax_` handler following the same shape (resolve product → build context → fill prompt → branch by connection mode → parse response → return JSON with cost): `ajax_generate_attributes` (expects JSON `{attributes:[{name,value}]}`, parsed by `parse_attributes_response`), `ajax_generate_short_description` and `ajax_generate_product_description` (free text, parsed by `parse_text_response`). Response parsers are defensive and handle OpenAI, Responses-API, and raw-text shapes.

**Prompt templating**: `build_product_context()` assembles product fields; `replace_placeholders()` substitutes `{title}`, `{attributes}`, `{brand_description}`, `{sample_products}`, etc. into the template. When adding a new placeholder, update `replace_placeholders()`, the default templates, and the placeholder hint lists in `settings_page()`.

**Sample products (few-shot)**: admins paste product URLs in settings; `ajax_fetch_sample_product` reads attributes either from a local product (`url_to_postid` → `wc_get_product`) or by scraping the WooCommerce attributes table from remote HTML (`parse_attributes_from_html` via DOMDocument/DOMXPath). Stored attributes are injected into the attributes prompt via `{sample_products}` (auto-appended if the saved template lacks the placeholder). The AI — not category matching — decides which sample is similar.

**Adding attributes = global attributes**: `ajax_add_attributes` registers attributes as global WooCommerce taxonomies (`pa_*`) so products stay filterable, not as per-product custom attributes. The find-or-create flow: `get_or_create_global_attribute()` (matches existing by normalized label via `wh_normalize_label`, else `wc_create_attribute`) → `get_or_create_attribute_term()` for each value → assign with `wp_set_object_terms` → write `_product_attributes` meta directly with `is_taxonomy => 1`. Persisting via raw meta + a fresh `wc_get_product()->save()` is deliberate: the CRUD `set_attributes()` path silently drops freshly-created taxonomy attributes.

## Client-side flow

`front.js` is jQuery, namespaced `whData` (localized in `enqueue_admin`/`enqueue_scripts` with `ajax_url`, `nonce`, and UI strings). The attributes UX is: click "افزودن ویژگی" → `wh_generate_attributes` → modal with editable, checkbox-per-row results → "افزودن ویژگی‌ها" → `wh_add_attributes`. Button placement into WooCommerce's own metaboxes is done by polling timers (`init*ButtonPlacement`) because those panels render late.

## Gotchas

- **UI text and stored values are Persian/RTL.** Normalize for comparison with `wh_normalize_label()` (strips ZWNJ/RLM/LRM). Don't assume ASCII slugs — `wc_create_attribute` has a fallback to a hashed ASCII slug.
- **New global attributes need `register_taxonomy()` in the same request** before inserting terms (`maybe_register_attribute_taxonomy`); WooCommerce only auto-registers them on `init` of the next page load.
- **Edit-screen metabox overwrite**: after an AJAX attribute add on the product edit screen, the Attributes metabox is stale; saving the product would wipe the addition. The JS reloads the edit page after a successful add to avoid this.
- AJAX security: every handler calls `check_ajax_referer('wh_nonce','nonce')`; settings save checks `manage_woocommerce` + `check_admin_referer`.
- Bump the version in **both** the plugin header docblock and `const VERSION` together on any release.

## Reference docs

`README.md` (Persian, user-facing: connection modes, placeholders, custom-API contract) and `PROJECT_STATE.md` (running changelog of what is implemented and key function names) are kept up to date — read `PROJECT_STATE.md` first when resuming work.
