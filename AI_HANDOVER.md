# AI Handover

## Current Development Status

The active implemented flow is `Schimb de ceara` / processing. The purchase
flow is intentionally disabled in the navigation and should be rebuilt from
zero later with separate stock rules.

- Dashboard starts with no flow selected and shows only the general menu.
- Selecting `Schimb de ceara` activates processing pages in the sidebar.
- Processing lots are movement-based and use append-only
  `processing_lot_movements`.
- Factory delivery immediately adds calculated foundations to
  `foundation_operational` and records `RECEIVE_FOUNDATION_FROM_FACTORY`.
- Buffer fabrica records plus/minus avize and generates linked NIR documents.
- Registru gestiune is store-scoped and calculated from inventory
  transactions.
- Document links are mock links for now and will later open PDFs from disk.
- The project runs locally under XAMPP and syncs via `scripts/sync-to-xampp.ps1`
  using local `config/xampp-target.local.txt`.

## Completed Decisions

- Use plain PHP plus MySQL instead of a framework-heavy stack.
- Keep the app server-rendered with light client-side behavior.
- Use a seeded initial admin account: `admin / admin`.
- Build role and permission management around admin and operator roles.
- Keep stores tied to users and use that store in operational flows.
- Store quantities in grams internally and display kilograms with three decimals.
- Treat processing documents as mock records for now.
- Keep factory delivery as a batch flow on its own page.
- Do not send lots to factory directly from the lot board.
- Use append-only processing movements and inventory transactions as the source
  of truth.
- Each gestiune is assigned to one processor. Multiple processors for one
  physical location should use separate gestiuni.
- Prevent negative `foundation_operational` on exchange and buffer-minus
  operations.

## Open Questions

- Exact numbering scheme for lots and documents.
- PDF generation/storage path for PV, AVIZ, NIR, FACT, BON.
- Whether batch history needs its own review and print screens.
- Whether partial factory deliveries need a more explicit audit trail per lot.
- Whether password change and user lifecycle need extra safeguards beyond the current MVP.
- Whether document generation should stay fully mocked until the third-party integrations are ready.

## Next Recommended Tasks

1. Define PDF layouts and storage path for PV/NIR/AVIZ documents.
2. Finalize the document and lot numbering rules.
3. Add batch history views for factory deliveries if needed.
4. Tighten permission rules for settings, stores, and processor administration.
5. Rebuild the purchase flow from zero with separate stock rules.

## Latest Refactor Note

Processing lots now use append-only operational movements as the source of truth.
`processing_lot_movements` was added, documents can be linked to `lot_id`,
`movement_id`, and `factory_batch_id`, and `Loturi ceara` is now a calculated
summary table rather than a status board.

Implemented in this pass:

- lot creation creates `RECEIVE_WAX_FROM_CLIENT` and linked `PV-CUST`;
- lot detail page shows calculated balances and movement journal;
- exchange creates `EXCHANGE_WAX_WITH_CLIENT` and leaves `FACT`, `BON`,
  `PV-FAG` for manual generation from the movement row;
- return creates `RETURN_WAX_TO_CLIENT` and a draft/linked `PV-RET`;
- factory delivery creates `SEND_WAX_TO_FACTORY` movements and `AVIZ`;
- old local processing lot data was cleared from the XAMPP database.

Still pending from the new spec:

- factory reception page/action for `RECEIVE_FOUNDATION_FROM_FACTORY` and
  `FACTORY_REJECT_WAX`;
- recovery action for `RECOVER_FOUNDATION_FROM_CLIENT`;
- admin-only loss action for `RECORD_LOSS`;
- stricter fiscal behavior for cancelled/regenerated documents.

## Latest UI / Register Note

Dashboard and sidebar are now flow-aware:

- initial sidebar: Dashboard, Documente, Rapoarte, Setari, Audit;
- active processing sidebar adds Procesare ceara, Loturi ceara, Predare
  fabrica, Buffer fabrica, Registru gestiune;
- Achizitie ceara is visible only as disabled dashboard choice.

Processing register details:

- page: `processing_register`;
- scope: current user's primary gestiune;
- source: `inventory_transactions`;
- document column links to `document_mock` placeholders;
- lot column links to lot detail when available;
- buffer avize generate NIR records automatically.

