<?php

namespace App\Services;

use App\Models\BomHeader;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\Machine;
use App\Models\Plant;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\RoutingHeader;
use App\Models\Shift;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WarehouseType;
use App\Models\WorkCenter;

class FactorySetupService
{
    /**
     * Open Getting Started only on the user's first login (while the guide is still available).
     */
    public function shouldOpenOnLogin(User $user): bool
    {
        if (! $this->showInSidebar($user)) {
            return false;
        }

        return $user->getting_started_prompted_at === null;
    }

    /**
     * Remember that this user has already been sent to Getting Started once.
     */
    public function markPrompted(User $user): void
    {
        if ($user->getting_started_prompted_at !== null) {
            return;
        }

        $user->forceFill([
            'getting_started_prompted_at' => now(),
        ])->save();
    }

    /**
     * Show Getting Started until the org dismisses it or is fully ready.
     */
    public function needsSetup(User $user): bool
    {
        if (! $user->organization_id || $user->hasRole('super-admin')) {
            return false;
        }

        $organization = $user->organization;

        if ($organization === null || $organization->setup_completed_at !== null) {
            return false;
        }

        return $this->roadmap($user)['percent_ready'] < 100;
    }

    /**
     * Show Getting Started in the sidebar until the user marks it completed.
     */
    public function showInSidebar(User $user): bool
    {
        if (! $user->organization_id || $user->hasRole('super-admin')) {
            return false;
        }

        return $user->organization?->setup_completed_at === null;
    }

