# 01 — Product Overview

## What is ProductVA?

ProductVA is a **cloud-based Manufacturing Execution Platform (MES)** designed for small-to-medium manufacturers who need a single unified system to manage:

- **Shop Floor Execution** — production orders, work orders, operator instructions
- **Inventory & Warehouse Management (WMS)** — multi-warehouse, multi-location stock tracking
- **Bill of Materials (BOM)** — versioned product structures with effectivity dates
- **Routing & Work Centers** — operation sequences, cycle times, machine assignment
- **Quality Control** — inspection plans, non-conformance tracking, hold management
- **Procurement** — purchase requisitions, purchase orders, goods receipt
- **Sales** — sales orders, pick-pack-ship, delivery notes
- **HR / Plant Resources** — employees, departments, shifts, work centers, machines
- **Reporting & Analytics** — real-time KPIs, OEE, inventory valuation, cost of goods

---

## Target Market

| Segment | Description |
|---------|-------------|
| **Primary** | Small-to-mid-size discrete manufacturers (50–500 employees) |
| **Secondary** | Process manufacturers with batch/lot requirements |
| **Geography** | Initially English-speaking markets; GST/VAT tax classes for India/UK/EU markets |
| **Industries** | Automotive parts, FMCG, electronics assembly, food & beverage, pharma |

---

## Value Proposition

Traditional ERP systems (SAP, Oracle, Microsoft Dynamics) are expensive, complex, and require months of implementation. ProductVA offers:

1. **Free to start** — build customer base before introducing subscription tiers
2. **Purpose-built for manufacturing** — not a generic ERP bolted-on to manufacturing
3. **Multi-plant, multi-organization** — designed for companies with multiple factories
4. **Modern UX** — React + Inertia SPA with real-time feedback, no page reloads
5. **Progressive complexity** — start with inventory, add production when ready

> **Implementation vs vision:** Marketing copy above describes the **target product**. For what is built today vs the 22-domain market checklist, see [07-business-rules.md](./07-business-rules.md) and [04-modules.md](./04-modules.md).

---

## Business Model (Planned)

| Phase | Strategy |
|-------|----------|
| Phase 1 (current) | **Free** — acquire early adopters, gather feedback |
| Phase 2 | **Freemium** — free tier limited to 1 plant, 2 users, 500 products |
| Phase 3 | **Subscription** — per-seat or per-plant pricing; enterprise contracts |
| Phase 4 | **Add-ons** — AI demand forecasting, barcode scanning mobile app, API integrations |

---

## Differentiators vs. Competitors

| Feature | ProductVA | Odoo | Katana MRP | Fishbowl |
|---------|-----------|------|------------|----------|
| MES + WMS + ERP unified | ✅ | ✅ | Partial | Partial |
| Multi-plant | ✅ | Paid | ❌ | ❌ |
| Modern React UI | ✅ | ❌ | ✅ | ❌ |
| Free to start | ✅ | ✅ | ❌ | ❌ |
| Open source | ❌ | ✅ | ❌ | ❌ |
| Laravel-based | ✅ | ❌ | ❌ | ❌ |

---

## Key Concepts

### Organization → Plant hierarchy

```
Organization (company/tenant)
  └── Plant (factory/site)
        ├── Departments
        ├── Employees
        ├── Shifts
        ├── Work Centers
        ├── Machines
        ├── Warehouses
        │     └── Locations (bins/zones/aisles)
        ├── Inventory
        └── Routing Headers
```

- An **Organization** is the top-level tenant.
- A **Plant** is a physical manufacturing site. All operational data is plant-scoped.
- Users have an **active plant** context — they switch plants like switching workspaces.
- **Products and BOMs** are organization-scoped (shared across all plants).
- **Inventory, Warehouses, Routings, Work Centers** are plant-scoped.

### Product Types

| Type | Description |
|------|-------------|
| Raw Material | Purchased inputs; consumed in production |
| Semi Finished | Intermediate goods; produced then consumed |
| Finished Good | End product sold to customers |
| Packaging | Boxes, labels, pallets |
| Consumable | Oils, gloves — not tracked as finished output |
| Spare Part | Maintenance materials |
| Service | Non-physical deliverables |

### BOM Versioning

Each product can have multiple BOM versions. Only one version is **default** at a time. BOMs have **effectivity dates** (effective_from / effective_to) to support date-based scheduling.

### Routing Lifecycle

`Draft → Released → Obsolete`

A routing in **Draft** can be edited. Once **Released**, it is locked and can only be Obsoleted.

---

## Users & Roles

See [08-permissions.md](./08-permissions.md) for the full role matrix.

| Role | Scope | Primary Use |
|------|-------|-------------|
| Super Admin | System-wide | Platform management, all organizations |
| Admin | Organization | Full access to one org |
| Plant Manager | Plant | Manage plant resources, production |
| Production Manager | Plant | BOMs, routings, production orders |
| Warehouse Manager | Plant | Inventory, warehouses, transfers |
| Quality Manager | Plant | Quality inspections, NCRs |
| Maintenance Manager | Plant | Machines, maintenance work orders |
| Purchasing Manager | Plant | Purchase requisitions, POs |
| Sales Manager | Plant | Sales orders, deliveries |
| Finance Manager | Plant | Cost reports, financial inventory valuation |
| Viewer | Organization | Read-only access |
