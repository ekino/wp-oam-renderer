<?php
/*
Plugin Name: wp-oam-renderer (Wordpress OAM Renderer)
Plugin URI: https://github.com/ekino/wp-oam-renderer
Description: Adds .oam / Adobe Edge Animate support in Media Library + front-office rendering using a dedicated shortcode
Authors: Thomas VIAL, Loïc CALVY
Company:  Ekino
Author URI: http://www.ekino.com
Version: 0.1

TODO:
- move from exec("unzip") to a cross platform solution
- add OAM icon in MediaLib (need wp_get_attachment_image hook)
- add OAM preview in MediaLib
- refator code...
*/


/*
 * Allow upload for .oam files
 */

add_filter('upload_mimes', 'addUploadMimes');
function addUploadMimes($mimes) {
  $mimes = array_merge($mimes, array(
      'oam' => 'application/octet-stream'
  ));
  return $mimes;
}

/*
 * Set icon to OAM files
 * Hook is missing in wordpress core... waiting for v3.6?
 * This part is Work In Progress
 */

// add_filter('wp_get_attachment_image', 'oam_icon_change');
// function oam_icon_change($post) {
//   $file = get_attached_file($post->ID);
//   $ext = pathinfo($file, PATHINFO_EXTENSION);
//   var_dump($post->ID);
//   if ($ext == 'oam') {
//     $icon = str_replace(get_bloginfo('wpurl').'/wp-includes/images/crystal/', WP_CONTENT_URL . '/plugins/wordpress-oam-renderer/', $icon);
//   }
//   return $icon;
// }


/*
 * Post-upload process for OAM Files
 */

add_action('add_attachment','oamPostProcessor');
function oamPostProcessor($post_ID) {
  $file = get_attached_file($post_ID);
  $ext = pathinfo($file, PATHINFO_EXTENSION);

  // process only oam files
  if ($ext == 'oam') {

    // settings some variables
    $filename = pathinfo($file, PATHINFO_BASENAME);
    $meta     = wp_get_attachment_metadata($file);
    $dir      = pathinfo($file, PATHINFO_DIRNAME);
    $out      = basename($filename, ".oam");

    // unzip file in the same directory
    exec("unzip $file -d $dir/$out", $result, $returnval);

    // getting config.xml
    $xml_config   = simplexml_load_file($dir.'/'.$out.'/config.xml');  
    $config_path  = $xml_config->xpath('//config/oamfile/@src');

    // getting edge assets XML to get icon src
    $xml_edge = simplexml_load_file($dir.'/'.$out.'/'. $config_path[0]);
    $xml_edge->registerXPathNamespace("edge", "http://openajax.org/metadata");
    $icon = $xml_edge->xpath('//edge:icon/@src');
    $w    = $xml_edge->xpath('//edge:icon/@width');
    $h    = $xml_edge->xpath('//edge:icon/@height');

    // adding meta to the oam file
    add_post_meta($post_ID, 'width', (int) $w[0]); 
    add_post_meta($post_ID, 'height', (int) $h[0]);

  } 
}


/*
 * Attachement deletion
 */

add_action('delete_attachment','oamFolderRemover');
function oamFolderRemover($post_ID) {
  $file = get_attached_file($post_ID);
  $ext = pathinfo($file, PATHINFO_EXTENSION);

  // process only oam files
  if ($ext == 'oam') {
    // settings some variables
    $filename = pathinfo($file, PATHINFO_BASENAME);
    $meta     = wp_get_attachment_metadata($file);
    $dir      = pathinfo($file, PATHINFO_DIRNAME);
    $out      = basename($filename, ".oam");

    // remove OAM unzipped folder
    deleteDirectory($dir."/".$out);

    // post-meta removed automatically by wordpress, nothing to do.

  }
}


/*
 * Shortcode for OAM rendering
 */

add_shortcode('oam', 'shortcode_oam');
function shortcode_oam($atts, $content = null) {
  // post using this animation
  global $post;
  $post_date  = $post->post_date;
  // id attribute is required
  if ($atts['id']) {
    // default $atts values
    $atts       = shortcode_atts(array(
      'id'      => '',
      'width'   => 960,
    ), $atts);
    // animation metadata
    $attach     = get_post($atts['id']); 
    $meta       = get_post_meta($atts['id']);
    $pathinfo   = pathinfo($attach->guid);
    $item       = $pathinfo['filename'];
    $path       = $pathinfo['dirname'];
    // calculate proportional height
    $height     = (int) $atts['width'] * (int) $meta['height'][0] / (int) $meta['width'][0];
    // return the ouput
    return '<iframe src="'.$path.'/'.$item.'/Assets/'.$attach->post_title.'.html" width="'.$atts['width'].'" height="'.$height.'"></iframe>';
  } else {
    return '[OAM ERROR => An id must be provided]';
  }
}


/*
 * Server-side OAM rendering with an iFrame 
 */

add_filter( 'the_content', 'oamRenderer' );
function oamRenderer($content) {
  $pattern  = '~(<a href="([^"]*)/wp-content/([^"]*).oam">)([^<]*)(</a>)~';
  $domain   = get_bloginfo('url');
  $result   = preg_replace($pattern, '<iframe src="'.$domain.'/wp-content/$3/Assets/$4.html" class="wp-oam-renderer" data-oam="true" data-ratio=""></iframe>', $content);
  $result  .= "<script>";
  $result  .= "$.each($('.wp-oam-renderer'), function() { $(this).width($('#content').width()) });";
  $result  .= "</script>";
  return $result;
}

/*
 * Remove recursively a directory
 */

function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!deleteDirectory($dir.DIRECTORY_SEPARATOR.$item)) return false;
    }
    return rmdir($dir);
}









