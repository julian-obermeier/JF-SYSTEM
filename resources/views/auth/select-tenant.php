<?php declare(strict_types=1); ?>
<section class="auth-card"><h1>Organisation auswählen</h1><p>Mit welcher Jugendfeuerwehr möchtest du arbeiten?</p>
<form method="post" action="/tenant/select" class="form-stack"><?= csrf_field() ?>
<label>Organisation<select name="tenant_id" required><?php foreach ($tenants as $tenant): ?><option value="<?= (int)$tenant['id'] ?>"><?= e($tenant['name']) ?></option><?php endforeach; ?></select></label>
<button class="button button-primary button-block">Weiter</button></form></section>
