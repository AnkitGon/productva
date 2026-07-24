<?php

namespace App\Services;

use App\Models\Machine;
use App\Models\Operation;
use App\Models\Product;
use App\Models\RoutingHeader;
use App\Models\RoutingOperation;
use App\Models\User;
use App\Models\WorkCenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoutingService
{
    /**
     * @param  array{
     *     product_id: int,
     *     version: string,
     *     is_default?: bool,
     *     effective_from?: string|null,
     *     effective_to?: string|null,
     *     status?: string,
     *     notes?: string|null,
     *     operations: list<array<string, mixed>>
     * }  $data
     */
    public function create(User $user, array $data): RoutingHeader
    {
        return DB::transaction(function () use ($user, $data) {
            $this->assertValidRoutingStructure($user, (int) $data['product_id'], $data['operations']);

            if (! empty($data['is_default'])) {
                $this->clearDefaultForProduct($user, (int) $data['product_id']);
            } elseif ($this->shouldBecomeDefault($user, (int) $data['product_id'])) {
                $data['is_default'] = true;
            }

            $status = $data['status'] ?? 'Draft';
            if ($status === 'Released') {
                $this->assertReadyForRelease($user, $data['operations']);
            }

            $header = RoutingHeader::create([
                'organization_id' => $user->organization_id,
                'plant_id' => $user->active_plant_id,
                'product_id' => $data['product_id'],
                'version' => $data['version'],
                'is_default' => (bool) ($data['is_default'] ?? false),
                'effective_from' => $data['effective_from'] ?? null,
                'effective_to' => $data['effective_to'] ?? null,
                'status' => $status,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->syncOperations($header, $data['operations']);

            return $header->fresh(['product', 'operations.workCenter', 'operations.machine', 'operations.operation']);
        });
    }

    /**
     * @param  array{
     *     product_id: int,
     *     version: string,
     *     is_default?: bool,
     *     effective_from?: string|null,
     *     effective_to?: string|null,
     *     status?: string,
     *     notes?: string|null,
     *     operations: list<array<string, mixed>>
     * }  $data
     */
    public function update(User $user, RoutingHeader $routing, array $data): RoutingHeader
    {
        return DB::transaction(function () use ($user, $routing, $data) {
            if (! $routing->is_editable) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft routings can be edited. Copy the routing to make changes.',
                ]);
            }

            $this->assertValidRoutingStructure($user, (int) $data['product_id'], $data['operations'], $routing->id);

            if (! empty($data['is_default'])) {
                $this->clearDefaultForProduct($user, (int) $data['product_id'], $routing->id);
            }

            $status = $data['status'] ?? $routing->status;
            if ($status === 'Released') {
                $this->assertReadyForRelease($user, $data['operations']);
            }

            $routing->update([
                'product_id' => $data['product_id'],
                'version' => $data['version'],
                'is_default' => (bool) ($data['is_default'] ?? false),
                'effective_from' => $data['effective_from'] ?? null,
                'effective_to' => $data['effective_to'] ?? null,
                'status' => $status,
                'notes' => $data['notes'] ?? null,
                'updated_by' => $user->id,
            ]);

            $routing->operations()->delete();
            $this->syncOperations($routing, $data['operations']);

            return $routing->fresh(['product', 'operations.workCenter', 'operations.machine', 'operations.operation']);
        });
    }

    public function release(User $user, RoutingHeader $routing): RoutingHeader
    {
        return DB::transaction(function () use ($user, $routing) {
            if ($routing->status !== 'Draft') {
                throw ValidationException::withMessages([
                    'status' => 'Only draft routings can be released.',
                ]);
            }

            $routing->loadMissing('operations');

            $payload = $routing->operations->map(fn (RoutingOperation $op) => [
                'sequence' => $op->sequence,
                'operation_id' => $op->operation_id,
                'work_center_id' => $op->work_center_id,
                'machine_id' => $op->machine_id,
                'run_time_per_unit' => $op->run_time_per_unit,
                'setup_time_minutes' => $op->setup_time_minutes,
            ])->all();

            $this->assertReadyForRelease($user, $payload);

            $routing->update([
                'status' => 'Released',
                'updated_by' => $user->id,
            ]);

            return $routing->fresh();
        });
    }

    public function obsolete(User $user, RoutingHeader $routing): RoutingHeader
    {
        return DB::transaction(function () use ($user, $routing) {
            if ($routing->status === 'Obsolete') {
                throw ValidationException::withMessages([
                    'status' => 'Routing is already obsolete.',
                ]);
            }

            $routing->update([
                'status' => 'Obsolete',
                'is_default' => false,
                'updated_by' => $user->id,
            ]);

            return $routing->fresh();
        });
    }

    public function copy(User $user, RoutingHeader $source): RoutingHeader
    {
        $source->loadMissing('operations');

        $operations = $source->operations->map(fn (RoutingOperation $op) => [
            'sequence' => $op->sequence,
            'operation_id' => $op->operation_id,
            'work_center_id' => $op->work_center_id,
            'machine_id' => $op->machine_id,
            'setup_time_minutes' => $op->setup_time_minutes,
            'run_time_per_unit' => $op->run_time_per_unit,
            'labor_time' => $op->labor_time,
            'queue_time' => $op->queue_time,
            'move_time' => $op->move_time,
            'wait_time' => $op->wait_time,
            'overlap_percent' => $op->overlap_percent,
            'notes' => $op->notes,
        ])->all();

        return $this->create($user, [
            'product_id' => $source->product_id,
            'version' => $this->nextVersionForProduct($user, (int) $source->product_id, $source->version),
            'is_default' => false,
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'status' => 'Draft',
            'notes' => $source->notes,
            'operations' => $operations,
        ]);
    }

    public function nextVersionForProduct(User $user, int $productId, string $currentVersion): string
    {
        if (preg_match('/^(\d+)\.(\d+)$/', $currentVersion, $matches) === 1) {
            $major = (int) $matches[1];
            $minor = (int) $matches[2] + 1;

            do {
                $candidate = sprintf('%d.%d', $major, $minor);
                $exists = RoutingHeader::query()
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id)
                    ->where('product_id', $productId)
                    ->where('version', $candidate)
                    ->exists();
                $minor++;
            } while ($exists);

            return $candidate;
        }

        $base = $currentVersion.'-copy';
        $candidate = $base;
        $suffix = 2;

        while (RoutingHeader::query()
            ->where('organization_id', $user->organization_id)
            ->where('plant_id', $user->active_plant_id)
            ->where('product_id', $productId)
            ->where('version', $candidate)
            ->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @param  list<array<string, mixed>>  $operations
     */
    public function assertValidRoutingStructure(
        User $user,
        int $productId,
        array $operations,
        ?int $ignoreRoutingId = null,
    ): void {
        if ($operations === []) {
            throw ValidationException::withMessages([
                'operations' => 'A routing must include at least one operation.',
            ]);
        }

        $product = Product::query()
            ->where('organization_id', $user->organization_id)
            ->findOrFail($productId);

        if (! in_array($product->type, ['Finished Good', 'Semi Finished'], true)) {
            throw ValidationException::withMessages([
                'product_id' => 'Only finished goods and semi-finished products can have routings.',
            ]);
        }

        $this->assertOperationLines($user, $operations);
    }

    /**
     * Full release gate: no empty lines, unique sequences, active WC/machine, run time, active operation master.
     *
     * @param  list<array<string, mixed>>  $operations
     */
    public function assertReadyForRelease(User $user, array $operations): void
    {
        if ($operations === []) {
            throw ValidationException::withMessages([
                'operations' => 'Cannot release a routing without operations.',
            ]);
        }

        $this->assertOperationLines($user, $operations, requireActiveMachine: true);
    }

    /**
     * @param  list<array<string, mixed>>  $operations
     */
    private function assertOperationLines(User $user, array $operations, bool $requireActiveMachine = false): void
    {
        $sequences = [];

        foreach ($operations as $index => $row) {
            $sequence = (int) ($row['sequence'] ?? 0);
            $seqKey = "operations.{$index}.sequence";

            if ($sequence < 1) {
                throw ValidationException::withMessages([
                    $seqKey => 'Sequence must be at least 1.',
                ]);
            }

            if (in_array($sequence, $sequences, true)) {
                throw ValidationException::withMessages([
                    $seqKey => 'Operation sequences must be unique within a routing.',
                ]);
            }

            $sequences[] = $sequence;

            if (empty($row['operation_id'])) {
                throw ValidationException::withMessages([
                    "operations.{$index}.operation_id" => 'Operation is required.',
                ]);
            }

            $operation = Operation::query()
                ->where('organization_id', $user->organization_id)
                ->where('status', 'Active')
                ->find((int) $row['operation_id']);

            if (! $operation) {
                throw ValidationException::withMessages([
                    "operations.{$index}.operation_id" => 'Operation must be an active operation master.',
                ]);
            }

            $workCenter = WorkCenter::query()
                ->where('organization_id', $user->organization_id)
                ->where('plant_id', $user->active_plant_id)
                ->where('status', 'Active')
                ->find((int) $row['work_center_id']);

            if (! $workCenter) {
                throw ValidationException::withMessages([
                    "operations.{$index}.work_center_id" => 'Work center must be active and belong to the active plant.',
                ]);
            }

            if (! empty($row['machine_id'])) {
                $machineQuery = Machine::query()
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id)
                    ->where('work_center_id', $workCenter->id)
                    ->whereKey((int) $row['machine_id']);

                if ($requireActiveMachine) {
                    $machineQuery->whereIn('status', Machine::ASSIGNABLE_STATUSES);
                }

                $machine = $machineQuery->first();

                if (! $machine) {
                    throw ValidationException::withMessages([
                        "operations.{$index}.machine_id" => $requireActiveMachine
                            ? 'Machine must be active/assignable and belong to the selected work center.'
                            : 'Machine must belong to the selected work center.',
                    ]);
                }
            }

            if ((float) ($row['run_time_per_unit'] ?? 0) <= 0) {
                throw ValidationException::withMessages([
                    "operations.{$index}.run_time_per_unit" => 'Run time per unit must be greater than zero.',
                ]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $operations
     */
    private function syncOperations(RoutingHeader $header, array $operations): void
    {
        foreach (array_values($operations) as $index => $row) {
            RoutingOperation::create([
                'routing_header_id' => $header->id,
                'sequence' => $row['sequence'] ?? (($index + 1) * 10),
                'operation_id' => $row['operation_id'],
                'work_center_id' => $row['work_center_id'],
                'machine_id' => $row['machine_id'] ?: null,
                'setup_time_minutes' => $row['setup_time_minutes'] ?? 0,
                'run_time_per_unit' => $row['run_time_per_unit'],
                'labor_time' => $row['labor_time'] ?? 0,
                'queue_time' => $row['queue_time'] ?? 0,
                'move_time' => $row['move_time'] ?? 0,
                'wait_time' => $row['wait_time'] ?? 0,
                'overlap_percent' => $row['overlap_percent'] ?? 0,
                'notes' => $row['notes'] ?? null,
            ]);
        }
    }

    private function clearDefaultForProduct(User $user, int $productId, ?int $exceptId = null): void
    {
        $query = RoutingHeader::query()
            ->where('organization_id', $user->organization_id)
            ->where('plant_id', $user->active_plant_id)
            ->where('product_id', $productId)
            ->where('is_default', true);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['is_default' => false]);
    }

    private function shouldBecomeDefault(User $user, int $productId): bool
    {
        return ! RoutingHeader::query()
            ->where('organization_id', $user->organization_id)
            ->where('plant_id', $user->active_plant_id)
            ->where('product_id', $productId)
            ->where('is_default', true)
            ->exists();
    }
}
