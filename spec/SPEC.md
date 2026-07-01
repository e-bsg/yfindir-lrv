# Yfindir — B2B Directory Platform Specification
## Senior Architect's Definitive Edition

**Version:** 3.1
**Date:** 2026-07-01
**Architect:** Perplexity (Senior Software Architect Role)
**Status:** Green-lit — Full Design Authority
**Deployment Target:** cPanel shared hosting (Apache, PHP 8.3+, MySQL/MariaDB, no SSH) + GitHub Actions CI/CD (FTP deploy) + Caddy local dev

---

## 0. Architect's Preamble

This is not a port of the Next.js prototype. This is a ground-up design for a production B2B directory platform, making full use of the Laravel ecosystem to eliminate every class of problem experienced in the JS stack. Every decision here is made with three priorities:

1. **Maintainability by a single developer** who is fluent in PHP but not in React/Next.js internals
2. **Convention over configuration** — let the framework and Filament do the heavy lifting
3. **Hostable on shared cPanel** — no SSH, no Redis, no WebSockets, no long-running processes required. FTP-only deployment via GitHub Actions CI

Where the original draft had gaps or fragile patterns, this spec replaces them entirely.

---

## 1. Product Vision

A multilingual B2B industrial directory where:
- Companies create rich profiles with logos, descriptions, transport capabilities, and service listings
- Buyers discover suppliers via search, filters, and category browsing
- Members communicate through an internal messaging system
- Admins moderate all content before it goes public
- Premium subscribers get enhanced visibility (boosted results, homepage featuring, verified badges)
- The platform owner (super_admin) has god-mode access to everything

**Target markets:** Greece primary, expanding to Italy, Bulgaria, Turkey, China. Content must be translatable per-field, not via duplicated columns.

---

## 2. Tech Stack (Final Decision)

| Layer | Package | Rationale |
|---|---|---|
| Framework | Laravel 12.x | LTS, mature, single-language codebase |
| PHP | 8.3+ | Readonly classes, typed class constants, `json_validate()` |
| Admin/Dashboard Panels | Filament v5.x | Released Jan 2026, Livewire v4 support [web:55] |
| Interactivity | Livewire 4.x | Server-driven UI, zero custom JS for most interactions [web:55] |
| Roles & Permissions | spatie/laravel-permission v7 | Laravel 12 compatible, role-permission matrix [web:52] |
| Translatable content | spatie/laravel-translatable v6 | JSON column per field, Filament locale tabs |
| Search | Laravel Scout + database driver | Starts free on MySQL; swap to Meilisearch/Algolia later without code changes |
| Notifications | Laravel Notifications (database + mail) | No external push service needed; works on shared hosting |
| File storage | Laravel Filesystem (local disk) | cPanel local; swap to S3 later via env change |
| Auth scaffolding | Laravel Breeze (Blade + Livewire) | Minimal, clean, no Inertia overhead |
| Frontend CSS | Tailwind CSS v4 | Ships with Filament; compiled via Vite in CI |
| Icons | Blade UI Icons (Heroicons set) | Bundled with Filament |
| DB | MySQL 8.0 / MariaDB 10.6+ | cPanel native |
| Optional queue | Laravel Queue (database driver) | For async email sending; no Redis needed |
| Local web server | Caddy v2 | Automatic HTTPS, simple Caddyfile config |
| Production web server | Apache (cPanel) | No SSH access; FTP-only deployment |

**Explicitly rejected:**
- SSH-based deployment — target cPanel has no SSH access; FTP-only via GitHub Actions
- October CMS — adds Twig templating layer (user dislikes latency feel), CMS abstraction mismatch for app-first product
- Next.js / any JS framework — user lacks confidence debugging the stack; switching cost outweighs benefits
- Redis — not available on target cPanel tier; database driver suffices
- WebSockets server — not available on shared hosting; use Livewire polling + database notifications
- `php artisan serve` for local dev — Caddy provides proper HTTPS, HTTP/2, and matches production-like behavior

---

## 3. Role & Permission Architecture

### 3.1 Roles

| Role | Who | Description |
|---|---|---|
| `super_admin` | Platform owner (developer) | God mode: settings, roles, source code, everything |
| `admin` | Client who purchased the platform | Full operational control: moderation, users, messaging oversight, featuring, subscriptions |
| `premium_member` | Paid subscriber | Everything a member has + visibility perks, higher quotas, verified badge eligibility |
| `member` | Registered company user | Manage own companies, listings, messaging |

### 3.2 Permission Matrix

| Permission slug | super_admin | admin | premium_member | member |
|---|---|---|---|---|
| `access_admin_panel` | ✅ | ✅ | ❌ | ❌ |
| `access_member_panel` | ✅ | ✅ | ✅ | ✅ |
| `manage_platform_settings` | ✅ | ❌ | ❌ | ❌ |
| `manage_roles` | ✅ | ❌ | ❌ | ❌ |
| `moderate_companies` | ✅ | ✅ | ❌ | ❌ |
| `moderate_listings` | ✅ | ✅ | ❌ | ❌ |
| `moderate_users` (block/unblock) | ✅ | ✅ | ❌ | ❌ |
| `feature_companies` | ✅ | ✅ | ❌ | ❌ |
| `feature_listings` | ✅ | ✅ | ❌ | ❌ |
| `manage_subscriptions` | ✅ | ✅ | ❌ | ❌ |
| `view_all_threads` | ✅ | ✅ | ❌ | ❌ |
| `reply_any_thread` | ✅ | ✅ | ❌ | ❌ |
| `verify_companies` | ✅ | ✅ | ❌ | ❌ |
| `create_company` | ✅ | ✅ | ✅ | ✅ |
| `create_listing` | ✅ | ✅ | ✅ | ✅ |
| `edit_own_company` | ✅ | ✅ | ✅ | ✅ |
| `edit_own_listing` | ✅ | ✅ | ✅ | ✅ |
| `delete_own_company` | ✅ | ✅ | ✅ | ✅ |
| `delete_own_listing` | ✅ | ✅ | ✅ | ✅ |
| `message_members` | ✅ | ✅ | ✅ | ✅ |
| `message_admins` | ✅ | ✅ | ✅ | ✅ |
| `result_boost` | ✅ | ✅ | ✅ | ❌ |
| `homepage_feature` | ✅ | ✅ | ✅ | ❌ |
| `verified_badge` | ✅ | ✅ | ✅ (if verified) | ❌ |
| `export_data` | ✅ | ✅ | ❌ | ❌ |

### 3.3 Quotas (configurable via platform settings)

