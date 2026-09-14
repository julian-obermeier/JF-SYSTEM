<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$page = (string) ($_GET['page'] ?? 'dashboard');
$allowedPages = ['login', 'dashboard', 'members', 'events', 'attendance', 'qualifications', 'users', 'settings'];
if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}

if ($page === 'login') {
    if (Auth::check()) {
        redirect('?page=dashboard');
    }

    $loginError = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        if (Auth::attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            redirect('?page=dashboard');
        }
        $loginError = 'Anmeldung fehlgeschlagen. Bitte prüfen Sie Ihre Zugangsdaten oder warten Sie nach mehreren Versuchen 15 Minuten.';
    }
    ?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Anmeldung · JF-SYSTEM.de</title>
<link rel="stylesheet" href="<?= e(asset_url('app.css')) ?>">
</head>
<body>
<main class="login-shell">
<section class="login-panel">
<div class="brand login-brand"><div class="brand-mark">🔥</div><div><div class="brand-title" style="color:#102b4e">JF-SYSTEM<em>.de</em></div><div class="brand-sub" style="color:#66758a">Stark. Gemeinsam. Für morgen.</div></div></div>
<h1>Willkommen zurück</h1><p>Melden Sie sich an, um Ihre Jugendfeuerwehr zu verwalten.</p>
<?php if ($loginError): ?><div class="flash flash-error" style="position:static;margin-bottom:18px"><?= e($loginError) ?></div><?php endif; ?>
<?php if (isset($_GET['expired'])): ?><div class="flash flash-error" style="position:static;margin-bottom:18px">Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.</div><?php endif; ?>
<form method="post" autocomplete="on">
<?= csrf_field() ?>
<div class="field"><label for="email">E-Mail-Adresse</label><input id="email" name="email" type="email" autocomplete="username" required autofocus></div>
<div class="field"><label for="password">Passwort</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
<button class="btn btn-primary" type="submit">Sicher anmelden</button>
</form>
<p style="font-size:12px;margin-top:28px">Probleme bei der Anmeldung? <a href="<?= e(app_config()['app']['support_url']) ?>" target="_blank" rel="noopener">Support öffnen</a></p>
</section>
<section class="login-art"><div class="login-art-copy"><h2>Mehr Zeit für Jugendarbeit.<br>Weniger Verwaltungsaufwand.</h2><p>Mitglieder, Dienste, Anwesenheiten und Qualifikationen an einem sicheren Ort.</p></div></section>
</main></body></html>
<?php
    exit;
}

