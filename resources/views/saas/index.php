<?php declare(strict_types=1); ?>
<header class="page-header">
    <div><h1>SaaS-Verwaltung</h1><p>Mandanten, Tarife und Laufzeiten zentral im Blick.</p></div>
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
