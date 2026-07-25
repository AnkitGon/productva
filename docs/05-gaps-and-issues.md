# 05 — Gaps, Issues & Missing Features

> This document is written from the perspective of a **paying enterprise customer** who has done thorough UAT before go-live. Every item here is either a blocker, a serious business gap, or a significant UX issue.

For the full market-readiness rule matrix (22 operational domains), see [07-business-rules.md](./07-business-rules.md). For module-level feature status, see [04-modules.md](./04-modules.md).

---

## 🔴 Critical Blockers (Cannot go live without these)

### B-01: No Production Orders Module
The platform is branded as a **Manufacturing Execution System** but has no production order (work order) functionality. There is no way to:
- Create a production order to manufacture X units of product Y
- Track production progress (scheduled → in-progress → completed)
- Record actual quantities produced
- Record scrap and rework
- Consume raw materials against a work order (backflush or manual issue)
- Record labour time against operations

**Impact:** The entire "manufacturing execution" proposition is undeliverable.

**Planned tables:** `production_orders`, `production_order_items`, `production_order_operations`

---

### B-02: Dashboard is a Blank Placeholder
The main dashboard (`/dashboard`) shows three placeholder boxes with no data whatsoever. Every operator who logs in sees this.

**Impact:** Zero operational insight. No KPIs. No alerts. First impression failure.

**Required KPIs minimum:**
- Open production orders count
- Today's scheduled production
- Low stock alerts
- Pending quality holds
- Open purchase orders
- Recent inventory movements

---

### B-03: No Goods Receipt / Purchase Order Flow
There is no way to receive stock from a supplier. The only way to add stock is via manual "Adjust Stock" — which loses all traceability of why stock was added.

**Impact:** Procurement and receiving teams have no workflow. Inventory accuracy is compromised from day one.

**Required:** Purchase Orders → Goods Receipt Note (GRN) → Inventory update with PO reference

---

### B-04: No Sales Order / Dispatch Flow
Products can have a selling price but there is no sales order. There is no way to:
- Create a customer order
- Reserve inventory against a sales order
- Pick, pack, and ship
- Issue a delivery note

**Impact:** Sales teams cannot use the system. Inventory reservation (`quantity_reserved`) is never populated.

---

### B-05: Inventory Transaction Types Are Not Fully Driven
The `inventory_transactions` table has an enum with types like `Production Receipt`, `Production Consumption`, `Purchase Receipt`, `Sales Issue`, `Return` — but none of these types are actually created by any workflow. Only `Adjustment` and `Transfer` types work. The other types exist in the schema with no backing workflow.

**Impact:** Transaction ledger is misleading — it implies capabilities that don't exist.

---

## 🟠 High Priority Gaps (Needed for real business use)

### H-01: No Supplier Master
- `preferred_supplier_id` on products is an integer field with no FK enforcement and no supplier table.
- There is no supplier management module at all.

**Impact:** Procurement is completely blocked. Product master shows a "preferred supplier" field that writes to a dead column.

---

### H-02: No Customer Master
- There is no customer database.
- Sales orders and delivery notes cannot be built without customers.

---

### H-03: No Quality Control Module
Permissions exist (`quality.view`, `quality.verify`) but there is no quality module. Cannot:
- Create inspection plans
- Record inspection results
- Raise a Non-Conformance Report (NCR)
- Put stock on quality hold
- Approve / reject incoming goods

**Impact:** Quality managers have access to nothing. The role is effectively empty.

---

### H-04: No Maintenance Module
Permissions exist (`maintenance.view`, `maintenance.manage`) but no module. Cannot:
- Schedule preventive maintenance
- Record maintenance work orders
- Track machine downtime
- Manage spare parts consumption

**Impact:** Maintenance managers have access to nothing.

---

### H-05: No Reports or Analytics
Permissions exist (`reports.view`, `reports.export`) but no reports exist anywhere in the system. A manufacturing business needs at minimum:
- Inventory valuation report
- Stock movement report (by product, date range, transaction type)
- Production summary
- Quality summary
- Purchase order status

---

### H-06: Audit Log / Activity Trail Missing
There is an `audit.view` permission but no audit log table or UI. In regulated industries (food, pharma), an audit trail is legally required. Even for general manufacturing, users need to know "who changed what and when."

**Required:** Log every create/update/delete action with: user, timestamp, model, record ID, before/after values.

---

### H-07: No Notifications System
No in-app notifications, no email alerts. Users have no way to be alerted about:
- Low stock levels
- Overdue production orders
- Machine maintenance due
- PO approval requests

---

### H-08: Dashboard Placeholder Is Not Removed on Navigation
When logged in as Super Admin and redirected to `/admin/dashboard`, this also shows a placeholder pattern with only the text "Welcome to your plant dashboard." No data, no value.

---

## 🟡 Medium Priority Gaps (Important for usability)

### M-01: BOM Where-Used Report Missing
Cannot answer: "Which finished goods use component X?" This is essential when a component is becoming obsolete or has a price change.

