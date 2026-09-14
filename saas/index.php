<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
Auth::requireLogin();
$operator = Auth::user();
if (!$operator || (int) $operator['is_superadmin'] !== 1) {
    http_response_code(403);
    exit('Nur der Plattform-Superadministrator darf den SaaS-Bereich öffnen.');
}

$pdo = db();
$schemaFiles = [dirname(__DIR__) . '/database/migrations/004_saas_platform.sql', dirname(__DIR__) . '/database/migrations/005_saas_operations.sql'];
foreach ($schemaFiles as $schemaFile) {
    if (!is_file($schemaFile)) continue;
    $sql = (string) file_get_contents($schemaFile);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) $pdo->exec($statement);
}
$seedPlans = [
    ['free','Free','Für kleine Jugendfeuerwehren zum Einstieg.',0,0,50,5,250],
    ['standard','Standard','Die vollständige Basisverwaltung.',9.90,99,250,15,2048],
    ['professional','Professional','Mehr Automatisierung und Auswertungen.',19.90,199,1000,50,10240],
    ['enterprise','Enterprise','Individuelle Limits und Betreuung.',49.90,499,null,null,51200],
];
$planInsert = $pdo->prepare('INSERT IGNORE INTO saas_plans (plan_key,name,description,monthly_price,yearly_price,member_limit,user_limit,storage_limit_mb) VALUES (?,?,?,?,?,?,?,?)');
foreach ($seedPlans as $plan) $planInsert->execute($plan);
$addonInsert = $pdo->prepare('INSERT IGNORE INTO saas_addons (addon_key,name,description,monthly_price) VALUES (?,?,?,?)');
foreach ([['advanced_reports','Erweiterte Auswertungen','PDF- und Jahresberichte',4.90],['parent_portal','Elternportal','Rückmeldungen und Informationen für Eltern',6.90],['priority_support','Prioritäts-Support','Bevorzugte Bearbeitung über OBERMEIER-IT',9.90]] as $addon) $addonInsert->execute($addon);

/*
 * Übernimmt Installationen, die vor dem SaaS-Ausbau angelegt wurden.
 * Der Betreiber-Mandant erhält Enterprise/manuell, weitere Bestandsmandanten
 * starten im kostenlosen 30-Tage-Test. Es werden keine Fachdaten verändert.
 */
$freePlanId = (int) $pdo->query("SELECT id FROM saas_plans WHERE plan_key='free' LIMIT 1")->fetchColumn();
$enterprisePlanId = (int) $pdo->query("SELECT id FROM saas_plans WHERE plan_key='enterprise' LIMIT 1")->fetchColumn();
$legacyTenants = $pdo->query(
    'SELECT o.id, o.name, o.email,
            EXISTS(SELECT 1 FROM users u WHERE u.tenant_id=o.id AND u.is_superadmin=1) AS is_operator_tenant
     FROM organizations o
     LEFT JOIN saas_subscriptions s ON s.tenant_id=o.id
     WHERE s.id IS NULL'
)->fetchAll();
$subscriptionInsert = $pdo->prepare(
    'INSERT IGNORE INTO saas_subscriptions
     (tenant_id,plan_id,status_name,billing_cycle,starts_at,trial_ends_at,current_period_start,current_period_end)
     VALUES (?,?,?,?,?,?,?,?)'
);
$settingsInsert = $pdo->prepare(
    'INSERT IGNORE INTO saas_tenant_settings (tenant_id,display_name,support_email) VALUES (?,?,?)'
);
foreach ($legacyTenants as $legacyTenant) {
    $isOperatorTenant = (int) $legacyTenant['is_operator_tenant'] === 1;
    $startsAt = date('Y-m-d');
    $periodEnd = $isOperatorTenant ? null : date('Y-m-d', strtotime('+30 days'));
    $subscriptionInsert->execute([
        (int) $legacyTenant['id'],
        $isOperatorTenant ? $enterprisePlanId : $freePlanId,
        $isOperatorTenant ? 'active' : 'trial',
        'manual',
        $startsAt,
        $periodEnd,
        $startsAt,
        $periodEnd,
    ]);
    $settingsInsert->execute([
        (int) $legacyTenant['id'],
        (string) $legacyTenant['name'],
        $legacyTenant['email'] ?: null,
    ]);
}

