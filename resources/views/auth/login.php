<?php declare(strict_types=1); ?>
<section class="auth-card"><h1>Willkommen zurück</h1><p>Melde dich an, um deine Jugendfeuerwehr zu verwalten.</p>
<?php if ($error): ?><div class="form-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="/login" class="form-stack"><?= csrf_field() ?>
<label>E-Mail-Adresse<input type="email" name="email" autocomplete="email" required></label>
<label>Passwort<input type="password" name="password" autocomplete="current-password" required></label>
<button class="button button-primary button-block" type="submit">Anmelden</button></form></section>
