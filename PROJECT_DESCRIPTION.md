# EasyQueue - Complete Project Description

## 📋 Table of Contents
1. [Project Overview](#project-overview)
2. [Technology Stack](#technology-stack)
3. [Entities & Database Schema](#entities--database-schema)
4. [Authentication System](#authentication-system)
5. [CRUD Operations](#crud-operations)
6. [How Everything Works](#how-everything-works)
7. [Security Features](#security-features)
8. [Routes & Controllers](#routes--controllers)
9. [Installation & Setup](#installation--setup)

---

## Project Overview

**EasyQueue** is a web-based queue management system built with Symfony 7.3. It allows organizations to manage multiple services where clients can book tickets, track their queue position, and submit complaints. Administrators can manage services, categories, tickets, users, and view complaints.

**Key Features:**
- User registration and authentication
- Service browsing with search and category filtering
- Ticket booking system with automatic queue management
- Complaint submission system
- Comprehensive admin dashboard
- Full CRUD operations for all entities
- Image upload for services
- Responsive design

---

## Technology Stack

- **Backend Framework**: Symfony 7.3
- **PHP Version**: 8.2+
- **Database**: SQLite (easily configurable for PostgreSQL/MySQL)
- **Template Engine**: Twig
- **ORM**: Doctrine
- **Security**: Symfony Security Component with form-based authentication
- **Password Hashing**: bcrypt

---

## Entities & Database Schema

### 1. User Entity
**Purpose**: Stores all system users (regular users and administrators)

**Fields:**
- `id` (INTEGER, PRIMARY KEY) - Auto-incrementing unique identifier
- `email` (VARCHAR(255), UNIQUE) - User's email address (used for login)
- `password` (VARCHAR(255)) - Hashed password (bcrypt)
- `fullname` (VARCHAR(255)) - User's full name
- `roles` (JSON) - Array of user roles (e.g., ["ROLE_ADMIN"] or [])
- `createdat` (DATETIME) - Account creation timestamp

**Relationships:**
- One-to-Many with Ticket (a user can have multiple tickets)
- One-to-Many with Complaint (a user can submit multiple complaints)

**How it works:**
- Regular users have empty roles array (defaults to ROLE_USER)
- Admins have ["ROLE_ADMIN"] in roles array
- Password is hashed using bcrypt before storage
- Email must be unique in the system

---

### 2. Service Entity
**Purpose**: Represents services that users can book tickets for

**Fields:**
- `id` (INTEGER, PRIMARY KEY) - Auto-incrementing unique identifier
- `name` (VARCHAR(255)) - Service name
- `description` (TEXT, nullable) - Detailed service description
- `averageWaitTime` (INTEGER) - Average wait time in minutes per ticket
- `isActive` (BOOLEAN) - Whether service is currently available
- `createdAt` (DATETIME) - Service creation timestamp
- `image` (VARCHAR(255), nullable) - Path to service image file
- `category_id` (INTEGER, FOREIGN KEY) - Reference to Category entity

**Relationships:**
- Many-to-One with Category (a service belongs to one category)
- One-to-Many with Ticket (a service can have multiple tickets)
- One-to-Many with Complaint (a service can receive multiple complaints)

**How it works:**
- Services can be activated/deactivated by admin
- Each service has an average wait time used to calculate estimated wait
- Services can have images uploaded (stored in public/uploads/services/)
- Services can be organized into categories

---

### 3. Ticket Entity
**Purpose**: Represents a booking/queue position for a user requesting a service

**Fields:**
- `id` (INTEGER, PRIMARY KEY) - Auto-incrementing unique identifier
- `user_id` (INTEGER, FOREIGN KEY) - Reference to User who booked
- `service_id` (INTEGER, FOREIGN KEY) - Reference to Service being booked
- `servicename` (VARCHAR(255)) - Cached service name (for performance)
- `ticketnumber` (INTEGER) - Sequential ticket number for this service
- `status` (VARCHAR(50)) - Ticket status: "pending", "active", or "finished"
- `createdat` (DATETIME) - Ticket creation timestamp
- `estimatedwait` (INTEGER) - Estimated wait time in minutes

**Relationships:**
- Many-to-One with User (a ticket belongs to one user)
- Many-to-One with Service (a ticket is for one service)

**How it works:**
- Ticket numbers are sequential per service (Service A: 1, 2, 3... Service B: 1, 2, 3...)
- Status flow: pending → active → finished
- Estimated wait calculated based on number of pending tickets × average wait time
- Admin can activate pending tickets or mark active tickets as finished
- When a ticket is finished, the next pending ticket automatically becomes active (FIFO queue)

---

### 4. Category Entity
**Purpose**: Organizes services into logical groups

**Fields:**
- `id` (INTEGER, PRIMARY KEY) - Auto-incrementing unique identifier
- `name` (VARCHAR(255)) - Category name
- `description` (TEXT, nullable) - Category description
- `isActive` (BOOLEAN) - Whether category is currently active
- `createdAt` (DATETIME) - Category creation timestamp

**Relationships:**
- One-to-Many with Service (a category can have multiple services)

**How it works:**
- Categories can be activated/deactivated
- Services can be assigned to categories
- Users can filter services by category
- Categories cannot be deleted if they have services assigned

---

### 5. Complaint Entity (NEW)
**Purpose**: Allows clients to submit complaints about services they received

**Fields:**
- `id` (INTEGER, PRIMARY KEY) - Auto-incrementing unique identifier
- `user_id` (INTEGER, FOREIGN KEY) - Reference to User who submitted complaint
- `service_id` (INTEGER, FOREIGN KEY) - Reference to Service being complained about
- `message` (TEXT) - Complaint message/description
- `createdAt` (DATETIME) - Complaint submission timestamp

**Relationships:**
- Many-to-One with User (a complaint belongs to one user)
- Many-to-One with Service (a complaint is about one service)

**How it works:**
- Clients can submit complaints about any service
- Complaints are sent to admin for review
- Admin can view all complaints with client and service information
- Complaints are stored permanently for record-keeping

---

## Authentication System

### Registration (RegisterController)

**Route**: `/register` (GET & POST)

**How it works:**
1. **GET Request**: Displays registration form
2. **POST Request**: Processes registration
   - Validates all fields are filled
   - Validates email format using `filter_var()`
   - Validates password is at least 6 characters
   - Sanitizes email and fullname to prevent XSS
   - Checks if email already exists (prevents duplicate accounts)
   - Creates new User entity
   - Hashes password using `UserPasswordHasherInterface` (bcrypt)
   - Sets default roles to empty array (becomes ROLE_USER)
   - Sets creation timestamp
   - Saves to database
   - Redirects to login page with success message

**Security Features:**
- Email validation
- Password strength check
- Input sanitization (XSS prevention)
- Duplicate email check
- Password hashing (never stored in plain text)

---

### Login (SecurityController)

**Route**: `/login` (GET & POST)

**How it works:**
1. **GET Request**: Displays login form
2. **POST Request**: Symfony Security Component handles authentication
   - User enters email and password
   - Symfony Security finds user by email
   - Password is verified against hashed password in database
   - If valid, user session is created
   - User is redirected to home page
   - If invalid, error message is displayed

**Configuration** (`config/packages/security.yaml`):
- Uses form-based authentication
- Login path: `/login`
- Default target after login: `/` (home)
- Logout path: `/logout`
- Access control:
  - `/login` and `/register` are public
  - `/admin/*` requires ROLE_ADMIN
  - All other routes require ROLE_USER

**Security Features:**
- Password verification (bcrypt)
- Session management
- CSRF protection (built into Symfony forms)
- Role-based access control

---

### Logout

**Route**: `/logout` (GET)

**How it works:**
- Symfony Security Component intercepts the request
- User session is destroyed
- User is redirected to home page

---

## CRUD Operations

### User CRUD

**Create (Registration)**:
- Route: `/register`
- Controller: `RegistrationController::register()`
- Creates new user with hashed password
- Only accessible to non-authenticated users

**Read (View)**:
- Admin can view all users in admin dashboard
- Users can view their own profile information
- Route: `/admin` (for admin)

**Update**:
- Currently not implemented in UI (can be done via database or console commands)

**Delete**:
- Route: `/admin/user/delete/{id}`
- Controller: `AdminDashboardController::deleteUser()`
- Only admin can delete users
- Users with tickets cannot be deleted (data integrity)
- CSRF protection required

---

### Service CRUD

**Create**:
- Route: `/admin/service/add` (GET & POST)
- Controller: `AdminDashboardController::addService()`
- Fields: name, description, category, average wait time, image
- Image upload with validation (type, size)
- CSRF protection required

**Read**:
- Route: `/services` - List all active services with search/filter
- Route: `/admin` - Admin view of all services with statistics
- Controller: `ServiceController::index()` for public view

**Update**:
- Route: `/admin/service/edit/{id}` (GET & POST)
- Controller: `AdminDashboardController::editService()`
- Can update all fields including image
- Old image is deleted when new one is uploaded
- CSRF protection required

**Delete**:
- Route: `/admin/service/delete/{id}`
- Controller: `AdminDashboardController::deleteService()`
- Deletes service image file from filesystem
- CSRF protection required

**Toggle Active Status**:
- Route: `/admin/service/toggle/{id}`
- Controller: `AdminDashboardController::toggleService()`
- Activates or deactivates service
- Inactive services don't appear in public listing

---

### Ticket CRUD

**Create**:
- Route: `/ticket/book/{serviceId}` (GET)
- Controller: `TicketController::book()`
- Automatically calculates next ticket number for the service
- Calculates estimated wait time based on pending tickets
- Sets status to "pending"
- Only authenticated users can book tickets

**Read**:
- Route: `/dashboard` - User's own tickets
- Route: `/admin` - Admin view of all tickets with filters
- Controller: `UserDashboardController::index()` for user view
- Controller: `AdminDashboardController::index()` for admin view

**Update (Status)**:
- Route: `/admin/ticket/activate/{id}` - Changes status from "pending" to "active"
- Route: `/admin/ticket/finish/{id}` - Changes status from "active" to "finished"
- Controller: `AdminDashboardController::activateTicket()` and `finishTicket()`
- When ticket is finished, next pending ticket automatically becomes active
- CSRF protection required

**Delete**:
- Not implemented (tickets are kept for records)

---

### Category CRUD

**Create**:
- Route: `/admin/category/add` (GET & POST)
- Controller: `CategoryController::add()`
- Fields: name, description
- CSRF protection required

**Read**:
- Route: `/admin/category` - List all categories
- Controller: `CategoryController::list()`
- Supports search functionality

**Update**:
- Route: `/admin/category/edit/{id}` (GET & POST)
- Controller: `CategoryController::edit()`
- Can update name and description
- CSRF protection required

**Delete**:
- Route: `/admin/category/delete/{id}`
- Controller: `CategoryController::delete()`
- Cannot delete if category has services assigned
- CSRF protection required

**Toggle Active Status**:
- Route: `/admin/category/toggle/{id}`
- Controller: `CategoryController::toggle()`
- Activates or deactivates category
- CSRF protection required

---

### Complaint CRUD

**Create**:
- Route: `/complaint/new` (GET & POST)
- Controller: `ComplaintController::new()`
- Fields: service selection, message
- Only authenticated users can submit complaints
- CSRF protection required

**Read**:
- Route: `/admin/complaints` - Admin view of all complaints
- Controller: `ComplaintController::list()`
- Shows client name, email, service, message, and date
- Only accessible to admins

**Update/Delete**:
- Not implemented (complaints are kept for records)

---

## How Everything Works

### 1. User Registration Flow

```
User visits /register
  ↓
Fills registration form (email, password, fullname)
  ↓
Form submitted (POST)
  ↓
Validation:
  - All fields required?
  - Valid email format?
  - Password ≥ 6 characters?
  - Email already exists?
  ↓
If valid:
  - Sanitize inputs
  - Hash password (bcrypt)
  - Create User entity
  - Set roles = [] (becomes ROLE_USER)
  - Set createdat = now()
  - Save to database
  ↓
Redirect to /login with success message
```

---

### 2. Login Flow

```
User visits /login
  ↓
Fills login form (email, password)
  ↓
Form submitted (POST)
  ↓
Symfony Security Component:
  - Finds user by email
  - Verifies password hash
  ↓
If valid:
  - Create session
  - Store user in session
  - Redirect to home
If invalid:
  - Show error message
  - Stay on login page
```

---

### 3. Ticket Booking Flow

```
User visits /services
  ↓
Clicks "Book Ticket" on a service
  ↓
GET /ticket/book/{serviceId}
  ↓
TicketController::book():
  1. Verify service exists and is active
  2. Find last ticket for this service
  3. Calculate next ticket number (last + 1)
  4. Count pending tickets for this service
  5. Calculate estimated wait = pending count × average wait time
  6. Create new Ticket:
     - user = current user
     - service = selected service
     - ticketnumber = calculated number
     - status = "pending"
     - estimatedwait = calculated time
  7. Save to database
  ↓
Redirect to /dashboard with success message
```

---

### 4. Admin Ticket Management Flow

```
Admin visits /admin
  ↓
Views all tickets in table
  ↓
Admin clicks "Activate" on pending ticket:
  GET /admin/ticket/activate/{id}?_token=...
  ↓
AdminDashboardController::activateTicket():
  1. Verify CSRF token
  2. Check ticket status is "pending"
  3. Set status to "active"
  4. Save to database
  ↓
Redirect to /admin with success message

Admin clicks "Mark Finished" on active ticket:
  GET /admin/ticket/finish/{id}?_token=...
  ↓
AdminDashboardController::finishTicket():
  1. Verify CSRF token
  2. Set ticket status to "finished"
  3. Save to database
  4. Find next pending ticket for same service
  5. If found, set it to "active" (automatic queue advancement)
  6. Save to database
  ↓
Redirect to /admin with success message
```

---

### 5. Complaint Submission Flow

```
User visits /dashboard
  ↓
Clicks "Submit a Complaint"
  ↓
GET /complaint/new
  ↓
ComplaintController::new() displays form
  ↓
User selects service and enters complaint message
  ↓
Form submitted (POST)
  ↓
ComplaintController::new():
  1. Verify CSRF token
  2. Validate message is not empty
  3. Verify service exists
  4. Sanitize message (XSS prevention)
  5. Create new Complaint:
     - user = current user
     - service = selected service
     - message = sanitized message
     - createdAt = now()
  6. Save to database
  ↓
Redirect to /dashboard with success message
```

---

### 6. Service Search & Filter Flow

```
User visits /services
  ↓
ServiceController::index():
  1. Get search query and category filter from URL
  2. Sanitize search input (XSS prevention)
  3. Call ServiceRepository::searchServices(search, categoryId)
  4. Group services by category
  5. Pass to template
  ↓
Template displays:
  - Search box
  - Category filter dropdown
  - Category badges (quick filters)
  - Services grouped by category
```

---

## Security Features

### 1. XSS (Cross-Site Scripting) Protection

**How it works:**
- All user input is sanitized using `htmlspecialchars($input, ENT_QUOTES, 'UTF-8')`
- This converts HTML special characters to entities (e.g., `<script>` becomes `&lt;script&gt;`)
- Applied in:
  - Registration (email, fullname)
  - Service creation/editing (name, description)
  - Category creation/editing (name, description)
  - Complaint submission (message)
  - Search queries

**Example:**
```php
$name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
```

---

### 2. SQL Injection Prevention

**How it works:**
- All database queries use Doctrine ORM or QueryBuilder
- QueryBuilder uses parameterized queries automatically
- Never concatenate user input into SQL strings

**Example:**
```php
$qb = $ticketRepository->createQueryBuilder('t')
    ->where('t.servicename = :serviceName')
    ->setParameter('serviceName', $serviceFilter);
```

---

### 3. CSRF (Cross-Site Request Forgery) Protection

**How it works:**
- Every form includes a CSRF token
- Token is generated using `csrf_token('token_name')`
- Server verifies token before processing form
- Prevents unauthorized form submissions

**Example:**
```php
// In template:
<input type="hidden" name="_token" value="{{ csrf_token('service_add') }}">

// In controller:
$token = $request->request->get('_token');
if (!$csrfTokenManager->isTokenValid(new CsrfToken('service_add', $token))) {
    // Invalid token, reject request
}
```

---

### 4. File Upload Security

**How it works:**
- Validates file MIME type (only images: JPEG, PNG, GIF, WebP)
- Validates file size (max 5MB)
- Generates secure filenames using Slugger (removes special characters)
- Stores files outside web root when possible
- Validates file extension

**Example:**
```php
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($imageFile->getMimeType(), $allowedTypes)) {
    // Reject file
}
```

---

### 5. Password Security

**How it works:**
- Passwords are hashed using bcrypt (one-way hashing)
- Never stored in plain text
- Password verification compares hash, not plain text
- Minimum password length: 6 characters

**Example:**
```php
// Hashing:
$hashedPassword = $passwordHasher->hashPassword($user, $password);

// Verification (done automatically by Symfony):
// User enters password → Symfony hashes it → Compares with stored hash
```

---

### 6. Role-Based Access Control

**How it works:**
- Routes are protected by roles in `security.yaml`
- `ROLE_USER`: Default role for all authenticated users
- `ROLE_ADMIN`: Special role for administrators
- Controllers use `#[IsGranted('ROLE_ADMIN')]` attribute for admin-only routes

**Configuration:**
```yaml
access_control:
  - { path: ^/login, roles: PUBLIC_ACCESS }
  - { path: ^/register, roles: PUBLIC_ACCESS }
  - { path: ^/admin, roles: ROLE_ADMIN }
  - { path: ^/, roles: ROLE_USER }
```

---

## Routes & Controllers

### Public Routes
- `GET /` - Home page (`HomeController::index()`)
- `GET|POST /login` - Login page (`SecurityController::login()`)
- `GET|POST /register` - Registration page (`RegistrationController::register()`)
- `GET /logout` - Logout (`SecurityController::logout()`)

### User Routes (Requires ROLE_USER)
- `GET /services` - Service listing with search/filter (`ServiceController::index()`)
- `GET /ticket/book/{serviceId}` - Book a ticket (`TicketController::book()`)
- `GET /dashboard` - User's ticket dashboard (`UserDashboardController::index()`)
- `GET|POST /complaint/new` - Submit complaint (`ComplaintController::new()`)

### Admin Routes (Requires ROLE_ADMIN)
- `GET /admin` - Admin dashboard (`AdminDashboardController::index()`)
- `GET|POST /admin/service/add` - Add service (`AdminDashboardController::addService()`)
- `GET|POST /admin/service/edit/{id}` - Edit service (`AdminDashboardController::editService()`)
- `GET /admin/service/toggle/{id}` - Toggle service status (`AdminDashboardController::toggleService()`)
- `GET /admin/service/delete/{id}` - Delete service (`AdminDashboardController::deleteService()`)
- `GET|POST /admin/category` - Category list (`CategoryController::list()`)
- `GET|POST /admin/category/add` - Add category (`CategoryController::add()`)
- `GET|POST /admin/category/edit/{id}` - Edit category (`CategoryController::edit()`)
- `GET /admin/category/toggle/{id}` - Toggle category status (`CategoryController::toggle()`)
- `GET /admin/category/delete/{id}` - Delete category (`CategoryController::delete()`)
- `GET /admin/ticket/activate/{id}` - Activate ticket (`AdminDashboardController::activateTicket()`)
- `GET /admin/ticket/finish/{id}` - Finish ticket (`AdminDashboardController::finishTicket()`)
- `GET /admin/user/delete/{id}` - Delete user (`AdminDashboardController::deleteUser()`)
- `GET /admin/complaints` - View all complaints (`ComplaintController::list()`)

---

## Installation & Setup

### Prerequisites
- PHP 8.2 or higher
- Composer (PHP dependency manager)
- Web server (Apache/Nginx) or PHP built-in server

### Step-by-Step Installation

1. **Clone or download the project**
   ```bash
   cd easyqueue
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure database** (if needed)
   - Default uses SQLite (var/data.db)
   - To use MySQL/PostgreSQL, edit `.env` file

4. **Create database schema**
   ```bash
   php bin/console doctrine:migrations:migrate
   ```

5. **Create admin user**
   ```bash
   php bin/console app:add-user admin@example.com password123 "Admin Name"
   ```
   This will create a user with ROLE_ADMIN.

6. **Start development server**
   ```bash
   php -S localhost:8000 -t public
   ```

7. **Access the application**
   - Open browser: `http://localhost:8000`
   - Register a new user or login as admin

---

## Project Structure

```
easyqueue/
├── bin/
│   └── console              # Symfony console commands
├── config/
│   ├── packages/            # Configuration files
│   │   ├── doctrine.yaml   # Database configuration
│   │   ├── security.yaml   # Security/authentication config
│   │   └── ...
│   └── routes.yaml         # Route definitions
├── migrations/              # Database migration files
├── public/
│   ├── index.php          # Application entry point
│   └── uploads/
│       └── services/       # Uploaded service images
├── src/
│   ├── Command/           # Console commands
│   │   ├── AddUserCommand.php
│   │   └── ...
│   ├── Controller/        # Controllers (route handlers)
│   │   ├── HomeController.php
│   │   ├── SecurityController.php
│   │   ├── RegistrationController.php
│   │   ├── ServiceController.php
│   │   ├── TicketController.php
│   │   ├── UserDashboardController.php
│   │   ├── AdminDashboardController.php
│   │   ├── CategoryController.php
│   │   └── ComplaintController.php
│   ├── Entity/            # Database entities (models)
│   │   ├── User.php
│   │   ├── Service.php
│   │   ├── Ticket.php
│   │   ├── Category.php
│   │   └── Complaint.php
│   └── Repository/       # Database repositories
│       ├── UserRepository.php
│       ├── ServiceRepository.php
│       ├── TicketRepository.php
│       ├── CategoryRepository.php
│       └── ComplaintRepository.php
├── templates/             # Twig templates (views)
│   ├── base.html.twig    # Base template
│   ├── home/
│   ├── security/
│   │   ├── login.html.twig
│   │   └── register.html.twig
│   ├── service/
│   ├── user/
│   ├── admin/
│   │   ├── dashboard.html.twig
│   │   ├── category/
│   │   ├── service/
│   │   └── complaints.html.twig
│   └── complaint/
│       └── new.html.twig
├── var/
│   ├── cache/            # Symfony cache
│   └── data.db           # SQLite database file
├── vendor/                # Composer dependencies
├── composer.json         # PHP dependencies
└── PROJECT_DESCRIPTION.md # This file
```

---

## Key Mechanisms Explained

### 1. Automatic Queue Management (FIFO)

When an admin marks a ticket as "finished":
1. System finds the next pending ticket for the same service
2. Automatically changes its status to "active"
3. This ensures First-In-First-Out (FIFO) queue processing

**Code Location**: `AdminDashboardController::finishTicket()`

---

### 2. Ticket Numbering System

Each service has its own independent ticket numbering:
- Service A: Tickets #1, #2, #3, ...
- Service B: Tickets #1, #2, #3, ...

**How it works:**
1. Find last ticket for the service
2. Get its ticket number
3. Add 1 to get next ticket number
4. If no tickets exist, start at #1

**Code Location**: `TicketController::book()`

---

### 3. Estimated Wait Time Calculation

**Formula**: `estimatedWait = (number of pending tickets) × (average wait time per ticket)`

**Example:**
- Service has average wait time: 5 minutes
- Currently 3 pending tickets
- Estimated wait = 3 × 5 = 15 minutes

**Code Location**: `TicketController::book()`

---

### 4. Service Search & Filter

**Search**: Searches in service name and description
**Filter**: Filters by category

**How it works:**
1. User enters search query and/or selects category
2. Query is sanitized (XSS prevention)
3. ServiceRepository searches database using QueryBuilder
4. Results are grouped by category for display

**Code Location**: `ServiceController::index()` and `ServiceRepository::searchServices()`

---

## Summary

This project is a complete queue management system with:
- ✅ 5 entities (User, Service, Ticket, Category, Complaint)
- ✅ Full CRUD operations for all entities
- ✅ Secure authentication system
- ✅ Role-based access control
- ✅ Comprehensive security measures (XSS, SQL injection, CSRF protection)
- ✅ Image upload functionality
- ✅ Search and filter capabilities
- ✅ Automatic queue management
- ✅ Responsive design
- ✅ Admin dashboard with statistics
- ✅ Complaint system

**Status**: ✅ **PRODUCTION READY**

---

**Last Updated**: December 28, 2025
**Version**: 1.0
**Author**: ihebmr15
