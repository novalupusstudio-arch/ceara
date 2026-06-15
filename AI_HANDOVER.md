# AI Handover

## Current Development Status

The app is at a working MVP stage for the main wax flows.

- Processing lots can be created from the processing screen.
- The lot board shows live status filters and status-specific actions.
- Factory delivery is now handled on a dedicated batch page.
- Lot documents are mocked and generated through the app.
- Settings already cover the core admin and user-management direction.
- The project is designed to run locally under XAMPP and sync to `E:\XAMP\htdocs\ceara`.

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
- Use append-only status tracking for lot events.

## Open Questions

- Exact numbering scheme for lots and documents.
- Whether batch history needs its own review and print screens.
- Whether partial factory deliveries need a more explicit audit trail per lot.
- Whether password change and user lifecycle need extra safeguards beyond the current MVP.
- Whether document generation should stay fully mocked until the third-party integrations are ready.

## Next Recommended Tasks

1. Finalize the document and lot numbering rules.
2. Add batch history views for factory deliveries if needed.
3. Tighten permission rules for settings, stores, and processor administration.
4. Refine the processing and factory delivery print documents.
5. Keep this handover in sync whenever a major architecture decision changes.

