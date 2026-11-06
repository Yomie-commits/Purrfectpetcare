<?php
/**
 * Sample ("demo") data: loading it from Settings or the installer, and removing it again.
 *
 * Everything added here is marked as demo so it can be taken out cleanly:
 *  - accounts use emails ending in @demo.local (their pets, appointments, payments,
 *    records, posts and reports are removed with them);
 *  - adoption listings are created by a demo vet;
 *  - stock items are remembered in the setting "demo_product_ids".
 * Real accounts and real data are never touched.
 */

const DEMO_PASSWORD = 'Demo1234';

function new_ref(): string
{
    return 'PPC' . date('ymdHis') . strtoupper(bin2hex(random_bytes(3)));
}

function demo_user_ids(): array
{
    return array_map('intval', array_column(q_all("SELECT id FROM users WHERE email LIKE '%@demo.local'"), 'id'));
}

function demo_product_ids(): array
{
    $raw = setting('demo_product_ids');
    if ($raw === null) {
        // Installs from before v3 did not record demo items: find them by name.
        $names = ['Amoxicillin 250mg', 'Meloxicam 1.5mg/ml oral', 'Deworming tablet (dogs)', 'Rabies vaccine', 'DHPP vaccine', 'Surgical gloves'];
        if (!demo_user_ids()) {
            return [];
        }
        $in = implode(',', array_fill(0, count($names), '?'));
        return array_map('intval', array_column(q_all("SELECT id FROM products WHERE name IN ($in)", $names), 'id'));
    }
    $ids = json_decode((string)$raw, true);
    return is_array($ids) ? array_map('intval', $ids) : [];
}

/** True when the item was added as demo data. */
function is_demo_product(int $id): bool
{
    static $ids = null;
    $ids ??= demo_product_ids();
    return in_array($id, $ids, true);
}