function saas_redirect(string $tab = 'overview'): never { header('Location: index.php?tab=' . urlencode($tab)); exit; }
function saas_date(?string $value): string { return $value ? (new DateTimeImmutable($value))->format('d.m.Y') : '–'; }
function saas_money(mixed $value): string { return number_format((float)$value, 2, ',', '.') . ' €'; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'tenant_create') {
            $name = trim((string)($_POST['name'] ?? ''));
            $city = trim((string)($_POST['city'] ?? ''));
            $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
            $first = trim((string)($_POST['first_name'] ?? ''));
            $last = trim((string)($_POST['last_name'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            if ($name === '' || $first === '' || $last === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) throw new RuntimeException('Bitte alle Pflichtfelder korrekt ausfüllen.');
            $slugSource = function_exists('iconv') ? iconv('UTF-8','ASCII//TRANSLIT',$name) : $name;
            $slug = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',(string)$slugSource),'-')) . '-' . bin2hex(random_bytes(2));
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO organizations (name,slug,city,email) VALUES (?,?,?,?)');
            $stmt->execute([$name,$slug,$city ?: null,$email]); $tenantId=(int)$pdo->lastInsertId();
            $stmt=$pdo->prepare('INSERT INTO users (tenant_id,first_name,last_name,email,password_hash,role,is_superadmin) VALUES (?,?,?,?,?,"admin",0)');
            $stmt->execute([$tenantId,$first,$last,$email,password_hash($password,PASSWORD_DEFAULT)]);
            $plan=(int)($_POST['plan_id'] ?? 0);
            if (!$plan) $plan=(int)$pdo->query("SELECT id FROM saas_plans WHERE plan_key='free' LIMIT 1")->fetchColumn();
            $today=date('Y-m-d'); $trial=date('Y-m-d',strtotime('+30 days'));
            $stmt=$pdo->prepare('INSERT INTO saas_subscriptions (tenant_id,plan_id,status_name,billing_cycle,starts_at,trial_ends_at,current_period_start,current_period_end) VALUES (?,?,?,"manual",?,?,?,?)');
            $stmt->execute([$tenantId,$plan,'trial',$today,$trial,$today,$trial]);
            $pdo->prepare("INSERT INTO saas_notifications (tenant_id,notification_type,title,message_text) VALUES (?,'welcome','Willkommen bei JF-SYSTEM.de','Ihr Mandantenkonto wurde eingerichtet. Die Testphase ist aktiv.')")->execute([$tenantId]);
            $pdo->commit(); flash('success','Mandant wurde angelegt.'); saas_redirect('tenants');
        }
        if ($action === 'subscription_save') {
            $tenant=(int)$_POST['tenant_id']; $plan=(int)$_POST['plan_id']; $status=(string)$_POST['status_name'];
            $allowed=['trial','active','past_due','paused','cancelled','expired']; if (!in_array($status,$allowed,true)) throw new RuntimeException('Ungültiger Status.');
            $stmt=$pdo->prepare('UPDATE saas_subscriptions SET plan_id=?,status_name=?,billing_cycle=?,trial_ends_at=?,current_period_end=? WHERE tenant_id=?');
            $cycle=in_array($_POST['billing_cycle'] ?? '',['monthly','yearly','manual'],true)?$_POST['billing_cycle']:'manual';
            $end=trim((string)($_POST['current_period_end'] ?? '')) ?: null; $trial=trim((string)($_POST['trial_ends_at'] ?? '')) ?: null;
            $stmt->execute([$plan,$status,$cycle,$trial,$end,$tenant]);
            $pdo->prepare("INSERT INTO saas_notifications (tenant_id,notification_type,title,message_text) VALUES (?,'subscription','Tarifstatus aktualisiert',?)")->execute([$tenant,'Ihr Abonnement hat jetzt den Status: '.$status.'.']);
            flash('success','Abonnement aktualisiert.'); saas_redirect('tenants');
        }
        if ($action === 'invoice_paid') {
            $id=(int)$_POST['invoice_id']; $pdo->prepare("UPDATE saas_invoices SET status_name='paid',paid_at=CURDATE() WHERE id=?")->execute([$id]); flash('success','Rechnung als bezahlt markiert.'); saas_redirect('billing');
        }
    } catch (Throwable $e) { flash('error',$e->getMessage()); }
}
$tab=in_array($_GET['tab']??'overview',['overview','tenants','billing','plans'],true)?(string)$_GET['tab']:'overview';
$flash=pull_flash();
$stats=[
'tenants'=>(int)$pdo->query('SELECT COUNT(*) FROM organizations')->fetchColumn(),
'active'=>(int)$pdo->query("SELECT COUNT(*) FROM saas_subscriptions WHERE status_name IN ('trial','active')")->fetchColumn(),
'revenue'=>(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM saas_invoices WHERE status_name='paid' AND issued_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetchColumn(),
'open'=>(int)$pdo->query("SELECT COUNT(*) FROM saas_invoices WHERE status_name IN ('open','overdue')")->fetchColumn(),
];
$plans=$pdo->query('SELECT * FROM saas_plans WHERE is_active=1 ORDER BY monthly_price')->fetchAll();
$tenants=$pdo->query("SELECT o.*,s.status_name,s.trial_ends_at,s.current_period_end,p.name AS plan_name,p.monthly_price FROM organizations o LEFT JOIN saas_subscriptions s ON s.tenant_id=o.id LEFT JOIN saas_plans p ON p.id=s.plan_id ORDER BY o.name")->fetchAll();
$invoices=$pdo->query("SELECT i.*,o.name AS organization_name FROM saas_invoices i JOIN organizations o ON o.id=i.tenant_id ORDER BY i.issued_at DESC,i.id DESC LIMIT 100")->fetchAll();
$selectedTenant=(int)($_GET['tenant']??0);
$subscription=null;
if($selectedTenant){$st=$pdo->prepare('SELECT s.*,o.name AS organization_name FROM saas_subscriptions s JOIN organizations o ON o.id=s.tenant_id WHERE s.tenant_id=?');$st->execute([$selectedTenant]);$subscription=$st->fetch()?:null;}
?><!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SaaS-Betreiberbereich · JF-SYSTEM.de</title>
<style>
:root{--navy:#0d2747;--navy2:#143b67;--red:#d71920;--bg:#f4f7fb;--ink:#152235;--muted:#6d7c90;--line:#e1e8f0;--green:#16844a;--amber:#b36b00}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.5 Inter,system-ui,-apple-system,sans-serif}.shell{min-height:100vh;display:grid;grid-template-columns:250px 1fr}.side{background:linear-gradient(180deg,var(--navy),#09192e);color:#fff;padding:28px 18px}.brand{font-size:22px;font-weight:800;margin:0 12px 34px}.brand span{color:#ff6468}.nav a{display:block;color:#b9c9da;text-decoration:none;padding:12px 14px;border-radius:10px;margin:4px 0}.nav a.active,.nav a:hover{background:#ffffff14;color:#fff}.side small{display:block;color:#89a0b8;margin:34px 12px 8px;text-transform:uppercase;letter-spacing:.08em}.main{padding:34px;max-width:1400px;width:100%}.top{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:28px}.top h1{margin:0;font-size:30px}.top p{margin:5px 0;color:var(--muted)}.back{color:var(--navy2);font-weight:700;text-decoration:none}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}.stat,.panel{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 10px 30px #102b4e0a}.stat{padding:20px}.stat small{color:var(--muted);font-weight:700}.stat strong{display:block;font-size:28px;margin-top:8px}.panel{overflow:hidden;margin-bottom:22px}.panel-head{padding:18px 22px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;gap:15px;align-items:center}.panel-head h2{font-size:17px;margin:0}.panel-body{padding:22px}.table-wrap{overflow:auto}.table{width:100%;border-collapse:collapse}.table th,.table td{text-align:left;padding:13px 16px;border-bottom:1px solid var(--line);white-space:nowrap}.table th{font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);background:#f8fafc}.table tr:last-child td{border-bottom:0}.pill{display:inline-block;border-radius:999px;padding:4px 9px;font-size:12px;font-weight:800;background:#eaf6ef;color:var(--green)}.pill.trial{background:#fff4df;color:var(--amber)}.pill.cancelled,.pill.expired{background:#ffe8e9;color:#a5161b}.btn{border:0;border-radius:9px;background:var(--red);color:#fff;padding:10px 14px;font:700 13px inherit;cursor:pointer;text-decoration:none;display:inline-block}.btn.secondary{background:#eef3f8;color:var(--navy)}.btn.small{padding:7px 10px}.flash{padding:13px 16px;border-radius:10px;margin-bottom:18px;background:#eaf6ef;color:#0c713d}.flash.error{background:#ffe8e9;color:#a5161b}.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:15px}.field.full{grid-column:1/-1}.field label{display:block;font-weight:700;font-size:13px;margin-bottom:6px}.field input,.field select{width:100%;border:1px solid #cad6e3;border-radius:9px;padding:10px 11px;font:inherit;background:#fff}.empty{padding:34px;text-align:center;color:var(--muted)}.price{font-size:25px;font-weight:800}.plan-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}.plan{padding:20px;border:1px solid var(--line);border-radius:14px}.plan h3{margin:0 0 7px}.plan p{color:var(--muted);min-height:48px}.plan strong{font-size:23px}.note{color:var(--muted);font-size:13px}@media(max-width:1050px){.shell{grid-template-columns:1fr}.side{position:static}.nav{display:flex;flex-wrap:wrap;gap:4px}.nav a{margin:0}.stats,.plan-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:650px){.main{padding:20px 14px}.stats,.plan-grid,.form-grid{grid-template-columns:1fr}.top{display:block}.table th,.table td{padding:11px 12px}}
</style></head><body><div class="shell"><aside class="side"><div class="brand">JF-SYSTEM<span>.de</span></div><nav class="nav">
<a class="<?= $tab==='overview'?'active':'' ?>" href="?tab=overview">Übersicht</a><a class="<?= $tab==='tenants'?'active':'' ?>" href="?tab=tenants">Mandanten</a><a class="<?= $tab==='billing'?'active':'' ?>" href="?tab=billing">Abrechnung</a><a class="<?= $tab==='plans'?'active':'' ?>" href="?tab=plans">Tarife & Add-ons</a><a href="registrations.php">Registrierungen</a><a href="../signup/">Registrierungsseite</a>
</nav><small>Angemeldet als</small><div style="padding:0 12px"><?=e($operator['first_name'].' '.$operator['last_name'])?></div><a class="back" style="display:block;margin:24px 12px;color:#fff" href="../">← Zur Anwendung</a></aside>
<main class="main"><header class="top"><div><h1><?= $tab==='overview'?'SaaS-Übersicht':($tab==='tenants'?'Mandantenverwaltung':($tab==='billing'?'Abrechnung':'Tarife & Add-ons')) ?></h1><p>Zentrale Plattformverwaltung für JF-SYSTEM.de</p></div><a class="back" href="https://ticket.obermeier-it.de" target="_blank" rel="noopener">Support über OBERMEIER-IT ↗</a></header>
<?php if($flash): ?><div class="flash <?= $flash['type']==='error'?'error':'' ?>"><?=e($flash['message'])?></div><?php endif; ?>
<?php if($tab==='overview'): ?><section class="stats"><div class="stat"><small>Mandanten gesamt</small><strong><?= $stats['tenants'] ?></strong></div><div class="stat"><small>Aktive / Testphase</small><strong><?= $stats['active'] ?></strong></div><div class="stat"><small>Umsatz diesen Monat</small><strong><?=saas_money($stats['revenue'])?></strong></div><div class="stat"><small>Offene Rechnungen</small><strong><?= $stats['open'] ?></strong></div></section>
<section class="panel"><div class="panel-head"><h2>Letzte Mandanten</h2><a class="btn small" href="?tab=tenants">Alle anzeigen</a></div><div class="table-wrap"><table class="table"><tr><th>Organisation</th><th>Tarif</th><th>Status</th><th>Testphase bis</th></tr><?php foreach(array_slice($tenants,0,8) as $t): ?><tr><td><strong><?=e($t['name'])?></strong><br><span class="note"><?=e($t['city']?:'–')?></span></td><td><?=e($t['plan_name']?:'Nicht zugeordnet')?></td><td><span class="pill <?=e($t['status_name']?:'expired')?>"><?=e($t['status_name']?:'ohne Abo')?></span></td><td><?=saas_date($t['trial_ends_at'])?></td></tr><?php endforeach; ?></table></div></section>
<?php elseif($tab==='tenants'): ?><section class="panel"><div class="panel-head"><h2>Neuen Mandanten anlegen</h2></div><div class="panel-body"><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="tenant_create"><div class="field full"><label>Organisation *</label><input name="name" required placeholder="z. B. Jugendfeuerwehr Hohenahr-Erda"></div><div class="field"><label>Ort</label><input name="city"></div><div class="field"><label>Kontakt-E-Mail *</label><input name="email" type="email" required></div><div class="field"><label>Admin Vorname *</label><input name="first_name" required></div><div class="field"><label>Admin Nachname *</label><input name="last_name" required></div><div class="field"><label>Startpasswort *</label><input name="password" type="password" minlength="10" required></div><div class="field"><label>Starttarif</label><select name="plan_id"><?php foreach($plans as $p): ?><option value="<?= (int)$p['id'] ?>"><?=e($p['name'])?> – <?=saas_money($p['monthly_price'])?> / Monat</option><?php endforeach; ?></select></div><div class="field full"><button class="btn">Mandantenkonto erstellen</button></div></form></div></section>
<section class="panel"><div class="panel-head"><h2>Alle Mandanten</h2></div><div class="table-wrap"><table class="table"><tr><th>Organisation</th><th>Kontakt</th><th>Tarif</th><th>Status</th><th>Aktion</th></tr><?php foreach($tenants as $t): ?><tr><td><strong><?=e($t['name'])?></strong><br><span class="note"><?=e($t['city']?:'–')?></span></td><td><?=e($t['email']?:'–')?></td><td><?=e($t['plan_name']?:'–')?></td><td><span class="pill <?=e($t['status_name']?:'expired')?>"><?=e($t['status_name']?:'ohne Abo')?></span></td><td><div style="display:flex;gap:6px"><a class="btn secondary small" href="?tab=tenants&tenant=<?=(int)$t['id']?>">Verwalten</a><form method="post" action="support.php" style="display:flex;gap:5px"><?=csrf_field()?><input type="hidden" name="tenant_id" value="<?=(int)$t['id']?>"><input name="reason_text" required maxlength="500" placeholder="Supportgrund" style="width:130px"><button class="btn small">Ansehen</button></form></div></td></tr><?php endforeach; ?></table></div></section>
<?php if($subscription): ?><section class="panel"><div class="panel-head"><h2>Abonnement: <?=e($subscription['organization_name'])?></h2></div><div class="panel-body"><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="subscription_save"><input type="hidden" name="tenant_id" value="<?= $selectedTenant ?>"><div class="field"><label>Tarif</label><select name="plan_id"><?php foreach($plans as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)$p['id']===(int)$subscription['plan_id']?'selected':'' ?>><?=e($p['name'])?></option><?php endforeach; ?></select></div><div class="field"><label>Status</label><select name="status_name"><?php foreach(['trial','active','past_due','paused','cancelled','expired'] as $s): ?><option <?= $s===$subscription['status_name']?'selected':'' ?>><?=e($s)?></option><?php endforeach; ?></select></div><div class="field"><label>Abrechnung</label><select name="billing_cycle"><?php foreach(['manual','monthly','yearly'] as $c): ?><option <?= $c===$subscription['billing_cycle']?'selected':'' ?>><?=e($c)?></option><?php endforeach; ?></select></div><div class="field"><label>Testphase bis</label><input type="date" name="trial_ends_at" value="<?=e($subscription['trial_ends_at'])?>"></div><div class="field"><label>Laufzeitende</label><input type="date" name="current_period_end" value="<?=e($subscription['current_period_end'])?>"></div><div class="field full"><button class="btn">Abonnement speichern</button></div></form></div></section><?php endif; ?>
<?php elseif($tab==='billing'): ?><section class="panel"><div class="panel-head"><h2>Rechnungen</h2><span class="note">Zahlungsanbieter später erweiterbar</span></div><div class="table-wrap"><table class="table"><tr><th>Nr.</th><th>Organisation</th><th>Betrag</th><th>Fällig</th><th>Status</th><th></th></tr><?php foreach($invoices as $i): ?><tr><td><?=e($i['invoice_number'])?></td><td><?=e($i['organization_name'])?></td><td><?=saas_money($i['amount'])?></td><td><?=saas_date($i['due_at'])?></td><td><span class="pill <?= $i['status_name']==='paid'?'':'trial' ?>"><?=e($i['status_name'])?></span></td><td><?php if($i['status_name']!=='paid'): ?><form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="action" value="invoice_paid"><input type="hidden" name="invoice_id" value="<?= (int)$i['id'] ?>"><button class="btn small">Als bezahlt markieren</button></form><?php endif; ?></td></tr><?php endforeach; ?></table><?php if(!$invoices): ?><div class="empty">Noch keine Rechnungen vorhanden.</div><?php endif; ?></div></section>
<?php else: ?><section class="panel"><div class="panel-head"><h2>Tarife</h2><span class="note">Hybrid: Free bis Enterprise</span></div><div class="panel-body"><div class="plan-grid"><?php foreach($plans as $p): ?><article class="plan"><h3><?=e($p['name'])?></h3><p><?=e($p['description'])?></p><strong><?=saas_money($p['monthly_price'])?></strong><span class="note"> / Monat</span><div class="note" style="margin-top:12px">Jahrespreis: <?=saas_money($p['yearly_price'])?><br>Mitglieder: <?= $p['member_limit']===null?'unbegrenzt':(int)$p['member_limit'] ?><br>Benutzer: <?= $p['user_limit']===null?'unbegrenzt':(int)$p['user_limit'] ?><br>Speicher: <?= $p['storage_limit_mb']===null?'unbegrenzt':(int)$p['storage_limit_mb'].' MB' ?></div></article><?php endforeach; ?></div></div></section><?php endif; ?>
</main></div></body></html>
