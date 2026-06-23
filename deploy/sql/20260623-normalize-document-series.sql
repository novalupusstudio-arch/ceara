-- Migrare productie 2026-06-23
-- Normalizeaza seriile documentelor pe gestiune.
-- Nu reseteaza next_number si nu modifica documentele deja emise.
-- Regula: seria = DOCUMENT_TYPE-COD_GESTIUNE, ex. PV-CUST-BC.

INSERT INTO document_series (store_id, document_type, series, next_number)
SELECT s.id, dt.document_type, CONCAT(dt.document_type, '-', UPPER(s.code)), 1
FROM stores s
JOIN (
    SELECT 'PV-CUST' AS document_type
    UNION ALL SELECT 'PV-FAG'
    UNION ALL SELECT 'PV-RET'
    UNION ALL SELECT 'AVIZ'
    UNION ALL SELECT 'NIR'
    UNION ALL SELECT 'FACT'
    UNION ALL SELECT 'BON'
    UNION ALL SELECT 'BORD'
) dt
LEFT JOIN document_series ds
    ON ds.store_id = s.id AND ds.document_type = dt.document_type
WHERE ds.id IS NULL;

UPDATE document_series ds
JOIN stores s ON s.id = ds.store_id
SET ds.series = CONCAT(ds.document_type, '-', UPPER(s.code))
WHERE ds.document_type IN ('PV-CUST', 'PV-FAG', 'PV-RET', 'AVIZ', 'NIR', 'FACT', 'BON', 'BORD')
  AND ds.series <> CONCAT(ds.document_type, '-', UPPER(s.code));

UPDATE stores
SET fgo_series = CONCAT('FACT-', UPPER(code))
WHERE fgo_series = '';