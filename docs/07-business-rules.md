# 07 — Business Rules

This document catalogs business rules for **real market operations** — what is enforced today, what is partially built, and what remains planned. It is aligned with the enterprise readiness checklist (Master Data through Deployment) but **every status is verified against the codebase**, not assumed from the checklist.

Use this with [04-modules.md](./04-modules.md) (feature status) and [05-gaps-and-issues.md](./05-gaps-and-issues.md) (blockers).

---

## Legend

| Icon | Meaning |
|------|---------|
| ✅ | Enforced in code (with test coverage where noted) |
| 🔧 | Partially enforced — UI or schema exists; workflow incomplete |
| ❌ | Not yet enforced — gap or module not built |
| ⏳ | Planned — required for market but no implementation yet |

---

## Market Readiness — Domain Summary

High-level view of the 22 operational domains. Detail tables follow in later sections.

| # | Domain | Current state | Notes |
|---|--------|---------------|-------|
| 1 | Master Data | 🔧 | Plants, warehouses, bins, departments, employees built; several delete guards stubbed |
| 2 | Product Management | 🔧 | Rich product master; variants/bundles/suppliers not built |
| 3 | BOM | 🔧 | Versioning, circular/duplicate guards; no production-use delete guard |
| 4 | Routing | 🔧 | Release lock, sequences, plant-scoped WC; no alternate routing |
| 5 | Inventory | 🔧 | Adjust, transfer, ledger; reservations/lot-serial workflows incomplete |
| 6 | Purchase | ⏳ | No PO module |
| 7 | ASN | ⏳ | Not built |
| 8 | Receiving / GRN | ⏳ | Not built — stock entry via manual adjustment only |
| 9 | Warehouse Operations | ⏳ | Putaway/pick/pack/dispatch not built |
| 10 | Production | ⏳ | No work orders — core MES gap |
| 11 | Quality | ⏳ | Permissions only; no inspection/NCR/CAPA |
| 12 | Planning | ⏳ | Shifts exist; no scheduling engine |
| 13 | Reports | ⏳ | Permissions only; no report screens |
| 14 | Users & Security | 🔧 | RBAC enforced; audit log and password policies incomplete |
| 15 | Notifications | ⏳ | Not built |
| 16 | Imports | 🔧 | Product CSV import; limited rollback/partial reporting |
| 17 | Exports | 🔧 | Product CSV export; no PDF report suite |
| 18 | Search & Filters | 🔧 | Per-module search/filters; no global search or saved filters |
| 19 | Performance | 🔧 | Not benchmarked; indexing via migrations only |
| 20 | Integrations | ⏳ | Not built |
| 21 | Data Integrity | 🔧 | Soft deletes + unique indexes; cascade/orphan gaps remain |
| 22 | Deployment Readiness | 🔧 | See [09-deployment.md](./09-deployment.md) |

---

## 1. Master Data

### Plants

| # | Rule | Status | Location |
|---|------|--------|----------|
| P-1 | An organization must always have at least one plant | ✅ | `PlantController::destroy()` |
| P-2 | A plant cannot be deleted once referenced (warehouses, departments, employees, work centers, machines, shifts, inventory, routings) | ✅ | `Plant::hasBlockingDependencies()` |
| P-3 | A plant with users whose `active_plant_id` points to it cannot be deleted | ✅ | `Plant::hasBlockingDependencies()` |
| P-4 | Only one default plant per organization | ✅ | `Plant::booted()` |
| P-5 | Inactive plants hidden from active plant selector | ❌ | Inactive plants still appear in `plant-dropdown.tsx` |
| P-6 | Plant name change updates displays without breaking FK references | ✅ | Name is display field; IDs unchanged |
| P-7 | Company (organization) isolation on all plant queries | ✅ | Controllers scope by `organization_id` |

### Warehouses

