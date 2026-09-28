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

## 2026-09-23 Milestone 14

Added checkout coupon reversal over promotion-owned immutable ledger state. Reversal is addressed by coupon/customer/order identity, is idempotent when the redemption is already absent or reversed, preserves missing-coupon outcomes as auditable no-op results, and does not mutate Cart or Order state.

Verification: PHPUnit 144 tests / 369 assertions green; PHPStan clean; fresh Xdebug coverage remains above canonical thresholds at lines 99.8%, methods 80.0%, branches 94.4%. Composer strict/check-lock remains valid on the canonical symlink dependency topology.

Canonical enforcement note: local Canonization Canon053 explicitly permits symlinked sibling repositories for Gating, Cruding, Viewing, Interfacing, Collectioning, Objecting, Tabling, Runtime, and Indexing. During Milestone 14 verification the installed Gating mirror briefly exposed an older four-item exception contour; an experimental VCS migration was fully reverted after proving that local VCS would allow Composer to attempt checkout operations in dirty sibling working repositories. Gating was subsequently synchronized with Canonization, Canon053 now passes on the normative symlink topology, and no Composer workaround remains in Promoting.

## 2026-09-23 Milestone 15

Added per-order campaign spend transactions over immutable promotion-owned state. Campaign application now has an optional transaction layer that records spend by campaign/order identity, preserves same-order idempotent replay, validates aggregate/ledger synchronization, and reverses spend atomically so cancelled/refunded orders release campaign budget without mutating Cart or Order ownership.

Verification: PHPUnit 153 tests / 415 assertions green; PHPStan clean; PHP-CS-Fixer clean; Playwright/behavioral coverage green; Composer strict/check-lock and PHP lint green; Symfony boot, YAML lint, and container lint green. Gating 69 rules with 0 failures and 0 warnings. Canon031 passes at 73.7% contract-method PHPDoc coverage. Canon040 passes at lines 99.8%, methods 81.0%, branches 94.6%.

## 2026-09-23 Milestone 16

Added explicit campaign-only promotion activation. Ordinary automatic selection now excludes both coupon-required and campaign-only promotions with stable reason codes; campaign selection explicitly admits automatic plus campaign-only promotions while continuing to reject coupon-only promotions. This prevents campaign-scoped incentives from leaking into ordinary checkout planning when all promotion definitions share one catalog.

Verification before final aggregate quality: PHPUnit 155 tests / 423 assertions green; PHPStan clean; fresh Xdebug coverage lines 99.8%, methods 80.4%, branches 94.0%. No new persistence, Cart/Order mutation, or sibling repository changes were introduced.

## 2026-09-23 Milestone 17

Extended the existing read-only checkout planner with an optional explicit campaign context. Eligible campaign promotions are resolved through the campaign selection service, merged with automatic and optional coupon promotions before the single priority/stacking/exclusivity resolver, and preserved as campaign selection evidence in the plan result. Typed benefits participate in the same final resolution. Planning remains side-effect free: campaign spend/budget, coupon redemption, Cart, and Order state are not mutated.

Verification: PHPUnit 157 tests / 437 assertions green; PHPStan clean; PHP-CS-Fixer clean; Playwright/behavioral coverage green; Symfony YAML/container lint green; Gating 69 rules with 0 failures and 0 warnings. Canon031 passes at 75.6% contract-method PHPDoc coverage. Canon040 passes at lines 99.8%, methods 80.4%, branches 94.0%. Active-campaign inclusion and inactive-campaign fallback are covered explicitly.

## 2026-09-23 Milestone 18 acceptance

Integrated campaign spend accounting into unified checkout application without transferring Cart or Order ownership. Checkout now derives campaign monetary spend from the final unified promotion resolution, records spend only for actually applied campaign discounts, preserves same-order idempotent replay, rejects aggregate/ledger drift and replay amount drift, releases prior spend before replanning a replay, and refuses budget overflow by replanning without campaign promotion effects. Non-monetary campaign benefits do not consume campaign budget.

Verification: PHPUnit 167 tests / 477 assertions green; PHPStan clean; PHP-CS-Fixer clean; behavioral/UI evidence refreshed; Gating 70 rules with 0 failures and 0 warnings. Canon040 remains green at lines 99.5%, methods 80.0%, branches 94.0%. Canon052/053 integration passes on the current packaged Gating contract.

