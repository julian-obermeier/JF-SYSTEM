<?php
declare(strict_types=1);

use JFS\Csrf;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $normalized = '/' . ltrim($path, '/');
    return $normalized === '/' ? '/' : $normalized;
}

function asset(string $path): string
{
    return '/assets/' . ltrim($path, '/');
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function initials(string $firstName, string $lastName): string
{
    $first = mb_substr(trim($firstName), 0, 1);
    $last = mb_substr(trim($lastName), 0, 1);
    return mb_strtoupper($first . $last);
}

function german_date(?string $date): string
{
    if (!$date) {
        return '–';
    }
    return (new DateTimeImmutable($date))->format('d.m.Y');
}

function age_from_birthdate(?string $date): string
{
    if (!$date) {
        return '–';
    }
    return (string) (new DateTimeImmutable($date))->diff(new DateTimeImmutable('today'))->y;
}
