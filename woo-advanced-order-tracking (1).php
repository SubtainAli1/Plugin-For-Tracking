<?php
/*
Plugin Name: Woo Advanced Order Tracking
Description: Adds custom tracking fields, statuses, and a map for WooCommerce orders.
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly
// Register custom order statuses
function waot_register_custom_order_statuses() {
    register_post_status('wc-ordered', array(
        'label'                     => 'Ordered',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Ordered (%s)', 'Ordered (%s)')
    ));
    register_post_status('wc-dispatched', array(
        'label'                     => 'Dispatched',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Dispatched (%s)', 'Dispatched (%s)')
    ));
    register_post_status('wc-in-transit', array(
        'label'                     => 'In Transit',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('In Transit (%s)', 'In Transit (%s)')
    ));
    register_post_status('wc-delivered', array(
        'label'                     => 'Delivered',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Delivered (%s)', 'Delivered (%s)')
    ));
}
add_action('init', 'waot_register_custom_order_statuses');

// Add to WooCommerce dropdown
function waot_add_to_order_statuses($order_statuses) {
    $new_statuses = array();
    foreach ($order_statuses as $key => $status) {
        $new_statuses[$key] = $status;
        if ('wc-processing' === $key) {
            $new_statuses['wc-ordered'] = 'Ordered';
            $new_statuses['wc-dispatched'] = 'Dispatched';
            $new_statuses['wc-in-transit'] = 'In Transit';
            $new_statuses['wc-delivered'] = 'Delivered';
        }
    }
    return $new_statuses;
}
add_filter('wc_order_statuses', 'waot_add_to_order_statuses');
// Add custom tracking field in admin
add_action('woocommerce_admin_order_data_after_order_details', function($order) {
    woocommerce_wp_text_input(array(
        'id' => '_tracking_number',
        'label' => 'Tracking Number:',
        'wrapper_class' => 'form-field-wide',
        'value' => get_post_meta($order->get_id(), '_tracking_number', true),
        'description' => 'Enter tracking number or custom notes here.'
    ));
});

// Save tracking field
add_action('woocommerce_process_shop_order_meta', function($order_id) {
    if (isset($_POST['_tracking_number'])) {
        update_post_meta($order_id, '_tracking_number', sanitize_text_field($_POST['_tracking_number']));
    }
});
// Add settings page
add_action('admin_menu', function() {
    add_options_page(
        'Order Tracking Settings',
        'Order Tracking',
        'manage_options',
        'waot-settings',
        'waot_settings_page_html'
    );
});

function waot_settings_page_html() {
    if (isset($_POST['waot_api_key'])) {
        update_option('waot_api_key', sanitize_text_field($_POST['waot_api_key']));
        echo '<div class="updated"><p>API Key Saved!</p></div>';
    }

    $api_key = get_option('waot_api_key', '');
    ?>
    <div class="wrap">
        <h2>Woo Order Tracking Settings</h2>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>Google Maps API Key</th>
                    <td><input type="text" name="waot_api_key" value="<?php echo esc_attr($api_key); ?>" size="50"></td>
                </tr>
            </table>
            <p><input type="submit" class="button-primary" value="Save Changes"></p>
        </form>
    </div>
    <?php
}


// Shortcode to display tracking info
add_shortcode('order_tracking', function($atts) {
    ob_start();
    ?>
    <form method="get">
        <input type="text" name="track_order_id" placeholder="Enter Order ID" required>
        <button type="submit">Track</button>
    </form>
    <?php
    if (!empty($_GET['track_order_id'])) {
        $order_id = intval($_GET['track_order_id']);
        $order = wc_get_order($order_id);
        if ($order) {
            $status = wc_get_order_status_name($order->get_status());
            $tracking_number = get_post_meta($order_id, '_tracking_number', true);

            echo "<h3>Order #$order_id - Status: $status</h3>";
            echo "<p>Tracking Number: $tracking_number</p>";

            // Simulated map coordinates based on status
            $coords = array(
                'wc-ordered' => ['lat' => 40.7128, 'lng' => -74.0060],
                'wc-dispatched' => ['lat' => 41.0, 'lng' => -73.9],
                'wc-in-transit' => ['lat' => 42.0, 'lng' => -73.5],
                'wc-delivered' => ['lat' => 43.0, 'lng' => -73.0],
            );
            $location = $coords[$order->get_status()] ?? $coords['wc-ordered'];
            ?>
            <div id="order-map" style="width:100%;height:400px;"></div>
           <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<div id="order-map" style="width:100%;height:400px;"></div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var map = L.map('order-map').setView([<?php echo $location['lat']; ?>, <?php echo $location['lng']; ?>], 8);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap Contributors'
    }).addTo(map);

    L.marker([<?php echo $location['lat']; ?>, <?php echo $location['lng']; ?>]).addTo(map)
        .bindPopup("Package Current Location")
        .openPopup();
});
</script>


            <?php
        } else {
            echo "<p>Order not found.</p>";
        }
    }
    return ob_get_clean();
});