---

### M-02: BOM Cost Rollup Not Implemented
BOMs contain quantities and unit costs (purchase_price on products), but there is no calculation of the total material cost for the BOM. A production manager cannot see "this product costs $X in raw materials."

---

### M-03: Routing Labour Cost Not Computed
Routings have operation times, but work centers don't have a cost-per-hour rate. Total labour cost per unit cannot be computed.

---

### M-04: UOM Conversion Not Enforced
Products have purchase_uom_id, sales_uom_id, manufacturing_uom_id, and base uom_id. UOMs have a `base_unit_id` and `conversion_factor`. However, the conversion is never applied in any calculation. If a product is purchased in "Boxes of 12" and stored in "Each", the conversion must be applied on receipt.

---

### M-05: Opening Stock Fields Do Not Post to Inventory
Products capture `opening_stock` and `opening_cost` on the inventory tab, but creating/updating a product **does not** create an `Opening Balance` inventory transaction or inventory balance. Stock must still be entered via manual adjustment (or future opening-balance workflow).

**Impact:** Opening stock on the product master is informational only until posting logic is built.

---

### M-06: Product Variants Not Supported
A single product SKU cannot have variants (e.g. T-Shirt in Red/Blue × S/M/L). Each combination must be created as a separate product with a manual SKU naming convention. This is a significant gap for apparel, packaging, and any product with size/color attributes.

---

### M-07: Warehouse Capacity Utilization Not Computed
Warehouse locations have capacity fields, but nothing computes or displays utilization (how full is each location/warehouse?).

---

### M-08: Transfer Does Not Validate Available Quantity
Adjustments and transfers enforce negative-stock rules when `allow_negative_stock = false` on the product **or** warehouse (`InventoryService::assertStockAllowed()`). However, transfers do not yet validate that `quantity ≤ (quantity_on_hand − quantity_reserved)`.

**File:** `app/Services/InventoryService.php`  
**Fix:** Reject transfer quantity greater than available balance.

---

### M-09: No Bulk Import for Inventory
Only products can be bulk-imported from CSV. Opening balances and inventory adjustments cannot be bulk-imported.

---

### M-10: Employee Manager Chain Has No Cycle Detection (RESOLVED)
The `manager_id` field on employees creates a reports-to chain. We have implemented cycle detection to prevent circular references (e.g., Employee A reports to Employee B who reports to Employee A) during profile creation/update validation.

---

### M-11: No Product Duplication / Clone Feature
Creating a new product that is similar to an existing one requires filling in every field from scratch. There is no "Clone product" action.

---

### M-12: Machine Status "Under Maintenance" Is Not Integrated
When a machine's status is set to "Under Maintenance", nothing in the system prevents that machine from being assigned to a routing operation or production order. The status is cosmetic only.

---

### M-13: BOM "Phantom" Item Flag Has No Effect
BOM items can be flagged as `is_phantom` but this flag has no processing logic. In real MRP, phantom assemblies are "exploded through" — their components are included in the parent BOM as if the phantom doesn't exist. This is unimplemented.

---

### M-14: Soft-Delete Does Not Cascade Properly (RESOLVED)
We have implemented explicit soft-delete validations using `withTrashed()` inside `InventoryService` to prevent stock movements (adjustments and transfers) in deleted warehouses or locations.

---

### M-15: No Inter-Plant Transfer (RESOLVED)
We have enabled inter-plant transfers by resolving destination warehouse/location balances and transactions to their respective target plant IDs instead of restricting them to the active user's active plant ID.

---

### M-16: No Stock Reservation Workflow
`quantity_reserved` exists in the inventory table but is never populated by any automated workflow. It can only be changed via raw stock adjustment. The field will remain 0 for all records until production orders or sales orders are built.

---

## 🟢 Low Priority / UX Polish

### L-01: Product Import Has No Error Row Download
When product CSV import has validation errors, the user sees an error count but cannot download a "failed rows" file to fix and re-import.

### L-02: No Keyboard Shortcut to Open Dialogs
Power users expect `N` to open "New" dialogs, `Esc` to close, `Enter` to save. None implemented.

### L-03: Pagination State Lost on Form Submit
After submitting a form on page 3 of a list, the user is redirected back to page 1. The pagination state should be preserved.

### L-04: No "Recently Viewed" or Favorites
Users frequently navigating back to the same products/BOMs would benefit from a recently-viewed list.

### L-05: Date Format Not Configurable
Dates are displayed in a fixed format. International customers expect localized date formats.

### L-06: Currency Not Configurable
Prices are stored without a currency. There is no organization-level currency setting. Values display without a currency symbol.

### L-07: No Multi-language (i18n) Support
All UI is hardcoded in English. For international markets, i18n is needed.

### L-08: Product Image Shows Broken Icon if File Missing
If an image path is stored but the file doesn't exist in storage, the UI shows a broken image. A fallback placeholder should be shown.
