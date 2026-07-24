# 03 — Data Model

Entity reference for ProductVA. For rule-level behavior see [07-business-rules.md](./07-business-rules.md). For feature status see [04-modules.md](./04-modules.md).

---

## Scoping Model

| Scope | Entities |
|-------|----------|
| **Organization** | User, Product, ProductCategory, UnitOfMeasure, BomHeader, Organization settings |
| **Plant** | Department, Employee, Shift, WorkCenter, Machine, Warehouse, WarehouseLocation, Inventory, InventoryTransaction, RoutingHeader, Operation (plant context) |
| **System** | Super Admin, roles (global permission names) |

Users operate in an **active plant** context (`users.active_plant_id`). Plant-scoped controllers reject cross-plant access.

---

## Core Entities

### Organization & tenancy

| Table | Model | Purpose |
|-------|-------|---------|
| `organizations` | `Organization` | Tenant / company |
| `users` | `User` | Login account; links to org and optional employee |
| `plants` | `Plant` | Factory site; `is_default`, status |

### HR & plant resources

| Table | Model | Purpose |
|-------|-------|---------|
| `departments` | `Department` | Org structure; optional manager |
| `employees` | `Employee` | Plant workforce; code, status, manager_id, personal fields |
| `shifts` | `Shift` | Shift definitions with color |
| `work_centers` | `WorkCenter` | Production areas |
| `machines` | `Machine` | Equipment on work centers |
| `operations` | `Operation` | Reusable operation master |

### Product master

| Table | Model | Purpose |
|-------|-------|---------|
| `products` | `Product` | SKU, barcode, types, pricing, stock thresholds, tracking flags, opening_stock/cost |
| `product_categories` | `ProductCategory` | Hierarchical categories |
| `product_attachments` | `ProductAttachment` | Extra files beyond primary image |
| `units_of_measure` | `UnitOfMeasure` | UOM with optional conversion to base |

### Engineering

| Table | Model | Purpose |
|-------|-------|---------|
| `bom_headers` | `BomHeader` | Versioned BOM per product; effectivity, default flag |
| `bom_items` | `BomItem` | Components, qty, UOM, phantom flag |
| `routing_headers` | `RoutingHeader` | Versioned routing per product/plant |
| `routing_operations` | `RoutingOperation` | Sequence, work center, machine, times |

### Warehouse & inventory

| Table | Model | Purpose |
|-------|-------|---------|
| `warehouse_types` | `WarehouseType` | Classification (raw, FG, etc.) |
| `warehouses` | `Warehouse` | Plant warehouse; default, negative stock flag |
| `warehouse_locations` | `WarehouseLocation` | Bins/zones; parent hierarchy |
| `inventories` | `Inventory` | Balance: on-hand, reserved, lot/serial |
| `inventory_transactions` | `InventoryTransaction` | Immutable ledger; types include Adjustment, Transfer, Opening Balance, and **planned** PO/Production/Sales types |

---

## Key Relationships

```
Organization 1──* Plant
Organization 1──* Product
Organization 1──* User
Plant 1──* Employee
Plant 1──* Warehouse 1──* WarehouseLocation
Plant 1──* Inventory (via product + warehouse + location)
Product 1──* BomHeader 1──* BomItem *──1 Product (component)
Product 1──* RoutingHeader (per plant) 1──* RoutingOperation
User *──1 Employee (optional)
Employee *──1 Employee (manager_id, self-referential)
```

---

## Soft Deletes & Uniqueness

Most master tables use `deleted_at`. Unique constraints on codes/names/SKU/barcode are **soft-delete aware** (see migration `add_soft_delete_aware_unique_indexes` and `database/Support/SoftDeleteAwareUnique.php`).

---

## Planned Tables (Not Migrated)

These are referenced in roadmap/gap docs but **do not exist yet**:

- `suppliers`, `customers`
- `purchase_orders`, `purchase_order_items`, `goods_receipts`
- `production_orders`, `production_order_operations`
- `sales_orders`, `sales_order_items`
- `quality_inspections`, `ncrs`
- `audit_logs`
- `notifications`

Do not document these as live schema until migrations ship.

---

## Inventory Transaction Types

Defined on `InventoryTransaction` — only **Adjustment**, **Transfer In/Out**, and **Opening Balance** are created by current workflows. Others are reserved for Phase 2 modules:

| Type | Used today |
|------|------------|
| Adjustment | ✅ |
| Transfer In / Transfer Out | ✅ |
| Opening Balance | 🔧 Enum exists; product opening fields don't auto-post |
| Purchase Receipt | ⏳ |
| Production Consumption / Production Receipt | ⏳ |
| Sales Issue / Return | ⏳ |

---

## Auto-Generated Codes

Several models use `AutoGeneratesCode` concern and `CodeGenerator` for org-scoped codes (departments, warehouses, employees, etc.).
