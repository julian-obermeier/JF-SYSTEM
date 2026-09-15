<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$db = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec((string) file_get_contents(dirname(__DIR__) . '/database/schema.sqlite.sql'));
$db->exec("INSERT INTO plans(plan_key,name) VALUES ('free','Free'),('enterprise','Enterprise')");
$db->exec("INSERT INTO users(first_name,last_name,email,password_hash,is_superadmin)
            VALUES ('Test','Operator','operator@example.test','unused',1)");
$operatorId = (int) $db->lastInsertId();
$service = new JFS\SaasRepository($db);
$new = [
    'organization'=>'Jugendfeuerwehr Beispiel', 'slug'=>'jf-beispiel', 'city'=>'Beispielstadt',
    'first_name'=>'Maria', 'last_name'=>'Muster', 'email'=>'maria@example.test',
    'password'=>'EinSicheresTestpasswort!2026', 'plan_id'=>1,
];
$tenantId = $service->createTenant($new, $operatorId, '127.0.0.1');
if ($tenantId < 1) throw new RuntimeException('Mandant fehlt.');
$adminId = (int) $db->query("SELECT id FROM users WHERE email='maria@example.test'")->fetchColumn();
if (!$adminId || !(int) $db->query("SELECT COUNT(*) FROM tenant_users WHERE tenant_id={$tenantId} AND user_id={$adminId} AND role_key='admin'")->fetchColumn()) {
    throw new RuntimeException('Admin-Zuordnung fehlt.');
}
$subscription = $db->query("SELECT status_name, trial_ends_at FROM subscriptions WHERE tenant_id={$tenantId}")->fetch();
if ($subscription['status_name'] !== 'trial' || $subscription['trial_ends_at'] === null) throw new RuntimeException('Testphase fehlt.');

$again = $new;
$again['slug'] = 'jf-zweit';
$again['organization'] = 'Jugendfeuerwehr Zwei';
$again['password'] = '';
$secondTenant = $service->createTenant($again, $operatorId, '127.0.0.1');
if ((int) $db->query("SELECT COUNT(*) FROM users WHERE email='maria@example.test'")->fetchColumn() !== 1) {
    throw new RuntimeException('Bestehendes Konto wurde dupliziert.');
}
$service->saveSubscription($secondTenant, 2, 'active', 'manual', null, $operatorId, '127.0.0.1');
if ((int) $db->query("SELECT COUNT(*) FROM audit_logs WHERE action_name='subscription.updated'")->fetchColumn() !== 1) {
    throw new RuntimeException('Abo-Audit fehlt.');
}

try {
    $service->createTenant($new, $operatorId, '127.0.0.1');
    throw new RuntimeException('Doppelter Kurzname wurde akzeptiert.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'Doppelter Kurzname wurde akzeptiert.') throw $exception;
}
if ((int) $db->query('SELECT COUNT(*) FROM tenants')->fetchColumn() !== 2) throw new RuntimeException('Rollback fehlgeschlagen.');

$_SESSION['user_id'] = $operatorId;
$auth = new JFS\AuthService($db);
if (count($auth->tenants()) !== 2 || !$auth->selectTenant($secondTenant)) throw new RuntimeException('Globaler Mandantenwechsel fehlt.');
echo "SaaS-Smoke-Test erfolgreich.\n";