| Resource | member (free) | premium_member |
|---|---|---|
| Max companies | 3 | 10 |
| Max active listings | 5 | 25 |
| Max photos per company | 3 | 12 |
| Max photos per listing | 1 | 5 |
| Message attachments | ❌ | ✅ (5MB max) |

### 3.4 Subscription Handling

Subscription state lives on the `users` table, **not** inferred from role. A premium_member whose subscription lapses keeps the role but loses perks — business logic checks `subscription_expires_at`.

```php
public function canBoostResults(User $user): bool
{
    return $user->hasRole('premium_member')
        && $user->subscription_expires_at?->isFuture();
}
```

Payments integration is **out of scope** for v1. Admin manually assigns premium tier via Filament. Architecture is ready for Stripe later.

---

## 4. Database Schema

All tables: InnoDB, `utf8mb4_unicode_ci`. bigIncrements for simplicity and performance on shared hosting.

### 4.1 `users`

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| name | VARCHAR(255) | Full name of account holder |
| email | VARCHAR(255) UNIQUE | |
| email_verified_at | TIMESTAMP NULL | |
| password | VARCHAR(255) | Bcrypt |
| phone | VARCHAR(30) NULL | |
| avatar_path | VARCHAR(255) NULL | |
| subscription_tier | ENUM('free','premium') | Default: 'free' |
| subscription_expires_at | TIMESTAMP NULL | NULL = no expiry / manual |
| preferred_locale | VARCHAR(5) | Default: 'en' |
| notification_preferences | JSON NULL | e.g. {"email_new_messages": true} |
| is_blocked | BOOLEAN | Default: false |
| blocked_reason | TEXT NULL | Admin notes |
| last_login_at | TIMESTAMP NULL | |
| remember_token | VARCHAR(100) | |
| timestamps | | |
| soft deletes | deleted_at | |

### 4.2 `companies`

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| user_id | BIGINT FK → users | Primary owner |
| category | ENUM('factory','business','transport','personnel') | |
| company_name | VARCHAR(255) | |
| slug | VARCHAR(255) UNIQUE | URL-safe, auto-generated |
| email | VARCHAR(255) | Public contact email |
| afm | VARCHAR(50) NULL | Tax ID (ΑΦΜ) |
| phone | VARCHAR(30) | |
| website | VARCHAR(255) NULL | |
| logo_path | VARCHAR(255) NULL | |
| description | JSON | Translatable via spatie/laravel-translatable |
| country | VARCHAR(2) | ISO 3166-1 alpha-2 code |
| city | VARCHAR(100) | |
| address | VARCHAR(255) NULL | |
| postal_code | VARCHAR(20) NULL | |
| latitude | DECIMAL(10,7) NULL | For future map view |
| longitude | DECIMAL(10,7) NULL | |
| moderation_status | ENUM('draft','pending_review','approved','rejected','suspended') | Default: 'draft' |
| moderation_notes | TEXT NULL | Admin feedback to user |
| moderated_by | BIGINT FK → users NULL | |
| moderated_at | TIMESTAMP NULL | |
| is_verified | BOOLEAN | Default: false |
| verified_at | TIMESTAMP NULL | |
| is_featured | BOOLEAN | Default: false |
| featured_until | TIMESTAMP NULL | Time-limited featuring |
| sort_priority | INTEGER | Default: 0 |
| view_count | INTEGER | Default: 0 |
| timestamps | | |
| soft deletes | deleted_at | |

**Indexes:** `(user_id)`, `(category)`, `(moderation_status)`, `(country)`, `(is_featured)`, `(is_verified)`, `(slug)`, FULLTEXT `(company_name)`

### 4.3 `company_members`

Multi-user per company. Owner registers, then invites employees.

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| company_id | BIGINT FK → companies | |
| user_id | BIGINT FK → users | |
| role | ENUM('owner','manager','editor') | Owner = creator; manager = full edit; editor = listings only |
| invited_at | TIMESTAMP | |
| accepted_at | TIMESTAMP NULL | NULL = pending invitation |
| timestamps | | |

**Unique:** `(company_id, user_id)`

### 4.4 `transport_details`

One-to-one with companies where `category = 'transport'`.

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| company_id | BIGINT FK → companies UNIQUE | |
| countries_served | JSON | Array of ISO country codes |
| vehicle_types | JSON | Array: truck, van, reefer, flatbed, tanker, container |
| has_adr | BOOLEAN | Hazardous goods certification |
| has_refrigerated | BOOLEAN | |
| min_capacity_kg | INTEGER NULL | |
| max_capacity_kg | INTEGER NULL | |
| insurance_coverage | VARCHAR(255) NULL | |
| timestamps | | |

### 4.5 `listings`

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| user_id | BIGINT FK → users | Creator |
| company_id | BIGINT FK → companies NULL | Optional association |
| type | ENUM('job_offer','job_seeking','service') | |
| title | JSON | Translatable |
| description | JSON | Translatable |
| category_id | BIGINT FK → categories NULL | |
| country | VARCHAR(2) | ISO code |
| city | VARCHAR(100) NULL | |
| contact_email | VARCHAR(255) | |
| contact_phone | VARCHAR(30) NULL | |
| salary_range | VARCHAR(100) NULL | For job listings |
| is_remote | BOOLEAN | Default: false |
| moderation_status | ENUM('draft','pending_review','approved','rejected','suspended') | Default: 'draft' |
| moderation_notes | TEXT NULL | |
| moderated_by | BIGINT FK → users NULL | |
| moderated_at | TIMESTAMP NULL | |
| is_featured | BOOLEAN | Default: false |
| sort_priority | INTEGER | Default: 0 |
| expires_at | TIMESTAMP NULL | Configurable duration (1, 3, or max 6 months) |
| view_count | INTEGER | Default: 0 |
| timestamps | | |
| soft deletes | deleted_at | |

**Indexes:** `(user_id)`, `(company_id)`, `(type)`, `(moderation_status)`, `(country)`, `(expires_at)`, `(is_featured)`, FULLTEXT `(title)`

### 4.6 `categories`

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| name | JSON | Translatable |
| slug | VARCHAR(255) UNIQUE | |
| parent_id | BIGINT FK → categories NULL | Self-referencing |
| sort_order | INTEGER | Default: 0 |
| is_active | BOOLEAN | Default: true |
| timestamps | | |

### 4.7 `company_photos`

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| company_id | BIGINT FK → companies | |
| path | VARCHAR(255) | |
| caption | JSON NULL | Translatable caption |
| sort_order | INTEGER | Default: 0 |
| timestamps | | |

