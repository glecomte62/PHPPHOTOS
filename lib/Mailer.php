<?php

class Mailer
{
    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private string $from;

    public function __construct(string $host, int $port, string $user, string $pass)
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->from = $user;
    }

    public function send(string $to, string $subject, string $body): bool
    {
        $socket = stream_socket_client(
            "ssl://{$this->host}:{$this->port}",
            $errno, $errstr, 15,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])
        );

        if (!$socket) return false;

        $read  = fn() => fgets($socket, 512);
        $write = fn(string $cmd) => fwrite($socket, $cmd . "\r\n");

        $read(); // 220 greeting
        $write("EHLO guillaumelecomte.net");
        while (true) { $line = $read(); if ($line[3] === ' ') break; } // multi-line EHLO
        $write("AUTH LOGIN");
        $read(); // 334
        $write(base64_encode($this->user));
        $read(); // 334
        $write(base64_encode($this->pass));
        $read(); // 235

        $write("MAIL FROM:<{$this->from}>");
        $read();
        $write("RCPT TO:<{$to}>");
        $read();
        $write("DATA");
        $read(); // 354

        $headers  = "From: guillaumelecomte.net <{$this->from}>\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: base64\r\n";

        $write($headers . "\r\n" . chunk_split(base64_encode($body)));
        $write(".");
        $read(); // 250

        $write("QUIT");
        fclose($socket);
        return true;
    }
}
