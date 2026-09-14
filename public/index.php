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

            $fields = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'birth_date' => ($_POST['birth_date'] ?? '') ?: null,
                'entry_date' => ($_POST['entry_date'] ?? '') ?: null,
                'member_type' => in_array($_POST['member_type'] ?? '', ['youth', 'staff'], true) ? $_POST['member_type'] : 'youth',
                'status_name' => in_array($_POST['status_name'] ?? '', ['active', 'paused', 'left'], true) ? $_POST['status_name'] : 'active',
                'email' => trim((string) ($_POST['email'] ?? '')) ?: null,
                'phone' => trim((string) ($_POST['phone'] ?? '')) ?: null,
                'address_street' => trim((string) ($_POST['address_street'] ?? '')) ?: null,
                'postal_code' => trim((string) ($_POST['postal_code'] ?? '')) ?: null,
                'city' => trim((string) ($_POST['city'] ?? '')) ?: null,
                'school_name' => trim((string) ($_POST['school_name'] ?? '')) ?: null,
                'shirt_size' => trim((string) ($_POST['shirt_size'] ?? '')) ?: null,
                'pants_size' => trim((string) ($_POST['pants_size'] ?? '')) ?: null,
                'shoe_size' => trim((string) ($_POST['shoe_size'] ?? '')) ?: null,
                'pickup_authorized' => isset($_POST['pickup_authorized']) ? 1 : 0,
                'emergency_name' => trim((string) ($_POST['emergency_name'] ?? '')) ?: null,
                'emergency_phone' => trim((string) ($_POST['emergency_phone'] ?? '')) ?: null,
                'medical_notes' => trim((string) ($_POST['medical_notes'] ?? '')) ?: null,
                'notes_text' => trim((string) ($_POST['notes_text'] ?? '')) ?: null,
            ];

            if ($id > 0) {
                $assignments = implode(', ', array_map(static fn(string $name): string => $name . '=?', array_keys($fields)));
                $stmt = db()->prepare('UPDATE members SET ' . $assignments . ' WHERE id=? AND tenant_id=?');
                $stmt->execute([...array_values($fields), $id, $tenantId]);
                audit('update', 'members', $id, 'Mitgliedsakte aktualisiert: ' . $firstName . ' ' . $lastName);
            } else {
                $columns = implode(',', array_keys($fields));
                $placeholders = implode(',', array_fill(0, count($fields), '?'));
                $stmt = db()->prepare('INSERT INTO members (' . $columns . ',tenant_id) VALUES (' . $placeholders . ',?)');
                $stmt->execute([...array_values($fields), $tenantId]);
                $id = (int) db()->lastInsertId();
                audit('create', 'members', $id, 'Mitglied angelegt: ' . $firstName . ' ' . $lastName);
            }
            flash('success', 'Mitgliedsakte wurde gespeichert.');
            redirect('?page=members&member_id=' . $id);
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

            $leaderId = (int) ($_POST['leader_id'] ?? 0) ?: null;
            if ($leaderId !== null) {
                $leaderCheck = db()->prepare("SELECT id FROM users WHERE id=? AND tenant_id=? AND is_active=1 AND role IN ('admin','leader','staff')");
                $leaderCheck->execute([$leaderId, $tenantId]);
                if (!$leaderCheck->fetchColumn()) {
                    throw new RuntimeException('Die ausgewählte Leitung ist nicht verfügbar.');
                }
            }

            $fields = [
                'title' => $title,
                'event_type' => in_array($_POST['event_type'] ?? '', ['practice', 'meeting', 'trip', 'competition', 'other'], true) ? $_POST['event_type'] : 'practice',
                'starts_at' => date('Y-m-d H:i:s', strtotime($start)),
                'ends_at' => date('Y-m-d H:i:s', strtotime($end)),
                'location_name' => trim((string) ($_POST['location_name'] ?? '')) ?: null,
                'description_text' => trim((string) ($_POST['description_text'] ?? '')) ?: null,
                'learning_goals' => trim((string) ($_POST['learning_goals'] ?? '')) ?: null,
                'material_needed' => trim((string) ($_POST['material_needed'] ?? '')) ?: null,
                'max_participants' => (int) ($_POST['max_participants'] ?? 0) ?: null,
                'response_deadline' => ($_POST['response_deadline'] ?? '') ? date('Y-m-d H:i:s', strtotime((string) $_POST['response_deadline'])) : null,
                'reminder_at' => ($_POST['reminder_at'] ?? '') ? date('Y-m-d H:i:s', strtotime((string) $_POST['reminder_at'])) : null,
                'recurrence_rule' => in_array($_POST['recurrence_rule'] ?? '', ['none', 'weekly', 'biweekly', 'monthly'], true) ? $_POST['recurrence_rule'] : 'none',
                'status_name' => in_array($_POST['status_name'] ?? '', ['draft', 'published', 'cancelled', 'completed'], true) ? $_POST['status_name'] : 'published',
                'leader_id' => $leaderId,
            ];

            if ($id > 0) {
                $assignments = implode(', ', array_map(static fn(string $name): string => $name . '=?', array_keys($fields)));
                $stmt = db()->prepare('UPDATE events SET ' . $assignments . ' WHERE id=? AND tenant_id=?');
                $stmt->execute([...array_values($fields), $id, $tenantId]);
                audit('update', 'events', $id, 'Dienst aktualisiert: ' . $title);
            } else {
                $columns = implode(',', array_keys($fields));
                $placeholders = implode(',', array_fill(0, count($fields), '?'));
                $stmt = db()->prepare('INSERT INTO events (' . $columns . ',tenant_id,created_by) VALUES (' . $placeholders . ',?,?)');
                $stmt->execute([...array_values($fields), $tenantId, (int) $user['id']]);
                $id = (int) db()->lastInsertId();
                audit('create', 'events', $id, 'Dienst angelegt: ' . $title);

                $recurrence = $fields['recurrence_rule'];
                if ($recurrence !== 'none') {
                    $interval = ['weekly' => '+1 week', 'biweekly' => '+2 weeks', 'monthly' => '+1 month'][$recurrence];
                    $currentStart = new DateTimeImmutable($fields['starts_at']);
                    $currentEnd = new DateTimeImmutable($fields['ends_at']);
                    $copy = db()->prepare(
                        'INSERT INTO events (title,event_type,starts_at,ends_at,location_name,description_text,learning_goals,material_needed,max_participants,response_deadline,reminder_at,recurrence_rule,status_name,leader_id,tenant_id,created_by)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                    );
                    for ($occurrence = 1; $occurrence <= 5; $occurrence++) {
                        $currentStart = $currentStart->modify($interval);
                        $currentEnd = $currentEnd->modify($interval);
                        $copy->execute([
                            $fields['title'], $fields['event_type'], $currentStart->format('Y-m-d H:i:s'), $currentEnd->format('Y-m-d H:i:s'),
                            $fields['location_name'], $fields['description_text'], $fields['learning_goals'], $fields['material_needed'],
                            $fields['max_participants'], null, null, $recurrence, $fields['status_name'], $fields['leader_id'], $tenantId, (int) $user['id']
                        ]);
                    }
                }
            }
            flash('success', 'Dienstplanung wurde gespeichert.');
            redirect('?page=events');
        }

        if ($action === 'event_delete') {
            $id = (int) ($_POST['id'] ?? 0);
            db()->prepare('DELETE FROM events WHERE id=? AND tenant_id=?')->execute([$id, $tenantId]);
            audit('delete', 'events', $id, 'Dienst gelöscht');
            flash('success', 'Dienst wurde gelöscht.');
            redirect('?page=events');
        }

        if ($action === 'guardian_save') {
            $memberId = (int) ($_POST['member_id'] ?? 0);
            $check = db()->prepare('SELECT id FROM members WHERE id=? AND tenant_id=?');
            $check->execute([$memberId, $tenantId]);
            if (!$check->fetchColumn()) {
                throw new RuntimeException('Mitglied wurde nicht gefunden.');
            }
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            if ($fullName === '') {
                throw new RuntimeException('Der Name des Sorgeberechtigten ist erforderlich.');
            }
            $stmt = db()->prepare(
                'INSERT INTO member_guardians (tenant_id,member_id,full_name,relationship_name,email,phone,is_primary,is_emergency_contact,is_pickup_authorized)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $tenantId, $memberId, $fullName, trim((string) ($_POST['relationship_name'] ?? '')) ?: null,
                trim((string) ($_POST['guardian_email'] ?? '')) ?: null, trim((string) ($_POST['guardian_phone'] ?? '')) ?: null,
                (int) ($_POST['is_primary'] ?? 0), (int) ($_POST['is_emergency_contact'] ?? 0), (int) ($_POST['is_pickup_authorized'] ?? 0)
            ]);
            audit('create', 'member_guardians', (int) db()->lastInsertId(), 'Sorgeberechtigten hinterlegt');
            flash('success', 'Sorgeberechtigter wurde gespeichert.');
            redirect('?page=members&member_id=' . $memberId);
        }

        if ($action === 'guardian_delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $memberId = (int) ($_POST['member_id'] ?? 0);
            db()->prepare('DELETE FROM member_guardians WHERE id=? AND member_id=? AND tenant_id=?')->execute([$id, $memberId, $tenantId]);
            audit('delete', 'member_guardians', $id, 'Sorgeberechtigten entfernt');
            flash('success', 'Kontakt wurde entfernt.');
            redirect('?page=members&member_id=' . $memberId);
        }

        if ($action === 'consent_save') {
            $memberId = (int) ($_POST['member_id'] ?? 0);
            $check = db()->prepare('SELECT id FROM members WHERE id=? AND tenant_id=?');
            $check->execute([$memberId, $tenantId]);
            if (!$check->fetchColumn()) {
                throw new RuntimeException('Mitglied wurde nicht gefunden.');
            }
            $type = in_array($_POST['consent_type'] ?? '', ['privacy','photo','swimming','trip','pickup','medical','other'], true) ? $_POST['consent_type'] : 'other';
            $status = in_array($_POST['consent_status'] ?? '', ['open','granted','declined','expired','revoked'], true) ? $_POST['consent_status'] : 'open';
            $title = trim((string) ($_POST['consent_title'] ?? ''));
            if ($title === '') {
                throw new RuntimeException('Die Bezeichnung der Einwilligung ist erforderlich.');
            }
            $stmt = db()->prepare(
                'INSERT INTO member_consents (tenant_id,member_id,consent_type,title,consent_status,granted_at,expires_at,document_reference,note_text,updated_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $tenantId, $memberId, $type, $title, $status, ($_POST['granted_at'] ?? '') ?: null,
                ($_POST['expires_at'] ?? '') ?: null, trim((string) ($_POST['document_reference'] ?? '')) ?: null,
                trim((string) ($_POST['consent_note'] ?? '')) ?: null, (int) $user['id']
            ]);
            audit('create', 'member_consents', (int) db()->lastInsertId(), 'Einwilligung dokumentiert: ' . $title);
            flash('success', 'Einwilligung wurde dokumentiert.');
            redirect('?page=members&member_id=' . $memberId);
        }

        if ($action === 'consent_delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $memberId = (int) ($_POST['member_id'] ?? 0);
            db()->prepare('DELETE FROM member_consents WHERE id=? AND member_id=? AND tenant_id=?')->execute([$id, $memberId, $tenantId]);
            audit('delete', 'member_consents', $id, 'Einwilligung entfernt');
            flash('success', 'Einwilligung wurde entfernt.');
            redirect('?page=members&member_id=' . $memberId);
        }

        if ($action === 'response_save') {
            $eventId = (int) ($_POST['event_id'] ?? 0);
            $check = db()->prepare('SELECT id FROM events WHERE id=? AND tenant_id=?');
            $check->execute([$eventId, $tenantId]);
            if (!$check->fetchColumn()) {
                throw new RuntimeException('Der ausgewählte Dienst wurde nicht gefunden.');
            }
            $stmt = db()->prepare(
                'INSERT INTO event_responses (tenant_id,event_id,member_id,response_status,responded_at,recorded_by)
                 VALUES (?,?,?,?,NOW(),?)
                 ON DUPLICATE KEY UPDATE response_status=VALUES(response_status), responded_at=NOW(), recorded_by=VALUES(recorded_by)'
            );
            $memberCheck = db()->prepare('SELECT id FROM members WHERE id=? AND tenant_id=? AND status_name=\'active\'');
            foreach ((array) ($_POST['responses'] ?? []) as $memberId => $status) {
                $memberId = (int) $memberId;
                $memberCheck->execute([$memberId, $tenantId]);
                if ($memberCheck->fetchColumn() && in_array($status, ['yes', 'no', 'maybe', 'open'], true)) {
                    $stmt->execute([$tenantId, $eventId, $memberId, $status, (int) $user['id']]);
                }
            }
            audit('update', 'event_responses', $eventId, 'Zu- und Absagen erfasst');
            flash('success', 'Rückmeldungen wurden gespeichert.');
            redirect('?page=attendance&event_id=' . $eventId);
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
            $memberCheck = db()->prepare('SELECT id FROM members WHERE id=? AND tenant_id=? AND status_name=\'active\'');
            foreach ((array) ($_POST['attendance'] ?? []) as $memberId => $status) {
                $memberId = (int) $memberId;
                $memberCheck->execute([$memberId, $tenantId]);
                if ($memberCheck->fetchColumn() && in_array($status, ['present', 'excused', 'absent', 'unknown'], true)) {
                    $stmt->execute([$tenantId, $eventId, $memberId, $status, (int) $user['id']]);
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
$leaderStmt = db()->prepare("SELECT id,first_name,last_name FROM users WHERE tenant_id=? AND is_active=1 AND role IN ('admin','leader','staff') ORDER BY last_name,first_name");
$leaderStmt->execute([$tenantId]);
$leaders = $leaderStmt->fetchAll();
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
    $consentStmt = db()->prepare("SELECT COUNT(*) FROM member_consents WHERE tenant_id=? AND (consent_status<>'granted' OR (expires_at IS NOT NULL AND expires_at<CURDATE()))");
    $consentStmt->execute([$tenantId]);
    $consentAlertCount = (int) $consentStmt->fetchColumn();
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
<div class="metric <?= $consentAlertCount ? 'metric-alert' : '' ?>"><div class="metric-icon"><?= icon('file') ?></div><div><strong><?= $consentAlertCount ?></strong><span>Einwilligungen prüfen</span></div></div>
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
    $stmt = db()->prepare("SELECT m.*,
        (SELECT COUNT(*) FROM member_guardians g WHERE g.member_id=m.id) AS guardian_count,
        (SELECT COUNT(*) FROM member_consents c WHERE c.member_id=m.id AND (c.consent_status<>'granted' OR (c.expires_at IS NOT NULL AND c.expires_at<CURDATE()))) AS consent_alerts
        FROM members m WHERE m.tenant_id=? ORDER BY m.status_name,m.last_name,m.first_name");
    $stmt->execute([$tenantId]);
    $members = $stmt->fetchAll();
    $selectedMemberId = (int) ($_GET['member_id'] ?? 0);
    $selectedMember = null; $guardians = []; $consents = [];
    if ($selectedMemberId) {
        $detailStmt = db()->prepare('SELECT * FROM members WHERE id=? AND tenant_id=?');
        $detailStmt->execute([$selectedMemberId,$tenantId]);
        $selectedMember = $detailStmt->fetch() ?: null;
        if ($selectedMember) {
            $gStmt = db()->prepare('SELECT * FROM member_guardians WHERE member_id=? AND tenant_id=? ORDER BY is_primary DESC,full_name');
            $gStmt->execute([$selectedMemberId,$tenantId]); $guardians = $gStmt->fetchAll();
            $cStmt = db()->prepare('SELECT * FROM member_consents WHERE member_id=? AND tenant_id=? ORDER BY expires_at IS NULL,expires_at,consent_type');
            $cStmt->execute([$selectedMemberId,$tenantId]); $consents = $cStmt->fetchAll();
        }
    }
?>
<section class="panel"><div class="table-wrap"><table class="data-table"><thead><tr><th>Mitglied</th><th>Kontakt & Anschrift</th><th>Sorgeberechtigte</th><th>Einwilligungen</th><th>Status</th><th></th></tr></thead><tbody>
<?php if (!$members): ?><tr><td colspan="6"><div class="empty">Noch keine Mitglieder angelegt.</div></td></tr><?php endif; ?>
<?php foreach ($members as $member):
$payload = e(json_encode([
'id'=>$member['id'],'first_name'=>$member['first_name'],'last_name'=>$member['last_name'],'birth_date'=>$member['birth_date'],'entry_date'=>$member['entry_date'],'member_type'=>$member['member_type'],'status_name'=>$member['status_name'],'email'=>$member['email'],'phone'=>$member['phone'],'address_street'=>$member['address_street'],'postal_code'=>$member['postal_code'],'city'=>$member['city'],'school_name'=>$member['school_name'],'shirt_size'=>$member['shirt_size'],'pants_size'=>$member['pants_size'],'shoe_size'=>$member['shoe_size'],'pickup_authorized'=>$member['pickup_authorized'],'emergency_name'=>$member['emergency_name'],'emergency_phone'=>$member['emergency_phone'],'medical_notes'=>$member['medical_notes'],'notes_text'=>$member['notes_text']
], JSON_UNESCAPED_UNICODE));
?>
<tr><td><strong><?= e($member['last_name'].', '.$member['first_name']) ?></strong><small><?= e(format_date($member['birth_date'])) ?> · <?= $member['member_type']==='staff'?'Betreuung':'Jugend' ?></small></td>
<td><?= e(trim(($member['address_street'] ?: '').' '.($member['postal_code'] ?: '').' '.($member['city'] ?: '')) ?: '–') ?><small><?= e($member['phone'] ?: $member['email'] ?: 'Kein Kontakt') ?></small></td>
<td><strong><?= (int)$member['guardian_count'] ?></strong><small>hinterlegt</small></td>
<td><?php if((int)$member['consent_alerts']): ?><span class="status status-cancelled"><?= (int)$member['consent_alerts'] ?> offen</span><?php else: ?><span class="status status-active">vollständig</span><?php endif; ?></td>
<td><span class="status status-<?= e($member['status_name']) ?>"><?= e(status_label($member['status_name'])) ?></span></td>
<td><div class="row-actions"><a class="btn btn-secondary btn-small" href="?page=members&amp;member_id=<?= (int)$member['id'] ?>">Akte öffnen</a><?php if(Auth::canManage()): ?><button class="btn btn-secondary btn-small" data-dialog-open="member-dialog" data-payload="<?= $payload ?>">Bearbeiten</button>
<form method="post" data-confirm="Mitglied wirklich löschen?"><?= csrf_field() ?><input type="hidden" name="action" value="member_delete"><input type="hidden" name="id" value="<?= (int)$member['id'] ?>"><button class="btn btn-danger btn-small">Löschen</button></form><?php endif; ?></div></td></tr><?php endforeach; ?>
</tbody></table></div></section>

<?php if($selectedMember): ?>
<div class="record-grid">
<section class="panel"><div class="panel-head"><div><p class="eyebrow">Mitgliederakte</p><h2><?= e($selectedMember['first_name'].' '.$selectedMember['last_name']) ?></h2></div><a class="btn btn-secondary btn-small" href="?page=members">Schließen</a></div><div class="panel-body record-summary">
<div><span>Adresse</span><strong><?= e(trim(($selectedMember['address_street'] ?: '').', '.($selectedMember['postal_code'] ?: '').' '.($selectedMember['city'] ?: ''), ', ') ?: 'Nicht hinterlegt') ?></strong></div>
<div><span>Schule</span><strong><?= e($selectedMember['school_name'] ?: 'Nicht hinterlegt') ?></strong></div>
<div><span>Größen</span><strong>Shirt <?= e($selectedMember['shirt_size'] ?: '–') ?> · Hose <?= e($selectedMember['pants_size'] ?: '–') ?> · Schuhe <?= e($selectedMember['shoe_size'] ?: '–') ?></strong></div>
<div><span>Notfallkontakt</span><strong><?= e($selectedMember['emergency_name'] ?: 'Nicht hinterlegt') ?><?= $selectedMember['emergency_phone'] ? ' · '.e($selectedMember['emergency_phone']) : '' ?></strong></div>
<?php if(Auth::canManage() && $selectedMember['medical_notes']): ?><div class="record-wide"><span>Medizinische Hinweise</span><strong><?= nl2br(e($selectedMember['medical_notes'])) ?></strong></div><?php endif; ?>
</div></section>

<section class="panel"><div class="panel-head"><h2>Sorgeberechtigte</h2></div><div class="panel-body">
<div class="consent-list"><?php foreach($guardians as $guardian): ?><article class="consent-card"><div><strong><?= e($guardian['full_name']) ?></strong><small><?= e($guardian['relationship_name'] ?: 'Kontaktperson') ?><?= $guardian['is_primary'] ? ' · Hauptkontakt' : '' ?><?= $guardian['is_pickup_authorized'] ? ' · Abholberechtigt' : '' ?></small><small><?= e($guardian['phone'] ?: '') ?><?= $guardian['email'] ? ' · '.e($guardian['email']) : '' ?></small></div><?php if(Auth::canManage()): ?><form method="post" data-confirm="Kontakt löschen?"><?= csrf_field() ?><input type="hidden" name="action" value="guardian_delete"><input type="hidden" name="id" value="<?= (int)$guardian['id'] ?>"><input type="hidden" name="member_id" value="<?= $selectedMemberId ?>"><button class="btn btn-danger btn-small">Löschen</button></form><?php endif; ?></article><?php endforeach; ?><?php if(!$guardians): ?><div class="empty">Noch kein Kontakt hinterlegt.</div><?php endif; ?></div>
<?php if(Auth::canManage()): ?><form method="post" class="form-grid compact-form"><?= csrf_field() ?><input type="hidden" name="action" value="guardian_save"><input type="hidden" name="member_id" value="<?= $selectedMemberId ?>">
<div class="field"><label>Name *</label><input name="full_name" required></div><div class="field"><label>Beziehung</label><input name="relationship_name" placeholder="z. B. Mutter"></div><div class="field"><label>Telefon</label><input name="guardian_phone"></div><div class="field"><label>E-Mail</label><input name="guardian_email" type="email"></div>
<div class="field field-full checkbox-row"><label><input type="checkbox" name="is_primary" value="1"> Hauptkontakt</label><label><input type="checkbox" name="is_emergency_contact" value="1"> Notfallkontakt</label><label><input type="checkbox" name="is_pickup_authorized" value="1"> Abholberechtigt</label></div><div class="field field-full"><button class="btn btn-primary">Kontakt hinzufügen</button></div></form><?php endif; ?>
</div></section>

<section class="panel"><div class="panel-head"><h2>Einwilligungen</h2></div><div class="panel-body">
<div class="consent-list"><?php foreach($consents as $consent):
$consentClass = $consent['consent_status']==='granted' && (!$consent['expires_at'] || $consent['expires_at']>=date('Y-m-d')) ? 'active' : 'cancelled';
?><article class="consent-card"><div><strong><?= e($consent['title']) ?></strong><small><?= e(ucfirst($consent['consent_status'])) ?><?= $consent['expires_at'] ? ' · gültig bis '.e(format_date($consent['expires_at'])) : '' ?></small><?php if($consent['document_reference']): ?><small>Dokument: <?= e($consent['document_reference']) ?></small><?php endif; ?></div><span class="status status-<?= $consentClass ?>"><?= $consentClass==='active'?'OK':'Prüfen' ?></span><?php if(Auth::canManage()): ?><form method="post" data-confirm="Einwilligung löschen?"><?= csrf_field() ?><input type="hidden" name="action" value="consent_delete"><input type="hidden" name="id" value="<?= (int)$consent['id'] ?>"><input type="hidden" name="member_id" value="<?= $selectedMemberId ?>"><button class="btn btn-danger btn-small">Löschen</button></form><?php endif; ?></article><?php endforeach; ?><?php if(!$consents): ?><div class="empty">Noch keine Einwilligungen erfasst.</div><?php endif; ?></div>
<?php if(Auth::canManage()): ?><form method="post" class="form-grid compact-form"><?= csrf_field() ?><input type="hidden" name="action" value="consent_save"><input type="hidden" name="member_id" value="<?= $selectedMemberId ?>">
<div class="field"><label>Titel *</label><input name="consent_title" required placeholder="z. B. Fotoerlaubnis"></div><div class="field"><label>Typ</label><select name="consent_type"><option value="photo">Foto/Video</option><option value="privacy">Datenschutz</option><option value="medical">Medizinisch</option><option value="trip">Ausflug</option><option value="other">Sonstiges</option></select></div>
<div class="field"><label>Status</label><select name="consent_status"><option value="granted">Erteilt</option><option value="open">Offen</option><option value="declined">Abgelehnt</option><option value="revoked">Widerrufen</option></select></div><div class="field"><label>Erteilt am</label><input name="granted_at" type="date"></div><div class="field"><label>Gültig bis</label><input name="expires_at" type="date"></div><div class="field"><label>Dokument/Referenz</label><input name="document_reference"></div><div class="field field-full"><label>Notiz</label><textarea name="consent_note"></textarea></div><div class="field field-full"><button class="btn btn-primary">Einwilligung hinzufügen</button></div></form><?php endif; ?>
</div></section>
</div>
<?php endif; ?>

<?php elseif ($page === 'events'):
    $stmt = db()->prepare("SELECT e.*,CONCAT(u.first_name,' ',u.last_name) AS leader_name,
        (SELECT COUNT(*) FROM event_responses r WHERE r.event_id=e.id AND r.response_status='yes') AS yes_count,
        (SELECT COUNT(*) FROM event_responses r WHERE r.event_id=e.id AND r.response_status='no') AS no_count,
        (SELECT COUNT(*) FROM event_responses r WHERE r.event_id=e.id AND r.response_status='maybe') AS maybe_count
        FROM events e LEFT JOIN users u ON u.id=e.leader_id WHERE e.tenant_id=? ORDER BY e.starts_at DESC");
    $stmt->execute([$tenantId]);
    $events = $stmt->fetchAll();
?>
<section class="panel"><div class="table-wrap"><table class="data-table"><thead><tr><th>Dienst</th><th>Termin & Ort</th><th>Rückmeldungen</th><th>Leitung</th><th>Status</th><th></th></tr></thead><tbody>
<?php if (!$events): ?><tr><td colspan="6"><div class="empty">Noch keine Dienste oder Übungen angelegt.</div></td></tr><?php endif; ?>
<?php foreach ($events as $event):
$payload = e(json_encode(['id'=>$event['id'],'title'=>$event['title'],'event_type'=>$event['event_type'],'starts_at'=>date('Y-m-d\TH:i',strtotime($event['starts_at'])),'ends_at'=>date('Y-m-d\TH:i',strtotime($event['ends_at'])),'location_name'=>$event['location_name'],'description_text'=>$event['description_text'],'learning_goals'=>$event['learning_goals'],'material_needed'=>$event['material_needed'],'max_participants'=>$event['max_participants'],'response_deadline'=>$event['response_deadline'] ? date('Y-m-d\TH:i',strtotime($event['response_deadline'])) : '','reminder_at'=>$event['reminder_at'] ? date('Y-m-d\TH:i',strtotime($event['reminder_at'])) : '','recurrence_rule'=>$event['recurrence_rule'],'leader_id'=>$event['leader_id'],'status_name'=>$event['status_name']], JSON_UNESCAPED_UNICODE));
?>
<tr><td><strong><?= e($event['title']) ?></strong><small><?= e(event_type_label($event['event_type'])) ?><?= $event['learning_goals'] ? ' · Lernziel hinterlegt' : '' ?></small></td>
<td><?= e(format_date($event['starts_at'],true)) ?> Uhr<small><?= e($event['location_name'] ?: 'Kein Ort') ?></small></td>
<td><div class="response-stats"><span class="response-yes">✓ <?= (int)$event['yes_count'] ?></span><span class="response-no">× <?= (int)$event['no_count'] ?></span><span class="response-maybe">? <?= (int)$event['maybe_count'] ?></span></div><?php if($event['response_deadline']): ?><small>Frist <?= e(format_date($event['response_deadline'],true)) ?></small><?php endif; ?></td>
<td><?= e($event['leader_name'] ?: 'Noch offen') ?></td>
<td><span class="status status-<?= e($event['status_name']) ?>"><?= e(status_label($event['status_name'])) ?></span></td>
<td><div class="row-actions"><a class="btn btn-secondary btn-small" href="?page=attendance&amp;event_id=<?= (int)$event['id'] ?>">Rückmeldungen</a><?php if(Auth::canManage()): ?><button class="btn btn-secondary btn-small" data-dialog-open="event-dialog" data-payload="<?= $payload ?>">Bearbeiten</button><form method="post" data-confirm="Dienst wirklich löschen?"><?= csrf_field() ?><input type="hidden" name="action" value="event_delete"><input type="hidden" name="id" value="<?= (int)$event['id'] ?>"><button class="btn btn-danger btn-small">Löschen</button></form><?php endif; ?></div></td></tr><?php endforeach; ?>
</tbody></table></div></section>

<?php elseif ($page === 'attendance'):
    $eventStmt = db()->prepare('SELECT id,title,starts_at,response_deadline FROM events WHERE tenant_id=? ORDER BY starts_at DESC LIMIT 30');
    $eventStmt->execute([$tenantId]);
    $eventOptions = $eventStmt->fetchAll();
    $selectedEventId = (int) ($_GET['event_id'] ?? ($eventOptions[0]['id'] ?? 0));
?>
<div class="toolbar"><form method="get"><input type="hidden" name="page" value="attendance"><div class="field"><label for="event-select">Dienst auswählen</label><select id="event-select" name="event_id" onchange="this.form.submit()"><?php foreach($eventOptions as $option): ?><option value="<?= (int)$option['id'] ?>" <?= $selectedEventId===(int)$option['id']?'selected':'' ?>><?= e(format_date($option['starts_at']).' · '.$option['title']) ?></option><?php endforeach; ?></select></div></form></div>
<?php if (!$selectedEventId): ?><section class="panel"><div class="empty">Legen Sie zuerst einen Dienst an.</div></section>
<?php else:
$stmt = db()->prepare("SELECT m.id,m.first_name,m.last_name,COALESCE(a.attendance_status,'unknown') AS attendance_status,COALESCE(r.response_status,'open') AS response_status FROM members m LEFT JOIN attendance a ON a.member_id=m.id AND a.event_id=? LEFT JOIN event_responses r ON r.member_id=m.id AND r.event_id=? WHERE m.tenant_id=? AND m.status_name='active' ORDER BY m.member_type,m.last_name,m.first_name");
$stmt->execute([$selectedEventId,$selectedEventId,$tenantId]);
$attendanceRows=$stmt->fetchAll();
?>
<div class="split-grid">
<form method="post"><input type="hidden" name="action" value="response_save"><input type="hidden" name="event_id" value="<?= $selectedEventId ?>"><?= csrf_field() ?>
<section class="panel"><div class="panel-head"><div><p class="eyebrow">Vor dem Dienst</p><h2>Zu- und Absagen</h2></div><?php if(Auth::canManage()): ?><button class="btn btn-primary" type="submit">Rückmeldungen speichern</button><?php endif; ?></div><div class="panel-body attendance-list">
<?php foreach($attendanceRows as $row): ?><div class="attendance-row"><strong><?= e($row['last_name'].', '.$row['first_name']) ?></strong>
<?php foreach(['yes'=>'Zusage','no'=>'Absage','maybe'=>'Vielleicht','open'=>'Offen'] as $value=>$label): ?><label class="choice"><input type="radio" name="responses[<?= (int)$row['id'] ?>]" value="<?= $value ?>" <?= $row['response_status']===$value?'checked':'' ?> <?= Auth::canManage()?'':'disabled' ?>><span><?= $label ?></span></label><?php endforeach; ?>
</div><?php endforeach; ?><?php if(!$attendanceRows): ?><div class="empty">Keine aktiven Mitglieder vorhanden.</div><?php endif; ?>
</div></section></form>

<form method="post"><input type="hidden" name="action" value="attendance_save"><input type="hidden" name="event_id" value="<?= $selectedEventId ?>"><?= csrf_field() ?>
<section class="panel"><div class="panel-head"><div><p class="eyebrow">Beim Dienst</p><h2>Anwesenheit</h2></div><?php if(Auth::canManage()): ?><button class="btn btn-primary" type="submit">Anwesenheit speichern</button><?php endif; ?></div><div class="panel-body attendance-list">
<?php foreach($attendanceRows as $row): ?><div class="attendance-row"><strong><?= e($row['last_name'].', '.$row['first_name']) ?></strong>
<?php foreach(['present'=>'Anwesend','excused'=>'Entschuldigt','absent'=>'Fehlt','unknown'=>'Offen'] as $value=>$label): ?><label class="choice"><input type="radio" name="attendance[<?= (int)$row['id'] ?>]" value="<?= $value ?>" <?= $row['attendance_status']===$value?'checked':'' ?> <?= Auth::canManage()?'':'disabled' ?>><span><?= $label ?></span></label><?php endforeach; ?>
</div><?php endforeach; ?>
</div></section></form>
</div><?php endif; ?>

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
<div class="form-section field-full"><span>Person</span></div>
<div class="field"><label>Vorname *</label><input name="first_name" required></div><div class="field"><label>Nachname *</label><input name="last_name" required></div>
<div class="field"><label>Geburtsdatum</label><input name="birth_date" type="date"></div><div class="field"><label>Eintrittsdatum</label><input name="entry_date" type="date"></div>
<div class="field"><label>Mitgliedsart</label><select name="member_type"><option value="youth">Jugendliche/r</option><option value="staff">Betreuer/in</option></select></div><div class="field"><label>Status</label><select name="status_name"><option value="active">Aktiv</option><option value="paused">Pausiert</option><option value="left">Ausgetreten</option></select></div>
<div class="field"><label>E-Mail</label><input name="email" type="email"></div><div class="field"><label>Telefon</label><input name="phone"></div>
<div class="form-section field-full"><span>Anschrift & Schule</span></div>
<div class="field field-full"><label>Straße und Hausnummer</label><input name="address_street"></div><div class="field"><label>PLZ</label><input name="postal_code"></div><div class="field"><label>Ort</label><input name="city"></div><div class="field field-full"><label>Schule</label><input name="school_name"></div>
<div class="form-section field-full"><span>Kleidergrößen</span></div>
<div class="field"><label>Shirt</label><input name="shirt_size" placeholder="z. B. 164"></div><div class="field"><label>Hose</label><input name="pants_size"></div><div class="field"><label>Schuhe</label><input name="shoe_size"></div><div class="field checkbox-row"><label><input type="checkbox" name="pickup_authorized" value="1"> Allgemein abholberechtigt</label></div>
<div class="form-section field-full"><span>Notfall & interne Angaben</span></div>
<div class="field"><label>Notfallkontakt</label><input name="emergency_name"></div><div class="field"><label>Notfalltelefon</label><input name="emergency_phone"></div>
<div class="field field-full"><label>Medizinische Hinweise</label><textarea name="medical_notes"></textarea></div><div class="field field-full"><label>Interne Notizen</label><textarea name="notes_text"></textarea></div>
</div><div class="dialog-actions"><button type="button" class="btn btn-secondary" data-dialog-close>Abbrechen</button><button class="btn btn-primary">Speichern</button></div></form></dialog>

<dialog id="event-dialog"><form method="post"><div class="dialog-head"><h2>Dienst oder Übung</h2><button type="button" class="dialog-close" data-dialog-close aria-label="Schließen">×</button></div><div class="dialog-body form-grid">
<?= csrf_field() ?><input type="hidden" name="action" value="event_save"><input type="hidden" name="id" value="">
<div class="field field-full"><label>Titel *</label><input name="title" required></div><div class="field"><label>Art</label><select name="event_type"><option value="practice">Übung</option><option value="meeting">Besprechung</option><option value="trip">Ausflug</option><option value="competition">Wettbewerb</option><option value="other">Sonstiges</option></select></div>
<div class="field"><label>Status</label><select name="status_name"><option value="published">Veröffentlicht</option><option value="draft">Entwurf</option><option value="completed">Abgeschlossen</option><option value="cancelled">Abgesagt</option></select></div>
<div class="field"><label>Beginn *</label><input name="starts_at" type="datetime-local" required></div><div class="field"><label>Ende *</label><input name="ends_at" type="datetime-local" required></div>
<div class="field"><label>Ort</label><input name="location_name"></div><div class="field"><label>Verantwortliche Leitung</label><select name="leader_id"><option value="">Noch offen</option><?php foreach($leaders as $leader): ?><option value="<?= (int)$leader['id'] ?>"><?= e($leader['last_name'].', '.$leader['first_name']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label>Max. Teilnehmende</label><input name="max_participants" type="number" min="1"></div><div class="field"><label>Wiederholung</label><select name="recurrence_rule"><option value="none">Keine</option><option value="weekly">Wöchentlich (6 Termine)</option><option value="biweekly">Alle 2 Wochen (6 Termine)</option><option value="monthly">Monatlich (6 Termine)</option></select></div>
<div class="field"><label>Rückmeldefrist</label><input name="response_deadline" type="datetime-local"></div><div class="field"><label>Erinnerung am</label><input name="reminder_at" type="datetime-local"></div>
<div class="field field-full"><label>Beschreibung</label><textarea name="description_text"></textarea></div><div class="field field-full"><label>Lernziele</label><textarea name="learning_goals"></textarea></div><div class="field field-full"><label>Benötigtes Material</label><textarea name="material_needed"></textarea></div>
</div><div class="dialog-actions"><button type="button" class="btn btn-secondary" data-dialog-close>Abbrechen</button><button class="btn btn-primary">Speichern</button></div></form></dialog>

<dialog id="user-dialog"><form method="post"><div class="dialog-head"><h2>Benutzerkonto anlegen</h2><button type="button" class="dialog-close" data-dialog-close aria-label="Schließen">×</button></div><div class="dialog-body form-grid">
<?= csrf_field() ?><input type="hidden" name="action" value="user_save">
<div class="field"><label>Vorname *</label><input name="first_name" required></div><div class="field"><label>Nachname *</label><input name="last_name" required></div>
<div class="field field-full"><label>E-Mail *</label><input name="email" type="email" required></div><div class="field"><label>Rolle</label><select name="role"><option value="staff">Betreuer</option><option value="leader">Jugendwart</option><option value="viewer">Leser</option><option value="admin">Administrator</option></select></div>
<div class="field"><label>Startpasswort *</label><input name="password" type="password" minlength="10" required></div>
</div><div class="dialog-actions"><button type="button" class="btn btn-secondary" data-dialog-close>Abbrechen</button><button class="btn btn-primary">Benutzer anlegen</button></div></form></dialog>
</body></html>
