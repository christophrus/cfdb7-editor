<?php
// Shortcode & Frontend-Ausgabe

if (!defined('ABSPATH')) exit;

function cfdb7_frontend_display($atts) {
    global $wpdb;

    $atts = shortcode_atts([
        'form_id' => 0,
        'limit'   => 10,
        'exclude' => '',
        'headers' => ''
    ], $atts, 'cfdb7_data');

    if (empty($atts['form_id'])) {
        return '<p>Bitte form_id angeben.</p>';
    }

    $exclude_fields = array_filter(array_map('trim', explode(',', strtolower($atts['exclude']))));
    $custom_headers = array_map('trim', explode(',', $atts['headers']));

    $table_name = $wpdb->prefix . 'db7_forms';
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table_name}
             WHERE form_post_id = %d
             ORDER BY form_date ASC
             LIMIT %d",
            intval($atts['form_id']),
            intval($atts['limit'])
        )
    );

    if (!$results) {
        return '<p>Keine Daten gefunden.</p>';
    }

    $all_fields = [];
    foreach ($results as $row) {
        $data = maybe_unserialize($row->form_value);
        if (!is_array($data)) {
            $data = json_decode($row->form_value, true);
        }
        if (is_array($data)) {
            foreach ($data as $field => $value) {
                if (
                    $field !== 'form_date' &&
                    !in_array($field, $all_fields, true) &&
                    !in_array(strtolower($field), $exclude_fields, true)
                ) {
                    $all_fields[] = $field;
                }
            }
        }
    }
    $all_fields[] = 'form_date';

    $output  = '<table border="1" cellpadding="5" style="border-collapse:collapse;width:100%;">';
    $output .= '<thead><tr>';
    $output .= '<th>#</th>';

    foreach ($all_fields as $idx => $field) {
        $header_text = ($field === 'form_date')
            ? (!empty($custom_headers[$idx]) ? $custom_headers[$idx] : 'Datum')
            : (!empty($custom_headers[$idx]) ? $custom_headers[$idx] : $field);
        $output .= '<th>'.esc_html($header_text).'</th>';
    }
    $output .= '<th>Bezahlt</th>';
    $output .= '</tr></thead><tbody>';

    $count = 1;
    foreach ($results as $row) {
        $data = maybe_unserialize($row->form_value);
        if (!is_array($data)) {
            $data = json_decode($row->form_value, true);
        }

        $output .= '<tr>';
        $output .= '<td>'.$count.'</td>';
        $count++;

        foreach ($all_fields as $field) {
            if ($field === 'form_date') {
                $ts = strtotime($row->form_date);
                $value = $ts ? date('d.m.Y H:i', $ts) : '';
            } else {
                $value = isset($data[$field]) ? $data[$field] : '';
            }
            if (is_array($value)) $value = implode(', ', $value);
            $output .= '<td>'.esc_html($value).'</td>';
        }

        $paid = cfdb7_get_paid_status($row->form_id);
        $output .= '<td>'.($paid ? '&#10004' : '&#10008;').'</td>';

        $output .= '</tr>';
    }

    $output .= '</tbody></table>';

    return $output;
}
add_shortcode('cfdb7_data', 'cfdb7_frontend_display');