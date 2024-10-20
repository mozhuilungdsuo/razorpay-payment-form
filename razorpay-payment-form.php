<?php

/**
 * Plugin Name: Razorpay Payment Form
 * Description: A plugin to collect custom details, make payments using Razorpay, and save the payment details in the database.
 * Version: 1.1
 * Author: Lungdsuo Mozhui
 */

if (!defined('ABSPATH')) {
    exit;
}

// Function to create the database table on plugin activation
function razorpay_create_payment_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'razorpay_payments';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        amount decimal(10,2) NOT NULL,
        payment_id varchar(255) NOT NULL,
        payment_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'razorpay_create_payment_table');

// Add Razorpay key setting in the options table
function razorpay_register_settings()
{
    register_setting('razorpay_options_group', 'razorpay_key_id', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '',
    ]);
}

add_action('admin_init', 'razorpay_register_settings');

// Add settings page to input Razorpay Key
function razorpay_add_settings_page()
{
    add_options_page(
        'Razorpay Settings',        // Page title
        'Razorpay Settings',        // Menu title
        'manage_options',           // Capability
        'razorpay-settings',        // Menu slug
        'razorpay_settings_page'    // Callback function
    );
}

add_action('admin_menu', 'razorpay_add_settings_page');

// Callback function to render the settings page
function razorpay_settings_page()
{
?>
    <div class="wrap">
        <h1>Razorpay Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('razorpay_options_group');
            do_settings_sections('razorpay_options_group');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Razorpay Key ID</th>
                    <td><input type="text" name="razorpay_key_id" value="<?php echo esc_attr(get_option('razorpay_key_id')); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
<?php
}

// Enqueue Razorpay SDK and custom JavaScript
function razorpay_enqueue_scripts()
{
    wp_enqueue_script('razorpay-checkout', 'https://checkout.razorpay.com/v1/checkout.js', [], null, true);
    wp_enqueue_script('sweet-alert', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);
    wp_enqueue_script('razorpay-custom', plugin_dir_url(__FILE__) . 'razorpay-custom.js', ['jquery'], null, true);
    wp_enqueue_style('razorpay-custom-css', plugin_dir_url(__FILE__) . 'razorpay-custom.css');
    // Pass the Razorpay key and Ajax URL to JavaScript
    wp_localize_script('razorpay-custom', 'ajax_object', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'razorpay_key' => get_option('razorpay_key_id') // Fetch Razorpay Key from the database
    ]);
}

add_action('wp_enqueue_scripts', 'razorpay_enqueue_scripts');

// Shortcode to display the payment form
function razorpay_payment_form()
{
    ob_start(); ?>

    <form id="razorpayPaymentForm">
        <label for="name">Name:</label>
        <input type="text" id="name" name="name" required><br><br>

        <label for="amount">Amount:</label>
        <input type="number" id="amount" name="amount" required><br><br>

        <button type="button" id="payButton">Pay with Razorpay</button>
    </form>

    <div id="razorpayResponse"></div>

<?php return ob_get_clean();
}

add_shortcode('razorpay_form', 'razorpay_payment_form');

// AJAX handler for Razorpay payment success and saving data to the database
function razorpay_payment_success()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'razorpay_payments';

    $payment_id = sanitize_text_field($_POST['payment_id']);
    $name = sanitize_text_field($_POST['name']);
    $amount = sanitize_text_field($_POST['amount']);

    // Insert payment details into the database
    $wpdb->insert(
        $table_name,
        [
            'name' => $name,
            'amount' => $amount,
            'payment_id' => $payment_id,
        ]
    );

    wp_send_json_success("Payment successful! Your Payment ID is: $payment_id");
}

add_action('wp_ajax_nopriv_razorpay_payment_success', 'razorpay_payment_success');
add_action('wp_ajax_razorpay_payment_success', 'razorpay_payment_success');

// Add an admin menu to view payments
function razorpay_add_admin_menu()
{
    add_menu_page(
        'Razorpay Payments',   // Page title
        'Razorpay Payments',   // Menu title
        'manage_options',      // Capability
        'razorpay-payments',   // Menu slug
        'razorpay_display_payments' // Callback function
    );
}

add_action('admin_menu', 'razorpay_add_admin_menu');

// Callback function to display payments with pagination and search
function razorpay_display_payments()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'razorpay_payments';

    // Search functionality
    $search_query = isset($_POST['payment_search']) ? sanitize_text_field($_POST['payment_search']) : '';

    // Pagination setup
    $limit = 10; // Number of rows per page
    $page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
    $offset = ($page - 1) * $limit;

    // Query for total records (for pagination)
    $total_query = "SELECT COUNT(*) FROM $table_name WHERE name LIKE %s OR payment_id LIKE %s";
    $total = $wpdb->get_var($wpdb->prepare($total_query, '%' . $search_query . '%', '%' . $search_query . '%'));

    // Fetch the data with search and pagination
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE name LIKE %s OR payment_id LIKE %s ORDER BY payment_date DESC LIMIT %d OFFSET %d",
        '%' . $search_query . '%',
        '%' . $search_query . '%',
        $limit,
        $offset
    ));

    // Display the search form using WordPress styling
    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">Razorpay Payments</h1>';
    echo '<form method="POST" class="search-form wp-clearfix">';
    echo '<p class="search-box">';
    echo '<label class="screen-reader-text" for="payment_search">Search Payments:</label>';
    echo '<input type="search" id="payment_search" name="payment_search" value="' . esc_attr($search_query) . '" placeholder="Search payments..." />';
    echo '<input type="submit" id="search-submit" class="button" value="Search">';
    echo '</p>';
    echo '</form>';

    // Display the table using WordPress admin table styling
    if ($results) {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th scope="col" class="manage-column">ID</th>';
        echo '<th scope="col" class="manage-column">Name</th>';
        echo '<th scope="col" class="manage-column">Amount</th>';
        echo '<th scope="col" class="manage-column">Payment ID</th>';
        echo '<th scope="col" class="manage-column">Date</th>';
        echo '</tr></thead>';

        echo '<tbody>';
        foreach ($results as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row->id) . '</td>';
            echo '<td>' . esc_html($row->name) . '</td>';
            echo '<td>' . esc_html($row->amount) . '</td>';
            echo '<td>' . esc_html($row->payment_id) . '</td>';
            echo '<td>' . esc_html($row->payment_date) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';

        // Pagination
        $total_pages = ceil($total / $limit);
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo paginate_links([
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => __('&laquo;'),
                'next_text' => __('&raquo;'),
                'total' => $total_pages,
                'current' => $page,
            ]);
            echo '</div></div>';
        }
    } else {
        echo '<p>No payments found.</p>';
    }

    echo '</div>';
}
