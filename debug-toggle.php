<?php
/*
Plugin Name: Debug Toggle
Plugin URI: https://www.web-jive.com/
Description: Manage WordPress debug settings from your dashboard. Toggle debug modes and prevent unauthorized changes.
Version: 1.7.8
Author: Eric Caldwell - WebJIVE
Author URI: https://www.web-jive.com
License: GPLv2 or later
Requires at least: 5.2
Requires PHP: 5.6
Text Domain: debug-toggle
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Load plugin textdomain for translations
function debug_toggle_load_textdomain() {
    load_plugin_textdomain( 'debug-toggle', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'debug_toggle_load_textdomain' );

// Define the debug constants
function debug_toggle_get_constants() {
    return array(
        'WP_DEBUG',
        'WP_DEBUG_LOG',
        'WP_DEBUG_DISPLAY',
        'SCRIPT_DEBUG',
        'SAVEQUERIES',
    );
}

// Add custom cron schedule BEFORE any scheduling occurs
add_filter( 'cron_schedules', 'debug_toggle_cron_schedules' );
function debug_toggle_cron_schedules( $schedules ) {
    $interval_hours = intval( get_option( 'debug_toggle_monitoring_interval', 3 ) );
    if ( $interval_hours < 1 || $interval_hours > 24 ) {
        $interval_hours = 3; // Ensure the interval is within bounds
    }

    // translators: %s: number of hours
    $display_text = sprintf( esc_html__( 'Every %s hours', 'debug-toggle' ), $interval_hours );

    $schedules['debug_toggle_interval'] = array(
        'interval' => $interval_hours * 3600,
        'display'  => $display_text,
    );

    return $schedules;
}

// Activation hook to set default options
register_activation_hook( __FILE__, 'debug_toggle_activate' );
function debug_toggle_activate() {
    // Set default options for each debug constant
    $debug_constants = debug_toggle_get_constants();
    foreach ( $debug_constants as $constant ) {
        $option_name = 'debug_toggle_' . strtolower( $constant );
        add_option( $option_name, 'disabled' ); // Default to 'disabled'
    }

    // Set other default options
    add_option( 'debug_toggle_monitoring', 'enabled' );
    add_option( 'debug_toggle_monitoring_interval', 3 ); // Default interval is 3 hours
    add_option( 'debug_toggle_admin_bar', 'enabled' );
    add_option( 'debug_toggle_remove_data_on_uninstall', 'disabled' ); // Default is disabled

    // Remove any existing debug constants added by the plugin
    debug_toggle_remove_debug_constants();

    // Remove individual debug constants from wp-config.php
    debug_toggle_remove_individual_debug_constants();

    // Update wp-config.php
    debug_toggle_update_wp_config();

    // Schedule the monitoring event if enabled
    if ( get_option( 'debug_toggle_monitoring', 'enabled' ) === 'enabled' ) {
        debug_toggle_schedule_monitoring_event();
    }
}

// Deactivation hook
register_deactivation_hook( __FILE__, 'debug_toggle_deactivate' );
function debug_toggle_deactivate() {
    wp_clear_scheduled_hook( 'debug_toggle_scheduled_monitoring' );

    // Remove debug constants from wp-config.php upon deactivation
    debug_toggle_remove_debug_constants();
}

// Uninstall hook to remove plugin data if the option is enabled
register_uninstall_hook( __FILE__, 'debug_toggle_uninstall' );
function debug_toggle_uninstall() {
    $remove_data = get_option( 'debug_toggle_remove_data_on_uninstall', 'disabled' );
    if ( $remove_data === 'enabled' ) {
        // Delete plugin options
        $debug_constants = debug_toggle_get_constants();
        foreach ( $debug_constants as $constant ) {
            $option_name = 'debug_toggle_' . strtolower( $constant );
            delete_option( $option_name );
        }

        delete_option( 'debug_toggle_monitoring' );
        delete_option( 'debug_toggle_monitoring_interval' );
        delete_option( 'debug_toggle_admin_bar' );
        delete_option( 'debug_toggle_remove_data_on_uninstall' );

        // Clear scheduled event
        wp_clear_scheduled_hook( 'debug_toggle_scheduled_monitoring' );

        // Remove debug constants from wp-config.php
        debug_toggle_remove_debug_constants();
    }
}

// Function to remove debug constants from wp-config.php (plugin-added section)
function debug_toggle_remove_debug_constants() {
    global $wp_filesystem;

    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    WP_Filesystem();

    $wp_config_path = ABSPATH . 'wp-config.php';

    if ( $wp_filesystem->exists( $wp_config_path ) && $wp_filesystem->is_writable( $wp_config_path ) ) {
        $config_file = $wp_filesystem->get_contents( $wp_config_path );

        if ( false === $config_file ) {
            return false;
        }

        // Remove existing plugin-defined constants, including any surrounding whitespace
        $start_marker = '// Debug Toggle Constants Start';
        $end_marker   = '// Debug Toggle Constants End';
        $pattern_plugin_constants = '/\s*' . preg_quote( $start_marker, '/' ) . '.*?' . preg_quote( $end_marker, '/' ) . '\s*/s';
        $config_file = preg_replace( $pattern_plugin_constants, '', $config_file );

        // Write the updated config back to the file
        $result = $wp_filesystem->put_contents( $wp_config_path, $config_file, FS_CHMOD_FILE );

        return $result;
    }

    return false;
}