## 2026-09-24 RC hardening and Canon052 closure

Reconnaissance re-read Promoting's boundary, roadmap, capability audit, current runtime/package contour, Objecting/Cruding/Viewing/Interfacing contracts, and normative Canonization rules including Canon021, Canon022, Canon045, Canon052, and Canon053. The market baseline was refreshed against current promotion-engine practice: campaign windows, spend/usage budgets, usage limits, and per-customer scoping remain promotion-domain concerns; pricing, cart/order aggregates, shipping execution, payment, and loyalty remain outside Promoting.

RC-critical work stayed separate from growth. No speculative promotion feature was added. The only factual RC blocker was Canon052: consumer `.gating/` had been polluted by a materialized copy of the Gating owner tree. The snapshot was preserved locally under ignored `var/`, `.gating/` was restored to its artifact-only consumer topology, and its README now states the canonical boundary. Growth candidates such as richer attribute-scoped campaign budgets and operator/admin UX remain post-RC work.

Acceptance evidence: Composer strict/check-lock validation passes; PHPUnit 167 tests / 477 assertions passes; PHPStan has zero errors; PHP-CS-Fixer reports zero fixable files; Playwright 1/1 passes; behavioral/UI evidence regenerates; Gating 70 rules reports 0 failures and 0 warnings, including Canon052 and Canon053. The aggregate `composer quality` wrapper itself exceeded Console MCP's bounded call timeout when re-run, so the same declared constituent scripts were executed independently with successful exit codes. No PHP source changed in this hardening pass.

## 2026-09-24 Milestone 19

Added global campaign application limits independently from monetary campaign budget. Campaign usage is represented by an immutable campaign/order ledger with deterministic active counts, same-order idempotent replay, and idempotent reversal that releases capacity. Campaign transactions now keep spend and usage replay state consistent and require explicit usage state when a limit is configured.

Unified checkout enforces the same limit before campaign effects participate. Exhausted campaigns fall back to non-campaign resolution with explicit reason codes; usage is recorded only when a campaign promotion actually participates in the final resolver, including benefit-only campaigns that consume no monetary budget. Spend replay without its corresponding usage record fails closed.

Verification: fresh Xdebug coverage is green at 182 tests / 544 assertions with lines 99.66%, methods 80.15%, branches 94.47%. Final aggregate quality is green at 182 tests / 544 assertions; PHPStan and PHP-CS-Fixer are clean, Playwright passes 1/1, behavioral evidence regenerates, Symfony YAML/test-container lint passes, Composer strict/check-lock validation passes, and Gating reports 9 rules with 0 failures and 0 warnings. Canon055 was also applied: Promoting human-facing descriptions now use neutral platform/component terminology, while the Smart Responder/Responsor aliases remain consumer/domain-only identities.

## 2026-09-25 RC replay-drift hardening

Reconnaissance re-read the Promoting boundary, roadmap, capability audit, Composer/runtime wiring, checkout/campaign transaction hotspots, Objecting/Cruding/Viewing/Interfacing contracts, Gating, and the applicable textual Canonization rules. The target mapping remains promoting/promotion -> App\\Promoting\\ -> Promotion*, with dual runtime, exact dev-master sibling path identities, no generic CRUD ownership, and no forbidden architecture roots.

Market comparison was refreshed against Medusa campaign budgets, Vendure condition/action promotion modeling, and Shopify discount combination/usage-limit behavior. RC-critical work remains lifecycle/idempotency safety; richer combination matrices, reservation/hold semantics, and additional operator UX remain post-RC growth.

The baseline composer quality gate passed before mutation at 182 tests / 544 assertions, PHPStan clean, PHP-CS-Fixer clean, Playwright 1/1, behavioral coverage generated, and Gating 9/9 with zero failures/warnings. A semantic replay gap remained outside those deterministic gates: an already-redeemed coupon could be re-planned for the same order after eligibility/stacking drift, return a plan in which the coupon no longer participated, yet leave the prior active redemption in the result ledger.

Checkout replay now fails closed when an existing coupon redemption no longer resolves to an eligible/reached coupon promotion in the current unified plan. Replay planning removes the matching prior redemption regardless of current coupon-book lookup so missing/deactivated/reordered coupon state cannot silently return a contradictory plan/ledger pair. A regression test covers participation drift caused by a newly dominant exclusive automatic promotion.

