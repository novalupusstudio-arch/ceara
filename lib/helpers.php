<?php

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $page, array $params = []): never
{
    $params = array_merge(['page' => $page], $params);
    header('Location: index.php?' . http_build_query($params));
    exit;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_initial_admin(): bool
{
    $user = current_user();
    return $user && (int) $user['id'] === 1 && $user['role'] === 'admin';
}

function require_login(): void
{
    if (!current_user()) {
        redirect('login');
    }
}

function flash(?string $message = null, string $type = 'success'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }

    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function grams_to_kg(int $grams): string
{
    return number_format($grams / 1000, 3, '.', '') . ' kg';
}

function kg_to_grams(string $kg): int
{
    $normalized = str_replace(',', '.', trim($kg));
    return (int) round(((float) $normalized) * 1000);
}

function money(int $cents): string
{
    return number_format($cents / 100, 2, '.', '') . ' lei';
}

function post_string(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function post_int(string $key, int $default = 0): int
{
    return (int) ($_POST[$key] ?? $default);
}
