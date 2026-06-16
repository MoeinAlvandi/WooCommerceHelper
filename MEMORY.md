# MEMORY

Working notes for resuming WooCamers Helper. Read `PROJECT_STATE.md` and `CLAUDE.md` alongside this.

Last updated: 2026-06-16 — current version `0.4.4`.

## What this session did

1. **Sample products / few-shot (0.4.0).** Settings section where the admin pastes product URLs; the plugin reads each product's attributes automatically and stores them. They are injected into the attributes prompt via `{sample_products}`. The AI decides similarity — there is deliberately **no category matching**, because the user's store keeps everything under one broad category.
2. **Global attributes for "Add attribute" (0.4.1).** Switched from per-product custom attributes to global `pa_*` taxonomies so products are filterable. Find-or-create attribute, find-or-create term, then assign.
3. **Persistence fix (0.4.2).** CRUD `set_attributes()` dropped freshly-created taxonomy attributes. Now uses raw `_product_attributes` meta + `wp_set_object_terms()` + fresh `save()`.
4. **Diagnostics + robust slug (0.4.3).** Response re-reads from DB (`verify`, `meta_keys`); slug truncation + ASCII-fallback retry for Persian names.
5. **Edit-screen reload (0.4.4).** After a successful add on the product edit screen, reload so the attribute shows in the metabox and is not overwritten on the next product save.
6. Created `CLAUDE.md` and these changelog/memory files.

## Key decisions & rationale

- **AI judges sample similarity, not code.** Requested explicitly by the user; do not reintroduce category-based matching.
- **Attributes are global (`pa_`), not custom.** Reason: the user wants to filter products by them later.
- **Raw meta write over CRUD `set_attributes()`.** The CRUD path silently drops attributes whose taxonomy was created in the same request. Keep the raw-meta approach.
- **Sample attributes are read by URL.** Local products resolve via `url_to_postid`; external URLs are scraped from the WooCommerce attributes table (`shop_attributes` / `woocommerce-product-attributes`).

## Gotchas confirmed this session

- A global attribute taxonomy created with `wc_create_attribute` in a request is **not registered** until the next page load; call `maybe_register_attribute_taxonomy()` before inserting/assigning terms.
- On the **product edit screen** the Attributes metabox is stale after an AJAX add; saving the product without reloading overwrites `_product_attributes` and wipes the addition. The 0.4.4 reload handles this.
- Persian/RTL labels: compare with `wh_normalize_label()` (strips ZWNJ/RLM/LRM); slugs may need the ASCII fallback.

## Open / not yet done

- Sample products only influence the **attributes** prompt. Short/product-description prompts can also use `{sample_products}` if the user wants — not wired into those tasks yet.
- Last user test (0.4.3) showed the success message **with counts**, confirming server-side persistence works; the remaining issue was display/overwrite on the edit screen, addressed in 0.4.4. **Awaiting user confirmation that 0.4.4 makes the attribute stick after reload.**
- No automated tests exist; verify with `php -l` and `node --check` before release.

## Environment note (this workspace only)

The Linux shell mount for `woocamers-helper.php` did not reliably sync edits made via the file tools (it showed a truncated/stale copy), so `php -l` could not be run here. The authoritative file is the one the file tools read/write. Lint on the real server before shipping.
