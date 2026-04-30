<?php
/**
 * Plugin Name: Pampers Swaddlers Healthcare Centres Registration
 * Description: Shortcode form for Pampers Swaddlers healthcare centres registration.
 * Version: 1.0.0
 * Author: BabyBrands
 * Text Domain: pampers-hc-registration
 */

if (!defined('ABSPATH')) {
    exit;
}

define('HCR_VERSION', '1.0.0');
define('HCR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HCR_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('HCR_ADMIN_PAGE_SLUG', 'hcr-submissions');
/** Posted `action` / nonce scheme for public form (`admin-post.php`). */
define('HCR_POST_ACTION', 'hcr_healthcare_register');
/** Default timezone used when saving `created_at`. */
define('HCR_CREATED_AT_TIMEZONE', 'America/New_York');

require_once HCR_PLUGIN_PATH . 'includes/class-hcr-submissions-list-table.php';

/**
 * @return string
 */
function hcr_get_submissions_table_name()
{
    global $wpdb;

    return $wpdb->prefix . 'hc_registration_submissions';
}

/**
 * Build a WHERE fragment and prepare args for submission list / export search.
 *
 * @return array{sql:string,args:array<int,string>}
 */
function hcr_submissions_search_where_clause($wpdb, $search)
{
    $search = trim((string) $search);
    if ($search === '') {
        return ['sql' => '', 'args' => []];
    }

    $like = '%' . $wpdb->esc_like($search) . '%';
    $fields = [
        'organization_name',
        'contact_first_name',
        'contact_last_name',
        'email',
        'city',
        'province',
        'phone',
        'postal_code',
        'category',
        'category_other',
        'job_title',
        'department',
        'address_1',
        'comments',
    ];
    $parts = [];
    $args = [];
    foreach ($fields as $field) {
        $parts[] = '`' . $field . '` LIKE %s';
        $args[] = $like;
    }

    return [
        'sql' => '(' . implode(' OR ', $parts) . ')',
        'args' => $args,
    ];
}

/**
 * CSV column order for exports (matches submissions table).
 *
 * @return string[]
 */
function hcr_submissions_csv_columns()
{
    return [
        'no',
        'organization_name',
        'contact_first_name',
        'contact_last_name',
        'email',
        'job_title',
        'department',
        'phone',
        'extension',
        'address_1',
        'suite',
        'city',
        'province',
        'postal_code',
        'category',
        'category_other',
        'patients_type',
        'weekly_expecting_parents',
        'number_of_packages',
        'confirmed_distribution',
        'confirmed_package',
        'comments',
        'created_at',
    ];
}

/**
 * Attempt to fix common UTF-8/Windows-1252 mojibake (e.g. "Ã‰" -> "É", "â€™" -> "’").
 *
 * If conversion doesn't produce valid UTF-8, the original string is returned.
 *
 * @param string $value
 * @return string
 */
function hcr_csv_normalize_value($value)
{
    $value = (string) $value;
    if ($value === '') {
        return $value;
    }

    // Normalize common punctuation to ASCII to avoid viewer-specific encoding issues.
    $value = str_replace(
        ["\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}", "\u{2013}", "\u{2014}", "\u{2026}"],
        ["'", "'", '"', '"', '-', '-', '...'],
        $value
    );

    // Convert accented characters to closest ASCII equivalent for maximum compatibility.
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && $ascii !== '') {
            return $ascii;
        }
    }

    return $value;
}

/**
 * Timezone used for `created_at` persistence and formatting.
 *
 * Filter: `hcr_created_at_timezone` (string $tzId).
 *
 * @return DateTimeZone
 */
function hcr_created_at_timezone()
{
    $tzId = (string) apply_filters('hcr_created_at_timezone', HCR_CREATED_AT_TIMEZONE);
    if ($tzId === '') {
        $tzId = HCR_CREATED_AT_TIMEZONE;
    }

    try {
        return new DateTimeZone($tzId);
    } catch (Exception $e) {
        return new DateTimeZone('UTC');
    }
}

/**
 * @return string MySQL datetime in ET (or filtered timezone).
 */
function hcr_created_at_mysql()
{
    $tz = hcr_created_at_timezone();
    try {
        $dt = new DateTimeImmutable('now', $tz);

        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return gmdate('Y-m-d H:i:s');
    }
}

/**
 * Convert a stored MySQL datetime (assumed ET / filtered tz) to timestamp.
 *
 * @param string $mysqlDatetime "Y-m-d H:i:s"
 * @return int|null
 */
function hcr_created_at_to_timestamp($mysqlDatetime)
{
    $mysqlDatetime = trim((string) $mysqlDatetime);
    if ($mysqlDatetime === '') {
        return null;
    }

    $tz = hcr_created_at_timezone();
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $mysqlDatetime, $tz);
    if ($dt instanceof DateTimeImmutable) {
        return $dt->getTimestamp();
    }

    $ts = strtotime($mysqlDatetime);

    return $ts ? (int) $ts : null;
}

function hcr_maybe_install_table()
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = hcr_get_submissions_table_name();
    $charsetCollate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        organization_name VARCHAR(255) NULL DEFAULT NULL,
        contact_first_name VARCHAR(250) NULL DEFAULT NULL,
        contact_last_name VARCHAR(120) NULL DEFAULT NULL,
        email VARCHAR(250) NULL DEFAULT NULL,
        job_title VARCHAR(250) NULL DEFAULT NULL,
        department VARCHAR(250) NULL DEFAULT NULL,
        phone VARCHAR(40) NULL DEFAULT NULL,
        extension VARCHAR(250) NULL DEFAULT NULL,
        address_1 VARCHAR(255) NULL DEFAULT NULL,
        suite VARCHAR(120) NULL DEFAULT NULL,
        city VARCHAR(120) NULL DEFAULT NULL,
        province VARCHAR(10) NULL DEFAULT NULL,
        postal_code VARCHAR(25) NULL DEFAULT NULL,
        category VARCHAR(120) NULL DEFAULT NULL,
        category_other VARCHAR(255) NULL DEFAULT NULL,
        patients_type VARCHAR(40) NULL DEFAULT NULL,
        weekly_expecting_parents VARCHAR(120) NULL DEFAULT NULL,
        number_of_packages VARCHAR(10) NULL DEFAULT NULL,
        confirmed_distribution TINYINT(1) NOT NULL DEFAULT 0,
        confirmed_package TINYINT(1) NOT NULL DEFAULT 0,
        comments TEXT NULL DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_email (email),
        KEY idx_created_at (created_at)
    ) {$charsetCollate};";

    dbDelta($sql);
}

add_action('init', static function () {
    hcr_maybe_install_table();
});

function hcr_get_canadian_provinces()
{
    return [
        'AB' => 'Alberta',
        'BC' => 'British Columbia',
        'MB' => 'Manitoba',
        'NB' => 'New Brunswick',
        'NL' => 'Newfoundland and Labrador',
        'NS' => 'Nova Scotia',
        'NT' => 'Northwest Territories',
        'NU' => 'Nunavut',
        'ON' => 'Ontario',
        'PE' => 'Prince Edward Island',
        'QC' => 'Quebec',
        'SK' => 'Saskatchewan',
        'YT' => 'Yukon',
    ];
}

/**
 * @return string[]
 */
function hcr_get_registration_categories()
{
    return [
        'Doula / Midwife',
        'Ultrasound',
        'Prenatal Instructor',
        'Hospital',
        'Dr. / OBGYN',
        'Pregnancy Centre',
        'Medical Centre',
        'Other',
    ];
}

/**
 * @return string[]
 */
function hcr_get_patients_types()
{
    return ['Prenatal', 'Postnatal', 'Both'];
}

/**
 * Validate submission fields (DB column keys). Used by public form and admin save.
 *
 * @param array<string,mixed> $input Raw values (e.g. from POST after wp_unslash).
 * @param bool $require_confirm Whether confirmed_distribution and confirmed_package must be 1.
 * @return array<string,mixed>|WP_Error Row for insert/update (nulls for empty optional strings).
 */
