<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Comment;
use App\Models\Photo;
use App\Services\CommentForm;
use App\Services\Env;
use App\Services\Mailer;
use App\Services\RateLimiter;
use App\Services\Visitor;

/** Vues, « j'aime » et commentaires envoyés par la visionneuse. */
final class EngagementController extends Controller
{
    private const VIEWS_PER_HOUR = 600;
    private const LIKES_PER_HOUR = 120;
    private const COMMENTS_PER_HOUR = 5;

    /** Une vue, envoyée une fois par photo et par session de navigation. */
    public function view(string $id): void
    {
        $photo = $this->publishedPhoto($id);
        if (!Visitor::isBot($_SERVER['HTTP_USER_AGENT'] ?? null) && !$this->limited('view', self::VIEWS_PER_HOUR)) {
            Photo::addView((int) $photo['id']);
        }
        http_response_code(204);
    }

    public function like(string $id): void
    {
        $photo = $this->publishedPhoto($id);
        $voter = Visitor::voterId($_POST['token'] ?? null, $this->key());
        if ($voter === null || Visitor::isBot($_SERVER['HTTP_USER_AGENT'] ?? null)) {
            json_response(['error' => 'Requête invalide.'], 400);
        }
        if ($this->limited('like', self::LIKES_PER_HOUR)) {
            json_response(['error' => 'Trop de « j\'aime » d\'un coup. Réessayez plus tard.'], 429);
        }
        $like = ($_POST['like'] ?? '1') !== '0';
        json_response(['likes' => Photo::setLike((int) $photo['id'], $voter, $like), 'liked' => $like]);
    }

    public function comment(string $id): void
    {
        $photo = $this->publishedPhoto($id);
        $result = CommentForm::validate($_POST, $this->key(), time());
        $thanks = ['ok' => true, 'message' => 'Merci ! Votre commentaire sera publié après relecture.'];
        if ($result['spam']) {
            json_response($thanks); // on ne dit rien au robot
        }
        if ($result['errors']) {
            json_response(['errors' => $result['errors']], 422);
        }
        if ($this->limited('comment', self::COMMENTS_PER_HOUR)) {
            json_response(['errors' => ['global' => 'Vous avez déjà envoyé plusieurs commentaires. Réessayez dans une heure.']], 429);
        }

        $d = $result['data'];
        Comment::create((int) $photo['id'], $d['name'], $d['message'], hash_hmac('sha256', client_ip(), $this->key()));
        $this->notify($photo, $d['name'], $d['message']);
        json_response($thanks, 201);
    }

    /** @return array<string, mixed> */
    private function publishedPhoto(string $id): array
    {
        $photo = Photo::find((int) $id);
        if ($photo === null || $photo['album_status'] !== 'published') {
            json_response(['error' => 'Photo introuvable.'], 404);
        }
        return $photo;
    }

    /** Compte la requête et indique si l'adresse IP a dépassé la limite horaire. */
    private function limited(string $action, int $perHour): bool
    {
        $limiter = new RateLimiter(ROOT . '/storage/ratelimit');
        $key = $action . ':' . client_ip();
        if ($limiter->tooMany($key, $perHour, 3600)) {
            return true;
        }
        $limiter->hit($key, 3600);
        return false;
    }

    private function key(): string
    {
        return (string) Env::get('APP_KEY', '');
    }

    /** @param array<string, mixed> $photo */
    private function notify(array $photo, string $name, string $message): void
    {
        $to = (string) Env::get('NOTIFY_EMAIL', '');
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }
        $title = photo_label((string) $photo['title'], (string) $photo['album_title']);
        $url = absolute_url('/' . $photo['theme_slug'] . '/' . $photo['album_slug'] . '/photo/' . (int) $photo['id']);
        $body = "Nouveau commentaire à valider sur « $title » (album {$photo['album_title']}) :\n\n"
            . "$name :\n$message\n\n"
            . "Photo : $url\nValider ou supprimer : " . absolute_url('/admin/commentaires') . "\n";
        if (!Mailer::fromEnv()->send($to, 'Commentaire à valider — « ' . $title . ' »', $body)) {
            error_log('Commentaire : notification par e-mail non envoyée.');
        }
    }
}
