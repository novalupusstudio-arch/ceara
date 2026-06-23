# Architecture Decisions

## Finalized Business And Technical Decisions

### 1. Plain PHP + MySQL on XAMPP

Chosen because the app is local-first, Windows-friendly, easy to copy between PCs, and the current workflow is heavily operational rather than framework-driven.

Why this was chosen:

- simplest fit for XAMPP
- easy local deploy into `htdocs`
- low ceremony for a form/table-heavy internal app

Rejected alternatives:

- Laravel or another full framework
- SPA-first frontend

### 2. Production-style seeded admin account

Chosen because the app needs an immediate bootstrap path for settings, permissions, and user creation after a clean reset.

Finalized behavior:

- reset SQL seeds one initial admin user
- current seeded login from `deploy/sql/init-production.sql` is `admin / CearaAdmin!2026`
- source config does not silently invent a local admin unless seeding is explicitly enabled

Rejected alternatives:

- `admin / admin`
- requiring manual SQL user creation before first login
- maintaining a different local bootstrap path than production

### 3. Small role model: `admin` and `operator`

Chosen because the business currently needs simple operational control, not enterprise RBAC complexity.

Finalized behavior:

- `admin` manages users, permissions, stores, processors, templates, reports, audit
- `operator` works inside operational flow with limited rights
- role permissions are editable from settings by admin-capable users

Rejected alternatives:

- no role model, only ad hoc user flags
- large granular RBAC from day one

### 4. One user works on one gestiune

Chosen because processing and stock actions must remain tied to one operational context per user.

Finalized behavior:

- one user has one active gestiune
- one gestiune may have many users
- the app should always use the assigned user gestiune in operational pages

Why:

- predictable stock ownership
- less room for operator mistakes
- simpler register and audit interpretation

Rejected alternatives:

- letting users choose any gestiune at runtime
- mixing multiple active gestiuni per operator workflow

### 5. Quantities stored in grams, presented in kilograms

Chosen because inventory math is safer in integer grams while UI remains readable in kg.

Finalized behavior:

- DB calculations use grams
- UI shows values like `1.234 kg`

Rejected alternatives:

- storing decimal kilograms
- mixed units in persistence and display

### 6. Separate stock buckets for separate ownership models

Chosen because customer custody wax and company-owned wax are fundamentally different assets.

Finalized stock buckets:

- `wax_custody`
- `foundation_operational`
- `wax_purchased`

Rejected alternatives:

- mixing customer and company wax in one stock bucket
- reusing processing delivery flow for purchased wax exits

### 7. Movement-based processing logic is the real operational source of truth

Chosen because lot balances evolve through partial exchanges, returns, factory deliveries, rejections, and possible recovery/loss steps.

Finalized behavior:

- balances are derived from `processing_lot_movements`
- summary logic belongs in services, not views
- persisted legacy lot status fields remain for compatibility/history, but operational UI increasingly depends on movement-derived calculations

Rejected alternatives:

- manually keeping many balance columns updated on the lot row
- putting summary math in PHP templates

### 8. Processing lot values snapshot at creation

Chosen because later processor/store setting edits must not rewrite historical lot economics.

Finalized behavior:

- lot stores its own `processing_price_cents`
- lot stores its own `shrinkage_pct`
- later exchange and service calculations use lot snapshots

Rejected alternatives:

- reading live processor values for old lots
- reading live store defaults for historical lots

### 9. `Acceptat` is terminal on the lot board; factory delivery is separate

Chosen because day-to-day lot handling and batch delivery are different operator tasks.

Finalized behavior:

- lot board is for lot monitoring and client-facing actions
- factory shipment happens from `Predare fabrica`
- multiple lots can be grouped into one factory batch

Rejected alternatives:

- shipping directly from lot board
- a single giant page for all processing actions

### 10. Factory delivery is processor-scoped batch work

Chosen because multiple lots are sent together to one processor and need common documents/totals.

Finalized behavior:

- user selects processor on `Predare fabrica`
- only lots for that processor appear
- each lot can have deliverable and rejected quantity inputs
- factory batch stores totals and item rows

Rejected alternatives:

- no batch header, only isolated lot updates
- mixing lots from multiple processors in one shipment

### 11. Factory delivery requires aviz metadata and generates both `AVIZ` and `NIR`

Chosen because shipment toward factory must have an operator-entered aviz reference, and reception into operational foundation stock needs a linked reception document.

Finalized behavior:

- `factory_batches` stores `aviz_number`
- `factory_batches` stores `aviz_date`
- batch creation auto-issues `AVIZ`
- batch creation auto-issues `NIR`
- processing register should link to those real documents

Rejected alternatives:

- auto-inventing aviz numbers without operator input
- issuing only one of the two documents
- leaving register rows as plain text without document links

### 12. Exchange from buffer does not remove custody wax

Chosen because physically the raw wax remains in custody until it either goes to factory or is returned to the client.

Finalized behavior:

- exchanging foundations to the client records `EXCHANGE_WAX_WITH_CLIENT`
- custody wax remains until `SEND_WAX_TO_FACTORY`, `RETURN_WAX_TO_CLIENT`, or loss
- lot can still have wax in custody after partial client exchange

Rejected alternatives:

- decrementing custody immediately on client exchange
- treating client exchange as equivalent to factory shipment

### 13. Factory rejection creates a warning, but does not fully block further commercial exchange

Chosen because the business may still choose to exchange on loss or return wax depending on the dispute/result.

Finalized behavior:

- rejected-wax lots are visually highlighted in lot detail
- exchangeable wax is not reduced just because factory rejected some previously sent wax
- open rejected wax remains relevant for closure/recovery logic

Rejected alternatives:

- blocking all future exchange after any rejection
- ignoring rejection completely in lot state

### 14. Rejected wax is considered resolved if it was returned to the client or recorded as loss

Chosen because otherwise lots can stay falsely open even after the rejected quantity has been operationally closed.

Finalized behavior:

- open rejected wax = rejected - returned - loss
- lot closure uses that balance, not raw rejected quantity

Rejected alternatives:

- keeping rejected wax open forever until a separate manual close
- subtracting only loss and ignoring customer return

### 15. ANAF lookup is a helper, not the final authority

Chosen because external data is useful for prefill but the operator must control the final saved values.

Finalized behavior:

- PJ lookup may prefill company data
- county/locality matching is best-effort through SIRUTA normalization
- final saved values are the fields present at submit time

Rejected alternatives:

- forcing ANAF data as immutable truth
- requiring exact external locality mapping before save

### 16. Critical configuration should fail loudly, not silently fall back

Chosen because silent fallback hides broken setup and creates wrong documents or stock behavior.

Finalized no-fallback areas:

- DB connection
- assigned store processor where required
- processing price/shrinkage in backend write path
- internal document series
- store FGO series
- FGO URL/token/CUI

Rejected alternatives:

- auto-using first processor/store
- placeholder document numbering
- silently filling missing business values from unrelated config

### 17. `App.php` stays thin; services carry business logic

Chosen because the codebase is already being split and long-term maintenance depends on keeping calculations and writes in focused services.

Finalized direction:

- `App.php` is a facade/orchestrator
- `ProcessingService` handles read/calculation logic
- `ProcessingWriteService` handles write/business actions
- views render prepared data only

Rejected alternatives:

- moving new calculations into views
- re-growing `App.php` into a monolith

## Maintenance Rule

After every meaningful architectural or business-rule change, update:

- `PROJECT_CONTEXT.md`
- `AI_HANDOVER.md`
- `decisions/architecture-decisions.md`
