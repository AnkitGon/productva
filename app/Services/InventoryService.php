<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /**
     * Find an existing inventory balance row, or null when none exists.
     */
    public function findBalance(
        User $user,
        int $warehouseId,
        int $locationId,
        int $productId,
        ?string $lotNumber = null,
        ?string $serialNumber = null,
    ): ?Inventory {
        return Inventory::query()
            ->where('organization_id', $user->organization_id)
            ->where('plant_id', $user->active_plant_id)
            ->where('warehouse_id', $warehouseId)
            ->where('warehouse_location_id', $locationId)
            ->where('product_id', $productId)
            ->where('lot_number', $this->normalizeTraceability($lotNumber))
            ->where('serial_number', $this->normalizeTraceability($serialNumber))
            ->first();
    }

    /**
     * Find or create an inventory balance row for the unique stock combination.
     */
    public function findOrCreateBalance(
        User $user,
        int $warehouseId,
        int $locationId,
        int $productId,
        ?string $lotNumber = null,
        ?string $serialNumber = null,
    ): Inventory {
        $lot = $this->normalizeTraceability($lotNumber);
        $serial = $this->normalizeTraceability($serialNumber);

        return Inventory::query()->firstOrCreate(
            [
                'organization_id' => $user->organization_id,
                'plant_id' => $user->active_plant_id,
                'warehouse_id' => $warehouseId,
                'warehouse_location_id' => $locationId,
                'product_id' => $productId,
                'lot_number' => $lot,
                'serial_number' => $serial,
            ],
            [
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
            ]
        );
    }

    /**
     * Apply a stock adjustment by setting the absolute new on-hand quantity.
     *
     * @param  array{
     *     warehouse_id: int,
     *     warehouse_location_id: int,
     *     product_id: int,
     *     new_quantity: float|int|string,
     *     lot_number?: string|null,
     *     serial_number?: string|null,
     *     notes?: string|null,
     *     reference_number?: string|null
     * }  $data
     */
    public function adjust(User $user, array $data): Inventory
    {
        return DB::transaction(function () use ($user, $data) {
            [$product, $warehouse, $location] = $this->resolveStockTargets($user, $data);

            $this->assertTraceability($product, $data['lot_number'] ?? null, $data['serial_number'] ?? null);

            $inventory = $this->findOrCreateBalance(
                $user,
                $warehouse->id,
                $location->id,
                $product->id,
                $data['lot_number'] ?? null,
                $data['serial_number'] ?? null,
            );

            $before = (float) $inventory->quantity_on_hand;
            $after = (float) $data['new_quantity'];
            $quantity = $after - $before;

            if ($quantity == 0.0) {
                throw ValidationException::withMessages([
                    'new_quantity' => 'New quantity must differ from current stock.',
                ]);
            }

            $this->assertStockAllowed($warehouse, $product, $inventory, $after);

            $inventory->update([
                'quantity_on_hand' => $after,
                'last_movement_at' => now(),
            ]);

            $this->recordTransaction(
                user: $user,
                inventory: $inventory,
                product: $product,
                warehouse: $warehouse,
                location: $location,
                quantity: $quantity,
                before: $before,
                after: $after,
                transactionType: 'Adjustment',
                fromWarehouseId: $quantity < 0 ? $warehouse->id : null,
                fromLocationId: $quantity < 0 ? $location->id : null,
                toWarehouseId: $quantity > 0 ? $warehouse->id : null,
                toLocationId: $quantity > 0 ? $location->id : null,
                lotNumber: $data['lot_number'] ?? null,
                serialNumber: $data['serial_number'] ?? null,
                notes: $data['notes'] ?? null,
                referenceNumber: $data['reference_number'] ?? null,
            );

            return $inventory->fresh(['product', 'warehouse', 'location']);
        });
    }

    /**
     * Transfer stock between locations in the active plant.
     *
     * @param  array{
     *     product_id: int,
     *     from_warehouse_id: int,
     *     from_location_id: int,
     *     to_warehouse_id: int,
     *     to_location_id: int,
     *     quantity: float|int|string,
     *     lot_number?: string|null,
     *     serial_number?: string|null,
     *     notes?: string|null,
     *     reference_number?: string|null
     * }  $data
     * @return array{0: Inventory, 1: Inventory}
     */
    public function transfer(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data) {
            $quantity = (float) $data['quantity'];
            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Transfer quantity must be greater than zero.',
                ]);
            }

            if (
                (int) $data['from_warehouse_id'] === (int) $data['to_warehouse_id']
                && (int) $data['from_location_id'] === (int) $data['to_location_id']
            ) {
                throw ValidationException::withMessages([
                    'to_warehouse_location_id' => 'Destination must differ from the source location.',
                ]);
            }

            $product = Product::query()
                ->where('organization_id', $user->organization_id)
                ->findOrFail($data['product_id']);

            $fromWarehouse = Warehouse::query()
                ->where('organization_id', $user->organization_id)
                ->where('plant_id', $user->active_plant_id)
                ->findOrFail($data['from_warehouse_id']);

            $fromLocation = WarehouseLocation::query()
                ->where('organization_id', $user->organization_id)
                ->where('warehouse_id', $fromWarehouse->id)
                ->findOrFail($data['from_location_id']);

            $toWarehouse = Warehouse::query()
                ->where('organization_id', $user->organization_id)
                ->where('plant_id', $user->active_plant_id)
                ->findOrFail($data['to_warehouse_id']);

            $toLocation = WarehouseLocation::query()
                ->where('organization_id', $user->organization_id)
                ->where('warehouse_id', $toWarehouse->id)
                ->findOrFail($data['to_location_id']);

            $this->assertTraceability($product, $data['lot_number'] ?? null, $data['serial_number'] ?? null);

            $fromInventory = $this->findOrCreateBalance(
                $user,
                $fromWarehouse->id,
                $fromLocation->id,
                $product->id,
                $data['lot_number'] ?? null,
                $data['serial_number'] ?? null,
            );

            $toInventory = $this->findOrCreateBalance(
                $user,
                $toWarehouse->id,
                $toLocation->id,
                $product->id,
                $data['lot_number'] ?? null,
                $data['serial_number'] ?? null,
            );

            $fromBefore = (float) $fromInventory->quantity_on_hand;
            $fromAfter = $fromBefore - $quantity;
            $this->assertStockAllowed($fromWarehouse, $product, $fromInventory, $fromAfter);

            $toBefore = (float) $toInventory->quantity_on_hand;
            $toAfter = $toBefore + $quantity;
            $this->assertStockAllowed($toWarehouse, $product, $toInventory, $toAfter);

            $transactionNo = $this->nextTransactionNo((int) $user->organization_id, 'TRF');

            $fromInventory->update([
                'quantity_on_hand' => $fromAfter,
                'last_movement_at' => now(),
            ]);

            $toInventory->update([
                'quantity_on_hand' => $toAfter,
                'last_movement_at' => now(),
            ]);

            $this->recordTransaction(
                user: $user,
                inventory: $fromInventory,
                product: $product,
                warehouse: $fromWarehouse,
                location: $fromLocation,
                quantity: -$quantity,
                before: $fromBefore,
                after: $fromAfter,
                transactionType: 'Transfer Out',
                fromWarehouseId: $fromWarehouse->id,
                fromLocationId: $fromLocation->id,
                toWarehouseId: $toWarehouse->id,
                toLocationId: $toLocation->id,
                lotNumber: $data['lot_number'] ?? null,
                serialNumber: $data['serial_number'] ?? null,
                notes: $data['notes'] ?? null,
                referenceNumber: $data['reference_number'] ?? null,
                transactionNo: $transactionNo,
            );

            $this->recordTransaction(
                user: $user,
                inventory: $toInventory,
                product: $product,
                warehouse: $toWarehouse,
                location: $toLocation,
                quantity: $quantity,
                before: $toBefore,
                after: $toAfter,
                transactionType: 'Transfer In',
                fromWarehouseId: $fromWarehouse->id,
                fromLocationId: $fromLocation->id,
                toWarehouseId: $toWarehouse->id,
                toLocationId: $toLocation->id,
                lotNumber: $data['lot_number'] ?? null,
                serialNumber: $data['serial_number'] ?? null,
                notes: $data['notes'] ?? null,
                referenceNumber: $data['reference_number'] ?? null,
                transactionNo: $transactionNo,
            );

            return [
                $fromInventory->fresh(['product', 'warehouse', 'location']),
                $toInventory->fresh(['product', 'warehouse', 'location']),
            ];
        });
    }

    public function nextTransactionNo(int $organizationId, string $prefix): string
    {
        $last = InventoryTransaction::query()
            ->where('organization_id', $organizationId)
            ->where('transaction_no', 'like', $prefix.'-%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('transaction_no');

        $next = 1;
        if (is_string($last) && preg_match('/^'.preg_quote($prefix, '/').'-(\d+)$/', $last, $matches) === 1) {
            $next = (int) $matches[1] + 1;
        }

        return sprintf('%s-%06d', $prefix, $next);
    }

    /**
     * @param  array{warehouse_id: int, warehouse_location_id: int, product_id: int}  $data
     * @return array{0: Product, 1: Warehouse, 2: WarehouseLocation}
     */
    private function resolveStockTargets(User $user, array $data): array
    {
        $product = Product::query()
            ->where('organization_id', $user->organization_id)
            ->findOrFail($data['product_id']);

        $warehouse = Warehouse::query()
            ->where('organization_id', $user->organization_id)
            ->where('plant_id', $user->active_plant_id)
            ->findOrFail($data['warehouse_id']);

        $location = WarehouseLocation::query()
            ->where('organization_id', $user->organization_id)
            ->where('warehouse_id', $warehouse->id)
            ->findOrFail($data['warehouse_location_id']);

        return [$product, $warehouse, $location];
    }

    private function assertStockAllowed(
        Warehouse $warehouse,
        Product $product,
        Inventory $inventory,
        float $after,
    ): void {
        if ($after < 0 && ! $warehouse->allow_negative_stock && ! $product->allow_negative_stock) {
            throw ValidationException::withMessages([
                'new_quantity' => 'This warehouse and product do not allow negative stock.',
                'quantity' => 'This warehouse and product do not allow negative stock.',
            ]);
        }

        if ((float) $inventory->quantity_reserved > $after) {
            throw ValidationException::withMessages([
                'new_quantity' => 'Reserved quantity cannot exceed on-hand quantity.',
                'quantity' => 'Reserved quantity cannot exceed on-hand quantity.',
            ]);
        }
    }

    private function recordTransaction(
        User $user,
        Inventory $inventory,
        Product $product,
        Warehouse $warehouse,
        WarehouseLocation $location,
        float $quantity,
        float $before,
        float $after,
        string $transactionType,
        ?int $fromWarehouseId,
        ?int $fromLocationId,
        ?int $toWarehouseId,
        ?int $toLocationId,
        ?string $lotNumber,
        ?string $serialNumber,
        ?string $notes,
        ?string $referenceNumber,
        ?string $transactionNo = null,
    ): InventoryTransaction {
        $prefix = InventoryTransaction::prefixFor($transactionType);
        $transactionNo ??= $this->nextTransactionNo((int) $user->organization_id, $prefix);

        return InventoryTransaction::create([
            'organization_id' => $user->organization_id,
            'plant_id' => $user->active_plant_id,
            'inventory_id' => $inventory->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'warehouse_location_id' => $location->id,
            'from_warehouse_id' => $fromWarehouseId,
            'from_location_id' => $fromLocationId,
            'to_warehouse_id' => $toWarehouseId,
            'to_location_id' => $toLocationId,
            'lot_number' => $this->normalizeTraceability($lotNumber) ?: null,
            'serial_number' => $this->normalizeTraceability($serialNumber) ?: null,
            'transaction_no' => $transactionNo,
            'transaction_type' => $transactionType,
            'reference_number' => $referenceNumber,
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'notes' => $notes,
            'created_by' => $user->id,
            'transacted_at' => now(),
        ]);
    }

    private function assertTraceability(Product $product, ?string $lotNumber, ?string $serialNumber): void
    {
        $lot = $this->normalizeTraceability($lotNumber);
        $serial = $this->normalizeTraceability($serialNumber);

        if ($product->lot_tracking && $lot === '') {
            throw ValidationException::withMessages([
                'lot_number' => 'Lot number is required for this product.',
            ]);
        }

        if (! $product->lot_tracking && $lot !== '') {
            throw ValidationException::withMessages([
                'lot_number' => 'Lot number is not allowed for this product.',
            ]);
        }

        if ($product->serial_tracking && $serial === '') {
            throw ValidationException::withMessages([
                'serial_number' => 'Serial number is required for this product.',
            ]);
        }

        if (! $product->serial_tracking && $serial !== '') {
            throw ValidationException::withMessages([
                'serial_number' => 'Serial number is not allowed for this product.',
            ]);
        }

        if ($lot !== '' && $serial !== '') {
            throw ValidationException::withMessages([
                'serial_number' => 'Lot and serial tracking cannot both be used on the same balance.',
            ]);
        }
    }

    private function normalizeTraceability(?string $value): string
    {
        return trim((string) $value);
    }
}
