<?php
// Isolated regression checks; no WordPress installation or downloads required.
define('ABSPATH', __DIR__ . '/');
define('WP_PLUGIN_DIR', __DIR__);
function add_action(...$args) {}
class WP_Error {}
function is_wp_error($value) { return $value instanceof WP_Error; }
$options = array('elementor_experiment-container' => 'active');
$activation = null;
$install = true;
$block_write = false;
function activate_plugin($plugin) { return $GLOBALS['activation']; }
function update_option($key, $value) {
    if ($GLOBALS['block_write']) { return false; }
    $unchanged = isset($GLOBALS['options'][$key]) && $GLOBALS['options'][$key] === $value;
    $GLOBALS['options'][$key] = $value;
    return !$unchanged;
}
function get_option($key) { return $GLOBALS['options'][$key] ?? false; }
class Automatic_Upgrader_Skin {}
class Plugin_Upgrader {
    function __construct($skin) {}
    function install($url) { return $GLOBALS['install']; }
}
require dirname(__DIR__) . '/quick-setup.php';
$count = 0;
function check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    ++$GLOBALS['count'];
}
check(install_elementor_plugin()['success'], 'Fresh install failed');
check(get_option('elementor_experiment-e_opt_in_v4') === 'inactive', 'V4 remains active');
check(get_option('elementor_experiment-e_atomic_elements') === 'inactive', 'Atomic widgets remain active');
check(get_option('elementor_experiment-container') === 'active', 'Containers were changed');
check(quick_setup_disable_atomic_editor()['success'], 'Unchanged options falsely failed');
$activation = new WP_Error();
$options['elementor_experiment-e_opt_in_v4'] = 'active';
check(!install_elementor_plugin()['success'], 'Activation error ignored');
check(get_option('elementor_experiment-e_opt_in_v4') === 'active', 'Settings changed after activation failed');
$activation = null;
$install = new WP_Error();
check(!install_elementor_plugin()['success'], 'Installation error ignored');
$install = true;
$block_write = true;
check(!quick_setup_disable_atomic_editor()['success'], 'Option write error ignored');
$block_write = false;
eval('namespace Elementor\\Modules\\AtomicWidgets\\OptIn; class Opt_In { const OPT_OUT_FEATURES = ["e_opt_in_v4", "e_atomic_elements", "global_classes"]; }');
check(quick_setup_disable_atomic_editor()['success'], 'Version-specific opt-out failed');
check(get_option('elementor_experiment-global_classes') === 'inactive', 'Dependent opt-out ignored');
if (!mkdir(__DIR__ . '/elementor')) { throw new RuntimeException('Cannot create installed-plugin fixture'); }
try {
    $install = new WP_Error(); // Installed branch must not download again.
    check(install_elementor_plugin()['success'], 'Existing installation failed');
    $activation = new WP_Error();
    check(!install_elementor_plugin()['success'], 'Existing activation error ignored');
} finally {
    rmdir(__DIR__ . '/elementor');
}
echo "PASS: $count Atomic Editor checks\n";
