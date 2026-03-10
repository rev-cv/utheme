<?php
/**
 * Adds custom classes to the body tag for conditional styling.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!function_exists('utheme_body_class_mods')) {
    /**
     * Adds body classes based on theme configuration from conf.scss.
     * This provides a reliable hook for CSS, avoiding browser-compatibility
     * issues with selectors like `:has()`.
     */
    function utheme_body_class_mods($classes) {
        if (function_exists('my_theme_get_config') && my_theme_get_config('main-menu') === 'newspaper') {
            $classes[] = 'has-newspaper-menu';
        }
        return $classes;
    }
    add_filter('body_class', 'utheme_body_class_mods');
}