<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ContactForm;
use App\Services\Env;
use App\Services\Mailer;
use App\Services\RateLimiter;

final class ContactController extends Controller
{
    private const MAX_PER_HOUR = 3;

    public function show(): void
    {
        $this->page(['sent' => isset($_GET['envoye'])]);
    }

    public function send(): void
    {
        $key = (string) Env::get('APP_KEY', '');
        $result = ContactForm::validate($_POST, $key, time());

        if ($result['spam']) {
            // On ne dit rien au robot : on fait comme si l'envoi avait réussi
            redirect('/contact?envoye=1');
        }
        if ($result['errors']) {
            $ts = (string) ($_POST['ts'] ?? '');
            $this->page(['errors' => $result['errors'], 'old' => $result['data'], 'ts' => preg_match('/^\d+\.[a-f0-9]{64}$/', $ts) === 1 ? $ts : null], 422);
            return;
        }

        $to = (string) Env::get('NOTIFY_EMAIL', '');
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            error_log('Contact : NOTIFY_EMAIL absent ou invalide, message non envoyé.');
            $this->page(['errors' => ['global' => 'Le formulaire est momentanément indisponible. Réessayez plus tard.'], 'old' => $result['data']], 503);
            return;
        }

        $limiter = new RateLimiter(ROOT . '/storage/ratelimit');
        $rateKey = 'contact:' . client_ip();
        if ($limiter->tooMany($rateKey, self::MAX_PER_HOUR, 3600)) {
            $this->page(['errors' => ['global' => 'Vous avez déjà envoyé plusieurs messages. Réessayez dans une heure.'], 'old' => $result['data']], 429);
            return;
        }
        $limiter->hit($rateKey, 3600);

        $d = $result['data'];
        $body = "Nom : {$d['name']}\nE-mail : {$d['email']}\n\n{$d['message']}\n\n— Envoyé depuis le formulaire de contact de guillaumelecomte.net";
        $sent = Mailer::fromEnv()->send($to, 'Contact site — ' . $d['name'], $body, $d['email']);
        if (!$sent) {
            $this->page(['errors' => ['global' => 'L\'envoi a échoué. Réessayez un peu plus tard.'], 'old' => $d], 502);
            return;
        }
        redirect('/contact?envoye=1');
    }

    /** @param array<string, mixed> $data */
    private function page(array $data, int $status = 200): void
    {
        $data['ts'] = $data['ts'] ?? ContactForm::token((string) Env::get('APP_KEY', ''), time());
        $this->render('public/contact', $data + [
            'meta' => ['title' => 'Contact', 'description' => 'Écrire à Guillaume Lecomte, photographe et pilote ULM.'],
            'sent' => false,
            'errors' => [],
            'old' => [],
        ], $status);
    }
}