function hcr_validate_submission_row(array $input, $require_confirm = true)
{
    $organization = sanitize_text_field($input['organization_name'] ?? '');
    $first = sanitize_text_field($input['contact_first_name'] ?? '');
    $last = sanitize_text_field($input['contact_last_name'] ?? '');
    $email = sanitize_email($input['email'] ?? '');
    $job = sanitize_text_field($input['job_title'] ?? '');
    $department = sanitize_text_field($input['department'] ?? '');
    $phone = sanitize_text_field($input['phone'] ?? '');
    $extension = sanitize_text_field($input['extension'] ?? '');
    $address1 = sanitize_text_field($input['address_1'] ?? '');
    $suite = sanitize_text_field($input['suite'] ?? '');
    $city = sanitize_text_field($input['city'] ?? '');
    $province = sanitize_text_field($input['province'] ?? '');
    $postalRaw = strtoupper(preg_replace('/\s+/', ' ', trim((string) ($input['postal_code'] ?? ''))));
    $postal = $postalRaw;
    $category = sanitize_text_field($input['category'] ?? '');
    $categoryOther = sanitize_text_field((string) ($input['category_other'] ?? ''));
    $patientsType = sanitize_text_field($input['patients_type'] ?? '');
    $weeklyExpecting = preg_replace('/\D/', '', (string) ($input['weekly_expecting_parents'] ?? ''));
    $numberOfPackages = sanitize_text_field($input['number_of_packages'] ?? '');
    $confirmRaw = $input['confirmed_distribution'] ?? '';
    $confirmDistribution = $confirmRaw === 1 || $confirmRaw === '1' || $confirmRaw === true;
    $confirmPackageRaw = $input['confirmed_package'] ?? '';
    $confirmPackage = $confirmPackageRaw === 1 || $confirmPackageRaw === '1' || $confirmPackageRaw === true;
    $comments = sanitize_textarea_field((string) ($input['comments'] ?? ''));

    $allowedProvinces = array_keys(hcr_get_canadian_provinces());
    if ($province !== '' && !in_array($province, $allowedProvinces, true)) {
        $province = '';
    }

    if ($category !== '' && !in_array($category, hcr_get_registration_categories(), true)) {
        $category = '';
    }

    if ($category !== 'Other') {
        $categoryOther = '';
    }

    if ($patientsType !== '' && !in_array($patientsType, hcr_get_patients_types(), true)) {
        $patientsType = '';
    }

    if (
        $organization === '' || $first === '' || $last === '' || !is_email($email) ||
        $job === '' || $department === '' || $phone === '' || $address1 === '' ||
        $city === '' || $province === '' || $postal === '' || $category === '' || $patientsType === '' ||
        $weeklyExpecting === '' || !in_array($numberOfPackages, ['12', '24'], true)
    ) {
        return new WP_Error('hcr_required', __('Please complete all required fields.', 'pampers-hc-registration'));
    }

    if ($require_confirm && (!$confirmDistribution || !$confirmPackage)) {
        return new WP_Error('hcr_required', __('Please complete all required fields.', 'pampers-hc-registration'));
    }

    if ($extension !== '' && !ctype_digit($extension)) {
        return new WP_Error('hcr_extension', __('Extension must contain digits only.', 'pampers-hc-registration'));
    }

    if (!preg_match('/^[A-Z]\d[A-Z] \d[A-Z]\d$/', $postal)) {
        return new WP_Error('hcr_postal', __('Please enter a valid postal code (format A1A 1A1).', 'pampers-hc-registration'));
    }

    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) !== 10) {
        return new WP_Error('hcr_phone', __('Please enter a valid 10-digit phone number.', 'pampers-hc-registration'));
    }

    if ($category === 'Other' && trim($categoryOther) === '') {
        return new WP_Error('hcr_required', __('Please specify your category.', 'pampers-hc-registration'));
    }

    return [
        'organization_name' => $organization,
        'contact_first_name' => $first,
        'contact_last_name' => $last,
        'email' => $email,
        'job_title' => $job,
        'department' => $department,
        'phone' => $phone,
        'extension' => $extension === '' ? null : $extension,
        'address_1' => $address1,
        'suite' => $suite === '' ? null : $suite,
        'city' => $city,
        'province' => $province,
        'postal_code' => $postal,
        'category' => $category,
        'category_other' => $categoryOther === '' ? null : $categoryOther,
        'patients_type' => $patientsType,
        'weekly_expecting_parents' => $weeklyExpecting,
        'number_of_packages' => $numberOfPackages,
        'confirmed_distribution' => $confirmDistribution ? 1 : 0,
        'confirmed_package' => $confirmPackage ? 1 : 0,
        'comments' => $comments === '' ? null : $comments,
    ];
}

function hcr_get_redirect_url()
{
    $redirectUrl = '';

    if (is_singular()) {
        $postId = get_queried_object_id();
        if ($postId) {
            $redirectUrl = get_permalink($postId);
        }
    }

    if ($redirectUrl === '') {
        $requestUri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $redirectUrl = home_url($requestUri);
    }

    return remove_query_arg(['hcr_status', 'hcr_message'], $redirectUrl);
}

function hcr_get_feedback()
{
    return [
        'status' => isset($_GET['hcr_status']) ? sanitize_key(wp_unslash($_GET['hcr_status'])) : '',
        'message' => isset($_GET['hcr_message']) ? sanitize_text_field(wp_unslash((string) $_GET['hcr_message'])) : '',
    ];
}

function hcr_build_feedback_url($status, $message = '', $redirectUrl = '')
{
    if ($redirectUrl === '') {
        $redirectUrl = hcr_get_redirect_url();
    }

    $args = ['hcr_status' => $status];
    if ($message !== '') {
        $args['hcr_message'] = $message;
    }

    return add_query_arg($args, $redirectUrl);
}

/**
 * Shortcode: [pampers_hc_registration]
 *
 * Attributes:
 * - thank_you_url — optional custom URL after success
 * - contact_email — shown on thank-you screen
 * - notification_email — optional; if set, submission summary is emailed there. Empty = no email.
 */