### 4.8 `message_threads`

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| subject | VARCHAR(255) | |
| thread_type | ENUM('direct','admin_support') | direct = member-to-member; admin_support = member-to-admin |
| created_by | BIGINT FK → users | Initiator |
| is_locked | BOOLEAN | Default: false |
| timestamps | |
| soft deletes | deleted_at | |

### 4.9 `thread_participants`

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| thread_id | BIGINT FK → message_threads | |
| user_id | BIGINT FK → users | |
| last_read_at | TIMESTAMP NULL | For unread badge |
| is_archived | BOOLEAN | Default: false |
| timestamps | | |

**Unique:** `(thread_id, user_id)`

### 4.10 `messages`

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| thread_id | BIGINT FK → message_threads | |
| user_id | BIGINT FK → users | Sender |
| body | TEXT | Plain text, sanitized |
| attachments | JSON NULL | Array of file paths (premium only) |
| timestamps | |
| soft deletes | deleted_at | Shows as "message deleted" |

**Indexes:** `(thread_id)`, `(user_id)`, `(created_at)`

### 4.11 `moderation_logs`

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| moderatable_type | VARCHAR(255) | Morph map: company, listing, user |
| moderatable_id | BIGINT | |
| action | ENUM('submitted','approved','rejected','suspended','unsuspended','featured','unfeatured','verified','unverified','blocked','unblocked') | |
| moderator_id | BIGINT FK → users | |
| previous_status | VARCHAR(50) NULL | |
| new_status | VARCHAR(50) NULL | |
| notes | TEXT NULL | |
| timestamps | | |

### 4.12 `settings`

Key-value store for configurable platform options.

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| key | VARCHAR(255) UNIQUE | e.g. member_company_quota |
| value | TEXT | Serialized value |
| type | ENUM('integer','boolean','string','json') | For form rendering |
| group | VARCHAR(50) | e.g. quotas, features, seo |
| timestamps | | |

### 4.13 `inquiries`

Buyer-to-company contact (lead generation, separate from messaging).

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| company_id | BIGINT FK → companies | |
| listing_id | BIGINT FK → listings NULL | |
| from_user_id | BIGINT FK → users NULL | NULL = guest inquiry |
| from_name | VARCHAR(255) | |
| from_email | VARCHAR(255) | |
| from_phone | VARCHAR(30) NULL | |
| subject | VARCHAR(255) | |
| body | TEXT | |
| status | ENUM('new','read','replied','archived') | Default: 'new' |
| replied_at | TIMESTAMP NULL | |
| timestamps | | |

### 4.14 `saved_searches`

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| user_id | BIGINT FK → users | |
| name | VARCHAR(255) | User-defined label |
| filters | JSON | Serialized filter criteria |
| entity_type | ENUM('company','listing') | |
| notify_new_matches | BOOLEAN | Default: false |
| last_notified_at | TIMESTAMP NULL | |
| timestamps | | |

---

## 5. Messaging System — Detailed Design

### 5.1 Thread Types

| Type | Trigger | Participants |
|---|---|---|
| `direct` | Member clicks "Message" on another member's profile | The two members |
| `admin_support` | Member contacts support or admin initiates | Member + one or more admins |

### 5.2 Access Rules

| Action | super_admin / admin | member / premium_member |
|---|---|---|
| View all threads in system | ✅ | ❌ — only own participant threads |
| Start direct thread with member | ✅ | ✅ |
| Start admin_support thread | ✅ (as admin) | ✅ (contacts admin team) |
| Reply in any thread | ✅ — even if not original participant | ❌ — only own threads |
| Lock thread | ✅ | ❌ |
| Delete thread | ✅ | ❌ — can only archive |
| Delete own message | ✅ | ✅ (soft delete → "Message deleted") |
| Attach files | ✅ | ✅ (premium_member only) |

### 5.3 Admin Oversight

Admin panel "Message Threads" resource shows every thread:
- Filterable by type, participant, date range, keyword search in body
- Admin can open, read full history, reply with "Admin" badge
- Admin can lock threads for moderation
- Export thread content as text/CSV

### 5.4 Member Messaging UI

Livewire components inside member dashboard:
- **Inbox list** (left): threads sorted by last message, unread badge, archive
- **Thread view** (right): message history, reply input, attachment upload (premium)
- **Compose modal**: select recipient (member or "Support"), subject, body
- **Ephemeral Attachments**: Image/document exchange allowed. Prominent UI warning: *"Attachments are automatically deleted after 7 days for privacy and storage limits."* A daily cron job deletes these files.
- **Email Toggle**: Users can toggle "Email me when I receive a new message" in their settings.
- **Global Unread Pill**: A persistent number pill in the main site header tracking unread messages, updated dynamically via Livewire across the whole site.
- **Polling**: inbox page polls every 15 seconds (no WebSockets needed)
- **Notifications**: new message triggers Laravel database notification

### 5.5 Inquiry System (separate from messaging)

When a buyer visits a company's public profile:
- "Contact this company" form (name, email, phone, subject, message)
- Creates an `inquiry` record in the company owner's dashboard
- Company owner can reply via email or convert to a direct message thread
- Admins can see all inquiries in the admin panel
- This is a **lead generation channel**, not member-to-member messaging

---

## 6. Moderation Workflow

### 6.1 Lifecycle

```
[User creates/edits company or listing]
         │
         ▼
      [draft] ← user edits freely, not publicly visible
         │
   user clicks "Submit for Review"
         │
         ▼
 [pending_review] ← appears in admin moderation queue
         │
    ┌────┴─────┐
    ▼          ▼
[approved]  [rejected] ← admin provides notes
    │           │
    │      user edits & resubmits → [pending_review]
    │
    ▼
[publicly visible]
    │
  admin can suspend at any time
    │
    ▼
[suspended] ← user sees reason, can edit & resubmit
```

### 6.2 Re-moderation on Edit

When a user edits an already-approved record:
- **Trivial fields** (description text, phone, website) → stays approved. Configurable via platform settings
- **Sensitive fields** (company_name, afm, category, country) → re-enters `pending_review`
- Previous approved version remains publicly visible until new edit is moderated

### 6.3 Moderation Queue UI

Admin panel "Moderation Queue" page:
- Tabbed view: Companies | Listings | Users (blocked)
- Bulk actions: Approve selected, Reject selected (with note template), Feature selected
- Inline preview: click a row to see full record without leaving the queue
- Filter by category, country, date range, submitter

### 6.4 Audit Trail

Every moderation action writes to `moderation_logs` with record type, ID, action, previous → new status, moderator ID, notes, timestamp.

---

## 7. Search & Ranking

