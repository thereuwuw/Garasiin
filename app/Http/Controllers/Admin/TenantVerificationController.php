<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantVerificationController extends Controller
{
    public function index(): View
    {
        return view('admin.verifications.index', [
            'pending' => UserService::pendingTenants(),
            'verified' => UserService::verifiedTenants(),
        ]);
    }

    public function accept(Request $request, User $user): RedirectResponse
    {
        try {
            UserService::accept($user, $request->user());
        } catch (GeneralException $e) {
            return back()->withErrors(['verification' => $e->getMessage()]);
        }

        return back()->with('success', "Pendaftaran {$user->name} diterima.");
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        try {
            UserService::reject($user, $request->user());
        } catch (GeneralException $e) {
            return back()->withErrors(['verification' => $e->getMessage()]);
        }

        return back()->with('success', "Pendaftaran {$user->name} ditolak.");
    }
}
