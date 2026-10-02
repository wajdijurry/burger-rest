# Implementation prompt for Claude Sonnet 5

Implement the following project in the current workspace. You are acting as a senior hands-on engineer helping me complete Foodics' Engineering Manager, ERP build challenge. Deliver a working application, meaningful tests, and the required submission documents. Begin with a short execution plan, then implement it; do not stop after proposing an architecture or generating scaffolding.

The application is a **Restaurant Inventory and Purchasing System with recipe-based POS consumption**.

If I attach `Build_Challenge___Engineering_Manager__ERP.pdf`, read all four pages first. Its explicit requirements are authoritative. This prompt supplies implementation choices for points the brief leaves open; label those choices as assumptions in the README. If the attachment is unavailable, the requirements below are sufficient to proceed. Explain any genuine conflict rather than silently dropping a requirement.

## 1. Context, priorities, and scope

One burger restaurant has one branch and buys ingredients from several suppliers. Deliveries can fulfill a purchase order in several parts. Each recorded POS sale consumes ingredients according to a recipe. The manager needs trustworthy current stock and visibility into outstanding supplier quantities.

Foodics expects approximately 7-9 hours of actual work within a 24-48-hour submission window, including documentation. Optimize for a small complete solution. Prioritize correctness, understandability, business-rule tests, and easy local execution.

Required capabilities:

1. Create and list ingredients, including name and unit.
2. Create and list suppliers.
3. Define and view menu items and their ingredient recipes.
4. Create purchase orders with a supplier and one or more ingredient lines.
5. Enforce the order states `draft`, `sent`, `received`, and `closed`.
6. Record full or partial deliveries against purchase orders.
7. Increase stock by exactly what an accepted delivery records.
8. Accept POS sale events containing a menu item and a quantity.
9. Decrease stock by the recipe quantities multiplied by the number sold.
10. Show current stock and open orders with outstanding quantities, with visible freshness.
11. Make all these workflows usable through the web UI, including a simple POS sale simulator that calls the real sale endpoint.

Do not add authentication, deployment, payment, pricing, invoicing, accounting, forecasting, email delivery, supplier integrations, recipe modifiers, or multiple restaurants/branches to this submission. Sending a purchase order is an internal workflow action, not an actual email. Ingredient, supplier, recipe, and order editing/deletion are not required. Avoid adding them unless the core implementation is complete and there is a clear reason.

The future product plan must address multiple restaurants, but the submitted application remains single-restaurant and single-branch.

## 2. Technology and development workflow

Use this stack for a new project:

- PHP and a supported stable Laravel version compatible with the installed runtime.
- Vue 3 with TypeScript and Vite for the UI.
- PostgreSQL for application storage and integration/concurrency tests.
- PHPUnit for backend tests; use the framework's existing testing conventions.
- Simple CSS or an existing lightweight styling solution.

Inspect the workspace, available runtimes, existing repository instructions, and existing dependencies first. Reuse a suitable existing setup. If it conflicts materially with this stack, explain the conflict before replacing it. Do not remove unrelated user work.

Verify framework/runtime compatibility from official documentation, choose stable releases, pin dependency lockfiles, and document the exact versions actually used. Do not assume that the newest version is available or compatible. Do not introduce a second backend framework.

Prefer one repository and one application. Serve the Vue application from Laravel or use a documented Vite development proxy for `/api`; avoid unnecessary cross-origin configuration. Supply a minimal, reliable local PostgreSQL setup through Docker Compose and straightforward application/frontend commands. Use an existing Laravel container setup if available. A full containerized application is optional if it makes execution simpler, not if it delays completion.

Implement incrementally, keeping the application runnable as each business flow is completed. Preserve existing Git history and make meaningful commits as real work is completed. Never manufacture, backdate, or rewrite history to simulate development progress. Do not publish or deploy the repository as part of this task.

## 3. Architecture: a small modular monolith

Organize business code around Catalog/Recipes, Purchasing, and Inventory/Sales using conventional Laravel directories or a few clear subdirectories. Use consistent names and avoid excessive nesting.