Auth::requireLogin();
$user = Auth::user();
$tenantId = tenant_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'logout') {
            audit('logout', 'users', (int) $user['id'], 'Abmeldung');
            Auth::logout();
            redirect('?page=login');
        }

        if (!Auth::canManage()) {
            throw new RuntimeException('Sie besitzen für diese Aktion keine Berechtigung.');
        }

        if ($action === 'member_save') {
            $id = (int) ($_POST['id'] ?? 0);
            $firstName = trim((string) ($_POST['first_name'] ?? ''));
            $lastName = trim((string) ($_POST['last_name'] ?? ''));
            if ($firstName === '' || $lastName === '') {
                throw new RuntimeException('Vor- und Nachname sind Pflichtfelder.');
            }
            $values = [
                $firstName,
                $lastName,
                ($_POST['birth_date'] ?? '') ?: null,
                ($_POST['entry_date'] ?? '') ?: null,
                in_array($_POST['member_type'] ?? '', ['youth', 'staff'], true) ? $_POST['member_type'] : 'youth',
                in_array($_POST['status_name'] ?? '', ['active', 'paused', 'left'], true) ? $_POST['status_name'] : 'active',
                trim((string) ($_POST['email'] ?? '')) ?: null,
                trim((string) ($_POST['phone'] ?? '')) ?: null,
                trim((string) ($_POST['emergency_name'] ?? '')) ?: null,
                trim((string) ($_POST['emergency_phone'] ?? '')) ?: null,
                trim((string) ($_POST['medical_notes'] ?? '')) ?: null,
                trim((string) ($_POST['notes_text'] ?? '')) ?: null,
            ];

            if ($id > 0) {
                $stmt = db()->prepare(
                    'UPDATE members SET first_name=?, last_name=?, birth_date=?, entry_date=?, member_type=?, status_name=?,
                     email=?, phone=?, emergency_name=?, emergency_phone=?, medical_notes=?, notes_text=?
                     WHERE id=? AND tenant_id=?'
                );
                $stmt->execute([...$values, $id, $tenantId]);
                audit('update', 'members', $id, 'Mitglied aktualisiert: ' . $firstName . ' ' . $lastName);
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO members (first_name,last_name,birth_date,entry_date,member_type,status_name,email,phone,emergency_name,emergency_phone,medical_notes,notes_text,tenant_id)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([...$values, $tenantId]);
                $id = (int) db()->lastInsertId();
                audit('create', 'members', $id, 'Mitglied angelegt: ' . $firstName . ' ' . $lastName);
            }
            flash('success', 'Mitglied wurde gespeichert.');
            redirect('?page=members');
        }

        if ($action === 'member_delete') {
            $id = (int) ($_POST['id'] ?? 0);
            db()->prepare('DELETE FROM members WHERE id=? AND tenant_id=?')->execute([$id, $tenantId]);
            audit('delete', 'members', $id, 'Mitglied gelöscht');
            flash('success', 'Mitglied wurde gelöscht.');
            redirect('?page=members');
        }

        if ($action === 'event_save') {
            $id = (int) ($_POST['id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            $start = trim((string) ($_POST['starts_at'] ?? ''));
            $end = trim((string) ($_POST['ends_at'] ?? ''));
            if ($title === '' || $start === '' || $end === '') {
                throw new RuntimeException('Titel, Beginn und Ende sind Pflichtfelder.');
            }
            if (strtotime($end) <= strtotime($start)) {
                throw new RuntimeException('Das Ende muss nach dem Beginn liegen.');
            }
            $values = [
                $title,
                in_array($_POST['event_type'] ?? '', ['practice', 'meeting', 'trip', 'competition', 'other'], true) ? $_POST['event_type'] : 'practice',
                date('Y-m-d H:i:s', strtotime($start)),
                date('Y-m-d H:i:s', strtotime($end)),
                trim((string) ($_POST['location_name'] ?? '')) ?: null,
                trim((string) ($_POST['description_text'] ?? '')) ?: null,
                in_array($_POST['status_name'] ?? '', ['draft', 'published', 'cancelled', 'completed'], true) ? $_POST['status_name'] : 'published',
            ];

            if ($id > 0) {
                $stmt = db()->prepare('UPDATE events SET title=?,event_type=?,starts_at=?,ends_at=?,location_name=?,description_text=?,status_name=? WHERE id=? AND tenant_id=?');
                $stmt->execute([...$values, $id, $tenantId]);
                audit('update', 'events', $id, 'Dienst aktualisiert: ' . $title);
            } else {
                $stmt = db()->prepare('INSERT INTO events (title,event_type,starts_at,ends_at,location_name,description_text,status_name,tenant_id,created_by) VALUES (?,?,?,?,?,?,?,?,?)');
                $stmt->execute([...$values, $tenantId, (int) $user['id']]);
                $id = (int) db()->lastInsertId();
                audit('create', 'events', $id, 'Dienst angelegt: ' . $title);
            }
            flash('success', 'Dienst wurde gespeichert.');
            redirect('?page=events');
        }

        if ($action === 'event_delete') {
            $id = (int) ($_POST['id'] ?? 0);
            db()->prepare('DELETE FROM events WHERE id=? AND tenant_id=?')->execute([$id, $tenantId]);
            audit('delete', 'events', $id, 'Dienst gelöscht');
            flash('success', 'Dienst wurde gelöscht.');
            redirect('?page=events');
        }

        if ($action === 'attendance_save') {
            $eventId = (int) ($_POST['event_id'] ?? 0);
            $check = db()->prepare('SELECT id FROM events WHERE id=? AND tenant_id=?');
            $check->execute([$eventId, $tenantId]);
            if (!$check->fetchColumn()) {
                throw new RuntimeException('Der ausgewählte Dienst wurde nicht gefunden.');
            }

            $stmt = db()->prepare(
                'INSERT INTO attendance (tenant_id,event_id,member_id,attendance_status,recorded_by)
                 VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE attendance_status=VALUES(attendance_status), recorded_by=VALUES(recorded_by)'
            );
            foreach ((array) ($_POST['attendance'] ?? []) as $memberId => $status) {
                if (in_array($status, ['present', 'excused', 'absent', 'unknown'], true)) {
                    $stmt->execute([$tenantId, $eventId, (int) $memberId, $status, (int) $user['id']]);
                }
            }
            audit('update', 'attendance', $eventId, 'Anwesenheiten erfasst');
            flash('success', 'Anwesenheiten wurden gespeichert.');
            redirect('?page=attendance&event_id=' . $eventId);
        }

        if ($action === 'user_save' && Auth::isAdmin()) {
            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
                throw new RuntimeException('Bitte geben Sie eine gültige E-Mail-Adresse und ein Passwort mit mindestens 10 Zeichen ein.');
            }
            $role = in_array($_POST['role'] ?? '', ['admin', 'leader', 'staff', 'viewer'], true) ? $_POST['role'] : 'staff';
            $stmt = db()->prepare('INSERT INTO users (tenant_id,first_name,last_name,email,password_hash,role) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$tenantId, trim((string) $_POST['first_name']), trim((string) $_POST['last_name']), $email, password_hash($password, PASSWORD_DEFAULT), $role]);
            audit('create', 'users', (int) db()->lastInsertId(), 'Benutzerkonto angelegt');
            flash('success', 'Benutzerkonto wurde angelegt.');
            redirect('?page=users');
        }

        if ($action === 'organization_save' && Auth::isAdmin()) {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Der Organisationsname darf nicht leer sein.');
            }
            $stmt = db()->prepare('UPDATE organizations SET name=?,city=?,email=?,phone=? WHERE id=?');
            $stmt->execute([$name, trim((string) $_POST['city']) ?: null, trim((string) $_POST['email']) ?: null, trim((string) $_POST['phone']) ?: null, $tenantId]);
            audit('update', 'organizations', $tenantId, 'Organisationseinstellungen geändert');
            flash('success', 'Einstellungen wurden gespeichert.');
            redirect('?page=settings');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
        redirect('?page=' . urlencode($page));
    }
}