function hcr_render_form_shortcode($atts, $content = '', $tag = '')
{
    $shortcodeTag = $tag !== '' ? $tag : 'pampers_hc_registration';
    $atts = shortcode_atts(
        [
            'thank_you_url' => '',
            'contact_email' => 'info@babybrands.com',
            'notification_email' => '',
        ],
        $atts,
        $shortcodeTag
    );

    $thankYouUrl = esc_url(trim((string) $atts['thank_you_url']));
    $contactEmail = sanitize_email((string) $atts['contact_email']);
    if ($contactEmail === '' || !is_email($contactEmail)) {
        $contactEmail = 'info@babybrands.com';
    }
    $contactEmail = (string) apply_filters('hcr_contact_email', $contactEmail, $shortcodeTag);

    $notifyEmail = sanitize_email((string) $atts['notification_email']);

    $feedback = hcr_get_feedback();
    $redirectUrl = hcr_get_redirect_url();
    $successRedirectUrl = $thankYouUrl !== '' ? $thankYouUrl : $redirectUrl;
    $successRedirectUrl = wp_validate_redirect($successRedirectUrl, $redirectUrl);
    $successRedirectUrl = remove_query_arg(['hcr_status', 'hcr_message'], $successRedirectUrl);
    $formPageUrl = remove_query_arg(['hcr_status', 'hcr_message'], $redirectUrl);

    wp_enqueue_style(
        'hcr-registration',
        HCR_PLUGIN_URL . 'assets/css/hc-registration.css',
        [],
        HCR_VERSION
    );

    $isSuccess = $feedback['status'] === 'success';
    $errorMessage = $feedback['status'] === 'error' ? $feedback['message'] : '';

    $provinces = hcr_get_canadian_provinces();

    $assets = [
        'step1' => HCR_PLUGIN_URL . 'assets/images/step1.webp',
        'samplits_logo' => HCR_PLUGIN_URL . 'assets/images/babybrands-logo.png',
        'thankyou_banner' => HCR_PLUGIN_URL . 'assets/images/distributors-banner-thankyou.webp',
    ];

    ob_start();
    ?>
    <main class="hcr-shell">
        <div class="hcr-container">
            <?php if ($isSuccess) : ?>
                <div class="hcr-thankyou hcr-thankyou-page">
                    <div class="hcr-thankyou-card">
                        <div class="hcr-thankyou-badge"><?php esc_html_e('PAMPERS SWADDLERS HEALTHCARE CENTRES REGISTRATION RECEIVED', 'pampers-hc-registration'); ?></div>
                        <h1 class="hcr-thankyou-card-title"><?php esc_html_e('Thank You!', 'pampers-hc-registration'); ?></h1>
                        <p class="hcr-thankyou-card-lead"><?php esc_html_e('We have received your form.', 'pampers-hc-registration'); ?></p>
                        <div class="hcr-thankyou-banner-wrap text-center">
                            <img class="hcr-thankyou-banner" src="<?php echo esc_url($assets['thankyou_banner']); ?>" alt="">
                        </div>
                        <p class="hcr-thankyou-card-text">
                            <?php esc_html_e('We will review your submission. If you have any questions, please contact', 'pampers-hc-registration'); ?>
                            <a href="mailto:<?php echo esc_attr($contactEmail); ?>"><?php echo esc_html($contactEmail); ?></a>
                        </p>
                        <a class="btn btn-primary hcr-thankyou-cta" href="<?php echo esc_url($formPageUrl); ?>"><?php esc_html_e('Submit another form', 'pampers-hc-registration'); ?></a>
                        <div class="hcr-thankyou-card-footer">
                            <div class="hcr-powered-by"><?php esc_html_e('Powered by Samplits', 'pampers-hc-registration'); ?></div>
                            <img src="<?php echo esc_url($assets['samplits_logo']); ?>" alt="<?php esc_attr_e('Samplits Logo', 'pampers-hc-registration'); ?>">
                        </div>
                    </div>
                </div>
                <script>
                (function () {
                    try {
                        var u = new URL(window.location.href);
                        if (!u.searchParams.has('hcr_status')) return;
                        u.searchParams.delete('hcr_status');
                        u.searchParams.delete('hcr_message');
                        var qs = u.searchParams.toString();
                        window.history.replaceState(null, '', u.pathname + (qs ? '?' + qs : '') + u.hash);
                    } catch (e) {}
                })();
                </script>
            <?php elseif ($errorMessage !== '') : ?>
                <div class="alert alert-danger text-center mb-4">
                    <?php echo esc_html($errorMessage); ?>
                </div>
                <script>
                (function () {
                    try {
                        var u = new URL(window.location.href);
                        if (!u.searchParams.has('hcr_status') && !u.searchParams.has('hcr_message')) return;
                        u.searchParams.delete('hcr_status');
                        u.searchParams.delete('hcr_message');
                        var qs = u.searchParams.toString();
                        window.history.replaceState(null, '', u.pathname + (qs ? '?' + qs : '') + u.hash);
                    } catch (e) {}
                })();
                </script>
            <?php endif; ?>

            <?php if (!$isSuccess) : ?>
                <div class="hcr-form-container">
                    <div class="p-3 pb-0">
                        <img class="hcr-header-image" src="<?php echo esc_url($assets['step1']); ?>" alt="">
                    </div>

                    <div class="hcr-topbar">
                        <div class="hcr-title"><?php esc_html_e('PAMPERS SWADDLERS HEALTHCARE CENTRES REGISTRATION', 'pampers-hc-registration'); ?></div>
                    </div>

                    <form class="hcr-form px-3 pb-3 mt-3" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" novalidate>
                        <input type="hidden" name="action" value="<?php echo esc_attr(HCR_POST_ACTION); ?>">
                        <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirectUrl); ?>">
                        <input type="hidden" name="hcr_success_redirect" value="<?php echo esc_url($successRedirectUrl); ?>">
                        <input type="hidden" name="hcr_notification_email" value="<?php echo esc_attr($notifyEmail); ?>">
                        <?php wp_nonce_field(HCR_POST_ACTION, 'hcr_nonce'); ?>

                        <h3 class="h6 font-weight-bold mb-3 hcr-section-title"><?php esc_html_e('General Information', 'pampers-hc-registration'); ?></h3>

                        <div class="form-group">
                            <label class="font-weight-bold d-block" for="hcr-name">*<?php esc_html_e('Name of the organization', 'pampers-hc-registration'); ?></label>
                            <input type="text" class="form-control" id="hcr-name" name="name" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold d-block" for="hcr-firstName">*<?php esc_html_e('Contact First Name', 'pampers-hc-registration'); ?></label>
                                    <input type="text" class="form-control" id="hcr-firstName" name="firstName" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold d-block" for="hcr-lastName">*<?php esc_html_e('Contact Last Name', 'pampers-hc-registration'); ?></label>
                                    <input type="text" class="form-control" id="hcr-lastName" name="lastName" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold d-block" for="hcr-email">*<?php esc_html_e('Email Address', 'pampers-hc-registration'); ?></label>
                                    <input type="email" class="form-control" id="hcr-email" name="email" required>
                                    <div class="invalid-feedback" id="hcr-emailError" style="display:none;"><?php esc_html_e('Invalid email address', 'pampers-hc-registration'); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold d-block" for="hcr-job">*<?php esc_html_e('Job Title', 'pampers-hc-registration'); ?></label>
                                    <input type="text" class="form-control" id="hcr-job" name="job" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold d-block" for="hcr-department">*<?php esc_html_e('Department/Unit', 'pampers-hc-registration'); ?></label>
                            <input type="text" class="form-control" id="hcr-department" name="department" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold d-block" for="hcr-phone">*<?php esc_html_e('Telephone Number', 'pampers-hc-registration'); ?></label>
                                    <input type="text" class="form-control" id="hcr-phone" name="phone" required>
                                    <div class="invalid-feedback" id="hcr-phoneError" style="display:none;"><?php esc_html_e('Please enter a valid phone number', 'pampers-hc-registration'); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold d-block" for="hcr-extension"><?php esc_html_e('Extension', 'pampers-hc-registration'); ?></label>
                                    <input type="text" class="form-control" id="hcr-extension" name="extension">
                                    <div class="invalid-feedback" id="hcr-extensionError" style="display:none;"><?php esc_html_e('Please enter numbers only', 'pampers-hc-registration'); ?></div>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <h3 class="h6 font-weight-bold mb-3 hcr-section-title"><?php esc_html_e('Shipping Information', 'pampers-hc-registration'); ?></h3>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold d-block" for="hcr-address1">*<?php esc_html_e('Address 1', 'pampers-hc-registration'); ?></label>
                                    <input type="text" class="form-control" id="hcr-address1" name="address1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold d-block" for="hcr-suite"><?php esc_html_e('Suite #', 'pampers-hc-registration'); ?></label>
                                    <input type="text" class="form-control" id="hcr-suite" name="suite">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="font-weight-bold d-block" for="hcr-city">*<?php esc_html_e('City', 'pampers-hc-registration'); ?></label>
                                    <input type="text" class="form-control" id="hcr-city" name="city" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="font-weight-bold d-block" for="hcr-province">*<?php esc_html_e('Province', 'pampers-hc-registration'); ?></label>
                                    <select class="form-control" id="hcr-province" name="province" required>
                                        <option value=""><?php esc_html_e('Select Province', 'pampers-hc-registration'); ?></option>
                                        <?php foreach ($provinces as $abbr => $label) : ?>
                                            <option value="<?php echo esc_attr($abbr); ?>"><?php echo esc_html($abbr . ' - ' . $label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="font-weight-bold d-block" for="hcr-postalCode">*<?php esc_html_e('Postal Code', 'pampers-hc-registration'); ?></label>
                                    <input type="text" class="form-control" id="hcr-postalCode" name="postalCode" required>
                                    <div class="invalid-feedback" id="hcr-postalCodeError" style="display:none;"><?php esc_html_e('Format: XNX NXN', 'pampers-hc-registration'); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mt-3">
                            <label class="font-weight-bold d-block">*<?php esc_html_e('Category', 'pampers-hc-registration'); ?></label>
                            <?php foreach (hcr_get_registration_categories() as $cat) :
                                $id = 'hcr-category-' . sanitize_title($cat);
                                ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="category" id="<?php echo esc_attr($id); ?>" value="<?php echo esc_attr($cat); ?>" required>
                                    <label class="form-check-label" for="<?php echo esc_attr($id); ?>"><?php echo esc_html($cat); ?></label>
                                </div>
                            <?php endforeach; ?>
                            <div class="form-group mt-2 hcr-category-other" style="display:none;">
                                <label class="font-weight-bold d-block" for="hcr-categoryOther"><?php esc_html_e('Other (please specify)', 'pampers-hc-registration'); ?></label>
                                <input type="text" class="form-control hcr-small-text-input" id="hcr-categoryOther" name="categoryOther" maxlength="255">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold d-block">*<?php esc_html_e('Identify the majority of patients you cater to', 'pampers-hc-registration'); ?></label>
                            <?php foreach (hcr_get_patients_types() as $pt) : ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="patientsType" id="hcr-patientsType-<?php echo esc_attr(strtolower($pt)); ?>" value="<?php echo esc_attr($pt); ?>" required>
                                    <label class="form-check-label" for="hcr-patientsType-<?php echo esc_attr(strtolower($pt)); ?>"><?php echo esc_html($pt); ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="hcr-late-fields">
                            <div class="form-group">
                                <div class="hcr-inline-label-input">
                                    <label class="font-weight-bold hcr-late-fields-top-label mb-0" for="hcr-weeklyExpecting">*<?php esc_html_e('Approximately how many expecting parents do you see on a weekly basis?', 'pampers-hc-registration'); ?></label>
                                    <input type="text" class="form-control hcr-inline-small-input" id="hcr-weeklyExpecting" name="weeklyExpecting" required inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                                </div>
                                <div class="invalid-feedback" id="hcr-weeklyExpectingError" style="display:none;"><?php esc_html_e('Please enter a whole number.', 'pampers-hc-registration'); ?></div>
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold d-block">*<?php esc_html_e('Number of packages you would like', 'pampers-hc-registration'); ?></label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="numberOfPackages" id="hcr-packages-12" value="12" required>
                                    <label class="form-check-label" for="hcr-packages-12">12</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="numberOfPackages" id="hcr-packages-24" value="24" required>
                                    <label class="form-check-label" for="hcr-packages-24">24</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="hcr-confirmDistribution" name="confirmDistribution" value="1" required>
                                    <label class="form-check-label" for="hcr-confirmDistribution"><?php esc_html_e('* I confirm that our healthcare centre will distribute Pampers Swaddlers to expecting parents only, and we will kindly encourage parents to register using the QR code provided on the package to access additional support and resources.', 'pampers-hc-registration'); ?></label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="hcr-confirmPackage" name="confirmPackage" value="1" required>
                                    <label class="form-check-label" for="hcr-confirmPackage"><?php esc_html_e('* One sample package per expecting parent.', 'pampers-hc-registration'); ?></label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold d-block" for="hcr-comments"><?php esc_html_e('Comments', 'pampers-hc-registration'); ?></label>
                                <textarea class="form-control" id="hcr-comments" name="comments" rows="3" maxlength="1000"></textarea>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary"><?php esc_html_e('Register', 'pampers-hc-registration'); ?></button>
                        </div>
                    </form>

                    <div class="hcr-footer p-4 hcr-thankyou-card-footer">
                        <div class="hcr-powered-by"><?php esc_html_e('Powered by Samplits', 'pampers-hc-registration'); ?></div>
                        <img src="<?php echo esc_url($assets['samplits_logo']); ?>" alt="<?php esc_attr_e('Samplits Logo', 'pampers-hc-registration'); ?>">
                    </div>
                </div>
                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var form = document.querySelector('.hcr-form');
                    if (!form || form.dataset.hcrReady === '1') return;
                    form.dataset.hcrReady = '1';

                    var catOtherWrap = form.querySelector('.hcr-category-other');
                    var catOtherInput = document.getElementById('hcr-categoryOther');
                    function updateCategoryOther() {
                        var selected = form.querySelector('input[name="category"]:checked');
                        var isOther = selected && selected.value === 'Other';
                        if (catOtherWrap) catOtherWrap.style.display = isOther ? 'block' : 'none';
                        if (catOtherInput) {
                            if (isOther) {
                                catOtherInput.setAttribute('required', 'required');
                            } else {
                                catOtherInput.removeAttribute('required');
                                catOtherInput.value = '';
                            }
                        }
                    }
                    form.addEventListener('change', function (e) {
                        if (e && e.target && e.target.name === 'category') updateCategoryOther();
                    });
                    updateCategoryOther();

                    function formatPhone(value) {
                        if (!value) return value;
                        var digits = value.replace(/\D/g, '').slice(0, 10);
                        if (digits.length <= 3) return digits;
                        if (digits.length <= 6) return '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
                        return '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
                    }

                    var phone = document.getElementById('hcr-phone');
                    if (phone) {
                        phone.addEventListener('input', function () {
                            phone.value = formatPhone(phone.value);
                            syncFieldVisual(phone, false);
                        });
                    }

                    function hideErr(id) {
                        var n = document.getElementById(id);
                        if (n) n.style.display = 'none';
                    }
                    function showErr(id) {
                        var n = document.getElementById(id);
                        if (n) n.style.display = 'block';
                    }

                    function syncFieldVisual(el, isBlur) {
                        if (!el || !form.contains(el)) return;
                        if (el.type === 'submit' || el.type === 'button') return;

                        if (el.type === 'checkbox') {
                            if (el.required) {
                                el.classList.toggle('is-valid', el.checked);
                                el.classList.toggle('is-invalid', !el.checked);
                            }
                            return;
                        }

                        if (el.type === 'radio' && el.name) {
                            var esc = el.name.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
                            var radios = form.querySelectorAll('input[type="radio"][name="' + esc + '"]');
                            if (!radios.length) return;
                            var tracked = ['category', 'patientsType', 'numberOfPackages'];
                            var req = false;
                            radios.forEach(function (r) { if (r.required) req = true; });
                            if (!req && tracked.indexOf(el.name) === -1) return;
                            var checked = form.querySelector('input[type="radio"][name="' + esc + '"]:checked');
                            var ok = !!checked;
                            radios.forEach(function (r) {
                                r.classList.remove('is-valid', 'is-invalid');
                                if (ok && r.checked) r.classList.add('is-valid');
                                if (!ok) r.classList.add('is-invalid');
                            });
                            return;
                        }

                        if (el.tagName !== 'INPUT' && el.tagName !== 'SELECT' && el.tagName !== 'TEXTAREA') return;

                        var v = (el.value || '').trim();
                        var id = el.id;

                        if (el.required && v === '') {
                            el.classList.remove('is-valid');
                            if (isBlur) el.classList.add('is-invalid');
                            else el.classList.remove('is-invalid');
                            if (id === 'hcr-email') hideErr('hcr-emailError');
                            if (id === 'hcr-phone') hideErr('hcr-phoneError');
                            if (id === 'hcr-extension') hideErr('hcr-extensionError');
                            if (id === 'hcr-postalCode') hideErr('hcr-postalCodeError');
                            if (id === 'hcr-weeklyExpecting') hideErr('hcr-weeklyExpectingError');
                            return;
                        }

                        var ok = true;
                        if (id === 'hcr-email') {
                            ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
                            if (!ok) showErr('hcr-emailError'); else hideErr('hcr-emailError');
                        } else if (id === 'hcr-phone') {
                            ok = /^\(\d{3}\) \d{3}-\d{4}$/.test(v);
                            if (!ok) showErr('hcr-phoneError'); else hideErr('hcr-phoneError');
                        } else if (id === 'hcr-extension') {
                            ok = v === '' || /^[0-9]+$/.test(v);
                            if (!ok) showErr('hcr-extensionError'); else hideErr('hcr-extensionError');
                        } else if (id === 'hcr-postalCode') {
                            ok = /^[A-Za-z]\d[A-Za-z] \d[A-Za-z]\d$/.test(v);
                            if (!ok) showErr('hcr-postalCodeError'); else hideErr('hcr-postalCodeError');
                        } else if (id === 'hcr-weeklyExpecting') {
                            ok = /^[0-9]+$/.test(v);
                            if (!ok) showErr('hcr-weeklyExpectingError'); else hideErr('hcr-weeklyExpectingError');
                        }

                        if (!ok) {
                            el.classList.remove('is-valid');
                            el.classList.add('is-invalid');
                            return;
                        }
                        el.classList.remove('is-invalid');
                        el.classList.add('is-valid');
                    }

                    form.addEventListener('input', function (e) {
                        if (form.contains(e.target)) syncFieldVisual(e.target, false);
                    });
                    form.addEventListener('change', function (e) {
                        if (form.contains(e.target)) syncFieldVisual(e.target, true);
                    });
                    form.addEventListener('blur', function (e) {
                        if (form.contains(e.target)) syncFieldVisual(e.target, true);
                    }, true);

                    function validateForm() {
                        var isValid = true;
                        var required = form.querySelectorAll('[required]');
                        required.forEach(function (el) {
                            if (el.type === 'checkbox') {
                                el.classList.toggle('is-invalid', !el.checked);
                                el.classList.toggle('is-valid', el.checked);
                                if (!el.checked) isValid = false;
                                return;
                            }
                            if (el.type === 'radio') {
                                var group = form.querySelectorAll('input[type="radio"][name="' + el.name.replace(/"/g, '\\"') + '"]');
                                var anyChecked = false;
                                group.forEach(function (g) { if (g.checked) anyChecked = true; });
                                group.forEach(function (g) {
                                    g.classList.toggle('is-invalid', !anyChecked);
                                    g.classList.toggle('is-valid', anyChecked && g.checked);
                                });
                                if (!anyChecked) isValid = false;
                                return;
                            }
                            if (el.value === '') {
                                el.classList.add('is-invalid');
                                el.classList.remove('is-valid');
                                isValid = false;
                            } else {
                                el.classList.remove('is-invalid');
                                el.classList.add('is-valid');
                            }
                        });

                        var email = document.getElementById('hcr-email');
                        var emailErr = document.getElementById('hcr-emailError');
                        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value || '')) {
                            isValid = false;
                            email.classList.add('is-invalid');
                            email.classList.remove('is-valid');
                            if (emailErr) emailErr.style.display = 'block';
                        } else if (email) {
                            email.classList.remove('is-invalid');
                            email.classList.add('is-valid');
                            if (emailErr) emailErr.style.display = 'none';
                        }

                        var phoneEl = document.getElementById('hcr-phone');
                        var phoneErr = document.getElementById('hcr-phoneError');
                        if (phoneEl && !/^\(\d{3}\) \d{3}-\d{4}$/.test(phoneEl.value || '')) {
                            isValid = false;
                            phoneEl.classList.add('is-invalid');
                            phoneEl.classList.remove('is-valid');
                            if (phoneErr) phoneErr.style.display = 'block';
                        } else if (phoneEl) {
                            phoneEl.classList.remove('is-invalid');
                            phoneEl.classList.add('is-valid');
                            if (phoneErr) phoneErr.style.display = 'none';
                        }

                        var extEl = document.getElementById('hcr-extension');
                        var extErr = document.getElementById('hcr-extensionError');
                        if (extEl && extEl.value !== '' && !/^[0-9]+$/.test(extEl.value)) {
                            isValid = false;
                            extEl.classList.add('is-invalid');
                            extEl.classList.remove('is-valid');
                            if (extErr) extErr.style.display = 'block';
                        } else if (extEl) {
                            extEl.classList.remove('is-invalid');
                            extEl.classList.add('is-valid');
                            if (extErr) extErr.style.display = 'none';
                        }

                        var postal = document.getElementById('hcr-postalCode');
                        var postalErr = document.getElementById('hcr-postalCodeError');
                        if (postal && !/^[A-Za-z]\d[A-Za-z] \d[A-Za-z]\d$/.test((postal.value || '').trim())) {
                            isValid = false;
                            postal.classList.add('is-invalid');
                            postal.classList.remove('is-valid');
                            if (postalErr) postalErr.style.display = 'block';
                        } else if (postal) {
                            postal.classList.remove('is-invalid');
                            postal.classList.add('is-valid');
                            if (postalErr) postalErr.style.display = 'none';
                        }

                        var weekly = document.getElementById('hcr-weeklyExpecting');
                        var weeklyErr = document.getElementById('hcr-weeklyExpectingError');
                        if (weekly && !/^[0-9]+$/.test((weekly.value || '').trim())) {
                            isValid = false;
                            weekly.classList.add('is-invalid');
                            weekly.classList.remove('is-valid');
                            if (weeklyErr) weeklyErr.style.display = 'block';
                        } else if (weekly) {
                            weekly.classList.remove('is-invalid');
                            weekly.classList.add('is-valid');
                            if (weeklyErr) weeklyErr.style.display = 'none';
                        }

                        return isValid;
                    }

                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        if (!validateForm()) {
                            var c = document.querySelector('.hcr-container');
                            if (c) window.scrollTo({ top: c.getBoundingClientRect().top + window.scrollY - 40, behavior: 'smooth' });
                            return;
                        }
                        if (typeof form.submit === 'function') form.submit();
                    });
                });
                </script>
            <?php endif; ?>
        </div>
    </main>
    <?php

    return ob_get_clean();
}

