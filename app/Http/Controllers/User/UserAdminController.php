<?php

namespace App\Http\Controllers\User;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use App\Http\Controllers\AdminController;

class UserAdminController extends AdminController
{
    public function index(): View
    {
        $users = User::orderBy('id')->paginate(50);

        return view('user.admin.index', [
            'users' => $users,
            'isSuperAdmin' => auth()->user()->isSuperAdmin,
        ]);
    }

    public function toggleAdmin(User $user): RedirectResponse
    {
        $currentUser = auth()->user();

        abort_if(!$currentUser->isSuperAdmin, 403);
        abort_if($user->id === $currentUser->id, 403);
        abort_if($user->isSuperAdmin, 403);

        $user->forceFill(['isAdmin' => !$user->isAdmin])->save();

        $action = $user->isAdmin ? 'granted' : 'revoked';
        flashMessage('success', __('Done'), __("Admin access {$action} for {$user->full_name}."));

        return redirect()->back();
    }
}
