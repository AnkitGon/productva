# ProductVA — Documentation Hub

> **Cloud-based Manufacturing Execution Platform (MES/ERP/WMS)**  
> Built with Laravel 13 · Inertia.js v3 · React 19 · TailwindCSS v4

This folder is the **single source of truth** for the entire project. Every developer, stakeholder, or operator can understand, deploy, extend, and maintain the product by reading these files.

---

## 📁 Documentation Index

| File | Description |
|------|-------------|
| [README.md](./README.md) | This file — master index |
| [01-overview.md](./01-overview.md) | Product vision, target market, and value proposition |
| [02-architecture.md](./02-architecture.md) | Technical stack, directory structure, and system design |
| [03-data-model.md](./03-data-model.md) | Complete database schema, entity relationships, and business rules |
| [04-modules.md](./04-modules.md) | Detailed feature breakdown for every existing module |
| [05-gaps-and-issues.md](./05-gaps-and-issues.md) | Business gaps, missing features, and workflow breaks (current state) |
| [06-roadmap.md](./06-roadmap.md) | MVP completion plan and future module roadmap |
| [07-business-rules.md](./07-business-rules.md) | Enforced and required business rules across all modules |
| [08-permissions.md](./08-permissions.md) | Role-based access control — all roles and their permissions |
| [09-deployment.md](./09-deployment.md) | Local development setup and production deployment guide |
| [10-api-reference.md](./10-api-reference.md) | Complete route and controller reference |
| [11-testing.md](./11-testing.md) | Testing strategy, test coverage map, and how to run tests |
| [12-decisions.md](./12-decisions.md) | Architectural decision records (ADR) |

---

## 🚀 Quick Start

```bash
# Clone and install
git clone <repo>
cd productva
composer install
npm install

# Environment
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate --seed

# Dev server
composer run dev
# or
npm run dev  # (in a second terminal)
```

Default accounts after seeding:

| Role | Email | Password |
|------|-------|----------|
| Super Admin | superadmin@example.com | password |
| Admin | admin@example.com | password |

---

## 🏗️ Current State (July 2026)

ProductVA is in **active MVP development**. Phase 1 foundation (master data, products, BOM, routing, warehouse, inventory adjust/transfer) is built and tested. The following are **stubs or fully missing** for market go-live:

- ❌ Production Orders / Work Orders
- ❌ Quality Control (inspection, NCR, CAPA)
- ❌ Procurement / Purchase Orders / ASN / GRN
- ❌ Sales Orders / Pick-Pack-Ship
- ❌ Maintenance Management
- ❌ Supplier & Customer Masters
- ❌ Reports & Analytics Dashboard
- ❌ Audit Trail / Notifications / Integrations

**Documentation:**
- [04-modules.md](./04-modules.md) — feature status per module
- [07-business-rules.md](./07-business-rules.md) — 22-domain market checklist vs code
- [05-gaps-and-issues.md](./05-gaps-and-issues.md) — UAT blockers and gaps
