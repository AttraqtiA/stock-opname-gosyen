<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminCompanyController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('admin.companies', [
            'pendingCompanies' => Company::query()
                ->with(['pic:id,name,email', 'requester:id,name,email'])
                ->where('status', 'pending')
                ->latest()
                ->get(),
            'approvedCompanies' => Company::query()
                ->with(['pic:id,name,email'])
                ->where('status', 'approved')
                ->orderBy('name')
                ->get(),
            'picUsers' => User::query()->where('is_approved', true)->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function approve(Request $request, Company $company): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $company->update([
            'status' => 'approved',
            'approved_by_user_id' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('status', "{$company->name} sudah aktif.");
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('companies', 'name')->ignore($company->id)],
            'location' => ['nullable', 'string', 'max:255'],
            'pic_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $company->update($data);

        return back()->with('status', "{$company->name} sudah diperbarui.");
    }

    public function destroy(Request $request, Company $company): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $name = $company->name;
        $company->delete();

        return back()->with('status', "{$name} sudah dihapus.");
    }
}