### 7.1 Catalog Search

Uses Laravel Scout with the database driver (MySQL FULLTEXT). When platform grows, swap to Meilisearch/Algolia by changing Scout driver in `.env`.

### 7.2 Result Ordering

```sql
ORDER BY
  sort_priority DESC,    -- featured > premium > regular
  is_verified DESC,       -- verified companies rank higher
  view_count DESC,        -- popularity signal
  created_at DESC         -- recency tiebreaker
```

**sort_priority values:**

| Condition | Priority |
|---|---|
| Admin-featured + premium member | 300 |
| Admin-featured only | 200 |
| Premium member (active subscription) | 100 |
| Regular member | 0 |

### 7.3 Filters

**Company catalog:** Category, Country, Transport capabilities (ADR, refrigerated, vehicle types), Verified only, Search by name/description

**Transport catalog (subset):** Countries served, Vehicle types, ADR certified, Refrigerated, Capacity range

**Listings board:** Type, Category, Country, Remote only (for jobs), Search by title/description

---

## 8. Internationalization

### 8.1 Strategy

| Content type | Mechanism |
|---|---|
| UI strings | Laravel `lang/` JSON files |
| User-generated content | `spatie/laravel-translatable` — JSON column per field |
| URL locale | `/{locale}/` route prefix; default `en` has no prefix |
| User preference | `users.preferred_locale` column |
| Filament forms | `filament/spatie-laravel-translatable-plugin` — locale toggle tabs |

### 8.2 Supported Locales

| Code | Language | Status |
|---|---|---|
| `en` | English | Primary — all content mandatory |
| `el` | Greek | Full translation |
| `it` | Italian | Full translation |
| `bg` | Bulgarian | Placeholder |
| `tr` | Turkish | Placeholder |
| `zh` | Chinese | Placeholder |

### 8.3 Locale Switching (Fixing the Original Bug)

The Next.js stack had a bug where switching language updated page content but not the navigation menu. This happened because locale resolution was split between next-intl middleware and component-level logic.

**Laravel fix:** Locale is resolved **once** in the `SetLocale` route middleware, stored in session, and available globally. All Blade views and Livewire components read from the same resolved locale.

```php
// app/Http/Middleware/SetLocale.php
public function handle(Request $request, Closure $next): Response
{
    $locale = $request->route('locale')
        ?? session('locale')
        ?? config('app.locale');

    if (!in_array($locale, config('app.available_locales'))) {
        $locale = config('app.locale');
    }

    App::setLocale($locale);
    session(['locale' => $locale]);

    return $next($request);
}
```

Locale switcher is a Livewire component that updates session and redirects to the equivalent route in the new locale. Single source of truth, no split-brain state.

---

## 9. Filament Panel Architecture

### 9.1 Admin Panel (`/admin`)

**Access:** `super_admin`, `admin`

**Resources:**

| Resource | Key Features |
|---|---|
| UserResource | View all users, block/unblock, assign roles, manage subscriptions, impersonate |
| CompanyResource | View all, moderate (approve/reject/suspend), feature, verify, edit any |
| ListingResource | View all, moderate, feature, edit any |
| CategoryResource | Full CRUD, nested hierarchy, translatable names |
| MessageThreadResource | View all threads, read, reply, lock, export |
| InquiryResource | View all inquiries, filter by company/status, reply |
| ModerationLogResource | View audit trail, filter by action/moderator/date |
| SettingsResource (page) | Platform settings: quotas, moderation rules, contact info |

**Dashboard widgets:** Pending moderation count, new registrations (7 days), active listings, premium subscribers, recent inquiries, message thread activity.

**Features:** Bulk approve/reject with note templates, user impersonation (super_admin only), Filament notifications for real-time admin alerts.

### 9.2 Member Dashboard (`/dashboard`)

**Access:** `member`, `premium_member` (also `admin`, `super_admin`)

**Resources (scoped to own records via Eloquent query scoping):**

| Resource | Scope | Features |
|---|---|---|
| MyCompanies | `where('user_id', auth()->id())` or via company_members | Create, edit, submit for review, view status, manage members, view inquiries |
| MyListings | `where('user_id', auth()->id())` | Create, edit, submit for review, view status |
| Messages | Participant-based scoping | Inbox, thread view, compose, reply, archive |
| SavedSearches | `where('user_id', auth()->id())` | Create, view, delete, toggle notifications |

---

## 10. Public Site Routes

| Route | Method | Description |
|---|---|---|
| `/{locale?}` | GET | Homepage: featured companies, categories, search bar |
| `/{locale}/catalog` | GET | Full B2B company directory with Livewire filters |
| `/{locale}/catalog/{slug}` | GET | Public company profile page |
| `/{locale}/transport` | GET | Transport-only filtered catalog |
| `/{locale}/listings` | GET | Job/service listings board with filters |
| `/{locale}/listings/{id}` | GET | Single listing detail page |
| `/{locale}/categories/{slug}` | GET | Companies/listings by category |
| `/{locale}/login` | GET/POST | Login form |
| `/{locale}/register` | GET/POST | Registration form (category selection) |
| `/{locale}/company/{slug}/contact` | POST | Submit inquiry to company |
| `/admin` | — | Filament admin panel |
| `/dashboard` | — | Filament member panel |
| `/sitemap.xml` | GET | Dynamic sitemap |
| `/robots.txt` | GET | Robots config |

---

## 11. SEO Strategy

### 11.1 Structured Data

Each company profile page outputs JSON-LD `Organization` schema. Each listing page outputs `JobPosting` or `Service` schema as appropriate.

### 11.2 Meta Tags

Dynamic `<title>`, `<meta description>`, OpenGraph tags per page. Canonical URLs with locale prefix. `hreflang` tags for all supported locales.

### 11.3 Sitemap

Laravel sitemap generating XML from approved companies and listings. Regenerated on cron daily or on moderation approval.

---

## 12. Notifications

Laravel notification system with database + mail channels. Queued via database driver (no Redis needed).

| Event | Recipients | Channels |
|---|---|---|
| Company submitted for review | Admins | Database + Email |
| Company approved/rejected | Company owner | Database + Email |
| Listing approved/rejected | Listing owner | Database + Email |
| New message in thread | Thread participants | Database + Email |
| New inquiry received | Company owner | Database + Email |
| Subscription expiring soon | Premium member | Database + Email |
| Account blocked | User | Email |
| New saved search match | Search owner | Database + Email (if enabled) |

---

## 13. Project Structure

