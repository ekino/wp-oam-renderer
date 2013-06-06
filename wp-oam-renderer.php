<?php
/*
Plugin Name: WP OAM Renderer
Plugin URI: https://github.com/ekino/wp-oam-renderer
Description: Adds .oam / Adobe Edge Animate support in Media Library + front-office rendering using a dedicated shortcode
Author: Ekino
Author URI: http://www.ekino.com
Version: 0.3

TODO:
- refator code...
*/

/*
 * Allow upload for .oam files
 */

add_filter('upload_mimes', 'wpoamr_upload_mimes');
function wpoamr_upload_mimes($mimes)
{
  $mimes = array_merge($mimes, array(
      'oam' => 'application/octet-stream'
  ));

  return $mimes;
}

/*
 * Displays icon from 'Poster.png' to OAM files
 * Only works in "Media > Library", not in "Post > Edit > Add Media"
 */

add_filter('wp_get_attachment_image_attributes', 'wpoamr_wp_get_attachment_image_attributes', 10, 2);
function wpoamr_wp_get_attachment_image_attributes($attr, $attachment = null)
{
  $ext        = pathinfo($attachment->guid, PATHINFO_EXTENSION);
  $pathinfo   = pathinfo($attachment->guid);
  $item       = $pathinfo['filename'];
  $path       = $pathinfo['dirname'];
  if ($ext == 'oam') {
    $attr['src'] = $path.'/'.$item.'/Assets/images/Poster.png';
  }

  return $attr;
}

/*
 * Post-upload process for OAM Files
 */

add_action('add_attachment','wpoamr_add_attachment');
function wpoamr_add_attachment($post_ID)
{
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
    // source: http://stackoverflow.com/questions/8889025/unzip-a-file-with-php
    $zip = new ZipArchive;
    $res = $zip->open($file);
    if ($res === TRUE) {
      // extract it to the path we determined above
      $zip->extractTo("$dir/$out");
      $zip->close();
    } else {
      echo "ERROR: can't open $file";
    }

    // getting config.xml
    $xml_config   = simplexml_load_file($dir.'/'.$out.'/config.xml');
    $config_path  = $xml_config->xpath('//config/oamfile/@src');

    // getting edge assets XML to get icon src
    $xml_edge = simplexml_load_file($dir.'/'.$out.'/'. $config_path[0]);
    $xml_edge->registerXPathNamespace("edge", "http://openajax.org/metadata");
    $icon = $xml_edge->xpath('//edge:icon/@src');
    $w    = $xml_edge->xpath('//edge:icon/@width');
    $h    = $xml_edge->xpath('//edge:icon/@height');
    $html = (string) $xml_edge->require['src'];

    // adding meta to the oam file
    if (isset($w[0])) {
        add_post_meta($post_ID, 'width', (int) $w[0]);
    }
    if (isset($h[0])) {
        add_post_meta($post_ID, 'height', (int) $h[0]);
    }
    add_post_meta($post_ID, 'htmlPath', $html);
  }
}

/*
 * Attachement deletion
 */
add_action('delete_attachment','wpoamr_delete_attachment');
function wpoamr_delete_attachment($post_ID)
{
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
add_shortcode('oam', 'wpoamr_shortcode_oam');
function wpoamr_shortcode_oam($atts, $content = null)
{
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

    // Respect height parameters
    if ($atts['height']) {
        $height = $atts['height'];
    } else {
        // calculate proportional height
        $height = (int) $atts['width'] * (int) $meta['height'][0] / (int) $meta['width'][0];
    }

    // return the ouput
    return '<img src="'.$path.'/'.$item.'/Assets/images/Poster.png" style="margin-left:-'.((int) $meta['width'][0]/2).'px; margin-top:-'.((int) $meta['height'][0]/2).'px;"/>'
            .'<iframe src="'.$path.'/'.$item.'/Assets/'.$meta['htmlPath'][0].'" width="'.$atts['width'].'" height="'.$height.'"></iframe>';
  } else {
    return '[OAM ERROR => An id must be provided]';
  }
}

