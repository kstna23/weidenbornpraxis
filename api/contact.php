<?php

declare(strict_types=1);

$requiredEnv = ['IONOS_USER', 'IONOS_PASS', 'IONOS_FROM', 'IONOS_TO'];

function loadEnvFile(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    $vars = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, " \t\n\r\0\x0B\"");
        $vars[$key] = $value;
    }

    return $vars;
}

function getEnvValue(string $key, array $fallback): ?string
{
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }

    return $fallback[$key] ?? null;
}

function respondJson(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respondJson(405, ['ok' => false, 'error' => 'POST erforderlich']);
    exit;
}

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody ?: '', true);
if (!is_array($data)) {
    respondJson(400, ['ok' => false, 'error' => 'Ungültiger JSON-Body']);
    exit;
}

$name = trim((string) ($data['name'] ?? ''));
$email = trim((string) ($data['email'] ?? ''));
$message = trim((string) ($data['message'] ?? ''));
$quizAnswer = (int) ($data['quiz_answer'] ?? 0);
$quizLeft = (int) ($data['quiz_left'] ?? 0);
$quizRight = (int) ($data['quiz_right'] ?? 0);
$website = trim((string) ($data['website'] ?? ''));
$startedAt = (int) ($data['form_started_at'] ?? 0);
$now = (int) round(microtime(true) * 1000);
$minimumFormTimeMs = 3000;

$errors = [];
if ($name === '') {
    $errors[] = 'Name fehlt.';
}
if ($email === '') {
    $errors[] = 'E-Mail fehlt.';
}
if ($message === '') {
    $errors[] = 'Nachricht fehlt.';
}
if ($website !== '') {
    $errors[] = 'Spam erkannt.';
}
if ($quizLeft + $quizRight !== $quizAnswer) {
    $errors[] = 'Kontrollfrage falsch.';
}
if ($startedAt > 0 && ($now - $startedAt) < $minimumFormTimeMs) {
    $errors[] = 'Bitte Formular regulär ausfüllen.';
}

if ($errors !== []) {
    respondJson(400, ['ok' => false, 'error' => implode(' ', $errors)]);
    exit;
}

$envFromFile = loadEnvFile(dirname(__DIR__) . '/.env');
$missing = [];
$env = [];
foreach ($requiredEnv as $key) {
    $value = getEnvValue($key, $envFromFile);
    if ($value === null || $value === '') {
        $missing[] = $key;
    }
    $env[$key] = $value;
}

if ($missing !== []) {
    respondJson(500, ['ok' => false, 'error' => 'Server-Konfiguration fehlt (' . implode(', ', $missing) . ')']);
    exit;
}

$subject = 'Neue Kontaktaufnahme Homepage';
$body = "Kontaktformular\n\nName: {$name}\nE-Mail: {$email}\n\nNachricht:\n{$message}";
$headers = [
    'From: ' . $env['IONOS_FROM'],
    'Reply-To: ' . $email,
    'Content-Type: text/plain; charset=UTF-8',
];

$sent = mail(
    $env['IONOS_TO'],
    $subject,
    $body,
    implode("\r\n", $headers)
);

if (!$sent) {
    respondJson(500, ['ok' => false, 'error' => 'E-Mail konnte nicht gesendet werden.']);
    exit;
}

respondJson(200, ['ok' => true]);
