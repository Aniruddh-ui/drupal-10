# FOSSEE Event Registration Module

A custom Drupal 10 module for managing event registrations with AJAX-driven forms, configurable email notifications, and administrative data export capabilities.

## Table of Contents

- [Setup](#setup)
- [URLs](#urls)
- [Architecture](#architecture)
- [AJAX Flow](#ajax-flow)
- [Validation](#validation)
- [Credits](#credits)

---

## Setup

### Requirements

- Drupal 10.x
- PHP 8.1+
- No contrib modules required (fully custom implementation)

### Installation

1. Copy the module folder to your Drupal installation:
   ```
   modules/custom/fossee_event/
   ```

2. Enable the module via Drush:
   ```bash
   drush en fossee_event -y
   ```
   Or via the admin UI: **Extend** → Search for "FOSSEE Event Registration" → Enable.

3. Configure permissions at **People** → **Permissions**:
   - `Administer FOSSEE Events` – For admins creating events and managing settings.
   - `View FOSSEE Registrations` – For viewing registration data and exporting CSV.
   - `Register for FOSSEE Events` – For public users to access the registration form.

4. Configure email settings at `/admin/config/fossee/settings`.

---

## URLs

| Path | Description | Permission |
|------|-------------|------------|
| `/admin/config/fossee/settings` | Global module settings (email configuration) | `administer fossee events` |
| `/admin/fossee/event/add` | Create new events | `administer fossee events` |
| `/admin/fossee/registrations` | View all registrations with filters | `view fossee registrations` |
| `/admin/fossee/export` | Export registrations to CSV | `view fossee registrations` |
| `/fossee/register` | Public registration form | `register for fossee events` |

---

## Architecture

### Database Schema

The module uses **two custom database tables** (NOT Config API) for storing dynamic event and registration data:

#### Table: `fossee_event_config`

Stores event definitions created by administrators.

| Column | Type | Description |
|--------|------|-------------|
| `id` | SERIAL | Primary key |
| `event_name` | VARCHAR(255) | Event display name |
| `category` | VARCHAR(128) | Event category (indexed for AJAX) |
| `start_date` | INT | Registration window opens (Unix timestamp) |
| `end_date` | INT | Registration window closes (Unix timestamp) |
| `event_date` | INT | Actual event date (Unix timestamp) |
| `created` | INT | Record creation timestamp |
| `changed` | INT | Record modification timestamp |

#### Table: `fossee_event_registration`

Stores user registration submissions.

| Column | Type | Description |
|--------|------|-------------|
| `id` | SERIAL | Primary key |
| `event_id` | INT | Foreign key to events table |
| `name` | VARCHAR(255) | Participant name |
| `email` | VARCHAR(255) | Participant email |
| `department` | VARCHAR(255) | Participant department |
| `event_date` | INT | Selected event date (Unix timestamp) |
| `created` | INT | Registration timestamp |

**Unique Constraint:** `email + event_date` – Prevents duplicate registrations.

### Service Layer

All business logic is encapsulated in services with dependency injection:

#### EventService (`fossee_event.event_service`)

Handles CRUD operations for the events table:
- `createEvent(array $data): int`
- `getCategories(): array`
- `getDatesByCategory(string $category): array`
- `getEventsByDateAndCategory(string $category, int $event_date): array`
- `getActiveEvents(): array`
- `loadEvent(int $id): ?array`
- `getAllEvents(): array`

#### RegistrationService (`fossee_event.registration_service`)

Handles registration submissions and email notifications:
- `saveRegistration(array $data): int`
- `isDuplicate(string $email, int $event_date): bool`
- `sendUserConfirmation(array $registration): void`
- `sendAdminNotification(array $registration): void`
- `getRegistrations(array $filters = []): array`
- `countRegistrations(array $filters = []): int`

### Configuration (Config API)

Global module settings are stored in Config API at `fossee_event.settings`:

```yaml
admin_notification_email: 'admin@example.com'
admin_notification_enabled: true
user_confirmation_enabled: true
```

---

## AJAX Flow

The registration form implements a three-tier cascading dropdown using Drupal's AJAX API:

```
┌─────────────┐     AJAX      ┌─────────────┐     AJAX      ┌─────────────┐
│  Category   │ ───────────▶  │ Event Date  │ ───────────▶  │ Event Name  │
│  (Select)   │               │  (Select)   │               │  (Select)   │
└─────────────┘               └─────────────┘               └─────────────┘
```

1. **User selects Category** → Triggers `updateEventDatesCallback`
2. **Event Date dropdown populates** with dates matching the selected category
3. **User selects Event Date** → Triggers `updateEventNamesCallback`
4. **Event Name dropdown populates** with events matching category + date
5. **User submits form** → Registration saved, emails triggered

### AJAX Implementation

- Uses `AjaxResponse` and `ReplaceCommand` from Drupal Core
- Callbacks are minimal; all query logic delegated to `EventService`
- Form elements wrapped in containers with unique IDs for targeted replacement

---

## Validation

### Text Field Sanitization

All text fields enforce strict character restrictions using regex:

| Field | Pattern | Allowed Characters |
|-------|---------|-------------------|
| `event_name` | `/^[a-zA-Z0-9\s\-]+$/` | Letters, numbers, spaces, hyphens |
| `category` | `/^[a-zA-Z0-9\s\-]+$/` | Letters, numbers, spaces, hyphens |
| `name` | `/^[a-zA-Z\s\-]+$/` | Letters, spaces, hyphens |
| `department` | `/^[a-zA-Z0-9\s\-]+$/` | Letters, numbers, spaces, hyphens |

### Date Validation

- **End Date ≥ Start Date** – Registration window must not close before it opens.
- **Event Date ≥ Start Date** – Event cannot occur before registration opens.

### Duplicate Prevention

- **Application Layer:** `RegistrationService::isDuplicate()` checks before save.
- **Database Layer:** Unique constraint on `email + event_date` (defense in depth).

### XSS Prevention

All user-submitted data is escaped using:
- `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` in table output
- Drupal render arrays for safe HTML generation

---

## Credits

**Module:** FOSSEE Event Registration  
**Version:** 1.0.0  
**Drupal Compatibility:** 10.x  
**Author:** FOSSEE Intern  
**Organization:** FOSSEE, IIT Bombay  
**License:** GPL-2.0+

---

## File Structure

```
modules/custom/fossee_event/
├── fossee_event.info.yml
├── fossee_event.install
├── fossee_event.module
├── fossee_event.permissions.yml
├── fossee_event.routing.yml
├── fossee_event.services.yml
├── config/
│   ├── install/
│   │   └── fossee_event.settings.yml
│   └── schema/
│       └── fossee_event.schema.yml
├── src/
│   ├── Controller/
│   │   └── RegistrationListController.php
│   ├── Form/
│   │   ├── EventForm.php
│   │   ├── RegistrationForm.php
│   │   └── SettingsForm.php
│   └── Service/
│       ├── EventService.php
│       └── RegistrationService.php
└── README.md
```