add_shortcode('pampers_hc_registration', 'hcr_render_form_shortcode');

function hcr_handle_submission()
{
    $redirectUrl = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : home_url('/');
    if ($redirectUrl === '') {
        $redirectUrl = home_url('/');
    }

    $successRedirect = isset($_POST['hcr_success_redirect'])
        ? esc_url_raw(wp_unslash($_POST['hcr_success_redirect']))
        : $redirectUrl;
    if ($successRedirect === '') {
        $successRedirect = $redirectUrl;
    }
    $successRedirect = wp_validate_redirect($successRedirect, $redirectUrl);
    $successRedirect = remove_query_arg(['hcr_status', 'hcr_message'], $successRedirect);

    $posted_action = isset($_POST['action']) ? sanitize_text_field(wp_unslash($_POST['action'])) : '';
    $nonce_by_action = [
        HCR_POST_ACTION => HCR_POST_ACTION,
        'hcr_submit_registration' => 'hcr_submit_registration',
    ];
    $nonce_action = isset($nonce_by_action[$posted_action]) ? $nonce_by_action[$posted_action] : HCR_POST_ACTION;
    if (
        !isset($_POST['hcr_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hcr_nonce'])), $nonce_action)
    ) {
        wp_safe_redirect(hcr_build_feedback_url('error', __('Security check failed. Please try again.', 'pampers-hc-registration'), $redirectUrl));
        exit;
    }
    global $wpdb;

    $input = [
        'organization_name' => wp_unslash($_POST['name'] ?? ''),
        'contact_first_name' => wp_unslash($_POST['firstName'] ?? ''),
        'contact_last_name' => wp_unslash($_POST['lastName'] ?? ''),
        'email' => wp_unslash($_POST['email'] ?? ''),
        'job_title' => wp_unslash($_POST['job'] ?? ''),
        'department' => wp_unslash($_POST['department'] ?? ''),
        'phone' => wp_unslash($_POST['phone'] ?? ''),
        'extension' => wp_unslash($_POST['extension'] ?? ''),
        'address_1' => wp_unslash($_POST['address1'] ?? ''),
        'suite' => wp_unslash($_POST['suite'] ?? ''),
        'city' => wp_unslash($_POST['city'] ?? ''),
        'province' => wp_unslash($_POST['province'] ?? ''),
        'postal_code' => wp_unslash($_POST['postalCode'] ?? ''),
        'category' => wp_unslash($_POST['category'] ?? ''),
        'category_other' => wp_unslash($_POST['categoryOther'] ?? ''),
        'patients_type' => wp_unslash($_POST['patientsType'] ?? ''),
        'weekly_expecting_parents' => wp_unslash($_POST['weeklyExpecting'] ?? ''),
        'number_of_packages' => wp_unslash($_POST['numberOfPackages'] ?? ''),
        'confirmed_distribution' => !empty($_POST['confirmDistribution']) ? wp_unslash($_POST['confirmDistribution']) : '',
        'confirmed_package' => !empty($_POST['confirmPackage']) ? wp_unslash($_POST['confirmPackage']) : '',
        'comments' => wp_unslash($_POST['comments'] ?? ''),
    ];

    $validated = hcr_validate_submission_row($input, true);
    if (is_wp_error($validated)) {
        wp_safe_redirect(hcr_build_feedback_url('error', $validated->get_error_message(), $redirectUrl));
        exit;
    }

    $table = hcr_get_submissions_table_name();
    $validated['created_at'] = hcr_created_at_mysql();
    $inserted = $wpdb->insert(
        $table,
        $validated,
        [
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            '%s', '%s', '%s', '%d', '%d', '%s', '%s',
        ]
    );

    if (!$inserted) {
        wp_safe_redirect(hcr_build_feedback_url('error', __('Could not save your submission. Please try again later.', 'pampers-hc-registration'), $redirectUrl));
        exit;
    }

    $notify = sanitize_email(wp_unslash($_POST['hcr_notification_email'] ?? ''));
    if ($notify !== '' && is_email($notify) && apply_filters('hcr_send_notification_email', true, $wpdb->insert_id)) {
        $subject = sprintf('[%s] New HC Registration', wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
        $body = sprintf(
            "Organization: %s\nContact: %s %s\nEmail: %s\nPhone: %s\nAddress: %s, %s, %s %s\nCategory: %s\nCategory (other): %s\nPatients: %s\nExpecting parents (weekly): %s\nPackages: %s\nConfirmed distribution: yes\nOne sample package per parent: %s\nComments: %s\n",
            $validated['organization_name'],
            $validated['contact_first_name'],
            $validated['contact_last_name'],
            $validated['email'],
            $validated['phone'],
            $validated['address_1'],
            $validated['city'],
            $validated['province'],
            $validated['postal_code'],
            $validated['category'],
            isset($validated['category_other']) && $validated['category_other'] !== null ? $validated['category_other'] : '',
            $validated['patients_type'],
            $validated['weekly_expecting_parents'],
            $validated['number_of_packages'],
            !empty($validated['confirmed_package']) ? 'yes' : 'no',
            isset($validated['comments']) && $validated['comments'] !== null ? $validated['comments'] : ''
        );
        wp_mail($notify, $subject, $body);
    }

    wp_safe_redirect(hcr_build_feedback_url('success', '', $successRedirect));
    exit;
}

