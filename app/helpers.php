<?php
declare(strict_types=1);

function app_config(): array
{
    static $config;
    return $config ??= require APP_ROOT . '/config/config.php';
}

function db(): PDO
{
    return Database::connection();
}

function tenant_id(): int
{
    return (int) ($_SESSION['support_tenant_id'] ?? $_SESSION['tenant_id'] ?? 0);
}

function support_mode(): bool
{
    return isset($_SESSION['support_tenant_id'], $_SESSION['support_session_id']);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function require_csrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token)) {
        http_response_code(419);
        exit('Die Sitzung ist abgelaufen. Bitte laden Sie die Seite neu.');
    }
}

function redirect(string $target): never
{
    header('Location: ' . $target);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function pull_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function audit(string $action, string $entityType, ?int $entityId, string $description): void
{
    if (!isset($_SESSION['tenant_id'])) {
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO audit_logs (tenant_id, user_id, action_name, entity_type, entity_id, description_text, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        tenant_id(),
        $_SESSION['user_id'] ?? null,
        $action,
        $entityType,
        $entityId,
        $description,
        substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45),
    ]);
}

function format_date(?string $date, bool $withTime = false): string
{
    if (!$date) {
        return '–';
    }
    $value = new DateTimeImmutable($date);
    return $value->format($withTime ? 'd.m.Y · H:i' : 'd.m.Y');
}

function role_label(string $role): string
{
    return ['admin' => 'Administrator', 'leader' => 'Jugendwart', 'staff' => 'Betreuer', 'viewer' => 'Leser'][$role] ?? $role;
}

function status_label(?string $status): string
{
    return [
        'active' => 'Aktiv',
        'paused' => 'Pausiert',
        'left' => 'Ausgetreten',
        'draft' => 'Entwurf',
        'published' => 'Veröffentlicht',
        'cancelled' => 'Abgesagt',
        'completed' => 'Abgeschlossen',
        'present' => 'Anwesend',
        'excused' => 'Entschuldigt',
        'absent' => 'Fehlt',
        'unknown' => 'Offen',
        'yes' => 'Zusage',
        'no' => 'Absage',
        'maybe' => 'Vielleicht',
        'open' => 'Offen',
    ][$status ?? ''] ?? ($status ?: '–');
}

function event_type_label(?string $type): string
{
    return [
        'practice' => 'Übung',
        'meeting' => 'Besprechung',
        'trip' => 'Ausflug',
        'competition' => 'Wettbewerb',
        'other' => 'Sonstiges',
    ][$type ?? ''] ?? ($type ?: '–');
}

function initials(string $firstName, string $lastName): string
{
    return mb_strtoupper(mb_substr($firstName, 0, 1) . mb_substr($lastName, 0, 1));
}

function asset_url(string $path): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $base = rtrim(dirname($script), '/.');
    $publicRoot = realpath(APP_ROOT . '/public');
    $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));

    // Unterstützt beide Installationsarten:
    // 1. Domain zeigt auf den Projektordner: /public/assets/...
    // 2. Domain zeigt direkt auf public/: /assets/...
    $publicIsDocumentRoot = $publicRoot !== false
        && $documentRoot !== false
        && rtrim($documentRoot, DIRECTORY_SEPARATOR) === rtrim($publicRoot, DIRECTORY_SEPARATOR);

    if (!$publicIsDocumentRoot && !str_contains($script, '/public/')) {
        $base .= '/public';
    }

    $relativePath = ltrim($path, '/');
    $url = ($base ?: '') . '/assets/' . $relativePath;
    $assetFile = APP_ROOT . '/public/assets/' . $relativePath;

    // Verhindert, dass Browser nach Updates alte CSS-/JS-Dateien verwenden.
    if (is_file($assetFile)) {
        $url .= '?v=' . (string) filemtime($assetFile);
    }

    return $url;
}

function icon(string $name): string
{
    $paths = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-7h6v7"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'award' => '<circle cx="12" cy="8" r="6"/><path d="M8.2 13 7 22l5-3 5 3-1.2-9"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21H9.6v-.09A1.7 1.7 0 0 0 8.5 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.6-1H3v-4h.09A1.7 1.7 0 0 0 4.6 8.9a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.6V3h4v.09A1.7 1.7 0 0 0 15.1 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.13.38.35.73.66 1H21v4h-.09a1.7 1.7 0 0 0-1.51 1Z"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-5"/>',
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h6"/>',
    ];
    return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' . ($paths[$name] ?? $paths['file']) . '</svg>';
}
