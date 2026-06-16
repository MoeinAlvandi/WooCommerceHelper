# WooCamers Helper - Project State

Last updated: 2026-06-16

## Goal

WooCommerce helper plugin for generating product attributes and product text with AI, with admin settings and quick action buttons in the product list and product edit screen.

## Current Status

- Plugin version: `0.4.2`
- Main plugin file: `woocamers-helper.php`
- Frontend/admin assets:
  - `assets/js/front.js`
  - `assets/css/style.css`

## What Is Implemented

- WooCommerce submenu: `Helper WooCamers`
- Settings page with saved options:
  - connection mode: `mock`, `endpoint`, `gapgpt`
  - API endpoint
  - API key
  - OpenAI model
  - reasoning effort
  - USD to Toman rate
  - prompt template for attributes
  - prompt template for product description
  - prompt template for short description
  - brand description
  - sample products (reference products entered by URL, with auto-read attributes)
- Product list action button: `افزودن ویژگی`
- Product edit screen button inside the attributes section: `افزودن ویژگی`
- AI modal for generated attributes:
  - shows the full prompt
  - editable attribute name/value fields
  - checkbox per row
  - adds only checked rows
- Short description generation:
  - button near the excerpt editor media buttons
  - uses its own prompt template
  - inserts result into the short description field
- Product description generation:
  - button near the main editor media buttons
  - uses its own prompt template
  - inserts result into the main product content editor
- Cost display:
  - returns estimated API cost in USD
  - converts to Toman using the saved USD rate
  - shows both values in the UI

## Sample Products (Few-Shot Reference) — v0.4.0

- New settings section "محصولات نمونه": admin pastes one or more product URLs and clicks "خواندن ویژگی‌ها".
- Attributes are read automatically:
  - if the URL maps to a product on this same site, attributes are read directly from the database (`url_to_postid` + `wc_get_product`);
  - otherwise the page HTML is fetched and the WooCommerce attributes table (`shop_attributes` / `woocommerce-product-attributes`) is scraped via `DOMDocument`/`DOMXPath`.
- Read attributes (name/value) are stored per sample inside the option, and shown as a preview under each row.
- During attribute generation, all sample products + their attributes are injected into the prompt via the `{sample_products}` placeholder. The AI itself decides whether the current product is similar to any sample (no hard category matching) and, if so, reuses the same attribute names with values adapted to the current product. Sample values are passed as examples only.
- If the saved attributes prompt template does not contain `{sample_products}`, the sample block is appended to the prompt automatically so the feature still works for existing installs.

## Add Attributes = Global Attributes — v0.4.1

`ajax_add_attributes()` now registers attributes as **global WooCommerce attributes** (`pa_*` taxonomies) instead of per-product custom attributes, so products become filterable:

- For each requested name/value: find the global attribute by normalized label/name; if missing, create it with `wc_create_attribute()` and register the taxonomy in-request.
- For the value (term): if the term already exists in that taxonomy, reuse it; otherwise create it with `wp_insert_term()`.
- Merge new term ids with any already assigned to the product, then `wp_set_object_terms()` (this is what powers layered-nav/filters) and update the product's `WC_Product_Attribute` with `set_id()` + term-id options.
- Any leftover custom (non-taxonomy) attribute with the same label is removed to avoid duplicate keys.
- Helpers: `wh_normalize_label()`, `maybe_register_attribute_taxonomy()`, `get_or_create_global_attribute()`, `get_or_create_attribute_term()`.
- Note: after adding, reload the product edit screen to see the attributes in the Attributes tab.

### v0.4.2 — reliable persistence

The CRUD path (`set_attributes()` + `save()`) silently dropped freshly-created taxonomy attributes. Now persistence is done via the raw storage format, which is the proven method:

1. `wp_set_object_terms()` assigns term ids to the product for each `pa_*` taxonomy (powers filters).
2. `_product_attributes` post meta is written directly with `is_taxonomy => 1`, `value => ''` per taxonomy.
3. A fresh `wc_get_product()->save()` fires `woocommerce_update_product` so the attributes-lookup table regenerates; then `wc_delete_product_transients()`.
4. The AJAX response returns `added_terms` / `new_attrs` / `errors`, and the modal now shows those counts so the result is verifiable.

## Important Behavior

- `mock` mode returns sample generated content locally.
- `gapgpt` mode sends requests to the OpenAI Chat Completions endpoint by default unless a custom endpoint is set.
- Product context is passed into the prompt and also included as JSON in the request payload for visibility/debugging.
- The prompt preview shown in the attribute modal is the full prompt sent to the AI.

## Important Files And Functions

### `woocamers-helper.php`

- Settings defaults:
  - `get_default_settings()`
  - `get_default_prompt_template()`
  - `get_default_short_description_prompt_template()`
  - `get_default_product_description_prompt_template()`
- Settings save:
  - `handle_settings_save()`
  - `sanitize_settings()`
  - `sanitize_sample_products()`
  - `get_settings()`
- Sample products:
  - `build_sample_products_text()`
  - `fetch_sample_product_attributes()`
  - `extract_attributes_from_local_url()`
  - `extract_attributes_from_remote_url()`
  - `parse_attributes_from_html()`
  - `ajax_fetch_sample_product()`
- Product context:
  - `build_product_data()`
  - `build_product_context()`
  - `format_product_attributes()`
  - `get_product_tags_list()`
  - `replace_placeholders()`
- API request helpers:
  - `maybe_build_request_args()`
  - `calculate_estimated_cost()`
  - `calculate_estimated_toman_cost()`
  - `extract_usage_data()`
- AJAX handlers:
  - `ajax_generate_attributes()`
  - `ajax_add_attributes()`
  - `ajax_generate_short_description()`
  - `ajax_generate_product_description()`
- UI:
  - `render_product_attributes_button()`
  - `settings_page()`
  - `enqueue_admin()`
  - `enqueue_scripts()`

### `assets/js/front.js`

- Attribute modal rendering and editing
- Short description insertion
- Product description insertion
- Button placement logic in admin
- Cost formatting and display

### `assets/css/style.css`

- Modal styling
- Attribute row styling
- Short description actions styling
- Product description actions styling
- Cost note styling

## Notes For Next Session

- Start by reading this file first.
- PHP CLI is not available in the current environment, so `php -l` could not be run here.
- JS syntax check passed with `node --check assets/js/front.js`.
- If you continue development, likely next steps are:
  - refine the product description prompt and editor placement
  - improve cost presentation in the admin UI
  - optionally add a test connection button in settings

