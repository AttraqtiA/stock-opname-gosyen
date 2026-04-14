<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('admin.users', [
            'pendingUsers' => User::query()->where('is_approved', false)->latest()->get(),
            'approvedUsers' => User::query()->where('is_approved', true)->orderBy('name')->get(),
        ]);
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'role' => ['required', 'in:admin,employee'],
        ]);

        $user->update([
            'role' => $data['role'],
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        return back()->with('status', "{$user->name} sudah diapprove.");
    }
}