Post-change acceptance is green: PHP lint passes on the two changed PHP files; Composer strict/check-lock validation passes; aggregate composer quality passes at 184 tests / 548 assertions with PHPStan clean, PHP-CS-Fixer clean, Playwright 1/1, behavioral evidence regenerated, and Gating 9/9 with zero failures/warnings; fresh Xdebug branch coverage is lines 99.66% (1201/1205), methods 80.15% (101/126), branches 94.48% (874/925); Symfony YAML lint validates both config files and test-container lint passes. Two regression cases cover stacking participation drift and a missing current coupon definition.

A follow-up acceptance pass classified the pre-existing dirty .gating tree as an accidental copy of the Gating owner repository: the tracked consumer README had been overwritten by Gating's own README and owner sources/configuration had been materialized beneath the consumer artifact surface. The tree was preserved non-destructively under ignored var/gating-owner-tree-quarantine-20260925-1807, then the canonical artifact-only .gating/README.md was restored from HEAD. A fresh aggregate composer quality run remained green at 184 tests / 548 assertions, Playwright 1/1, PHPStan/CS clean, Gating 9/9 with zero failures/warnings, and the owner-tree did not rematerialize. The repository worktree returned to clean state before this journal closure.

## 2026-09-26 RC benefit-only campaign replay hardening

Reconnaissance re-read all tracked Promoting Markdown/AsciiDoc documentation, the component boundary and roadmap, Composer/runtime manifests, campaign/coupon checkout services and tests, plus the mandatory Objecting, Cruding, Viewing, Interfacing, Gating, and Canonization contours. Normative textual rules consulted directly in Canonization included Canon001 (technical-role-first), Canon002 (implementation/interface mirroring), Canon007 (literal PSR-4 identity), Canon018 (Composer component/subject identity), and Canon033 (development/production manifest identity parity). The target mapping remains promoting/promotion -> App\\Promoting\\ -> Promotion*, with zero generic CRUD controllers/routes and no forbidden Domain/Port/Adapter/Adaptor topology.

RC-critical work remains lifecycle and idempotency safety. Growth remains separate: richer campaign targeting/budget dimensions, operator UX, and broader sibling integrations are post-RC unless independently required for correctness.

Baseline acceptance was green before mutation: Composer strict validation passed; aggregate composer quality passed at 184 tests / 548 assertions with PHPStan clean, PHP-CS-Fixer clean, Playwright 1/1, behavioral evidence generated, and Gating 9/9 with zero failures/warnings.

A semantic replay gap remained for benefit-only campaigns: an active campaign usage record can exist without monetary spend, and a same-order replay could later lose campaign participation through changed stacking/eligibility while retaining the prior usage slot. Checkout replay now tracks the prior campaign usage record and fails closed when the current unified plan no longer contains a participating campaign promotion. A regression test covers a benefit-only campaign displaced on replay by a higher-priority exclusive automatic promotion. Post-change acceptance is green: Composer strict validation passes; aggregate composer quality passes at 185 tests / 552 assertions with PHPStan clean, PHP-CS-Fixer clean, Playwright 1/1, behavioral evidence regenerated, and Gating 9/9 with zero failures/warnings. Fresh Xdebug coverage is lines 99.66% (1205/1209), methods 80.15% (101/126), and branches 94.51% (878/929). The pre-existing `.gating/` owner-tree contamination remains outside this change set and is not staged.

### CanonScanning root cause

The recurring consumer `.gating/` contamination was traced to the external operational scanner at `D:\PhpstormProjects\www\CanonScanning\bin\canon-scan.ps1`, not to Promoting or the Promoting quality pipeline. Its repository scan path deletes each non-Gating repository's existing `.gating/` directory, mirrors the full canonical `Gating` owner repository into `<repo>/.gating` with `robocopy /MIR`, creates a synthetic local autoloader, and executes that mirrored runtime. The revalidation path also assumes `<repo>/.gating/bin/gating`.

This directly conflicts with Canon052 and the current Gating contract that consumer `.gating/` is artifact-only. The scanner also excludes `.gating/**` from repository fingerprints, so the mutation is masked from its own repository-change detection. The correct external repair is to execute the canonical sibling `Gating/bin/gating` directly against each repository via `--target`, using `Gating/.gating` only as the canonical policy/severity root, in both primary scan and revalidation paths. Promoting does not own CanonScanning, so this run records the blocker but does not patch that external operational component under the Promoting workspace authority.

