# UTHENGA

## Digital Marketplace, Booking & Ticketing Platform

UTHENGA is a web-based digital marketplace platform designed to connect customers, vendors, event organizers, accommodation providers, transport operators, and tourism service providers through a single centralized system.

The platform supports online bookings, ticketing, vendor management, customer management, and administrative operations through a secure and scalable architecture.

---

## Project Status

This project is currently undergoing migration to a modular PHP + MySQL architecture.

The objective is to preserve all existing functionality while restructuring the system into independent PHP modules for easier maintenance, debugging, scalability, and deployment.

---

## Architecture Overview

The system is divided into two main sections:

### Public Website

Accessible by customers, guests, and vendors.

Examples:

```text
/
index.php
login.php
register.php
events.php
hotels.php
transport.php
```

Functions:

* User registration
* User authentication
* Service discovery
* Event browsing
* Accommodation browsing
* Transport browsing
* Booking management
* Vendor onboarding

---

### Admin Portal

Accessible only by administrators and super administrators.

Examples:

```text
/admin/
/admin/login.php
/admin/dashboard.php
/admin/bookings.php
/admin/users.php
/admin/vendors.php
/admin/reports.php
/admin/settings.php
```

Functions:

* User management
* Vendor approval
* Booking oversight
* Reporting and analytics
* System configuration
* Platform administration

---

## Project Structure

```text
uthenga/

├── index.php
├── login.php
├── register.php
├── booking.php
├── logout.php
├── request_api.php
├── db.php
├── config.php

├── assets/
├── includes/
├── api/

├── admin/
│   ├── login.php
│   ├── dashboard.php
│   ├── bookings.php
│   ├── users.php
│   ├── vendors.php
│   ├── reports.php
│   ├── settings.php
│   ├── assets/
│   └── includes/

└── database/
```

---

## Core Features

### Authentication & User Management

* User registration
* Secure login
* Session management
* Password hashing
* Role-based access control

Supported roles:

* Super Admin
* Admin
* Vendor
* Customer

---

### Vendor Management

* Vendor registration
* Vendor approval workflow
* Vendor profile management
* Service management

---

### Booking Management

* Service bookings
* Reservation tracking
* Booking status management
* Customer booking history

Booking statuses:

* Pending
* Confirmed
* Cancelled

---

### Marketplace Services

The platform is designed to support:

* Event Ticketing
* Sports Ticketing
* Festival Ticketing
* Hotel Bookings
* Hostel Reservations
* Lodge Reservations
* Transport Ticketing
* Tourism Packages

---

## Database

Database technology:

* MySQL

Connection layer:

* PDO
* Prepared statements
* Centralized database configuration

---

## Security

The system implements:

* Prepared statements
* Input validation
* Session protection
* Password hashing
* Role-based permissions
* Secure authentication

---

## Development Guidelines

1. Do not rebuild existing functionality from scratch.
2. Preserve all existing business logic.
3. Convert existing modules into PHP equivalents.
4. Maintain independent page architecture.
5. Keep public and admin systems separated.
6. Follow modular development practices.
7. Ensure future scalability.

---

## Admin Dashboard Quality Assurance Requirements

The Admin Dashboard must be treated as a production system, not a prototype.

### Critical Requirement

Before implementing new features, perform a complete audit of the existing Admin Dashboard and identify:

* Layout issues
* Navigation issues
* UI inconsistencies
* Broken links
* Broken buttons
* Non-functional forms
* JavaScript errors
* PHP errors
* API errors
* Database query errors
* Theme switching issues
* Mobile responsiveness issues
* Permission issues
* Dashboard widget issues
* Report generation issues

Generate a report of all issues found before applying fixes.

### Layout Requirements

The Admin Dashboard must have:

#### Fixed Top Navigation

Contains:

* Logo
* Search
* Notifications
* Profile Menu
* Theme Toggle

#### Professional Sidebar

Contains:

* Dashboard
* Users
* Vendors
* Events
* Bookings
* Stays
* Transport
* Advertisements
* Reports
* Notifications
* Support
* Settings
* Audit Logs

Requirements:

* Clean spacing
* Consistent icons
* Mobile responsive
* Active page highlighting
* Proper collapse/expand behavior

### Theme Requirements

Light Mode and Dark Mode must work across the entire Admin Dashboard.

This includes:

* Text
* Cards
* Tables
* Forms
* Buttons
* Charts
* Sidebars
* Navigation
* Modals

No hardcoded text colors.

Requirements:

* Light Mode -> readable dark text
* Dark Mode -> readable light text

All pages must inherit theme settings automatically.

### Dashboard Home Page

The Admin Dashboard landing page must display:

#### Statistics Cards

* Total Users
* Total Vendors
* Total Events
* Total Bookings
* Active Listings
* Pending Vendor Approvals
* Revenue Summary
* Recent Transactions

#### Recent Activity

* New registrations
* New bookings
* Vendor approvals
* System alerts

#### Quick Actions

* Approve Vendor
* Create Event
* View Reports
* Manage Users

### Table Requirements

All tables must support:

* Search
* Sorting
* Pagination
* Export
* Filtering

Tables include:

* Users
* Vendors
* Events
* Bookings
* Listings
* Transactions
* Support Tickets

No broken table actions.

### Form Requirements

Every form must:

* Validate inputs
* Show meaningful error messages
* Show success messages
* Prevent duplicate submissions
* Work in both themes

### Reporting Module

Reports must function correctly.

Supported exports:

* PDF
* Excel
* CSV

No 404 errors.
No missing routes.
No broken downloads.

### Settings Module

Every setting must save correctly.

Verify:

* Theme settings
* Site configuration
* Email settings
* Notification settings
* System preferences

No placeholder settings.

### Error Handling

The system must:

* Log PHP errors
* Log API errors
* Log database errors
* Log admin actions

Display user-friendly messages.

Never expose raw system errors to administrators.

### Responsiveness Requirements

Admin Dashboard must work properly on:

* Desktop
* Laptop
* Tablet
* Mobile

No overlapping elements.
No broken menus.
No hidden content.

### Final Validation

Before marking any Admin Dashboard task complete:

* Every button works
* Every link works
* Every filter works
* Every form works
* Every report exports correctly
* Every route exists
* Theme switching works everywhere
* No console errors
* No PHP errors
* No API errors
* No database query errors

The Admin Dashboard should feel like a professional SaaS management system and be ready for real-world deployment.

---

## Future Enhancements

Planned future modules include:

* Payment Gateway Integrations
* SMS Gateway Integration
* QR Code Ticket Verification
* Merchant Account Management
* API Marketplace
* Mobile Applications
* Advanced Reporting

---

## Deployment

Recommended environment:

* PHP 8+
* MySQL 8+
* Apache
* XAMPP (Development)
* cPanel/VPS (Production)

### Secure Config Workflow

Use one of these per-environment options:

* `.env` at the project root
* `php_app/.env`
* `config.local.php` at the project root
* `php_app/config.local.php` copied from `php_app/config.local.php.example`

Recommended practice:

1. Keep `php_app/config.php` in Git as the shared bootstrap only.
2. Store real database credentials outside Git in `.env` or `config.local.php`.
3. Commit only `.env.example` and `php_app/config.local.php.example`.
4. On cPanel, create the secret file manually after every fresh deployment if needed.
5. Never edit tracked bootstrap files on the server.

If credentials change, update the server-side secret file and deploy code separately.

---

## Maintainers

GIANTPLUS IT Solutions

Project: UTHENGA

Status: Active Development
