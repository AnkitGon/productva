<?php

namespace App\Services;

use App\Models\BomHeader;
use App\Models\BomItem;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BomService
{
    /**
     * Create a BOM header with component lines.
     *
     * @param  array{
     *     product_id: int,
     *     version: string,
     *     is_default?: bool,
     *     effective_from?: string|null,
     *     effective_to?: string|null,
     *     status?: string,
     *     notes?: string|null,
     *     items: list<array{
     *         component_product_id: int,
     *         quantity: float|int|string,
     *         uom_id: int,
     *         scrap_percentage?: float|int|string|null,
     *         sequence?: int|null,
     *         notes?: string|null
     *     }>
     * }  $data
     */
    public function create(User $user, array $data): BomHeader
    {
        return DB::transaction(function () use ($user, $data) {
            $this->assertValidBomStructure($user, (int) $data['product_id'], $data['items']);

            if (! empty($data['is_default'])) {
                $this->clearDefaultForProduct($user, (int) $data['product_id']);
            } elseif ($this->shouldBecomeDefault($user, (int) $data['product_id'])) {
                $data['is_default'] = true;
            }

            $header = BomHeader::create([
                'organization_id' => $user->organization_id,
                'product_id' => $data['product_id'],
                'version' => $data['version'],
                'is_default' => (bool) ($data['is_default'] ?? false),
                'effective_from' => $data['effective_from'] ?? null,
                'effective_to' => $data['effective_to'] ?? null,
                'status' => $data['status'] ?? 'Draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->syncItems($header, $data['items']);

            return $header->fresh(['product', 'items.component', 'items.uom']);
        });
    }

    /**
     * Update a BOM header and replace component lines.
     *
     * @param  array{
     *     product_id: int,
     *     version: string,
     *     is_default?: bool,
     *     effective_from?: string|null,
     *     effective_to?: string|null,
     *     status?: string,
     *     notes?: string|null,
     *     items: list<array{
     *         component_product_id: int,
     *         quantity: float|int|string,
     *         uom_id: int,
     *         scrap_percentage?: float|int|string|null,
     *         sequence?: int|null,
     *         notes?: string|null
     *     }>
     * }  $data
     */
    public function update(User $user, BomHeader $bom, array $data): BomHeader
    {
        return DB::transaction(function () use ($user, $bom, $data) {
            $this->assertValidBomStructure(
                $user,
                (int) $data['product_id'],
                $data['items'],
                $bom->id,
            );

            if (! empty($data['is_default'])) {
                $this->clearDefaultForProduct($user, (int) $data['product_id'], $bom->id);
            }

            $bom->update([
                'product_id' => $data['product_id'],
                'version' => $data['version'],
                'is_default' => (bool) ($data['is_default'] ?? false),
                'effective_from' => $data['effective_from'] ?? null,
                'effective_to' => $data['effective_to'] ?? null,
                'status' => $data['status'] ?? $bom->status,
                'notes' => $data['notes'] ?? null,
                'updated_by' => $user->id,
            ]);

            $bom->items()->delete();
            $this->syncItems($bom, $data['items']);

            return $bom->fresh(['product', 'items.component', 'items.uom']);
        });
    }

    /**
     * Copy a BOM into a new Draft version for the same product.
     */
    public function copy(User $user, BomHeader $source): BomHeader
    {
        $source->loadMissing('items');

        $items = $source->items->map(fn (BomItem $item) => [
            'component_product_id' => $item->component_product_id,
            'quantity' => $item->quantity,
            'uom_id' => $item->uom_id,
            'scrap_percentage' => $item->scrap_percentage,
            'sequence' => $item->sequence,
            'notes' => $item->notes,
        ])->all();

        return $this->create($user, [
            'product_id' => $source->product_id,
            'version' => $this->nextVersionForProduct($user, (int) $source->product_id, $source->version),
            'is_default' => false,
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'status' => 'Draft',
            'notes' => $source->notes,
            'items' => $items,
        ]);
    }

    /**
     * Suggest the next unique version string for a product (e.g. 1.0 → 1.1).
     */
    public function nextVersionForProduct(User $user, int $productId, string $currentVersion): string
    {
        if (preg_match('/^(\d+)\.(\d+)$/', $currentVersion, $matches) === 1) {
            $major = (int) $matches[1];
            $minor = (int) $matches[2] + 1;

            do {
                $candidate = sprintf('%d.%d', $major, $minor);
                $exists = BomHeader::query()
                    ->where('organization_id', $user->organization_id)
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

        while (BomHeader::query()
            ->where('organization_id', $user->organization_id)
            ->where('product_id', $productId)
            ->where('version', $candidate)
            ->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @param  list<array{
     *     component_product_id: int,
     *     quantity: float|int|string,
     *     uom_id: int,
     *     scrap_percentage?: float|int|string|null,
     *     sequence?: int|null,
     *     notes?: string|null
     * }>  $items
     */
    public function assertValidBomStructure(
        User $user,
        int $productId,
        array $items,
        ?int $ignoreBomId = null,
    ): void {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'A BOM must include at least one component.',
            ]);
        }

        $product = Product::query()
            ->where('organization_id', $user->organization_id)
            ->findOrFail($productId);

        $componentIds = [];

        foreach ($items as $index => $item) {
            $componentId = (int) $item['component_product_id'];
            $key = "items.{$index}.component_product_id";

            if ($componentId === $product->id) {
                throw ValidationException::withMessages([
                    $key => 'A BOM cannot contain the parent product as a component.',
                ]);
            }

            if (in_array($componentId, $componentIds, true)) {
                throw ValidationException::withMessages([
                    $key => 'Duplicate components are not allowed in the same BOM.',
                ]);
            }

            $componentIds[] = $componentId;

            $component = Product::query()
                ->where('organization_id', $user->organization_id)
                ->find($componentId);

            if (! $component) {
                throw ValidationException::withMessages([
                    $key => 'Selected component product is invalid.',
                ]);
            }

            $uom = UnitOfMeasure::query()
                ->where('organization_id', $user->organization_id)
                ->find((int) $item['uom_id']);

            if (! $uom) {
                throw ValidationException::withMessages([
                    "items.{$index}.uom_id" => 'Selected unit of measure is invalid.',
                ]);
            }

            if ($this->wouldCreateCircularBom(
                organizationId: (int) $user->organization_id,
                parentProductId: $product->id,
                componentProductId: $componentId,
                ignoreBomId: $ignoreBomId,
            )) {
                throw ValidationException::withMessages([
                    $key => 'This component would create a circular BOM.',
                ]);
            }
        }
    }

    /**
     * Walk component BOM trees; if the parent product appears, a cycle exists.
     */
    public function wouldCreateCircularBom(
        int $organizationId,
        int $parentProductId,
        int $componentProductId,
        ?int $ignoreBomId = null,
    ): bool {
        if ($parentProductId === $componentProductId) {
            return true;
        }

        $visited = [];
        $queue = [$componentProductId];

        while ($queue !== []) {
            $current = array_shift($queue);

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            if ($current === $parentProductId) {
                return true;
            }

            $bomQuery = BomHeader::query()
                ->where('organization_id', $organizationId)
                ->where('product_id', $current)
                ->whereIn('status', ['Draft', 'Active']);

            if ($ignoreBomId !== null) {
                $bomQuery->where('id', '!=', $ignoreBomId);
            }

            $childIds = BomItem::query()
                ->whereIn('bom_header_id', $bomQuery->select('id'))
                ->pluck('component_product_id');

            foreach ($childIds as $childId) {
                $queue[] = (int) $childId;
            }
        }

        return false;
    }

    /**
     * @param  list<array{
     *     component_product_id: int,
     *     quantity: float|int|string,
     *     uom_id: int,
     *     scrap_percentage?: float|int|string|null,
     *     sequence?: int|null,
     *     notes?: string|null
     * }>  $items
     */
    private function syncItems(BomHeader $header, array $items): void
    {
        foreach (array_values($items) as $index => $item) {
            BomItem::create([
                'bom_header_id' => $header->id,
                'component_product_id' => $item['component_product_id'],
                'quantity' => $item['quantity'],
                'uom_id' => $item['uom_id'],
                'scrap_percentage' => $item['scrap_percentage'] ?? 0,
                'sequence' => $item['sequence'] ?? (($index + 1) * 10),
                'notes' => $item['notes'] ?? null,
            ]);
        }
    }

    private function clearDefaultForProduct(User $user, int $productId, ?int $exceptBomId = null): void
    {
        $query = BomHeader::query()
            ->where('organization_id', $user->organization_id)
            ->where('product_id', $productId)
            ->where('is_default', true);

        if ($exceptBomId !== null) {
            $query->where('id', '!=', $exceptBomId);
        }

        $query->update(['is_default' => false]);
    }

    private function shouldBecomeDefault(User $user, int $productId): bool
    {
        return ! BomHeader::query()
            ->where('organization_id', $user->organization_id)
            ->where('product_id', $productId)
            ->where('is_default', true)
            ->exists();
    }
}
