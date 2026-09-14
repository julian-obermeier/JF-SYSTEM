<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$configFile = $root . '/config/config.php';
$lockFile = $root . '/storage/installed.lock';
$errors = [];
$success = false;

if (is_file($lockFile) || is_file($configFile)) {
    http_response_code(403);
    exit('JF-SYSTEM v2 ist bereits installiert.');
}

$requirements = [
    'PHP 8.1 oder neuer' => PHP_VERSION_ID >= 80100,
    'PDO verfügbar' => extension_loaded('pdo'),
    'PDO MySQL verfügbar' => extension_loaded('pdo_mysql'),
    'mbstring verfügbar' => extension_loaded('mbstring'),
    'Konfiguration beschreibbar' => is_writable(dirname($configFile)),
    'Speicher beschreibbar' => is_writable(dirname($lockFile)),
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach ($requirements as $label => $passed) {
        if (!$passed) $errors[] = $label . ' – Voraussetzung nicht erfüllt.';
    }
    foreach (['db_name','db_user','organization','first_name','last_name','email','password'] as $field) {
        if (trim((string) ($_POST[$field] ?? '')) === '') $errors[] = 'Bitte alle Pflichtfelder ausfüllen.';
    }
    if (strlen((string) ($_POST['password'] ?? '')) < 10) $errors[] = 'Das Administratorpasswort muss mindestens 10 Zeichen lang sein.';

    if ($errors === []) {
        try {
            $host = trim((string) ($_POST['db_host'] ?? 'localhost'));
            $port = (int) ($_POST['db_port'] ?? 3306);
            $name = trim((string) $_POST['db_name']);
            $user = trim((string) $_POST['db_user']);
            $password = (string) ($_POST['db_password'] ?? '');
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            $pdo->exec((string) file_get_contents($root . '/database/schema.mysql.sql'));
            $pdo->beginTransaction();
            $pdo->exec("INSERT INTO plans(plan_key,name,member_limit,user_limit,storage_limit_mb) VALUES ('free','Free',25,3,250),('standard','Standard',75,10,2048),('professional','Professional',250,30,10240),('enterprise','Enterprise',NULL,NULL,NULL)");
            foreach (['admin'=>['members.view','members.manage','events.view','events.manage','settings.manage'], 'leader'=>['members.view','members.manage','events.view','events.manage'], 'staff'=>['members.view','events.view']] as $role=>$permissions) {
                $statement=$pdo->prepare('INSERT INTO role_permissions(role_key,permission_key) VALUES(?,?)');
                foreach($permissions as $permission) $statement->execute([$role,$permission]);
            }
            $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i','-',(string)$_POST['organization']),'-'));
            $tenant=$pdo->prepare('INSERT INTO tenants(name,slug,city) VALUES(?,?,?)');
            $tenant->execute([trim((string)$_POST['organization']),$slug,trim((string)($_POST['city']??''))?:null]);
            $tenantId=(int)$pdo->lastInsertId();
            $admin=$pdo->prepare('INSERT INTO users(first_name,last_name,email,password_hash,is_superadmin,email_verified_at) VALUES(?,?,?,?,1,NOW())');
            $admin->execute([trim((string)$_POST['first_name']),trim((string)$_POST['last_name']),mb_strtolower(trim((string)$_POST['email'])),password_hash((string)$_POST['password'],PASSWORD_DEFAULT)]);
            $userId=(int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO tenant_users(tenant_id,user_id,role_key) VALUES(?,?,'admin')")->execute([$tenantId,$userId]);
            $planId=(int)$pdo->query("SELECT id FROM plans WHERE plan_key='enterprise'")->fetchColumn();
            $pdo->prepare("INSERT INTO subscriptions(tenant_id,plan_id,status_name,billing_cycle) VALUES(?,?,'active','manual')")->execute([$tenantId,$planId]);
            $pdo->commit();

            $appUrl = rtrim((string)($_POST['app_url'] ?? ''), '/');
            $config = ['app'=>['name'=>'JF-SYSTEM v2','url'=>$appUrl,'timezone'=>'Europe/Berlin','debug'=>false,'installed'=>true], 'database'=>['driver'=>'mysql','host'=>$host,'port'=>$port,'name'=>$name,'user'=>$user,'password'=>$password], 'security'=>['session_name'=>'jfs_v2_session','session_timeout'=>7200]];
            $contents = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($config,true) . ";\n";
            if (file_put_contents($configFile,$contents,LOCK_EX)===false) throw new RuntimeException('Konfiguration konnte nicht geschrieben werden.');
            if (file_put_contents($lockFile,date(DATE_ATOM),LOCK_EX)===false) throw new RuntimeException('Installationssperre konnte nicht geschrieben werden.');
            $success=true;
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $errors[]='Installation fehlgeschlagen: '.$exception->getMessage();
        }
    }
}
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$defaultUrl = $scheme.'://'.($_SERVER['HTTP_HOST']??'jf-system.de');
?>
<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>JF-SYSTEM v2 installieren</title><link rel="stylesheet" href="/assets/css/app.css"></head><body class="auth-page"><main class="auth-shell" style="width:min(760px,100%)"><div class="auth-brand"><span class="brand-flame">♦</span><strong>JF-SYSTEM <small>v2</small></strong></div><section class="auth-card"><h1>JF-SYSTEM v2 installieren</h1><p>Die Neuinstallation richtet Datenbank, Betreiberzugang und ersten Mandanten ein.</p>
<?php if($success):?><div class="flash">Installation erfolgreich. Der Installer wurde gesperrt.</div><a class="button button-primary button-block" href="/login">Jetzt anmelden</a>
<?php else:?><?php if($errors):?><div class="form-alert"><?=htmlspecialchars(implode(' ',array_unique($errors)),ENT_QUOTES,'UTF-8')?></div><?php endif;?><div class="panel" style="padding:14px;margin-bottom:18px"><?php foreach($requirements as $label=>$passed):?><div style="display:flex;justify-content:space-between;padding:5px"><span><?=htmlspecialchars($label)?></span><strong style="color:<?=$passed?'#168655':'#b91c2c'?>"><?=$passed?'OK':'Fehlt'?></strong></div><?php endforeach;?></div>
<form method="post" class="form-grid" style="padding:0"><label>Datenbankserver<input name="db_host" value="<?=htmlspecialchars($_POST['db_host']??'localhost')?>" required></label><label>Port<input type="number" name="db_port" value="<?=htmlspecialchars($_POST['db_port']??'3306')?>" required></label><label>Datenbankname<input name="db_name" required></label><label>Datenbankbenutzer<input name="db_user" required></label><label class="span-2">Datenbankpasswort<input type="password" name="db_password"></label><label class="span-2">Anwendungs-URL<input type="url" name="app_url" value="<?=htmlspecialchars($_POST['app_url']??$defaultUrl)?>" required></label><label class="span-2">Jugendfeuerwehr / Organisation<input name="organization" required></label><label class="span-2">Ort<input name="city"></label><label>Vorname Administrator<input name="first_name" required></label><label>Nachname Administrator<input name="last_name" required></label><label>E-Mail-Adresse<input type="email" name="email" required></label><label>Passwort (mind. 10 Zeichen)<input type="password" name="password" minlength="10" required></label><button class="button button-primary span-2" type="submit">Sicher installieren</button></form><?php endif;?></section></main></body></html>
