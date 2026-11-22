<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Enums\UserStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    /**
     * Halaman satu-satunya untuk penyewa yang masih pending atau rejected.
     */
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->status === UserStatus::Accepted) {
            return redirect()->route('tenant.rentals.index');
        }

        return view('tenant.approval', ['user' => $user]);
    }
}
