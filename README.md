# ArcWell Manufacturing Execution Platform

A modern cloud-based Manufacturing Execution Platform (MES) built with Laravel and Vue.js to help manufacturers manage production, inventory, warehouse operations, quality control, and shop floor execution from a single system.

---

# Features

## Core

- Multi-Plant Support
- Multi-Warehouse
- Multi-Company (optional)
- Role & Permission Management
- Staff Management
- Departments
- Shifts
- Work Centers
- Machines

## Production

- Production Orders
- Work Orders
- Routing
- Bill of Materials (BOM)
- Material Issue
- Production Reporting
- Finished Goods Receipt

## Inventory

- Product Management
- Stock Management
- Warehouse Management
- Bin Locations
- Stock Transfers
- Batch / Lot Tracking
- Serial Numbers

## Shop Floor

- Operator Dashboard
- Machine Assignment
- Production Tracking
- Downtime Recording
- Barcode / QR Scanning

## Quality

- Incoming Inspection
- In Process Inspection
- Final Inspection
- Reject & Rework

## Reporting

- Live Production Dashboard
- OEE Dashboard
- Inventory Reports
- Machine Utilization
- Production Analytics

---

# Technology Stack

Backend

- Laravel
- PHP 8.4+
- MySQL

Frontend

- Vue 3
- Vue Router
- Vuex / Pinia
- Tailwind CSS

Others

- Laravel Queue
- Laravel Scheduler
- Redis
- Axios

---

# Project Structure

```
app/
Modules/
resources/
routes/
database/
storage/
```

Each business module should remain isolated whenever possible.

Example

```
Modules/

Staff
Warehouse
Inventory
Production
Quality
Reports
Settings
```

---

# Development Standards

## Backend

- Repository Pattern (where appropriate)
- Form Requests for validation
- Resource classes for API responses
- Service classes for business logic
- Eloquent relationships
- Database Transactions
- Queue heavy operations

## Frontend

- Reusable Components
- Common DataGrid
- Reusable Modal
- Reusable Form Components
- Toast Notifications
- Loading Skeletons

---

# Naming Convention

Controllers

```
StaffController
WarehouseController
ProductionOrderController
```

Services

```
StaffService
WarehouseService
```

Repositories

```
StaffRepository
```

Vue Components

```
StaffTable.vue
StaffForm.vue
WarehouseModal.vue
```

---

# Common Components

## DataGrid

Used throughout the application.

Supports

- Server Pagination
- Server Sorting
- Search
- Column Filters
- Saved Views
- Column Visibility
- Density
- Bulk Actions
- Export
- Sticky Header
- Sticky First Column

---

# Permissions

Every module follows

View

Create

Update

Delete

Export

Import

Approve

Example

```
staff.view
staff.create
staff.update
staff.delete

warehouse.view
warehouse.create
```

---

# Coding Style

Backend

- PSR-12
- Laravel Best Practices
- Small Methods
- Single Responsibility

Frontend

- Composition API
- Reusable Components
- Avoid duplicated logic

---

# Branch Strategy

```
main

staging

feature/module-name

bugfix/issue-name

hotfix/issue-name
```

---

# Environment

```
cp .env.example .env

composer install

npm install

php artisan key:generate

php artisan migrate

npm run dev

php artisan serve
```

---

# Future Roadmap

## Phase 1

- Authentication
- Plants
- Roles
- Staff

## Phase 2

- Products
- Warehouse
- Inventory

## Phase 3

- Production
- Shop Floor

## Phase 4

- Quality

## Phase 5

- Reports

## Phase 6

- AI Manufacturing Copilot

---

# Design Principles

- Build reusable components first.
- Every feature should support permissions.
- Server-side pagination for large datasets.
- Mobile-friendly where practical.
- Avoid duplicated business logic.
- Prefer composition over duplication.
- Performance before visual effects.
- Every module should be independently maintainable.

---

# License

Copyright © ArcWell.
All Rights Reserved.