Apply these patterns only where they improve the project:

- **Application actions/use cases:** focused classes such as `CreatePurchaseOrder`, `SendPurchaseOrder`, `ReceiveDelivery`, and `RecordSale` own each business operation and its transaction boundary.
- **Thin controllers:** validate/map requests, invoke an action or query, and serialize the response. Do not duplicate business rules in controllers and Vue components.
- **Quantity value object:** centralize exact quantity parsing, validation, arithmetic, comparison, and formatting.
- **Typed order-status enum:** make states explicit and keep transition rules in one place. A separate class for every state is unnecessary.
- **Database unit of work:** commit each accepted delivery or sale as one transaction.
- **Stock movement ledger:** record immutable inventory effects with their source transaction.
- **Focused read queries and API resources:** keep aggregate queries and response serialization understandable.
- **Dependency injection:** use it for real dependencies and useful extension seams; avoid creating an interface for every class.

Use Eloquent and the query builder directly where appropriate. Do not create generic repository wrappers, a custom ORM, a generic workflow engine, a command bus, microservices, Redis, background workers, full event sourcing, or elaborate CQRS infrastructure.

The owner must be able to explain every line during a live interview. Prefer explicit business code over clever abstractions. Comments should explain business decisions or concurrency guarantees rather than narrating syntax.

## 4. Business policies to implement

### Ingredients, recipes, and quantities

- An ingredient has one canonical unit, such as `g`, `ml`, `piece`, or another validated short unit label.
- Purchase, receipt, recipe, and stock quantities for an ingredient always use that same unit. No conversions between grams/kilograms or pieces/boxes are implemented.
- Trim names, reject empty names, and apply sensible length limits. Choose and document a consistent duplicate-name policy; ingredient identity is its database ID, not its display name.
- A recipe belongs to one menu item and has at least one ingredient line.
- Each ingredient may appear only once in a recipe. Reject duplicates rather than silently merging them.
- Each recipe quantity is positive. Fractional ingredient quantities are supported, including fractional `piece` quantities if explicitly entered.
- Menu-item sale quantity is a positive integer, bounded to 10,000 per event.
- Recipe definition is create-only for the initial scope. Do not derive historical stock effects by rereading a current recipe.
- Stock starts at zero. Demo stock must be established through legitimate receiving operations, not unexplained balance changes.

Use exact decimal arithmetic:

- Store ingredient quantities and movement quantities as PostgreSQL `NUMERIC(18,3)`.
- Accept ingredient quantities as decimal strings, for example `"150.000"` or `"0.125"`, and return them as strings.
- Use BCMath or an equally small, verified exact-decimal solution in PHP. Include the required extension/dependency in local setup.
- Implement a small immutable `Quantity` abstraction with parsing, addition, subtraction, multiplication by a positive integer, comparison, and formatting.
- Reject scientific notation, non-finite values, more than three decimal places, and invalid/negative/zero quantities where a positive quantity is required. Do not silently round excessive precision.
- Bound user-entered ingredient quantities to 1,000,000 canonical units per line. Check arithmetic/storage limits before writes; return a domain validation error rather than overflowing or partially committing.
- Never cast business quantities to PHP floats or JavaScript numbers for inventory arithmetic. Vue can send input strings and display formatted strings. Sale counts can remain bounded integers.

### Purchase orders and state transitions

A purchase order has one supplier and one or more distinct ingredient lines. Each line has a positive ordered quantity. Duplicate ingredient lines are rejected. New orders always start as `draft`; the client cannot choose an initial status.

Use these meanings:

| State | Meaning |
| --- | --- |
| `draft` | Created, not sent, no receipts allowed |
| `sent` | Sent to supplier, no accepted receipt yet |
| `received` | At least one delivery accepted, but some lines remain outstanding |
| `closed` | Every line has been fully received |

Allowed transitions:

