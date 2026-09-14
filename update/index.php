<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
Auth::requireLogin();

if (!Auth::isAdmin()) {
    http_response_code(403);
    exit('Nur Administratoren dürfen Systemupdates ausführen.');
}

$pdo = db();
$messages = [];
$error = null;

function update_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function update_constraint_exists(PDO $pdo, string $name): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME=?');
    $stmt->execute([$name]);
    return (int) $stmt->fetchColumn() > 0;
}

function update_migration_applied(PDO $pdo, string $key): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration_key=?');
    $stmt->execute([$key]);
    return (int) $stmt->fetchColumn() > 0;
}

function update_record_migration(PDO $pdo, string $key): void
{
    $stmt = $pdo->prepare('INSERT IGNORE INTO schema_migrations (migration_key) VALUES (?)');
    $stmt->execute([$key]);
}

function update_create_tables(PDO $pdo, string $file): void
{
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('Migrationsdatei konnte nicht gelesen werden: ' . basename($file));
    }
    preg_match_all('/CREATE TABLE\s+([a-z_]+)\s*\(.*?\) ENGINE=InnoDB.*?;/si', $sql, $matches);
    foreach ($matches[0] as $createSql) {
        $createSql = preg_replace('/CREATE TABLE\s+/i', 'CREATE TABLE IF NOT EXISTS ', $createSql, 1);
        $pdo->exec((string) $createSql);
    }
}

function update_run_sql_file(PDO $pdo, string $file): void
{
    $sql = file_get_contents($file);
    if ($sql === false) throw new RuntimeException('Migrationsdatei konnte nicht gelesen werden: ' . basename($file));
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        try { $pdo->exec($statement); }
        catch (PDOException $exception) {
            // 1060/1061: Spalte/Index schon vorhanden – wichtig nach abgebrochenen Updates.
            if (!in_array((int)($exception->errorInfo[1] ?? 0), [1050,1060,1061], true)) throw $exception;
        }
    }
}

$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
    migration_key VARCHAR(100) PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$key002 = '002-member-records-events-responses';
