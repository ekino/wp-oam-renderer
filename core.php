<?php
/**
 * This file is a part of the Ekino WP-OAM-Renderer Wordpress plugin
 */

/**
 * Returns OAM mime type merged to original mime types
 *
 * @param array $mimes Original mime types array
 *
 * @return array
 */
function wp_oam_renderer_upload_mimes($mimes)
{
    return array_merge($mimes, array(
        'oam' => 'application/octet-stream'
    ));
}

/**
 * Returns OAM attachment file attributes
 *
 * @param array    $attributes Original attributes
 * @param \WP_Post $attachment An attachment instance
 *
 * @return array
 */
function wp_oam_renderer_get_attachment_images_attributes($attributes, $attachment = null)
{
    $pathinfo = pathinfo($attachment->guid);

    if ($pathinfo['extension'] == 'oam') {
        $dirname = pathinfo(get_attached_file($attachment->ID), PATHINFO_DIRNAME);
        $directory = sprintf('%s/%s/Assets/images/', $dirname, $pathinfo['filename']);

        $filename = wp_oam_renderer_get_poster_filename($directory);

        if ($filename) {
            $baseUrl = sprintf('%s/%s/Assets/images', $pathinfo['dirname'], $pathinfo['filename']);
            $attributes['src'] = sprintf('%s/%s', $baseUrl, $filename);
        }
    }

    return $attributes;
}

/**
 * Add attachment process
 *
 * @param integer $identifier An attachment identifier
 */
function wp_oam_renderer_add_attachment($identifier)
{
    $file = get_attached_file($identifier);
    $pathinfo = pathinfo($file);

    if ($pathinfo['extension'] == 'oam') {
        $filename  = $pathinfo['basename'];
        $directory = $pathinfo['dirname'];

        $output = basename($filename, '.oam');

        // Unzip file in the same directory
        $zip = new ZipArchive();
        $result = $zip->open($file);

        if (!$result) {
            echo "ERROR: can't open $file";
            return;
        }

        $zip->extractTo(sprintf('%s/%s', $directory, $output));
        $zip->close();

        // Parsing config.xml
        $configuration = simplexml_load_file(sprintf('%s/%s/config.xml', $directory, $output));
        $oamSource = $configuration->xpath('//config/oamfile/@src');

        // Getting edge assets XML
        $edges = simplexml_load_file(sprintf('%s/%s/%s', $directory, $output, $oamSource[0]));
        $edges->registerXPathNamespace('edge', 'http://openajax.org/metadata');

        $width  = $edges->xpath('//edge:icon/@width');
        $height = $edges->xpath('//edge:icon/@height');
        $html   = (string) $edges->require['src'];

        // Adds width & height metadata to the OAM attachment
        if (isset($width[0])) {
            add_post_meta($identifier, 'width', (int) $width[0]);
        }

        if (isset($height[0])) {
            add_post_meta($identifier, 'height', (int) $height[0]);
        }

        add_post_meta($identifier, 'htmlPath', $html);
    }
}

/**
 * Deletes an OAM file attachment and also deletes unzipped content
 *
 * @param integer $identifier An attachment identifier
 */
function wp_oam_renderer_delete_attachment($identifier)
{
    $file = get_attached_file($identifier);
    $pathinfo = pathinfo($file);

    if ($pathinfo['extension'] == 'oam') {
        $output = basename($pathinfo['basename'], '.oam');

        wp_oam_renderer_delete_directory(sprintf('%s/%s', $pathinfo['dirname'], $output), $pathinfo['dirname']);
    }
}

/**
 * Returns HTML code generated for 'oam' short code
 *
 * @param array       $attributes An attributes array
 * @param string|null $content    HTML content
 *
 * @return string
 */
function wp_oam_renderer_oam_short_code($attributes, $content = null)
{
    if (!isset($attributes['id']) || !$attributes['id']) {
        return '[OAM ERROR => An id must be provided]';
    }

    $attributes = shortcode_atts(array(
        'id'    => '',
        'width' => 960,
    ), $attributes);

    $attachment = get_post($attributes['id']);
    $metadata   = get_post_meta($attributes['id']);

    if (!$metadata) {
        return sprintf('[OAM ERROR => Cannot retrieve attachment with id "%s"]', $attributes['id']);
    }

    $height = (int) $attributes['width'] * (int) $metadata['height'][0] / (int) $metadata['width'][0];

    if (isset($attributes['height']) && $attributes['height']) {
        $height = $attributes['height'];
    }

    $pathinfo = pathinfo($attachment->guid);

    $source = sprintf('%s/%s/Assets/%s', $pathinfo['dirname'], $pathinfo['filename'], $metadata['htmlPath'][0]);
    $iframe = sprintf('<iframe src="%s" width="%s" height="%s"></iframe>', $source, $attributes['width'], $height);

    return $iframe;
}

/**
 * Displays OAM short code in an input element on "Insert media" window on post edit
 *
 * @param array    $form_fields A form fields array
 * @param |WP_Post $attachment  An attachment object
 *
 * @return array
 */
function wp_oam_renderer_edit_fields($form_fields, $attachment) {
    if (substr($attachment->guid, -3) == 'oam') {
        $shortcode = sprintf('[oam id="%s"]', $attachment->ID);

        $form_fields['oam'] = array(
            'label' => 'Embed OAM',
            'input' => 'html',
            'html'  => sprintf('<input id="oam-embed" name="oam-embed" type="text" value=\'%s\' />', $shortcode),
        );
    }

    return $form_fields;
}

/**
 * Returns attachment data array with first-time generated OAM icon file
 *
 * @param array $attachment
 *
 * @return array
 */
function wp_oam_renderer_prepare_attachment_for_js($attachment) {
    if (substr($attachment['filename'], -3) == 'oam') {
        $post = get_post($attachment['id']);
        $pathinfo = pathinfo($post->guid);

        $dirname = pathinfo(get_attached_file($attachment['id']), PATHINFO_DIRNAME);
        $directory = sprintf('%s/%s/Assets/images', $dirname, $pathinfo['filename']);

        $filename = wp_oam_renderer_get_poster_filename($directory);

        if (!$filename) {
            return $attachment;
        }

        $width  = 55;
        $height = 55;

        $info = pathinfo($filename);

        $iconFile = sprintf('%s-%sx%s.%s', $info['filename'], $width, $height, $info['extension']);
        $iconFilepath = sprintf('%s/%s', $directory, $iconFile);

        if (!file_exists($iconFilepath)) {
            image_resize(sprintf('%s/%s', $directory, $filename), $width, $height, true, null, $directory, 100);
        }

        $baseUrl = sprintf('%s/%s/Assets/images', $pathinfo['dirname'], $pathinfo['filename']);
        $attachment['icon'] = sprintf('%s/%s', $baseUrl, $iconFile);
    }

    return $attachment;
}