- `draft -> sent`: explicit send action.
- `sent -> received`: first accepted partial delivery.
- `received -> received`: another delivery while quantities remain outstanding.
- `received -> closed`: a delivery fulfills the final outstanding quantities.
- A full first delivery performs the logical `sent -> received -> closed` progression atomically and returns the final `closed` state; an intermediate transaction is unnecessary.

Sending an already `sent` order may return its existing representation without changes. Sending a `received` or `closed` order is rejected as an invalid action. Do not permit backward transitions, receiving a draft, or manually closing an incomplete order.

Supplier and ordered quantities remain fixed after sending. No arbitrary status-update endpoint. Closed orders remain visible in history but are excluded from open orders. Display drafts among non-closed orders with a clear 'not yet sent' label.

### Receiving deliveries

- A delivery belongs to exactly one purchase order and contains one or more receipt lines.
- A receipt line references a purchase-order line ID, not merely an ingredient ID.
- Validate that every referenced line belongs to the target order.
- A delivery may cover only some lines and some of their outstanding quantities.
- Reject duplicate line IDs, empty deliveries, non-positive quantities, and quantities above the current outstanding amount.
- Over-receiving is rejected for the whole delivery. Never silently clamp the quantity or discard the excess.
- Cumulative received quantity comes from accepted receipt lines.
- Outstanding per line equals ordered quantity minus cumulative received quantity.
- Inventory rises by the exact accepted receipt quantities, independent of what was ordered.
- An order closes only when every line's outstanding quantity is exactly zero.
- A valid retry of a previously accepted delivery must succeed even if that delivery already closed the order; recognize the retry before rejecting the current order state.

### POS sales and negative stock

- A sale event identifies one menu item, a positive integer quantity, and a stable external `event_id`.
- Validate the menu item and its non-empty recipe before creating any inventory effects.
- Record the sale and all ingredient deductions in one transaction.
- Consumption per ingredient equals recipe quantity multiplied by sale count.
- Accept sales even when the resulting recorded stock is negative. The endpoint represents a completed POS sale, so recording its consumption preserves reality and exposes discrepancies.
- Show negative stock prominently; never clamp it to zero or silently omit an ingredient deduction.
- Document this policy and its assumption. Keep the decision in an obvious location so a future pre-sale availability policy can be introduced deliberately.
- Negative sale counts are not a refund mechanism. Refunds, voids, and reversal policies are future work.
- Persist the actual deduction amounts in stock movements so previous sales are unaffected by future recipe editing.

### Purchasing versus inventory

Stock and purchasing fulfillment are independent:

`stock(ingredient) = sum(all signed stock movements for that ingredient)`

`outstanding(order line) = ordered quantity - sum(accepted receipts for that line)`

Sales never change purchase-order outstanding quantities. Completion must be checked per line; never sum unrelated units to determine whether an order is complete.

## 5. Data model and database integrity

Use a small relational model with these concepts:

- `ingredients`
- `suppliers`
- `menu_items`
- `recipe_lines`
- `purchase_orders`
- `purchase_order_lines`
- `deliveries`
- `delivery_lines`
- `sales`
- `stock_movements`

Include ordinary IDs and UTC timestamps. Keep the schema readable. Use foreign keys with restrictive deletion behavior for business history, positive-quantity check constraints, status/sign constraints where appropriate, and uniqueness constraints for duplicate recipe/order/delivery lines.

Prefer deriving cumulative receipts and current stock from their source records. Do not add mutable `received_quantity` or `stock_balance` columns unless you have a justified need and can prove their transactional consistency.

Each movement records an ingredient, a signed quantity, a movement type, and an unambiguous source. For this small scope, explicit nullable `delivery_line_id` and `sale_id` foreign keys with a check requiring exactly one source are a reasonable design. Receipt movements must be positive and sale movements negative. Enforce one movement per delivery line and one sale movement per ingredient per sale. An equally simple design is acceptable if it preserves actual database referential integrity and equivalent uniqueness; avoid unconstrained polymorphic source references.

