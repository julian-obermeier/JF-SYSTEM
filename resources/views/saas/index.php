<?php declare(strict_types=1); ?>
<header class="page-header">
    <div><h1>SaaS-Verwaltung</h1><p>Mandanten, Tarife und Laufzeiten zentral im Blick.</p></div>
    <button class="button button-primary" type="button" data-dialog-open="new-tenant-dialog">Mandant anlegen</button>
</header>
<section class="panel">
    <header class="panel-header"><h2>Mandanten</h2><span><?= count($organizations) ?> Organisationen</span></header>
    <div class="table-wrap"><table>
        <thead><tr><th>Organisation</th><th>Nutzung</th><th>Tarif</th><th>Status</th><th>Abrechnung</th><th>Testende</th><th>Aktion</th></tr></thead>
        <tbody>
        <?php foreach ($organizations as $organization):
            $formId = 'subscription-' . (int) $organization['id'];
            $currentStatus = (string) ($organization['status_name'] ?? 'trial');
            $currentCycle = (string) ($organization['billing_cycle'] ?? 'manual');
        ?>
        <tr>
            <td><strong><?= e($organization['name']) ?></strong><br><small><?= e($organization['city'] ?: $organization['slug']) ?></small></td>
            <td><?= (int) $organization['member_count'] ?> Mitglieder<br><?= (int) $organization['user_count'] ?> Benutzer</td>
            <td><select name="plan_id" form="<?= e($formId) ?>" aria-label="Tarif für <?= e($organization['name']) ?>" required>
                <?php foreach ($plans as $plan): ?><option value="<?= (int) $plan['id'] ?>" <?= (int) $organization['plan_id'] === (int) $plan['id'] ? 'selected' : '' ?>><?= e($plan['name']) ?></option><?php endforeach; ?>
            </select></td>
            <td><select name="status" form="<?= e($formId) ?>" aria-label="Status für <?= e($organization['name']) ?>">
                <?php foreach (['trial'=>'Testphase','active'=>'Aktiv','past_due'=>'Zahlung offen','paused'=>'Pausiert','cancelled'=>'Gekündigt','expired'=>'Abgelaufen'] as $key=>$label): ?>
                    <option value="<?= e($key) ?>" <?= $currentStatus === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select></td>
            <td><select name="cycle" form="<?= e($formId) ?>" aria-label="Abrechnungszyklus für <?= e($organization['name']) ?>">
                <?php foreach (['manual'=>'Manuell','monthly'=>'Monatlich','yearly'=>'Jährlich'] as $key=>$label): ?>
                    <option value="<?= e($key) ?>" <?= $currentCycle === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select></td>
            <td><input type="date" name="trial_ends_at" form="<?= e($formId) ?>" value="<?= e($organization['trial_ends_at'] ?? '') ?>" aria-label="Testende für <?= e($organization['name']) ?>"></td>
            <td><form id="<?= e($formId) ?>" method="post" action="/saas"><?= csrf_field() ?><input type="hidden" name="tenant_id" value="<?= (int) $organization['id'] ?>"><button class="button button-secondary" type="submit">Speichern</button></form></td>
        </tr>
        <?php endforeach; ?>
        <?php if ($organizations === []): ?><tr><td colspan="7" class="empty-cell">Noch keine Mandanten vorhanden.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>
<p class="saas-hint">Diese Stufe verwaltet manuelle Tarif- und Abostände. Zahlungen, Rechnungen und automatische Tariflimits sind noch nicht aktiviert.</p>
<dialog class="modal" id="new-tenant-dialog">
    <form class="modal-card" action="/saas" method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="create_tenant">
        <header><div><h2>Mandant anlegen</h2><p>Ein Admin-Konto und eine 30-Tage-Testphase werden angelegt.</p></div><button type="button" class="icon-button" data-dialog-close aria-label="Schließen"><svg><use href="#i-close"/></svg></button></header>
        <div class="form-grid">
            <label class="span-2">Organisation<input name="organization" maxlength="180" required></label>
            <label>Kurzname für spätere URL<input name="slug" pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="120" placeholder="jugendfeuerwehr-erda" required></label>
            <label>Ort<input name="city" maxlength="120"></label>
            <label class="span-2">Tarif<select name="plan_id" required><?php foreach ($plans as $plan): ?><option value="<?= (int) $plan['id'] ?>"><?= e($plan['name']) ?></option><?php endforeach; ?></select></label>
            <label>Vorname Admin<input name="first_name" maxlength="100" required></label>
            <label>Nachname Admin<input name="last_name" maxlength="100" required></label>
            <label class="span-2">E-Mail Admin<input name="email" type="email" maxlength="190" autocomplete="off" required></label>
            <label class="span-2">Startpasswort für neue Konten<input name="password" type="password" minlength="12" autocomplete="new-password" placeholder="Nur bei neuer E-Mail erforderlich"></label>
        </div>
        <p class="saas-hint">Ist die E-Mail bereits im System vorhanden, wird das bestehende Konto zugeordnet; sein Passwort bleibt unverändert. Bei neuen Konten das Startpasswort vertraulich an den Admin übergeben. E-Mail-Bestätigung und Passwort-Reset folgen später.</p>
        <footer><button type="button" class="button button-secondary" data-dialog-close>Abbrechen</button><button type="submit" class="button button-primary">Mandant erstellen</button></footer>
    </form>
</dialog>