| # | Rule | Status | Location |
|---|------|--------|----------|
| W-1 | Warehouse belongs to a plant | ✅ | FK + validation |
| W-2 | Warehouse cannot be deleted if inventory exists | ❌ | `Warehouse::hasBlockingDependencies()` returns `false` (stub) |
| W-3 | Warehouse cannot be changed if transactions exist | ❌ | `hasTransactions()` stub returns `false` |
| W-4 | Warehouse status respected in transactions | ❌ | No status check on adjust/transfer |
| W-5 | Only one default warehouse per plant enforced on save | ✅ | `WarehouseController` clears other defaults |
| W-6 | Warehouse and locations share the same plant | ✅ | Denormalized `plant_id` on locations |

### Bin Locations (Warehouse Locations)

| # | Rule | Status | Location |
|---|------|--------|----------|
| BL-1 | Bin belongs to the correct warehouse / plant | ✅ | `WarehouseLocationController::validateLocation()` |
| BL-2 | Duplicate bin codes prevented within a warehouse | ✅ | Soft-delete-aware unique on `(warehouse_id, code)` |
| BL-3 | Bin with stock cannot be deleted | ❌ | Delete blocked only when child locations exist |
| BL-4 | Parent/child hierarchy cannot be circular | ✅ | `WarehouseLocation::isDescendantOf()` |
| BL-5 | Default receiving/picking bins | ❌ | Fields not implemented |
| BL-6 | Location status affects transactions | ❌ | Not validated on stock movement |

### Departments

| # | Rule | Status | Location |
|---|------|--------|----------|
| D-1 | Department cannot be deleted if employees or work centers assigned | ✅ | `DepartmentController::destroy()` |
| D-2 | Inactive department prevents new employee assignments | ❌ | Status stored but not validated on assign |
| D-3 | Department belongs to active plant context | ✅ | Plant-scoped queries |

### Employees

| # | Rule | Status | Location |
|---|------|--------|----------|
| E-1 | Employee code unique within organization (soft-delete aware) | ✅ | Migration + validation |
| E-2 | Employee belongs to a plant | ✅ | `plant_id` required |
| E-3 | Inactive employees cannot log in | ✅ | `Employee::canAuthenticate()`, `FortifyServiceProvider` |
| E-4 | Inactive employees excluded from assignment pickers | ✅ | `Employee::scopeAssignable()` |
| E-5 | Inactive employees cannot receive new work orders | ⏳ | Work orders not built; assignable scope ready |
| E-6 | Role permissions enforced on actions | ✅ | `CheckPermission` middleware |
| E-7 | Employee cannot be their own manager | ❌ | Not validated |
| E-8 | Circular manager chain rejected | ❌ | Gap M-10 |
| E-9 | Terminated/inactive employee not assignable as plant/department manager | 🔧 | Assignable scope covers pickers; manager FK not fully guarded |
| E-10 | Hard delete blocked when operational history exists | 🔧 | `hasOperationalHistory()` stub returns `false` until production/QC modules ship |

---

## 2. Product Management

