<?php
declare(strict_types=1);

const REQUIRED_ENV = ['IONOS_USER', 'IONOS_PASS', 'IONOS_FROM', 'IONOS_TO'];

function loadEnv(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");

        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function jsonResponse(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function validateBody(array $body): array
{
    $errors = [];
    $name = trim((string)($body['name'] ?? ''));
    $email = trim((string)($body['email'] ?? ''));
    $message = trim((string)($body['message'] ?? ''));
    $quizAnswer = (int)($body['quiz_answer'] ?? 0);
    $quizLeft = (int)($body['quiz_left'] ?? 0);
    $quizRight = (int)($body['quiz_right'] ?? 0);
    $website = trim((string)($body['website'] ?? ''));
    $startedAt = (int)($body['form_started_at'] ?? 0);
    $minimumFormTimeMs = 3000;
    $now = (int)round(microtime(true) * 1000);

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
    $expectedQuiz = $quizLeft + $quizRight;
    if ($quizLeft === 0 && $quizRight === 0) {
        $expectedQuiz = null;
    }
    if ($expectedQuiz === null || $quizAnswer !== $expectedQuiz) {
        $errors[] = 'Kontrollfrage falsch.';
    }
    if ($startedAt > 0 && ($now - $startedAt) < $minimumFormTimeMs) {
        $errors[] = 'Bitte Formular regulär ausfüllen.';
    }

    return [$errors, $name, $email, $message];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    jsonResponse(405, ['ok' => false, 'error' => 'POST erforderlich']);
}

$root = dirname(__DIR__);
loadEnv($root . '/.env');

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '{}', true);
if (!is_array($body) || $body === []) {
    if (!empty($_POST)) {
        $body = $_POST;
    }
}
if (!is_array($body)) {
    jsonResponse(400, ['ok' => false, 'error' => 'Ungültiger Anfrage-Body']);
}

[$errors, $name, $email, $message] = validateBody($body);
if ($errors !== []) {
    jsonResponse(400, ['ok' => false, 'error' => implode(' ', $errors)]);
}

$missing = [];
foreach (REQUIRED_ENV as $key) {
    if (getenv($key) === false || getenv($key) === '') {
        $missing[] = $key;
    }
}

if ($missing !== []) {
    jsonResponse(500, ['ok' => false, 'error' => 'Server-Konfiguration fehlt (' . implode(', ', $missing) . ')']);
}

$from = getenv('IONOS_FROM');
$to = getenv('IONOS_TO');
$subject = 'Neue Kontaktaufnahme Homepage';
$headers = [
    'From: ' . $from,
    'Reply-To: ' . $email,
    'Content-Type: text/plain; charset=utf-8',
];

$messageBody = "Kontaktformular\n\nName: {$name}\nE-Mail: {$email}\n\nNachricht:\n{$message}";

$fromEmail = $from;
if (preg_match('/<([^>]+)>/', $from, $matches) === 1) {
    $fromEmail = $matches[1];
}

$sent = mail($to, $subject, $messageBody, implode("\r\n", $headers), '-f' . $fromEmail);

if (!$sent) {
    jsonResponse(500, ['ok' => false, 'error' => 'E-Mail konnte nicht gesendet werden.']);
}

jsonResponse(200, ['ok' => true]);
