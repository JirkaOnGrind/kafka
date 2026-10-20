# Fresh local SEO acceptance — 2026-10-19T10:26:29.443793+00:00

**FAIL**

Target: http://localhost:8083. Production requests: 0. Fresh HTTP records: 1755. Baseline legacy URLs: 888.

Routing regressions: 0. Missing/inactive content URLs: 44 (separate from routing). Actionable failures: 4.

Catalog metadata preserved: True. Sitemap: 9792 eligible URLs, 6 files, languages cs, sk, de.

## Actionable failures

- missing_content_must_stay_404: "http://localhost:8083/sk/damska-tilka/3511-tilko-go-pogo-2.html"
- private_cart_noindex: "http://localhost:8083/cs/cart"
- private_cart_noindex: "http://localhost:8083/de/cart"
- private_cart_noindex: "http://localhost:8083/sk/cart"

Full failures: `failures.csv`. Missing data: `missing-content.csv`. Redirects: `legacy-url-results.csv`. Fresh HTTP and headers: `fresh-http.jsonl`.

Historical source HTTP was used only to classify the preserved baseline. All target evidence in this folder was fetched during this run; no earlier target HTTP cache was read.

Sitemap membership and alternate links are checked for every XML entry against existing eligible local records. HTTP coverage is the baseline plus normalization, language and pagination probes; it is not every catalog URL.

No new SQL, content, translations, media, order or production change is part of this audit. Local noindex protection must remain enabled. Production cutover and Search Console require separate authorization.
