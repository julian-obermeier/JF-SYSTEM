<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$lockFile = $root . '/storage/installed.lock';
$configFile = $root . '/config/config.php';
$errors = [];
$success = false;

if (is_file($lockFile) || is_file($configFile)) {
    http_response_code(403);
    exit('JF-SYSTEM.de ist bereits installiert. Entfernen Sie den Installer vom Server.');
}

$requirements = [
    'PHP 8.1+' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'PDO' => extension_loaded('pdo'),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'mbstring' => extension_loaded('mbstring'),
    'config/ beschreibbar' => is_writable($root . '/config'),
    'storage/ beschreibbar' => is_writable($root . '/storage'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($requirements as $label => $passed) {
        if (!$passed) {
            $errors[] = 'Voraussetzung nicht erfüllt: ' . $label;
        }
    }

    $host = trim((string) ($_POST['db_host'] ?? 'localhost'));
    $port = (int) ($_POST['db_port'] ?? 3306);
    $database = trim((string) ($_POST['db_name'] ?? ''));
    $dbUser = trim((string) ($_POST['db_user'] ?? ''));
    $dbPassword = (string) ($_POST['db_password'] ?? '');
    $organization = trim((string) ($_POST['organization'] ?? ''));
    $city = trim((string) ($_POST['city'] ?? ''));
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if ($database === '' || $dbUser === '' || $organization === '' || $firstName === '' || $lastName === '') {
        $errors[] = 'Bitte füllen Sie alle Pflichtfelder aus.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
    }
    if (strlen($password) < 10) {
        $errors[] = 'Das Administrator-Passwort muss mindestens 10 Zeichen lang sein.';
    }

    if (!$errors) {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
            $pdo = new PDO($dsn, $dbUser, $dbPassword, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $schema = file_get_contents($root . '/database/schema.sql');
            foreach (array_filter(array_map('trim', explode(';', (string) $schema))) as $statement) {
                $pdo->exec($statement);
            }

            $pdo->beginTransaction();
            $slugSource = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT', $organization) : $organization;
            $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', (string) $slugSource), '-'));
            $stmt = $pdo->prepare('INSERT INTO organizations (name, slug, city, email) VALUES (?, ?, ?, ?)');
            $stmt->execute([$organization, $slug ?: 'jugendfeuerwehr', $city ?: null, $email]);
            $tenantId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                'INSERT INTO users (tenant_id, first_name, last_name, email, password_hash, role, is_superadmin)
                 VALUES (?, ?, ?, ?, ?, "admin", 1)'
            );
            $stmt->execute([$tenantId, $firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT)]);

            $qualifications = [
                ['Jugendflamme Stufe 1', 'Jugendflamme'],
                ['Jugendflamme Stufe 2', 'Jugendflamme'],
                ['Jugendflamme Stufe 3', 'Jugendflamme'],
                ['Leistungsspange', 'Auszeichnung'],
                ['Erste Hilfe', 'Ausbildung'],
            ];
            $stmt = $pdo->prepare('INSERT INTO qualifications (tenant_id, title, category_name) VALUES (?, ?, ?)');
            foreach ($qualifications as $qualification) {
                $stmt->execute([$tenantId, $qualification[0], $qualification[1]]);
            }
            $pdo->commit();

            $config = [
                'app' => [
                    'name' => 'JF-SYSTEM.de',
                    'timezone' => 'Europe/Berlin',
                    'support_url' => 'https://ticket.obermeier-it.de',
                ],
                'database' => [
                    'host' => $host,
                    'port' => $port,
                    'name' => $database,
                    'user' => $dbUser,
                    'password' => $dbPassword,
                ],
            ];
            $configContent = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";

            if (file_put_contents($configFile, $configContent, LOCK_EX) === false) {
                throw new RuntimeException('Die Konfigurationsdatei konnte nicht geschrieben werden.');
            }
            if (file_put_contents($lockFile, date(DATE_ATOM), LOCK_EX) === false) {
                throw new RuntimeException('Die Installationssperre konnte nicht geschrieben werden.');
            }
            $success = true;
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Installation fehlgeschlagen: ' . $exception->getMessage();
        }
    }
}
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>JF-SYSTEM.de · Installation</title>
<style>
:root{--navy:#102b4e;--red:#d71920;--ink:#122033;--muted:#637083;--line:#dce3eb;--bg:#f2f5f8;--green:#16844a}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.5 Inter,system-ui,-apple-system,sans-serif}
.wrap{max-width:980px;margin:48px auto;padding:0 24px}.brand{display:flex;align-items:center;gap:14px;margin-bottom:28px}.mark{width:50px;height:58px;background:var(--navy);color:#fff;display:grid;place-items:center;clip-path:polygon(50% 0,95% 18%,87% 76%,50% 100%,13% 76%,5% 18%);font-size:25px}.brand strong{font-size:25px}.brand strong span{color:var(--red)}
.panel{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 14px 45px rgba(16,43,78,.08);overflow:hidden}.head{padding:30px 34px;border-bottom:1px solid var(--line)}h1{margin:0 0 8px;font-size:30px}.head p{margin:0;color:var(--muted)}
.content{display:grid;grid-template-columns:280px 1fr}.requirements{padding:28px;background:#f8fafc;border-right:1px solid var(--line)}.requirements h2{font-size:15px;margin:0 0 18px}.req{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid var(--line)}.ok{color:var(--green);font-weight:700}.bad{color:var(--red);font-weight:700}
form{padding:28px 34px}.section{font-size:13px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-weight:800;margin:5px 0 14px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.full{grid-column:1/-1}label{display:block;font-weight:700;font-size:13px;margin-bottom:6px}input{width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:11px 12px;font:inherit;outline:none}input:focus{border-color:var(--navy);box-shadow:0 0 0 3px rgba(16,43,78,.1)}
button{border:0;border-radius:10px;background:var(--red);color:#fff;font:700 14px inherit;padding:13px 20px;cursor:pointer;margin-top:22px}.alert{padding:13px 15px;border-radius:10px;margin-bottom:18px;background:#fff0f0;color:#9d1015}.success{padding:50px;text-align:center}.success a{display:inline-block;margin-top:20px;background:var(--red);color:#fff;text-decoration:none;padding:13px 22px;border-radius:10px;font-weight:800}
@media(max-width:760px){.wrap{margin:20px auto;padding:0 12px}.content{grid-template-columns:1fr}.requirements{border-right:0;border-bottom:1px solid var(--line)}.grid{grid-template-columns:1fr}.full{grid-column:auto}}
</style>
</head>
<body><main class="wrap">
<div class="brand"><div class="mark">🔥</div><strong>JF-SYSTEM<span>.de</span></strong></div>
<section class="panel">
<div class="head"><h1>Online-Installation</h1><p>In wenigen Minuten zur einsatzbereiten Jugendfeuerwehr-Verwaltung.</p></div>
<?php if ($success): ?>
<div class="success"><h2>Installation erfolgreich</h2><p>JF-SYSTEM.de wurde eingerichtet. Löschen oder sperren Sie jetzt den Ordner <strong>install</strong>.</p><a href="../">Zur Anmeldung</a></div>
<?php else: ?>
<div class="content"><aside class="requirements"><h2>Systemprüfung</h2>
<?php foreach ($requirements as $label => $passed): ?><div class="req"><span><?= htmlspecialchars($label) ?></span><span class="<?= $passed ? 'ok' : 'bad' ?>"><?= $passed ? 'OK' : 'FEHLT' ?></span></div><?php endforeach; ?>
</aside>
<form method="post" autocomplete="off">
<?php if ($errors): ?><div class="alert"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div><?php endif; ?>
<div class="section">Datenbank</div><div class="grid">
<div><label>Datenbank-Host *</label><input name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required></div>
<div><label>Port *</label><input name="db_port" type="number" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>" required></div>
<div><label>Datenbankname *</label><input name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required></div>
<div><label>Datenbankbenutzer *</label><input name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required></div>
<div class="full"><label>Datenbankpasswort</label><input name="db_password" type="password"></div>
</div>
<div class="section" style="margin-top:26px">Organisation & Administrator</div><div class="grid">
<div class="full"><label>Name der Jugendfeuerwehr *</label><input name="organization" value="<?= htmlspecialchars($_POST['organization'] ?? 'Jugendfeuerwehr Hohenahr-Erda') ?>" required></div>
<div class="full"><label>Ort</label><input name="city" value="<?= htmlspecialchars($_POST['city'] ?? 'Hohenahr-Erda') ?>"></div>
<div><label>Vorname *</label><input name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required></div>
<div><label>Nachname *</label><input name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required></div>
<div><label>E-Mail *</label><input name="email" type="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required></div>
<div><label>Passwort (mind. 10 Zeichen) *</label><input name="password" type="password" minlength="10" required></div>
</div><button type="submit">JF-SYSTEM.de installieren</button>
</form></div>
<?php endif; ?>
</section></main></body></html>
