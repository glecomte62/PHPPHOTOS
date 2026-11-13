<?php
declare(strict_types=1);

namespace App\Services;

/** Client SMTP minimal (SSL implicite, AUTH LOGIN), adapté au SMTP d'O2switch. */
final class Mailer
{
    public function __construct(
        private string $host,
        private int $port,
        private string $user,
        private string $pass,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            (string) Env::get('MAIL_HOST', ''),
            (int) Env::get('MAIL_PORT', '465'),
            (string) Env::get('MAIL_USER', ''),
            (string) Env::get('MAIL_PASS', ''),
        );
    }

    public static function buildMessage(string $from, string $to, string $subject, string $body, ?string $replyTo = null): string
    {
        $clean = static fn (string $v): string => str_replace(["\r", "\n"], '', $v);
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: guillaumelecomte.net <' . $clean($from) . '>',
            'To: ' . $clean($to),
            'Subject: =?UTF-8?B?' . base64_encode($clean($subject)) . '?=',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL) !== false) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        return implode("\r\n", $headers) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($body), 76, "\r\n"));
    }

    public function send(string $to, string $subject, string $body, ?string $replyTo = null): bool
    {
        $socket = @stream_socket_client("ssl://{$this->host}:{$this->port}", $errno, $errstr, 15);
        if ($socket === false) {
            error_log("SMTP : connexion impossible ($errno) $errstr");
            return false;
        }
        stream_set_timeout($socket, 15);
        try {
            $this->expect($socket, 220);
            $this->command($socket, 'EHLO guillaumelecomte.net', 250);
            $this->command($socket, 'AUTH LOGIN', 334);
            $this->command($socket, base64_encode($this->user), 334);
            $this->command($socket, base64_encode($this->pass), 235);
            $this->command($socket, "MAIL FROM:<{$this->user}>", 250);
            $this->command($socket, "RCPT TO:<{$to}>", 250);
            $this->command($socket, 'DATA', 354);
            $message = preg_replace('/^\./m', '..', self::buildMessage($this->user, $to, $subject, $body, $replyTo));
            $this->command($socket, $message . "\r\n.", 250);
            fwrite($socket, "QUIT\r\n");
            return true;
        } catch (\RuntimeException $e) {
            error_log('SMTP : ' . $e->getMessage());
            return false;
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket */
    private function command($socket, string $line, int $expected): void
    {
        fwrite($socket, $line . "\r\n");
        $this->expect($socket, $expected);
    }

    /** @param resource $socket */
    private function expect($socket, int $expected): void
    {
        do {
            $line = fgets($socket, 515);
            if ($line === false) {
                throw new \RuntimeException("pas de réponse (attendu $expected)");
            }
        } while (isset($line[3]) && $line[3] === '-');
        if ((int) substr($line, 0, 3) !== $expected) {
            throw new \RuntimeException("attendu $expected, reçu " . trim($line));
        }
    }
}
