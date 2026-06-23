-- Migrare productie 2026-06-23
-- Adauga seria FGO la nivel de gestiune.
-- Ruleaza o singura data, dupa backup DB, pe baza de productie existenta.
-- Nu sterge si nu modifica loturi, documente, useri, gestiuni sau stocuri.

ALTER TABLE stores
    ADD COLUMN fgo_series VARCHAR(80) NOT NULL DEFAULT '' AFTER address;