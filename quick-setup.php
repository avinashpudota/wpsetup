<?php
/*
Plugin Name: WordPress Quick Setup - Simple
Description: Simple one-click setup for theme, plugins, and pages. Self-deletes after completion.
Version: 2.1
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
        <p><strong>🚀 WordPress Quick Setup:</strong> Ready to automatically install Hello Elementor theme, Elementor plugin, and create basic pages?</p>
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
            .container { background: white; padding: 40px; border-radius: 8px; max-width: 500px; margin: 0 auto; }
            .progress { margin: 20px 0; }
            .step { padding: 10px; margin: 5px 0; background: #f8f9fa; border-radius: 4px; }
            .step.completed { background: #d4edda; color: #155724; }
            .step.current { background: #fff3cd; color: #856404; }
            .step.pending { background: #f8f9fa; color: #6c757d; }
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
        'plugins' => 'Installing Elementor plugin...',
        'pages' => 'Creating pages and menu...',
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
            break;
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
                    <li>Basic pages (Home, About, Services, Contact)</li>
                    <li>Navigation menu</li>
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
        case 'plugins':
            return install_plugins();
        case 'pages':
            return create_pages_and_menu();
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
        return array('success' => true, 'message' => 'Theme already installed');
    }
    
    $theme_url = 'https://downloads.wordpress.org/theme/hello-elementor.latest-stable.zip';
    
    $upgrader = new Theme_Upgrader(new Automatic_Upgrader_Skin());
    $result = $upgrader->install($theme_url);
    
    if (!is_wp_error($result) && $result) {
        if (wp_get_theme('hello-elementor')->exists()) {
            switch_theme('hello-elementor');
            return array('success' => true, 'message' => 'Theme installed and activated');
        }
    }
    
    return array('success' => false, 'message' => 'Theme installation failed');
}

function install_plugins() {
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
    
    return array('success' => false, 'message' => 'Plugin installation failed');
}

function create_pages_and_menu() {
    $pages = array(
        'Home' => '',
        'About Us' => 'Welcome to our about page.',
        'Our Services' => 'Learn about our services.',
        'Contact Us' => 'Get in touch with us.'
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
                    'post_type' => 'page'
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

function cleanup_setup() {
    // Prevent Elementor setup wizard
    update_option('elementor_onboarded', true);
    delete_transient('elementor_activation_redirect');
    
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
