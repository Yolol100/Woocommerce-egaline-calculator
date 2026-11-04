# Repository 3/37: WooCommerce Egaline Calculator quality log

Audit date: 2026-11-04. Status: **review pending — not a release or production sign-off**.

- Baseline: `main` commit `4c016ad327b75f508f6541ca86ca07e22ebbe3da`.
- Product: WooCommerce Egaline Calculator, release metadata `1.0.9`, WordPress 6.8+, PHP 8.2+, WooCommerce required.
- Change: [draft PR #27](https://github.com/Yolol100/Woocommerce-egaline-calculator/pull/27), branch `quality/cart-data-type-guards-20261104`.
- Reviewed: initial bootstrap/requirements, `Egaline_Calculator_Cart` hooks, frontend discount tests and JS, custom pricing, runtime compatibility matrix, checkout metadata, settings metadata and readme.
- CI runs in isolated MySQL/WordPress/WooCommerce with synthetic products. **No connected customer store or checkout was modified**.

## Confirmed issues

| ID | Severity | Problem / test-first evidence | Correction |
|---|---|---|---|
| EGC-001 | High reliability | A session or cart entry with `calculator_data` set to a scalar (not an array) triggered a fatal `TypeError` during minicart/session recovery on both runtime combinations: [red #41844434261](https://github.com/Yolol100/Woocommerce-egaline-calculator/actions/runs/41844434261). | Reuse the existing canonical `cart_item_has_valid_calculator_data()` guard in session restoration and verify `is_array` before the typed validator. The same tests pass: [green #41844703720](https://github.com/Yolol100/Woocommerce-egaline-calculator/actions/runs/41844703720). |
| EGC-002 | High reliability / pricing | Nested non-scalar or nonfinite values inside `calculator_data` were accepted after PHP casts; the minicart could replace a price from malformed data. [Red valid-product fixture #41845316305](https://github.com/Yolol100/Woocommerce-egaline-calculator/actions/runs/41845316305). | Reject nonnumeric, nonfinite and nonpositive values; require a positive integral count for bags. [Green runtime #41845598604](https://github.com/Yolol100/Woocommerce-egaline-calculator/actions/runs/41845598604). Existing valid threshold/quantity behavior and product cloning were tested separately: [#41845844215](https://github.com/Yolol100/Woocommerce-egaline-calculator/actions/runs/41845844215). |
| EGC-003 | Low / repository quality | `readme.txt` listed older changelog entries before current release 1.0.9. [Red release metadata gate #41846182699](https://github.com/Yolol100/Woocommerce-egaline-calculator/actions/runs/41846182699). | Move 1.0.9 to the top, preserve existing changelog content, and extend the existing release validator to reject stale/duplicate/out-of-order entries. |

## Sources compared

- [WooCommerce core: cart totals](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/class-wc-cart-totals.php): cart totals come from a validated product price multiplied by quantity. Kept WooCommerce's native `set_price()` cart-item mechanism.
- [WooCommerce formatting functions](https://woocommerce.github.io/code-reference/files/woocommerce-includes-wc-formatting-functions.html): decimal formatting and rounding are handled by the platform; validation must reject nonfinite and nonnumeric inputs before they reach pricing.
- [WooCommerce Store API cart documentation](https://developer.woocommerce.com/docs/apis/store-api/resources-endpoints/cart/): Store API and Cart/Checkout Blocks require separate integration proof; classic add-to-cart hooks alone cannot prove this.
- [10up/wp-scaffold](https://github.com/10up/wp-scaffold): automated source and runtime gates; extended existing CI rather than creating a parallel system.
- [WordPress Plugin Check](https://wordpress.org/plugins/plugin-check/): static checks and runtime checks are complementary. Plugin Check itself was **not** run.

## Current acceptance and open risks

- CI: existing frontend JavaScript pricing tests; clean WordPress 6.8 / PHP 8.2 / WooCommerce 10.7.0 and WordPress 7.1 / PHP 8.3 / WooCommerce 11.1.0 installation, syntax, hook tests, invalid cart data rejection, and valid quantity-dependent discount with unmodified original product price.
- Before claiming green on the final candidate, recheck **all workflows against the exact final head** after this report is committed.
- Not proven: real product->cart->checkout->order placement with tax/coupons and Store API blocks; payment gateway flows; variable products, shipping, HPOS upgrade/downgrade, third-party session formats and staging/browser/mobile QA.
- `filter_cart_item_subtotal()` formats custom subtotal itself and may not preserve tax-display conventions in every shop; **risk noted, not changed without targeted tax scenarios**.
- Existing pricing, discount thresholds and client-visible behavior are preserved. No blind refactors of JS, settings, meta keys or checkout hooks. Version remains 1.0.9 until separately approved release packaging with changelog/version parity and ZIP re-extraction.
- No release or merge performed. To roll back, close PR #27. `main` remains unchanged.
