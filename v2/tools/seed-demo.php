<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$configFile = $root . '/config/config.php';
if (!is_file($configFile)) {
    copy($root . '/config/config.example.php', $configFile);
}
$config = require $configFile;
$path = $config['database']['path'];
if (is_file($path)) unlink($path);
$pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec((string) file_get_contents($root . '/database/schema.sqlite.sql'));
$pdo->exec("INSERT INTO plans(plan_key,name,member_limit,user_limit,storage_limit_mb) VALUES ('free','Free',25,3,250),('standard','Standard',75,10,2048),('professional','Professional',250,30,10240),('enterprise','Enterprise',NULL,NULL,NULL)");
foreach (['admin'=>['members.view','members.manage','events.view','events.manage','settings.manage'], 'leader'=>['members.view','members.manage','events.view','events.manage'], 'staff'=>['members.view','events.view']] as $role=>$permissions) {
    $statement=$pdo->prepare('INSERT INTO role_permissions(role_key,permission_key) VALUES(?,?)');
    foreach($permissions as $permission) $statement->execute([$role,$permission]);
}
$pdo->prepare('INSERT INTO tenants(name,slug,city) VALUES(?,?,?)')->execute(['Jugendfeuerwehr Hohenahr-Erda','hohenahr-erda','Hohenahr']);
$tenantId=(int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO users(first_name,last_name,email,password_hash,is_superadmin,email_verified_at) VALUES(?,?,?,?,1,CURRENT_TIMESTAMP)')->execute(['Julian','Obermeier','julian@example.test',password_hash('Demo123!',PASSWORD_DEFAULT)]);
$userId=(int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO tenant_users(tenant_id,user_id,role_key) VALUES(?,?,'admin')")->execute([$tenantId,$userId]);
$planId=(int)$pdo->query("SELECT id FROM plans WHERE plan_key='enterprise'")->fetchColumn();
$pdo->prepare("INSERT INTO subscriptions(tenant_id,plan_id,status_name,billing_cycle) VALUES(?,?,'active','manual')")->execute([$tenantId,$planId]);

$names=[['Lena','Müller','2011-11-07','2023-03-12','lena.mueller@web.de'],['Paul','Schmidt','2010-05-14','2022-09-01','paul.schmidt@web.de'],['Emma','Wagner','2012-02-19','2024-04-17','emma.wagner@web.de'],['Finn','Becker','2009-08-03','2021-06-05','finn.becker@web.de'],['Jonas','Fischer','2011-04-22','2023-10-10','jonas.fischer@web.de'],['Mia','Schneider','2010-09-13','2023-01-21','mia.schneider@web.de'],['Ben','Keller','2012-06-08','2024-05-14','ben.keller@web.de'],['Sophie','Neumann','2009-12-21','2022-09-03','sophie.neumann@web.de']];
$member=$pdo->prepare("INSERT INTO members(tenant_id,first_name,last_name,birth_date,entry_date,email,status_name,created_by) VALUES(?,?,?,?,?,?,'active',?)");
foreach($names as $index=>$person){$member->execute([$tenantId,...$person,$userId]);$memberId=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO member_guardians(tenant_id,member_id,full_name,relationship_name,email,phone,is_primary) VALUES(?,?,?,?,?,?,1)')->execute([$tenantId,$memberId,$person[1].' Familie','Elternteil','kontakt.'.strtolower($person[1]).'@example.test','0151 23456789']);$consent=$pdo->prepare("INSERT INTO member_consents(tenant_id,member_id,consent_type,title,status_name,granted_at) VALUES(?,?,?,?,?,?)");$consent->execute([$tenantId,$memberId,'photo','Foto- und Mediennutzung',$index%3===2?'open':'granted',$index%3===2?null:'2026-03-10']);$consent->execute([$tenantId,$memberId,'event','Teilnahme an Veranstaltungen','granted','2026-03-10']);}
$event=$pdo->prepare("INSERT INTO events(tenant_id,title,event_type,starts_at,ends_at,location_name,status_name,created_by) VALUES(?,?,?,?,?,?,'published',?)");
foreach([['Technische Hilfe – Grundlagen','practice','2026-09-18 18:00:00','2026-09-18 20:00:00','Gerätehaus Erda'],['FwDV 3 – Praktische Übung','practice','2026-09-26 10:00:00','2026-09-26 12:00:00','Übungsplatz'],['Fahrzeug- und Gerätekunde','practice','2026-10-02 18:00:00','2026-10-02 20:00:00','Gerätehaus Erda']] as $item)$event->execute([$tenantId,...$item,$userId]);
echo "Demo-Datenbank angelegt: {$path}\n";
