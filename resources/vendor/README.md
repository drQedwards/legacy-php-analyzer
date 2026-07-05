# Vendored front-end assets

| File | Version | Source | License |
|------|---------|--------|---------|
| `vis-network.min.js` | 9.1.9 | https://cdn.jsdelivr.net/npm/vis-network@9.1.9/standalone/umd/vis-network.min.js | MIT / Apache-2.0 (dual) |

Committed to the repo on purpose: `HtmlReporter` inlines this bundle
into the generated report at generation time (plain string
concatenation — no build step), so the resulting HTML opens with **zero
network dependency**. To upgrade, download the new version to the same
path and update this table.