add_action('admin_post_nopriv_' . HCR_POST_ACTION, 'hcr_handle_submission');
add_action('admin_post_' . HCR_POST_ACTION, 'hcr_handle_submission');
/** @deprecated Prior action name; kept for cached HTML. */
add_action('admin_post_nopriv_hcr_submit_registration', 'hcr_handle_submission');
add_action('admin_post_hcr_submit_registration', 'hcr_handle_submission');

function hcr_register_admin_menu()
{
    add_menu_page(
        __('HC Registrations', 'pampers-hc-registration'),
        __('HC Registrations', 'pampers-hc-registration'),
        'manage_options',
        HCR_ADMIN_PAGE_SLUG,
        'hcr_render_admin_submissions_page',
        'dashicons-clipboard',
        26
    );
}

add_action('admin_menu', 'hcr_register_admin_menu');

function hcr_maybe_export_submissions_csv()
{
    if (empty($_GET['page']) || sanitize_key(wp_unslash((string) $_GET['page'])) !== HCR_ADMIN_PAGE_SLUG) {
        return;
    }
    if (empty($_GET['hcr_export']) || sanitize_key(wp_unslash((string) $_GET['hcr_export'])) !== 'csv') {
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'pampers-hc-registration'));
    }

    check_admin_referer('hcr_export_csv');

    global $wpdb;

    $table = hcr_get_submissions_table_name();
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if ($exists !== $table) {
        wp_die(esc_html__('Submissions table is not available.', 'pampers-hc-registration'));
    }

    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
    $cond = hcr_submissions_search_where_clause($wpdb, $search);

    $sql = "SELECT * FROM `{$table}`";
    if ($cond['sql'] !== '') {
        $sql .= ' WHERE ' . $cond['sql'];
    }
    $sql .= ' ORDER BY `created_at` DESC, `id` DESC';

    $rows = $cond['args'] === []
        ? $wpdb->get_results($sql, ARRAY_A)
        : $wpdb->get_results($wpdb->prepare($sql, ...$cond['args']), ARRAY_A);

    if (!is_array($rows)) {
        $rows = [];
    }

    $columns = hcr_submissions_csv_columns();
    $filename = 'hc-registration-submissions-' . gmdate('Y-m-d') . '.csv';

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'wb');
    if ($out === false) {
        exit;
    }

    // Excel on Windows detects UTF-8 reliably when a BOM is present.
    // (Some plain-text viewers may show it as "ï»¿" if opened with the wrong encoding.)

    fputcsv($out, $columns);

    $no = 0;
    foreach ($rows as $row) {
        $no++;
        $line = [];
        foreach ($columns as $col) {
            if ($col === 'no') {
                $line[] = (string) $no;
                continue;
            }

            $val = isset($row[$col]) && $row[$col] !== null ? (string) $row[$col] : '';
            $line[] = hcr_csv_normalize_value($val);
        }
        fputcsv($out, $line);
    }

    fclose($out);
    exit;
}

