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

require_once __DIR__ . '/includes/functions-paid.php';
require_once __DIR__ . '/includes/admin-page.php';
require_once __DIR__ . '/includes/frontend-shortcode.php';
require_once __DIR__ . '/includes/ajax.php';

add_action('admin_enqueue_scripts', function() {
    wp_enqueue_style(
        'cfdb7-editor-css',
        plugin_dir_url(__FILE__) . 'cfdb7-editor.css',
        [],
        filemtime(__DIR__ . '/cfdb7-editor.css')
    );
    wp_enqueue_script(
        'cfdb7-editor-admin-js',
        plugin_dir_url(__FILE__) . 'assets/js/admin.js',
        ['jquery'],
        filemtime(__DIR__ . '/assets/js/admin.js'),
        true
    );
});

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'cfdb7-editor-css',
        plugin_dir_url(__FILE__) . 'cfdb7-editor.css',
        [],
        filemtime(__DIR__ . '/cfdb7-editor.css')
    );
});
