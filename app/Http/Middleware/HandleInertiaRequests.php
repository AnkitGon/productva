<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        if ($user) {
            $user->loadMissing(['roles.permissions', 'organization', 'activePlant']);
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('slug'),
                    'permissions' => $user->roles
                        ->flatMap->permissions
                        ->pluck('slug')
                        ->unique()
                        ->values()
                        ->all(),
                    'organization_id' => $user->organization_id,
                    'active_plant_id' => $user->active_plant_id ?: ($user->organization ? $user->organization->plants()->where('is_default', true)->value('id') : null),
                    'plants' => $user->organization
                        ? $user->organization->plants()
                            ->with(['manager:id,name,email'])
                            ->get()
                        : [],
                    'active_plant' => $user->activePlant
                        ? $user->activePlant->only(['id', 'name', 'code', 'slug'])
                        : ($user->organization
                            ? $user->organization->plants()->where('is_default', true)->first(['id', 'name', 'code', 'slug'])
                            : null),
                ] : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
