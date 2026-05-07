<?php
/*
Plugin Name: WordPress Quick Setup - Enhanced
Description: Enhanced one-click setup for theme, plugins (Elementor, Pro Elements, Envato Elements), and pages. Self-deletes after completion.
Version: 2.5
Author: Avinash P
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Simple setup without AJAX complications
add_action('admin_init', 'simple_quick_setup_init');
add_action('admin_notices', 'simple_quick_setup_notice');

function simple_quick_setup_init() {
    // Check if setup is completed
    if (get_option('quick_setup_completed')) {
        return;
    }
    
    // Check if user clicked start setup
    if (isset($_GET['start_quick_setup']) && $_GET['start_quick_setup'] === '1') {
        // Verify user can install plugins
        if (!current_user_can('install_plugins')) {
            wp_die('You do not have permission to install plugins.');
        }
        
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'quick_setup_start')) {
            wp_die('Security check failed.');
        }
        
        // Run the setup
        run_quick_setup();
    }
}

function simple_quick_setup_notice() {
    // Don't show if already completed
    if (get_option('quick_setup_completed')) {
        return;
    }
    
    // Don't show on the setup page itself
    if (isset($_GET['start_quick_setup'])) {
        return;
    }
    
    $setup_url = wp_nonce_url(
        admin_url('admin.php?start_quick_setup=1'),
        'quick_setup_start'
    );
    
    ?>
    <div class="notice notice-info">
        <p><strong>🚀 WordPress Quick Setup:</strong> Ready to automatically install Hello Elementor theme, plugins, pages, theme settings, and permalinks?</p>
        <p>
            <a href="<?php echo esc_url($setup_url); ?>" class="button button-primary">Start Quick Setup</a>
            <button onclick="this.parentElement.parentElement.parentElement.style.display='none'" class="button">Dismiss</button>
        </p>
    </div>
    <?php
}

function run_quick_setup() {
    // Show a simple progress page
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Setting up WordPress...</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f1f1f1; }
            .container { background: white; padding: 40px; border-radius: 8px; max-width: 600px; margin: 0 auto; }
            .progress { margin: 20px 0; }
            .step { padding: 10px; margin: 5px 0; background: #f8f9fa; border-radius: 4px; }
            .step.completed { background: #d4edda; color: #155724; }
            .step.current { background: #fff3cd; color: #856404; }
            .step.pending { background: #f8f9fa; color: #6c757d; }
            .step.error { background: #f8d7da; color: #721c24; }
        </style>
    </head>
    <body>
        <div class="container">
            <h2>🚀 Setting up WordPress...</h2>
            <div class="progress">
    <?php
    
    $steps = array(
        'prepare' => 'Preparing installation...',
        'theme' => 'Installing Hello Elementor theme...',
        'elementor' => 'Installing Elementor plugin...',
        'proelements' => 'Installing Pro Elements plugin...',
        'envato' => 'Installing Envato Elements plugin...',
        'pages' => 'Creating pages and menu...',
        'comments' => 'Disabling comments...',
        'permalinks' => 'Configuring permalinks...',
        'cleanup' => 'Finalizing setup...'
    );
    
    $results = array();
    
    foreach ($steps as $step_key => $step_name) {
        echo '<div class="step current">' . $step_name . '</div>';
        echo str_repeat(' ', 1024); // Force output
        flush();
        
        $result = execute_setup_step($step_key);
        $results[$step_key] = $result;
        
        echo '<script>document.querySelector(".step.current").className = "step ' . ($result['success'] ? 'completed' : 'error') . '";</script>';
        
        if (!$result['success']) {
            echo '<div style="color: red; margin: 10px 0;">Error: ' . $result['message'] . '</div>';
            // Continue with other steps even if one fails
        }
        
        sleep(1); // Brief pause for visual effect
    }
    
    // Mark as completed
    update_option('quick_setup_completed', true);
    
    // Self-delete the plugin
    $plugin_file = __FILE__;
    deactivate_plugins(plugin_basename($plugin_file));
    
    // Try to delete the plugin file
    if (is_writable($plugin_file)) {
        unlink($plugin_file);
    }
    
    ?>
            </div>
            <div style="margin-top: 30px;">
                <h3>✅ Setup Complete!</h3>
                <p>WordPress has been configured with:</p>
                <ul style="text-align: left; display: inline-block;">
                    <li>Hello Elementor theme (activated)</li>
                    <li>Elementor plugin (installed)</li>
                    <li>Pro Elements plugin (installed)</li>
                    <li>Envato Elements plugin (installed)</li>
                    <li>Basic pages (Home, About, Services, Contact)</li>
                    <li>Navigation menu</li>
                    <li>Comments disabled site-wide</li>
                    <li>Permalinks set to /%postname%</li>
                    <li>Hello Elementor theme header/footer, page title, and description meta tag disabled</li>
                    <li>Default content cleaned up</li>
                </ul>
                <p><a href="<?php echo admin_url(); ?>" class="button button-primary" style="background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">Go to Dashboard</a></p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

function execute_setup_step($step) {
    switch ($step) {
        case 'prepare':
            return prepare_setup();
        case 'theme':
            return install_theme();
        case 'elementor':
            return install_elementor_plugin();
        case 'proelements':
            return install_proelements_plugin();
        case 'envato':
            return install_envato_elements_plugin();
        case 'pages':
            return create_pages_and_menu();
        case 'comments':
            return disable_comments();
        case 'permalinks':
            return configure_permalinks();
        case 'cleanup':
            return cleanup_setup();
        default:
            return array('success' => false, 'message' => 'Unknown step');
    }
}

function prepare_setup() {
    // Include necessary files
    if (!function_exists('request_filesystem_credentials')) {
        include_once ABSPATH . 'wp-admin/includes/file.php';
    }
    include_once ABSPATH . 'wp-admin/includes/theme.php';
    include_once ABSPATH . 'wp-admin/includes/misc.php';
    include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
    
    return array('success' => true, 'message' => 'Preparation complete');
}

function install_theme() {
    // Check if Hello Elementor is already installed
    if (wp_get_theme('hello-elementor')->exists()) {
        switch_theme('hello-elementor');
        configure_hello_theme();
        delete_default_themes();
        return array('success' => true, 'message' => 'Theme already installed');
    }
    
    $theme_url = 'https://downloads.wordpress.org/theme/hello-elementor.latest-stable.zip';
    
    $upgrader = new Theme_Upgrader(new Automatic_Upgrader_Skin());
    $result = $upgrader->install($theme_url);
    
    if (!is_wp_error($result) && $result) {
        if (wp_get_theme('hello-elementor')->exists()) {
            switch_theme('hello-elementor');
            configure_hello_theme();
            delete_default_themes();
            return array('success' => true, 'message' => 'Theme installed and activated');
        }
    }
    
    return array('success' => false, 'message' => 'Theme installation failed');
}

function configure_hello_theme() {
    // Disable built-in Hello Elementor features that are handled elsewhere.
    update_option('hello_elementor_settings_description_meta_tag', 'true');
    update_option('hello_elementor_settings_header_footer', 'true');
    update_option('hello_elementor_settings_page_title', 'true');
}

function delete_default_themes() {
    // Delete default WordPress themes
    $default_themes = array(
        'twentytwentyfour',
        'twentytwentythree',
        'twentytwentyfive'
    );
    
    foreach ($default_themes as $theme_slug) {
        $theme = wp_get_theme($theme_slug);
        if ($theme->exists()) {
            delete_theme($theme_slug);
        }
    }
}

function install_elementor_plugin() {
    $plugin_url = 'https://downloads.wordpress.org/plugin/elementor.latest-stable.zip';
    
    // Check if Elementor is already installed
    if (is_dir(WP_PLUGIN_DIR . '/elementor')) {
        activate_plugin('elementor/elementor.php');
        return array('success' => true, 'message' => 'Elementor already installed');
    }
    
    $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
    $result = $upgrader->install($plugin_url);
    
    if (!is_wp_error($result) && $result) {
        // Activate the plugin
        $activate_result = activate_plugin('elementor/elementor.php');
        if (!is_wp_error($activate_result)) {
            return array('success' => true, 'message' => 'Elementor installed and activated');
        }
    }
    
    return array('success' => false, 'message' => 'Elementor installation failed');
}

function install_proelements_plugin() {
    // Check if Pro Elements is already installed
    if (is_dir(WP_PLUGIN_DIR . '/proelements')) {
        activate_plugin('proelements/proelements.php');
        return array('success' => true, 'message' => 'Pro Elements already installed');
    }
    
    // GitHub download URL for Pro Elements
    $plugin_url = 'https://github.com/proelements/proelements/archive/refs/heads/master.zip';
    
    // Download the plugin
    $temp_file = download_url($plugin_url);
    if (is_wp_error($temp_file)) {
        return array('success' => false, 'message' => 'Failed to download Pro Elements: ' . $temp_file->get_error_message());
    }
    
    // Extract the plugin
    $plugin_dir = WP_PLUGIN_DIR . '/proelements-temp';
    
    // Create temporary directory
    if (!wp_mkdir_p($plugin_dir)) {
        unlink($temp_file);
        return array('success' => false, 'message' => 'Failed to create temporary directory');
    }
    
    // Extract ZIP file
    $unzip_result = unzip_file($temp_file, $plugin_dir);
    unlink($temp_file);
    
    if (is_wp_error($unzip_result)) {
        return array('success' => false, 'message' => 'Failed to extract Pro Elements: ' . $unzip_result->get_error_message());
    }
    
    // Move from extracted folder to proper plugin directory
    $extracted_folder = $plugin_dir . '/proelements-master';
    $final_plugin_dir = WP_PLUGIN_DIR . '/proelements';
    
    if (is_dir($extracted_folder)) {
        // Move the contents
        if (rename($extracted_folder, $final_plugin_dir)) {
            // Clean up temporary directory
            rmdir($plugin_dir);
            
            // Try to activate the plugin
            $activate_result = activate_plugin('proelements/proelements.php');
            if (!is_wp_error($activate_result)) {
                return array('success' => true, 'message' => 'Pro Elements installed and activated');
            } else {
                return array('success' => true, 'message' => 'Pro Elements installed (activation may require manual setup)');
            }
        }
    }
    
    return array('success' => false, 'message' => 'Failed to install Pro Elements plugin');
}

function install_envato_elements_plugin() {
    $plugin_url = 'https://downloads.wordpress.org/plugin/envato-elements.latest-stable.zip';
    
    // Check if Envato Elements is already installed
    if (is_dir(WP_PLUGIN_DIR . '/envato-elements')) {
        activate_plugin('envato-elements/envato-elements.php');
        return array('success' => true, 'message' => 'Envato Elements already installed');
    }
    
    $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
    $result = $upgrader->install($plugin_url);
    
    if (!is_wp_error($result) && $result) {
        // Activate the plugin
        $activate_result = activate_plugin('envato-elements/envato-elements.php');
        if (!is_wp_error($activate_result)) {
            return array('success' => true, 'message' => 'Envato Elements installed and activated');
        }
    }
    
    return array('success' => false, 'message' => 'Envato Elements installation failed');
}

function create_pages_and_menu() {
    // Create empty pages
    $pages = array(
        'Home' => '',
        'About Us' => '',
        'Our Services' => '',
        'Contact Us' => ''
    );
    
    $menu_name = 'Main Menu';
    $menu_exists = wp_get_nav_menu_object($menu_name);
    
    if (!$menu_exists) {
        $menu_id = wp_create_nav_menu($menu_name);
        
        foreach ($pages as $title => $content) {
            // Check if page exists
            $existing_page = get_page_by_title($title, OBJECT, 'page');
            if (!$existing_page) {
                $page_id = wp_insert_post(array(
                    'post_title' => $title,
                    'post_content' => $content,
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'comment_status' => 'closed', // Disable comments on pages
                    'ping_status' => 'closed' // Disable pingbacks
                ));
                
                if ($page_id && !is_wp_error($page_id)) {
                    // Set Home as front page
                    if ($title === 'Home') {
                        update_option('page_on_front', $page_id);
                        update_option('show_on_front', 'page');
                    }
                    
                    // Add to menu
                    wp_update_nav_menu_item($menu_id, 0, array(
                        'menu-item-title' => $title,
                        'menu-item-object' => 'page',
                        'menu-item-object-id' => $page_id,
                        'menu-item-type' => 'post_type',
                        'menu-item-status' => 'publish'
                    ));
                }
            }
        }
        
        // Set as primary menu
        $locations = get_theme_mod('nav_menu_locations');
        $locations['primary'] = $menu_id;
        set_theme_mod('nav_menu_locations', $locations);
    }
    
    return array('success' => true, 'message' => 'Pages and menu created');
}

function disable_comments() {
    // Disable comments on posts and pages by default
    update_option('default_comment_status', 'closed');
    update_option('default_ping_status', 'closed');
    
    // Close comments on existing posts and pages
    global $wpdb;
    
    // Close comments on all existing posts
    $wpdb->query("UPDATE {$wpdb->posts} SET comment_status = 'closed', ping_status = 'closed' WHERE post_status = 'publish'");
    
    // Hide existing comments
    update_option('comments_notify', 0);
    update_option('moderation_notify', 0);
    
    // Remove comment support from posts and pages
    update_option('page_comments', 0);
    update_option('comments_per_page', 0);
    
    // Clear comment count cache
    wp_cache_delete('comments', 'counts');
    
    return array('success' => true, 'message' => 'Comments disabled');
}

function configure_permalinks() {
    global $wp_rewrite;

    $permalink_structure = '/%postname%';

    if (is_object($wp_rewrite) && method_exists($wp_rewrite, 'set_permalink_structure')) {
        $wp_rewrite->set_permalink_structure($permalink_structure);
    } else {
        update_option('permalink_structure', $permalink_structure);
    }

    flush_rewrite_rules(false);

    return array('success' => true, 'message' => 'Permalinks configured');
}

function cleanup_setup() {
    // Prevent Elementor setup wizard
    update_option('elementor_onboarded', true);
    delete_transient('elementor_activation_redirect');
    
    // Delete default Sample Page
    $sample_page = get_page_by_title('Sample Page');
    if ($sample_page) {
        wp_delete_post($sample_page->ID, true);
    }
    
    // Delete Privacy Policy page
    $privacy_page = get_page_by_title('Privacy Policy');
    if ($privacy_page) {
        wp_delete_post($privacy_page->ID, true);
    }
    
    // Delete default Hello World post
    $hello_world = get_page_by_title('Hello world!', OBJECT, 'post');
    if ($hello_world) {
        wp_delete_post($hello_world->ID, true);
    }
    
    // Delete default comment on Hello World post
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_ID = 1");
    $wpdb->query("DELETE FROM {$wpdb->commentmeta} WHERE comment_id = 1");
    
    return array('success' => true, 'message' => 'Setup completed');
}

// Prevent Elementor redirect on activation
add_action('admin_init', function() {
    delete_transient('elementor_activation_redirect');
    if (get_option('quick_setup_completed')) {
        update_option('elementor_onboarded', true);
    }
}, 1);
?>