// Function to remove individual debug constants from wp-config.php
function debug_toggle_remove_individual_debug_constants() {
    global $wp_filesystem;

    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    WP_Filesystem();

    $wp_config_path = ABSPATH . 'wp-config.php';

    if ( $wp_filesystem->exists( $wp_config_path ) && $wp_filesystem->is_writable( $wp_config_path ) ) {
        $config_file = $wp_filesystem->get_contents( $wp_config_path );

        if ( false === $config_file ) {
            return false;
        }

        // Remove individual debug constants
        $debug_constants = debug_toggle_get_constants();
        foreach ( $debug_constants as $constant ) {
            $pattern = '/define\(\s*[\'"]' . preg_quote( $constant, '/' ) . '[\'"]\s*,\s*(true|false)\s*\)\s*;\s*/i';
            $config_file = preg_replace( $pattern, '', $config_file );
        }

        // Write the updated config back to the file
        $result = $wp_filesystem->put_contents( $wp_config_path, $config_file, FS_CHMOD_FILE );

        return $result;
    }

    return false;
}

// Schedule the monitoring event based on the interval
function debug_toggle_schedule_monitoring_event() {
    wp_clear_scheduled_hook( 'debug_toggle_scheduled_monitoring' );

    $interval_hours = intval( get_option( 'debug_toggle_monitoring_interval', 3 ) );
    if ( $interval_hours < 1 || $interval_hours > 24 ) {
        $interval_hours = 3; // Ensure the interval is within bounds
    }

    if ( ! wp_next_scheduled( 'debug_toggle_scheduled_monitoring' ) ) {
        wp_schedule_event( time(), 'debug_toggle_interval', 'debug_toggle_scheduled_monitoring' );
    }
}

// The scheduled event function
add_action( 'debug_toggle_scheduled_monitoring', 'debug_toggle_scheduled_monitoring' );
function debug_toggle_scheduled_monitoring() {
    debug_toggle_restore_debug_settings();
}

// Restore debug settings if they've been changed
function debug_toggle_restore_debug_settings() {
    // Set all debug settings to 'disabled' and update wp-config.php
    $debug_constants = debug_toggle_get_constants();
    foreach ( $debug_constants as $constant ) {
        $option_name = 'debug_toggle_' . strtolower( $constant );
        update_option( $option_name, 'disabled' );
    }
    debug_toggle_update_wp_config();
}

