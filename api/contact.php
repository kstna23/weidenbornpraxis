<?php
declare(strict_types=1);

const REQUIRED_ENV = ['IONOS_USER', 'IONOS_PASS', 'IONOS_FROM', 'IONOS_TO'];
const SMTP_HOST = 'smtp.ionos.de';
const SMTP_PORT = 587;

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

function smtpSendCommand($socket, string $command, int $expectCode): void
{
    if ($command !== '') {
        fwrite($socket, $command . "\r\n");
    }

    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }

    $code = (int)substr($response, 0, 3);
    if ($code !== $expectCode) {
        throw new RuntimeException('SMTP-Fehler: ' . trim($response));
    }
}

function sendMailViaSmtp(string $from, string $replyTo, string $to, string $subject, string $messageBody): void
{
    $socket = stream_socket_client(
        'tcp://' . SMTP_HOST . ':' . SMTP_PORT,
        $errno,
        $errstr,
        10,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        throw new RuntimeException('SMTP-Verbindung fehlgeschlagen: ' . $errstr);
    }

    stream_set_timeout($socket, 10);

    smtpSendCommand($socket, '', 220);
    smtpSendCommand($socket, 'EHLO localhost', 250);
    smtpSendCommand($socket, 'STARTTLS', 220);
    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        throw new RuntimeException('SMTP TLS fehlgeschlagen');
    }
    smtpSendCommand($socket, 'EHLO localhost', 250);

    $user = getenv('IONOS_USER') ?: '';
    $pass = getenv('IONOS_PASS') ?: '';
    smtpSendCommand($socket, 'AUTH LOGIN', 334);
    smtpSendCommand($socket, base64_encode($user), 334);
    smtpSendCommand($socket, base64_encode($pass), 235);

    $fromEmail = $from;
    if (preg_match('/<([^>]+)>/', $from, $matches) === 1) {
        $fromEmail = $matches[1];
    }

    smtpSendCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', 250);
    smtpSendCommand($socket, 'RCPT TO:<' . $to . '>', 250);
    smtpSendCommand($socket, 'DATA', 354);

    $headers = [
        'From: ' . $from,
        'Reply-To: ' . $replyTo,
        'To: ' . $to,
        'Subject: ' . $subject,
        'Content-Type: text/plain; charset=utf-8',
    ];

    $data = implode("\r\n", $headers) . "\r\n\r\n" . $messageBody . "\r\n.";
    smtpSendCommand($socket, $data, 250);
    smtpSendCommand($socket, 'QUIT', 221);
    fclose($socket);
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

$messageBody = "Kontaktformular\n\nName: {$name}\nE-Mail: {$email}\n\nNachricht:\n{$message}";

try {
    $smtpFrom = $from;
    if (!str_contains($smtpFrom, '@')) {
        $smtpFrom = getenv('IONOS_USER') ?: $from;
    }
    sendMailViaSmtp($smtpFrom, $email, $to, $subject, $messageBody);
} catch (Throwable $error) {
    jsonResponse(500, ['ok' => false, 'error' => 'E-Mail konnte nicht gesendet werden.']);
}

jsonResponse(200, ['ok' => true]);