    /**
     * Production Readiness roadmap (read-only). Status reflects business readiness.
     *
     * @return array<string, mixed>
     */
    public function roadmap(User $user): array
    {
        $organizationId = (int) $user->organization_id;
        $plantId = $user->active_plant_id ? (int) $user->active_plant_id : null;
        $metrics = $this->metrics($organizationId, $plantId);

        $required = [
            $this->item(
                key: 'plants',
                title: 'Plants',
                why: 'A plant is a factory or site. Daily inventory and manufacturing run inside a plant.',
                href: null,
                done: $metrics['plants'] > 0,
                metric: (string) $metrics['plants'],
                metricDetail: $metrics['plants'] === 1 ? '1 plant configured' : "{$metrics['plants']} plants configured",
                warnings: [],
                hint: 'Use the plant switcher in the top bar to add more sites.',
                category: 'configuration',
            ),
            $this->item(
                key: 'departments',
                title: 'Departments',
                why: 'Organize people and ownership across Production, Warehouse, Quality, and more.',
                href: '/departments',
                done: $metrics['departments'] > 0,
                metric: (string) $metrics['departments'],
                metricDetail: null,
                warnings: [],
                hint: null,
                category: 'configuration',
            ),
            $this->item(
                key: 'employees',
                title: 'Employees',
                why: 'Your team must be assigned to a plant (and ideally a shift) before operations start.',
                href: '/employees',
                done: $metrics['employees'] > 0 && $metrics['employees_unassigned'] === 0,
                metric: $metrics['employees'] > 0
                    ? "{$metrics['employees_assigned']} / {$metrics['employees']}"
                    : '0',
                metricDetail: $metrics['employees'] > 0
                    ? "{$metrics['employees_assigned']} of {$metrics['employees']} assigned to department & shift"
                    : 'No employees yet',
                warnings: array_values(array_filter([
                    $metrics['employees'] === 0 ? 'No employees invited' : null,
                    $metrics['employees_unassigned'] > 0
                        ? "{$metrics['employees_unassigned']} missing department or shift"
                        : null,
                ])),
                hint: null,
                category: 'resources',
            ),
            $this->item(
                key: 'products',
                title: 'Products',
                why: 'You need finished goods (and usually raw materials) before BOM, routing, and stock make sense.',
                href: '/products',
                done: $metrics['finished_goods'] > 0
                    && $metrics['products_missing_uom'] === 0
                    && $metrics['products_missing_category'] === 0,
                metric: (string) $metrics['products'],
                metricDetail: $metrics['finished_goods'] > 0
                    ? "{$metrics['products']} products · {$metrics['finished_goods']} finished goods"
                    : ($metrics['products'] > 0 ? "{$metrics['products']} products · no finished goods" : 'No products yet'),
                warnings: array_values(array_filter([
                    $metrics['finished_goods'] === 0 ? 'No finished product exists' : null,
                    $metrics['products_missing_uom'] > 0 ? "{$metrics['products_missing_uom']} missing UOM" : null,
                    $metrics['products_missing_category'] > 0 ? "{$metrics['products_missing_category']} missing category" : null,
                    $metrics['products_inactive'] > 0 ? "{$metrics['products_inactive']} inactive" : null,
                    $metrics['fg_missing_bom'] > 0 ? "{$metrics['fg_missing_bom']} finished goods missing BOM" : null,
                    $metrics['fg_missing_routing'] > 0 ? "{$metrics['fg_missing_routing']} finished goods missing routing" : null,
                ])),
                hint: null,
                category: 'inventory',
            ),
            $this->item(
                key: 'warehouses',
                title: 'Warehouses',
                why: 'Stock must live in warehouses. Locations tell you exactly where materials sit.',
                href: '/warehouses',
                done: $metrics['warehouses'] > 0 && $metrics['warehouses_without_locations'] === 0,
                metric: (string) $metrics['warehouses'],
                metricDetail: $metrics['locations'] > 0
                    ? "{$metrics['warehouses']} warehouses · {$metrics['locations']} locations"
                    : "{$metrics['warehouses']} warehouses",
                warnings: array_values(array_filter([
                    $metrics['warehouses'] === 0 ? 'No warehouses configured' : null,
                    ...$metrics['warehouse_location_warnings'],
                ])),
                hint: null,
                category: 'inventory',
            ),
            $this->item(
                key: 'boms',
                title: 'Bill of Materials (BOM)',
                why: 'Defines what materials are required. Required before creating production orders.',
                href: '/boms',
                done: $metrics['finished_goods'] > 0 && $metrics['fg_missing_bom'] === 0,
                metric: $metrics['finished_goods'] > 0
                    ? "{$metrics['fg_with_bom']} / {$metrics['finished_goods']}"
                    : '0',
                metricDetail: $metrics['finished_goods'] > 0
                    ? "{$metrics['fg_with_bom']} of {$metrics['finished_goods']} finished goods have a BOM"
                    : 'Add finished goods first',
                warnings: array_values(array_filter([
                    $metrics['finished_goods'] === 0 ? 'No finished products to cover' : null,
                    $metrics['fg_missing_bom'] > 0 ? "{$metrics['fg_missing_bom']} finished products have no BOM" : null,
                ])),
                hint: null,
                category: 'manufacturing',
            ),
            $this->item(
                key: 'routings',
                title: 'Routing',
                why: 'Defines how your product is manufactured. Required before creating production orders.',
                href: '/routings',
                done: $metrics['finished_goods'] > 0 && $metrics['fg_missing_routing'] === 0,
                metric: $metrics['finished_goods'] > 0
                    ? "{$metrics['fg_with_routing']} / {$metrics['finished_goods']}"
                    : '0',
                metricDetail: $metrics['finished_goods'] > 0
                    ? "{$metrics['fg_with_routing']} of {$metrics['finished_goods']} finished goods have a routing"
                    : 'Add finished goods first',
                warnings: array_values(array_filter([
                    $metrics['finished_goods'] === 0 ? 'No finished products to cover' : null,
                    $metrics['fg_missing_routing'] > 0 ? "{$metrics['fg_missing_routing']} finished products have no routing" : null,
                ])),
                hint: null,
                category: 'manufacturing',
            ),
            $this->item(
                key: 'opening_stock',
                title: 'Opening Stock',
                why: 'Existing inventory becomes your starting balance before live operations begin.',
                href: '/inventory',
                done: $metrics['opening_stock_lines'] > 0,
                metric: (string) $metrics['opening_stock_lines'],
                metricDetail: $metrics['opening_stock_lines'] > 0
                    ? "{$metrics['opening_stock_lines']} stock lines with quantity"
                    : 'No opening stock entered',
                warnings: $metrics['opening_stock_lines'] === 0 ? ['Opening stock not entered'] : [],
                hint: null,
                category: 'inventory',
            ),
        ];

        $optional = [
            $this->item(
                key: 'shifts',
                title: 'Shifts',
                why: 'Define working hours so employees can be scheduled.',
                href: '/shifts',
                done: $metrics['shifts'] > 0,
                metric: (string) $metrics['shifts'],
                metricDetail: null,
                warnings: [],
                hint: null,
                category: 'configuration',
                required: false,
            ),
            $this->item(
                key: 'roles',
                title: 'Roles & Permissions',
                why: 'Control who can view or change each part of the system.',
                href: '/admin/roles',
                done: $metrics['roles'] > 0,
                metric: (string) $metrics['roles'],
                metricDetail: null,
                warnings: [],
                hint: null,
                category: 'configuration',
                required: false,
            ),
            $this->item(
                key: 'work_centers',
                title: 'Work Centers',
                why: 'Production areas used on routings (Cutting, Assembly, Painting…).',
                href: '/work-centers',
                done: $metrics['work_centers'] > 0,
                metric: (string) $metrics['work_centers'],
                metricDetail: null,
                warnings: [],
                hint: null,
                category: 'resources',
                required: false,
            ),
            $this->item(
                key: 'machines',
                title: 'Machines',
                why: 'Assign equipment to work centers for capacity and routing detail.',
                href: '/machines',
                done: $metrics['machines'] > 0 && $metrics['machines_bad_work_center'] === 0,
                metric: (string) $metrics['machines'],
                metricDetail: null,
                warnings: array_values(array_filter([
                    $metrics['machines_bad_work_center'] > 0
                        ? "{$metrics['machines_bad_work_center']} machines on inactive/missing work centers"
                        : null,
                ])),
                hint: null,
                category: 'resources',
                required: false,
            ),
            $this->item(
                key: 'uom',
                title: 'Units of Measure',
                why: 'Standardize how quantities are counted (PCS, KG, M, L).',
                href: '/units-of-measure',
                done: $metrics['uom'] > 0,
                metric: (string) $metrics['uom'],
                metricDetail: null,
                warnings: [],
                hint: null,
                category: 'inventory',
                required: false,
            ),
            $this->item(
                key: 'categories',
                title: 'Product Categories',
                why: 'Group products for reporting and navigation.',
                href: '/product-categories',
                done: $metrics['categories'] > 0,
                metric: (string) $metrics['categories'],
                metricDetail: null,
                warnings: [],
                hint: null,
                category: 'inventory',
                required: false,
            ),
            $this->item(
                key: 'warehouse_types',
                title: 'Warehouse Types',
                why: 'Classify warehouses (Raw, FG, WIP, Scrap).',
                href: '/warehouse-types',
                done: $metrics['warehouse_types'] > 0,
                metric: (string) $metrics['warehouse_types'],
                metricDetail: null,
                warnings: [],
                hint: null,
                category: 'inventory',
                required: false,
            ),
            $this->item(
                key: 'locations',
                title: 'Warehouse Locations',
                why: 'Bins, racks, and shelves for precise stock placement.',
                href: '/warehouse-locations',
                done: $metrics['locations'] > 0,
                metric: (string) $metrics['locations'],
                metricDetail: null,
                warnings: [],
                hint: null,
                category: 'inventory',
                required: false,
            ),
            $this->item(
                key: 'future_modules',
                title: 'Future Modules',
                why: 'Purchase, sales, quality, and maintenance arrive in later releases.',
                href: null,
                done: false,
                metric: '—',
                metricDetail: 'Not available yet',
                warnings: [],
                hint: null,
                category: 'configuration',
                required: false,
            ),
        ];

        $problems = $this->problems($metrics);
        $health = $this->factoryHealth($required, $optional);
        $percentReady = $this->percentReady($required);
        $nextRequired = collect($required)->first(fn (array $item) => ! $item['done']);

        return [
            'percent_ready' => $percentReady,
            'percent_complete' => $percentReady, // backward-compatible for older UI/tests
            'ready' => $percentReady === 100 && $problems === [],
            'required_done' => collect($required)->where('done', true)->count(),
            'required_total' => count($required),
            'done_count' => collect($required)->where('done', true)->count(),
            'total_count' => count($required),
            'next_required' => $nextRequired ? [
                'key' => $nextRequired['key'],
                'title' => $nextRequired['title'],
                'href' => $nextRequired['href'],
            ] : null,
            'next_item' => $nextRequired ? [
                'key' => $nextRequired['key'],
                'title' => $nextRequired['title'],
                'href' => $nextRequired['href'],
            ] : null,
            'dismissed' => $user->organization?->setup_completed_at !== null,
            'required' => $required,
            'optional' => $optional,
            'problems' => $problems,
            'factory_health' => $health,
            'go_live' => [
                'title' => 'Your factory is ready.',
                'subtitle' => 'Estimated time to start: 15 minutes',
                'next_steps' => [
                    'Create your first Production Order',
                    'Receive Raw Materials',
                    'Start Production',
                    'Track Inventory',
                ],
            ],
            'daily_operations' => [
                'Production Orders',
                'Issue Materials',
                'Start Production',
                'Complete Operations',
                'Receive Finished Goods',
                'Inventory Updated Automatically',
            ],
            'future_modules' => [
                'Suppliers',
                'Purchase Orders',
                'Goods Receipt',
                'Customers',
                'Sales Orders',
                'Quality',
                'Maintenance',
                'Reports',
                'Dashboards',
            ],
        ];
    }

