<?php
/**
 * This file is a part of the Ekino WP-OAM-Renderer Wordpress plugin
 */

/**
 * Returns poster filename
 *
 * @param string $directory A directory to find poster file
 *
 * @return string
 */
function wp_oam_renderer_get_poster_filename($directory)
{
    foreach (scandir($directory) as $item) {
        if (preg_match('/Poster\./', $item) != 0) {
            return $item;
        }
    }

    return null;
}

/**
 * Removes a directory recursively
 *
 * @param string $directory     A directory name
 * @param string $baseDirectory A base directory for security
 *
 * @return bool
 */
function wp_oam_renderer_delete_directory($directory, $baseDirectory)
{
    if (false === strpos($directory, $baseDirectory)) {
        return false;
    }

    if (!file_exists($directory)) {
        return true;
    }

    if (!is_dir($directory) || is_link($directory)) {
        return unlink($directory);
    }

    foreach (scandir($directory) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }

        $directoryItem = sprintf('%s/%s', $directory, $item);

        if (!wp_oam_renderer_delete_directory($directoryItem, $baseDirectory)) {
            chmod($directoryItem, 0777);

            if (!wp_oam_renderer_delete_directory($directoryItem, $baseDirectory)) {
                return false;
            }
        }
    }

    return rmdir($directory);
}