```
yfindir/
├── app/
│   ├── Models/
│   │   ├── User.php
│   │   ├── Company.php
│   │   ├── CompanyMember.php
│   │   ├── TransportDetail.php
│   │   ├── Listing.php
│   │   ├── Category.php
│   │   ├── CompanyPhoto.php
│   │   ├── MessageThread.php
│   │   ├── ThreadParticipant.php
│   │   ├── Message.php
│   │   ├── ModerationLog.php
│   │   ├── Inquiry.php
│   │   ├── SavedSearch.php
│   │   └── Setting.php
│   ├── Filament/
│   │   ├── Admin/
│   │   │   ├── Resources/
│   │   │   │   ├── UserResource.php
│   │   │   │   ├── CompanyResource.php
│   │   │   │   ├── ListingResource.php
│   │   │   │   ├── CategoryResource.php
│   │   │   │   ├── MessageThreadResource.php
│   │   │   │   ├── InquiryResource.php
│   │   │   │   └── ModerationLogResource.php
│   │   │   ├── Pages/
│   │   │   │   └── ManageSettings.php
│   │   │   └── Widgets/
│   │   └── Member/
│   │       ├── Resources/
│   │       │   ├── CompanyResource.php
│   │       │   ├── ListingResource.php
│   │       │   ├── MessageResource.php
│   │       │   ├── InquiryResource.php
│   │       │   └── SavedSearchResource.php
│   │       └── Pages/
│   │           └── Dashboard.php
│   ├── Livewire/
│   │   ├── CatalogSearch.php
│   │   ├── TransportSearch.php
│   │   ├── ListingsBoard.php
│   │   ├── LocaleSwitcher.php
│   │   ├── FeaturedCompanies.php
│   │   ├── CompanyContactForm.php
│   │   └── Messaging/
│   │       ├── Inbox.php
│   │       ├── ThreadView.php
│   │       └── ComposeMessage.php
│   ├── Policies/
│   │   ├── CompanyPolicy.php
│   │   ├── ListingPolicy.php
│   │   ├── MessageThreadPolicy.php
│   │   ├── InquiryPolicy.php
│   │   └── UserPolicy.php
│   ├── Services/
│   │   ├── ModerationService.php
│   │   ├── SearchRankingService.php
│   │   ├── QuotaService.php
│   │   ├── MessagingService.php
│   │   └── SubscriptionService.php
│   ├── Notifications/
│   │   ├── CompanySubmittedForReview.php
│   │   ├── CompanyApproved.php
│   │   ├── CompanyRejected.php
│   │   ├── NewMessage.php
│   │   ├── NewInquiry.php
│   │   ├── SubscriptionExpiring.php
│   │   └── AccountBlocked.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── CatalogController.php
│   │   │   ├── CompanyProfileController.php
│   │   │   ├── ListingController.php
│   │   │   └── SitemapController.php
│   │   └── Middleware/
│   │       ├── SetLocale.php
│   │       └── VerifyDeployToken.php
│   └── Providers/
│       ├── Filament/
│       │   ├── AdminPanelProvider.php
│       │   └── MemberPanelProvider.php
│       └── AppServiceProvider.php
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 0001_01_01_000001_create_companies_table.php
│   │   ├── 0001_01_01_000002_create_company_members_table.php
│   │   ├── 0001_01_01_000003_create_transport_details_table.php
│   │   ├── 0001_01_01_000004_create_listings_table.php
│   │   ├── 0001_01_01_000005_create_categories_table.php
│   │   ├── 0001_01_01_000006_create_company_photos_table.php
│   │   ├── 0001_01_01_000007_create_message_threads_table.php
│   │   ├── 0001_01_01_000008_create_thread_participants_table.php
│   │   ├── 0001_01_01_000009_create_messages_table.php
│   │   ├── 0001_01_01_000010_create_moderation_logs_table.php
│   │   ├── 0001_01_01_000011_create_inquiries_table.php
│   │   ├── 0001_01_01_000012_create_saved_searches_table.php
│   │   ├── 0001_01_01_000013_create_settings_table.php
│   │   ├── 0001_01_01_000014_create_permission_tables.php
│   │   └── 0001_01_01_000015_create_notifications_table.php
│   ├── seeders/
│   │   ├── DatabaseSeeder.php
│   │   ├── RolePermissionSeeder.php
│   │   ├── CategorySeeder.php
│   │   ├── SettingsSeeder.php
│   │   └── DemoDataSeeder.php
│   └── factories/
├── lang/
│   ├── en.json
│   ├── el.json
│   ├── it.json
│   ├── bg.json
│   ├── tr.json
│   └── zh.json
├── resources/
│   └── views/
│       ├── components/
│       │   ├── layouts/
│       │   │   ├── public.blade.php
│       │   │   └── app.blade.php
│       │   ├── nav.blade.php
│       │   ├── footer.blade.php
│       │   ├── company-card.blade.php
│       │   ├── listing-card.blade.php
│       │   ├── locale-switcher.blade.php
│       │   ├── premium-badge.blade.php
│       │   └── verified-badge.blade.php
│       └── pages/
│           ├── home.blade.php
│           ├── catalog.blade.php
│           ├── company-profile.blade.php
│           ├── transport.blade.php
│           ├── listings.blade.php
│           ├── listing-detail.blade.php
│           ├── login.blade.php
│           └── register.blade.php
├── routes/
│   ├── web.php
│   └── channels.php
├── Caddyfile
├── .github/workflows/deploy.yml
├── .env.example
├── composer.json
├── package.json
└── SPEC.md
```

---


## 13.5 Preemptive UX & Roadblock Mitigation

To prevent common user friction and support requests, the platform implements these preemptive measures:

### A. Media Management & Storage Limits
- **Logo Fallback**: If a company uploads no logo, the UI automatically generates an SVG avatar with the company's initials (e.g., "YF" for Yfantis) on a deterministic colored background.
- **Auto-Compression**: Users often upload 10MB photos directly from phones. The backend uses Laravel's `Intervention Image` to automatically resize all uploads to max 1920x1080 and convert to `WebP` upon upload, avoiding cPanel disk quota exhaustion.
- **Ephemeral Message Attachments**: To prevent the database/storage from bloating with chat images, message attachments are wiped after 7 days via a scheduled command (`php artisan messages:prune-attachments`).

### B. Form Fatigue & Data Loss
- **Auto-Save Drafts**: Filament form states are preserved in the session/Livewire. If a user accidentally refreshes or their session expires while typing a long description, their progress is recovered.
- **Translation Fallbacks**: If a user only fills in the Greek description, the English view will gracefully fall back to displaying the Greek text rather than an empty layout, with a small "Translated" prompt or indicator.
- **Listing Expiry Controls**: Users choose an "Active Until" duration (1, 3, or maximum 6 months) when creating a listing. 7 days before expiry, they receive a reminder email with a 1-click "Renew for 6 months" link.

