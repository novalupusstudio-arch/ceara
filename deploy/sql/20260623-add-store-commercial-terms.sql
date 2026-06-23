-- Migrare productie 2026-06-23
-- Muta setarile comerciale pe gestiune pentru procesatorul asignat.
-- Ruleaza dupa backup DB. Nu sterge si nu modifica loturi/documente/stocuri.

ALTER TABLE stores
    ADD COLUMN processing_shrinkage_pct DECIMAL(6,3) NOT NULL DEFAULT 0 AFTER fgo_series,
    ADD COLUMN processing_price_cents INT NOT NULL DEFAULT 0 AFTER processing_shrinkage_pct,
    ADD COLUMN purchase_shrinkage_pct DECIMAL(6,3) NOT NULL DEFAULT 0 AFTER processing_price_cents,
    ADD COLUMN purchase_price_cents_per_kg INT NOT NULL DEFAULT 0 AFTER purchase_shrinkage_pct;

UPDATE stores s
JOIN processors p ON p.id = s.processor_id
SET s.processing_shrinkage_pct = p.exchange_shrinkage_pct,
    s.processing_price_cents = p.processing_price_cents,
    s.purchase_shrinkage_pct = p.purchase_shrinkage_pct
WHERE s.processing_shrinkage_pct = 0
  AND s.processing_price_cents = 0
  AND s.purchase_shrinkage_pct = 0;