# Pambers Swaddlers HC Registration

WordPress plugin that renders a healthcare-centre registration form via shortcode. Styling aligns with the **photo release** form (colours, section headings) and the distributor step-one field layout, without loading Bootstrap or jQuery from CDNs.

**Text domain:** `pambers-hc-registration`  
**Version:** 1.0.0 (`HCR_VERSION` in the main plugin file).

## Requirements

- WordPress 5.x or newer (uses `admin-post.php`, nonces, `dbDelta`, block-friendly shortcodes).

## Installation

1. Copy the folder `hc-registration-plugin` into `wp-content/plugins/`.
2. In **Plugins**, activate **Pambers Swaddlers HC Registration**.
3. Add a shortcode to any page or post (Classic editor, Shortcode block, etc.).

## Shortcodes

| Shortcode | Use |
|-----------|-----|
| `[pambers_hc_registration]` | Renders the registration form |

### Attributes

| Attribute | Description |
|-----------|-------------|
| `thank_you_url` | Optional. After a successful submit, redirect here. If omitted, the user stays on the same page (query args cleaned). |
| `contact_email` | Email shown on the thank-you screen. Default: `info@babybrands.com`. |
| `notification_email` | Optional. If set to a valid address, a plain-text summary is sent with `wp_mail` on each submission. |

**Example**

```text
[pambers_hc_registration contact_email="support@example.com" notification_email="admin@example.com"]
```

## Form contents (summary)

- **General information:** Organization name, contact name, email, job title, department, phone, extension.
- **Shipping information:** Address, suite, city, province (Canada), postal code (validated as `A1A 1A1`).
- **Category** (single choice) and **patients** type (Prenatal / Postnatal / Both).
- **Weekly volume:** Numeric “expecting parents per week”.
- **Packages:** `12` or `24`.
- **Confirmation:** Required checkbox for Pampers Swaddlers distribution / QR wording.

Submissions are validated on the client (basic patterns) and on the server (sanitization, allowed values, nonce).

## Database

On `init`, the plugin creates or updates:

**Table:** `{wpdb_prefix}hc_registration_submissions`

Columns include: organization and contact fields, address fields, `category`, `patients_type`, `weekly_expecting_parents`, `number_of_packages`, `confirmed_distribution`, `created_at`. All **VARCHAR** columns are nullable (`NULL DEFAULT NULL`). `confirmed_distribution` remains `TINYINT NOT NULL DEFAULT 0` and `created_at` is `NOT NULL`. The table is created or brought in line with `dbDelta()` on `init`.

## Assets

Place or replace files under `assets/images/` as needed:

- `step1.webp` — header image above the title.
- `samplits-logo.png` — footer / thank-you branding.
- `distributors-banner-thankyou.webp` — thank-you banner.

Styles: `assets/css/hc-registration.css` (self-contained layout and form primitives; no Bootstrap bundle).

## Developer hooks

- **`hcr_contact_email`** — Filter the resolved contact email (shortcode + validation).
- **`hcr_send_notification_email`** — `(bool $send, int $insert_id)` — Return `false` to skip the admin notification email.

## Submission flow

- Form posts to `admin-post.php` with `action=hcr_healthcare_register` (constant `HCR_POST_ACTION` in the main plugin file).
- Hooks: `admin_post_hcr_healthcare_register` and `admin_post_nopriv_hcr_healthcare_register`. Deprecated `admin_post_*` hooks for `hcr_submit_registration` remain registered so briefly cached form HTML from an earlier plugin version can still post until the page is refreshed.
- Success and error states return to the form URL with `hcr_status` / `hcr_message` query args; the shortcode clears them from the address bar with a small inline script where applicable.

## WordPress admin (review submissions)

- In the dashboard sidebar: **HC registrations** (clipboard icon). Requires the **`manage_options`** capability (typically Administrators).
- Paginated list (20 per page) with sortable columns.
- **Actions** column (last): **View** (read-only detail), **Edit** (full form, same validation as the public form), **Delete** (nonce-protected `admin-post.php` action; browser confirm before delete).
- Detail screen also has **Edit** and **Delete** at the bottom. After a successful save you are returned to the detail view with a success notice.
- Admin screen slug: `hcr-submissions` (`admin.php?page=hcr-submissions`).

## Support

For behaviour tied to other BabyBrands plugins (e.g. photo gallery / distributor forms), compare field names and styling in this plugin’s PHP and CSS under the same workspace.