// Add the settings page to the admin menu
add_action( 'admin_menu', 'debug_toggle_menu' );
function debug_toggle_menu() {
    add_options_page(
        esc_html__( 'Debug Toggle', 'debug-toggle' ),
        esc_html__( 'Debug Toggle', 'debug-toggle' ),
        'manage_options',
        'debug-toggle',
        'debug_toggle_options_page'
    );
}

// Display the settings page
function debug_toggle_options_page() {
    if ( ! current_user_can( 'manage_options' ) )  {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'debug-toggle' ) );
    }

    debug_toggle_handle_form_submission();

    // Display the settings page
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Debug Toggle', 'debug-toggle' ) . '</h1>';

    if ( isset( $_GET['updated'] ) && $_GET['updated'] == 'true' ) {
        echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings updated successfully.', 'debug-toggle' ) . '</p></div>';
    }

    debug_toggle_display_settings_form();

    echo '</div>';
}

// Handle form submission for settings page
function debug_toggle_handle_form_submission() {
    if ( isset( $_POST['debug_toggle_nonce_field'] ) && check_admin_referer( 'debug_toggle_nonce_action', 'debug_toggle_nonce_field' ) ) {
        // Nonce verified, process form data

        // Save monitoring option
        $monitoring = isset( $_POST['debug_toggle_monitoring'] ) ? 'enabled' : 'disabled';
        update_option( 'debug_toggle_monitoring', $monitoring );

        // Save monitoring interval
        if ( isset( $_POST['debug_toggle_monitoring_interval'] ) ) {
            $interval = intval( $_POST['debug_toggle_monitoring_interval'] );
            if ( $interval < 1 || $interval > 24 ) {
                $interval = 3; // Default to 3 hours if out of bounds
            }
            update_option( 'debug_toggle_monitoring_interval', $interval );
        }

        // Schedule or clear the monitoring event
        if ( $monitoring === 'enabled' ) {
            debug_toggle_schedule_monitoring_event();
        } else {
            wp_clear_scheduled_hook( 'debug_toggle_scheduled_monitoring' );
        }

        // Save admin bar option
        $admin_bar = isset( $_POST['debug_toggle_admin_bar'] ) ? 'enabled' : 'disabled';
        update_option( 'debug_toggle_admin_bar', $admin_bar );

        // Save remove data on uninstall option
        $remove_data = isset( $_POST['debug_toggle_remove_data_on_uninstall'] ) ? 'enabled' : 'disabled';
        update_option( 'debug_toggle_remove_data_on_uninstall', $remove_data );

        // Handle debug constants settings
        $debug_constants = debug_toggle_get_constants();

        if ( $monitoring === 'enabled' ) {
            // If monitoring is enabled, set all debug settings to 'disabled'
            foreach ( $debug_constants as $constant ) {
                $option_name = 'debug_toggle_' . strtolower( $constant );
                update_option( $option_name, 'disabled' );
            }
        } else {
            // If monitoring is disabled, allow individual settings
            foreach ( $debug_constants as $constant ) {
                $option_name = 'debug_toggle_' . strtolower( $constant );
                $new_value = ( isset( $_POST[ $option_name ] ) && $_POST[ $option_name ] === 'enabled' ) ? 'enabled' : 'disabled';
                update_option( $option_name, $new_value );
            }
        }

        // Update wp-config.php to match the settings
        $result = debug_toggle_update_wp_config();

        if ( $result ) {
            // Redirect to avoid resubmission
            if ( isset( $_SERVER['REQUEST_URI'] ) ) {
                wp_redirect( add_query_arg( 'updated', 'true', esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) );
                exit;
            }
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Failed to write to wp-config.php file. Please check file permissions.', 'debug-toggle' ) . '</p></div>';
        }
    }
}