$key003 = '003-member-documents';
$key004 = '004-saas-platform';
$key005 = '005-saas-operations';
$key006 = '006-tenant-management';
$has002 = update_migration_applied($pdo, $key002);
$has003 = update_migration_applied($pdo, $key003);
$has004 = update_migration_applied($pdo, $key004);
$has005 = update_migration_applied($pdo, $key005);
$has006 = update_migration_applied($pdo, $key006);
$isCurrent = $has002 && $has003 && $has004 && $has005 && $has006;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isCurrent) {
    require_csrf();

    try {
        if (!$has002) {
            $columns = [
                'members' => [
                    'address_street' => 'VARCHAR(190) NULL AFTER phone',
                    'postal_code' => 'VARCHAR(20) NULL AFTER address_street',
                    'city' => 'VARCHAR(120) NULL AFTER postal_code',
                    'school_name' => 'VARCHAR(180) NULL AFTER city',
                    'shirt_size' => 'VARCHAR(20) NULL AFTER school_name',
                    'pants_size' => 'VARCHAR(20) NULL AFTER shirt_size',
                    'shoe_size' => 'VARCHAR(20) NULL AFTER pants_size',
                    'pickup_authorized' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER shoe_size',
                ],
                'events' => [
                    'learning_goals' => 'TEXT NULL AFTER description_text',
                    'material_needed' => 'TEXT NULL AFTER learning_goals',
                    'max_participants' => 'SMALLINT UNSIGNED NULL AFTER material_needed',
                    'response_deadline' => 'DATETIME NULL AFTER max_participants',
                    'reminder_at' => 'DATETIME NULL AFTER response_deadline',
                    'recurrence_rule' => "ENUM('none','weekly','biweekly','monthly') NOT NULL DEFAULT 'none' AFTER reminder_at",
                    'leader_id' => 'BIGINT UNSIGNED NULL AFTER status_name',
                ],
            ];
            foreach ($columns as $table => $tableColumns) {
                foreach ($tableColumns as $name => $definition) {
                    if (!update_column_exists($pdo, $table, $name)) {
                        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$name} {$definition}");
                    }
                }
            }
            if (!update_constraint_exists($pdo, 'events_leader_fk')) {
                $pdo->exec('ALTER TABLE events ADD CONSTRAINT events_leader_fk FOREIGN KEY (leader_id) REFERENCES users(id) ON DELETE SET NULL');
            }
            update_create_tables($pdo, dirname(__DIR__) . '/database/migrations/002_member_records_and_responses.sql');
            update_record_migration($pdo, $key002);
            audit('system_update', 'schema_migrations', null, 'Migration 002 installiert');
            $messages[] = 'Migration 002 – Mitgliederakten und Rückmeldungen';
            $has002 = true;
        }

        if (!$has003) {
            update_create_tables($pdo, dirname(__DIR__) . '/database/migrations/003_member_documents.sql');
            $documentRoot = dirname(__DIR__) . '/storage/member-documents';
            if (!is_dir($documentRoot) && !mkdir($documentRoot, 0750, true) && !is_dir($documentRoot)) {
                throw new RuntimeException('Das Dokumentenverzeichnis konnte nicht angelegt werden.');
            }
            update_record_migration($pdo, $key003);
            audit('system_update', 'schema_migrations', null, 'Migration 003 installiert');
            $messages[] = 'Migration 003 – Sichere Dokumentenablage';
            $has003 = true;
        }

        foreach ([
            [$key004, '004_saas_platform.sql', 'Migration 004 – SaaS-Tarife und Abonnements'],
            [$key005, '005_saas_operations.sql', 'Migration 005 – SaaS-Betrieb und Benachrichtigungen'],
            [$key006, '006_tenant_management.sql', 'Migration 006 – Mandantenverwaltung, Rollen und Datenschutz'],
        ] as [$migrationKey, $migrationFile, $migrationLabel]) {
            if (!update_migration_applied($pdo, $migrationKey)) {
                update_run_sql_file($pdo, dirname(__DIR__) . '/database/migrations/' . $migrationFile);
                update_record_migration($pdo, $migrationKey);
                audit('system_update', 'schema_migrations', null, $migrationLabel . ' installiert');
                $messages[] = $migrationLabel;
            }
        }
        $has004 = $has005 = $has006 = true;

        $planInsert=$pdo->prepare('INSERT IGNORE INTO saas_plans (plan_key,name,description,monthly_price,yearly_price,member_limit,user_limit,storage_limit_mb) VALUES (?,?,?,?,?,?,?,?)');
        foreach([
            ['free','Free','Für kleine Jugendfeuerwehren zum Einstieg.',0,0,50,5,250],
            ['standard','Standard','Die vollständige Basisverwaltung.',9.90,99,250,15,2048],
            ['professional','Professional','Mehr Automatisierung und Auswertungen.',19.90,199,1000,50,10240],
            ['enterprise','Enterprise','Individuelle Limits und Betreuung.',49.90,499,null,null,51200],
        ] as $plan)$planInsert->execute($plan);
        $freeId=(int)$pdo->query("SELECT id FROM saas_plans WHERE plan_key='free'")->fetchColumn();
        $enterpriseId=(int)$pdo->query("SELECT id FROM saas_plans WHERE plan_key='enterprise'")->fetchColumn();
        $legacy=$pdo->query('SELECT o.id,o.name,o.email,EXISTS(SELECT 1 FROM users u WHERE u.tenant_id=o.id AND u.is_superadmin=1) operator_tenant FROM organizations o LEFT JOIN saas_subscriptions s ON s.tenant_id=o.id WHERE s.id IS NULL')->fetchAll();
        $subscriptionInsert=$pdo->prepare('INSERT INTO saas_subscriptions (tenant_id,plan_id,status_name,billing_cycle,starts_at,trial_ends_at,current_period_start,current_period_end) VALUES (?,?,?,\'manual\',?,?,?,?)');
        $settingsInsert=$pdo->prepare('INSERT IGNORE INTO saas_tenant_settings (tenant_id,display_name,support_email) VALUES (?,?,?)');
        foreach($legacy as $tenant){$operator=(int)$tenant['operator_tenant']===1;$today=date('Y-m-d');$end=$operator?null:date('Y-m-d',strtotime('+30 days'));$subscriptionInsert->execute([(int)$tenant['id'],$operator?$enterpriseId:$freeId,$operator?'active':'trial',$today,$end,$today,$end]);$settingsInsert->execute([(int)$tenant['id'],$tenant['name'],$tenant['email']?:null]);}

        $isCurrent = $has002 && $has003 && $has004 && $has005 && $has006;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
?><!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Systemupdate · JF-SYSTEM.de</title>
<style>
:root{--navy:#102b4e;--red:#d71920;--line:#dce4ed;--bg:#f3f6f9;--green:#16844a}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:#122033;font:15px/1.5 Inter,system-ui,sans-serif}.wrap{max-width:760px;margin:60px auto;padding:0 20px}.panel{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 16px 50px #102b4e14;overflow:hidden}.head,.body{padding:28px 32px}.head{background:var(--navy);color:#fff}.head h1{margin:0 0 6px}.head p{margin:0;color:#c8d7e8}.status{padding:15px;border-radius:10px;margin-bottom:18px;background:#eaf6ef;color:#0c713d}.error{background:#fde9ea;color:#a5161b}.list{margin:16px 0;padding-left:20px}.actions{display:flex;gap:10px;align-items:center}.btn{border:0;border-radius:9px;padding:11px 16px;background:var(--red);color:#fff;font-weight:800;text-decoration:none;cursor:pointer}.secondary{background:#fff;color:var(--navy);border:1px solid var(--line)}.version{display:flex;justify-content:space-between;gap:18px;padding:12px 0;border-bottom:1px solid var(--line)}.version:last-child{border:0}.ok{color:var(--green);font-weight:800}.pending{color:#a5161b;font-weight:800}
</style></head><body><main class="wrap"><section class="panel"><header class="head"><h1>JF-SYSTEM.de Update</h1><p>Datenbank sicher auf den aktuellen Stand bringen</p></header><div class="body">
<?php if ($error): ?><div class="status error"><strong>Update fehlgeschlagen:</strong><br><?= e($error) ?></div><?php endif; ?>
<?php if ($messages): ?><div class="status"><strong>Installiert:</strong><ul class="list"><?php foreach ($messages as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="version"><span>002 · Mitgliederakten & Rückmeldungen</span><span class="<?= $has002?'ok':'pending' ?>"><?= $has002?'Installiert':'Ausstehend' ?></span></div>
<div class="version"><span>003 · Dokumentenablage</span><span class="<?= $has003?'ok':'pending' ?>"><?= $has003?'Installiert':'Ausstehend' ?></span></div>
<div class="version"><span>004 · SaaS-Tarife & Abonnements</span><span class="<?= $has004?'ok':'pending' ?>"><?= $has004?'Installiert':'Ausstehend' ?></span></div>
<div class="version"><span>005 · SaaS-Betrieb & Meldungen</span><span class="<?= $has005?'ok':'pending' ?>"><?= $has005?'Installiert':'Ausstehend' ?></span></div>
<div class="version"><span>006 · Mandantenverwaltung & DSGVO</span><span class="<?= $has006?'ok':'pending' ?>"><?= $has006?'Installiert':'Ausstehend' ?></span></div>
<?php if ($isCurrent): ?><div class="status" style="margin-top:20px"><strong>System ist aktuell.</strong><br>Alle verfügbaren Migrationen wurden installiert.</div><div class="actions"><a class="btn" href="../">Zur Anwendung</a></div>
<?php else: ?><p>Erstellen Sie vor dem Start eine vollständige Datenbanksicherung. Das Update kann danach ohne SSH im Browser ausgeführt werden.</p><form method="post"><?= csrf_field() ?><button class="btn">Ausstehende Updates installieren</button> <a class="btn secondary" href="../">Abbrechen</a></form><?php endif; ?>
</div></section></main></body></html>
