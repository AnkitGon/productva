<?php

namespace App\Http\Controllers;

use App\Services\FactorySetupService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SetupWizardController extends Controller
{
    public function __construct(private FactorySetupService $setup) {}

    /**
     * Getting Started roadmap (read-only checklist with deep links).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id) {
            abort(403);
        }

        if ($user->hasRole('super-admin')) {
            return redirect('/admin/dashboard');
        }

        return Inertia::render('setup/index', [
            'roadmap' => $this->setup->roadmap($user),
            'userName' => $user->name,
            'organizationName' => $user->organization?->name,
            'activePlantName' => $user->activePlant?->name,
        ]);
    }

    /**
     * Dismiss Getting Started redirects (roadmap remains available).
     */
    public function complete(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id) {
            abort(403);
        }

        $this->setup->dismiss($user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Getting Started marked complete. It has been removed from the sidebar.',
        ]);

        return redirect()->route('dashboard');
    }
}
