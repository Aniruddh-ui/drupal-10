# FOSSEE Event Registration Module

A custom Drupal 10/11 module providing a complete Event Registration system with an administrative interface for event management and a public-facing AJAX-powered registration form.

---

## Module Overview

The **FOSSEE Event Registration Module** is a professionally architected, fully custom Drupal solution designed to handle event registration workflows without relying on any contributed modules. It provides administrators with a streamlined interface to create and manage events, while offering end-users an intuitive, dynamic registration experience powered by AJAX-driven form interactions.

The module strictly adheres to Drupal coding standards, employing dependency injection throughout all service classes, forms, and controllers. All business logic is encapsulated within dedicated service layers, ensuring testability, maintainability, and separation of concerns.

---

## Key Features

| Feature | Description |
|---------|-------------|
| **Custom Database Tables** | Uses Drupal's Schema API to define two dedicated tables for events and registrations—ensuring data integrity and optimal query performance. |
| **AJAX Dependent Dropdowns** | The registration form implements a three-tier cascading dropdown (Category → Date → Event Name) using Drupal's AJAX API for a seamless user experience. |
| **Strict Regex Validation** | All text inputs are validated against strict regex patterns to prevent special characters and ensure data sanitization. |
| **CSV Export** | Administrators can export filtered registration data to CSV with proper UTF-8 encoding and Excel-compatible formatting. |
| **Configurable Email Notifications** | Supports both user confirmation emails and admin notification emails, with enable/disable toggles via the configuration interface. |
| **XSS Prevention** | All user-submitted data is properly sanitized using `htmlspecialchars()` and Drupal render arrays. |

---

## Technical Architecture

### Database Schema

The module creates two custom database tables via `hook_schema()`:

#### Table: `fossee_event_config`

Stores event definitions created by administrators.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (Serial) | Primary key - Unique event identifier |
| `event_name` | VARCHAR(255) | Display name of the event |
| `category` | VARCHAR(128) | Event category for filtering (indexed) |
| `start_date` | INT (Timestamp) | Registration window opens |
| `end_date` | INT (Timestamp) | Registration window closes |
| `event_date` | INT (Timestamp) | Actual event date |
| `created` | INT (Timestamp) | Record creation time |
| `changed` | INT (Timestamp) | Last modification time |

#### Table: `fossee_event_registration`

Stores participant registration data.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (Serial) | Primary key - Unique registration ID |
| `event_id` | INT | Foreign key → `fossee_event_config.id` |
| `name` | VARCHAR(255) | Participant's full name |
| `email` | VARCHAR(255) | Participant's email address |
| `department` | VARCHAR(255) | Participant's department/organization |
| `event_date` | INT (Timestamp) | Selected event date |
| `created` | INT (Timestamp) | Registration submission time |

**Constraint:** A unique composite index on `(email, event_date)` prevents duplicate registrations.

---

## Installation & Configuration

### Step 1: Install the Module

1. Place the module in your Drupal installation:
   ```
   /modules/custom/fossee_event/
   ```

2. Enable the module using Drush:
   ```bash
   drush en fossee_event -y
   drush cr
   ```
   
   Or via the Admin UI: Navigate to **Extend** → Search for "FOSSEE Event Registration" → Check the box → Click **Install**.

### Step 2: Configure Permissions

Navigate to **People** → **Permissions** and assign the following:

| Permission | Recommended Role |
|------------|------------------|
| `Administer FOSSEE Events` | Administrator |
| `View FOSSEE Registrations` | Administrator, Content Editor |
| `Register for FOSSEE Events` | Authenticated User, Anonymous User |

> **Note:** If you want anonymous users to register, ensure the "Register for FOSSEE Events" permission is granted to the "Anonymous user" role.

### Step 3: Configure Email Settings

Navigate to `/admin/config/fossee/settings` to configure:

- **Admin Notification Email** – Email address to receive notifications for new registrations.
- **Enable Admin Notifications** – Toggle to enable/disable admin emails.
- **Enable User Confirmation** – Toggle to enable/disable user confirmation emails.

---

## User Guide (How to Test)

Follow this workflow to verify all module functionality:

### Step 1: Create an Event

1. Navigate to: `/admin/fossee/event/add`
2. Fill in the required fields:
   - **Event Name:** e.g., "Python Workshop 2024"
   - **Category:** e.g., "Workshop"
   - **Registration Start Date:** Set to today or earlier
   - **Registration End Date:** Set to a future date
   - **Event Date:** The actual date of the event
3. Click **Create Event**
4. Verify the event appears in the "Existing Events" table below the form

### Step 2: Register for the Event

1. Navigate to: `/fossee/register`
2. Observe the AJAX behavior:
   - Select a **Category** → The Event Date dropdown populates automatically
   - Select an **Event Date** → The Event Name dropdown populates automatically
3. Fill in participant details:
   - **Full Name:** Your name (letters, spaces, hyphens only)
   - **Email:** A valid email address
   - **Department:** Your department (alphanumeric, spaces, hyphens only)
4. Click **Register**
5. Verify the success message appears

### Step 3: View & Export Registration Data

1. Navigate to: `/admin/fossee/registrations`
2. Review the registrations table showing:
   - Participant counter ("Total participants: X")
   - Filter dropdowns for Event Date and Event Name
   - Registration details in tabular format
3. Apply filters to narrow down results
4. Click **Export to CSV** to download the filtered data
5. Open the CSV file to verify UTF-8 encoding and data accuracy

### Step 4: Configure Module Settings

1. Navigate to: `/admin/config/fossee/settings`
2. Enter an admin email address
3. Toggle notification settings as needed
4. Click **Save configuration**
5. Submit a new registration and verify email notifications are sent

---

## Requirements

| Requirement | Version |
|-------------|---------|
| Drupal | 10.x or 11.x |
| PHP | 8.1+ |
| Database | MySQL 5.7+ / MariaDB 10.3+ / PostgreSQL 12+ |

### Dependencies

- **None** – This module is fully custom and does not require any contributed modules.

---

## Module Routes Summary

| Path | Purpose |
|------|---------|
| `/admin/config/fossee/settings` | Global module configuration |
| `/admin/fossee/event/add` | Create new events |
| `/admin/fossee/registrations` | View and filter registrations |
| `/admin/fossee/export` | Export registrations to CSV |
| `/fossee/register` | Public registration form |

---

## File Structure

```
modules/custom/fossee_event/
├── fossee_event.info.yml          # Module metadata
├── fossee_event.install           # Database schema (hook_schema)
├── fossee_event.module            # Procedural hooks (hook_mail)
├── fossee_event.permissions.yml   # Permission definitions
├── fossee_event.routing.yml       # Route definitions
├── fossee_event.services.yml      # Service definitions with DI
├── config/
│   ├── install/
│   │   └── fossee_event.settings.yml
│   └── schema/
│       └── fossee_event.schema.yml
└── src/
    ├── Controller/
    │   └── RegistrationListController.php
    ├── Form/
    │   ├── EventForm.php
    │   ├── RegistrationForm.php
    │   └── SettingsForm.php
    └── Service/
        ├── EventService.php
        └── RegistrationService.php
```

---

## Author

**Module:** FOSSEE Event Registration  
**Version:** 1.0.0  
**Compatibility:** Drupal 10.x / 11.x  
**Author:** FOSSEE Intern  
**Organization:** FOSSEE, IIT Bombay  
**License:** GPL-2.0-or-later
