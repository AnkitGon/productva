<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationUserController extends Controller
{
    /**
     * Search users in the authenticated user's organization and active plant.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $search = trim((string) $request->get('q', ''));

        if (mb_strlen($search) < 1) {
            return response()->json([]);
        }

        $query = User::query()
            ->where('organization_id', $user->organization_id)
            ->where('active_plant_id', $user->active_plant_id)
            ->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(20);

        if ($request->boolean('with_employee')) {
            $query->whereHas('employee', function ($builder) use ($user) {
                $builder->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id);
            })->with(['employee' => function ($builder) use ($user) {
                $builder->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id)
                    ->select(['id', 'user_id', 'first_name', 'last_name']);
            }]);
        }

        $excludeId = $request->integer('exclude_user_id') ?: null;
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $results = $query->get(['id', 'name', 'email'])->map(function (User $found) use ($request) {
            $payload = [
                'id' => $found->id,
                'name' => $found->name,
                'email' => $found->email,
                'label' => "{$found->name} ({$found->email})",
            ];

            if ($request->boolean('with_employee')) {
                $payload['employee_id'] = $found->employee?->id;
            }

            return $payload;
        })->values();

        return response()->json($results);
    }
}
