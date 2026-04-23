<?php
/**
 * Admin list table for HC registration submissions (paginated).
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class HCR_Submissions_List_Table extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct(
            [
                'singular' => 'submission',
                'plural' => 'submissions',
                'ajax' => false,
            ]
        );
    }

    public function get_columns()
    {
        return [
            'hcr_no' => __('No.', 'pambers-hc-registration'),
            'created_at' => __('Submitted', 'pambers-hc-registration'),
            'organization_name' => __('Organization', 'pambers-hc-registration'),
            'contact' => __('Contact', 'pambers-hc-registration'),
            'email' => __('Email', 'pambers-hc-registration'),
            'city' => __('City', 'pambers-hc-registration'),
            'province' => __('Province', 'pambers-hc-registration'),
            'category' => __('Category', 'pambers-hc-registration'),
            'patients_type' => __('Patients', 'pambers-hc-registration'),
            'number_of_packages' => __('Packages', 'pambers-hc-registration'),
            'hcr_actions' => __('Actions', 'pambers-hc-registration'),
        ];
    }

    protected function get_sortable_columns()
    {
        return [
            'created_at' => ['created_at', true],
            'organization_name' => ['organization_name', false],
            'email' => ['email', false],
            'city' => ['city', false],
        ];
    }

    public function no_items()
    {
        esc_html_e('No submissions yet.', 'pambers-hc-registration');
    }

    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        global $wpdb;

        $table = hcr_get_submissions_table_name();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            $this->items = [];
            $this->set_pagination_args(
                [
                    'total_items' => 0,
                    'per_page' => 20,
                    'total_pages' => 0,
                ]
            );

            return;
        }

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $orderby = isset($_REQUEST['orderby']) ? sanitize_key(wp_unslash($_REQUEST['orderby'])) : 'created_at';
        $allowed = ['created_at', 'organization_name', 'email', 'city', 'province', 'category'];
        if (!in_array($orderby, $allowed, true)) {
            $orderby = 'created_at';
        }

        $order = isset($_REQUEST['order']) ? strtoupper(sanitize_text_field(wp_unslash($_REQUEST['order']))) : 'DESC';
        if ($order !== 'ASC' && $order !== 'DESC') {
            $order = 'DESC';
        }

        $total_items = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");

        $per_page = absint($per_page);
        $offset = absint($offset);
        $sql = "SELECT * FROM `{$table}` ORDER BY `{$orderby}` {$order}, `id` DESC LIMIT {$per_page} OFFSET {$offset}";
        $this->items = $wpdb->get_results($sql);

        if (is_array($this->items)) {
            foreach ($this->items as $i => $row) {
                $row->hcr_row_no = $offset + (int) $i + 1;
            }
        }

        $this->set_pagination_args(
            [
                'total_items' => $total_items,
                'per_page' => $per_page,
                'total_pages' => (int) ceil($total_items / $per_page),
            ]
        );
    }

    protected function column_default($item, $column_name)
    {
        if (!isset($item->$column_name)) {
            return '';
        }

        return esc_html((string) $item->$column_name);
    }

    protected function column_hcr_no($item)
    {
        return isset($item->hcr_row_no) ? sprintf('<strong>%d</strong>', (int) $item->hcr_row_no) : '';
    }

    protected function column_hcr_actions($item)
    {
        $id = (int) $item->id;
        $no = isset($item->hcr_row_no) ? (int) $item->hcr_row_no : 0;
        $viewUrl = add_query_arg(
            array_filter(
                [
                    'page' => HCR_ADMIN_PAGE_SLUG,
                    'view' => $id,
                    'hcr_no' => $no > 0 ? $no : null,
                ]
            ),
            admin_url('admin.php')
        );
        $editUrl = add_query_arg(
            array_filter(
                [
                    'page' => HCR_ADMIN_PAGE_SLUG,
                    'action' => 'edit',
                    'id' => $id,
                    'hcr_no' => $no > 0 ? $no : null,
                ]
            ),
            admin_url('admin.php')
        );
        $deleteUrl = wp_nonce_url(
            add_query_arg(
                [
                    'action' => 'hcr_delete_submission',
                    'id' => $id,
                ],
                admin_url('admin-post.php')
            ),
            'hcr_delete_submission_' . $id
        );
        $confirm = esc_js(__('Delete this submission? This cannot be undone.', 'pambers-hc-registration'));

        return sprintf(
            '<a class="button button-small" href="%1$s">%2$s</a> <a class="button button-small" href="%3$s">%4$s</a> <a class="button button-small button-link-delete" href="%5$s" onclick="return window.confirm(%6$s);">%7$s</a>',
            esc_url($viewUrl),
            esc_html__('View', 'pambers-hc-registration'),
            esc_url($editUrl),
            esc_html__('Edit', 'pambers-hc-registration'),
            esc_url($deleteUrl),
            wp_json_encode($confirm),
            esc_html__('Delete', 'pambers-hc-registration')
        );
    }

    protected function column_created_at($item)
    {
        if (empty($item->created_at)) {
            return '';
        }

        $ts = strtotime((string) $item->created_at);

        return esc_html($ts ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $ts) : (string) $item->created_at);
    }

    protected function column_contact($item)
    {
        $first = isset($item->contact_first_name) ? (string) $item->contact_first_name : '';
        $last = isset($item->contact_last_name) ? (string) $item->contact_last_name : '';
        $full = trim($first . ' ' . $last);

        return $full !== '' ? esc_html($full) : '—';
    }

    protected function column_email($item)
    {
        $email = isset($item->email) ? (string) $item->email : '';

        return $email !== '' ? '<a href="' . esc_url('mailto:' . $email) . '">' . esc_html($email) . '</a>' : '—';
    }
}
