# PLAN.md — From single-restaurant demo to a multi-restaurant product

Team: 3 engineers + 1 PM. This is a plan for the product, not a description
of the submitted demo.

## 1. Breakdown

**Epic A — Tenancy & access.** Add `restaurant_id` to every business table
plus a membership table; scope every query by it. *AC:* a user authorized
for Restaurant A never reads/writes Restaurant B's data — proven by an
isolation test per endpoint, not convention.

**Epic B — POS reliability at scale.** A durable intake queue in front of
`/sales` (ack immediately, process async): a real POS fires far more
events/minute than the demo's synchronous path assumes. *AC:* a retry storm
never duplicates deductions, never blocks the till.

**Epic C — Inventory reconciliation.** A manager "physical count" workflow
creating signed adjustment movements against any discrepancy, never
rewriting history. *AC:* post-count, recorded stock equals the counted
figure, as its own ledger entry.

**Epic D — Fleet manager workflows.** A cross-restaurant dashboard (stock
health, open orders). *AC:* loads under 2s for 50 restaurants, no
per-restaurant N+1 queries.

## 2. First release

**Scope:** Epic A, plus the existing demo feature set hardened. **Cut**:
Epic B's async queue (ship synchronous `/sales` first), Epic C, Epic D.
Tenancy is the one gap blocking a second restaurant at all; the rest are
value-adds a single pilot doesn't need day one.

**Date:** assuming a Monday kickoff, full 3-engineer capacity, no major
interruptions — tenancy migration + isolation tests + pilot UI tweaks is
~4 weeks → **pilot-ready in 4 calendar weeks**.

**Dependencies:** a committed pilot restaurant (real menu/suppliers), and,
if its POS isn't our simulator, confirmed API/webhook access to it — both
external, needed before week 3's integration testing, not discovered then.

## 3. Risks

- **Trusting recorded vs. physical stock.** Ship Epic C before claiming
  "trustworthy stock" to a second restaurant; until then, tell the pilot
  recorded stock is a model, not a guarantee.
- **Cross-tenant data leakage.** Isolation tests are a release gate, not a
  nice-to-have — every new endpoint requires one before merge.
- **POS integration fragility.** Validate against our own simulator first
  (already proven in the demo); integrate the real POS behind the same
  `/sales` contract so a bad integration can't corrupt the core model.
- **Solo ownership of the tenancy migration.** It touches every table —
  pair on it so a reviewer catches scoping gaps one author misses.

## 4. Squad

Engineer 1 owns tenancy migration + isolation tests; Engineer 2 owns pilot
UI/UX hardening and onboarding; Engineer 3 owns the POS-integration
contract and reconciliation groundwork. PM owns the pilot relationship,
scope cuts, and go/no-go. Every PR needs one reviewer; AI-assisted code is
welcome, but the author must explain every line in review — unexplainable
generated code is unmerged. **Release blockers, no exceptions:** incorrect
stock balances, an unsafe (non-idempotent) replay path, any cross-tenant
access, an untested critical rule, or generated code the owner can't
explain.

## 5. Signals (first 4 weeks post-release)

1. **Physical-count discrepancy** — % variance between recorded and
   counted stock per ingredient per week, from the Epic C count workflow;
   target <2%.
2. **POS processing failures/lag** — error rate and p95 latency on
   `/sales`, from app logs; target zero failed sales, p95 <300ms.
3. **Weekly active restaurants completing receiving** — distinct
   restaurants recording ≥1 delivery per week, from `deliveries` rows
   grouped by restaurant; signals real adoption, not just logins.
