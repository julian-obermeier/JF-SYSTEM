<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = db();
$schema = dirname(__DIR__) . '/database/migrations/005_saas_operations.sql';
foreach (array_filter(array_map('trim', explode(';', (string) file_get_contents($schema)))) as $statement) {
    $pdo->exec($statement);
}
$plans = $pdo->query("SELECT id,name,monthly_price FROM saas_plans WHERE is_active=1 AND is_public=1 ORDER BY monthly_price")->fetchAll();
$errors=[]; $sent=false;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    require_csrf();
    $org=trim((string)($_POST['organization_name']??'')); $city=trim((string)($_POST['city']??''));
    $email=mb_strtolower(trim((string)($_POST['email']??''))); $first=trim((string)($_POST['first_name']??''));
    $last=trim((string)($_POST['last_name']??'')); $password=(string)($_POST['password']??'');
    $plan=(int)($_POST['plan_id']??0);
    if($org===''||$first===''||$last===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<10) $errors[]='Bitte alle Pflichtfelder korrekt ausfüllen.';
    if(!$errors){
        $exists=$pdo->prepare('SELECT COUNT(*) FROM users WHERE email=? UNION ALL SELECT COUNT(*) FROM saas_registrations WHERE contact_email=? AND status_name IN ("pending","verified")');
        $exists->execute([$email,$email]); $counts=$exists->fetchAll(PDO::FETCH_COLUMN);
        if(array_sum(array_map('intval',$counts))>0) $errors[]='Diese E-Mail-Adresse ist bereits registriert oder wartet auf Freigabe.';
    }
    if(!$errors){
        $stmt=$pdo->prepare('INSERT INTO saas_registrations (organization_name,city,contact_email,first_name,last_name,password_hash,plan_id,verification_token) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$org,$city?:null,$email,$first,$last,password_hash($password,PASSWORD_DEFAULT),$plan?:null,bin2hex(random_bytes(32))]);
        $sent=true;
    }
}
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Registrierung · JF-SYSTEM.de</title>
<style>:root{--navy:#102b4e;--red:#d71920;--bg:#f3f6f9;--line:#dce4ed;--muted:#66758a}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:#152235;font:15px/1.5 Inter,system-ui,sans-serif}.wrap{max-width:760px;margin:50px auto;padding:0 18px}.brand{font-size:24px;font-weight:800;color:var(--navy);margin-bottom:24px}.brand em{color:var(--red);font-style:normal}.panel{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 14px 45px #102b4e12;overflow:hidden}.head{padding:28px 32px;background:var(--navy);color:#fff}.head h1{margin:0 0 8px}.head p{margin:0;color:#d2dfec}.body{padding:30px 32px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.full{grid-column:1/-1}label{display:block;font-weight:700;font-size:13px;margin-bottom:6px}input,select{width:100%;padding:11px 12px;border:1px solid #cbd5e1;border-radius:9px;font:inherit}button{border:0;border-radius:9px;background:var(--red);color:#fff;padding:12px 18px;font-weight:800;cursor:pointer;margin-top:20px}.error{background:#fff0f0;color:#a5161b;border-radius:9px;padding:12px;margin-bottom:18px}.success{background:#eaf6ef;color:#0c713d;border-radius:9px;padding:18px}.back{display:inline-block;margin-top:22px;color:var(--navy);font-weight:700}@media(max-width:650px){.grid{grid-template-columns:1fr}.full{grid-column:auto}.wrap{margin:20px auto}.body{padding:22px 18px}}</style></head><body><main class="wrap"><div class="brand">JF-SYSTEM<em>.de</em></div><section class="panel"><header class="head"><h1>Jugendfeuerwehr registrieren</h1><p>Fordern Sie einen kostenlosen Zugang mit 30 Tagen Testphase an.</p></header><div class="body"><?php if($sent): ?><div class="success"><strong>Anfrage eingegangen.</strong><br>Ihre Registrierung wird durch den Plattformbetreiber geprüft. Sie erhalten danach die Zugangsinformationen.</div><a class="back" href="../">Zur Anmeldung</a><?php else: ?><?php if($errors): ?><div class="error"><?=implode('<br>',array_map('e',$errors))?></div><?php endif; ?><form method="post"><div class="grid"><?=csrf_field()?><div class="full"><label>Jugendfeuerwehr / Organisation *</label><input name="organization_name" required value="<?=e($_POST['organization_name']??'')?>"></div><div><label>Ort</label><input name="city" value="<?=e($_POST['city']??'')?>"></div><div><label>Gewünschter Tarif</label><select name="plan_id"><?php foreach($plans as $p): ?><option value="<?= (int)$p['id'] ?>"><?=e($p['name'])?> – <?=number_format((float)$p['monthly_price'],2,',','.')?> € / Monat</option><?php endforeach; ?></select></div><div><label>Vorname *</label><input name="first_name" required></div><div><label>Nachname *</label><input name="last_name" required></div><div class="full"><label>E-Mail-Adresse *</label><input type="email" name="email" required></div><div class="full"><label>Passwort *</label><input type="password" name="password" minlength="10" required></div></div><button>Registrierungsanfrage senden</button></form><?php endif; ?></div></section></main></body></html>