Derive a receipt movement's ingredient from its referenced purchase-order line, and a sale movement's ingredient from the accepted recipe. Never accept client-supplied stock movement amounts, ingredient substitutions, or movement sources as authoritative.

Stock movements and accepted receipts/sales are immutable through application code. Do not expose edit/delete endpoints for them. Future corrections should append explicitly linked reversal/adjustment records rather than rewriting historical effects.

Use indexes for foreign-key joins, stock aggregation by ingredient, order status filtering, and idempotency lookup. Avoid N+1 queries. Database migrations must contain the constraints that matter, not rely entirely on request validation.

Return ingredients with zero stock too, using an appropriate left join/aggregation. Avoid duplicated aggregate quantities caused by joining several one-to-many relations at once; aggregate receipts and movements separately before combining read results.

## 6. Transactions, concurrency, and idempotency

These are mandatory correctness requirements for this implementation.

### Transaction boundaries

The following must commit together or not at all:

- Creating an order and all its lines.
- Creating a menu item and all its recipe lines.
- Receiving a delivery: delivery header, receipt lines, stock movements, and resulting order status.
- Recording a sale: sale/event identity, actual ingredient deductions, and all stock movements.

Validation failures and exceptions must leave all affected records unchanged. Do not hide stock updates in asynchronous jobs or model observers.

### Receiving concurrency

Within a transaction, lock the purchase-order row before reading its state or cumulative receipts for a new delivery. All receiving and sending paths must follow the same parent-lock discipline. Re-read the relevant data after obtaining the lock and calculate outstanding quantities there. If multiple rows need locks, use a deterministic acquisition order.

A transaction alone does not protect a check-then-write rule without appropriate concurrency control. Use PostgreSQL behavior and Laravel's `lockForUpdate()` correctly. Keep transactions short and bounded; use limited deadlock retries only where safe.

Acceptance case: with 6 units outstanding, two distinct concurrent deliveries each request 4. Exactly one may succeed. The other returns a domain conflict, and total accepted receiving must be 4, not 8.

### Idempotent sales

- Require a UUID `event_id`, unique in the `sales` table.
- Same ID and equivalent payload: return the previously accepted operation without new movements.
- Same ID and different payload: return `409 IDEMPOTENCY_CONFLICT`.
- Concurrent duplicate requests: one committed sale and one set of deductions.
- Persist request identity and its inventory effects atomically.
- Use a database uniqueness constraint and a PostgreSQL-safe race-resolution approach. A preliminary 'does this exist?' query is insufficient.
- After a uniqueness race, resolve the committed existing result from a usable transaction/connection. Do not continue querying inside an aborted PostgreSQL transaction.

### Idempotent deliveries

- Require an `Idempotency-Key` UUID header for receiving.
- Scope it to the purchase order and enforce unique `(purchase_order_id, idempotency_key)` in the database.
- Same key and equivalent receipt payload: return the original accepted operation.
- Same key and different payload: return `409 IDEMPOTENCY_CONFLICT`.
- Check for an accepted replay before applying state/remaining-quantity rules for a new delivery. Replaying the final delivery on a now-closed order must work.

Canonicalize semantic payloads before comparison/hash storage: normalize decimal strings and sort delivery lines by their stable line IDs. For example, `"1"` and `"1.000"` are equivalent quantities; reordering the same delivery lines is not a different operation. Sale identity comparison includes menu-item ID and count, not today's recipe contents.

Initially return `201` for creation and `200` for an accepted replay, identifying the original sale/delivery and its original effects. Do not report an old result as a current inventory snapshot. The UI obtains current inventory through fresh read endpoints. Failed transactions do not leave a permanently consumed idempotency key. Retrying after a lost response must be safe.

The delivery and POS simulator UI must preserve a submission's key/event ID across retries until its outcome is resolved. Editing the payload for a new attempt or intentionally recording a second sale creates a new identity. Do not generate a new key on every click or network retry.