$flash = pull_flash();
$pageTitles = [
    'dashboard' => ['Übersicht', $user['organization_name']],
    'members' => ['Mitglieder', 'Jugendliche und Betreuer verwalten'],
    'events' => ['Dienste & Übungen', 'Termine planen und veröffentlichen'],
    'attendance' => ['Anwesenheit', 'Teilnahmen zuverlässig dokumentieren'],
    'qualifications' => ['Qualifikationen', 'Ausbildungsstände im Blick behalten'],
    'users' => ['Benutzer & Rollen', 'Zugänge für das Leitungsteam'],
    'settings' => ['Einstellungen', 'Ihre Jugendfeuerwehr konfigurieren'],
];
[$heading, $subheading] = $pageTitles[$page] ?? $pageTitles['dashboard'];

$navItems = [
    'dashboard' => ['home', 'Übersicht'],
    'members' => ['users', 'Mitglieder'],
    'events' => ['calendar', 'Dienste & Übungen'],
    'attendance' => ['check', 'Anwesenheit'],
    'qualifications' => ['award', 'Qualifikationen'],
];
if (Auth::isAdmin()) {
    $navItems['users'] = ['shield', 'Benutzer & Rollen'];
    $navItems['settings'] = ['settings', 'Einstellungen'];
}
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($heading) ?> · JF-SYSTEM.de</title>
<link rel="stylesheet" href="<?= e(asset_url('app.css')) ?>">
<script src="<?= e(asset_url('app.js')) ?>" defer></script>
</head>
<body>
<div class="app">
<aside class="sidebar">
<a class="brand" href="?page=dashboard"><div class="brand-mark">🔥</div><div><div class="brand-title">JF-SYSTEM<em>.de</em></div><div class="brand-sub">Stark. Gemeinsam. Für morgen.</div></div></a>
<nav class="nav" aria-label="Hauptnavigation">
<?php foreach ($navItems as $key => [$navIcon, $label]): ?><a href="?page=<?= e($key) ?>" class="<?= $page === $key ? 'active' : '' ?>"><?= icon($navIcon) ?><span><?= e($label) ?></span></a><?php endforeach; ?>
</nav>
<div class="sidebar-foot"><div class="org-name"><?= e($user['organization_name']) ?></div><a class="support" href="<?= e(app_config()['app']['support_url']) ?>" target="_blank" rel="noopener">Support durch OBERMEIER IT</a></div>
</aside>
<div class="overlay" data-overlay></div>
<div class="main">
<header class="topbar">
<button class="menu-button" type="button" data-menu aria-label="Navigation öffnen"><?= icon('menu') ?></button>
<div class="search"><span><?= icon('search') ?></span><input type="search" placeholder="Mitglieder und Einträge durchsuchen …" data-table-search></div>
<div class="user-menu"><div class="avatar"><?= e(initials($user['first_name'], $user['last_name'])) ?></div><div class="user-copy"><strong><?= e($user['first_name'] . ' ' . $user['last_name']) ?></strong><span><?= e(role_label($user['role'])) ?></span></div>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button class="menu-button" style="display:block" aria-label="Abmelden"><?= icon('logout') ?></button></form>
</div>
</header>
<main class="content">
<?php if ($flash): ?><div class="flash <?= $flash['type'] === 'error' ? 'flash-error' : '' ?>"><?= e($flash['message']) ?></div><?php endif; ?>
<div class="page-head"><div><h1><?= e($heading) ?></h1><p><?= e($subheading) ?></p></div>
<?php if ($page === 'members' && Auth::canManage()): ?><button class="btn btn-primary" data-dialog-open="member-dialog"><?= icon('plus') ?> Mitglied anlegen</button><?php endif; ?>
<?php if ($page === 'events' && Auth::canManage()): ?><button class="btn btn-primary" data-dialog-open="event-dialog"><?= icon('plus') ?> Dienst anlegen</button><?php endif; ?>
<?php if ($page === 'users' && Auth::isAdmin()): ?><button class="btn btn-primary" data-dialog-open="user-dialog"><?= icon('plus') ?> Benutzer anlegen</button><?php endif; ?>
</div>

