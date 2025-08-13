<?php
/*
Plugin Name: CFDB7 Editor
Plugin URI: https://github.com/christophrus/cfdb7-editor
Description: Bearbeiten von CFDB7-Einträgen im Backend und Anzeige im Frontend über Shortcode.
Version: 1.0
Author: christophrus
License: GPLv2 or later
*/

if (!defined('ABSPATH')) exit;

global $wpdb;

// Backend-Menü
add_action('admin_menu', function() {
    add_menu_page(
        'CFDB7 Editor',
        'CFDB7 Editor',
        'manage_options',
        'cfdb7-editor',
        'cfdb7_editor_overview',
        'dashicons-edit',
        26
    );
});

// Backend: Übersicht
function cfdb7_editor_overview() {
    global $wpdb;
    $table = $wpdb->prefix . 'db7_forms';
    $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY form_date DESC LIMIT 100");

    echo '<div class="wrap"><h1>CFDB7 Editor</h1>';
    echo '<table class="widefat fixed striped"><thead><tr>';

    // Spaltenüberschriften dynamisch
    $fields = [];
    foreach ($rows as $row) {
        $data = maybe_unserialize($row->form_value);
        if (!is_array($data)) $data = json_decode($row->form_value, true);
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                if (!in_array($k, $fields, true)) {
                    $fields[] = $k;
                }
            }
        }
    }
    $fields[] = 'form_date';
    $fields[] = 'bezahlt';

    echo '<th>ID</th>';
    foreach ($fields as $f) {
        echo '<th>' . esc_html($f) . '</th>';
    }
    echo '<th>Aktion</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $data = maybe_unserialize($row->form_value);
        if (!is_array($data)) $data = json_decode($row->form_value, true);

        $bezahlt = get_post_meta($row->form_id, '_cfdb7_bezahlt', true);
        echo '<tr>';
        echo '<td>' . intval($row->form_id) . '</td>';
        foreach ($fields as $f) {
            if ($f === 'form_date') {
                echo '<td>' . esc_html(date('d.m.Y H:i', strtotime($row->form_date))) . '</td>';
            } elseif ($f === 'bezahlt') {
                echo '<td>' . ($bezahlt ? 'Ja' : 'Nein') . '</td>';
            } else {
                $val = isset($data[$f]) ? $data[$f] : '';
                if (is_array($val)) $val = implode(', ', $val);
                echo '<td>' . esc_html($val) . '</td>';
            }
        }
        echo '<td><a href="' . admin_url('admin.php?page=cfdb7-editor&edit=' . intval($row->form_id)) . '">Bearbeiten</a></td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

// Backend: Bearbeitungsformular
add_action('admin_init', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'cfdb7-editor' && isset($_GET['edit'])) {
        add_action('admin_notices', 'cfdb7_editor_edit_form');
    }
});

function cfdb7_editor_edit_form() {
    global $wpdb;
    $table = $wpdb->prefix . 'db7_forms';
    $id = intval($_GET['edit']);
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE form_id=%d", $id));
    if (!$row) {
        echo '<div class="error"><p>Eintrag nicht gefunden.</p></div>';
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('cfdb7_edit_entry')) {
        $data = [];
        foreach ($_POST['fields'] as $k => $v) {
            $data[$k] = sanitize_text_field($v);
        }
        $bezahlt = isset($_POST['bezahlt']) ? 1 : 0;

        $wpdb->update($table, [
            'form_value' => wp_json_encode($data),
        ], ['form_id' => $id]);

        update_post_meta($id, '_cfdb7_bezahlt', $bezahlt);

        echo '<div class="updated"><p>Eintrag aktualisiert.</p></div>';
    }

    $data = maybe_unserialize($row->form_value);
    if (!is_array($data)) $data = json_decode($row->form_value, true);
    if (!is_array($data)) $data = [];

    $bezahlt = get_post_meta($row->form_id, '_cfdb7_bezahlt', true);

    echo '<div class="wrap"><h2>Eintrag bearbeiten</h2>';
    echo '<form method="post">';
    wp_nonce_field('cfdb7_edit_entry');
    foreach ($data as $k => $v) {
        if (is_array($v)) $v = implode(', ', $v);
        echo '<p><label>' . esc_html($k) . ':<br>';
        echo '<input type="text" name="fields[' . esc_attr($k) . ']" value="' . esc_attr($v) . '" class="regular-text"></label></p>';
    }
    echo '<p><label><input type="checkbox" name="bezahlt" value="1"' . checked($bezahlt, 1, false) . '> Bezahlt</label></p>';
    echo '<p><input type="submit" class="button-primary" value="Speichern"></p>';
    echo '</form></div>';
}

// Frontend-Shortcode
function cfdb7_frontend_display($atts) {
    global $wpdb;

    $atts = shortcode_atts([
        'form_id' => 0,
        'limit'   => 10,
        'exclude' => '',
        'headers' => ''
    ], $atts);

    if (empty($atts['form_id'])) {
        return '<p>Bitte form_id angeben.</p>';
    }

    $exclude_fields = array_map('trim', explode(',', strtolower($atts['exclude'])));
    $custom_headers = array_map('trim', explode(',', $atts['headers']));

    $table_name = $wpdb->prefix . 'db7_forms';
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name 
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
        if (!is_array($data)) $data = json_decode($row->form_value, true);
        if (is_array($data)) {
            foreach ($data as $field => $value) {
                if ($field !== 'form_date' && !in_array($field, $all_fields, true) && !in_array(strtolower($field), $exclude_fields, true)) {
                    $all_fields[] = $field;
                }
            }
        }
    }

    $all_fields[] = 'form_date';
    $all_fields[] = 'bezahlt';

    $output  = '<table border="1" cellpadding="5" style="border-collapse:collapse;width:100%;">';
    $output .= '<thead><tr>';
    $output .= '<th>#</th>';

    foreach ($all_fields as $index => $field) {
        if ($field === 'form_date') {
            $header_text = !empty($custom_headers[$index]) ? $custom_headers[$index] : 'Datum';
        } elseif ($field === 'bezahlt') {
            $header_text = !empty($custom_headers[$index]) ? $custom_headers[$index] : 'Bezahlt';
        } else {
            $header_text = !empty($custom_headers[$index]) ? $custom_headers[$index] : $field;
        }
        $output .= '<th>' . esc_html($header_text) . '</th>';
    }

    $output .= '</tr></thead><tbody>';

    $counter = 1;
    foreach ($results as $row) {
        $data = maybe_unserialize($row->form_value);
        if (!is_array($data)) $data = json_decode($row->form_value, true);

        $bezahlt = get_post_meta($row->form_id, '_cfdb7_bezahlt', true);

        $output .= '<tr>';
        $output .= '<td>' . $counter . '</td>';
        $counter++;

        foreach ($all_fields as $field) {
            if ($field === 'form_date') {
                $date = strtotime($row->form_date);
                $value = date('d.m.Y H:i', $date);
            } elseif ($field === 'bezahlt') {
                $value = $bezahlt ? 'Ja' : 'Nein';
            } else {
                $value = isset($data[$field]) ? $data[$field] : '';
            }
            if (is_array($value)) $value = implode(', ', $value);
            $output .= '<td>' . esc_html($value) . '</td>';
        }
        $output .= '</tr>';
    }

    $output .= '</tbody></table>';

    return $output;
}
add_shortcode('cfdb7_data', 'cfdb7_frontend_display');
