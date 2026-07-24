# 06 — Roadmap

## MVP Definition

An MVP for ProductVA means a real manufacturing business can run their **core daily operations** on it:
- Maintain their product catalog and stock levels
- Create and track production orders
- Receive goods from suppliers
- Issue materials to production
- Ship to customers
- See where their inventory is at any time

---

## Phase 1: Foundation (Current State — July 2026)

**Status:** ✅ Complete for master data, product master, BOM, routing, warehouse, and basic inventory.

Cross-reference: [07-business-rules.md](./07-business-rules.md) maps the full **22-domain market checklist** to implemented vs planned rules.

| Module | Status |
|--------|--------|
| Authentication (login, 2FA, passkeys) | ✅ |
| Organization & Plant setup | ✅ |
| User & Role management | ✅ |
| HR: Employees, Departments, Shifts | ✅ |
| Plant Resources: Work Centers, Machines | ✅ |
| Product Master (comprehensive) | ✅ |
| Product bulk actions & extended filters | ✅ |
| Unit of Measure | ✅ |
| Product Categories | ✅ |
| Bill of Materials | ✅ |
| Operations Master | ✅ |
| Routings | ✅ |
| Warehouse Management (types, warehouses, locations) | ✅ |
| Inventory Management (adjust, transfer, ledger) | ✅ |
| CSV Import/Export (products, inventory) | ✅ |

---

## Phase 2: MVP Completion (Next Priority — Q3 2026)

These items are **required before any paying customer can go live**.

### Sprint 1: Supplier & Customer Masters (2 weeks)

**Goal:** Enable procurement and sales workflows by establishing master data.

| Task | Description |
|------|-------------|
| Supplier model & migration | Name, code, contact, address, payment terms, lead time |
| Supplier controller & pages | CRUD + search |
| Link Product.preferred_supplier_id | Add FK + dropdown in product form |
| Customer model & migration | Name, code, contact, address, credit limit, payment terms |
| Customer controller & pages | CRUD + search |

---

### Sprint 2: Purchase Orders & Goods Receipt (3 weeks)

**Goal:** Stock enters the system from suppliers with full traceability.

| Task | Description |
|------|-------------|
| PurchaseOrder model | Supplier, status (Draft → Sent → Partial Receipt → Received → Cancelled), order date |
| PurchaseOrderItem model | Product, qty ordered, qty received, unit price, UOM |
| PO controller & pages | Create, edit, approve, send |
| Goods Receipt (GRN) | Receive against PO line by line; supports partial receipt |
| GRN → Inventory | Creates `inventory_transaction` type: `Purchase Receipt` |
| Lot/Serial assignment on GRN | Assign lot numbers at receiving |
| Print GRN | Generate PDF receipt document |

---

### Sprint 3: Production Orders & Work Orders (4 weeks)

**Goal:** The MES core — plan and track production.

| Task | Description |
|------|-------------|
| ProductionOrder model | Product, quantity, BOM version, routing version, plant, status, scheduled dates |
| ProductionOrderItem model | BOM explosion on creation |
| ProductionOrder status lifecycle | Draft → Released → In Progress → Completed → Cancelled |
| Material issue (pick) | Issue materials from inventory to WO; creates `Production Consumption` transactions |
| Production receipt | Report finished goods; creates `Production Receipt` transaction |
| Backflush | Auto-consume materials when FG receipt is posted (if backflush flag = true) |
| Scrap reporting | Record scrap quantity per operation |
| WO show page | Current status, issued materials, finished qty |

---

### Sprint 4: Sales Orders & Shipping (3 weeks)

**Goal:** Stock leaves the system to customers with traceability.

| Task | Description |
|------|-------------|
| SalesOrder model | Customer, status (Draft → Confirmed → Partial → Shipped → Invoiced) |
| SalesOrderItem model | Product, qty ordered, qty shipped, price, UOM |
| SO controller & pages | Create, confirm, cancel |
| Pick list / delivery order | Select items and quantities for dispatch |
| Dispatch → Inventory | Creates `Sales Issue` transactions, populates quantity_reserved until shipped |
| Delivery note PDF | Printable document |

---

### Sprint 5: Dashboard & KPIs (2 weeks)

**Goal:** Useful first screen — operational at a glance.

| KPI Card | Source |
|----------|--------|
| Open production orders | production_orders |
| Production orders due today | production_orders.scheduled_end_date |
| Low stock alerts | inventories + product thresholds |
| Out of stock count | inventories |
| Open purchase orders | purchase_orders |
| Pending goods receipts | purchase_orders where partial |
| Open sales orders | sales_orders |
| Quality holds | inventory_holds |

Charts:
- Inventory value trend (7-day)
- Production efficiency (planned vs actual qty)
- Top 10 products by stock value

---

### Sprint 6: Quality Control (2 weeks)

**Goal:** Inspect incoming goods and production output; manage holds.

| Task | Description |
|------|-------------|
| QualityInspection model | Linked to GRN or production order, inspector, result |
| InspectionItem model | Per-product check with pass/fail/quantity |
| NCR (Non-Conformance Report) | Severity, root cause, corrective action |
| Stock Hold | Lock stock in quarantine location pending QC decision |
| Quarantine release/rejection | Accept: move to usable location. Reject: write off |

---

### Sprint 7: Reports (2 weeks)

**Goal:** Business intelligence from existing data.

| Report | Description |
|--------|-------------|
| Inventory Valuation | On-hand × standard/purchase cost by product |
| Stock Movement | All transactions in date range, by product or warehouse |
| Production Summary | Orders completed, quantities, scrap %, efficiency |
| Purchase Order Status | Open POs, received value, outstanding |
| Sales Order Status | Open SOs, shipped value, outstanding |
| Low Stock Reorder | Products below reorder level with recommended PO qty |

---

### Sprint 8: Audit Log (1 week)

| Task | Description |
|------|-------------|
| audit_logs table | model_type, model_id, action, user_id, before_values, after_values, timestamp |
| Automatic logging | Model observer on all business models |
| Audit log page | Filter by user, model, date range |

---

## Phase 3: Advanced Features (Q4 2026)

| Feature | Priority |
|---------|----------|
| Maintenance Management (schedules, work orders, downtime) | High |
| Cycle Count / Physical Inventory | High |
| Inter-plant transfers | Medium |
| UOM conversion enforcement | Medium |
| BOM cost rollup | Medium |
| Routing labour cost | Medium |
| Demand forecasting | Low |
| Barcode scanning (mobile web) | Medium |
| Email notifications (low stock, PO approval, etc.) | High |
| Webhook / API integrations | Medium |

---

## Phase 4: Scale & Monetization (2027)

| Feature | Notes |
|---------|-------|
| Subscription plan enforcement | Free tier limits: 1 plant, 2 users, 500 products |
| Per-seat pricing | Billing module |
| Multi-currency | Organization-level currency + exchange rates |
| Multi-language / i18n | Starting with Arabic, French |
| Mobile app (React Native) | Barcode scan, shop floor reporting |
| AI demand forecasting | Based on sales history |
| Customer portal | Customers can see order status |
| Supplier portal | Suppliers can confirm POs and send ASNs |
