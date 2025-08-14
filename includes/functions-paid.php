<?php
// Hilfsfunktionen für "Bezahlt"-Status

if (!defined('ABSPATH')) exit;

function cfdb7_paid_option_key($entry_id) {
    return 'cfdb7_paid_' . intval($entry_id);
}
function cfdb7_get_paid_status($entry_id) {
    return (int) get_option(cfdb7_paid_option_key($entry_id), 0);
}
function cfdb7_set_paid_status($entry_id, $status) {
    return update_option(cfdb7_paid_option_key($entry_id), $status ? 1 : 0, false);
}