// Update the wp-config.php file
function debug_toggle_update_wp_config() {
    global $wp_filesystem;

    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    WP_Filesystem();

    $debug_constants = debug_toggle_get_constants();
    $wp_config_path = ABSPATH . 'wp-config.php';

    if ( $wp_filesystem->exists( $wp_config_path ) && $wp_filesystem->is_writable( $wp_config_path ) ) {
        $config_file = $wp_filesystem->get_contents( $wp_config_path );

        if ( false === $config_file ) {
            return false;
        }

        // Remove existing plugin-defined constants, including any surrounding whitespace
        $start_marker = '// Debug Toggle Constants Start';
        $end_marker   = '// Debug Toggle Constants End';
        $pattern_plugin_constants = '/\s*' . preg_quote( $start_marker, '/' ) . '.*?' . preg_quote( $end_marker, '/' ) . '\s*/s';
        $config_file = preg_replace( $pattern_plugin_constants, '', $config_file );

        // Build new definitions without extra newlines
        $new_definitions = $start_marker . "\n";
        foreach ( $debug_constants as $constant ) {
            $option_name = 'debug_toggle_' . strtolower( $constant );
            $value = get_option( $option_name, 'disabled' ) === 'enabled' ? 'true' : 'false';
            $new_definitions .= "define( '$constant', $value );\n";
        }
        $new_definitions .= $end_marker;

        // Ensure we don't add settings below "That's all" comment
        $thats_all_pattern = '/\/\*\s*That\'s all.*?stop editing.*?Happy (blogging|publishing).*?\*\//is';

        if ( preg_match( $thats_all_pattern, $config_file, $matches, PREG_OFFSET_CAPTURE ) ) {
            $position = $matches[0][1]; // Position of the "That's all" comment
            $config_before = substr( $config_file, 0, $position );
            $config_after = substr( $config_file, $position );

            // Insert new definitions before the "That's all" comment
            $config_file = rtrim( $config_before ) . "\n\n" . $new_definitions . "\n\n" . ltrim( $config_after );
        } else {
            // If the "That's all" comment is not found, append at the end
            $config_file = rtrim( $config_file ) . "\n\n" . $new_definitions;
        }

        // Ensure the final config file has a single trailing newline
        $config_file = rtrim( $config_file ) . "\n";

        // Write the updated config back to the file
        $result = $wp_filesystem->put_contents( $wp_config_path, $config_file, FS_CHMOD_FILE );

        return $result;
    }

    return false;
}