<?php if ($page === 'dashboard'):
    $memberCount = (int) db()->query("SELECT COUNT(*) FROM members WHERE tenant_id={$tenantId} AND member_type='youth' AND status_name='active'")->fetchColumn();
    $staffCount = (int) db()->query("SELECT COUNT(*) FROM members WHERE tenant_id={$tenantId} AND member_type='staff' AND status_name='active'")->fetchColumn();
    $eventCount = (int) db()->query("SELECT COUNT(*) FROM events WHERE tenant_id={$tenantId} AND starts_at>=NOW() AND status_name='published'")->fetchColumn();
    $rateStmt = db()->prepare("SELECT ROUND(100*SUM(a.attendance_status='present')/NULLIF(COUNT(*),0)) FROM attendance a WHERE a.tenant_id=?");
    $rateStmt->execute([$tenantId]);
    $attendanceRate = (int) ($rateStmt->fetchColumn() ?: 0);
    $nextStmt = db()->prepare("SELECT * FROM events WHERE tenant_id=? AND starts_at>=NOW() AND status_name='published' ORDER BY starts_at LIMIT 1");
    $nextStmt->execute([$tenantId]);
    $nextEvent = $nextStmt->fetch();
    $auditStmt = db()->prepare('SELECT a.*, CONCAT(u.first_name," ",u.last_name) AS user_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE a.tenant_id=? ORDER BY a.created_at DESC LIMIT 6');
    $auditStmt->execute([$tenantId]);
    $activities = $auditStmt->fetchAll();
