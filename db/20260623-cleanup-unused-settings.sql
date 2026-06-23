ALTER TABLE company_settings
    DROP COLUMN fgo_private_key,
    DROP COLUMN purchase_default_shrinkage_pct,
    DROP COLUMN purchase_default_price_cents_per_kg,
    DROP COLUMN purchase_factory_shrinkage_pct,
    DROP COLUMN purchase_factory_price_cents_per_kg;

ALTER TABLE processors
    DROP COLUMN contact,
    DROP COLUMN purchase_shrinkage_pct;
