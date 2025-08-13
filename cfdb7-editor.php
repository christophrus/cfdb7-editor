<?php
/*
Plugin Name: CFDB7 Editor & Frontend Viewer
Description: Bearbeiten und Anzeigen von CFDB7-Einträgen im Backend und Frontend.
Version: 1.1
Author: christophrus
*/

if (!defined('ABSPATH')) {
    exit;
}

/** =========================
 *  Hilfsfunktionen: Bezahlt
 *  ========================= */
function cfdb7_paid_option_key($entry_id) {
    return 'cfdb7_paid_' . intval($entry_id);
}
function cfdb7_get_paid_status($entry_id) {
    return (int) get_option(cfdb7_paid_option_key($entry_id), 0);
}
function cfdb7_set_paid_status($entry_id, $status) {
    return update_option(cfdb7_paid_option_key($entry_id), $status ? 1 : 0, false);
}

/** =========================
 *  Admin-Menü
 *  ========================= */
add_action('admin_menu', function () {
    add_menu_page(
        'CFDB7 Editor',
        'CFDB7 Editor',
        'manage_options',
        'cfdb7-editor',
        'cfdb7_editor_page',
        'dashicons-edit',
        26
    );
});

/** =========================
 *  Backend-Seite
 *  ========================= */
function cfdb7_editor_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Keine Berechtigung.'));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'db7_forms';

    echo '<div class="wrap"><h1>CFDB7 Editor</h1>';

    // Speichern nach Editieren
    if (isset($_POST['cfdb7_save_entry']) && isset($_GET['edit']) && check_admin_referer('cfdb7_edit_entry')) {
        $id = intval($_GET['edit']);
        $form_data = [];

        if (!empty($_POST['form_data']) && is_array($_POST['form_data'])) {
            foreach ($_POST['form_data'] as $key => $value) {
                $form_data[$key] = is_array($value)
                    ? implode(', ', array_map('sanitize_text_field', $value))
                    : sanitize_text_field($value);
            }
        }

        $paid_status = isset($_POST['bezahlt']) ? 1 : 0;

        // CFDB7-kompatibel speichern
        $updated = $wpdb->update(
            $table_name,
            ['form_value' => maybe_serialize($form_data)],
            ['form_id'    => $id],
            ['%s'],
            ['%d']
        );

        // "bezahlt" persistieren (Options-API pro form_id)
        cfdb7_set_paid_status($id, $paid_status);

        if ($updated !== false) {
            echo '<div class="updated"><p>Eintrag gespeichert.</p></div>';
        } else {
            echo '<div class="error"><p>Fehler beim Aktualisieren des Eintrags.</p></div>';
        }
    }

    // Editor-Ansicht
    if (isset($_GET['edit'])) {
        $id = intval($_GET['edit']);
        $entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE form_id = %d", $id));
        if (!$entry) {
            echo '<div class="error"><p>Eintrag nicht gefunden.</p></div></div>';
            return;
        }

        $form_data = maybe_unserialize($entry->form_value);
        if (!is_array($form_data)) {
            $form_data = json_decode($entry->form_value, true);
        }
        if (!is_array($form_data)) {
            echo '<div class="error"><p>Formulardaten konnten nicht geladen werden (ungültiges Format).</p></div></div>';
            return;
        }

        $bezahlt = cfdb7_get_paid_status($id);

        echo '<h2>Eintrag bearbeiten #'.esc_html($id).'</h2>';
        echo '<form method="post">';
        wp_nonce_field('cfdb7_edit_entry');

        foreach ($form_data as $field => $value) {
            $val = is_array($value) ? implode(', ', $value) : $value;
            echo '<p><label><strong>'.esc_html($field).':</strong><br>';
            echo '<input type="text" name="form_data['.esc_attr($field).']" value="'.esc_attr($val).'" style="width:100%;max-width:700px;"></label></p>';
        }

        echo '<p><label><input type="checkbox" name="bezahlt" value="1" '.checked($bezahlt, 1, false).'> Bezahlt</label></p>';
        submit_button('Speichern', 'primary', 'cfdb7_save_entry');

        echo '</form></div>';
        return;
    }

    // Übersicht (letzte 100 Einträge)
    $entries = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY form_date DESC LIMIT 100");

    if ($entries) {
        // Alle Feldnamen für Header sammeln
        $all_fields = [];
        foreach ($entries as $entry) {
            $data = maybe_unserialize($entry->form_value);
            if (!is_array($data)) {
                $data = json_decode($entry->form_value, true);
            }
            if (is_array($data)) {
                foreach ($data as $field => $value) {
                    if (!in_array($field, $all_fields, true)) {
                        $all_fields[] = $field;
                    }
                }
            }
        }

        echo '<h2>Einträge</h2>';
        echo '<table class="widefat fixed striped" style="width:100%;">';
        echo '<thead><tr>';
        echo '<th style="width:60px;">ID</th>';
        foreach ($all_fields as $field) {
            echo '<th>'.esc_html($field).'</th>';
        }
        echo '<th>Datum</th>';
        echo '<th>Bezahlt</th>';
        echo '<th style="width:110px;">Aktionen</th>';
        echo '</tr></thead><tbody>';

        foreach ($entries as $entry) {
            $data = maybe_unserialize($entry->form_value);
            if (!is_array($data)) {
                $data = json_decode($entry->form_value, true);
            }

            echo '<tr>';
            echo '<td>'.intval($entry->form_id).'</td>';

            foreach ($all_fields as $field) {
                $value = isset($data[$field]) ? $data[$field] : '';
                if (is_array($value)) $value = implode(', ', $value);
                echo '<td>'.esc_html($value).'</td>';
            }

            $ts = strtotime($entry->form_date);
            echo '<td>'.esc_html($ts ? date('d.m.Y H:i', $ts) : '').'</td>';

            $paid = cfdb7_get_paid_status($entry->form_id);
            $paid_class = $paid ? 'cfdb7-paid cfdb7-paid-yes' : 'cfdb7-paid cfdb7-paid-no';
            echo '<td class="'.$paid_class.'">';
            echo '<span class="cfdb7-toggle-paid" data-id="'.intval($entry->form_id).'" style="cursor:pointer;">'.($paid ? '✔️' : '❌').'</span>';
            echo '</td>';

            $edit_url = admin_url('admin.php?page=cfdb7-editor&edit=' . intval($entry->form_id));
            echo '<td><a href="'.esc_url($edit_url).'">Bearbeiten</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    } else {
        echo '<p>Keine Einträge gefunden.</p>';
    }

    echo '</div>';
}