### C. Data Quality
- **Greek AFM Validation**: Strict validation for the Greek Tax ID (`afm` field) using the modulo 11 checksum algorithm, preventing typos in B2B profiles.
- **Rich Text Sanitization**: WYSIWYG editors strip all harmful HTML and inline styling (copy-pasted from Word), ensuring consistent typography across the site.


## 13.5 Preemptive UX & Roadblock Mitigation

To prevent common user friction and support requests, the platform implements these preemptive measures:

### A. Media Management & Storage Limits
- **Logo Fallback**: If a company uploads no logo, the UI automatically generates an SVG avatar with the company's initials (e.g., "YF" for Yfantis) on a deterministic colored background.
- **Auto-Compression**: Users often upload 10MB photos directly from phones. The backend uses Laravel's `Intervention Image` to automatically resize all uploads to max 1920x1080 and convert to `WebP` upon upload, avoiding cPanel disk quota exhaustion.
- **Ephemeral Message Attachments**: To prevent the database/storage from bloating with chat images, message attachments are wiped after 7 days via a scheduled command (`php artisan messages:prune-attachments`).

### B. Form Fatigue & Data Loss
- **Auto-Save Drafts**: Filament form states are preserved in the session/Livewire. If a user accidentally refreshes or their session expires while typing a long description, their progress is recovered.
- **Translation Fallbacks**: If a user only fills in the Greek description, the English view will gracefully fall back to displaying the Greek text rather than an empty layout, with a small "Translated" prompt or indicator.
- **Listing Expiry Controls**: Users choose an "Active Until" duration (1, 3, or maximum 6 months) when creating a listing. 7 days before expiry, they receive a reminder email with a 1-click "Renew for 6 months" link.

### C. Data Quality
- **Greek AFM Validation**: Strict validation for the Greek Tax ID (`afm` field) using the modulo 11 checksum algorithm, preventing typos in B2B profiles.
- **Rich Text Sanitization**: WYSIWYG editors strip all harmful HTML and inline styling (copy-pasted from Word), ensuring consistent typography across the site.

## 14. Service Classes (Business Logic)

### 14.1 ModerationService
```php
class ModerationService
{
    public function submitForReview(Company|Listing $record): void
    public function approve(Company|Listing $record, User $moderator, ?string $notes = null): void
    public function reject(Company|Listing $record, User $moderator, string $notes): void
    public function suspend(Company|Listing $record, User $moderator, string $reason): void
    public function unsuspend(Company|Listing $record, User $moderator): void
    public function feature(Company|Listing $record, ?Carbon $until = null): void
    public function unfeature(Company|Listing $record): void
    public function verify(Company $company, User $moderator): void
}
```

### 14.2 SearchRankingService
```php
class SearchRankingService
{
    public function getSortPriority(Company|Listing $record): int
    public function applyRanking(Builder $query): Builder
    public function isEligibleForBoost(User $user): bool
}
```

### 14.3 QuotaService
```php
class QuotaService
{
    public function canCreateCompany(User $user): bool
    public function canCreateListing(User $user): bool
    public function getCompanyQuota(User $user): int
    public function getListingQuota(User $user): int
    public function getRemainingCompanySlots(User $user): int
    public function getRemainingListingSlots(User $user): int
}
```

### 14.4 MessagingService
```php
class MessagingService
{
    public function createThread(User $creator, User $recipient, string $subject, string $body, string $type = 'direct'): MessageThread
    public function reply(MessageThread $thread, User $sender, string $body, ?array $attachments = null): Message
    public function markAsRead(MessageThread $thread, User $user): void
    public function getUnreadCount(User $user): int
    public function canAccessThread(User $user, MessageThread $thread): bool
    public function lockThread(MessageThread $thread, User $admin): void
}
```

---

## 15. Development & Deployment Architecture

### 15.1 Local Development — Caddy v2

Caddy serves the Laravel app locally with automatic HTTPS, HTTP/2, and clean config. This replaces `php artisan serve` and gives a production-like environment.

**Caddyfile:**
```caddyfile
yfindir.test {
    root * /home/{user}/Projects/yfindir/public
    php_fastcgi 127.0.0.1:9000  # or unix socket depending on PHP-FPM config
    file_server
    encode gzip zstd
    header {
        Cache-Control "no-cache"
    }
}
```

**Local dev workflow:**
- PHP-FPM runs locally (via mise or system PHP)
- Caddy reverse-proxies PHP requests to PHP-FPM
- Vite dev server (`npm run dev`) for hot-reloading Tailwind/Blade changes
- MySQL/MariaDB running locally or via Docker
- `.env` points to local DB

### 15.2 CI/CD Pipeline — GitHub Actions (The "Vercel Replacement")

Since the cPanel target has **no SSH access**, GitHub Actions becomes the build server. It compiles everything, strips dev dependencies, and deploys via FTP — mirroring a Vercel-style "push to deploy" workflow [web:65][web:67].

**Build steps that happen in CI (never on the server):**
1. `composer install --no-dev --optimize-autoloader` (strips dev packages)
2. `npm ci && npm run build` (compiles Tailwind/Vite assets)
3. Remove all non-production files (tests, node_modules, .git, raw assets)
4. FTP upload the clean artifact to cPanel
5. Trigger post-deploy Artisan commands via HTTP endpoint (since no SSH)

### 15.3 GitHub Actions Workflow

