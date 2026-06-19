# Architecture Decisions

## Finalized Decisions

### 1. Plain PHP + MySQL on XAMPP

- Chosen because the app needs to stay simple, local-first, and easy to move between Windows machines.
- This fits the current workflow of editing in `E:\NovaLupus\ceara` and syncing into XAMPP.

Rejected alternatives:

- Laravel or another framework, because it would add structure that is not needed for the current MVP.
- A SPA-first frontend, because the app is dominated by form-based business flows and server-rendered tables.

### 2. Seeded initial admin account

- Chosen because the project needs an immediate bootstrap path for permissions and user setup.
- The initial admin is `admin / admin`.

Rejected alternatives:

- Requiring manual database user setup before the app can be used.
- A separate invite-only onboarding flow, which would slow down first deployment.

### 3. Role-based access with admin and operator

- Chosen because the business rules need a small permission model that can be expanded later.
- Admin controls users, permissions, stores, processors, and reporting.
- Operator works inside assigned operational limits.

Rejected alternatives:

- One global permission list without roles, which would become harder to manage.
- A much more granular enterprise RBAC model, which would be overkill for the current scope.

### 4. Store assignment is tied to the user

- Chosen because operational work must stay scoped to a specific gestiune.
- This keeps processing and stock movement predictable.

Rejected alternatives:

- Letting users pick any store at runtime, which would weaken traceability.
- A shared pool of stores for all users, which would blur responsibility.

### 5. Quantities stored in grams, displayed in kilograms

- Chosen because stock math is cleaner and safer in integer grams.
- The UI still presents readable values such as `1.234 kg`.

Rejected alternatives:

- Storing kilograms as decimals, which would create rounding risk.
- Mixing presentation units with storage units, which would complicate inventory math.

### 6. DB document records plus generated PDFs

- Chosen because document numbering and third-party integrations are still being defined, but PDF output is already needed for local testing.
- Document rows remain in DB as the durable reference.
- `PV-CUST` is generated from an editable HTML template through Dompdf.
- Generated files are saved under `storage/documents/<store_code>/` and opened inline from the document endpoint.
- Other document types may still be placeholder records until their templates are defined.

Rejected alternatives:

- Integrating real third-party document services immediately.
- Deferring all document rendering until production readiness.
- Relying on Composer at install time. `vendor/` is intentionally committed so PC2/server deployments are self-contained.

### 7. Processing and factory delivery are separate flows

- Chosen because the user flow is clearer when lot creation, validation, and batch delivery are separated.
- The lot board stays focused on lot status, while the factory page handles batching.

Rejected alternatives:

- Sending lots to factory directly from the lot board.
- Merging processing creation and factory batch preparation into one large screen.

### 8. Processing lots start in validation

- Chosen because the flow needs a controlled first state before acceptance.
- This matches the current operational rule set.

Rejected alternatives:

- Starting lots directly as accepted.
- Letting users skip validation.

### 9. `Acceptat` is terminal on the lot board

- Chosen because the next operational step is handled in batch on `Predare fabrica`.
- This keeps the lot board from doing too much.

Rejected alternatives:

- Allowing a direct `Predat Fabricii` action from the lot board.
- Keeping all status transitions on one screen.

### 10. Batch delivery creates factory batch records

- Chosen because multiple lots can be sent together to one processor.
- The batch model supports partial quantities and later history review.

Rejected alternatives:

- Updating lots independently without a batch header.
- Treating each lot shipment as a separate unrelated transaction.

### 11. App state is tracked with status events plus batch data

- Chosen because sensitive business actions need traceability.
- The status-event approach supports later audit and reporting features.

Rejected alternatives:

- Storing only the current lot status with no history.
- Keeping batch actions only in UI state without persistence.

### 12. Local sync to XAMPP is the development deployment path

- Chosen because it supports working on one machine and continuing on another with the same folder structure.
- The app can be copied into `E:\XAMP\htdocs\ceara` for immediate local use.

Rejected alternatives:

- Running only from the source folder without a deploy step.
- Introducing a more complex deployment pipeline before the app stabilizes.

## Maintenance Rule

Whenever a major architectural or business decision is finalized, update:

- `PROJECT_CONTEXT.md`
- `AI_HANDOVER.md`
- this file


### 13. Purchase flow uses separate company-owned stock

- Chosen because purchased wax is owned by the company and must not mix with customer custody wax.
- Purchase entries write positive `wax_purchased` inventory movements.
- Purchase exits write negative `wax_purchased` inventory movements.
- Purchase factory/partner exits are not handled by processing `Predare fabrica`; they use the separate `Iesire ceara` page.
- Purchase documents are external references only for now, not generated PDFs.

Rejected alternatives:

- Reusing `wax_custody` or processing factory delivery for purchased wax.
- Generating internal borderou/factura/NIR documents for purchase entries before the accounting flow is finalized.
- Allocating exits to exact purchase lots immediately; current MVP uses stock-level exits.
