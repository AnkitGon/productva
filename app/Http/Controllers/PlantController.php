<?php

namespace App\Http\Controllers;

use App\Models\Plant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PlantController extends Controller
{
    /**
     * Store a newly created plant in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id) {
            abort(403, 'User does not belong to an organization.');
        }

        $request->merge([
            'manager_id' => $request->filled('manager_id') ? $request->input('manager_id') : null,
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('plants')
                    ->where('organization_id', $user->organization_id)
                    ->whereNull('deleted_at'),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('plants')
                    ->where('organization_id', $user->organization_id)
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->whereNull('deleted_at'),
            ],
            'status' => ['required', 'string', Rule::in(['Active', 'Inactive'])],
            'is_default' => ['required', 'boolean'],
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
            $slugBase = $validated['slug'];
            $count = 1;
            while (Plant::where('organization_id', $user->organization_id)->where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $slugBase.'-'.$count++;
            }
        }

        $plant = Plant::create([
            ...$validated,
            'organization_id' => $user->organization_id,
        ]);

        // Auto-activate the newly created plant
        $user->update([
            'active_plant_id' => $plant->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Plant created successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Update the specified plant in storage.
     */
    public function update(Request $request, Plant $plant): RedirectResponse
    {
        $user = $request->user();
        if (! $user || $plant->organization_id !== $user->organization_id) {
            abort(403);
        }

        $request->merge([
            'manager_id' => $request->filled('manager_id') ? $request->input('manager_id') : null,
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('plants')
                    ->where('organization_id', $user->organization_id)
                    ->whereNull('deleted_at')
                    ->ignore($plant->id),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('plants')
                    ->where('organization_id', $user->organization_id)
                    ->whereNull('deleted_at')
                    ->ignore($plant->id),
            ],
            'description' => ['nullable', 'string'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->whereNull('deleted_at'),
            ],
            'status' => ['required', 'string', Rule::in(['Active', 'Inactive'])],
            'is_default' => ['required', 'boolean'],
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
            $slugBase = $validated['slug'];
            $count = 1;
            while (Plant::where('organization_id', $user->organization_id)->where('slug', $validated['slug'])->where('id', '!=', $plant->id)->exists()) {
                $validated['slug'] = $slugBase.'-'.$count++;
            }
        }

        $plant->update($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Plant updated successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Remove the specified plant from storage.
     */
    public function destroy(Request $request, Plant $plant): RedirectResponse
    {
        $user = $request->user();
        if (! $user || $plant->organization_id !== $user->organization_id) {
            abort(403);
        }

        $plantsCount = Plant::where('organization_id', $user->organization_id)->count();
        if ($plantsCount <= 1) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Unable to delete plant. At least one plant must exist.',
            ]);

            return redirect()->back()->withErrors(['error' => 'You cannot delete the last plant. At least one plant must exist.']);
        }

        // A plant must NEVER be deleted once it is attached or referenced anywhere in the system.
        if ($plant->hasBlockingDependencies()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Unable to delete plant. It is referenced by warehouses, departments, employees, work centers, machines, shifts, inventory records, or routings. Deactivate it instead.',
            ]);

            return redirect()->back()->withErrors([
                'error' => 'This plant cannot be deleted because it is referenced by other records. Deactivate the plant instead.',
            ]);
        }

        $wasActive = ($user->active_plant_id === $plant->id);

        $plant->delete();

        if ($wasActive) {
            $nextActivePlant = Plant::where('organization_id', $user->organization_id)->first();
            $user->update([
                'active_plant_id' => $nextActivePlant?->id,
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Plant deleted successfully.',
        ]);

        return redirect()->back();
    }
}
