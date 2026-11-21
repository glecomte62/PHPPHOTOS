<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Comment;

final class CommentController extends AdminController
{
    public function index(): void
    {
        $this->page('admin/comments', [
            'title' => 'Commentaires',
            'pending' => Comment::pending(),
            'approved' => Comment::recentApproved(30),
        ]);
    }

    public function approve(int $id): void
    {
        $this->verifyCsrf();
        Comment::approve($id);
        flash('success', 'Commentaire publié.');
        redirect('/admin/commentaires');
    }

    public function delete(int $id): void
    {
        $this->verifyCsrf();
        Comment::delete($id);
        flash('success', 'Commentaire supprimé.');
        redirect('/admin/commentaires');
    }
}
