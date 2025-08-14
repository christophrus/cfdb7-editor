<?php
// AJAX-Handler für "Bezahlt"-Toggle

if (!defined('ABSPATH')) exit;

add_action('wp_ajax_cfdb7_toggle_paid', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Keine Berechtigung');
    }
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        wp_send_json_error('Ungültige ID');
    }
    $current = cfdb7_get_paid_status($id);
    $new = $current ? 0 : 1;
    cfdb7_set_paid_status($id, $new);
    wp_send_json_success(['paid' => $new]);
});