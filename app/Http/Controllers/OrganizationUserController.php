<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationUserController extends Controller
{
    /**
     * Search users or employees in the authenticated user's organization.
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

        if ($request->boolean('with_employee')) {
            return $this->searchEmployees($request, $user, $search);
        }

        return $this->searchUsers($request, $user, $search);
    }

    /**
     * Search organization users (login accounts). Active plant session does not matter.
     */
    private function searchUsers(Request $request, User $user, string $search): JsonResponse
    {
        $query = User::query()
            ->where('organization_id', $user->organization_id)
            ->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->where(function ($builder) {
                $builder->whereDoesntHave('employee')
                    ->orWhereHas('employee', fn ($employee) => $employee->where('status', 'Active'));
            })
            ->orderBy('name')
            ->limit(20);

        $excludeId = $request->integer('exclude_user_id') ?: null;
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $results = $query->get(['id', 'name', 'email'])->map(fn (User $found) => [
            'id' => $found->id,
            'name' => $found->name,
            'email' => $found->email,
            'label' => "{$found->name} ({$found->email})",
        ])->values();

        return response()->json($results);
    }

    /**
     * Search active employees on the active plant by first name, last name, or code.
     * Inactive employees are excluded from assignment pickers.
     */
    private function searchEmployees(Request $request, User $user, string $search): JsonResponse
    {
        $query = Employee::query()
            ->where('organization_id', $user->organization_id)
            ->where('plant_id', $user->active_plant_id)
            ->assignable()
            ->where(function ($builder) use ($search) {
                $builder->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(20);

        $excludeEmployeeId = $request->integer('exclude_employee_id') ?: null;
        if ($excludeEmployeeId) {
            $query->where('id', '!=', $excludeEmployeeId);
        }

        $results = $query->get()->map(function (Employee $employee) {
            $name = $employee->name;

            return [
                'id' => $employee->user_id ?: $employee->id,
                'employee_id' => $employee->id,
                'name' => $name,
                'email' => $employee->email,
                'label' => "{$name} ({$employee->employee_code})",
            ];
        })->values();

        return response()->json($results);
    }
}