With ledger-derived balances and the chosen negative-stock policy, independent legitimate sales should append movements without a mutable balance read-modify-write race. Do not introduce unnecessary ingredient locks to solve a race the model avoids.

## 7. API contract

Use JSON under `/api/v1`. Suggested routes:

| Method | Route | Purpose |
| --- | --- | --- |
| GET / POST | `/ingredients` | List/create ingredients |
| GET / POST | `/suppliers` | List/create suppliers |
| GET / POST | `/menu-items` | List/create items with their recipes |
| GET / POST | `/purchase-orders` | List/create orders, with documented status filtering |
| GET | `/purchase-orders/{id}` | Lines, cumulative receiving, outstanding quantities, and receipt history |
| POST | `/purchase-orders/{id}/send` | Controlled send action |
| POST | `/purchase-orders/{id}/deliveries` | Idempotent receiving |
| POST | `/sales` | Idempotent POS event ingestion |
| GET | `/stock` | Every ingredient and its current balance |
| GET | `/dashboard` | Consistent stock/open-order snapshot and generation timestamp |

Define concrete request/response examples in the README. A sale request can be:

```json
{
  "event_id": "be446e18-a222-4baa-8d34-2b859fd7f375",
  "menu_item_id": 1,
  "quantity": 3
}
```

A delivery request uses the `Idempotency-Key` header and:

```json
{
  "lines": [
    { "purchase_order_line_id": 10, "quantity": "4000.000" },
    { "purchase_order_line_id": 11, "quantity": "20.000" }
  ]
}
```

Use consistent error responses with a stable machine-readable code, an actionable message, and optional field errors. Distinguish malformed input (`422`), missing resources (`404`), and state/fulfillment/idempotency conflicts (`409`). Never expose stack traces as normal API errors. Validate source ownership server-side and accept only explicitly allowed writable fields.

For dashboard reads involving multiple queries, use a single database snapshot, for example a short read-only repeatable-read transaction configured before its first data query, or an equally clear single-statement solution. Do not assume that several ordinary reads automatically share one snapshot. Document the chosen approach without implying that a returned timestamp guarantees continuous real-time freshness.

## 8. UI requirements and freshness

Build a plain, responsive, usable interface with:

- Dashboard: current stock with units, negative-stock warnings, and non-closed orders.
- Ingredients and suppliers: create forms and lists.
- Menu items: a recipe form with repeatable ingredient rows and a recipe list.
- Purchase orders: supplier selection, repeatable ingredient rows, list/detail view, and send action.
- Receiving: show ordered, received, and outstanding quantities per line; allow receiving a subset of lines.
- POS simulator: choose a menu item and sale count, then call the actual sale API.

Use labels, keyboard-accessible controls, visible loading states, field errors, empty states, and clear success/failure feedback. Show units beside every quantity. Disable duplicate submission while a request is pending, but rely on server idempotency for correctness. Preserve user-entered form data after recoverable failures.

Implement freshness without adding push infrastructure:

- Fetch dashboard data on initial load.
- Refresh immediately after accepted deliveries and sales, including accepted replays.
- Poll about every 5 seconds while the dashboard is visible.
- Refresh when the browser tab becomes active again.
- Avoid overlapping polling requests and dispose timers/listeners when components unmount.
- Prevent outdated in-flight responses from overwriting newer data; invalidate/cancel pre-mutation requests when appropriate.
- Show last successful refresh time, plus loading/refresh-failure/stale indicators. Do not advance the timestamp after a failed request.
- Retain last known data on a refresh failure while clearly labeling its freshness.

The UI must call the API for business operations. Do not implement a separate stock calculator or independently authoritative workflow rules in JavaScript. Client validation is convenience; backend validation is authoritative.

## 9. Tests and executable acceptance criteria

Write meaningful tests for domain rules and database-backed business behavior. Use the real PostgreSQL engine for integration and concurrency guarantees; do not substitute SQLite and claim the same locking behavior.

Essential coverage:

