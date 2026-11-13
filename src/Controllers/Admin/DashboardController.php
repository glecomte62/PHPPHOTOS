<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Album;
use App\Services\Database;

final class DashboardController extends AdminController
{
    public function index(): void
    {
        $this->page('admin/dashboard', [
            'title' => 'Tableau de bord',
            'counts' => [
                'themes' => (int) Database::value('SELECT COUNT(*) FROM themes'),
                'albums' => (int) Database::value('SELECT COUNT(*) FROM albums'),
                'photos' => (int) Database::value('SELECT COUNT(*) FROM photos'),
            ],
            'albums' => Album::recentForAdmin(8),
            'limits' => [
                'upload' => (string) ini_get('upload_max_filesize'),
                'post' => (string) ini_get('post_max_size'),
                'memory' => (string) ini_get('memory_limit'),
            ],
        ]);
    }
}
