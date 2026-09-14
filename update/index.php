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
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function update_constraint_exists(PDO $pdo, string $name): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?'
    );
    $stmt->execute([$name]);
    return (int) $stmt->fetchColumn() > 0;
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        migration_key VARCHAR(100) PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$alreadyApplied = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration_key = ?');
$alreadyApplied->execute(['002-member-records-events-responses']);
$isCurrent = (int) $alreadyApplied->fetchColumn() > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isCurrent) {
    require_csrf();

    try {
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
                    $messages[] = "{$table}.{$name} ergänzt";
                }
            }
        }

        if (!update_constraint_exists($pdo, 'events_leader_fk')) {
            $pdo->exec('ALTER TABLE events ADD CONSTRAINT events_leader_fk FOREIGN KEY (leader_id) REFERENCES users(id) ON DELETE SET NULL');
            $messages[] = 'Verantwortlichen-Zuordnung ergänzt';
        }

        $migration = file_get_contents(dirname(__DIR__) . '/database/migrations/002_member_records_and_responses.sql');
        preg_match_all('/CREATE TABLE\s+([a-z_]+)\s*\(.*?\) ENGINE=InnoDB.*?;/si', (string) $migration, $matches);
        foreach ($matches[0] as $createSql) {
            $createSql = preg_replace('/CREATE TABLE\s+/i', 'CREATE TABLE IF NOT EXISTS ', $createSql, 1);
            $pdo->exec((string) $createSql);
        }

        $record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)');
        $record->execute(['002-member-records-events-responses']);
        audit('system_update', 'schema_migrations', null, 'Migration 002 installiert');
        $isCurrent = true;
        $messages[] = 'Migration 002 erfolgreich abgeschlossen';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
?><!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Systemupdate · JF-SYSTEM.de</title>
<style>
:root{--navy:#102b4e;--red:#d71920;--line:#dce4ed;--bg:#f3f6f9;--green:#16844a}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:#122033;font:15px/1.5 Inter,system-ui,sans-serif}.wrap{max-width:760px;margin:60px auto;padding:0 20px}.panel{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 16px 50px #102b4e14;overflow:hidden}.head,.body{padding:28px 32px}.head{background:var(--navy);color:#fff}.head h1{margin:0 0 6px}.head p{margin:0;color:#c8d7e8}.status{padding:15px;border-radius:10px;margin-bottom:18px;background:#eaf6ef;color:#0c713d}.error{background:#fde9ea;color:#a5161b}.list{margin:16px 0;padding-left:20px}.actions{display:flex;gap:10px;align-items:center}.btn{border:0;border-radius:9px;padding:11px 16px;background:var(--red);color:#fff;font-weight:800;text-decoration:none;cursor:pointer}.secondary{background:#fff;color:var(--navy);border:1px solid var(--line)}
</style></head><body><main class="wrap"><section class="panel"><header class="head"><h1>JF-SYSTEM.de Update</h1><p>Mitgliederakten, Einwilligungen, Dienstplanung und Rückmeldungen</p></header><div class="body">
<?php if ($error): ?><div class="status error"><strong>Update fehlgeschlagen:</strong><br><?= e($error) ?></div><?php endif; ?>
<?php if ($messages): ?><div class="status"><strong>Durchgeführte Schritte:</strong><ul class="list"><?php foreach ($messages as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($isCurrent): ?><div class="status"><strong>System ist aktuell.</strong><br>Migration 002 wurde erfolgreich installiert.</div><div class="actions"><a class="btn" href="../">Zur Anwendung</a></div>
<?php else: ?><p>Dieses Update erweitert die Datenbank. Erstellen Sie vor dem Start eine vollständige Datenbanksicherung.</p><ul class="list"><li>Erweiterte Mitglieder- und Kontaktdaten</li><li>Sorgeberechtigte und Abholberechtigungen</li><li>Einwilligungen mit Ablaufüberwachung</li><li>Erweiterte Dienstplanung und Rückmeldungen</li></ul><form method="post"><?= csrf_field() ?><button class="btn">Update jetzt installieren</button> <a class="btn secondary" href="../">Abbrechen</a></form><?php endif; ?>
</div></section></main></body></html>
