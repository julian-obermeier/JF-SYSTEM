<?php declare(strict_types=1); ?>
<header class="page-header">
    <div><h1>Anwesenheit</h1><p>Rückmeldungen je Dienst prüfen und direkt nachführen.</p></div>
</header>
<section class="panel">
    <header class="panel-header"><div><h2>Dienst auswählen</h2><span><?= count($events) ?> Dienste</span></div>
        <form method="get"><label class="sr-only" for="attendance-event">Dienst</label><select id="attendance-event" name="event" data-auto-submit>
            <?php foreach ($events as $event): ?><option value="<?= (int) $event['id'] ?>" <?= (int) ($selectedEvent['id'] ?? 0) === (int) $event['id'] ? 'selected' : '' ?>><?= e(german_date(substr($event['starts_at'], 0, 10))) ?> · <?= e($event['title']) ?></option><?php endforeach; ?>
        </select></form>
    </header>
    <?php if (!$selectedEvent): ?><div class="empty-state"><h3>Noch kein Dienst vorhanden</h3><p>Lege zuerst unter „Dienste & Übungen“ einen Termin an.</p></div>
    <?php else: ?><div class="attendance-summary"><strong><?= e($selectedEvent['title']) ?></strong><span><?= e(german_date(substr($selectedEvent['starts_at'], 0, 10))) ?> · <?= e(substr($selectedEvent['starts_at'], 11, 5)) ?> Uhr</span></div>
    <div class="table-wrap"><table><thead><tr><th>Mitglied</th><th>Typ</th><th>Rückmeldung</th><th>Speichern</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?><tr><td><strong><?= e($row['last_name'] . ', ' . $row['first_name']) ?></strong></td><td><?= e($row['member_type'] === 'staff' ? 'Betreuung' : 'Jugendliche/r') ?></td><td><form id="attendance-<?= (int) $row['id'] ?>" method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int) $selectedEvent['id'] ?>"><input type="hidden" name="member_id" value="<?= (int) $row['id'] ?>"><select name="status"><option value="open" <?= $row['response_status'] === 'open' ? 'selected' : '' ?>>Offen</option><option value="yes" <?= $row['response_status'] === 'yes' ? 'selected' : '' ?>>Zusagt</option><option value="no" <?= $row['response_status'] === 'no' ? 'selected' : '' ?>>Abgesagt</option><option value="maybe" <?= $row['response_status'] === 'maybe' ? 'selected' : '' ?>>Vielleicht</option></select></form></td><td><button class="button button-secondary button-small" type="submit" form="attendance-<?= (int) $row['id'] ?>">Speichern</button></td></tr><?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="4" class="empty-cell">Keine aktiven Mitglieder vorhanden.</td></tr><?php endif; ?></tbody></table></div><?php endif; ?>
</section>
