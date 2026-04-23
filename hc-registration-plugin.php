<?php
/**
 * Plugin Name: Pambers Swaddlers HC Registration
 * Description: Shortcode form for winners photo release / HC registration, matching distributor registration step 1 layout and styling.
 * Version: 1.0.0
 * Author: BabyBrands
 * Text Domain: pambers-hc-registration
 */

if (!defined('ABSPATH')) {
    exit;
}

define('HCR_VERSION', '1.0.0');
define('HCR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HCR_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * @return string
 */
function hcr_get_submissions_table_name()
{
    global $wpdb;

    return $wpdb->prefix . 'hc_registration_submissions';
}

function hcr_maybe_install_table()
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = hcr_get_submissions_table_name();
    $charsetCollate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        organization_name VARCHAR(255) NOT NULL,
        contact_first_name VARCHAR(120) NOT NULL,
        contact_last_name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL,
        job_title VARCHAR(190) NOT NULL,
        department VARCHAR(190) NOT NULL,
        phone VARCHAR(40) NOT NULL,
        extension VARCHAR(20) NULL,
        address_1 VARCHAR(255) NOT NULL,
        suite VARCHAR(120) NULL,
        city VARCHAR(120) NOT NULL,
        province VARCHAR(10) NOT NULL,
        postal_code VARCHAR(25) NOT NULL,
        category VARCHAR(120) NOT NULL,
        patients_type VARCHAR(40) NOT NULL,
        weekly_expecting_parents VARCHAR(120) NOT NULL DEFAULT '',
        number_of_packages VARCHAR(10) NOT NULL DEFAULT '',
        confirmed_distribution TINYINT(1) NOT NULL DEFAULT 0,
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
 * Shortcode: [pambers_hc_registration] or [hc_winners_photo_release_form]
 *
 * Attributes:
 * - thank_you_url — optional custom URL after success
 * - contact_email — shown on thank-you screen
 * - notification_email — optional; if set, submission summary is emailed there. Empty = no email.
 */
function hcr_render_form_shortcode($atts, $content = '', $tag = '')
{
    $shortcodeTag = $tag !== '' ? $tag : 'pambers_hc_registration';
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
        'samplits_logo' => HCR_PLUGIN_URL . 'assets/images/samplits-logo.png',
        'thankyou_banner' => HCR_PLUGIN_URL . 'assets/images/distributors-banner-thankyou.webp',
    ];

    ob_start();
    ?>
    <main class="hcr-shell">
        <div class="hcr-container">
            <?php if ($isSuccess) : ?>
                <div class="hcr-thankyou hcr-thankyou-page">
                    <div class="hcr-thankyou-card">
                        <div class="hcr-thankyou-badge"><?php esc_html_e('PAMBERS SWADDLERS HC REGISTRATION RECEIVED', 'pambers-hc-registration'); ?></div>
                        <h1 class="hcr-thankyou-card-title"><?php esc_html_e('Thank You!', 'pambers-hc-registration'); ?></h1>
                        <p class="hcr-thankyou-card-lead"><?php esc_html_e('We have received your form.', 'pambers-hc-registration'); ?></p>
                        <div class="hcr-thankyou-banner-wrap text-center">
                            <img class="hcr-thankyou-banner" src="<?php echo esc_url($assets['thankyou_banner']); ?>" alt="">
                        </div>
                        <p class="hcr-thankyou-card-text">
                            <?php esc_html_e('We will review your submission. If you have any questions, please contact', 'pambers-hc-registration'); ?>
                            <a href="mailto:<?php echo esc_attr($contactEmail); ?>"><?php echo esc_html($contactEmail); ?></a>
                        </p>
                        <a class="btn btn-primary hcr-thankyou-cta" href="<?php echo esc_url($formPageUrl); ?>"><?php esc_html_e('Submit another form', 'pambers-hc-registration'); ?></a>
                        <div class="hcr-thankyou-card-footer">
                            <div class="hcr-powered-by"><?php esc_html_e('Powered by Samplits', 'pambers-hc-registration'); ?></div>
                            <img src="<?php echo esc_url($assets['samplits_logo']); ?>" alt="<?php esc_attr_e('Samplits Logo', 'pambers-hc-registration'); ?>">
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
                    <div class="p-3">
                        <img class="hcr-header-image" src="<?php echo esc_url($assets['step1']); ?>" alt="">
                    </div>

                    <div class="hcr-topbar">
                        <div class="hcr-title"><?php esc_html_e('PAMBERS SWADDLERS HC REGISTRATION', 'pambers-hc-registration'); ?></div>
                    </div>

                    <form class="hcr-form px-3 pb-3" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" novalidate>
                        <input type="hidden" name="action" value="hcr_winners_register">
                        <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirectUrl); ?>">
                        <input type="hidden" name="hcr_success_redirect" value="<?php echo esc_url($successRedirectUrl); ?>">
                        <input type="hidden" name="hcr_notification_email" value="<?php echo esc_attr($notifyEmail); ?>">
                        <?php wp_nonce_field('hcr_winners_register', 'hcr_nonce'); ?>

                        <h3 class="h6 font-weight-bold mb-3 hcr-section-title"><?php esc_html_e('General Information', 'pambers-hc-registration'); ?></h3>

                        <div class="form-group">
                            <label class="font-weight-bold d-block" for="hcr-name">*<?php esc_html_e('Name of the organization', 'pambers-hc-registration'); ?></label>
                            <input type="text" class="form-control" id="hcr-name" name="name" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold d-block" for="hcr-firstName">*<?php esc_html_e('Contact First Name', 'pambers-hc-registration'); ?></label>
                                    <input type="text" class="form-control" id="hcr-firstName" name="firstName" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold d-block" for="hcr-lastName">*<?php esc_html_e('Contact Last Name', 'pambers-hc-registration'); ?></label>
                                    <input type="text" class="form-control" id="hcr-lastName" name="lastName" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold d-block" for="hcr-email">*<?php esc_html_e('Email Address', 'pambers-hc-registration'); ?></label>
                                    <input type="email" class="form-control" id="hcr-email" name="email" required>
                                    <div class="invalid-feedback" id="hcr-emailError" style="display:none;"><?php esc_html_e('Invalid email address', 'pambers-hc-registration'); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold d-block" for="hcr-job">*<?php esc_html_e('Job Title', 'pambers-hc-registration'); ?></label>
                                    <input type="text" class="form-control" id="hcr-job" name="job" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold d-block" for="hcr-department">*<?php esc_html_e('Department/Unit', 'pambers-hc-registration'); ?></label>
                            <input type="text" class="form-control" id="hcr-department" name="department" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold d-block" for="hcr-phone">*<?php esc_html_e('Telephone Number', 'pambers-hc-registration'); ?></label>
                                    <input type="text" class="form-control" id="hcr-phone" name="phone" required>
                                    <div class="invalid-feedback" id="hcr-phoneError" style="display:none;"><?php esc_html_e('Please enter a valid phone number', 'pambers-hc-registration'); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold d-block" for="hcr-extension"><?php esc_html_e('Extension', 'pambers-hc-registration'); ?></label>
                                    <input type="text" class="form-control" id="hcr-extension" name="extension">
                                    <div class="invalid-feedback" id="hcr-extensionError" style="display:none;"><?php esc_html_e('Please enter numbers only', 'pambers-hc-registration'); ?></div>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <h3 class="h6 font-weight-bold mb-3 hcr-section-title"><?php esc_html_e('Shipping Information', 'pambers-hc-registration'); ?></h3>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold d-block" for="hcr-address1">*<?php esc_html_e('Address 1', 'pambers-hc-registration'); ?></label>
                                    <input type="text" class="form-control" id="hcr-address1" name="address1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold d-block" for="hcr-suite"><?php esc_html_e('Suite #', 'pambers-hc-registration'); ?></label>
                                    <input type="text" class="form-control" id="hcr-suite" name="suite">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="font-weight-bold d-block" for="hcr-city">*<?php esc_html_e('City', 'pambers-hc-registration'); ?></label>
                                    <input type="text" class="form-control" id="hcr-city" name="city" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="font-weight-bold d-block" for="hcr-province">*<?php esc_html_e('Province', 'pambers-hc-registration'); ?></label>
                                    <select class="form-control" id="hcr-province" name="province" required>
                                        <option value=""><?php esc_html_e('Select Province', 'pambers-hc-registration'); ?></option>
                                        <?php foreach ($provinces as $abbr => $label) : ?>
                                            <option value="<?php echo esc_attr($abbr); ?>"><?php echo esc_html($abbr . ' - ' . $label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="font-weight-bold d-block" for="hcr-postalCode">*<?php esc_html_e('Postal Code', 'pambers-hc-registration'); ?></label>
                                    <input type="text" class="form-control" id="hcr-postalCode" name="postalCode" required>
                                    <div class="invalid-feedback" id="hcr-postalCodeError" style="display:none;"><?php esc_html_e('Format: XNX NXN', 'pambers-hc-registration'); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mt-3">
                            <label class="font-weight-bold d-block">*<?php esc_html_e('Category', 'pambers-hc-registration'); ?></label>
                            <?php
                            $categories = [
                                'Doula / Midwife',
                                'Ultrasound',
                                'Prenatal Instructor',
                                'Hospital',
                                'Dr. / OBGYN',
                                'Trade Show',
                                'Pregnancy Centre',
                                'Medical Centre',
                                'Other',
                            ];
                            foreach ($categories as $cat) :
                                $id = 'hcr-category-' . sanitize_title($cat);
                                ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="category" id="<?php echo esc_attr($id); ?>" value="<?php echo esc_attr($cat); ?>" required>
                                    <label class="form-check-label" for="<?php echo esc_attr($id); ?>"><?php echo esc_html($cat); ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold d-block">*<?php esc_html_e('Identify the majority of patients you cater to', 'pambers-hc-registration'); ?></label>
                            <?php foreach (['Prenatal', 'Postnatal', 'Both'] as $pt) : ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="patientsType" id="hcr-patientsType-<?php echo esc_attr(strtolower($pt)); ?>" value="<?php echo esc_attr($pt); ?>" required>
                                    <label class="form-check-label" for="hcr-patientsType-<?php echo esc_attr(strtolower($pt)); ?>"><?php echo esc_html($pt); ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <hr class="hcr-late-fields-sep">

                        <div class="hcr-late-fields">
                            <div class="form-group">
                                <label class="font-weight-bold d-block hcr-late-fields-top-label" for="hcr-weeklyExpecting">*<?php esc_html_e('Approximately how many expecting parents do you see on a weekly basis?', 'pambers-hc-registration'); ?></label>
                                <input type="text" class="form-control" id="hcr-weeklyExpecting" name="weeklyExpecting" required inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                                <div class="invalid-feedback" id="hcr-weeklyExpectingError" style="display:none;"><?php esc_html_e('Please enter a whole number.', 'pambers-hc-registration'); ?></div>
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold d-block">*<?php esc_html_e('Number of packages you would like', 'pambers-hc-registration'); ?></label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="numberOfPackages" id="hcr-packages-12" value="12" required>
                                    <label class="form-check-label" for="hcr-packages-12">12</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="numberOfPackages" id="hcr-packages-24" value="24" required>
                                    <label class="form-check-label" for="hcr-packages-24">24</label>
                                </div>
                            </div>
                            <div class="form-group hcr-confirm-check">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="hcr-confirmDistribution" name="confirmDistribution" value="1" required>
                                    <label class="form-check-label" for="hcr-confirmDistribution"><?php esc_html_e('I confirm that our healthcare centre will distribute Pampers Swaddlers to expecting parents only, and we will kindly encourage parents to register using the QR code provided on the package to access additional support and resources.', 'pambers-hc-registration'); ?></label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary"><?php esc_html_e('Submit', 'pambers-hc-registration'); ?></button>
                        </div>
                    </form>

                    <div class="hcr-footer p-4 hcr-thankyou-card-footer">
                        <div class="hcr-powered-by"><?php esc_html_e('Powered by Samplits', 'pambers-hc-registration'); ?></div>
                        <img src="<?php echo esc_url($assets['samplits_logo']); ?>" alt="<?php esc_attr_e('Samplits Logo', 'pambers-hc-registration'); ?>">
                    </div>
                </div>
                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var form = document.querySelector('.hcr-form');
                    if (!form || form.dataset.hcrReady === '1') return;
                    form.dataset.hcrReady = '1';

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

add_shortcode('pambers_hc_registration', 'hcr_render_form_shortcode');
add_shortcode('hc_winners_photo_release_form', 'hcr_render_form_shortcode');

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

    if (
        !isset($_POST['hcr_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hcr_nonce'])), 'hcr_winners_register')
    ) {
        wp_safe_redirect(hcr_build_feedback_url('error', __('Security check failed. Please try again.', 'pambers-hc-registration'), $redirectUrl));
        exit;
    }
    wp_safe_redirect(hcr_build_feedback_url('success', '', $successRedirect));
    exit;
    global $wpdb;

    $organization = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $first = sanitize_text_field(wp_unslash($_POST['firstName'] ?? ''));
    $last = sanitize_text_field(wp_unslash($_POST['lastName'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $job = sanitize_text_field(wp_unslash($_POST['job'] ?? ''));
    $department = sanitize_text_field(wp_unslash($_POST['department'] ?? ''));
    $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
    $extension = sanitize_text_field(wp_unslash($_POST['extension'] ?? ''));
    $address1 = sanitize_text_field(wp_unslash($_POST['address1'] ?? ''));
    $suite = sanitize_text_field(wp_unslash($_POST['suite'] ?? ''));
    $city = sanitize_text_field(wp_unslash($_POST['city'] ?? ''));
    $province = sanitize_text_field(wp_unslash($_POST['province'] ?? ''));
    $postalRaw = strtoupper(preg_replace('/\s+/', ' ', trim((string) wp_unslash($_POST['postalCode'] ?? ''))));
    $postal = $postalRaw;
    $category = sanitize_text_field(wp_unslash($_POST['category'] ?? ''));
    $patientsType = sanitize_text_field(wp_unslash($_POST['patientsType'] ?? ''));
    $weeklyExpecting = preg_replace('/\D/', '', (string) wp_unslash($_POST['weeklyExpecting'] ?? ''));
    $numberOfPackages = sanitize_text_field(wp_unslash($_POST['numberOfPackages'] ?? ''));
    $confirmDistribution = !empty($_POST['confirmDistribution']) && (string) wp_unslash($_POST['confirmDistribution']) === '1';

    $allowedProvinces = array_keys(hcr_get_canadian_provinces());
    if ($province !== '' && !in_array($province, $allowedProvinces, true)) {
        $province = '';
    }

    $allowedCategories = [
        'Doula / Midwife',
        'Ultrasound',
        'Prenatal Instructor',
        'Hospital',
        'Dr. / OBGYN',
        'Trade Show',
        'Pregnancy Centre',
        'Medical Centre',
        'Other',
    ];
    if ($category !== '' && !in_array($category, $allowedCategories, true)) {
        $category = '';
    }

    $allowedPatients = ['Prenatal', 'Postnatal', 'Both'];
    if ($patientsType !== '' && !in_array($patientsType, $allowedPatients, true)) {
        $patientsType = '';
    }

    if (
        $organization === '' || $first === '' || $last === '' || !is_email($email) ||
        $job === '' || $department === '' || $phone === '' || $address1 === '' ||
        $city === '' || $province === '' || $postal === '' || $category === '' || $patientsType === '' ||
        $weeklyExpecting === '' || !in_array($numberOfPackages, ['12', '24'], true) || !$confirmDistribution
    ) {
        wp_safe_redirect(hcr_build_feedback_url('error', __('Please complete all required fields.', 'pambers-hc-registration'), $redirectUrl));
        exit;
    }

    if ($extension !== '' && !ctype_digit($extension)) {
        wp_safe_redirect(hcr_build_feedback_url('error', __('Extension must contain digits only.', 'pambers-hc-registration'), $redirectUrl));
        exit;
    }

    if (!preg_match('/^[A-Z]\d[A-Z] \d[A-Z]\d$/', $postal)) {
        wp_safe_redirect(hcr_build_feedback_url('error', __('Please enter a valid postal code (format A1A 1A1).', 'pambers-hc-registration'), $redirectUrl));
        exit;
    }

    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) !== 10) {
        wp_safe_redirect(hcr_build_feedback_url('error', __('Please enter a valid 10-digit phone number.', 'pambers-hc-registration'), $redirectUrl));
        exit;
    }

    $table = hcr_get_submissions_table_name();
    $inserted = $wpdb->insert(
        $table,
        [
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
            'patients_type' => $patientsType,
            'weekly_expecting_parents' => $weeklyExpecting,
            'number_of_packages' => $numberOfPackages,
            'confirmed_distribution' => 1,
        ],
        [
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            '%s', '%s', '%d',
        ]
    );

    if (!$inserted) {
        wp_safe_redirect(hcr_build_feedback_url('error', __('Could not save your submission. Please try again later.', 'pambers-hc-registration'), $redirectUrl));
        exit;
    }

    $notify = sanitize_email(wp_unslash($_POST['hcr_notification_email'] ?? ''));
    if ($notify !== '' && is_email($notify) && apply_filters('hcr_send_notification_email', true, $wpdb->insert_id)) {
        $subject = sprintf('[%s] New HC registration', wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
        $body = sprintf(
            "Organization: %s\nContact: %s %s\nEmail: %s\nPhone: %s\nAddress: %s, %s, %s %s\nCategory: %s\nPatients: %s\nExpecting parents (weekly): %s\nPackages: %s\nConfirmed distribution: yes\n",
            $organization,
            $first,
            $last,
            $email,
            $phone,
            $address1,
            $city,
            $province,
            $postal,
            $category,
            $patientsType,
            $weeklyExpecting,
            $numberOfPackages
        );
        wp_mail($notify, $subject, $body);
    }

    wp_safe_redirect(hcr_build_feedback_url('success', '', $successRedirect));
    exit;
}

add_action('admin_post_nopriv_hcr_winners_register', 'hcr_handle_submission');
add_action('admin_post_hcr_winners_register', 'hcr_handle_submission');
