<?php
/*
Plugin Name: WP OAM Renderer
Plugin URI: https://github.com/ekino/wp-oam-renderer
Description: Adds .oam / Adobe Edge Animate support in Media Library + front-office rendering using a dedicated shortcode
Author: Ekino
Author URI: http://www.ekino.com
Version: 1.1
*/

require_once 'core.php';
require_once 'functions.php';

/**
 * Allows upload for .oam files
 */
add_filter('upload_mimes', 'wp_oam_renderer_upload_mimes');

/**
 * Displays Poster file (icon) from OAM files
 * Only works in "Media > Library", not in "Post > Edit > Add Media"
 */
add_filter('wp_get_attachment_image_attributes', 'wp_oam_renderer_get_attachment_images_attributes', 10, 2);

/**
 * Post-upload process for OAM Files
 */
add_action('add_attachment', 'wp_oam_renderer_add_attachment');

/**
 * Attachment deletion
 */
add_action('delete_attachment', 'wp_oam_renderer_delete_attachment');

/**
 * Short code for OAM rendering
 */
add_shortcode('oam', 'wp_oam_renderer_oam_short_code');

/**
 * Displays HTML render code in "Insert media" window on post edit
 */
add_filter('attachment_fields_to_edit', 'wp_oam_renderer_edit_fields', 10, 2);

/**
 * Displays icon in "Insert media" post edition popin
 */
add_filter('wp_prepare_attachment_for_js', 'wp_oam_renderer_prepare_attachment_for_js', 10, 1);
