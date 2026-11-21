<?php
declare(strict_types=1);

// This endpoint answers with JSON only. PHP warnings must go to the error
// log, never into the response (they would break the JSON and leak paths).
ini_set('display_errors', '0');

const REQUIRED_ENV = ['IONOS_USER', 'IONOS_PASS', 'IONOS_FROM', 'IONOS_TO'];
const SMTP_HOST = 'smtp.ionos.de';
const SMTP_PORT = 587;

const QUIZ_SESSION_KEY = 'contact_quiz';
const QUIZ_MIN_AGE_SECONDS = 3;
const QUIZ_MAX_AGE_SECONDS = 7200;
const MAX_NAME_LENGTH = 200;
const MAX_MESSAGE_LENGTH = 5000;

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
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function startQuizSession(): void
{
    session_name('wbp_contact');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/api/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

/**
 * Issues a new arithmetic question. The expected sum stays in the session,
 * so a bot cannot pick its own numbers as with the former client-side quiz.
 */
function issueQuiz(): array
{
    $left = random_int(3, 6);
    $right = random_int(4, 8);
    $_SESSION[QUIZ_SESSION_KEY] = ['sum' => $left + $right, 'issued_at' => time()];

    return ['left' => $left, 'right' => $right];
}

/** Returns an error message, or null when the answer is valid. One attempt per question. */
function checkQuiz(int $answer): ?string
{
    $quiz = $_SESSION[QUIZ_SESSION_KEY] ?? null;
    unset($_SESSION[QUIZ_SESSION_KEY]);

    if (!is_array($quiz)) {
        return 'Kontrollfrage abgelaufen. Bitte die neue Aufgabe lösen.';
    }
    $age = time() - (int)$quiz['issued_at'];
    if ($age < QUIZ_MIN_AGE_SECONDS) {
        return 'Bitte Formular regulär ausfüllen.';
    }
    if ($age > QUIZ_MAX_AGE_SECONDS) {
        return 'Kontrollfrage abgelaufen. Bitte die neue Aufgabe lösen.';
    }
    if ($answer !== (int)$quiz['sum']) {
        return 'Kontrollfrage falsch.';
    }

    return null;
}

function validateBody(array $body): array
{
    $errors = [];
    $name = trim((string)($body['name'] ?? ''));
    $email = trim((string)($body['email'] ?? ''));
    $message = trim((string)($body['message'] ?? ''));
    $website = trim((string)($body['website'] ?? ''));

    if ($name === '') {
        $errors[] = 'Name fehlt.';
    } elseif (mb_strlen($name) > MAX_NAME_LENGTH || preg_match('/[\r\n]/', $name) === 1) {
        $errors[] = 'Name ungültig.';
    }
    if ($email === '') {
        $errors[] = 'E-Mail fehlt.';
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        // Also rules out CR/LF, so the address is safe as a Reply-To header.
        $errors[] = 'E-Mail-Adresse ungültig.';
    }
    if ($message === '') {
        $errors[] = 'Nachricht fehlt.';
    } elseif (mb_strlen($message) > MAX_MESSAGE_LENGTH) {
        $errors[] = 'Nachricht zu lang (maximal ' . MAX_MESSAGE_LENGTH . ' Zeichen).';
    }
    if ($website !== '') {
        $errors[] = 'Spam erkannt.';
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

function emailAddress(string $mailbox): string
{
    if (preg_match('/<([^>]+)>/', $mailbox, $matches) === 1) {
        return trim($matches[1]);
    }

    return trim($mailbox);
}

function emailDomain(string $address): string
{
    $at = strrpos($address, '@');

    return $at === false ? 'localhost' : substr($address, $at + 1);
}

/**
 * Builds a standards-compliant message: Date, Message-ID and MIME headers
 * present, CRLF line endings, quoted-printable body (UTF-8 umlauts stay
 * 7-bit safe) and SMTP dot-stuffing. Missing Date/Message-ID headers,
 * bare LF and unencoded 8-bit text are typical spam-filter signals.
 */
function buildMessage(string $from, string $replyTo, string $to, string $subject, string $body): string
{
    $domain = emailDomain(emailAddress($from));
    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
        'From: ' . $from,
        'Reply-To: ' . $replyTo,
        'To: ' . $to,
        'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8', 'Q'),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: quoted-printable',
    ];

    $normalizedBody = preg_replace('/\r\n|\r|\n/', "\r\n", $body);
    $encodedBody = quoted_printable_encode($normalizedBody);

    $data = implode("\r\n", $headers) . "\r\n\r\n" . $encodedBody;

    // RFC 5321 4.5.2: a line starting with "." must get an extra "." in DATA.
    return preg_replace('/^\./m', '..', $data);
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

    $fromEmail = emailAddress($from);
    // "EHLO localhost" is scored as spam; announce the sender's domain instead.
    $ehlo = 'EHLO ' . emailDomain($fromEmail);

    smtpSendCommand($socket, '', 220);
    smtpSendCommand($socket, $ehlo, 250);
    smtpSendCommand($socket, 'STARTTLS', 220);
    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        throw new RuntimeException('SMTP TLS fehlgeschlagen');
    }
    smtpSendCommand($socket, $ehlo, 250);

    $user = getenv('IONOS_USER') ?: '';
    $pass = getenv('IONOS_PASS') ?: '';
    smtpSendCommand($socket, 'AUTH LOGIN', 334);
    smtpSendCommand($socket, base64_encode($user), 334);
    smtpSendCommand($socket, base64_encode($pass), 235);

    smtpSendCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', 250);
    smtpSendCommand($socket, 'RCPT TO:<' . emailAddress($to) . '>', 250);
    smtpSendCommand($socket, 'DATA', 354);

    $data = buildMessage($from, $replyTo, $to, $subject, $messageBody) . "\r\n.";
    smtpSendCommand($socket, $data, 250);
    smtpSendCommand($socket, 'QUIT', 221);
    fclose($socket);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['quiz'])) {
    startQuizSession();
    jsonResponse(200, ['ok' => true] + issueQuiz());
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    jsonResponse(405, ['ok' => false, 'error' => 'POST erforderlich']);
}

startQuizSession();

// Credentials belong outside the webroot (one level above it), so the web
// server can never serve them. The .env in the webroot is a legacy fallback
// that .htaccess blocks; remove it once the file above the webroot exists.
$configCandidates = [
    dirname(__DIR__, 2) . '/weidenbornpraxis.env',
    dirname(__DIR__) . '/.env',
];
foreach ($configCandidates as $configPath) {
    // @: open_basedir may forbid the path above the webroot; that is an
    // expected "not there", not an error, and must not leak into the JSON.
    if (@is_readable($configPath)) {
        loadEnv($configPath);
        break;
    }
}

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
$quizError = checkQuiz((int)($body['quiz_answer'] ?? 0));
if ($quizError !== null) {
    $errors[] = $quizError;
}
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
    error_log('contact.php: ' . $error->getMessage());
    jsonResponse(500, ['ok' => false, 'error' => 'E-Mail konnte nicht gesendet werden.']);
}

jsonResponse(200, ['ok' => true]);