add_action('admin_init', 'hcr_maybe_export_submissions_csv', 1);

function hcr_render_admin_submissions_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'pampers-hc-registration'));
    }

    if (!empty($_GET['action']) && $_GET['action'] === 'edit' && !empty($_GET['id'])) {
        $editId = absint($_GET['id']);
        if ($editId > 0) {
            hcr_render_submission_edit($editId);

            return;
        }
    }

    if (!empty($_GET['view'])) {
        $viewId = absint($_GET['view']);
        if ($viewId > 0) {
            hcr_render_submission_detail($viewId);

            return;
        }
    }

    $list_table = new HCR_Submissions_List_Table();
    $list_table->prepare_items();

    $notice = isset($_GET['hcr_notice']) ? sanitize_key(wp_unslash($_GET['hcr_notice'])) : '';
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e('Pampers Swaddlers Healthcare Centres Registration Submissions', 'pampers-hc-registration'); ?></h1>
        <hr class="wp-header-end">
        <?php if ($notice === 'saved') : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Submission updated.', 'pampers-hc-registration'); ?></p></div>
        <?php elseif ($notice === 'deleted') : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Submission deleted.', 'pampers-hc-registration'); ?></p></div>
        <?php elseif ($notice === 'error') : ?>
            <div class="notice notice-error is-dismissible"><p><?php echo esc_html(isset($_GET['hcr_err']) ? sanitize_text_field(wp_unslash((string) $_GET['hcr_err'])) : __('Something went wrong.', 'pampers-hc-registration')); ?></p></div>
        <?php endif; ?>
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr(HCR_ADMIN_PAGE_SLUG); ?>">
            <?php if (!empty($_GET['orderby'])) : ?>
                <input type="hidden" name="orderby" value="<?php echo esc_attr(sanitize_key(wp_unslash((string) $_GET['orderby']))); ?>">
            <?php endif; ?>
            <?php if (!empty($_GET['order'])) : ?>
                <input type="hidden" name="order" value="<?php echo esc_attr(strtoupper(sanitize_text_field(wp_unslash((string) $_GET['order']))) === 'ASC' ? 'ASC' : 'DESC'); ?>">
            <?php endif; ?>
            <?php $list_table->display(); ?>
        </form>
    </div>
    <?php
}

/**
 * @param int $id Submission primary key.
 */