?>
<div class="metrics">
<div class="metric"><div class="metric-icon"><?= icon('users') ?></div><div><strong><?= $memberCount ?></strong><span>aktive Jugendliche</span></div></div>
<div class="metric"><div class="metric-icon"><?= icon('shield') ?></div><div><strong><?= $staffCount ?></strong><span>Betreuer</span></div></div>
<div class="metric"><div class="metric-icon"><?= icon('check') ?></div><div><strong><?= $attendanceRate ?> %</strong><span>Anwesenheit gesamt</span></div></div>
<div class="metric"><div class="metric-icon"><?= icon('calendar') ?></div><div><strong><?= $eventCount ?></strong><span>kommende Dienste</span></div></div>
</div>
<div class="dashboard-grid">
<section class="panel"><div class="panel-head"><h2>Nächster Dienst</h2><a class="btn btn-secondary btn-small" href="?page=events">Alle anzeigen</a></div><div class="panel-body">
<?php if ($nextEvent): ?><div class="next-event"><h3><?= e($nextEvent['title']) ?></h3><div class="detail-list"><span><?= icon('calendar') ?> <?= e(format_date($nextEvent['starts_at'], true)) ?> Uhr</span><span><?= icon('home') ?> <?= e($nextEvent['location_name'] ?: 'Kein Ort hinterlegt') ?></span><span><?= icon('file') ?> <?= e($nextEvent['description_text'] ?: 'Keine Beschreibung hinterlegt') ?></span></div></div>
<?php else: ?><div class="empty">Noch kein kommender Dienst geplant.</div><?php endif; ?>
</div></section>
<section class="panel"><div class="panel-head"><h2>Schnellzugriff</h2></div><div class="panel-body quick-actions"><a href="?page=members">Mitglieder verwalten</a><a href="?page=events">Dienstplan öffnen</a><a href="?page=attendance">Anwesenheit erfassen</a><a href="?page=qualifications">Qualifikationen prüfen</a></div></section>
<section class="panel" style="grid-column:1/-1"><div class="panel-head"><h2>Letzte Aktivitäten</h2></div><div class="panel-body activity-list">
<?php if (!$activities): ?><div class="empty">Noch keine Aktivitäten vorhanden.</div><?php endif; ?>
<?php foreach ($activities as $activity): ?><div class="activity"><span class="dot"></span><div><strong><?= e($activity['description_text']) ?></strong><small><?= e($activity['user_name'] ?: 'System') ?></small></div><small><?= e(format_date($activity['created_at'], true)) ?></small></div><?php endforeach; ?>
</div></section></div>

<?php elseif ($page === 'members'):
    $stmt = db()->prepare('SELECT * FROM members WHERE tenant_id=? ORDER BY status_name, last_name, first_name');
    $stmt->execute([$tenantId]);
    $members = $stmt->fetchAll();
?>
<section class="panel"><div class="table-wrap"><table class="data-table"><thead><tr><th>Mitglied</th><th>Art</th><th>Geburtsdatum</th><th>Eintritt</th><th>Status</th><th></th></tr></thead><tbody>
<?php if (!$members): ?><tr><td colspan="6"><div class="empty">Noch keine Mitglieder angelegt.</div></td></tr><?php endif; ?>
<?php foreach ($members as $member):
$payload = e(json_encode([
'id'=>$member['id'],'first_name'=>$member['first_name'],'last_name'=>$member['last_name'],'birth_date'=>$member['birth_date'],'entry_date'=>$member['entry_date'],'member_type'=>$member['member_type'],'status_name'=>$member['status_name'],'email'=>$member['email'],'phone'=>$member['phone'],'emergency_name'=>$member['emergency_name'],'emergency_phone'=>$member['emergency_phone'],'medical_notes'=>$member['medical_notes'],'notes_text'=>$member['notes_text']
], JSON_UNESCAPED_UNICODE));
?><tr data-search-row><td><div class="member-cell"><span class="mini-avatar"><?= e(initials($member['first_name'],$member['last_name'])) ?></span><div><strong><?= e($member['last_name'] . ', ' . $member['first_name']) ?></strong><small><?= e($member['email'] ?: $member['phone'] ?: 'Keine Kontaktdaten') ?></small></div></div></td><td><?= $member['member_type']==='youth'?'Jugendliche/r':'Betreuer/in' ?></td><td><?= e(format_date($member['birth_date'])) ?></td><td><?= e(format_date($member['entry_date'])) ?></td><td><span class="status status-<?= e($member['status_name']) ?>"><?= ['active'=>'Aktiv','paused'=>'Pausiert','left'=>'Ausgetreten'][$member['status_name']] ?></span></td><td><div class="actions">
<?php if (Auth::canManage()): ?><button class="btn btn-secondary btn-small" data-dialog-open="member-dialog" data-payload="<?= $payload ?>">Bearbeiten</button><form method="post" onsubmit="return confirm('Mitglied wirklich löschen?')"><?= csrf_field() ?><input type="hidden" name="action" value="member_delete"><input type="hidden" name="id" value="<?= (int)$member['id'] ?>"><button class="btn btn-danger btn-small">Löschen</button></form><?php endif; ?>
</div></td></tr><?php endforeach; ?>
</tbody></table></div></section>