    /**
     * Compact dashboard payload.
     *
     * @return array<string, mixed>
     */
    public function dashboardProgress(User $user): array
    {
        $roadmap = $this->roadmap($user);

        return [
            'percent_ready' => $roadmap['percent_ready'],
            'percent_complete' => $roadmap['percent_ready'],
            'ready' => $roadmap['ready'],
            'dismissed' => $roadmap['dismissed'],
            'required_done' => $roadmap['required_done'],
            'required_total' => $roadmap['required_total'],
            'done_count' => $roadmap['done_count'],
            'total_count' => $roadmap['total_count'],
            'next_required' => $roadmap['next_required'],
            'next_item' => $roadmap['next_required'],
            'problems' => array_slice($roadmap['problems'], 0, 5),
            'factory_health' => $roadmap['factory_health'],
            'rows' => collect($roadmap['required'])->map(fn (array $item) => [
                'title' => $item['title'],
                'done' => $item['done'],
                'href' => $item['href'],
                'metric' => $item['metric'],
                'warnings' => $item['warnings'],
            ])->values()->all(),
            'go_live' => $roadmap['go_live'],
        ];
    }

    public function dismiss(User $user): void
    {
        $user->organization?->update([
            'setup_completed_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function metrics(int $organizationId, ?int $plantId): array
    {
        $plantScoped = fn ($query) => $plantId
            ? $query->where('organization_id', $organizationId)->where('plant_id', $plantId)
            : $query->where('organization_id', $organizationId)->whereRaw('1 = 0');

        $products = Product::query()->where('organization_id', $organizationId);
        $productCount = (clone $products)->count();
        $productsInactive = (clone $products)->where('status', '!=', 'Active')->count();
        $productsMissingUom = (clone $products)->where('status', 'Active')->whereNull('uom_id')->count();
        $productsMissingCategory = (clone $products)->where('status', 'Active')->whereNull('category_id')->count();

        $finishedGoods = Product::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'Active')
            ->where('type', 'Finished Good');
        $fgCount = (clone $finishedGoods)->count();

        $bomProductIds = BomHeader::query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', ['Draft', 'Active'])
            ->select('product_id');

        $fgWithBom = (clone $finishedGoods)->whereIn('id', $bomProductIds)->count();

        $routingProductIds = $plantId
            ? RoutingHeader::query()
                ->where('organization_id', $organizationId)
                ->where('plant_id', $plantId)
                ->whereIn('status', ['Draft', 'Released'])
                ->select('product_id')
            : RoutingHeader::query()->whereRaw('1 = 0')->select('product_id');

        $fgWithRouting = (clone $finishedGoods)->whereIn('id', $routingProductIds)->count();

        $employees = $plantScoped(Employee::query());
        $employeeCount = (clone $employees)->count();
        $employeesUnassigned = (clone $employees)
            ->where(function ($q) {
                $q->whereNull('department_id')->orWhereNull('shift_id');
            })
            ->count();

        $warehouses = $plantScoped(Warehouse::query())->with('locations:id,warehouse_id')->get(['id', 'code', 'name']);
        $warehousesWithoutLocations = $warehouses->filter(fn (Warehouse $w) => $w->locations->isEmpty());
        $warehouseLocationWarnings = $warehousesWithoutLocations
            ->map(fn (Warehouse $w) => "Warehouse {$w->code} has no locations")
            ->values()
            ->all();

        $machinesBadWc = $plantId
            ? Machine::query()
                ->where('organization_id', $organizationId)
                ->where('plant_id', $plantId)
                ->where(function ($q) {
                    $q->whereNull('work_center_id')
                        ->orWhereHas('workCenter', fn ($wc) => $wc->where('status', '!=', 'Active'));
                })
                ->count()
            : 0;

        return [
            'plants' => Plant::query()->where('organization_id', $organizationId)->count(),
            'departments' => $plantScoped(Department::query())->count(),
            'shifts' => $plantScoped(Shift::query())->count(),
            'employees' => $employeeCount,
            'employees_unassigned' => $employeesUnassigned,
            'employees_assigned' => max($employeeCount - $employeesUnassigned, 0),
            'roles' => Role::query()->count(),
            'work_centers' => $plantScoped(WorkCenter::query())->count(),
            'machines' => $plantScoped(Machine::query())->count(),
            'machines_bad_work_center' => $machinesBadWc,
            'uom' => UnitOfMeasure::query()->where('organization_id', $organizationId)->count(),
            'categories' => ProductCategory::query()->where('organization_id', $organizationId)->count(),
            'products' => $productCount,
            'products_inactive' => $productsInactive,
            'products_missing_uom' => $productsMissingUom,
            'products_missing_category' => $productsMissingCategory,
            'finished_goods' => $fgCount,
            'fg_with_bom' => $fgWithBom,
            'fg_missing_bom' => max($fgCount - $fgWithBom, 0),
            'fg_with_routing' => $fgWithRouting,
            'fg_missing_routing' => max($fgCount - $fgWithRouting, 0),
            'warehouse_types' => WarehouseType::query()->where('organization_id', $organizationId)->count(),
            'warehouses' => $warehouses->count(),
            'warehouses_without_locations' => $warehousesWithoutLocations->count(),
            'warehouse_location_warnings' => $warehouseLocationWarnings,
            'locations' => WarehouseLocation::query()
                ->where('organization_id', $organizationId)
                ->when(
                    $plantId,
                    fn ($query) => $query->whereHas('warehouse', fn ($warehouse) => $warehouse->where('plant_id', $plantId)),
                    fn ($query) => $query->whereRaw('1 = 0')
                )
                ->count(),
            'opening_stock_lines' => $plantScoped(Inventory::query())->where('quantity_on_hand', '!=', 0)->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return list<array{severity: string, href: string|null}>
     */
    private function problems(array $metrics): array
    {
        $problems = [];

        if ($metrics['plants'] === 0) {
            $problems[] = ['severity' => 'No plant configured', 'href' => null];
        }
        if ($metrics['departments'] === 0) {
            $problems[] = ['severity' => 'No departments created', 'href' => '/departments'];
        }
        if ($metrics['employees'] === 0) {
            $problems[] = ['severity' => 'No employees invited', 'href' => '/employees'];
        } elseif ($metrics['employees_unassigned'] > 0) {
            $problems[] = [
                'severity' => "{$metrics['employees_unassigned']} employees missing department or shift",
                'href' => '/employees',
            ];
        }
        if ($metrics['finished_goods'] === 0) {
            $problems[] = ['severity' => 'No finished products exist', 'href' => '/products'];
        }
        if ($metrics['fg_missing_bom'] > 0) {
            $problems[] = [
                'severity' => "{$metrics['fg_missing_bom']} finished products have no BOM",
                'href' => '/boms',
            ];
        }
        if ($metrics['fg_missing_routing'] > 0) {
            $problems[] = [
                'severity' => "{$metrics['fg_missing_routing']} finished products have no routing",
                'href' => '/routings',
            ];
        }
        foreach ($metrics['warehouse_location_warnings'] as $warning) {
            $problems[] = ['severity' => $warning, 'href' => '/warehouse-locations'];
        }
        if ($metrics['warehouses'] === 0) {
            $problems[] = ['severity' => 'No warehouses configured', 'href' => '/warehouses'];
        }
        if ($metrics['opening_stock_lines'] === 0) {
            $problems[] = ['severity' => 'Opening stock has not been entered', 'href' => '/inventory'];
        }
        if ($metrics['machines_bad_work_center'] > 0) {
            $problems[] = [
                'severity' => "{$metrics['machines_bad_work_center']} machines belong to inactive or missing work centers",
                'href' => '/machines',
            ];
        }

        return $problems;
    }

    /**
     * @param  list<array<string, mixed>>  $required
     * @param  list<array<string, mixed>>  $optional
     * @return array{overall: int, inventory: int, manufacturing: int, resources: int, configuration: int}
     */
    private function factoryHealth(array $required, array $optional): array
    {
        $all = array_merge($required, array_filter($optional, fn (array $i) => $i['key'] !== 'future_modules'));

        $score = function (string $category) use ($all): int {
            $items = array_values(array_filter($all, fn (array $i) => $i['category'] === $category));
            if ($items === []) {
                return 100;
            }

            $done = count(array_filter($items, fn (array $i) => $i['done']));

            return (int) round(($done / count($items)) * 100);
        };

        $requiredDone = count(array_filter($required, fn (array $i) => $i['done']));
        $overall = count($required) > 0
            ? (int) round(($requiredDone / count($required)) * 100)
            : 0;

        return [
            'overall' => $overall,
            'inventory' => $score('inventory'),
            'manufacturing' => $score('manufacturing'),
            'resources' => $score('resources'),
            'configuration' => $score('configuration'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $required
     */
    private function percentReady(array $required): int
    {
        if ($required === []) {
            return 0;
        }

        $done = count(array_filter($required, fn (array $i) => $i['done']));

        return (int) round(($done / count($required)) * 100);
    }

    /**
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    private function item(
        string $key,
        string $title,
        string $why,
        ?string $href,
        bool $done,
        string $metric,
        ?string $metricDetail,
        array $warnings,
        ?string $hint,
        string $category,
        bool $required = true,
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'why' => $why,
            'href' => $href,
            'done' => $done,
            'metric' => $metric,
            'metric_detail' => $metricDetail,
            'warnings' => $warnings,
            'hint' => $hint,
            'category' => $category,
            'required' => $required,
        ];
    }
}
