-- Update incremental 1.2.010 -> 1.2.012.
-- Ruleaza pe baza de productie dupa backup. Nu sterge si nu importa date.
-- MariaDB 10.11; comanda este idempotenta si poate fi reluata.

ALTER TABLE `factory_batches`
    ADD COLUMN IF NOT EXISTS `processor_invoice_number` VARCHAR(100) NOT NULL DEFAULT '' AFTER `aviz_date`;
