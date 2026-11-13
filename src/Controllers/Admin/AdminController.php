<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Services\Auth;
use App\Services\Csrf;
use App\Services\ImageProcessor;
use App\Services\View;

abstract class AdminController extends Controller
{
    protected const SORTABLE_JS = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js';

    public function __construct(View $view)
    {
        parent::__construct($view);
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');
        if (!Auth::check()) {
            if ($this->wantsJson()) {
                json_response(['error' => 'Session expirée : reconnectez-vous.'], 401);
            }
            redirect('/admin/login');
        }
    }

    /** @param array<string, mixed> $data */
    protected function page(string $template, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        $data['scripts'] = array_merge([self::SORTABLE_JS, asset('js/admin.js')], $data['scripts'] ?? []);
        echo $this->view->render($template, $data + ['nav' => true], 'admin/layout');
    }

    protected function verifyCsrf(): void
    {
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Csrf::check($token)) {
            if ($this->wantsJson()) {
                json_response(['error' => 'Session expirée : rechargez la page.'], 419);
            }
            http_response_code(419);
            exit('Session expirée : rechargez la page.');
        }
    }

    /** Corps de requête dépassant post_max_size : PHP vide $_POST et $_FILES, le jeton CSRF semblerait absent. */
    protected function rejectOversizedPost(): void
    {
        $max = ImageProcessor::parseBytes((string) ini_get('post_max_size'));
        $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($max > 0 && $length > $max) {
            $message = 'Envoi trop volumineux (max ' . ini_get('post_max_size') . 'o).';
            if ($this->wantsJson()) {
                json_response(['error' => $message], 413);
            }
            flash('error', $message);
            redirect($_SERVER['HTTP_REFERER'] ?? '/admin');
        }
    }

    protected function wantsJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    /** @return array<string, mixed> */
    protected function jsonBody(): array
    {
        $data = json_decode((string) file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }

    protected function abort404(string $message = 'Élément introuvable.'): never
    {
        if ($this->wantsJson()) {
            json_response(['error' => $message], 404);
        }
        http_response_code(404);
        exit($message);
    }
}