<?php elseif ($page === 'events'):
    $stmt = db()->prepare('SELECT * FROM events WHERE tenant_id=? ORDER BY starts_at DESC');
    $stmt->execute([$tenantId]);
    $events = $stmt->fetchAll();
?>
<section class="panel"><div class="table-wrap"><table class="data-table"><thead><tr><th>Dienst</th><th>Termin</th><th>Ort</th><th>Art</th><th>Status</th><th></th></tr></thead><tbody>
<?php if (!$events): ?><tr><td colspan="6"><div class="empty">Noch keine Dienste oder Übungen angelegt.</div></td></tr><?php endif; ?>
<?php foreach ($events as $event):
$payload = e(json_encode(['id'=>$event['id'],'title'=>$event['title'],'event_type'=>$event['event_type'],'starts_at'=>date('Y-m-d\TH:i',strtotime($event['starts_at'])),'ends_at'=>date('Y-m-d\TH:i',strtotime($event['ends_at'])),'location_name'=>$event['location_name'],'description_text'=>$event['description_text'],'status_name'=>$event['status_name']], JSON_UNESCAPED_UNICODE));
?><tr data-search-row><td><strong><?= e($event['title']) ?></strong><br><small><?= e($event['description_text'] ?: 'Keine Beschreibung') ?></small></td><td><?= e(format_date($event['starts_at'],true)) ?> Uhr</td><td><?= e($event['location_name'] ?: '–') ?></td><td><?= ['practice'=>'Übung','meeting'=>'Besprechung','trip'=>'Ausflug','competition'=>'Wettbewerb','other'=>'Sonstiges'][$event['event_type']] ?></td><td><span class="status status-<?= e($event['status_name']) ?>"><?= ['draft'=>'Entwurf','published'=>'Veröffentlicht','cancelled'=>'Abgesagt','completed'=>'Abgeschlossen'][$event['status_name']] ?></span></td><td><div class="actions">
<a class="btn btn-secondary btn-small" href="?page=attendance&event_id=<?= (int)$event['id'] ?>">Anwesenheit</a>
<?php if(Auth::canManage()): ?><button class="btn btn-secondary btn-small" data-dialog-open="event-dialog" data-payload="<?= $payload ?>">Bearbeiten</button><form method="post" onsubmit="return confirm('Dienst wirklich löschen?')"><?= csrf_field() ?><input type="hidden" name="action" value="event_delete"><input type="hidden" name="id" value="<?= (int)$event['id'] ?>"><button class="btn btn-danger btn-small">Löschen</button></form><?php endif; ?>
</div></td></tr><?php endforeach; ?>
</tbody></table></div></section>

<?php elseif ($page === 'attendance'):
    $eventStmt = db()->prepare('SELECT id,title,starts_at FROM events WHERE tenant_id=? ORDER BY starts_at DESC LIMIT 30');
    $eventStmt->execute([$tenantId]);
    $eventOptions = $eventStmt->fetchAll();
    $selectedEventId = (int) ($_GET['event_id'] ?? ($eventOptions[0]['id'] ?? 0));