| # | Rule | Status | Location |
|---|------|--------|----------|
| PR-1 | SKU unique per organization (soft-delete aware) | ✅ | Migration + `ProductController` |
| PR-2 | Barcode unique per organization when set | ✅ | Validation + import check |
| PR-3 | Product code (SKU) required and validated | ✅ | Form validation |
| PR-4 | Required fields validated by product type | 🔧 | Core fields validated; type-specific auto-rules limited |
| PR-5 | UOM consistency (base, purchase, sales, manufacturing UOMs) | 🔧 | Fields stored; conversion not applied |
| PR-6 | Default warehouse assignment | ✅ | `default_warehouse_id`; cleared when `track_inventory = false` |
| PR-7 | Product image / attachment handling | ✅ | Upload, remove, attachments CRUD |
| PR-8 | Product tags | 🔧 | `tags` JSON field; no tag master |
| PR-9 | Product status (Active/Inactive/Obsolete) | ✅ | Status field + bulk activate/deactivate |
| PR-10 | Inventory tracking flag | ✅ | `track_inventory`; disables stock fields when off |
| PR-11 | Lot / serial / batch / expiry tracking flags | 🔧 | Flags on product; not enforced on transactions |
| PR-12 | Tax configuration (GST/VAT classes) | 🔧 | Fields stored; no tax engine |
| PR-13 | Supplier assignments / multiple suppliers | ❌ | `preferred_supplier_id` has no supplier master |
| PR-14 | Product variants | ❌ | Each variant = separate SKU |
| PR-15 | Product bundles / kits | ❌ | Not built |
| PR-16 | Duplicate prevention on import | ✅ | SKU/barcode checks in CSV import |
| PR-17 | Product cannot be deleted once referenced in BOM/routing | ✅ | `Product::hasBlockingDependencies()` |
| PR-18 | Product with inventory cannot be hard-deleted | ❌ | Inventory not in `hasBlockingDependencies()` (UI message implies it is) |
| PR-19 | Stock level hierarchy: Maximum ≥ Minimum ≥ Safety ≤ Reorder | ✅ | `ProductController::stockLevelValidationErrors()` |
| PR-20 | Opening stock / opening cost captured on product | 🔧 | Stored on product; **does not auto-post** to inventory ledger |
| PR-21 | Bulk actions: activate, deactivate, category, warehouse, delete, export | ✅ | `ProductController::bulk()` |

### Product Categories

| # | Rule | Status | Location |
|---|------|--------|----------|
| PC-1 | Category name unique per organization | ✅ | Soft-delete-aware validation |
| PC-2 | Category cannot be deleted if products assigned | ✅ | `ProductCategory::hasProducts()` |
| PC-3 | Category cannot be deleted if child categories exist | ✅ | `ProductCategory::hasChildren()` |
| PC-4 | Parent category cannot create circular hierarchy | ✅ | Validation in `ProductCategoryController` |

### Units of Measure

| # | Rule | Status | Location |
|---|------|--------|----------|
| UOM-1 | UOM code unique per organization | ✅ | Soft-delete-aware index |
| UOM-2 | UOM name unique per organization | ✅ | Validation in `UnitOfMeasureController` |
| UOM-3 | UOM conversion on transactions | ❌ | `conversion_factor` not applied |

---

## 3. Bill of Materials

| # | Rule | Status | Location |
|---|------|--------|----------|
| B-1 | No circular BOM references | ✅ | `BomService::wouldCreateCircularBom()` |
| B-2 | Same child not duplicated in one BOM | ✅ | Duplicate component check in `BomService` |
| B-3 | Quantities must be > 0 | ✅ | Validation |
| B-4 | UOM per line validated against org | ✅ | `BomService` |
| B-5 | UOM conversion at explosion time | ❌ | Not built (no production explosion) |
| B-6 | Effective dates (effective_from / effective_to) | 🔧 | Stored; auto-obsolete on date not enforced |
| B-7 | Revision / version handling | ✅ | Version field + copy BOM |
| B-8 | Only one default BOM per product | ✅ | `BomService` clears other defaults |
| B-9 | BOM activation / deactivation by status | 🔧 | Status field; edit not blocked by Active status |
| B-10 | BOM cannot be deleted after production use | ⏳ | Production orders not built |
| B-11 | Phantom item processing | ❌ | `is_phantom` stored; no MRP logic |

---

## 4. Routing

| # | Rule | Status | Location |
|---|------|--------|----------|
| R-1 | Work center sequence on operations | ✅ | Sequence field; unique on release |
| R-2 | Estimated / run time captured | ✅ | Operation times on routing lines |
| R-3 | Machine assignment validated against work center | ✅ | `RoutingService` release gate |
| R-4 | Alternate routing | ❌ | Single routing per product/plant version set |
| R-5 | Revision / version support | ✅ | Version + copy routing |
| R-6 | Released routing cannot be edited | ✅ | `RoutingHeader::is_editable`; controller guard |
| R-7 | Only one default routing per product per plant | ✅ | `RoutingService` |
| R-8 | At least one operation required to release | ✅ | `RoutingService` release validation |
| R-9 | Operation sequences unique within routing | ✅ | Validated on release |
| R-10 | Work center must belong to same plant as routing | ✅ | Plant-scoped exists rules |
| R-11 | Routing cannot be deleted after production use | ⏳ | Production orders not built |