```yaml
name: Deploy to cPanel

on:
  push:
    branches: [main]

env:
  PHP_VERSION: '8.3'
  NODE_VERSION: '20'

jobs:
  build-and-deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ env.PHP_VERSION }}
          extensions: mbstring, mysql, pdo, bcmath, xml, curl, zip, gd
          coverage: none

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: ${{ env.NODE_VERSION }}

      - name: Install Composer dependencies (production only)
        run: composer install --no-dev --optimize-autoloader --no-interaction

      - name: Install npm dependencies
        run: npm ci

      - name: Build frontend assets
        run: npm run build

      - name: Prepare deployment artifact
        run: |
          mkdir -p deploy
          rsync -av \
            --exclude='.git' \
            --exclude='node_modules' \
            --exclude='.env' \
            --exclude='tests' \
            --exclude='phpunit.xml' \
            --exclude='.github' \
            --exclude='resources/css' \
            --exclude='resources/js' \
            --exclude='package.json' \
            --exclude='package-lock.json' \
            --exclude='README.md' \
            --exclude='SPEC.md' \
            ./ deploy/

      - name: Deploy via FTP
        uses: SamKirkland/FTP-Deploy-Action@v4.3.4
        with:
          server: ${{ secrets.FTP_HOST }}
          username: ${{ secrets.FTP_USERNAME }}
          password: ${{ secrets.FTP_PASSWORD }}
          local-dir: ./deploy/
          server-dir: ${{ secrets.FTP_TARGET_DIR }}
          exclude: 'storage/**'
          dangerous-clean-slate: false

      - name: Run post-deploy commands via HTTP
        run: |
          DEPLOY_TOKEN=${{ secrets.DEPLOY_TOKEN }}
          DEPLOY_URL=${{ secrets.DEPLOY_URL }}

          echo "Running migrations..."
          curl -s -X POST "${DEPLOY_URL}/deploy/migrate" \
            -H "X-Deploy-Token: ${DEPLOY_TOKEN}" \
            --max-time 120

          echo "Clearing and caching config..."
          curl -s -X POST "${DEPLOY_URL}/deploy/optimize" \
            -H "X-Deploy-Token: ${DEPLOY_TOKEN}" \
            --max-time 120

          echo "Running storage:link..."
          curl -s -X POST "${DEPLOY_URL}/deploy/storage-link" \
            -H "X-Deploy-Token: ${DEPLOY_TOKEN}" \
            --max-time 60

          echo "Deployment complete."
```

### 15.4 Post-Deploy HTTP Endpoint (No SSH Artisan Runner)

Since cPanel has no SSH, we use a **secure HTTP endpoint** to run Artisan commands. This is a common pattern for shared hosting Laravel deployments [web:72][web:79].

**Route (in `routes/web.php`):**
```php
Route::prefix('deploy')->middleware('deploy.token')->group(function () {
    Route::post('migrate', function () {
        Artisan::call('migrate', ['--force' => true]);
        return response()->json(['status' => 'ok', 'output' => Artisan::output()]);
    });

    Route::post('optimize', function () {
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');
        Artisan::call('event:cache');
        Artisan::call('filament:optimize');
        return response()->json(['status' => 'ok', 'output' => 'All caches optimized']);
    });

    Route::post('storage-link', function () {
        Artisan::call('storage:link', ['--force' => true]);
        return response()->json(['status' => 'ok', 'output' => Artisan::output()]);
    });

    Route::post('seed', function () {
        Artisan::call('db:seed', ['--force' => true]);
        return response()->json(['status' => 'ok', 'output' => Artisan::output()]);
    });
});
```

**Middleware (in `app/Http/Middleware/VerifyDeployToken.php`):**
```php
class VerifyDeployToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Deploy-Token');

        if (!hash_equals(config('app.deploy_token', ''), $token ?? '')) {
            abort(403, 'Unauthorized deploy action');
        }

        return $next($request);
    }
}
```

**Security measures:**
- Token is a long random string stored in `.env` as `DEPLOY_TOKEN=...`
- Middleware checks `X-Deploy-Token` header against env value using `hash_equals()` (timing-safe)
- Routes only active when `DEPLOY_TOKEN` is set in `.env`
- Set `DEPLOY_TOKEN=` empty in local dev `.env`
- Consider restricting to GitHub Actions IP ranges via additional middleware

### 15.5 cPanel Configuration

| Item | Value |
|---|---|
| PHP version | 8.3+ (via MultiPHP Manager) |
| Document root | Project subfolder (addon domain or subdomain pointing to `public/`) |
| `.env` file | Manually created via cPanel File Manager; never deployed via CI |
| `storage/` directory | Created once; excluded from FTP sync to preserve logs/sessions/uploads |
| `bootstrap/cache/` | Writable (755 or 775) |
| Cron job | `* * * * * cd /home/user/yfindir && php artisan schedule:run >> /dev/null 2>&1` |
| FTP account | Dedicated FTP user scoped to project directory |

### 15.6 cPanel Directory Structure

Since cPanel Apache serves from `public_html` by default:

```
/home/{cpanel_user}/
├── yfindir/                    # Project root (above public_html)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── lang/
│   ├── resources/
│   ├── routes/
│   ├── storage/               # Excluded from FTP sync (preserved)
│   ├── vendor/                # Uploaded via FTP (composer install done in CI)
│   ├── .env                   # Manually created, never overwritten
│   ├── artisan
│   └── composer.json
├── public_html/               # Apache document root
│   └── yfindir/               # → symlink or actual public/ contents
│       ├── index.php          # Modified to point to /home/{user}/yfindir/
│       ├── .htaccess
│       └── build/             # Compiled Vite assets (uploaded via FTP)
```

**`public/index.php` modification (for cPanel):**
```php
<?php
require __DIR__.'/../yfindir/vendor/autoload.php';
$app = require_once __DIR__.'/../yfindir/bootstrap/app.php';
```