/** =========================
 *  Frontend Shortcode
 *  ========================= */
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

    // Alle Feldnamen sammeln (ohne form_date), excludes berücksichtigen
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
    // Datum immer am Ende
    $all_fields[] = 'form_date';

    // Tabelle
    $output  = '<table border="1" cellpadding="5" style="border-collapse:collapse;width:100%;">';
    $output .= '<thead><tr>';
    $output .= '<th>#</th>';

    foreach ($all_fields as $idx => $field) {
        $header_text = ($field === 'form_date')
            ? (!empty($custom_headers[$idx]) ? $custom_headers[$idx] : 'Datum')
            : (!empty($custom_headers[$idx]) ? $custom_headers[$idx] : $field);
        $output .= '<th>'.esc_html($header_text).'</th>';
    }
    // Rechte Zusatzspalte: Bezahlt
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

        // Bezahlt aus Options-API
        $paid = cfdb7_get_paid_status($row->form_id);
        $output .= '<td>'.($paid ? '✔️' : '❌').'</td>';

        $output .= '</tr>';
    }

    $output .= '</tbody></table>';

    return $output;
}
add_shortcode('cfdb7_data', 'cfdb7_frontend_display');

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

add_action('admin_footer', function() {
    ?>
    <script>
    jQuery(document).ready(function($){
        $('.cfdb7-toggle-paid').on('click', function(){
            var $el = $(this);
            var id = $el.data('id');
            $el.css('opacity', '0.5');
            $.post(ajaxurl, {
                action: 'cfdb7_toggle_paid',
                id: id
            }, function(resp){
                $el.css('opacity', '1');
                if(resp.success) {
                    if(resp.data.paid) {
                        $el.html('✔️');
                        $el.closest('td').removeClass('cfdb7-paid-no').addClass('cfdb7-paid-yes');
                    } else {
                        $el.html('❌');
                        $el.closest('td').removeClass('cfdb7-paid-yes').addClass('cfdb7-paid-no');
                    }
                } else {
                    alert('Fehler: ' + resp.data);
                }
            });
        });
    });
    </script>
    <?php
});