/** Add all sample data. Returns a short summary. */
function seed_all_demo(): array
{
    if (demo_user_ids()) {
        fail('Demo data is already loaded. Remove it first if you want a fresh copy.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $summary = seed_demo();
        backfill_appointment_items();
        $summary['products'] = seed_demo_extras();
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $summary;
}

function seed_demo(): array
{
    $pw = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);
    $d = fn(string $rel) => date('Y-m-d', strtotime($rel));
    $user = fn(array $u) => insert('users', $u + ['password_hash' => $pw, 'status' => 'active', 'created_at' => now()]);

    $vet = $user(['name' => 'Dr. Sarah Wanjiku', 'email' => 'vet@demo.local', 'phone' => '0711000001', 'role' => 'vet', 'specialization' => 'Small animals & dentistry']);
    $vet2 = $user(['name' => 'Dr. Brian Otieno', 'email' => 'vet2@demo.local', 'phone' => '0711000002', 'role' => 'vet', 'specialization' => 'Avian & exotics']);
    $john = $user(['name' => 'John Kamau', 'email' => 'owner@demo.local', 'phone' => '0712345678', 'address' => 'Kilimani, Nairobi', 'role' => 'owner']);
    $amina = $user(['name' => 'Amina Hassan', 'email' => 'amina@demo.local', 'phone' => '0722334455', 'address' => 'Westlands, Nairobi', 'role' => 'owner']);
    $grace = $user(['name' => 'Grace Achieng', 'email' => 'grace@demo.local', 'phone' => '0733445566', 'address' => 'Karen, Nairobi', 'role' => 'owner']);

    $pet = fn(array $p) => insert('pets', $p + ['status' => 'active', 'created_at' => now()]);
    $max = $pet(['owner_id' => $john, 'name' => 'Max', 'species' => 'Dog', 'breed' => 'Golden Retriever', 'sex' => 'Male', 'date_of_birth' => $d('-3 years'), 'weight_kg' => 31.5, 'color' => 'Golden', 'microchip' => '981000000000101']);
    $luna = $pet(['owner_id' => $john, 'name' => 'Luna', 'species' => 'Cat', 'breed' => 'Persian', 'sex' => 'Female', 'date_of_birth' => $d('-2 years'), 'weight_kg' => 4.2, 'color' => 'White']);
    $kiwi = $pet(['owner_id' => $amina, 'name' => 'Kiwi', 'species' => 'Bird', 'breed' => 'African Grey Parrot', 'sex' => 'Unknown', 'date_of_birth' => $d('-1 year'), 'weight_kg' => 0.45]);
    $bella = $pet(['owner_id' => $grace, 'name' => 'Bella', 'species' => 'Dog', 'breed' => 'German Shepherd', 'sex' => 'Female', 'date_of_birth' => $d('-5 years'), 'weight_kg' => 28.0, 'color' => 'Black and tan', 'notes' => 'Nervous with strangers; muzzle for nail trims.']);

    // Use the clinic's own services; fall back to the first service if one was renamed.
    $firstService = q_one('SELECT id, price FROM services ORDER BY id LIMIT 1');
    if (!$firstService) {
        fail('Add at least one service (Services & Prices) before loading demo data.');
    }
    $svc = function (string $n) use ($firstService) {
        $s = q_one('SELECT id, price FROM services WHERE name = ?', [$n]) ?: $firstService;
        return [(int)$s['id'], (float)$s['price']];
    };
    $appt = function (array $a, string $service) use ($svc) {
        [$sid, $price] = $svc($service);
        return insert('appointments', $a + ['service_id' => $sid, 'price' => $price, 'created_at' => now()]);
    };
    $nextDay = in_array(date('w', strtotime('+2 days')), ['0'], true) ? '+3 days' : '+2 days';

    $past = $appt(['owner_id' => $john, 'pet_id' => $max, 'vet_id' => $vet, 'appt_date' => $d('-20 days'), 'appt_time' => '10:00:00', 'reason' => 'Annual check-up', 'status' => 'completed', 'vet_notes' => 'Healthy. Continue current diet.'], 'Wellness Exam');
    $unpaid = $appt(['owner_id' => $john, 'pet_id' => $luna, 'vet_id' => $vet, 'appt_date' => $d('-5 days'), 'appt_time' => '11:30:00', 'reason' => 'FVRCP booster', 'status' => 'completed'], 'Vaccination');
    $bellaPast = $appt(['owner_id' => $grace, 'pet_id' => $bella, 'vet_id' => $vet2, 'appt_date' => $d('-9 days'), 'appt_time' => '15:00:00', 'reason' => 'Limping on the back left leg', 'status' => 'completed', 'vet_notes' => 'Mild sprain. Rest for 7 days.'], 'Consultation');
    $appt(['owner_id' => $grace, 'pet_id' => $bella, 'vet_id' => $vet, 'appt_date' => $d('today'), 'appt_time' => '16:00:00', 'reason' => 'Follow-up on the sprain', 'status' => 'confirmed'], 'Consultation');
    $appt(['owner_id' => $john, 'pet_id' => $max, 'vet_id' => $vet, 'appt_date' => $d($nextDay), 'appt_time' => '09:00:00', 'reason' => 'Tartar build-up', 'status' => 'confirmed'], 'Dental Care');
    $appt(['owner_id' => $amina, 'pet_id' => $kiwi, 'vet_id' => null, 'appt_date' => $d($nextDay), 'appt_time' => '14:00:00', 'reason' => 'Feather plucking', 'status' => 'pending'], 'Consultation');

    $pay = fn(int $userId, int $apptId, float $amount, string $desc, string $when, string $method, ?string $phone = null) => insert('payments', [
        'user_id' => $userId, 'appointment_id' => $apptId, 'amount' => $amount, 'method' => $method, 'status' => 'completed',
        'reference' => new_ref(), 'provider_ref' => $method === 'mpesa' ? 'SIMDEMO' . $apptId : null, 'phone' => $phone,
        'description' => $desc, 'paid_at' => $when, 'created_at' => $when,
    ]);
    $pay($john, $past, $svc('Wellness Exam')[1], 'Wellness Exam - Max', $d('-20 days') . ' 10:45:00', 'mpesa', '254712345678');
    $pay($grace, $bellaPast, $svc('Consultation')[1], 'Consultation - Bella', $d('-9 days') . ' 15:40:00', 'cash');

    $rec = fn(array $r) => insert('health_records', $r + ['created_at' => now()]);
    $rec(['pet_id' => $max, 'vet_id' => $vet, 'appointment_id' => $past, 'record_date' => $d('-20 days'), 'record_type' => 'checkup', 'title' => 'Annual wellness exam', 'diagnosis' => 'Healthy, good body condition (5/9).', 'treatment' => 'None required', 'weight_kg' => 31.5]);
    $rec(['pet_id' => $luna, 'vet_id' => $vet, 'appointment_id' => $unpaid, 'record_date' => $d('-5 days'), 'record_type' => 'vaccination', 'title' => 'FVRCP booster', 'diagnosis' => 'Routine vaccination', 'treatment' => 'FVRCP administered, no reaction']);
    $rec(['pet_id' => $bella, 'vet_id' => $vet2, 'appointment_id' => $bellaPast, 'record_date' => $d('-9 days'), 'record_type' => 'treatment', 'title' => 'Back left leg sprain', 'diagnosis' => 'Soft-tissue sprain, no fracture felt.', 'treatment' => 'Rest, short lead walks only', 'prescription' => 'Meloxicam 1.5mg/ml, 1.9 ml once daily with food for 5 days', 'weight_kg' => 28.0]);

    $vac = fn(array $v) => insert('vaccinations', $v + ['vet_id' => $vet, 'reminder_sent' => 0, 'created_at' => now()]);
    $vac(['pet_id' => $max, 'vaccine' => 'Rabies', 'date_given' => $d('-350 days'), 'next_due' => $d('+15 days'), 'batch_no' => 'RB-2291']);
    $vac(['pet_id' => $max, 'vaccine' => 'DHPP', 'date_given' => $d('-20 days'), 'next_due' => $d('+345 days')]);
    $vac(['pet_id' => $luna, 'vaccine' => 'FVRCP', 'date_given' => $d('-5 days'), 'next_due' => $d('+360 days')]);
    $vac(['pet_id' => $bella, 'vaccine' => 'Rabies', 'date_given' => $d('-380 days'), 'next_due' => $d('-15 days')]);

    insert('forum_posts', ['user_id' => $john, 'title' => 'Tips for brushing a dog\'s teeth?', 'content' => 'Max hates the toothbrush. Any tips that worked for you?', 'status' => 'approved', 'created_at' => now()]);
    insert('forum_posts', ['user_id' => $amina, 'title' => 'Best toys for parrots', 'content' => 'Looking for safe toys to keep Kiwi busy. What do you recommend?', 'status' => 'pending', 'created_at' => now()]);

    insert('adoption_listings', ['name' => 'Simba', 'species' => 'Dog', 'breed' => 'Mixed breed', 'age_text' => '1 year', 'sex' => 'Male', 'description' => 'Friendly and playful, vaccinated and dewormed. Good with children.', 'status' => 'available', 'created_by' => $vet, 'created_at' => now()]);
    insert('adoption_listings', ['name' => 'Mittens', 'species' => 'Cat', 'breed' => 'Domestic shorthair', 'age_text' => '6 months', 'sex' => 'Female', 'description' => 'Calm, litter-trained kitten looking for a quiet home.', 'status' => 'available', 'created_by' => $vet, 'created_at' => now()]);

    insert('lost_found', ['user_id' => $amina, 'report_type' => 'found', 'species' => 'Cat', 'description' => 'Grey tabby with a red collar, very friendly.', 'location' => 'Westlands, near Sarit Centre', 'event_date' => $d('-2 days'), 'contact_phone' => '0722334455', 'status' => 'open', 'created_at' => now()]);

    return ['users' => 5, 'pets' => 4, 'appointments' => 6];
}

/** Demo stock items and vet working hours. Returns how many items were added. */
function seed_demo_extras(): int
{
    $items = [
        ['Amoxicillin 250mg', 'medicine', 'tablet', 50, 200, 50],
        ['Meloxicam 1.5mg/ml oral', 'medicine', 'ml', 30, 100, 20],
        ['Deworming tablet (dogs)', 'medicine', 'tablet', 150, 60, 20],
        ['Rabies vaccine', 'vaccine', 'dose', 800, 25, 10],
        ['DHPP vaccine', 'vaccine', 'dose', 1200, 4, 10],
        ['Surgical gloves', 'supply', 'pair', 40, 300, 100],
    ];
    $ids = demo_product_ids();
    foreach ($items as [$n, $c, $u, $p, $q, $r]) {
        $ids[] = insert('products', ['name' => $n, 'category' => $c, 'unit' => $u, 'price' => $p, 'stock_qty' => $q, 'reorder_level' => $r, 'active' => 1, 'created_at' => now()]);
    }
    set_setting('demo_product_ids', json_encode(array_values(array_unique($ids))));

    $vet = (int)q_val("SELECT id FROM users WHERE email = 'vet@demo.local'");
    $vet2 = (int)q_val("SELECT id FROM users WHERE email = 'vet2@demo.local'");
    foreach ([1, 2, 3, 4, 5] as $d) {
        insert('vet_schedules', ['vet_id' => $vet, 'weekday' => $d, 'start_time' => '08:00:00', 'end_time' => '17:00:00']);
    }
    foreach ([2, 4, 6] as $d) {
        insert('vet_schedules', ['vet_id' => $vet2, 'weekday' => $d, 'start_time' => '10:00:00', 'end_time' => '19:00:00']);
    }
    return count($items);
}

// ---------------------------------------------------------------------------
// API actions (admin only)
// ---------------------------------------------------------------------------

function demo_data_load(): array
{
    require_role('admin');
    $s = seed_all_demo();
    return $s + ['message' => "Demo data loaded: {$s['users']} demo accounts, {$s['pets']} pets, {$s['appointments']} appointments and {$s['products']} stock items. Demo password: " . DEMO_PASSWORD];
}

function demo_data_remove(): array
{
    $me = require_role('admin');
    $ids = demo_user_ids();
    $productIds = demo_product_ids();
    if (!$ids && !$productIds) {
        return ['removed_users' => 0, 'message' => 'There is no demo data to remove.'];
    }
    if (in_array((int)$me['id'], $ids, true)) {
        fail('You are logged in with a demo account. Log in with your own admin account first.');
    }
    $pets = 0;
    $listings = [];
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $listings = array_column(q_all("SELECT id FROM adoption_listings WHERE created_by IN ($in)", $ids), 'id');
        purge_attachments('adoption', $listings);
        if ($listings) {
            q('DELETE FROM adoption_listings WHERE id IN (' . implode(',', array_fill(0, count($listings), '?')) . ')', $listings);
        }
        foreach ($ids as $id) {
            purge_user_files($id);
        }
        $pets = (int)q_val("SELECT COUNT(*) FROM pets WHERE owner_id IN ($in)", $ids);
        q("DELETE FROM users WHERE id IN ($in)", $ids);
    }
    // Demo stock items go too, unless a real invoice has used them (then they are hidden instead).
    $removedProducts = 0;
    foreach ($productIds as $pid) {
        if (q_val('SELECT COUNT(*) FROM appointment_items WHERE product_id = ?', [$pid])) {
            q('UPDATE products SET active = 0 WHERE id = ?', [$pid]);
        } else {
            q('DELETE FROM stock_movements WHERE product_id = ?', [$pid]);
            q('DELETE FROM products WHERE id = ?', [$pid]);
            $removedProducts++;
        }
    }
    set_setting('demo_product_ids', '[]');
    return [
        'removed_users' => count($ids),
        'removed_pets' => $pets,
        'removed_listings' => count($listings),
        'removed_products' => $removedProducts,
        'message' => 'Demo data removed: ' . count($ids) . ' demo accounts, ' . $pets . ' pets, ' . count($listings) . ' adoption listings, ' . $removedProducts . ' stock items, and their appointments, payments and records.',
    ];
}