?>
<div class="toolbar"><form method="get"><input type="hidden" name="page" value="attendance"><div class="field"><label for="event-select">Dienst auswählen</label><select id="event-select" name="event_id" onchange="this.form.submit()"><?php foreach($eventOptions as $option): ?><option value="<?= (int)$option['id'] ?>" <?= $selectedEventId===(int)$option['id']?'selected':'' ?>><?= e(format_date($option['starts_at']).' · '.$option['title']) ?></option><?php endforeach; ?></select></div></form></div>
<?php if (!$selectedEventId): ?><section class="panel"><div class="empty">Legen Sie zuerst einen Dienst an.</div></section>
<?php else:
$stmt = db()->prepare("SELECT m.id,m.first_name,m.last_name,COALESCE(a.attendance_status,'unknown') AS attendance_status FROM members m LEFT JOIN attendance a ON a.member_id=m.id AND a.event_id=? WHERE m.tenant_id=? AND m.status_name='active' ORDER BY m.member_type,m.last_name,m.first_name");
$stmt->execute([$selectedEventId,$tenantId]);
$attendanceRows=$stmt->fetchAll();
?>
<form method="post"><input type="hidden" name="action" value="attendance_save"><input type="hidden" name="event_id" value="<?= $selectedEventId ?>"><?= csrf_field() ?>
<section class="panel"><div class="panel-head"><h2>Anwesenheitsliste</h2><?php if(Auth::canManage()): ?><button class="btn btn-primary" type="submit">Anwesenheit speichern</button><?php endif; ?></div><div class="panel-body attendance-list">
<?php foreach($attendanceRows as $row): ?><div class="attendance-row"><strong><?= e($row['last_name'].', '.$row['first_name']) ?></strong>
<?php foreach(['present'=>'Anwesend','excused'=>'Entschuldigt','absent'=>'Fehlt','unknown'=>'Offen'] as $value=>$label): ?><label class="choice"><input type="radio" name="attendance[<?= (int)$row['id'] ?>]" value="<?= $value ?>" <?= $row['attendance_status']===$value?'checked':'' ?> <?= Auth::canManage()?'':'disabled' ?>><span><?= $label ?></span></label><?php endforeach; ?>
</div><?php endforeach; ?>
<?php if(!$attendanceRows): ?><div class="empty">Keine aktiven Mitglieder vorhanden.</div><?php endif; ?>
</div></section></form><?php endif; ?>

<?php elseif ($page === 'qualifications'):
$stmt=db()->prepare('SELECT q.*,COUNT(mq.id) AS holders FROM qualifications q LEFT JOIN member_qualifications mq ON mq.qualification_id=q.id WHERE q.tenant_id=? GROUP BY q.id ORDER BY q.category_name,q.title');
$stmt->execute([$tenantId]);$qualifications=$stmt->fetchAll();
?>
<section class="panel"><div class="table-wrap"><table class="data-table"><thead><tr><th>Qualifikation</th><th>Kategorie</th><th>Erreicht von</th><th>Beschreibung</th></tr></thead><tbody>
<?php foreach($qualifications as $qualification): ?><tr data-search-row><td><strong><?= e($qualification['title']) ?></strong></td><td><?= e($qualification['category_name'] ?: 'Allgemein') ?></td><td><?= (int)$qualification['holders'] ?> Mitglied(er)</td><td><?= e($qualification['description_text'] ?: '–') ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>

<?php elseif ($page === 'users' && Auth::isAdmin()):
$stmt=db()->prepare('SELECT * FROM users WHERE tenant_id=? ORDER BY last_name,first_name');$stmt->execute([$tenantId]);$users=$stmt->fetchAll();
?>
<section class="panel"><div class="table-wrap"><table class="data-table"><thead><tr><th>Benutzer</th><th>E-Mail</th><th>Rolle</th><th>Letzte Anmeldung</th><th>Status</th></tr></thead><tbody>
<?php foreach($users as $account): ?><tr data-search-row><td><div class="member-cell"><span class="mini-avatar"><?= e(initials($account['first_name'],$account['last_name'])) ?></span><strong><?= e($account['first_name'].' '.$account['last_name']) ?></strong></div></td><td><?= e($account['email']) ?></td><td><?= e(role_label($account['role'])) ?></td><td><?= e(format_date($account['last_login_at'],true)) ?></td><td><span class="status <?= $account['is_active']?'status-active':'status-left' ?>"><?= $account['is_active']?'Aktiv':'Gesperrt' ?></span></td></tr><?php endforeach; ?>
</tbody></table></div></section>

<?php elseif ($page === 'settings' && Auth::isAdmin()):
$stmt=db()->prepare('SELECT * FROM organizations WHERE id=?');$stmt->execute([$tenantId]);$organization=$stmt->fetch();
?>
<section class="panel"><form method="post"><div class="panel-head"><h2>Organisation</h2><button class="btn btn-primary">Einstellungen speichern</button></div><div class="panel-body form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="organization_save">
<div class="field field-full"><label>Name</label><input name="name" value="<?= e($organization['name']) ?>" required></div>
<div class="field"><label>Ort</label><input name="city" value="<?= e($organization['city']) ?>"></div>
<div class="field"><label>Telefon</label><input name="phone" value="<?= e($organization['phone']) ?>"></div>
<div class="field field-full"><label>Zentrale E-Mail</label><input name="email" type="email" value="<?= e($organization['email']) ?>"></div>
</div></form></section>
<?php endif; ?>
</main></div></div>

