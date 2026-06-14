# WooCamers Helper - Project State

Last updated: 2026-06-10

## Goal

WooCommerce helper plugin for generating product attributes and product text with AI, with admin settings and quick action buttons in the product list and product edit screen.

## Current Status

- Plugin version: `0.3.3`
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
  - `get_settings()`
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

