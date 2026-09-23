# CMCP Change Journal

## Canonization read

Read current local Canonization `AGENTS.md`, `README.md`, `MANIFEST.json`, Canon000/007/009/018/020/022/023/024/025/026/029/031/032/033/034/039/043, and the corresponding current executable Gating rules.

## Target-to-canon mapping

- Composer: `promoting/promotion`.
- Namespace: `App\\Promoting\\`.
- Subject: `Promotion*`.
- Dual runtime: standalone Symfony plus reusable `PromotingBundle`.
- Mandatory sibling dependencies use symlinked path repositories and exact `dev-master`.
- Production manifest uses package dependencies only.
- Generic CRUD remains in Cruding.
- Campaign remains inside Promoting; no Campaigning repository is created.

## Baseline repository state

The target repository did not exist before materialization. Requested collision aliases returned no canonical repository-file evidence, and workspace creation succeeded without overwrite.

## Created

Composer manifests and lockfile, Symfony bootstrap/bundle surfaces, component-owned `.gating/profile.yaml`, quality tooling, smoke test, ignore baseline, boundary/canon/roadmap/benchmark docs, Git repository metadata on `master`, and this journal.

## Risks

- Composer install depends on sibling package consistency and external resolution.
- No speculative promotion model is created in this wave.

## Gates to run

Composer validation/install, PHP lint, PHPUnit, coverage where driver permits, PHPStan, PHP-CS-Fixer dry-run, Gating, and Symfony boot/container checks.

## Validation result

All requested component-local gates passed after repair: Composer validation/install, explicit PHP syntax lint, PHPUnit, Xdebug branch coverage with persistent summary, PHPStan, PHP-CS-Fixer dry-run, selected Gating rules (17/17; zero failures/warnings/skips), Symfony boot, YAML lint, and container lint.

## Milestone notation

Replaced the repository's compact M-prefixed milestone labels with `Milestone 1` through `Milestone 4`. The standalone `M` token is reserved for CMP goal budget magnitude and must not be reused as a milestone prefix.

## 2026-09-20 product capability implementation pass

Implemented the earliest incomplete product capabilities through the current promotion-side Milestone 4 boundary:

- deterministic promotion lifecycle, ordered eligibility conditions, fixed and basis-point percentage application;
- coupon lifecycle/validation, global and per-customer limits, immutable idempotent redemption/reversal ledger semantics;
- deterministic priority ordering, stacking, exclusivity, and stable tie-breaking;
- campaign grouping, activation/pause/resume, explicit validity windows, budgets, and bounded spend;
- typed BXGY, free-gift, and free-shipping benefit facts without taking Carting, Ordering, Shipping, Pricing, or Rewarding ownership.

Durable coupon issuance/redemption persistence and direct sibling-consumer wiring remain explicit integration follow-ups rather than speculative infrastructure in this repository. Promotion-level validity windows are implemented with explicit caller-supplied evaluation time.

Verification after implementation: PHPUnit 18 tests / 61 assertions green; PHPStan zero errors; PHP-CS-Fixer clean; Gating 17/17 with zero failures/warnings/skips; Xdebug branch-coverage execution green. Promotion-level validity windows were then added with explicit caller-supplied evaluation time; BXGY explanation now preserves ordinary eligibility reasons.

Final platform verification also passed: Composer strict validation, PHP syntax lint across all changed/untracked PHP files, Symfony standalone boot, YAML lint, and container lint. Carting's existing promotion provider was inspected: it can consume monetary adjustment facts, but production wiring still requires a real promotion catalog/persistence source and deterministic clock/composition decision. No empty provider, fake repository, or cross-boundary stub was introduced.

## 2026-09-21 Milestone 5

Added coupon issuance as a complete in-memory business slice: immutable coupon book with normalized uniqueness, explicit issue/deactivate lifecycle, issuance timestamp, optional validity window, optional customer binding, and deterministic validation/redeem time context. Existing coupon limit and redemption semantics remain compatible with coupons that have no issuance metadata.

Verification: PHPUnit 21 tests / 76 assertions green; PHPStan zero errors; PHP-CS-Fixer clean; Gating 17/17 with zero failures/warnings/skips; Xdebug branch-coverage execution green.

## 2026-09-21 Milestone 6

Added an immutable promotion catalog with promotion-id uniqueness and deterministic eligible selection. Selection preserves every promotion evaluation result for auditability and returns eligible promotions ordered by priority descending and id ascending.

Verification: PHPUnit 24 tests / 83 assertions green; PHPStan zero errors; PHP-CS-Fixer clean; Gating 17/17 with zero failures/warnings/skips; Xdebug branch-coverage execution green.

## 2026-09-22 Milestone 7

Added coupon-to-promotion resolution across the immutable coupon book and promotion catalog. Resolution validates coupon lifecycle/audience/limits/window first, resolves the linked promotion second, then evaluates the promotion and preserves the combined reason trail without mutating redemption state.

### Current RC verification and tooling closure