add_shortcode('oamjs', 'wpoamr_shortcode_oamjs');
function wpoamr_shortcode_oamjs($atts, $content = null)
{
    // post using this animation
    global $post;
    $post_date  = $post->post_date;
    // id attribute is required
    if ($atts['idfull'] || $atts['idsmall']) {
        // default $atts values
        $atts       = shortcode_atts(array(
            'idfull'      => '',
            'idsmall'      => '',
            'width'   => 960,
            'height'  => '',
        ), $atts);

        // animation 1 metadata
        $attach1             = get_post($atts['idfull']);
        $meta1               = get_post_meta($atts['idfull']);
        $pathinfo1           = pathinfo($attach1->guid);
        $item1               = $pathinfo1['filename'];
        $baseUrl1            = $pathinfo1['dirname'];
        $basePath1           = pathinfo(get_attached_file($attach1->ID), PATHINFO_DIRNAME);
        $posterFileName1     = getPosterFileName($basePath1.'/'.$item1.'/Assets/images/');

        // animation 2 metadata
        $attach2             = get_post($atts['idsmall']);
        $meta2               = get_post_meta($atts['idsmall']);
        $pathinfo2           = pathinfo($attach2->guid);
        $item2               = $pathinfo2['filename'];
        $baseUrl2            = $pathinfo2['dirname'];
        $basePath2           = pathinfo(get_attached_file($attach2->ID), PATHINFO_DIRNAME);
        $posterFileName2     = getPosterFileName($basePath2.'/'.$item2.'/Assets/images/');

        // Respect height parameters
        if ($atts['height']) {
            $height = $atts['height'];
        } else {
            // calculate proportional height
            $height     = (int) $atts['width'] * (int) $meta['height'][0] / (int) $meta['width'][0];
        }

        $html1Path = $meta1['htmlPath'][0];
        $html2Path = $attach2->post_title.'.html';

        $toPrint =     '<script type="text/javascript" class="oamjs-'. $atts['idfull'] .'">'.
                        'if (!window.oamjs) {'.
                            'window.oamjs = [];'.
                        '}'.
                        'window.oamjs.push({'.
                            'normal : {'.
                                'id :"'. $atts['idfull'] .'",'.
                                'path :"'.$baseUrl1.'/'.$item1.'/Assets/'.$html1Path.'",'.
                                'width :"'.$atts['width'].'",'.
                                'height :"'.$height.'",'.
                                'poster : "'.$baseUrl1.'/'.$item1.'/Assets/images/'.$posterFileName1.'"'.
                            '},'.
                            'xlarge : {'.
                                'id :"'. $atts['idfull'] .'",'.
                                'path :"'.$baseUrl1.'/'.$item1.'/Assets/'.$html1Path.'",'.
                                'width :"'.$atts['width'].'",'.
                                'height :"'.$height.'",'.
                                'poster : "'.$baseUrl1.'/'.$item1.'/Assets/images/'.$posterFileName1.'"'.
                            '},'.
                            'small : {'.
                                'id :"'. $atts['idsmall'] .'",'.
                                'path :"'.$baseUrl2.'/'.$item2.'/Assets/'.$html2Path.'",'.
                                'width :"'.$atts['width'].'",'.
                                'height :"'.$height.'",'.
                                'poster : "'.$baseUrl2.'/'.$item2.'/Assets/images/'.$posterFileName2.'"'.
                            '}'.
                        '});'.
                    '</script>';
        // return the ouput
        return     $toPrint;
    } else {
        return '[OAMJS ERROR => An id must be provided]';
    }

}

function getPosterFileName($dirName)
{
    $sdir = scandir($dirName);
    $file = null;
    foreach ($sdir as $item) {
        if (preg_match('/Poster\./', $item) != 0) {
            $file = $item;
            break;
        }
    }

    return $file;
}

/*
 * Server-side OAM rendering with an iFrame
 */

add_filter( 'the_content', 'wpoamr_the_content' );
function wpoamr_the_content($content)
{
  $pattern  = '~(<a href="([^"]*)/wp-content/([^"]*).oam">)([^<]*)(</a>)~';
  $domain   = get_bloginfo('url');
  $result   = preg_replace($pattern, '<iframe src="'.$domain.'/wp-content/$3/Assets/$4.html" class="wp-oam-renderer" data-oam="true" data-ratio=""></iframe>', $content);
  $result  .= "<script>";
  $result  .= "$.each($('.wp-oam-renderer'), function() { $(this).width($('#content').width()) });";
  $result  .= "</script>";

  return $result;
}

/*
 * Removes recursively a directory
 * Source: http://www.php.net/manual/fr/function.rmdir.php#92050
 */

function deleteDirectory($dir)
{
  if (!file_exists($dir)) return true;
  if (!is_dir($dir) || is_link($dir)) return unlink($dir);
  foreach (scandir($dir) as $item) {
    if ($item == '.' || $item == '..') continue;
    if (!deleteDirectory($dir . "/" . $item)) {
      chmod($dir . "/" . $item, 0777);
      if (!deleteDirectory($dir . "/" . $item)) return false;
    };
  }

  return rmdir($dir);
}