function hcr_render_submission_detail($id)
{
    global $wpdb;

    $table = hcr_get_submissions_table_name();
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if ($exists !== $table) {
        echo '<div class="wrap"><p>' . esc_html__('Submissions table is not available.', 'pampers-hc-registration') . '</p></div>';

        return;
    }

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d", $id));
    if (!$row) {
        echo '<div class="wrap"><p>' . esc_html__('Submission not found.', 'pampers-hc-registration') . '</p></div>';

        return;
    }

    $hcr_no = isset($_GET['hcr_no']) ? absint(wp_unslash($_GET['hcr_no'])) : 0;

    $back_url = add_query_arg(['page' => HCR_ADMIN_PAGE_SLUG], admin_url('admin.php'));
    $edit_url = add_query_arg(
        array_filter(
            [
                'page' => HCR_ADMIN_PAGE_SLUG,
                'action' => 'edit',
                'id' => (int) $row->id,
                'hcr_no' => $hcr_no > 0 ? $hcr_no : null,
            ]
        ),
        admin_url('admin.php')
    );
    $delete_url = wp_nonce_url(
        add_query_arg(
            [
                'action' => 'hcr_delete_submission',
                'id' => (int) $row->id,
            ],
            admin_url('admin-post.php')
        ),
        'hcr_delete_submission_' . (int) $row->id
    );
    $confirm_js = wp_json_encode(__('Delete this submission? This cannot be undone.', 'pampers-hc-registration'));

    $fields = [
        'organization_name' => __('Organization', 'pampers-hc-registration'),
        'contact_first_name' => __('Contact first name', 'pampers-hc-registration'),
        'contact_last_name' => __('Contact last name', 'pampers-hc-registration'),
        'email' => __('Email', 'pampers-hc-registration'),
        'job_title' => __('Job title', 'pampers-hc-registration'),
        'department' => __('Department', 'pampers-hc-registration'),
        'phone' => __('Phone', 'pampers-hc-registration'),
        'extension' => __('Extension', 'pampers-hc-registration'),
        'address_1' => __('Address', 'pampers-hc-registration'),
        'suite' => __('Suite', 'pampers-hc-registration'),
        'city' => __('City', 'pampers-hc-registration'),
        'province' => __('Province', 'pampers-hc-registration'),
        'postal_code' => __('Postal code', 'pampers-hc-registration'),
        'category' => __('Category', 'pampers-hc-registration'),
        'category_other' => __('Category (other)', 'pampers-hc-registration'),
        'patients_type' => __('Patients type', 'pampers-hc-registration'),
        'weekly_expecting_parents' => __('Expecting parents (weekly)', 'pampers-hc-registration'),
        'number_of_packages' => __('Packages', 'pampers-hc-registration'),
        'confirmed_distribution' => __('Confirmed distribution', 'pampers-hc-registration'),
        'confirmed_package' => __('One sample package per expecting parent', 'pampers-hc-registration'),
        'comments' => __('Comments', 'pampers-hc-registration'),
        'created_at' => __('Submitted', 'pampers-hc-registration'),
    ];
    ?>
    <div class="wrap hcr-submission-detail">
        <h1><?php echo $hcr_no > 0 ? esc_html(sprintf(__('No. %d', 'pampers-hc-registration'), $hcr_no)) : esc_html__('Submission', 'pampers-hc-registration'); ?></h1>
        <?php if (!empty($_GET['hcr_notice']) && sanitize_key(wp_unslash($_GET['hcr_notice'])) === 'saved') : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Submission updated.', 'pampers-hc-registration'); ?></p></div>
        <?php endif; ?>
        <p><a href="<?php echo esc_url($back_url); ?>">&larr; <?php esc_html_e('Back to list', 'pampers-hc-registration'); ?></a></p>
        <table class="widefat striped" style="max-width:920px;">
            <tbody>
            <?php foreach ($fields as $key => $label) : ?>
                <tr>
                    <th scope="row" style="width:220px;"><?php echo esc_html($label); ?></th>
                    <td>
                        <?php
                        $val = isset($row->$key) ? (string) $row->$key : '';
                        if ($key === 'confirmed_distribution' || $key === 'confirmed_package') {
                            echo (int) $val === 1 ? esc_html__('Yes', 'pampers-hc-registration') : esc_html__('No', 'pampers-hc-registration');
                        } elseif ($key === 'email' && $val !== '') {
                            echo '<a href="' . esc_url('mailto:' . $val) . '">' . esc_html($val) . '</a>';
                        } elseif ($key === 'created_at' && $val !== '') {
                            $ts = hcr_created_at_to_timestamp($val);
                            echo $ts ? esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $ts, hcr_created_at_timezone())) : esc_html($val);
                        } elseif ($key === 'comments' && $val !== '') {
                            echo nl2br(esc_html($val));
                        } else {
                            echo $val !== '' ? esc_html($val) : '—';
                        }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="submit" style="max-width:920px;">
            <a class="button button-primary" href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Edit', 'pampers-hc-registration'); ?></a>
            <a class="button button-link-delete" href="<?php echo esc_url($delete_url); ?>" onclick="return window.confirm(<?php echo $confirm_js; ?>);"><?php esc_html_e('Delete', 'pampers-hc-registration'); ?></a>
        </p>
    </div>
    <?php
}

/**
 * @param int $id Submission primary key.
 */
function hcr_render_submission_edit($id)
{
    global $wpdb;

    $table = hcr_get_submissions_table_name();
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if ($exists !== $table) {
        echo '<div class="wrap"><p>' . esc_html__('Submissions table is not available.', 'pampers-hc-registration') . '</p></div>';

        return;
    }

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d", $id));
    if (!$row) {
        echo '<div class="wrap"><p>' . esc_html__('Submission not found.', 'pampers-hc-registration') . '</p></div>';

        return;
    }

    $hcr_no = isset($_GET['hcr_no']) ? absint(wp_unslash($_GET['hcr_no'])) : 0;

    $back_list = add_query_arg(['page' => HCR_ADMIN_PAGE_SLUG], admin_url('admin.php'));
    $back_view = add_query_arg(
        array_filter(
            [
                'page' => HCR_ADMIN_PAGE_SLUG,
                'view' => (int) $row->id,
                'hcr_no' => $hcr_no > 0 ? $hcr_no : null,
            ]
        ),
        admin_url('admin.php')
    );

    $v = static function ($key) use ($row) {
        return isset($row->$key) ? (string) $row->$key : '';
    };

    $provinces = hcr_get_canadian_provinces();
    ?>
    <div class="wrap">
        <h1><?php echo $hcr_no > 0 ? esc_html(sprintf(__('Edit — No. %d', 'pampers-hc-registration'), $hcr_no)) : esc_html__('Edit submission', 'pampers-hc-registration'); ?></h1>
        <?php
        $admNotice = isset($_GET['hcr_notice']) ? sanitize_key(wp_unslash($_GET['hcr_notice'])) : '';
        if ($admNotice === 'error' && !empty($_GET['hcr_err'])) :
            $errMsg = sanitize_text_field(wp_unslash((string) $_GET['hcr_err']));
            ?>
            <div class="notice notice-error is-dismissible"><p><?php echo esc_html($errMsg); ?></p></div>
        <?php endif; ?>
        <p>
            <a href="<?php echo esc_url($back_list); ?>">&larr; <?php esc_html_e('Back to list', 'pampers-hc-registration'); ?></a>
            &nbsp;|&nbsp;
            <a href="<?php echo esc_url($back_view); ?>"><?php esc_html_e('View detail', 'pampers-hc-registration'); ?></a>
        </p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="hcr-admin-edit-form">
            <?php wp_nonce_field('hcr_save_submission_' . (int) $row->id); ?>
            <input type="hidden" name="action" value="hcr_save_submission">
            <input type="hidden" name="submission_id" value="<?php echo (int) $row->id; ?>">
            <input type="hidden" name="hcr_display_no" value="<?php echo $hcr_no > 0 ? (int) $hcr_no : ''; ?>">

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="hcr-adm-org"><?php esc_html_e('Organization', 'pampers-hc-registration'); ?></label></th>
                    <td><input name="organization_name" id="hcr-adm-org" type="text" class="regular-text" value="<?php echo esc_attr($v('organization_name')); ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hcr-adm-fn"><?php esc_html_e('Contact first name', 'pampers-hc-registration'); ?></label></th>
                    <td><input name="contact_first_name" id="hcr-adm-fn" type="text" class="regular-text" value="<?php echo esc_attr($v('contact_first_name')); ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hcr-adm-ln"><?php esc_html_e('Contact last name', 'pampers-hc-registration'); ?></label></th>
                    <td><input name="contact_last_name" id="hcr-adm-ln" type="text" class="regular-text" value="<?php echo esc_attr($v('contact_last_name')); ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hcr-adm-em"><?php esc_html_e('Email', 'pampers-hc-registration'); ?></label></th>
                    <td><input name="email" id="hcr-adm-em" type="email" class="regular-text" value="<?php echo esc_attr($v('email')); ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hcr-adm-job"><?php esc_html_e('Job title', 'pampers-hc-registration'); ?></label></th>
                    <td><input name="job_title" id="hcr-adm-job" type="text" class="regular-text" value="<?php echo esc_attr($v('job_title')); ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hcr-adm-dept"><?php esc_html_e('Department', 'pampers-hc-registration'); ?></label></th>
                    <td><input name="department" id="hcr-adm-dept" type="text" class="regular-text" value="<?php echo esc_attr($v('department')); ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hcr-adm-ph"><?php esc_html_e('Phone', 'pampers-hc-registration'); ?></label></th>
                    <td><input name="phone" id="hcr-adm-ph" type="text" class="regular-text" value="<?php echo esc_attr($v('phone')); ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hcr-adm-ext"><?php esc_html_e('Extension', 'pampers-hc-registration'); ?></label></th>
                    <td><input name="extension" id="hcr-adm-ext" type="text" class="regular-text" value="<?php echo esc_attr($v('extension')); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hcr-adm-a1"><?php esc_html_e('Address', 'pampers-hc-registration'); ?></label></th>
                    <td><input name="address_1" id="hcr-adm-a1" type="text" class="regular-text" value="<?php echo esc_attr($v('address_1')); ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hcr-adm-suite"><?php esc_html_e('Suite', 'pampers-hc-registration'); ?></label></th>
                    <td><input name="suite" id="hcr-adm-suite" type="text" class="regular-text" value="<?php echo esc_attr($v('suite')); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hcr-adm-city"><?php esc_html_e('City', 'pampers-hc-registration'); ?></label></th>
                    <td><input name="city" id="hcr-adm-city" type="text" class="regular-text" value="<?php echo esc_attr($v('city')); ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hcr-adm-prov"><?php esc_html_e('Province', 'pampers-hc-registration'); ?></label></th>
                    <td>
                        <select name="province" id="hcr-adm-prov" required>
                            <option value=""><?php esc_html_e('Select…', 'pampers-hc-registration'); ?></option>
                            <?php foreach ($provinces as $abbr => $label) : ?>
                                <option value="<?php echo esc_attr($abbr); ?>" <?php selected($v('province'), $abbr); ?>><?php echo esc_html($abbr . ' — ' . $label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hcr-adm-postal"><?php esc_html_e('Postal code', 'pampers-hc-registration'); ?></label></th>
                    <td><input name="postal_code" id="hcr-adm-postal" type="text" class="regular-text" value="<?php echo esc_attr($v('postal_code')); ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Category', 'pampers-hc-registration'); ?></th>
                    <td>
                        <?php foreach (hcr_get_registration_categories() as $cat) : ?>
                            <label style="display:inline-block;margin-right:12px;">
                                <input type="radio" name="category" value="<?php echo esc_attr($cat); ?>" <?php checked($v('category'), $cat); ?> required>
                                <?php echo esc_html($cat); ?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hcr-adm-cat-other"><?php esc_html_e('Category (other)', 'pampers-hc-registration'); ?></label></th>
                    <td><input name="category_other" id="hcr-adm-cat-other" type="text" class="regular-text" value="<?php echo esc_attr($v('category_other')); ?>" maxlength="255"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Patients type', 'pampers-hc-registration'); ?></th>
                    <td>
                        <?php foreach (hcr_get_patients_types() as $pt) : ?>
                            <label style="display:inline-block;margin-right:12px;">
                                <input type="radio" name="patients_type" value="<?php echo esc_attr($pt); ?>" <?php checked($v('patients_type'), $pt); ?> required>
                                <?php echo esc_html($pt); ?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hcr-adm-weekly"><?php esc_html_e('Expecting parents (weekly)', 'pampers-hc-registration'); ?></label></th>
                    <td><input name="weekly_expecting_parents" id="hcr-adm-weekly" type="text" class="small-text" value="<?php echo esc_attr($v('weekly_expecting_parents')); ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Packages', 'pampers-hc-registration'); ?></th>
                    <td>
                        <label><input type="radio" name="number_of_packages" value="12" <?php checked($v('number_of_packages'), '12'); ?> required> 12</label>
                        &nbsp;&nbsp;
                        <label><input type="radio" name="number_of_packages" value="24" <?php checked($v('number_of_packages'), '24'); ?>> 24</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Confirmation', 'pampers-hc-registration'); ?></th>
                    <td>
                        <p>
                            <label>
                                <input type="checkbox" name="confirmed_distribution" value="1" <?php checked((int) $v('confirmed_distribution'), 1); ?> required>
                                <?php esc_html_e('Confirmed distribution statement', 'pampers-hc-registration'); ?>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="confirmed_package" value="1" <?php checked((int) $v('confirmed_package'), 1); ?> required>
                                <?php esc_html_e('One sample package per expecting parent.', 'pampers-hc-registration'); ?>
                            </label>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hcr-adm-comments"><?php esc_html_e('Comments', 'pampers-hc-registration'); ?></label></th>
                    <td>
                        <textarea name="comments" id="hcr-adm-comments" class="large-text" rows="4" maxlength="1000"><?php echo esc_textarea($v('comments')); ?></textarea>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Save changes', 'pampers-hc-registration')); ?>
        </form>
    </div>
    <?php
}

function hcr_handle_admin_delete_submission()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'pampers-hc-registration'));
    }

    $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    if ($id < 1) {
        wp_safe_redirect(hcr_admin_submissions_list_url(['hcr_notice' => 'error', 'hcr_err' => __('Invalid submission.', 'pampers-hc-registration')]));
        exit;
    }

    check_admin_referer('hcr_delete_submission_' . $id);

    global $wpdb;
    $table = hcr_get_submissions_table_name();
    $wpdb->delete($table, ['id' => $id], ['%d']);

    wp_safe_redirect(hcr_admin_submissions_list_url(['hcr_notice' => 'deleted']));
    exit;
}

