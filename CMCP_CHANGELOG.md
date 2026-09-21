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

