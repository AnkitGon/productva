# 02 — Architecture

## Technology Stack

| Layer | Technology | Version |
|-------|------------|---------|
| **Backend** | PHP | 8.4 |
| **Framework** | Laravel | 13.x |
| **Frontend Framework** | React | 19.x |
| **SPA Bridge** | Inertia.js | v3 (Laravel + React adapters) |
| **CSS** | TailwindCSS | v4 |
| **Build Tool** | Vite | latest |
| **Type Safety** | TypeScript | 5.x |
| **Routing (FE)** | Laravel Wayfinder | v0 |
| **Auth Backend** | Laravel Fortify | v1 |
| **Testing** | Pest | v4 |
| **Code Style** | Laravel Pint | v1 |
| **Static Analysis** | Larastan | v3 |
| **Database** | MySQL / MariaDB | 8+ |
| **Queue** | Database (default; Redis recommended for prod) |
| **Cache** | File (default; Redis recommended for prod) |
| **Storage** | Local disk (public); S3-compatible for production |

---

## Directory Structure

```
productva/
├── app/
│   ├── Actions/               # Fortify action overrides (CreateNewUser, etc.)
│   ├── Concerns/              # Shared PHP traits
│   ├── Console/               # Artisan commands
│   ├── Http/
│   │   ├── Controllers/       # Feature controllers (PlantController, etc.)
│   │   │   ├── Admin/         # Super-admin only controllers
│   │   │   └── Settings/      # Account/profile settings
│   │   └── Middleware/        # CheckPermission, etc.
│   ├── Models/                # Eloquent models
│   │   └── Concerns/          # Model traits (BelongsToActivePlant, etc.)
│   ├── Providers/             # AppServiceProvider, FortifyServiceProvider
│   ├── Services/              # Business logic services
│   │   ├── BomService.php
│   │   ├── InventoryService.php
│   │   ├── RoutingService.php
│   │   └── FactorySetupService.php
│   └── Support/
│       └── DefaultRoles.php   # Permission & role definitions
├── database/
│   ├── migrations/            # One migration per schema change
│   ├── seeders/               # RolesAndPermissionsSeeder, DatabaseSeeder
│   └── factories/             # Model factories for testing
├── resources/
│   ├── js/
│   │   ├── components/        # Reusable React components
│   │   │   ├── ui/            # shadcn/ui primitives
│   │   │   └── ...
│   │   ├── layouts/           # App layout, Auth layout
│   │   ├── pages/             # Inertia pages (one dir per feature)
│   │   │   ├── admin/         # Super-admin pages
│   │   │   ├── auth/          # Login, register, etc.
│   │   │   ├── boms/          # Bill of Materials
│   │   │   ├── departments/
│   │   │   ├── employees/
│   │   │   ├── inventory/
│   │   │   ├── inventory-transactions/
│   │   │   ├── machines/
│   │   │   ├── operations/
│   │   │   ├── product-categories/
│   │   │   ├── products/
│   │   │   ├── routings/
│   │   │   ├── settings/
│   │   │   ├── setup/
│   │   │   ├── shifts/
│   │   │   ├── units-of-measure/
│   │   │   ├── warehouse-locations/
│   │   │   ├── warehouse-types/
│   │   │   ├── warehouses/
│   │   │   └── work-centers/
│   │   ├── actions/           # Wayfinder-generated route helpers
│   │   └── routes/            # Wayfinder-generated named routes
│   └── views/                 # Blade templates (app.blade.php root only)
├── routes/
│   ├── web.php                # All authenticated routes
│   ├── admin.php              # Super-admin routes
│   └── settings.php           # Profile/account settings routes
├── tests/
│   ├── Feature/               # HTTP/integration tests
│   └── Unit/                  # Pure unit tests
└── docs/                      # This folder — project documentation
```

---

## Architectural Patterns

### Multi-Tenancy: Organization + Plant scoping

- Every table that stores operational data has **organization_id** (tenant) and **plant_id** (sub-tenant).
- **Organization-scoped**: Products, BOMs, Product Categories, UOMs, Employees (org-level HR), Roles.
- **Plant-scoped**: Warehouses, Inventory, Work Centers, Machines, Shifts, Routings, Departments.
- The `BelongsToActivePlant` model concern provides `scopeForActivePlant()` that automatically filters queries by the user's current active plant.

### Authentication & Authorization

- **Fortify** handles login, registration, password reset, 2FA, passkeys.
- Roles and permissions are stored in the `roles` and `permissions` tables with a many-to-many pivot.
- The `CheckPermission` middleware (`app/Http/Middleware/CheckPermission.php`) gates every route.
- The `DefaultRoles` class (`app/Support/DefaultRoles.php`) is the canonical source for all permission slugs and role assignments — changes there propagate on `php artisan db:seed`.

### Service Layer

Business logic lives in `app/Services/`:

| Service | Responsibility |
|---------|---------------|
| `InventoryService` | Stock adjustments, transfers, opening stock creation, transaction ledger |
| `BomService` | BOM creation with items, copy, version management |
| `RoutingService` | Routing creation with operations, copy, release/obsolete lifecycle |
| `FactorySetupService` | Initial organization setup wizard (seed defaults) |

### Frontend (Inertia SPA)

- Pages are React components in `resources/js/pages/`.
- Shared data (user, permissions, active plant) flows through Inertia's **shared props**.
- Navigation is handled by `<Link>` from `@inertiajs/react`; no full page reloads.
- Route URLs are typed via **Wayfinder** — never hardcode `/some-url` in frontend code.
- Component library: **shadcn/ui** (Radix primitives + Tailwind).

---

## Data Flow: Inventory Adjustment

```
User submits form
  → POST /inventory/adjust
  → CheckPermission middleware: inventory.adjust
  → InventoryController::adjust()
  → Request validation
  → InventoryService::adjust()
      → find or create Inventory row (org + plant + warehouse + location + product + lot + serial)
      → compute delta
      → update inventory.quantity_on_hand
      → create InventoryTransaction (type: Adjustment, reference, notes, user)
  → Inertia::flash('toast', ...)
  → redirect()->back()
```

---

## Key Design Decisions

1. **Soft deletes everywhere** — No hard deletes for any business entity. Data is retained for audit and traceability.
2. **Plant guard on destruction** — A plant with any child records (warehouses, departments, inventory, etc.) cannot be deleted; it must be deactivated.
3. **Wayfinder for type-safe routing** — All frontend route calls go through generated helpers in `@/actions/` and `@/routes/`.
4. **Shared Props over API endpoints** — Inertia shared props (user, permissions, plant list) remove the need for a separate auth API.
5. **Permission slugs as source of truth** — `DefaultRoles::permissions()` is the canonical list. UI reads these at runtime; no permission is hard-coded in TSX.

See [12-decisions.md](./12-decisions.md) for full ADR.