function hcr_handle_admin_save_submission()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'pampers-hc-registration'));
    }

    $id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;
    $display_no = isset($_POST['hcr_display_no']) ? absint(wp_unslash((string) $_POST['hcr_display_no'])) : 0;
    if ($id < 1 || !isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'hcr_save_submission_' . $id)) {
        wp_safe_redirect(hcr_admin_submissions_list_url(['hcr_notice' => 'error', 'hcr_err' => __('Security check failed.', 'pampers-hc-registration')]));
        exit;
    }

    $input = [
        'organization_name' => wp_unslash($_POST['organization_name'] ?? ''),
        'contact_first_name' => wp_unslash($_POST['contact_first_name'] ?? ''),
        'contact_last_name' => wp_unslash($_POST['contact_last_name'] ?? ''),
        'email' => wp_unslash($_POST['email'] ?? ''),
        'job_title' => wp_unslash($_POST['job_title'] ?? ''),
        'department' => wp_unslash($_POST['department'] ?? ''),
        'phone' => wp_unslash($_POST['phone'] ?? ''),
        'extension' => wp_unslash($_POST['extension'] ?? ''),
        'address_1' => wp_unslash($_POST['address_1'] ?? ''),
        'suite' => wp_unslash($_POST['suite'] ?? ''),
        'city' => wp_unslash($_POST['city'] ?? ''),
        'province' => wp_unslash($_POST['province'] ?? ''),
        'postal_code' => wp_unslash($_POST['postal_code'] ?? ''),
        'category' => wp_unslash($_POST['category'] ?? ''),
        'category_other' => wp_unslash($_POST['category_other'] ?? ''),
        'patients_type' => wp_unslash($_POST['patients_type'] ?? ''),
        'weekly_expecting_parents' => wp_unslash($_POST['weekly_expecting_parents'] ?? ''),
        'number_of_packages' => wp_unslash($_POST['number_of_packages'] ?? ''),
        'confirmed_distribution' => !empty($_POST['confirmed_distribution']) ? '1' : '',
        'confirmed_package' => !empty($_POST['confirmed_package']) ? '1' : '',
        'comments' => wp_unslash($_POST['comments'] ?? ''),
    ];

    $validated = hcr_validate_submission_row($input, true);
    if (is_wp_error($validated)) {
        wp_safe_redirect(
            hcr_admin_submissions_list_url(
                array_filter(
                    [
                        'hcr_notice' => 'error',
                        'hcr_err' => $validated->get_error_message(),
                        'action' => 'edit',
                        'id' => $id,
                        'hcr_no' => $display_no > 0 ? $display_no : null,
                    ]
                )
            )
        );
        exit;
    }

    global $wpdb;
    $table = hcr_get_submissions_table_name();
    $wpdb->update(
        $table,
        $validated,
        ['id' => $id],
        [
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            '%s', '%s', '%s', '%d', '%d', '%s',
        ],
        ['%d']
    );

    if ($wpdb->last_error !== '') {
        wp_safe_redirect(
            hcr_admin_submissions_list_url(
                array_filter(
                    [
                        'hcr_notice' => 'error',
                        'hcr_err' => __('Could not save changes.', 'pampers-hc-registration'),
                        'action' => 'edit',
                        'id' => $id,
                        'hcr_no' => $display_no > 0 ? $display_no : null,
                    ]
                )
            )
        );
        exit;
    }

    wp_safe_redirect(
        add_query_arg(
            array_filter(
                [
                    'page' => HCR_ADMIN_PAGE_SLUG,
                    'view' => $id,
                    'hcr_notice' => 'saved',
                    'hcr_no' => $display_no > 0 ? $display_no : null,
                ]
            ),
            admin_url('admin.php')
        )
    );
    exit;
}

/**
 * @param array<string,string> $args Query args merged onto admin list URL.
 */
function hcr_admin_submissions_list_url(array $args = [])
{
    return add_query_arg(array_merge(['page' => HCR_ADMIN_PAGE_SLUG], $args), admin_url('admin.php'));
}

add_action('admin_post_hcr_delete_submission', 'hcr_handle_admin_delete_submission');
add_action('admin_post_hcr_save_submission', 'hcr_handle_admin_save_submission');
