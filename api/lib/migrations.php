<?php
/**
 * Automatic database upgrades.
 *
 * Each migration runs once, in order, and the version reached is stored in
 * settings.schema_version. They run automatically on the first API request
 * after new code is uploaded, so the live database upgrades itself.
 * SQL is written for MySQL and converted for SQLite when needed.
 */
defined('APP_LOADED') or exit;

/** Convert MySQL DDL to SQLite when running on SQLite. */
function sql_for_driver(string $sql): string
{
    if (db_driver() !== 'sqlite') {
        return $sql;
    }
    $sql = preg_replace('/^SET NAMES.*$/m', '', $sql);
    $sql = str_replace('INT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = preg_replace('/^\s*INDEX .*$\n/m', '', $sql);
    $sql = preg_replace('/\)\s*ENGINE=[^;]*;/', ');', $sql);
    $sql = str_replace('DEFAULT CURRENT_TIMESTAMP', "DEFAULT (datetime('now','localtime'))", $sql);
    $sql = preg_replace('/,(\s*\n\s*\);)/', '$1', $sql);
    return $sql;
}

function run_sql_script(string $sql): void
{
    $sql = preg_replace('/^--.*$/m', '', sql_for_driver($sql));
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        db()->exec($stmt);
    }
}

const SCHEMA_VERSION = 4;

function run_migrations(): void
{
    $current = (int)setting('schema_version', '1');
    if ($current >= SCHEMA_VERSION) {
        return;
    }
    if ($current < 2) {
        migration_2();
        set_setting('schema_version', '2');
    }
    if ($current < 3) {
        migration_3();
        set_setting('schema_version', '3');
    }
    if ($current < 4) {
        migration_4();
        set_setting('schema_version', '4');
    }
}

/** v4: weight history, digital health cards, enquiries, message log for SMS/email reminders. */
function migration_4(): void
{
    add_column('pets', 'card_token', 'VARCHAR(40) NULL');
    add_column('pets', 'card_show_phone', 'TINYINT(1) NOT NULL DEFAULT 0');
    $t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    run_sql_script("
CREATE TABLE IF NOT EXISTS pet_weights (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pet_id INT UNSIGNED NOT NULL,
  weight_kg DECIMAL(6,2) NOT NULL,
  measured_on DATE NOT NULL,
  note VARCHAR(200) NULL,
  record_id INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pw_pet (pet_id, measured_on),
  FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) $t;
CREATE TABLE IF NOT EXISTS enquiries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(30) NULL,
  pet_id INT UNSIGNED NULL,
  subject VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  INDEX idx_enq_status (status),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE SET NULL
) $t;
CREATE TABLE IF NOT EXISTS enquiry_replies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  enquiry_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_er_enq (enquiry_id),
  FOREIGN KEY (enquiry_id) REFERENCES enquiries(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) $t;
CREATE TABLE IF NOT EXISTS message_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  channel VARCHAR(10) NOT NULL,
  kind VARCHAR(20) NOT NULL,
  to_addr VARCHAR(190) NULL,
  subject VARCHAR(150) NULL,
  body TEXT NULL,
  status VARCHAR(10) NOT NULL,
  provider_ref VARCHAR(100) NULL,
  error VARCHAR(255) NULL,
  ref_key VARCHAR(80) NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ml_created (created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) $t;
");
    // Start each pet's weight history from what is already known.
    foreach (q_all('SELECT id, pet_id, weight_kg, record_date, vet_id FROM health_records WHERE weight_kg IS NOT NULL AND weight_kg > 0') as $r) {
        insert('pet_weights', ['pet_id' => $r['pet_id'], 'weight_kg' => $r['weight_kg'], 'measured_on' => $r['record_date'], 'record_id' => $r['id'], 'created_by' => $r['vet_id'], 'note' => 'From health record', 'created_at' => now()]);
    }
    foreach (q_all('SELECT p.id, p.weight_kg, p.created_at FROM pets p WHERE p.weight_kg IS NOT NULL AND p.weight_kg > 0 AND NOT EXISTS (SELECT 1 FROM pet_weights w WHERE w.pet_id = p.id)') as $p) {
        insert('pet_weights', ['pet_id' => $p['id'], 'weight_kg' => $p['weight_kg'], 'measured_on' => substr($p['created_at'], 0, 10), 'note' => 'Weight when registered', 'created_at' => now()]);
    }
}

