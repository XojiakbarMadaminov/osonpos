<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\PlatformOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EnterOrganizationAdminController extends Controller
{
    public function __invoke(Request $request, PlatformOrganizationAccess $access): RedirectResponse
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
        ], [
            'organization_id.required' => 'Tashkilotni tanlang.',
            'organization_id.integer' => 'Tanlangan tashkilot noto‘g‘ri.',
            'organization_id.exists' => 'Tanlangan tashkilot topilmadi.',
        ]);

        $organization = Organization::query()->findOrFail($validated['organization_id']);
        $access->enter($request->user(), $organization);

        return redirect('/admin');
    }
}