- Re-read Promoting boundary, current capability audit/roadmap, coupon/evaluation/application services, Composer/runtime configuration, current dirty slice, and the shared Canonization/Gating plus Objecting/Cruding/Viewing/Interfacing contour.
- Market comparison confirms deterministic rank/stacking, coupon validity/usage limits, campaign windows, and explainable eligibility as promotion-engine concerns; Pricing, Carting, Ordering, Shipping, Rewarding, and payment execution remain external owners.
- Preserved the pre-existing Milestone 7 implementation and verified it rather than replacing it.
- Added canonical consumer Gating integration, target-owned Gating profile, Symfony Test Pack/Panther, repository-local Playwright smoke, and reproducible behavioral coverage evidence.
- Untracked generated config/reference.php and ignored it per Canon037; authoritative configuration remains source-controlled.
- `composer quality`: PASS — PHPUnit 28/28 with 98 assertions, PHPStan clean, PHP-CS-Fixer clean, Playwright 1/1, behavioral coverage evidence generated, Gating 68 rules with 0 failures.
- `composer validate --strict --check-lock`: PASS.
- Fresh coverage: lines 84.3%, methods 42.3%, branches 79.2%. Canon040 remains a non-blocking HIGH_TEST_DEBT warning because method coverage is below target; line and branch thresholds pass.
- No persistence or product HTTP API was invented. Coupon resolution is read-only against coupon book/redemption ledger/catalog state and does not mutate redemption or absorb neighboring ownership.
- Git has no configured remote/upstream, so publication/PR is unavailable after local integration.

## 2026-09-22 Milestone 8

Added an end-to-end coupon application transaction over explicit immutable state: resolve coupon, evaluate/apply its linked promotion, then record redemption only after successful application. Same coupon/customer/order replays validate against a ledger view that excludes that same active redemption, then flow through the existing idempotent redemption branch without duplicating usage.

Verification: PHPUnit 31 tests / 114 assertions green; PHPStan clean; PHP-CS-Fixer clean; Playwright/behavioral coverage green; Gating 68 rules with 0 failures and one non-blocking Canon040 HIGH_TEST_DEBT warning. Fresh PHP coverage: lines 83.3%, methods 43.2%, branches 79.5%.

## 2026-09-22 Milestone 9

Added campaign-scoped promotion selection. Campaign availability is evaluated first with explicit time context; member IDs are resolved through PromotionCatalog; missing members remain visible as audit reasons; resolved members are evaluated and ordered through the existing deterministic selection service.

Verification: PHPUnit 35 tests / 129 assertions green; PHPStan clean; PHP-CS-Fixer clean; Playwright/behavioral coverage green; Gating 68 rules with 0 failures and one non-blocking Canon040 HIGH_TEST_DEBT warning. Fresh PHP coverage: lines 84.4%, methods 46.8%, branches 80.3%.

## 2026-09-22 Milestone 10

Added campaign application with atomic spend accounting. Campaign-scoped eligible promotions run through the existing stacking resolver; zero-discount applications do not consume budget; a discount that would exceed the remaining campaign budget is rejected without changing campaign state; successful discounts are recorded as cumulative spentMinor.

Verification: PHPUnit 39 tests / 146 assertions green; PHPStan clean; PHP-CS-Fixer clean; Playwright/behavioral coverage green; Gating 68 rules with 0 failures and one non-blocking Canon040 HIGH_TEST_DEBT warning. Semantic PHPDoc coverage is 75.0%. Fresh PHP coverage: lines 84.5%, methods 47.5%, branches 80.6%.

## 2026-09-22 Milestone 11

Added explicit promotion activation modes. Promotions default to automatic activation for backward compatibility; coupon-required promotions are excluded from ordinary catalog selection with the auditable reason `promotion_coupon_required`, while coupon resolution can still evaluate and activate them through a validated coupon code.

Verification: PHPUnit 40 tests / 150 assertions green; PHPStan clean; PHP-CS-Fixer clean; Playwright/behavioral coverage green; Gating 68 rules with 0 failures and one non-blocking Canon040 HIGH_TEST_DEBT warning. Semantic PHPDoc coverage remains 75.0%; fresh PHP coverage remains lines 84.5%, methods 47.5%, branches 80.6%.

## 2026-09-22 coverage hardening

Closed the remaining Canon040 test-debt warning without weakening domain invariants. Added invariant, edge-case, coverage-completion, and standalone Kernel tests; simplified opaque CFG-heavy expressions into explicit business branches; removed one unreachable BXGY defensive branch already guaranteed by PromotionBenefit construction; and added a reusable local HTML branch-coverage script under ignored var/coverage/html.

Final verification: PHPUnit 120 tests / 304 assertions green; PHPStan clean; PHP-CS-Fixer clean; Playwright/behavioral coverage green; Gating 68 rules with 0 failures and 0 warnings. Canon040 now passes at lines 100.0%, methods 80.0%, branches 94.7%; Canon031 remains green at 75.0% contract-method PHPDoc coverage.

## 2026-09-22 Milestone 12

Added a read-only checkout promotion plan that combines automatic promotions and an optional validated coupon promotion before one priority/stacking/exclusivity resolution. Typed benefits are emitted only for promotions actually reached and eligible in that resolution. Monetary and benefit requests must describe the same subtotal, currency, and evaluation time. Planning does not mutate coupon redemption, campaign, Cart, or Order state.

Verification: PHPUnit 128 tests / 325 assertions green; PHPStan clean; PHP-CS-Fixer clean; Playwright/behavioral coverage green; Gating 68 rules with 0 failures and 0 warnings. Canon040 remains green at lines 99.7%, methods 80.0%, branches 94.5%; Canon031 is green at 75.4% contract-method PHPDoc coverage.

## 2026-09-23 Milestone 13

Added unified checkout promotion application over promotion-owned state. Coupon redemption is recorded only when the linked coupon promotion actually participates in the unified priority/stacking/exclusivity resolution. Higher-priority exclusive automatic promotions can displace a coupon without consuming it; invalid coupons leave the ledger unchanged; same-order replay remains idempotent at usage limits; and redemption-backend failure preserves the original ledger.

Verification: PHPUnit 138 tests / 355 assertions green; PHPStan clean; PHP-CS-Fixer clean; Playwright/behavioral coverage green; Gating 68 rules with 0 failures and 0 warnings. Canon040 passes at lines 99.7%, methods 80.7%, branches 94.7%; Canon031 remains green above threshold.

