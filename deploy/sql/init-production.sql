CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(160) NOT NULL,
    role ENUM('admin', 'operator') NOT NULL DEFAULT 'operator',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    address VARCHAR(255) NOT NULL DEFAULT '',
    processor_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    code VARCHAR(80) PRIMARY KEY,
    label VARCHAR(160) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_name ENUM('admin', 'operator') NOT NULL,
    permission_code VARCHAR(80) NOT NULL,
    allowed TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (role_name, permission_code),
    FOREIGN KEY (permission_code) REFERENCES permissions(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_stores (
    user_id INT NOT NULL,
    store_id INT NOT NULL,
    PRIMARY KEY (user_id, store_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS processors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    cui VARCHAR(40) NOT NULL DEFAULT '',
    address VARCHAR(255) NOT NULL DEFAULT '',
    contact VARCHAR(160) NOT NULL DEFAULT '',
    processing_price_cents INT NOT NULL DEFAULT 0,
    exchange_shrinkage_pct DECIMAL(6,3) NOT NULL DEFAULT 0,
    purchase_shrinkage_pct DECIMAL(6,3) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_type ENUM('PF', 'PJ') NOT NULL DEFAULT 'PF',
    name VARCHAR(160) NOT NULL,
    phone VARCHAR(80) NOT NULL DEFAULT '',
    address VARCHAR(255) NOT NULL DEFAULT '',
    cui VARCHAR(40) NOT NULL DEFAULT '',
    representative VARCHAR(160) NOT NULL DEFAULT '',
    known_customer TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    supplier_type ENUM('PF', 'Producator agricol', 'PFA/SRL') NOT NULL,
    cui VARCHAR(40) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS processing_lots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lot_number VARCHAR(40) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    status ENUM('In Validare', 'Acceptat', 'Predat Fabricii', 'Respins', 'Returnat') NOT NULL,
    gross_g INT NOT NULL,
    factory_sent_g INT NOT NULL DEFAULT 0,
    processing_price_cents INT NOT NULL DEFAULT 0,
    shrinkage_pct DECIMAL(6,3) NOT NULL DEFAULT 0,
    foundation_g INT NOT NULL,
    store_id INT NOT NULL,
    processor_id INT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (processor_id) REFERENCES processors(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS processing_lot_status_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lot_id INT NOT NULL,
    status ENUM('In Validare', 'Acceptat', 'Predat Fabricii', 'Respins', 'Returnat') NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lot_id) REFERENCES processing_lots(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS processing_lot_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lot_id INT NOT NULL,
    movement_type ENUM(
        'RECEIVE_WAX_FROM_CLIENT',
        'EXCHANGE_WAX_WITH_CLIENT',
        'RETURN_WAX_TO_CLIENT',
        'SEND_WAX_TO_FACTORY',
        'RECEIVE_FOUNDATION_FROM_FACTORY',
        'FACTORY_REJECT_WAX',
        'RECORD_LOSS',
        'RECOVER_FOUNDATION_FROM_CLIENT'
    ) NOT NULL,
    wax_g INT NOT NULL DEFAULT 0,
    foundation_g INT NOT NULL DEFAULT 0,
    service_value_cents INT NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lot_id) REFERENCES processing_lots(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS factory_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_number VARCHAR(40) NOT NULL UNIQUE,
    processor_id INT NOT NULL,
    store_id INT NOT NULL,
    wax_g INT NOT NULL,
    foundation_g INT NOT NULL,
    processing_cost_cents INT NOT NULL DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (processor_id) REFERENCES processors(id),
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS factory_batch_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    processing_lot_id INT NOT NULL,
    wax_g INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES factory_batches(id) ON DELETE CASCADE,
    FOREIGN KEY (processing_lot_id) REFERENCES processing_lots(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS factory_buffer_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    adjustment_type ENUM('plus', 'minus') NOT NULL,
    aviz_number VARCHAR(80) NOT NULL,
    qty_g INT NOT NULL,
    store_id INT NOT NULL,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_lots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lot_number VARCHAR(40) NOT NULL UNIQUE,
    supplier_id INT NOT NULL,
    supplier_type ENUM('PF', 'Producator agricol', 'PFA/SRL') NOT NULL,
    status ENUM('Achizitie', 'Predat Procesator', 'Receptionat Faguri', 'Inchis') NOT NULL,
    gross_g INT NOT NULL,
    shrinkage_pct DECIMAL(6,3) NOT NULL DEFAULT 0,
    foundation_g INT NOT NULL,
    store_id INT NOT NULL,
    processor_id INT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (processor_id) REFERENCES processors(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_series (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    document_type VARCHAR(40) NOT NULL,
    series VARCHAR(80) NOT NULL,
    next_number INT NOT NULL DEFAULT 1,
    UNIQUE KEY unique_store_doc (store_id, document_type),
    FOREIGN KEY (store_id) REFERENCES stores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_type VARCHAR(40) NOT NULL,
    series VARCHAR(80) NOT NULL,
    number INT NOT NULL,
    store_id INT NOT NULL,
    lot_id INT NULL,
    movement_id INT NULL,
    factory_batch_id INT NULL,
    reference_type VARCHAR(60) NOT NULL,
    reference_id INT NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_by INT NULL,
    printed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (lot_id) REFERENCES processing_lots(id) ON DELETE SET NULL,
    FOREIGN KEY (movement_id) REFERENCES processing_lot_movements(id) ON DELETE SET NULL,
    FOREIGN KEY (factory_batch_id) REFERENCES factory_batches(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    body_html MEDIUMTEXT NOT NULL,
    variables_json TEXT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    updated_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    movement_type VARCHAR(80) NOT NULL,
    qty_g INT NOT NULL,
    store_id INT NOT NULL,
    reference_type VARCHAR(60) NOT NULL,
    reference_id INT NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    operation VARCHAR(120) NOT NULL,
    entity VARCHAR(120) NOT NULL,
    entity_id INT NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Production seed: admin user, standard permissions and role defaults only.
START TRANSACTION;

INSERT INTO users (username, password_hash, full_name, role, active)
VALUES ('admin', '$2y$10$IS58HCK3SGmnI0qOkG/uVefZ2Y7GjtoivzJ9lEU.AB2kdgwl8GTAe', 'Administrator', 'admin', 1)
ON DUPLICATE KEY UPDATE username = username;

INSERT INTO permissions (code, label) VALUES
('USER_CREATE', 'Creare utilizatori'),
('USER_EDIT', 'Editare utilizatori'),
('USER_RESET_PASSWORD', 'Resetare parole'),
('STORE_MANAGE', 'Administrare gestiuni'),
('PROCESSOR_MANAGE', 'Administrare procesatori'),
('DOCUMENT_TEMPLATE_MANAGE', 'Administrare template documente'),
('PROCESSING_CREATE', 'Creare procesare'),
('PROCESSING_ACCEPT', 'Acceptare procesare'),
('PROCESSING_REJECT', 'Respingere procesare'),
('PURCHASE_CREATE', 'Creare achizitii'),
('REPORT_VIEW', 'Vizualizare rapoarte'),
('AUDIT_VIEW', 'Vizualizare audit')
ON DUPLICATE KEY UPDATE label = VALUES(label);

INSERT INTO role_permissions (role_name, permission_code, allowed) VALUES
('admin', 'USER_CREATE', 1),
('admin', 'USER_EDIT', 1),
('admin', 'USER_RESET_PASSWORD', 1),
('admin', 'STORE_MANAGE', 1),
('admin', 'PROCESSOR_MANAGE', 1),
('admin', 'DOCUMENT_TEMPLATE_MANAGE', 1),
('admin', 'PROCESSING_CREATE', 1),
('admin', 'PROCESSING_ACCEPT', 1),
('admin', 'PROCESSING_REJECT', 1),
('admin', 'PURCHASE_CREATE', 1),
('admin', 'REPORT_VIEW', 1),
('admin', 'AUDIT_VIEW', 1),
('operator', 'USER_CREATE', 0),
('operator', 'USER_EDIT', 0),
('operator', 'USER_RESET_PASSWORD', 0),
('operator', 'STORE_MANAGE', 0),
('operator', 'PROCESSOR_MANAGE', 0),
('operator', 'DOCUMENT_TEMPLATE_MANAGE', 0),
('operator', 'PROCESSING_CREATE', 1),
('operator', 'PROCESSING_ACCEPT', 0),
('operator', 'PROCESSING_REJECT', 0),
('operator', 'PURCHASE_CREATE', 1),
('operator', 'REPORT_VIEW', 1),
('operator', 'AUDIT_VIEW', 0)
ON DUPLICATE KEY UPDATE allowed = VALUES(allowed);

INSERT INTO document_templates (code, name, description, body_html, variables_json, active) VALUES
('PV-CUST', 'PV primire ceara bruta in custodie', 'Proces-verbal pentru luarea in custodie a cerii brute de la client.', '<h2 style="text-align:center;">
  PROCES-VERBAL DE PREDARE IN CUSTODIE CEARA BRUTA
</h2>

<p style="text-align:center;">
  pentru serviciul de procesare ceara
</p>

<p>
  <strong>Nr.:</strong> [document_number] &nbsp;&nbsp;
  <strong>Data:</strong> [document_date]
</p>

<h3>1. Date prestator</h3>

<p>
  <strong>Societate:</strong> [company_name]<br>
  <strong>CUI:</strong> [company_vat_number]<br>
  <strong>Nr. Reg. Com.:</strong> [company_registry_number]<br>
  <strong>Sediu:</strong> [company_address]<br>
  <strong>Punct de lucru / gestiune:</strong> [store_name] - [store_address]
</p>

<p>
  Reprezentata prin operator / gestionar: <strong>[operator_name]</strong>
</p>

<h3>2. Date client</h3>

<p>
  <strong>Nume / Denumire:</strong> [customer_name]<br>
  <strong>CNP / CUI:</strong> [customer_identifier]<br>
  <strong>Adresa / Localitate:</strong> [customer_address]<br>
  <strong>Telefon:</strong> [customer_phone]<br>
  <strong>Tip client:</strong> [customer_type]
</p>

<h3>3. Date lot</h3>

<p>
  <strong>Lot intern:</strong> [lot_number]<br>
  <strong>Cantitate ceara bruta predata:</strong> [gross_wax_kg] kg<br>
  <strong>Numar bucati / colete:</strong> [package_count]<br>
  <strong>Observatii privind starea cerii:</strong><br>
  [wax_observations]
</p>

<h3>4. Obiectul predarii</h3>

<p>
  Clientul preda societatii cantitatea de ceara bruta mentionata mai sus, in custodie,
  in vederea prestarii serviciului de procesare ceara.
</p>

<p>
  Predarea cerii nu reprezinta vanzare, achizitie, donatie sau transfer de proprietate catre societate.
</p>

<p>
  Ceara bruta ramane in evidenta operationala a societatii ca bun primit in custodie
  pentru executarea serviciului de procesare.
</p>

<h3>5. Conditii de procesare</h3>

<p>Clientul ia la cunostinta ca:</p>

<ul>
  <li>societatea poate efectua schimbul imediat, din stocul operational de faguri, sau poate conditiona predarea fagurilor de verificarea prealabila a cerii;</li>
  <li>cantitatea de faguri rezultata se calculeaza prin aplicarea scazamantului stabilit pentru serviciul de procesare;</li>
  <li>serviciul se poate realiza in sistem de echivalent cantitativ si calitativ, fara obligatia restituirii fizice a exact aceleiasi mase de ceara;</li>
  <li>ceara cu suspiciune de parafina, impuritati excesive, corpuri straine sau alte neconformitati poate fi refuzata;</li>
  <li>ceara refuzata se poate restitui clientului pe baza de proces-verbal de predare ceara neacceptata.</li>
</ul>

<h3>6. Conditii generale</h3>

<p>
  Clientul declara ca a luat cunostinta si accepta
  <strong>Conditiile Generale pentru Serviciul de Procesare Ceara</strong>
  ale societatii, disponibile in punctul de lucru si/sau pe site-ul societatii.
</p>

<h3>7. Confirmare predare</h3>

<p>
  Prin semnarea prezentului proces-verbal, clientul confirma ca a predat cantitatea de ceara bruta
  mentionata mai sus, iar operatorul confirma primirea acesteia in custodie.
</p>

<table style="width:100%; margin-top:40px;">
  <tr>
    <td style="width:50%; text-align:center;">
      <strong>Client / Predator</strong><br><br>
      Nume: [customer_name]<br><br>
      Semnatura: ______________________
    </td>
    <td style="width:50%; text-align:center;">
      <strong>Operator / Gestionar</strong><br><br>
      Nume: [operator_name]<br><br>
      Semnatura: ______________________
    </td>
  </tr>
</table>

<p style="font-size:10px; margin-top:30px;">
  Document generat din aplicatia [app_name] la data [generated_at]. Cod document: [document_number]. Lot: [lot_number].
</p>', '["document_number","document_date","company_name","company_vat_number","company_registry_number","company_address","store_name","store_address","operator_name","customer_name","customer_identifier","customer_address","customer_phone","customer_type","lot_number","gross_wax_kg","package_count","wax_observations","app_name","generated_at"]', 1)
ON DUPLICATE KEY UPDATE code = code;

COMMIT;
