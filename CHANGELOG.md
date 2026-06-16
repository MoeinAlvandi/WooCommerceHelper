# Changelog

All notable changes to WooCamers Helper are documented here.
Format loosely follows [Keep a Changelog](https://keepachangelog.com/).

## [0.4.4] - 2026-06-16

### Fixed
- On the product **edit screen**, after a successful attribute add, the page now reloads (via a confirm dialog) so the freshly added global attribute appears in the Attributes metabox and is not wiped when the product is next saved. This addresses the "says added but nothing sticks" report — the data was persisting, but the stale metabox overwrote it on the next product save.

## [0.4.3] - 2026-06-16

### Added
- Server-side verification in the add-attributes response (`verify`, `meta_keys`): after saving, the product is re-read from the database and the actually-stored taxonomies/terms are returned and shown in the result dialog, so persistence can be confirmed from the UI.

### Changed
- More robust global-attribute creation: the taxonomy slug is truncated to the 28-char limit, and creation is retried once with a safe ASCII slug if a Persian/long slug fails.

## [0.4.2] - 2026-06-16

### Fixed
- Attributes reported as added but not actually saved. The CRUD path (`set_attributes()` + `save()`) silently dropped freshly-created taxonomy attributes. Persistence now uses the proven raw method: `wp_set_object_terms()` to assign terms, direct `_product_attributes` meta write with `is_taxonomy => 1`, then a fresh `wc_get_product()->save()` to fire WooCommerce hooks (regenerating the attributes-lookup table used by filters), followed by `wc_delete_product_transients()`.

### Changed
- The result dialog now shows real counts returned by the server (`added_terms`, `new_attrs`) and any per-item errors instead of a static success string.

## [0.4.1] - 2026-06-16

### Changed
- "Add attribute" now registers attributes as **global WooCommerce attributes** (`pa_*` taxonomies) instead of per-product custom attributes, so products remain filterable. Find-or-create logic: existing attribute reused if its normalized label matches (`wh_normalize_label`), otherwise created with `wc_create_attribute`; existing term values reused, missing values added as new terms. Helpers added: `wh_normalize_label()`, `maybe_register_attribute_taxonomy()`, `get_or_create_global_attribute()`, `get_or_create_attribute_term()`.
- Duplicate custom (non-taxonomy) attributes with the same label are removed in favor of the global one.

## [0.4.0] - 2026-06-16

### Added
- **Sample products (few-shot)** setting. Admins paste one or more product URLs and click "خواندن ویژگی‌ها"; attributes are read automatically — directly from the database for local products (`url_to_postid` + `wc_get_product`), or by scraping the WooCommerce attributes table from the page HTML (`DOMDocument`/`DOMXPath`) for external URLs.
- New AJAX handler `ajax_fetch_sample_product` and helpers `sanitize_sample_products()`, `build_sample_products_text()`, `fetch_sample_product_attributes()`, `extract_attributes_from_local_url()`, `extract_attributes_from_remote_url()`, `parse_attributes_from_html()`.
- New prompt placeholder `{sample_products}`. During attribute generation, all sample products + attributes are injected; the AI itself decides whether the current product is similar (no hard category matching) and reuses the matching attribute names with values adapted to the current product. If the saved template lacks the placeholder, the sample block is appended automatically.
- Settings UI for sample products (repeatable URL rows with fetch/remove buttons and an attribute preview) plus supporting JS in `front.js` and CSS in `style.css`.

## [0.3.3] - 2026-06-10

- Baseline before this session: attribute / short-description / product-description generation, four connection modes (`mock`, `endpoint`, `gapgpt`, `avalai`), per-request cost estimate, brand description, editable attribute modal.