**`.htaccess` (in document root):**
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ yfindir/index.php [QSA,L]
</IfModule>
```

### 15.7 FTP Exclusions

Critical: `storage/` must **never** be overwritten during deploy — it contains user uploads, logs, sessions, and cached views.

Files deployed fresh every time:
- `app/`, `bootstrap/`, `config/`, `database/migrations/`, `lang/`, `routes/`, `resources/views/`
- `vendor/` (pre-built in CI with --no-dev)
- `public/build/` (compiled Vite assets)
- `public/index.php`, `public/.htaccess`

Files **never** deployed (managed on server):
- `.env`
- `storage/**`

### 15.8 First-Time Setup Checklist

- [ ] Create addon domain or subdomain in cPanel
- [ ] Set document root to the desired `public/` path
- [ ] Set PHP version to 8.3+ in MultiPHP Manager
- [ ] Create MySQL database + user via cPanel
- [ ] Create dedicated FTP account scoped to project directory
- [ ] Upload project files via FTP (first deploy)
- [ ] Manually create `.env` on server with production credentials
- [ ] Run `php artisan key:generate` via the deploy HTTP endpoint
- [ ] Run `php artisan migrate` via the deploy HTTP endpoint
- [ ] Run `php artisan storage:link` via the deploy HTTP endpoint
- [ ] Run `php artisan db:seed` via the deploy HTTP endpoint (seeders only)
- [ ] Set up cron job for scheduler
- [ ] Set `storage/` and `bootstrap/cache/` permissions to 775
- [ ] Add GitHub Secrets: `FTP_HOST`, `FTP_USERNAME`, `FTP_PASSWORD`, `FTP_TARGET_DIR`, `DEPLOY_TOKEN`, `DEPLOY_URL`
- [ ] Push to `main` branch to trigger first automated deploy

### 15.9 Rollback Strategy

Since FTP doesn't support atomic deploys, rollback is manual:
1. Keep the previous `vendor/` and `public/build/` directories backed up locally
2. If a deploy breaks, re-run the GitHub Action from the previous commit
3. For database rollback: `php artisan migrate:rollback` via the deploy HTTP endpoint

---

## 16. Key Packages

```json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^12.0",
        "filament/filament": "^5.0",
        "livewire/livewire": "^4.0",
        "spatie/laravel-permission": "^7.0",
        "spatie/laravel-translatable": "^6.0",
        "filament/spatie-laravel-translatable-plugin": "^5.0",
        "laravel/breeze": "^3.0",
        "laravel/scout": "^10.0"
    }
}
```

---

## 17. Environment Variables

```env
APP_NAME="Yfindir"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=yfindir
DB_USERNAME=
DB_PASSWORD=

FILESYSTEM_DISK=local

APP_LOCALE=en
APP_AVAILABLE_LOCALES=en,el,it,bg,tr,zh

SCOUT_DRIVER=database

QUEUE_CONNECTION=database

# Deploy token (for HTTP-triggered Artisan commands)
DEPLOY_TOKEN=

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=465
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="Yfindir"
```

---

## 18. Build Phases

### Phase 1 — Foundation (Week 1)

- [ ] `composer create-project laravel/laravel yfindir`
- [ ] Install Filament, Livewire, Spatie Permission, Spatie Translatable, Scout
- [ ] Configure admin + member Filament panels
- [ ] Create all migrations (14 tables + permission tables)
- [ ] Run RolePermissionSeeder + SettingsSeeder + CategorySeeder
- [ ] Configure `SetLocale` middleware + locale route groups
- [ ] Set up Breeze auth scaffolding
- [ ] Create base layout, nav, footer Blade components
- [ ] Set up Caddy local dev environment with Caddyfile + PHP-FPM
- [ ] Set up GitHub Actions deploy workflow + deploy HTTP endpoints

### Phase 2 — Core Models & Admin Panel (Week 2)

- [ ] Company model + relationships + translatable fields
- [ ] CompanyMember model (multi-user per company)
- [ ] TransportDetail model + Filament relation manager
- [ ] Listing model + relationships
- [ ] Category model (nested hierarchy)
- [ ] CompanyPhoto model
- [ ] ModerationService (submit, approve, reject, suspend, feature, verify)
- [ ] ModerationLog model + auto-logging
- [ ] Admin CompanyResource (full CRUD + moderation actions)
- [ ] Admin ListingResource (full CRUD + moderation actions)
- [ ] Admin UserResource (roles, block/unblock, subscriptions)
- [ ] Admin CategoryResource (nested CRUD, translatable)
- [ ] Admin ModerationLogResource (read-only, filterable)
- [ ] Admin Settings page (platform configuration)
- [ ] Dashboard widgets (pending count, registrations, premium count)

### Phase 3 — Member Dashboard (Week 3)

- [ ] Member CompanyResource (scoped, create/edit/submit)
- [ ] Member ListingResource (scoped, create/edit/submit)
- [ ] Translatable form fields with locale tabs
- [ ] Company member management (invite, roles)
- [ ] Quota enforcement (QuotaService)
- [ ] Subscription status display + upsell
- [ ] Registration form with category selection
- [ ] Auth flows (login, register, password reset, email verify)

### Phase 4 — Messaging System (Week 3-4)

- [ ] MessageThread, ThreadParticipant, Message models
- [ ] MessagingService (create thread, reply, mark read, access checks)
- [ ] MessageThreadPolicy (admin sees all, member sees own)
- [ ] Member messaging Livewire components (inbox, thread, compose)
- [ ] Admin MessageThreadResource (all threads, reply, lock, export)
- [ ] Unread badge in nav + Livewire polling
- [ ] Database notifications for new messages
- [ ] Attachment upload (premium only)
- [ ] Inquiry system (company contact form, inquiry management)

### Phase 5 — Public Site (Week 4)

- [ ] Homepage (featured companies, categories, search bar, premium showcase)
- [ ] Catalog page with Livewire search/filters
- [ ] Transport-filtered catalog
- [ ] Listings board with filters
- [ ] Company profile public page (with inquiry form, photos, transport details)
- [ ] Listing detail public page
- [ ] Category browse pages
- [ ] Locale switcher component (middleware-resolved, single source of truth)
- [ ] SEO: JSON-LD structured data, dynamic meta tags, hreflang
- [ ] Sitemap.xml generation
- [ ] Saved searches + notification on new matches

### Phase 6 — Deploy & Polish (Week 4-5)

- [ ] GitHub Actions CI/CD pipeline
- [ ] cPanel environment configuration
- [ ] Cron job for scheduler
- [ ] Storage symlink
- [ ] Seed demo data (companies, listings, users)
- [ ] Test moderation flow end-to-end
- [ ] Test messaging flow end-to-end (member-to-member, member-to-admin, admin oversight)
- [ ] Test inquiry flow (guest contact → company receives → reply)
- [ ] Test locale switching (verify nav + content switch together)
- [ ] Test quota enforcement
- [ ] Test premium boosting in search results
- [ ] Test verification badge display
- [ ] Test FTP deploy from GitHub Actions end-to-end
- [ ] Test post-deploy HTTP endpoints (migrate, optimize, storage-link)
- [ ] Performance: enable all caches, verify page load times

---

## 19. Seed Data

| Role | Email | Purpose |
|---|---|---|
| super_admin | admin@yfindir.com | Developer/owner |
| admin | client@yfindir.com | Client admin |
| member | demo@demo-b2b.gr | Demo company user (free) |
| premium_member | premium@demo-b2b.gr | Demo premium company |

Seed also creates:
- 11 demo companies across all categories
- 5 demo listings across all types
- 2 demo message threads
- 3 demo inquiries
- Full category tree
- Platform settings defaults

---

## 20. Future Considerations (Not in v1)

| Feature | When |
|---|---|
| Stripe payment integration | When client ready to sell subscriptions |
| Meilisearch/Algolia search | When MySQL FULLTEXT hits performance wall |
| Pusher/WebSockets for real-time messaging | When polling feels too slow |
| Mobile app or API | When market demands |
| Company reviews/ratings | Phase 2 product |
| Product catalog per company | Phase 2 product |
| Multi-currency pricing | When listings need pricing |
| Advanced analytics dashboard | When data volume justifies it |

---

*End of specification. This document represents the architect's full design authority and supersedes all previous versions.*
