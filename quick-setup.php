<?php
/*
Plugin Name: WordPress Quick Setup
Description: Automatically sets up theme, plugins, pages, and menus after WordPress installation. Self-deletes after completion.
Version: 2.0
Author: Avinash P
GitHub: https://github.com/avinashpudota/wpsetup/
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Quick_Setup {
    private $setup_steps = array();
    private $current_step = 0;
    private $errors = array();
    
    public function __construct() {
        add_action('admin_init', array($this, 'init_setup'));
        add_action('wp_ajax_quick_setup_step', array($this, 'process_setup_step'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_notices', array($this, 'show_setup_notice'));
    }
    
    public function init_setup() {
        // Check if setup is already completed
        if (get_option('quick_setup_completed')) {
            return;
        }
        
        // Initialize setup steps
        $this->setup_steps = array(
            'prepare' => 'Preparing setup...',
            'theme' => 'Installing Hello Elementor theme...',
            'plugins' => 'Installing plugins...',
            'activate_plugins' => 'Activating plugins...',
            'pages' => 'Creating pages and menu...',
            'cleanup' => 'Finishing setup...'
        );
        
        // Check if we're in the middle of setup
        $setup_in_progress = get_transient('quick_setup_in_progress');
        if (!$setup_in_progress && !isset($_GET['quick_setup'])) {
            // Show initial setup notice
            return;
        }
    }
    
    public function enqueue_scripts() {
        if (!get_option('quick_setup_completed') && (isset($_GET['quick_setup']) || get_transient('quick_setup_in_progress'))) {
            wp_enqueue_script('jquery');
            ?>
            <style>
                .quick-setup-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0,0,0,0.8);
                    z-index: 999999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .quick-setup-modal {
                    background: white;
                    padding: 30px;
                    border-radius: 8px;
                    max-width: 500px;
                    width: 90%;
                    text-align: center;
                }
                .progress-bar {
                    width: 100%;
                    height: 20px;
                    background: #f0f0f0;
                    border-radius: 10px;
                    overflow: hidden;
                    margin: 20px 0;
                }
                .progress-fill {
                    height: 100%;
                    background: #0073aa;
                    width: 0%;
                    transition: width 0.3s ease;
                }
                .setup-status {
                    margin: 15px 0;
                    font-weight: 500;
                }
            </style>
            <script>
            jQuery(document).ready(function($) {
                if (window.location.search.indexOf('quick_setup=start') > -1) {
                    startSetup();
                }
                
                function startSetup() {
                    $('body').append('<div class="quick-setup-overlay"><div class="quick-setup-modal"><h2>Setting up WordPress...</h2><div class="progress-bar"><div class="progress-fill"></div></div><div class="setup-status">Initializing...</div></div></div>');
                    processNextStep();
                }
                
                function processNextStep() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'quick_setup_step',
                            security: '<?php echo wp_create_nonce('quick_setup_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('.progress-fill').css('width', response.data.progress + '%');
                                $('.setup-status').text(response.data.message);
                                
                                if (response.data.completed) {
                                    $('.setup-status').text('Setup completed! Redirecting...');
                                    setTimeout(function() {
                                        window.location.href = '<?php echo admin_url(); ?>';
                                    }, 2000);
                                } else {
                                    setTimeout(processNextStep, 1000);
                                }
                            } else {
                                $('.setup-status').html('<span style="color: red;">Error: ' + response.data + '</span>');
                            }
                        },
                        error: function() {
                            $('.setup-status').html('<span style="color: red;">Setup failed. Please refresh and try again.</span>');
                        }
                    });
                }
            });
            </script>
            <?php
        }
    }
    
    public function show_setup_notice() {
        if (get_option('quick_setup_completed') || get_transient('quick_setup_in_progress')) {
            return;
        }
        
        ?>
        <div class="notice notice-info">
            <p><strong>WordPress Quick Setup:</strong> Ready to automatically install theme, plugins, and create basic pages?</p>
            <p>
                <a href="<?php echo admin_url('admin.php?quick_setup=start'); ?>" class="button button-primary">Start Quick Setup</a>
                <a href="#" onclick="this.parentElement.parentElement.parentElement.style.display='none'; return false;" class="button">Skip Setup</a>
            </p>
        </div>
        <?php
    }
    
    public function process_setup_step() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['security'], 'quick_setup_nonce')) {
            wp_die('Security check failed');
        }
        
        // Set setup in progress
        set_transient('quick_setup_in_progress', true, 300); // 5 minutes timeout
        
        $current_step = get_option('quick_setup_current_step', 0);
        $steps = array_keys($this->setup_steps);
        $total_steps = count($steps);
        
        if ($current_step >= $total_steps) {
            wp_send_json_success(array(
                'completed' => true,
                'progress' => 100,
                'message' => 'Setup completed successfully!'
            ));
            return;
        }
        
        $step_name = $steps[$current_step];
        $step_message = $this->setup_steps[$step_name];
        
        // Process the current step
        $result = $this->execute_step($step_name);
        
        if ($result['success']) {
            $current_step++;
            update_option('quick_setup_current_step', $current_step);
            
            $progress = ($current_step / $total_steps) * 100;
            
            if ($current_step >= $total_steps) {
                // Mark setup as completed
                update_option('quick_setup_completed', true);
                delete_option('quick_setup_current_step');
                delete_transient('quick_setup_in_progress');
                
                // Self-delete the plugin
                deactivate_plugins(plugin_basename(__FILE__));
                delete_plugins(array(plugin_basename(__FILE__)));
                
                wp_send_json_success(array(
                    'completed' => true,
                    'progress' => 100,
                    'message' => 'Setup completed! Plugin removed automatically.'
                ));
            } else {
                wp_send_json_success(array(
                    'completed' => false,
                    'progress' => $progress,
                    'message' => $step_message
                ));
            }
        } else {
            // Log error and continue
            $this->log_error("Step $step_name failed: " . $result['message']);
            wp_send_json_error($result['message']);
        }
    }
    
    private function execute_step($step) {
        switch ($step) {
            case 'prepare':
                return $this->prepare_setup();
            case 'theme':
                return $this->install_theme();
            case 'plugins':
                return $this->install_plugins();
            case 'activate_plugins':
                return $this->activate_plugins();
            case 'pages':
                return $this->create_pages_and_menu();
            case 'cleanup':
                return $this->cleanup_setup();
            default:
                return array('success' => false, 'message' => 'Unknown step');
        }
    }
    
    private function prepare_setup() {
        // Include necessary WordPress files
        if (!function_exists('request_filesystem_credentials')) {
            include_once ABSPATH . 'wp-admin/includes/file.php';
        }
        include_once ABSPATH . 'wp-admin/includes/theme.php';
        include_once ABSPATH . 'wp-admin/includes/misc.php';
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        
        return array('success' => true, 'message' => 'Setup prepared');
    }
    
    private function install_theme() {
        $theme_url = 'https://downloads.wordpress.org/theme/hello-elementor.latest-stable.zip';
        
        // Check if theme already exists
        if (wp_get_theme('hello-elementor')->exists()) {
            switch_theme('hello-elementor');
            return array('success' => true, 'message' => 'Theme already installed and activated');
        }
        
        $theme_upgrader = new Theme_Upgrader(new Automatic_Upgrader_Skin());
        $result = $theme_upgrader->install($theme_url);
        
        if (!is_wp_error($result) && $result) {
            if (wp_get_theme('hello-elementor')->exists()) {
                switch_theme('hello-elementor');
                return array('success' => true, 'message' => 'Theme installed and activated');
            }
        }
        
        $error_message = is_wp_error($result) ? $result->get_error_message() : 'Theme installation failed';
        return array('success' => false, 'message' => $error_message);
    }
    
    private function install_plugins() {
        $plugins = array(
            'elementor' => 'https://downloads.wordpress.org/plugin/elementor.latest-stable.zip',
            'envato-elements' => 'https://downloads.wordpress.org/plugin/envato-elements.latest-stable.zip',
            'proelements' => 'https://github.com/proelements/proelements/archive/refs/heads/master.zip'
        );
        
        $installed_plugins = array();
        
        foreach ($plugins as $slug => $url) {
            // Check if plugin directory already exists
            $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
            if (is_dir($plugin_dir)) {
                $installed_plugins[] = $slug;
                continue;
            }
            
            $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
            $result = $upgrader->install($url);
            
            if (!is_wp_error($result) && $result) {
                $installed_plugins[] = $slug;
            } else {
                $this->log_error("Failed to install plugin $slug: " . (is_wp_error($result) ? $result->get_error_message() : 'Unknown error'));
            }
        }
        
        // Store installed plugins for activation step
        update_option('quick_setup_installed_plugins', $installed_plugins);
        
        return array('success' => true, 'message' => 'Plugins installation completed');
    }
    
    private function activate_plugins() {
        $plugin_files = array(
            'elementor/elementor.php',
            'envato-elements/envato-elements.php',
            'proelements-master/proelements.php'
        );
        
        $activated = 0;
        foreach ($plugin_files as $plugin_file) {
            if (file_exists(WP_PLUGIN_DIR . '/' . $plugin_file)) {
                $result = activate_plugin($plugin_file);
                if (!is_wp_error($result)) {
                    $activated++;
                }
            }
        }
        
        return array('success' => true, 'message' => "Activated $activated plugins");
    }
    
    private function create_pages_and_menu() {
        $pages = array(
            'Home' => '',
            'About Us' => '',
            'Our Services' => '',
            'Contact Us' => '',
        );
        
        $menu_name = 'Main Menu';
        $menu_exists = wp_get_nav_menu_object($menu_name);
        
        if (!$menu_exists) {
            $menu_id = wp_create_nav_menu($menu_name);
            
            foreach ($pages as $title => $content) {
                // Check if page already exists
                $existing_page = get_page_by_title($title, OBJECT, 'page');
                if (!$existing_page) {
                    $page_id = wp_insert_post(array(
                        'post_title' => $title,
                        'post_content' => $content,
                        'post_status' => 'publish',
                        'post_type' => 'page',
                    ));
                    
                    if ($page_id && !is_wp_error($page_id)) {
                        // Set Home page as front page
                        if ($title === 'Home') {
                            update_option('page_on_front', $page_id);
                            update_option('show_on_front', 'page');
                        }
                        
                        // Add page to menu
                        wp_update_nav_menu_item($menu_id, 0, array(
                            'menu-item-title' => $title,
                            'menu-item-object' => 'page',
                            'menu-item-object-id' => $page_id,
                            'menu-item-type' => 'post_type',
                            'menu-item-status' => 'publish',
                        ));
                    }
                }
            }
            
            // Set menu as primary menu
            $locations = get_theme_mod('nav_menu_locations');
            $locations['primary'] = $menu_id;
            set_theme_mod('nav_menu_locations', $locations);
        }
        
        return array('success' => true, 'message' => 'Pages and menu created');
    }
    
    private function cleanup_setup() {
        // Clear any temporary options
        delete_option('quick_setup_installed_plugins');
        delete_transient('quick_setup_in_progress');
        
        // Suppress Elementor setup wizard
        update_option('elementor_onboarded', true);
        
        return array('success' => true, 'message' => 'Cleanup completed');
    }
    
    private function log_error($message) {
        error_log('WP Quick Setup Error: ' . $message);
        $this->errors[] = $message;
    }
}

// Initialize the plugin
new WP_Quick_Setup();

// Hook to suppress Elementor setup redirect
add_action('admin_init', function() {
    if (get_option('quick_setup_completed')) {
        // Prevent Elementor setup wizard from redirecting
        delete_transient('elementor_activation_redirect');
        update_option('elementor_onboarded', true);
    }
}, 1);
?>
