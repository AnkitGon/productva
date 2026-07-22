<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ShiftController extends Controller
{
    /**
     * Display a listing of shifts for the active plant.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $query = Shift::query()
            ->forActivePlant($user)
            ->withCount(['employees' => fn ($q) => $q->where('plant_id', $user->active_plant_id)]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('overnight')) {
            $query->where('overnight', $request->overnight === '1' || $request->overnight === 'true');
        }

        if ($request->filled('start_time')) {
            $query->where('start_time', $request->start_time);
        }

        $allowedSorts = ['code', 'name', 'start_time', 'end_time', 'working_minutes', 'status', 'employees_count', 'created_at'];
        $sortBy = in_array($request->get('sort_by'), $allowedSorts, true) ? $request->get('sort_by') : 'start_time';
        $sortDir = $request->get('sort_dir') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir);

        return Inertia::render('shifts/index', [
            'shifts' => $query->paginate($perPage)->withQueryString(),
            'filters' => $request->only(['search', 'status', 'overnight', 'start_time', 'sort_by', 'sort_dir', 'per_page']),
            'templates' => $this->templates(),
        ]);
    }

    /**
     * Store a newly created shift for the active plant.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $validated = $this->validateShift($request, (int) $user->active_plant_id);
        $this->assertBusinessRules($validated);

        Shift::create([
            ...$validated,
            'organization_id' => $user->organization_id,
            'plant_id' => $user->active_plant_id,
            'working_minutes' => Shift::calculateWorkingMinutes(
                $validated['start_time'],
                $validated['end_time'],
                (bool) $validated['overnight'],
                (int) $validated['break_minutes'],
            ),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Shift created successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Update the specified shift.
     */
    public function update(Request $request, Shift $shift)
    {
        $user = $request->user();
        if (! $user || ! $this->shiftBelongsToActivePlant($user, $shift)) {
            abort(403);
        }

        $validated = $this->validateShift($request, (int) $user->active_plant_id, $shift);
        $this->assertBusinessRules($validated);

        $shift->update([
            ...$validated,
            'working_minutes' => Shift::calculateWorkingMinutes(
                $validated['start_time'],
                $validated['end_time'],
                (bool) $validated['overnight'],
                (int) $validated['break_minutes'],
            ),
            'updated_by' => $user->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Shift updated successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Archive (soft delete) the specified shift.
     */
    public function destroy(Request $request, Shift $shift)
    {
        $user = $request->user();
        if (! $user || ! $this->shiftBelongsToActivePlant($user, $shift)) {
            abort(403);
        }

        if ($shift->employees()->where('plant_id', $user->active_plant_id)->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Cannot archive this shift while employees are still assigned. Reassign them first.',
            ]);

            return redirect()->back();
        }

        $shift->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Shift archived successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateShift(Request $request, int $plantId, ?Shift $shift = null): array
    {
        foreach (['start_time', 'end_time'] as $timeField) {
            $value = $request->input($timeField);
            if (is_string($value) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1) {
                $request->merge([$timeField => substr($value, 0, 5)]);
            }
        }

        $request->merge([
            'overnight' => $request->boolean('overnight'),
            'break_minutes' => $request->filled('break_minutes') ? $request->integer('break_minutes') : 0,
            'grace_in_minutes' => $request->filled('grace_in_minutes') ? $request->integer('grace_in_minutes') : 0,
            'grace_out_minutes' => $request->filled('grace_out_minutes') ? $request->integer('grace_out_minutes') : 0,
            'notes' => $request->filled('notes') ? $request->input('notes') : null,
            'code' => strtoupper(trim((string) $request->input('code', ''))),
        ]);

        $uniqueCode = Rule::unique('shifts', 'code')
            ->where(fn ($query) => $query
                ->where('plant_id', $plantId)
                ->whereNull('deleted_at'));

        if ($shift) {
            $uniqueCode->ignore($shift->id);
        }

        return $request->validate([
            'code' => ['required', 'string', 'max:20', $uniqueCode],
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'overnight' => ['required', 'boolean'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'grace_in_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'grace_out_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertBusinessRules(array $validated): void
    {
        $start = (string) $validated['start_time'];
        $end = (string) $validated['end_time'];
        $overnight = (bool) $validated['overnight'];
        $breakMinutes = (int) ($validated['break_minutes'] ?? 0);
        $graceIn = (int) ($validated['grace_in_minutes'] ?? 0);
        $graceOut = (int) ($validated['grace_out_minutes'] ?? 0);

        if ($start === $end) {
            throw ValidationException::withMessages([
                'end_time' => 'End time cannot be the same as start time.',
            ]);
        }

        if ($overnight && $end >= $start) {
            throw ValidationException::withMessages([
                'overnight' => 'Overnight shifts must end earlier on the clock than they start (next calendar day).',
            ]);
        }

        if (! $overnight && $end < $start) {
            throw ValidationException::withMessages([
                'overnight' => 'Enable overnight when the end time is on the next day.',
            ]);
        }

        $duration = Shift::durationMinutes($start, $end, $overnight);

        if ($breakMinutes > $duration) {
            throw ValidationException::withMessages([
                'break_minutes' => 'Break cannot exceed the shift duration.',
            ]);
        }

        if ($graceIn > $duration) {
            throw ValidationException::withMessages([
                'grace_in_minutes' => 'Grace in cannot exceed the shift duration.',
            ]);
        }

        if ($graceOut > $duration) {
            throw ValidationException::withMessages([
                'grace_out_minutes' => 'Grace out cannot exceed the shift duration.',
            ]);
        }
    }

    private function shiftBelongsToActivePlant($user, Shift $shift): bool
    {
        return (int) $shift->organization_id === (int) $user->organization_id
            && (int) $shift->plant_id === (int) $user->active_plant_id;
    }

    /**
     * @return list<array{name: string, code: string, start_time: string, end_time: string, overnight: bool, break_minutes: int}>
     */
    private function templates(): array
    {
        return [
            [
                'name' => 'Morning Shift',
                'code' => 'MORN',
                'start_time' => '06:00',
                'end_time' => '14:00',
                'overnight' => false,
                'break_minutes' => 60,
            ],
            [
                'name' => 'Evening Shift',
                'code' => 'EVE',
                'start_time' => '14:00',
                'end_time' => '22:00',
                'overnight' => false,
                'break_minutes' => 60,
            ],
            [
                'name' => 'Night Shift',
                'code' => 'NIGHT',
                'start_time' => '22:00',
                'end_time' => '06:00',
                'overnight' => true,
                'break_minutes' => 60,
            ],
            [
                'name' => 'General Shift',
                'code' => 'GEN',
                'start_time' => '09:00',
                'end_time' => '18:00',
                'overnight' => false,
                'break_minutes' => 60,
            ],
        ];
    }
}
