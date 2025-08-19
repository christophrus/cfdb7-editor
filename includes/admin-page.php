<?php
// Backend-Seite & Admin-Menü

if (!defined('ABSPATH')) exit;

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

function cfdb7_editor_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Keine Berechtigung.'));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'db7_forms';

    echo '<div class="wrap"><h1>CFDB7 Editor</h1>';

    // Zwischenschritt: Formular-IDs auflisten
    $form_ids = $wpdb->get_col("SELECT DISTINCT form_post_id FROM {$table_name} ORDER BY form_post_id ASC");
    $selected_form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
    echo '<form method="get" action="">';
    echo '<input type="hidden" name="page" value="cfdb7-editor">';
    echo '<label for="cfdb7-form-id"><strong>Formular wählen:</strong></label> ';
    echo '<select name="form_id" id="cfdb7-form-id" style="min-width:220px;">';
    echo '<option value="">-- Bitte wählen --</option>';
    foreach ($form_ids as $fid) {
        // Formularname aus wp_posts holen
        $form_post = get_post($fid);
        $form_title = $form_post ? $form_post->post_title : '';
        echo '<option value="'.intval($fid).'"'.($selected_form_id == $fid ? ' selected' : '').'>Formular-ID: '.intval($fid).' '.($form_title ? '– '.esc_html($form_title) : '').'</option>';
    }
    echo '</select> ';
    echo '<button type="submit" class="button">Anzeigen</button>';
    echo '</form>';

    // Wenn keine Formular-ID gewählt, Übersicht beenden
    if (!$selected_form_id) {
        echo '<p>Bitte zuerst ein Formular auswählen.</p></div>';
        return;
    }

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

        $updated = $wpdb->update(
            $table_name,
            ['form_value' => maybe_serialize($form_data)],
            ['form_id'    => $id],
            ['%s'],
            ['%d']
        );

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

    // Übersicht (letzte 100 Einträge für gewählte Formular-ID)
    $entries = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE form_post_id = %d ORDER BY form_date DESC LIMIT 100",
            $selected_form_id
        )
    );

    if ($entries) {
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
        echo '<form method="post" id="cfdb7-bulk-form">';
        wp_nonce_field('cfdb7_bulk_action');
        echo '<div style="margin-bottom:10px;">';
        echo '<select name="bulk_action" style="min-width:120px;">';
        echo '<option value="">Aktion wählen</option>';
        echo '<option value="delete">Löschen</option>';
        echo '</select> ';
        echo '<button type="submit" class="button">Ausführen</button>';
        echo '</div>';

        echo '<table class="widefat fixed striped" style="width:100%;">';
        echo '<thead><tr>';
        echo '<th style="width:30px;"><input type="checkbox" id="cfdb7-select-all"></th>';
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
            echo '<td><input type="checkbox" name="bulk_ids[]" value="'.intval($entry->form_id).'"></td>';
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
            echo '<button type="button" class="cfdb7-toggle-paid" data-id="'.intval($entry->form_id).'" title="Klick zum Umschalten">'.($paid ? '&#10004;' : '&#10008;').'</button>';
            echo '</td>';

            $edit_url = admin_url('admin.php?page=cfdb7-editor&form_id=' . intval($selected_form_id) . '&edit=' . intval($entry->form_id));
            echo '<td><a href="'.esc_url($edit_url).'">Bearbeiten</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</form>';
    } else {
        echo '<p>Keine Einträge gefunden.</p>';
    }

    if (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'delete' && !empty($_POST['bulk_ids']) && is_array($_POST['bulk_ids'])) {
        check_admin_referer('cfdb7_bulk_action');
        global $wpdb;
        $table_name = $wpdb->prefix . 'db7_forms';
        $ids = array_map('intval', $_POST['bulk_ids']);
        foreach ($ids as $id) {
            $wpdb->delete($table_name, ['form_id' => $id], ['%d']);
            delete_option(cfdb7_paid_option_key($id));
        }
        echo '<div class="updated"><p>'.count($ids).' Einträge gelöscht.</p></div>';
    }

    echo '</div>';
}