## 2026-09-28 Inspecting complexity remediation

Baseline: master at `2379073493bebb60548c5da639776d1d71bcc7da`, synchronized with `origin/master`. Pre-existing materialized `.gating/` changes are unrelated/protected and were not cleaned, reset, staged, or absorbed.

Read/verified for this pass: Promoting boundary/README/Composer/gating profile and the RED Inspecting report from `20260928-030002`; Objecting, Cruding, Viewing, Interfacing dependency contour; Canonization Canon000, Canon007, Canon012, Canon018, Canon021, Canon022, Canon052, Canon053; Gating enforcement contour. Target mapping remains `promoting/promotion` -> `App\\Promoting\\` -> `Promotion*`; no generic CRUD ownership was introduced; platform baseline dependencies remain direct symlinked development dependencies.

Selected RC-critical workstream: behavior-preserving decomposition of the eight Inspecting structural findings, prioritizing checkout application complexity 44. Growth work such as richer targeting/segmentation, fraud/velocity controls, omnichannel predicates, and business-authored rule UX remains post-RC.

Implemented in this pass:
- decomposed checkout application orchestration into coupon planning, campaign planning, unified-plan, coupon-effect, campaign-effect, and campaign-usage phases;
- decomposed checkout planning into campaign inclusion, coupon inclusion, and benefit resolution;
- decomposed coupon validation into identity, audience, time-window, and limit checks;
- decomposed coupon application replay/redemption phases;
- decomposed campaign transaction replay/usage phases;
- decomposed campaign constructor invariants into focused validators.

Verification after remediation: PHP syntax lint PASS for all six changed PHP files; `composer validate --strict --check-lock` PASS; PHPUnit 185/185 tests with 552 assertions PASS; PHPStan PASS with zero errors; PHP-CS-Fixer dry-run PASS; Gating PASS with 9 rules, zero failures/warnings/suppressed/skipped; Playwright standalone runtime smoke PASS (1/1); `git diff --check` PASS. Structural self-check shows none of the changed methods at >=60 physical lines or the prior high branch-count envelope. Fresh Inspecting remains unresolved: the long post-mutation invocation exceeded the MCP call window, and a bounded 30-second retry returned `INSPECTING_FAILED` without stdout/stderr. Therefore this remediation is committed as verified code improvement but the Inspecting front is not declared GREEN until a fresh report is obtained for the current repository fingerprint.

## 2026-09-28 Inspecting SRP/cohesion follow-up

Fresh persisted Inspecting report `D--PhpstormProjects-www-Promoting-20260928-125522.json` confirmed that the original eight structural findings were reduced to three medium findings: one large-class finding on `PromotionCheckoutApplicationService` and low-property-cohesion findings on checkout application and plan services. PHPStan inside Inspecting reported zero errors and max cyclomatic complexity dropped from 44 to 14.

Remediation:
- extracted typed checkout candidate selection into `PromotionCheckoutCandidateService` with `PromotionCheckoutCandidateResultDTO`;
- extracted benefit resolution into `PromotionCheckoutBenefitResolutionService`;
- extracted coupon replay/redemption/reversal into `PromotionCheckoutCouponOperationService` and typed planning/effect DTOs;
- extracted campaign replay/usage/spend accounting into `PromotionCheckoutCampaignOperationService` and typed planning/effect DTOs;
- extracted campaign-aware plan finalization into `PromotionCheckoutApplicationPlanService` and `PromotionCheckoutApplicationPlanResultDTO`;
- reduced `PromotionCheckoutPlanService` and `PromotionCheckoutApplicationService` to cohesive orchestration facades while preserving their public interfaces and the existing application-service constructor contract;
- updated Symfony service wiring and unit fixtures without changing promotion ownership boundaries.

Verification after the follow-up: PHPUnit 185/185 tests with 552 assertions PASS; PHPStan PASS with zero errors; PHP-CS-Fixer PASS; Gating PASS with zero failures/warnings; `git diff --check` PASS. The standalone Inspecting capability is currently failing before persisting a new report, so no new Inspecting GREEN claim is made yet. The code is integrated only after deterministic gates are green; Inspecting will be retried against the committed fingerprint.