---

## 5. Inventory

| # | Rule | Status | Location |
|---|------|--------|----------|
| I-1 | Stock never negative unless product or warehouse allows it | ✅ | `InventoryService::assertStockAllowed()` |
| I-2 | Reservations respected (reserved ≤ on-hand) | 🔧 | Validated on adjustment; no reservation workflow |
| I-3 | Available quantity = on-hand − reserved | ✅ | `Inventory` accessor |
| I-4 | On-hand, reserved quantities maintained | ✅ | `inventories` table |
| I-5 | Incoming / outgoing quantity fields | ❌ | Not modeled separately |
| I-6 | Lot tracking on transactions | 🔧 | Fields on transaction; optional capture only |
| I-7 | Serial tracking on transactions | 🔧 | Fields on transaction; uniqueness not enforced |
| I-8 | Bin transfers (within plant) | ✅ | Transfer between locations |
| I-9 | Warehouse transfers | ✅ | Transfer between warehouses (same plant) |
| I-10 | Inventory adjustments | ✅ | `InventoryService::adjust()` |
| I-11 | Audit trail / transaction history | ✅ | Immutable `inventory_transactions` |
| I-12 | Every movement creates a transaction record | ✅ | `InventoryService` |
| I-13 | Transfer cannot exceed available (on-hand − reserved) | ❌ | Validates qty > 0 and negative-stock rules only |
| I-14 | Stock in deleted warehouse/location cannot move | ❌ | Existence check only; `deleted_at` not checked |
| I-15 | Transaction numbers unique per org | ✅ | `InventoryService::nextTransactionNo()` |
| I-16 | Inter-plant transfer | ❌ | Gap M-15 |

---

## 6–12. Planned Operational Modules

These domains are **required for full market readiness** but are **not implemented** beyond schema stubs, permissions, or manual workarounds. Rules below are **targets for Phase 2+** (see [06-roadmap.md](./06-roadmap.md)).

| Domain | Key rules (planned) | Status |
|--------|---------------------|--------|
| **Purchase** | PO approval, partial receipts, over-receipt limits, back orders, supplier validation, duplicate PO prevention | ⏳ |
| **ASN** | ASN-to-PO match, partial ASN, tolerance, damaged goods, status lifecycle | ⏳ |
| **Receiving** | GRN generation, lot/serial capture, bin allocation, inspection hold, rejected stock posting | ⏳ |
| **Warehouse Ops** | Putaway suggestions, FIFO/FEFO picking, pick confirm, short pick, packing, dispatch, carrier tracking | ⏳ |
| **Production** | WO lifecycle, material allocation/shortage, start/pause/complete, scrap/rework, WIP, labour/machine time | ⏳ |
| **Quality** | Incoming/production/final inspection, NCR, CAPA, quarantine hold, shipment release | ⏳ |
| **Planning** | Production scheduling, capacity validation, material availability, shift/resource conflicts | ⏳ |

When built, each workflow must create the corresponding `inventory_transaction` types already defined in the schema (`Purchase Receipt`, `Production Consumption`, `Sales Issue`, etc.) — today only `Adjustment`, `Transfer`, and `Opening Balance` types are used in practice.

---

## 13. Reports

| # | Rule | Status | Location |
|---|------|--------|----------|
| RP-1 | Reports reconcile to database totals | ⏳ | No report module |
| RP-2 | Inventory valuation | ⏳ | — |
| RP-3 | Stock movement | ⏳ | Transaction list UI exists; not a formal report |
| RP-4 | Production summary / OEE / scrap / downtime | ⏳ | — |
| RP-5 | Warehouse utilization / employee productivity | ⏳ | — |