function column_exists(string $table, string $column): bool
{
    try {
        db()->query("SELECT $column FROM $table LIMIT 0");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function add_column(string $table, string $column, string $definition): void
{
    if (!column_exists($table, $column)) {
        db()->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }
}

/** v3: staff roles, medical alerts, treatments, check-in, discounts, activity log, record history. */
function migration_3(): void
{
    add_column('pets', 'allergies', 'TEXT NULL');
    add_column('pets', 'conditions', 'TEXT NULL');
    add_column('pets', 'medications', 'TEXT NULL');
    add_column('pets', 'diet', 'TEXT NULL');
    add_column('pets', 'behaviour', 'TEXT NULL');
    add_column('users', 'emergency_name', 'VARCHAR(120) NULL');
    add_column('users', 'emergency_phone', 'VARCHAR(30) NULL');
    add_column('vaccinations', 'kind', "VARCHAR(12) NOT NULL DEFAULT 'vaccine'");
    add_column('services', 'category', "VARCHAR(20) NOT NULL DEFAULT 'consultation'");
    add_column('appointments', 'source', "VARCHAR(10) NOT NULL DEFAULT 'online'");
    add_column('appointments', 'checked_in_at', 'DATETIME NULL');
    add_column('appointment_items', 'discount', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    add_column('products', 'cost_price', 'DECIMAL(10,2) NULL');

    $t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    run_sql_script("
CREATE TABLE IF NOT EXISTS activity_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(40) NOT NULL,
  entity_type VARCHAR(20) NOT NULL,
  entity_id INT UNSIGNED NULL,
  summary VARCHAR(255) NOT NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_al_created (created_at),
  INDEX idx_al_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) $t;
CREATE TABLE IF NOT EXISTS record_versions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  record_id INT UNSIGNED NOT NULL,
  data TEXT NOT NULL,
  changed_by INT UNSIGNED NULL,
  reason VARCHAR(255) NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rv_record (record_id),
  FOREIGN KEY (record_id) REFERENCES health_records(id) ON DELETE CASCADE,
  FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) $t;
");

    // Put existing services into categories by name.
    $map = ['vaccin' => 'vaccination', 'dental' => 'dental', 'groom' => 'grooming', 'surg' => 'surgery', 'emergen' => 'emergency',
            'wellness' => 'checkup', 'package' => 'checkup', 'check' => 'checkup', 'consult' => 'consultation'];
    foreach (q_all('SELECT id, name FROM services') as $s) {
        foreach ($map as $needle => $cat) {
            if (stripos($s['name'], $needle) !== false) {
                q('UPDATE services SET category = ? WHERE id = ?', [$cat, $s['id']]);
                break;
            }
        }
    }
    // The clinic offers grooming: add a starter grooming service if there is none yet (price can be edited).
    if (!q_val("SELECT COUNT(*) FROM services WHERE category = 'grooming'")) {
        insert('services', ['name' => 'Grooming', 'description' => 'Bath, blow-dry, brushing, nail trim and ear cleaning.', 'price' => 2000, 'duration_minutes' => 60, 'category' => 'grooming', 'active' => 1, 'created_at' => now()]);
    }
    // Deworming / flea and tick entries recorded as vaccinations before v3.
    q("UPDATE vaccinations SET kind = 'deworming' WHERE LOWER(vaccine) LIKE '%worm%'");
    q("UPDATE vaccinations SET kind = 'flea_tick' WHERE LOWER(vaccine) LIKE '%flea%' OR LOWER(vaccine) LIKE '%tick%'");
}

/** v2: attachments, vet schedules, inventory, itemised invoices. */
function migration_2(): void
{
    $t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    run_sql_script("
CREATE TABLE IF NOT EXISTS attachments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_type VARCHAR(12) NOT NULL,
  entity_id INT UNSIGNED NOT NULL,
  kind VARCHAR(10) NOT NULL DEFAULT 'document',
  original_name VARCHAR(200) NOT NULL,
  stored_name VARCHAR(120) NOT NULL,
  mime VARCHAR(80) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_att_entity (entity_type, entity_id),
  FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) $t;

CREATE TABLE IF NOT EXISTS vet_schedules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vet_id INT UNSIGNED NOT NULL,
  weekday TINYINT NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  INDEX idx_vs_vet (vet_id),
  FOREIGN KEY (vet_id) REFERENCES users(id) ON DELETE CASCADE
) $t;

CREATE TABLE IF NOT EXISTS vet_time_off (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vet_id INT UNSIGNED NOT NULL,
  date_from DATE NOT NULL,
  date_to DATE NOT NULL,
  reason VARCHAR(200) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_vto_vet (vet_id),
  FOREIGN KEY (vet_id) REFERENCES users(id) ON DELETE CASCADE
) $t;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  category VARCHAR(12) NOT NULL DEFAULT 'medicine',
  unit VARCHAR(30) NOT NULL DEFAULT 'unit',
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  stock_qty DECIMAL(10,2) NOT NULL DEFAULT 0,
  reorder_level DECIMAL(10,2) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) $t;

CREATE TABLE IF NOT EXISTS stock_movements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  change_qty DECIMAL(10,2) NOT NULL,
  reason VARCHAR(20) NOT NULL,
  appointment_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  note VARCHAR(200) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sm_product (product_id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) $t;

CREATE TABLE IF NOT EXISTS appointment_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT UNSIGNED NOT NULL,
  item_type VARCHAR(12) NOT NULL DEFAULT 'service',
  product_id INT UNSIGNED NULL,
  description VARCHAR(200) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ai_appt (appointment_id),
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) $t;
");
    backfill_appointment_items();
}

/** Give every priced appointment without invoice lines a single service line. */
function backfill_appointment_items(): void
{
    $rows = q_all('SELECT a.id, a.price, s.name AS service_name FROM appointments a LEFT JOIN services s ON s.id = a.service_id
                   WHERE a.price > 0 AND NOT EXISTS (SELECT 1 FROM appointment_items i WHERE i.appointment_id = a.id)');
    foreach ($rows as $r) {
        insert('appointment_items', [
            'appointment_id' => (int)$r['id'],
            'item_type' => 'service',
            'description' => $r['service_name'] ?: 'Consultation',
            'quantity' => 1,
            'unit_price' => (float)$r['price'],
            'created_at' => now(),
        ]);
    }
}
