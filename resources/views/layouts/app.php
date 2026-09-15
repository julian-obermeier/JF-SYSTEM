<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · JF-SYSTEM v2</title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
<svg class="svg-sprite" aria-hidden="true">
    <symbol id="i-home" viewBox="0 0 24 24"><path d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></symbol>
    <symbol id="i-users" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8m13 10v-2a4 4 0 0 0-3-3.87m-2-12a4 4 0 0 1 0 7.75"/></symbol>
    <symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.7 2.7L16.5 9"/></symbol>
    <symbol id="i-award" viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="m8.5 13-1 8 4.5-2 4.5 2-1-8"/></symbol>
    <symbol id="i-file" viewBox="0 0 24 24"><path d="M6 2h8l4 4v16H6zM14 2v5h5"/></symbol>
    <symbol id="i-mail" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 7L22 7"/></symbol>
    <symbol id="i-chart" viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20V7"/></symbol>
    <symbol id="i-settings" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6 1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1z"/></symbol>
    <symbol id="i-help" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.2 9a3 3 0 1 1 4.5 2.6c-1 .6-1.7 1.2-1.7 2.4M12 18h.01"/></symbol>
    <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></symbol>
    <symbol id="i-bell" viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></symbol>
    <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
    <symbol id="i-upload" viewBox="0 0 24 24"><path d="M12 16V3m0 0L7 8m5-5 5 5M4 15v5h16v-5"/></symbol>
    <symbol id="i-chevron" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></symbol>
    <symbol id="i-close" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></symbol>
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></symbol>
</svg>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand"><span class="brand-flame">♦</span><span><strong>JF-SYSTEM</strong><small>Stark. Gemeinsam. Zukunft.</small></span></div>
        <nav class="nav" aria-label="Hauptnavigation">
            <?php
            $items = [
                ['dashboard','/','i-home','Übersicht'], ['members','/members','i-users','Mitglieder'],
                ['events','/events','i-calendar','Dienste & Übungen'], ['attendance','/attendance','i-check','Anwesenheit'],
                ['qualifications','#','i-award','Qualifikationen'], ['documents','#','i-file','Dokumente'],
                ['messages','#','i-mail','Nachrichten'], ['reports','#','i-chart','Auswertungen'],
                ['settings','#','i-settings','Einstellungen'],
            ];
            if ((int) ($user['is_superadmin'] ?? 0) === 1) {
                $items[] = ['saas','/saas','i-settings','SaaS-Verwaltung'];
            }
            foreach ($items as [$key,$href,$icon,$label]): ?>
                <a class="nav-link <?= $active === $key ? 'is-active' : '' ?> <?= $href === '#' ? 'is-disabled' : '' ?>" href="<?= e($href) ?>" <?= $href === '#' ? 'aria-disabled="true" data-coming-soon="true"' : '' ?>>
                    <svg><use href="#<?= e($icon) ?>"/></svg><span><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <a class="nav-link nav-help" href="#" data-coming-soon="true"><svg><use href="#i-help"/></svg><span>Hilfe & Support</span></a>
    </aside>
    <div class="workspace">
        <header class="topbar">
            <button class="icon-button mobile-menu" type="button" aria-label="Navigation öffnen" data-sidebar-toggle><svg><use href="#i-menu"/></svg></button>
            <form class="tenant-switch" action="/tenant/select" method="post">
                <?= csrf_field() ?>
                <label class="sr-only" for="tenant-select">Organisation</label>
                <select id="tenant-select" name="tenant_id" data-auto-submit>
                    <?php foreach ($tenants as $availableTenant): ?>
                        <option value="<?= (int) $availableTenant['id'] ?>" <?= (int) $availableTenant['id'] === (int) ($tenant['id'] ?? 0) ? 'selected' : '' ?>><?= e($availableTenant['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <div class="topbar-actions">
                <label class="global-search"><svg><use href="#i-search"/></svg><input type="search" placeholder="Mitglieder, Dienste, Dokumente suchen …" aria-label="Globale Suche"><kbd>Strg K</kbd></label>
                <button type="button" class="icon-button has-dot" aria-label="Benachrichtigungen" data-coming-soon="true"><svg><use href="#i-bell"/></svg></button>
                <button type="button" class="user-menu" data-menu-toggle="user-dropdown"><span class="avatar"><?= e(initials($user['first_name'], $user['last_name'])) ?></span><span><strong><?= e($user['first_name']) ?></strong><small>Jugendwart</small></span><span>⌄</span></button>
                <div class="dropdown" id="user-dropdown" hidden>
                    <form action="/logout" method="post"><?= csrf_field() ?><button type="submit">Abmelden</button></form>
                </div>
            </div>
        </header>
        <main class="main-content">
            <?php if ($flash): ?><div class="flash" role="status"><?= e($flash) ?></div><?php endif; ?>
            <?= $content ?>
        </main>
    </div>
</div>
<div class="toast" id="toast" hidden></div>
</body>
</html>