---

## 14. Users & Security

| # | Rule | Status | Location |
|---|------|--------|----------|
| U-1 | User must belong to organization | ✅ | Controllers |
| U-2 | Active plant required for plant-scoped features | ✅ | Controllers |
| U-3 | Permissions on every protected route | ✅ | `CheckPermission` |
| U-4 | Super Admin bypasses permission checks | ✅ | Middleware |
| U-5 | Menu visibility by permission | ✅ | Sidebar nav |
| U-6 | Unauthorized URL access blocked | ✅ | 403 on missing permission |
| U-7 | Audit logs for changes | ❌ | Permission exists; no audit table |
| U-8 | Password policies beyond Fortify defaults | 🔧 | Fortify rules only |
| U-9 | Session timeout | 🔧 | Laravel session config |
| U-10 | Multi-company isolation | ✅ | Organization scoping |
| U-11 | User cannot delete own account | ❌ | Not validated |
| U-12 | Last org admin cannot be demoted | ❌ | Not enforced |

---

## 15–18. Notifications, Imports, Exports, Search

| Domain | Rule | Status |
|--------|------|--------|
| **Notifications** | Email, in-app, queue failure handling, deduplication | ⏳ |
| **Imports** | Product CSV with row errors; invalid/duplicate row handling | 🔧 Partial — no failed-row download (L-01) |
| **Imports** | Inventory bulk import | ❌ |
| **Exports** | Product CSV + bulk export selected | 🔧 |
| **Exports** | Excel, PDF, filtered large exports, timezone | ❌ |
| **Search** | Per-module search and column filters | ✅ |
| **Search** | Global search, saved filters | ❌ |
| **Search** | Pagination and sorting on list pages | ✅ |

---

## 19–22. Performance, Integrations, Data Integrity, Deployment

| Domain | Rule | Status |
|--------|------|--------|
| **Performance** | Dashboard / list / report load targets | ❌ Not benchmarked |
| **Performance** | Database indexing for org/plant scoped queries | 🔧 Soft-delete-aware uniques + FK indexes |
| **Integrations** | FTP, ERP/accounting sync, scanners, printers, webhooks | ⏳ |
| **Data integrity** | No orphan records | 🔧 Gaps on soft-delete cascade (M-14) |
| **Data integrity** | Soft delete + restore | ✅ Most masters support soft delete |
| **Data integrity** | Transaction rollbacks | ✅ DB transactions in `InventoryService`, `BomService` |
| **Deployment** | Queues, scheduler, cache, storage, backups, SSL, monitoring | 🔧 See [09-deployment.md](./09-deployment.md) |

---

## Shifts, Work Centers & Machines

| # | Rule | Status | Location |
|---|------|--------|----------|
| S-1 | Shift times must not overlap per plant | ❌ | Not validated |
| S-2 | Net available minutes > 0 | ❌ | Not validated |
| WC-1 | Work center with machines cannot be deleted | ❌ | Not enforced |
| WC-2 | Machine "Under Maintenance" not assignable to routing release | 🔧 | Validated on routing release; no production block |
| WC-3 | Efficiency percent 1–100 | 🔧 | Stored; limited UI validation |

---

## Cross-Module Rules (When Phase 2 Ships)

| Module | Rule |
|--------|------|
| Purchase Orders | Cannot receive more than ordered without approval; cancelled PO cannot be received |
| Production Orders | Cannot release without components (unless negative stock allowed); cannot complete over ordered qty |
| Sales Orders | Cannot ship over ordered qty; credit limit check |
| Quality | Finished goods under QC hold cannot ship |

---

## How to Extend This Document

When implementing a new rule:

1. Add or update the row in the relevant section with ✅ and file reference.
2. Add a Pest feature test (see [11-testing.md](./11-testing.md)).
3. If the rule closes a gap, remove or downgrade the matching item in [05-gaps-and-issues.md](./05-gaps-and-issues.md).