1. Create/list ingredients and suppliers, rejecting invalid input.
2. Create a valid recipe; reject empty recipes, unknown ingredients, duplicate lines, invalid precision, and non-positive quantities.
3. Create a draft purchase order; reject missing suppliers, empty/duplicate lines, and invalid quantities.
4. Sending preserves stock; prohibited state actions fail.
5. First partial receiving produces `received`, correct stock, and correct outstanding quantities.
6. Successive receipts accumulate correctly.
7. One fully received line does not close a multi-line order with another line outstanding.
8. Final receiving closes the order; a full first delivery also closes it.
9. Draft/closed receiving, over-receiving, and cross-order line references fail without changes.
10. A multi-line delivery with an invalid line leaves no partial effects.
11. Sale consumption is correct across every recipe ingredient.
12. Exact-zero and below-zero stock follow the documented policy.
13. Sales do not change purchase-order fulfillment.
14. Shared ingredients across recipes/suppliers contribute to the same stock calculation.
15. Duplicate sale/delivery requests have exactly one effect; conflicting payload reuse is rejected.
16. Final-delivery replay succeeds after automatic closure.
17. Decimal canonicalization and reordered equivalent receipt payloads replay correctly.
18. A controlled failure during a sale or delivery rolls back every business write. If fault injection is needed, use a narrow existing seam or test-only mechanism, not a production fault endpoint or an elaborate interface hierarchy.
19. Real concurrent distinct receipts cannot over-receive.
20. Real concurrent duplicate sale/delivery requests produce one operation, not server errors or duplicate movements.
21. Concurrent distinct sales preserve the total sum of all accepted consumption.
22. Stock listing includes ingredients with zero movements.

Concurrency tests must use independent database connections/processes and committed fixtures. Avoid wrapping setup in an outer test transaction invisible to worker connections. Use bounded coordination/timeouts rather than fragile long sleeps, clean up test fixtures, and ensure workers cannot hang the suite.

Add one focused browser smoke test for the complete manager workflow if browser tooling is available without disproportionate setup. Otherwise run and document the manual workflow below; never claim an automated UI test was executed when it was not. Verify polling/failure feedback through a focused test or documented browser check.

Use this deterministic acceptance example:

- Recipe: Classic Burger uses `150.000 g` beef, `1.000 piece` bun, and `20.000 g` cheese.
- Order: `10000.000 g` beef, `40.000` buns, and `800.000 g` cheese.
- Send: stock remains zero.
- First delivery: `4000.000 g` beef, `20.000` buns, `400.000 g` cheese.
- Order becomes `received`; outstanding is `6000.000`, `20.000`, `400.000` respectively.
- Sell 3 burgers: stock becomes `3550.000 g` beef, `17.000` buns, `340.000 g` cheese. Outstanding quantities do not change.
- Retry the same sale event: all values remain unchanged.
- Receive the remainder: order becomes `closed`; stock becomes `9550.000 g` beef, `37.000` buns, `740.000 g` cheese.
- Retry that final delivery: values remain unchanged, and replay succeeds.

Provide deterministic seed data for a quick demo, using the same business actions where practical. Clearly document any reset command as destructive to local demo data; ordinary startup must not wipe persisted data.

Run the actual backend suite, TypeScript checking, frontend production build, and relevant formatting checks. Report what ran and its result. Resolve failures before finishing. If an environmental dependency genuinely blocks a check, state the precise limitation; do not replace evidence with 'should pass'.

## 10. Required documentation

### README.md

Write concise, accurate documentation covering:

- Purpose and implemented scope.
- Exact prerequisites and versions used.
- Copyable installation, environment, database, migration, seed, run, build, and test commands verified against this repository.
- Local application URL and a short demo walkthrough.
- Small data-model description or compact Mermaid diagram.
- API examples, quantity representation, error codes, and idempotency contracts.
- Chosen order-state interpretation, negative stock, over-receiving, precision, initial stock, open-order definition, and other material assumptions.
- Transactions, locking, source of truth, and freshness behavior in plain language.
- Actual AI usage: main prompts, useful output, specific mistakes discovered, how they were corrected, and verification performed. This implementation prompt is one of the main prompts.
- Honest limitations and the most valuable next steps.