<dialog id="member-dialog"><form method="post"><div class="dialog-head"><h2>Mitglied verwalten</h2><button type="button" class="dialog-close" data-dialog-close aria-label="Schließen">×</button></div><div class="dialog-body form-grid">
<?= csrf_field() ?><input type="hidden" name="action" value="member_save"><input type="hidden" name="id" value="">
<div class="field"><label>Vorname *</label><input name="first_name" required></div><div class="field"><label>Nachname *</label><input name="last_name" required></div>
<div class="field"><label>Geburtsdatum</label><input name="birth_date" type="date"></div><div class="field"><label>Eintrittsdatum</label><input name="entry_date" type="date"></div>
<div class="field"><label>Mitgliedsart</label><select name="member_type"><option value="youth">Jugendliche/r</option><option value="staff">Betreuer/in</option></select></div>
<div class="field"><label>Status</label><select name="status_name"><option value="active">Aktiv</option><option value="paused">Pausiert</option><option value="left">Ausgetreten</option></select></div>
<div class="field"><label>E-Mail</label><input name="email" type="email"></div><div class="field"><label>Telefon</label><input name="phone"></div>
<div class="field"><label>Notfallkontakt</label><input name="emergency_name"></div><div class="field"><label>Notfalltelefon</label><input name="emergency_phone"></div>
<div class="field field-full"><label>Medizinische Hinweise</label><textarea name="medical_notes"></textarea></div><div class="field field-full"><label>Interne Notizen</label><textarea name="notes_text"></textarea></div>
</div><div class="dialog-actions"><button type="button" class="btn btn-secondary" data-dialog-close>Abbrechen</button><button class="btn btn-primary">Speichern</button></div></form></dialog>

<dialog id="event-dialog"><form method="post"><div class="dialog-head"><h2>Dienst oder Übung</h2><button type="button" class="dialog-close" data-dialog-close aria-label="Schließen">×</button></div><div class="dialog-body form-grid">
<?= csrf_field() ?><input type="hidden" name="action" value="event_save"><input type="hidden" name="id" value="">
<div class="field field-full"><label>Titel *</label><input name="title" required></div><div class="field"><label>Art</label><select name="event_type"><option value="practice">Übung</option><option value="meeting">Besprechung</option><option value="trip">Ausflug</option><option value="competition">Wettbewerb</option><option value="other">Sonstiges</option></select></div>
<div class="field"><label>Status</label><select name="status_name"><option value="published">Veröffentlicht</option><option value="draft">Entwurf</option><option value="completed">Abgeschlossen</option><option value="cancelled">Abgesagt</option></select></div>
<div class="field"><label>Beginn *</label><input name="starts_at" type="datetime-local" required></div><div class="field"><label>Ende *</label><input name="ends_at" type="datetime-local" required></div>
<div class="field field-full"><label>Ort</label><input name="location_name"></div><div class="field field-full"><label>Beschreibung</label><textarea name="description_text"></textarea></div>
</div><div class="dialog-actions"><button type="button" class="btn btn-secondary" data-dialog-close>Abbrechen</button><button class="btn btn-primary">Speichern</button></div></form></dialog>

<dialog id="user-dialog"><form method="post"><div class="dialog-head"><h2>Benutzerkonto anlegen</h2><button type="button" class="dialog-close" data-dialog-close aria-label="Schließen">×</button></div><div class="dialog-body form-grid">
<?= csrf_field() ?><input type="hidden" name="action" value="user_save">
<div class="field"><label>Vorname *</label><input name="first_name" required></div><div class="field"><label>Nachname *</label><input name="last_name" required></div>
<div class="field field-full"><label>E-Mail *</label><input name="email" type="email" required></div><div class="field"><label>Rolle</label><select name="role"><option value="staff">Betreuer</option><option value="leader">Jugendwart</option><option value="viewer">Leser</option><option value="admin">Administrator</option></select></div>
<div class="field"><label>Startpasswort *</label><input name="password" type="password" minlength="10" required></div>
</div><div class="dialog-actions"><button type="button" class="btn btn-secondary" data-dialog-close>Abbrechen</button><button class="btn btn-primary">Benutzer anlegen</button></div></form></dialog>
</body></html>