// Display the settings form with current debug settings and dropdowns
function debug_toggle_display_settings_form() {
    $monitoring_enabled    = ( get_option( 'debug_toggle_monitoring', 'enabled' ) === 'enabled' );
    $monitoring_interval   = intval( get_option( 'debug_toggle_monitoring_interval', 3 ) );
    $admin_bar_enabled     = ( get_option( 'debug_toggle_admin_bar', 'enabled' ) === 'enabled' );
    $remove_data_enabled   = ( get_option( 'debug_toggle_remove_data_on_uninstall', 'disabled' ) === 'enabled' );

    echo '<form method="post" action="">';
    wp_nonce_field( 'debug_toggle_nonce_action', 'debug_toggle_nonce_field' );

    // Debug Monitoring Toggle
    echo '<h2>' . esc_html__( 'Debug Monitoring', 'debug-toggle' ) . '</h2>';
    echo '<p>';
    echo '<input type="checkbox" name="debug_toggle_monitoring" id="debug_monitoring" value="enabled" ' . checked( $monitoring_enabled, true, false ) . ' />';
    echo '<label for="debug_monitoring"> ' . esc_html__( 'Enable Debug Monitoring (disables all debug settings and prevents changes).', 'debug-toggle' ) . '</label>';
    echo '</p>';
    echo '<p><small>' . esc_html__( 'When enabled, all debug settings will be turned off and cannot be changed until this feature is disabled. The plugin will periodically check and enforce this setting.', 'debug-toggle' ) . '</small></p>';

    // Monitoring Interval
    echo '<p>';
    echo '<label for="debug_monitoring_interval">' . esc_html__( 'Monitoring Interval (hours):', 'debug-toggle' ) . '</label> ';
    echo '<input type="number" name="debug_toggle_monitoring_interval" id="debug_monitoring_interval" value="' . esc_attr( $monitoring_interval ) . '" min="1" max="24" />';
    echo '<br><small>' . esc_html__( 'Set the interval between 1 and 24 hours.', 'debug-toggle' ) . '</small>';
    echo '</p>';

    // Debug Settings
    echo '<h2>' . esc_html__( 'Current Debug Settings', 'debug-toggle' ) . '</h2>';
    echo '<table class="form-table">';
    $debug_constants = debug_toggle_get_constants();
    foreach ( $debug_constants as $constant ) {
        $option_name   = 'debug_toggle_' . strtolower( $constant );
        $current_value = get_option( $option_name, 'disabled' );
        if ( defined( $constant ) ) {
            $status = constant( $constant ) ? esc_html__( 'Enabled', 'debug-toggle' ) : esc_html__( 'Disabled', 'debug-toggle' );
        } else {
            $status = esc_html__( 'Not Defined', 'debug-toggle' );
        }
        $disabled_attr = $monitoring_enabled ? 'disabled' : '';
        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr( $option_name ) . '">' . esc_html( $constant ) . '</label></th>';
        echo '<td>';
        echo '<select name="' . esc_attr( $option_name ) . '" id="' . esc_attr( $option_name ) . '" ' . esc_attr( $disabled_attr ) . '>';
        echo '<option value="disabled"' . selected( $current_value, 'disabled', false ) . '>' . esc_html__( 'Disabled', 'debug-toggle' ) . '</option>';
        echo '<option value="enabled"' . selected( $current_value, 'enabled', false ) . '>' . esc_html__( 'Enabled', 'debug-toggle' ) . '</option>';
        echo '</select> ';
        echo '<span>' . esc_html__( 'Current Status:', 'debug-toggle' ) . ' <strong>' . esc_html( $status ) . '</strong></span>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</table>';

    // Admin Bar Feature Toggle
    echo '<h2>' . esc_html__( 'Admin Bar Feature', 'debug-toggle' ) . '</h2>';
    echo '<p>';
    echo '<input type="checkbox" name="debug_toggle_admin_bar" id="admin_bar_feature" value="enabled" ' . checked( $admin_bar_enabled, true, false ) . ' />';
    echo '<label for="admin_bar_feature"> ' . esc_html__( 'Enable the admin bar menu for quick debug mode toggling.', 'debug-toggle' ) . '</label>';
    echo '</p>';

    // Remove Data on Uninstall Option
    echo '<h2>' . esc_html__( 'Uninstall Options', 'debug-toggle' ) . '</h2>';
    echo '<p>';
    echo '<input type="checkbox" name="debug_toggle_remove_data_on_uninstall" id="remove_data_on_uninstall" value="enabled" ' . checked( $remove_data_enabled, true, false ) . ' />';
    echo '<label for="remove_data_on_uninstall"> ' . esc_html__( 'Remove all plugin data upon uninstall.', 'debug-toggle' ) . '</label>';
    echo '<br><small>' . esc_html__( 'If enabled, all plugin settings and debug constants added to wp-config.php will be deleted when you uninstall the plugin.', 'debug-toggle' ) . '</small>';
    echo '</p>';

    // Submit Button
    echo '<p>';
    echo '<input type="submit" class="button button-primary" value="' . esc_attr__( 'Save Changes', 'debug-toggle' ) . '" />';
    echo '</p>';
    echo '</form>';
}

// Check if debug mode is enabled via this plugin
function debug_toggle_is_debug_enabled() {
    return defined( 'WP_DEBUG' ) && WP_DEBUG;
}

// Conditionally add admin bar menu if the feature is enabled
function debug_toggle_maybe_add_admin_bar_menu() {
    if ( get_option( 'debug_toggle_admin_bar', 'enabled' ) === 'enabled' ) {
        add_action( 'admin_bar_menu', 'debug_toggle_admin_bar_menu', 100 );
    }
}
add_action( 'init', 'debug_toggle_maybe_add_admin_bar_menu' );

function debug_toggle_admin_bar_menu( $wp_admin_bar ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $debug_enabled = debug_toggle_is_debug_enabled();
    $args = array(
        'id'    => 'debug_toggle',
        'title' => esc_html__( 'Debug Mode: ', 'debug-toggle' ) . ( $debug_enabled ? esc_html__( 'Enabled', 'debug-toggle' ) : esc_html__( 'Disabled', 'debug-toggle' ) ),
        'meta'  => array( 'class' => 'debug-toggle-admin-bar' ),
    );
    $wp_admin_bar->add_node( $args );

    $wp_admin_bar->add_node( array(
        'id'     => 'debug_toggle_enable',
        'title'  => esc_html__( 'Enable All Debug Modes', 'debug-toggle' ),
        'parent' => 'debug_toggle',
        'href'   => wp_nonce_url(
            add_query_arg( 'debug_toggle_action', 'enable_all', admin_url() ),
            'debug_toggle_admin_bar_nonce_action',
            'debug_toggle_admin_bar_nonce_field'
        ),
        'meta'   => array( 'title' => esc_html__( 'Enable All Debug Modes', 'debug-toggle' ) ),
    ) );

    $wp_admin_bar->add_node( array(
        'id'     => 'debug_toggle_disable',
        'title'  => esc_html__( 'Disable All Debug Modes', 'debug-toggle' ),
        'parent' => 'debug_toggle',
        'href'   => wp_nonce_url(
            add_query_arg( 'debug_toggle_action', 'disable_all', admin_url() ),
            'debug_toggle_admin_bar_nonce_action',
            'debug_toggle_admin_bar_nonce_field'
        ),
        'meta'   => array( 'title' => esc_html__( 'Disable All Debug Modes', 'debug-toggle' ) ),
    ) );

    // Add link to settings page
    $wp_admin_bar->add_node( array(
        'id'     => 'debug_toggle_settings',
        'title'  => esc_html__( 'Settings', 'debug-toggle' ),
        'parent' => 'debug_toggle',
        'href'   => admin_url( 'options-general.php?page=debug-toggle' ),
        'meta'   => array( 'title' => esc_html__( 'Debug Toggle Settings', 'debug-toggle' ) ),
    ) );
}

// Handle admin bar actions
add_action( 'admin_init', 'debug_toggle_admin_bar_action' );
function debug_toggle_admin_bar_action() {
    if ( isset( $_GET['debug_toggle_action'] ) ) {
        $action = sanitize_text_field( wp_unslash( $_GET['debug_toggle_action'] ) );
        if ( in_array( $action, array( 'enable_all', 'disable_all' ), true ) ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'debug-toggle' ) );
            }

            if ( check_admin_referer( 'debug_toggle_admin_bar_nonce_action', 'debug_toggle_admin_bar_nonce_field' ) ) {
                // Update all debug constants based on action
                $debug_constants = debug_toggle_get_constants();
                $new_value = $action === 'enable_all' ? 'enabled' : 'disabled';
                foreach ( $debug_constants as $constant ) {
                    $option_name = 'debug_toggle_' . strtolower( $constant );
                    update_option( $option_name, $new_value );
                }

                // Disable Debug Monitoring temporarily
                update_option( 'debug_toggle_monitoring', 'disabled' );
                wp_clear_scheduled_hook( 'debug_toggle_scheduled_monitoring' );

                $result = debug_toggle_update_wp_config();

                if ( $result ) {
                    wp_safe_redirect( remove_query_arg( array( 'debug_toggle_action', 'debug_toggle_admin_bar_nonce_field' ) ) );
                    exit;
                } else {
                    add_action( 'admin_notices', function() {
                        echo '<div class="notice notice-error"><p>' . esc_html__( 'Failed to write to wp-config.php file. Please check file permissions.', 'debug-toggle' ) . '</p></div>';
                    } );
                }
            }
        }
    }
}

// Add Settings link on plugin page
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'debug_toggle_action_links' );
function debug_toggle_action_links( $links ) {
    $settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=debug-toggle' ) ) . '">' . esc_html__( 'Settings', 'debug-toggle' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}