Maintain the AI record during implementation. Do not invent errors, prompts, manual reviews, tests, working time, or stakeholder decisions. If no substantive AI mistake was observed, say so. Do not claim compliance with a work-hour budget without knowing the actual time spent.

### PLAN.md

Keep it to approximately one printed page: aim for around 450-550 words with compact formatting. This is a plan for turning the demo into a product for many restaurants with **three engineers and one product manager**, not a description of the completed demo.

Cover all five requirements:

1. **Breakdown:** a small set of epics and a few implementable stories with acceptance criteria. Include tenant isolation/access, POS reliability, inventory reconciliation, and manager workflows where appropriate.
2. **First release:** opinionated pilot scope, explicit cuts with reasons, a concrete calendar release date, and dependencies. Derive the date from a stated kickoff/capacity assumption, not an invented stakeholder commitment. Identify external POS access and pilot-restaurant availability as dependencies if the plan relies on them.
3. **Risks:** main technical, delivery, and product risks and specific mitigations. Include trusting recorded versus physical stock and ensuring tenant data cannot leak.
4. **Squad:** a practical split across three engineers, PM responsibilities, collaboration/review practices, and how the team uses AI. State what blocks release: incorrect balances, unsafe replay, cross-tenant access, untested critical rules, and generated code the owner cannot explain.
5. **Signals:** two or three concrete post-release measures combining stock trust and actual use, such as physical-count discrepancy, POS processing failures/lag, and weekly active restaurants completing receiving workflows. Define how each would be measured.

Do not implement future-product infrastructure merely because it appears in the plan.

## 11. Extension readiness and interview preparation

Keep the current behavior straightforward while making these future changes local and understandable:

- Stock adjustment/wastage: a new action, explicit reason/source, and append-only signed movements; existing sale/receipt history remains intact.
- Refunds/voids: linked reversal records with an explicit policy, not negative sale counts or deletion.
- Recipe editing: versioning or consumption snapshots preserve historical effects; current movements already capture actual deductions.
- Purchase-order cancellation: new transition rules and a decision about unreceived versus already received quantities.
- Unit conversion: a defined quantity/conversion boundary, without mixing unit interpretation into every controller.
- Multiple restaurants/branches: a deliberate tenant/location migration and access/query scoping, with isolation tests. Current single-branch data is not production-ready tenancy.
- Push updates or cached balances: replace/read-optimize the visibility layer while preserving transactional source records.

Do not build these features, unused interfaces, or placeholder frameworks now. Briefly explain the likely change locations in the README. The goal is a solution that can be extended in a 35-minute live session and whose tradeoffs I can defend.

Before finishing, review your own diff as an engineering manager. Block unprotected races, duplicate effects, partial transactions, float arithmetic, invalid transitions, historical recomputation, cross-order references, stale UI presented as current, and misleading documentation. Separate these from non-blocking style preferences.

## 12. Final completion report

Finish with a short report containing:

- What works end to end and how to run the demo.
- Tests/checks actually executed and their results.
- The most important business assumptions.
- Any remaining genuine limitations or blockers.
- A short explanation of the main actions, transaction boundaries, and why this architecture is sufficient.
- Locations of README.md and PLAN.md.

Deliver working code, not pseudocode or TODO-filled core workflows. If time becomes constrained, simplify styling and optional tooling before compromising stock correctness or omitting required workflows.

Use official documentation for version-specific APIs. Useful starting points, selecting the documentation version matching the installed project:

- [Laravel database transactions](https://laravel.com/docs/13.x/database#database-transactions)
- [Laravel pessimistic locking](https://laravel.com/docs/13.x/queries#pessimistic-locking)
- [PostgreSQL transaction isolation](https://www.postgresql.org/docs/current/transaction-iso.html)

Start by inspecting the workspace and reading the brief, state your short plan, then implement and verify the complete solution.
