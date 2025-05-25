<?php
/*
Plugin Name: WordPress Quick Setup
Description: Automatically sets up theme, plugins, pages, and menus after a manual WordPress installation.
Version: 1.0
Author: Your Name
*/

add_action('admin_init', 'quick_setup');

function quick_setup() {
    // Check if the setup has already been run
    if (get_option('quick_setup_completed')) {
        return;
    }

    // Include necessary WordPress files
    include_once ABSPATH . 'wp-admin/includes/theme.php';
    include_once ABSPATH . 'wp-admin/includes/file.php';
    include_once ABSPATH . 'wp-admin/includes/misc.php';
    include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    include_once ABSPATH . 'wp-admin/includes/plugin.php';

    // Install and activate the theme
    $theme_url = 'https://downloads.wordpress.org/theme/hello-elementor.latest-stable.zip';

    $theme_upgrader = new Theme_Upgrader();
    $result = $theme_upgrader->install($theme_url);

    if (!is_wp_error($result)) {
        $theme_slug = 'hello-elementor'; // Adjust this to match the folder name of the theme
        if (wp_get_theme($theme_slug)->exists()) {
            switch_theme($theme_slug);
        } else {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>Failed to activate theme.</p></div>';
            });
        }
    } else {
        add_action('admin_notices', function () use ($result) {
            echo '<div class="notice notice-error"><p>Failed to install theme: ' . $result->get_error_message() . '</p></div>';
        });
    }

    // Install and activate plugins
    $plugins = [
        'https://downloads.wordpress.org/plugin/elementor.latest-stable.zip',
        'https://downloads.wordpress.org/plugin/envato-elements.latest-stable.zip',
        'https://github.com/proelements/proelements/archive/refs/heads/master.zip'
    ];

    foreach ($plugins as $plugin_url) {
        $upgrader = new Plugin_Upgrader();
        $result = $upgrader->install($plugin_url);

        if (is_wp_error($result)) {
            add_action('admin_notices', function () use ($plugin_url) {
                echo '<div class="notice notice-error"><p>Failed to install plugin from ' . $plugin_url . '.</p></div>';
            });
        }
    }

    // Activate plugins
    activate_plugin('elementor/elementor.php');
    activate_plugin('envato-elements/envato-elements.php');
    activate_plugin('proelements-master/proelements.php');

    // Create pages and add to menu
    $pages = [
        'Home' => '',
        'About Us' => '',
        'Our Services' => '',
        'Contact Us' => '',
    ];

    $menu_name = 'Main Menu';
    $menu_exists = wp_get_nav_menu_object($menu_name);

    if (!$menu_exists) {
        $menu_id = wp_create_nav_menu($menu_name);

        foreach ($pages as $title => $content) {
            // Check if the page already exists
            $existing_page = get_page_by_title($title, OBJECT, 'page');
            if (!$existing_page) {
                $page_id = wp_insert_post([
                    'post_title' => $title,
                    'post_content' => $content,
                    'post_status' => 'publish',
                    'post_type' => 'page',
                ]);

                // Set Home page as front page
                if ($title === 'Home') {
                    update_option('page_on_front', $page_id);
                    update_option('show_on_front', 'page');
                }

                // Add page to the menu
                wp_update_nav_menu_item($menu_id, 0, [
                    'menu-item-title' => $title,
                    'menu-item-object' => 'page',
                    'menu-item-object-id' => $page_id,
                    'menu-item-type' => 'post_type',
                    'menu-item-status' => 'publish',
                ]);
            }
        }

        // Set the menu as the primary menu
        $locations = get_theme_mod('nav_menu_locations');
        $locations['primary'] = $menu_id;
        set_theme_mod('nav_menu_locations', $locations);
    }

    // Mark setup as complete
    update_option('quick_setup_completed', true);

    // Notify the admin
    add_action('admin_notices', function () {
        echo '<div class="notice notice-success is-dismissible"><p>Quick setup completed!</p></div>';
    });
}
