<?php declare(strict_types=1); ?>
<header class="page-header"><div><h1>Guten Abend, <?= e($user['first_name']) ?></h1><p>Hier ist der aktuelle Stand deiner Jugendfeuerwehr.</p></div><button class="button button-primary" type="button" data-dialog-open="event-dialog"><svg><use href="#i-plus"/></svg>Dienst anlegen</button></header>

<?php if ($openConsents > 0): ?><div class="notice"><strong><?= (int)$openConsents ?> offene Einwilligungen prüfen</strong><span>Bei einigen Mitgliedern fehlen noch erforderliche Rückmeldungen.</span><a href="/members?status=active">Jetzt prüfen</a><button type="button" aria-label="Hinweis schließen" data-dismiss><svg><use href="#i-close"/></svg></button></div><?php endif; ?>

<section class="metric-row" aria-label="Kennzahlen">
    <article class="metric"><svg><use href="#i-users"/></svg><div><strong><?= (int)$memberCount ?></strong><span>aktive Mitglieder</span><small>Jugendliche und Betreuer</small></div></article>
    <article class="metric"><svg><use href="#i-calendar"/></svg><div><strong><?= (int)$upcomingCount ?></strong><span>kommende Dienste</span><small>in den nächsten Wochen</small></div></article>
    <article class="metric"><svg><use href="#i-chart"/></svg><div><strong><?= (int)$attendanceRate ?> %</strong><span>Anwesenheit</span><small>Durchschnitt der letzten 8 Wochen</small></div></article>
    <article class="metric"><svg><use href="#i-file"/></svg><div><strong><?= (int)$openConsents ?></strong><span>offene Einwilligungen</span><small>noch zu bearbeiten</small></div></article>
</section>

<div class="dashboard-grid">
    <div class="dashboard-main">
        <section class="panel"><header class="panel-header"><h2>Nächste Dienste</h2><a href="/events">Alle Dienste anzeigen →</a></header>
            <div class="table-wrap"><table><thead><tr><th>Datum</th><th>Titel</th><th>Zeit</th><th>Rückmeldungen</th><th>Teilnehmer</th></tr></thead><tbody>
            <?php if ($events === []): ?><tr><td colspan="5" class="empty-cell">Noch keine kommenden Dienste angelegt.</td></tr><?php endif; ?>
            <?php foreach ($events as $event): ?><tr><td><?= e(german_date(substr($event['starts_at'],0,10))) ?></td><td><strong><?= e($event['title']) ?></strong></td><td><?= e(substr($event['starts_at'],11,5)) ?>–<?= e(substr($event['ends_at'],11,5)) ?></td><td><span class="status status-info"><?= (int)$event['accepted'] ?> zugesagt</span></td><td><?= (int)$event['accepted'] ?> / <?= max((int)$memberCount, 1) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </section>
        <section class="panel attendance-panel"><header class="panel-header"><h2>Anwesenheit der letzten 8 Wochen</h2></header><div class="bar-chart" aria-label="Anwesenheitsquote der letzten acht Wochen">
            <?php foreach ($attendanceWeeks as $index => $value): ?><div class="bar-column"><strong><?= (int)$value ?> %</strong><span style="--bar:<?= (int)$value ?>%" class="bar <?= $index === 7 ? 'is-current' : '' ?>"></span><small>KW <?= 37+$index ?></small></div><?php endforeach; ?>
        </div></section>
    </div>
    <aside class="panel today-panel"><header class="panel-header"><h2>Heute im Blick</h2><small><?= e((new DateTimeImmutable())->format('d.m.Y')) ?></small></header>
        <div class="today-section"><h3>Geburtstage</h3><?php if ($birthdays === []): ?><p>Keine Geburtstage hinterlegt.</p><?php endif; ?><?php foreach ($birthdays as $birthday): ?><a href="/members"><span class="mini-icon">♪</span><span><strong><?= e($birthday['first_name'].' '.$birthday['last_name']) ?></strong><small><?= e(age_from_birthdate($birthday['birth_date'])) ?> Jahre</small></span><svg><use href="#i-chevron"/></svg></a><?php endforeach; ?></div>
        <div class="today-section"><h3>Qualifikationen</h3><a href="#" data-coming-soon="true"><span class="mini-icon">✓</span><span><strong>Jugendflamme Stufe 2</strong><small>Nächster Termin in Vorbereitung</small></span><svg><use href="#i-chevron"/></svg></a></div>
        <div class="today-section"><h3>Einwilligungen</h3><a href="/members"><span class="mini-icon warning">!</span><span><strong><?= (int)$openConsents ?> Vorgänge offen</strong><small>Unterlagen prüfen</small></span><svg><use href="#i-chevron"/></svg></a></div>
    </aside>
</div>

<dialog class="modal" id="event-dialog"><form method="post" action="/events" class="modal-card"><?= csrf_field() ?><header><div><h2>Dienst anlegen</h2><p>Plane eine Übung, Besprechung oder Veranstaltung.</p></div><button type="button" class="icon-button" data-dialog-close aria-label="Schließen"><svg><use href="#i-close"/></svg></button></header>
<div class="form-grid"><label class="span-2">Titel<input name="title" required placeholder="z. B. Technische Hilfe – Grundlagen"></label><label>Art<select name="event_type"><option value="practice">Übung</option><option value="meeting">Besprechung</option><option value="trip">Ausflug</option><option value="competition">Wettbewerb</option><option value="other">Sonstiges</option></select></label><label>Ort<input name="location_name" placeholder="Gerätehaus Erda"></label><label>Beginn<input type="datetime-local" name="starts_at" required></label><label>Ende<input type="datetime-local" name="ends_at" required></label><label class="span-2">Beschreibung<textarea name="description_text" rows="4"></textarea></label></div><footer><button type="button" class="button button-secondary" data-dialog-close>Abbrechen</button><button class="button button-primary" type="submit">Dienst speichern</button></footer></form></dialog>
