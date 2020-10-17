<?php
/**
 * License and update functionality for WP Offload SES.
 *
 * @author Delicious Brains
 * @package WP Offload SES
 */

namespace DeliciousBrains\WP_Offload_SES\Pro;

use DeliciousBrains\WP_Offload_SES\WP_Offload_SES;
use DeliciousBrains\WP_Offload_SES\Compatibility_Check;
use DeliciousBrains\WP_Offload_SES\Error;
use DeliciousBrains\WP_Offload_SES\Licences\Delicious_Brains_API_Licences;
use DeliciousBrains\WP_Offload_SES\Licences\Delicious_Brains_API_Plugin;

/**
 * Class Licences_Updates
 *
 * @since 1.0.0
 */
class Licences_Updates extends Delicious_Brains_API_Licences {

	/**
	 * The main WP_Offload_SES plugin class.
	 *
	 * @var WP_Offload_SES
	 */
	private $wposes;

	/**
	 * Construct the Licenses_Updates class
	 *
	 * @param WP_Offload_SES $wposes The main WP_Offload_SES plugin class.
	 */
	public function __construct( WP_Offload_SES $wposes ) {
		$this->wposes = $wposes;

		$plugin = new Delicious_Brains_API_Plugin();

		$plugin->global_meta_prefix       = 'wposes';
		$plugin->slug                     = 'wp-offload-ses';
		$plugin->name                     = 'WP Offload SES';
		$plugin->version                  = $GLOBALS['wposes_meta']['wp-offload-ses']['version'];
		$plugin->basename                 = $this->wposes->get_plugin_basename();
		$plugin->dir_path                 = $this->wposes->get_plugin_dir_path();
		$plugin->prefix                   = 'wposes';
		$plugin->settings_url_path        = $this->wposes->get_plugin_pagenow() . '?page=wp-offload-ses';
		$plugin->settings_url_hash        = '#licence';
		$plugin->hook_suffix              = $this->wposes->hook_suffix;
		$plugin->email_address_name       = 'wposes';
		$plugin->notices_hook             = 'wposes_pre_settings_render';
		$plugin->load_hook                = 'wposes_plugin_load';
		$plugin->expired_licence_is_valid = true;
		$plugin->purchase_url             = $this->wposes->dbrains_url(
			'/wp-offload-ses/pricing/',
			array(
				'utm_campaign' => 'WP+Offload+SES',
			)
		);

		parent::__construct( $plugin );

		$this->init();
	}

	/**
	 * Initialize the actions and filters for the class.
	 */
	public function init() {
		add_action( 'admin_notices', array( $this, 'dashboard_licence_issue_notice' ) );
		add_action( 'network_admin_notices', array( $this, 'dashboard_licence_issue_notice' ) );
		add_action( 'wposes_pre_settings_render', array( $this, 'licence_issue_notice' ), 11 );

		add_action( 'wposes_licence_field', array( $this, 'render_licence_settings' ) );
		add_action( 'wposes_support_pre_debug', array( $this, 'render_licence_info' ) );

		add_filter( 'wposes_js_nonces', array( $this, 'add_licence_nonces' ) );
		add_filter( 'wposes_js_strings', array( $this, 'add_licence_strings' ) );

		add_action( 'wposes_plugin_load', array( $this, 'http_dismiss_licence_notice' ) );
		add_action( 'wposes_http_refresh_licence', array( $this, 'do_http_refresh_licence' ) );
		add_action( 'wposes_activate_licence_response', array( $this, 'refresh_licence_notice' ) );
		add_action( 'wposes_ajax_check_licence_response', array( $this, 'refresh_licence_notice' ) );
		add_filter( 'wposes_licence_status_message', array( $this, 'licence_status_message' ), 10, 2 );
		add_filter( 'wposes_pre_plugin_row_update_notice', array( $this, 'suppress_plugin_row_update_notices' ), 10, 2 );
		add_action( 'check_admin_referer', array( $this, 'block_updates_with_invalid_license' ) );
	}

	/**
	 * Accessor for the licence key
	 *
	 * @return int|mixed|string|\WP_Error
	 */
	protected function get_plugin_licence_key() {
		return $this->wposes->settings->get_setting( 'licence' );
	}

	/**
	 * Setter for the licence key
	 *
	 * @param string $key The license key.
	 *
	 * @return void
	 */
	protected function set_plugin_licence_key( $key ) {
		$this->wposes->settings->get_settings();
		$this->wposes->settings->set_setting( 'licence', $key );
		$this->wposes->settings->save_settings();
	}

	/**
	 * Display the licence form.
	 */
	public function render_licence_settings() {
		$this->wposes->render_view(
			'pro/license-settings',
			array(
				'is_defined'     => $this->is_licence_constant(),
				'is_set'         => (bool) $this->get_licence_key(),
				'masked_licence' => $this->get_masked_licence(),
			)
		);
	}

	/**
	 * Display the licence details and support form.
	 *
	 * @return void
	 */
	public function render_licence_info() {
		$args = array( 'licence' => $this->get_plugin_licence_key() );
		$this->wposes->render_view( 'pro/licence-info', $args );
	}

	/**
	 * Add more nonces to the wposes Javascript object
	 *
	 * @param array $nonces The existing nonces in the JS object.
	 *
	 * @return array
	 */
	public function add_licence_nonces( $nonces ) {
		$nonces['check_licence']      = wp_create_nonce( 'check-licence' );
		$nonces['activate_licence']   = wp_create_nonce( 'activate-licence' );
		$nonces['remove_licence']     = wp_create_nonce( 'remove-licence' );
		$nonces['reactivate_licence'] = wp_create_nonce( 'reactivate-licence' );

		return $nonces;
	}

	/**
	 * Add licence-related strings to wposes Javascript object
	 *
	 * @param array $strings The existing strings in the JS object.
	 *
	 * @return array
	 */
	public function add_licence_strings( $strings ) {
		$licence_strings = array(
			'license_check_problem'          => __( 'A problem occurred when trying to check the license, please try again.', 'wp-offload-ses' ),
			'has_licence'                    => esc_html( $this->get_licence_key() == '' ? '0' : '1' ),
			'enter_licence_key'              => __( 'Please enter your license key.', 'wp-offload-ses' ),
			'register_license_problem'       => __( 'A problem occurred when trying to register the license, please try again.', 'wp-offload-ses' ),
			'license_registered'             => __( 'Your license has been activated. You will now receive automatic updates and access to email support.', 'wp-offload-ses' ),
			'fetching_license'               => __( 'Fetching license details, please wait&hellip;', 'wp-offload-ses' ),
			'activate_licence_problem'       => __( 'An error occurred when trying to reactivate your license. Please contact support.', 'wp-offload-ses' ),
			'attempting_to_activate_licence' => __( 'Attempting to activate your license, please wait&hellip;', 'wp-offload-ses' ),
			'status'                         => _x( 'Status', 'Current request status', 'wp-offload-ses' ),
			'response'                       => _x( 'Response', 'The message the server responded with', 'wp-offload-ses' ),
			'licence_reactivated'            => __( 'License successfully activated, please wait&hellip;', 'wp-offload-ses' ),
			'temporarily_activated_licence'  => __( "<strong>We've temporarily activated your licence and will complete the activation once the Delicious Brains API is available again.</strong><br />Please refresh this page to continue.", 'wp-offload-ses' ),
		);

		return array_merge( $strings, $licence_strings );
	}

	/**
	 * Clear the media attachment transients when we refresh the license
	 */
	public function do_http_refresh_licence() {
		// Force a check of the license again as we aren't hitting the support tab.
		$licence = $this->get_licence_key();
		$this->check_licence( $licence );
	}

	/**
	 * Helper for creating nonced action URLs
	 *
	 * @param string $action           The action to nonce.
	 * @param bool   $send_to_settings Send back to settings tab.
	 * @param bool   $dashboard        True if displaying elsewhere in the dashboard.
	 *
	 * @return string
	 */
	public function get_licence_notice_url( $action, $send_to_settings = true, $dashboard = false ) {
		$action     = $this->plugin->prefix . '-' . $action;
		$query_args = array(
			'nonce' => wp_create_nonce( $action ),
			$action => 1,
		);

		if ( $dashboard ) {
			$query_args['sendback'] = urlencode( $_SERVER['REQUEST_URI'] ); // phpcs:ignore
		}

		$path = $this->plugin->settings_url_path;
		if ( $send_to_settings ) {
			$path .= $this->plugin->settings_url_hash;
		}

		$url = add_query_arg( $query_args, $this->admin_url( $path ) );

		return $url;
	}

	/**
	 * Display our license issue notice which covers -
	 *  - No license
	 *  - Expired licenses
	 *  - Reached the site limit
	 *
	 * @param bool $dashboard      If we are displaying across the dashboard.
	 * @param bool $skip_transient If the transient should be skipped.
	 */
	public function licence_issue_notice( $dashboard = false, $skip_transient = false ) {
		if ( ! $this->wposes->is_plugin_setup() ) {
			// Don't show the notice if basic plugin requirements are not met.
			return;
		}

		if ( $dashboard && method_exists( 'Compatibility_Check', 'is_installing_or_updating_plugins' ) && Compatibility_Check::is_installing_or_updating_plugins() ) {
			// Don't show the notice for plugin installs & updates, just too much noise.
			return;
		}

		$license_check = $this->is_licence_expired();
		$args          = compact( 'dashboard' );

		if ( ! empty( $license_check['errors']['no_licence'] ) ) {
			$this->display_no_licence_notice( $args );
			return;
		}

		if ( ! empty( $license_check['errors']['subscription_expired'] ) ) {
			$this->display_expired_licence_notice( $args );
		} elseif ( ! isset( $license_check['errors'] ) ) {
			$this->clear_licence_issue();
		}
	}

	/**
	 * Display the notice for a missing licence.
	 *
	 * @param array $args Args to render.
	 */
	protected function display_no_licence_notice( $args ) {
		if ( $args['dashboard'] ) {
			return;
		}

		$license_check = $this->is_licence_expired();

		$this->render_licence_notice( array_merge( $args, array(
			'title'   => __( 'Activate Your License', 'wp-offload-ses' ),
			'message' => $license_check['errors']['no_licence'],
			'type'    => 'no_licence',
			'links'   => array( 'check_again' ),
		) ) );
	}

	/**
	 * Display the notice for an expired licence.
	 *
	 * @param array $args Args to render.
	 */
	protected function display_expired_licence_notice( $args ) {
		if ( $args['dashboard'] ) {
			$title = sprintf( __( 'Your %s License Has Expired', 'wp-offload-ses' ), $this->plugin->name );
		} else {
			$title = __( 'Your License Has Expired', 'wp-offload-ses' );
		}

		$this->render_licence_notice( array_merge( $args, array(
			'title'   => $title,
			'message' => __( 'All features will continue to work, but you won\'t have access to software updates or email support.', 'wp-offload-ses' ),
			'type'    => 'subscription_expired',
			'links'   => array( 'renew_now', 'check_again' ),
		) ) );
	}

	/**
	 * Render a licence notice.
	 *
	 * @param array $args Args to render.
	 */
	public function render_licence_notice( $args = array() ) {
		$args = array_merge( array(
			'title'       => '',
			'type'        => '',
			'message'     => '',
			'extra'       => '',
			'links'       => array(),
			'dashboard'   => false,
			'dismissible' => false,
			'dismiss_url' => '',
		), $args );

		// Don't show if not in network admin.
		if ( is_multisite() && ! is_network_admin() ) {
			return;
		}

		// Don't show if current user has dismissed notice.
		if ( $args['dashboard'] && get_user_meta( get_current_user_id(), $this->plugin->prefix . '-dismiss-licence-notice' ) ) {
			return;
		}

		if ( $args['dashboard'] ) {
			$args['dismissible'] = true;
			$args['dismiss_url'] = $this->get_licence_notice_url( 'dismiss-licence-notice', false, true );
		}

		$link_map = array(
			'upgrade_now' => sprintf( '<a href="%s" class="wposes-upgrade-now">%s</a>', $this->wposes->get_my_account_url(), __( 'Upgrade Your License Now', 'wp-offload-ses' ) ),
			'renew_now'   => sprintf( '<a href="%s" class="wposes-renew-now">%s</a>', $this->wposes->get_my_account_url(), __( 'Renew Your License Now', 'wp-offload-ses' ) ),
			'check_again' => sprintf( '<a href="%s" class="wposes-check-again">%s</a>', $this->get_licence_notice_url( 'check-licence', true, $args['dashboard'] ), __( 'Check again', 'wp-offload-ses' ) ),
		);

		if ( ! empty( $args['links'] ) ) {
			$args['links'] = array_map( function ( $link ) use ( $link_map ) {
				return isset( $link_map[ $link ] ) ? $link_map[ $link ] : $link;
			}, $args['links'] );
		}

		$this->wposes->render_view( 'pro/licence-notice', $args );
		$this->update_licence_issue( $args['type'] );
	}

	/**
	 * Update the saved license issue type.
	 *
	 * @param string $type The type of issue.
	 */
	protected function update_licence_issue( $type ) {
		if ( $type !== get_site_option( $this->plugin->prefix . '_licence_issue_type' ) ) {
			// Delete the dismissed flag for the user.
			delete_user_meta( get_current_user_id(), $this->plugin->prefix . '-dismiss-licence-notice' );

			// Store the type of issue for comparison later.
			update_site_option( $this->plugin->prefix . '_licence_issue_type', $type );
		}
	}

	/**
	 * Clear the saved licence issue type.
	 */
	protected function clear_licence_issue() {
		delete_site_option( $this->plugin->prefix . '_licence_issue_type' );
	}

	/**
	 * Dismiss the license issue notice
	 */
	public function http_dismiss_licence_notice() {
		if ( isset( $_GET[ $this->plugin->prefix . '-dismiss-licence-notice' ] ) && wp_verify_nonce( $_GET['nonce'], $this->plugin->prefix . '-dismiss-licence-notice' ) ) { // input var okay
			$hash = ( isset( $_GET['hash'] ) ) ? '#' . sanitize_title( $_GET['hash'] ) : ''; // input var okay
			// Store the dismissed flag against the user.
			update_user_meta( get_current_user_id(), $this->plugin->prefix . '-dismiss-licence-notice', true );
			$sendback = filter_input( INPUT_GET, 'sendback' ) ?: $this->admin_url( $this->plugin->settings_url_path . $hash );
			// Redirecting because we don't want to keep the query string in the web browsers address bar.
			wp_safe_redirect( $sendback );
			exit;
		}
	}

	/**
	 * Display the license issue notice site wide except on our plugin page
	 */
	public function dashboard_licence_issue_notice() {
		if ( isset( $_GET['page'] ) && 'wp-offload-ses' === $_GET['page'] ) {
			return;
		}
		global $wposes_compat_check;
		if ( ! $wposes_compat_check->check_capabilities() ) {
			return;
		}
		return $this->licence_issue_notice( true );
	}

	/**
	 * Return the custom license error to the API when activating / checking a license
	 *
	 * @param array $decoded_response The decoded response.
	 *
	 * @return array
	 */
	public function refresh_licence_notice( $decoded_response ) {
		ob_start();
		$this->licence_issue_notice( false, true );
		$licence_error = ob_get_contents();
		ob_end_clean();

		if ( $licence_error ) {
			$license                       = $this->is_licence_expired();
			$decoded_response['errors']    = empty( $license['errors'] ) ? '' : $license['errors'];
			$decoded_response['pro_error'] = $licence_error;
		}

		return $decoded_response;
	}

	/**
	 * Override the default license expired message for the email support section
	 *
	 * @param string $message The status message.
	 * @param array  $errors  The errors array.
	 *
	 * @return string
	 */
	public function licence_status_message( $message, $errors ) {
		if ( isset( $errors['subscription_expired'] ) ) {
			$check_licence_again_url = $this->admin_url( $this->plugin->settings_url_path . '&nonce=' . wp_create_nonce( $this->plugin->prefix . '-check-licence' ) . '&' . $this->plugin->prefix . '-check-licence=1' . $this->plugin->settings_url_hash );

			$url     = $this->wposes->dbrains_url( '/my-account/', array(
				'utm_campaign' => 'error+messages',
			) );
			$message = sprintf( __( '<strong>Your License Has Expired</strong> &mdash; Please visit <a href="%s" target="_blank">My Account</a> to renew your license and continue receiving access to email support.' ), $url ) . ' ';
			$message .= sprintf( '<a href="%s">%s</a>', $check_licence_again_url, __( 'Check again' ) );
		}

		return $message;
	}

	/**
	 * Don't show plugin row update notices when AWS not set up
	 *
	 * @param bool  $pre
	 * @param array $licence_response
	 *
	 * @return bool
	 */
	public function suppress_plugin_row_update_notices( $pre, $licence_response ) {
		if ( isset( $licence_response['errors']['no_licence'] ) && ! $this->wposes->get_aws()->are_access_keys_set() ) {
			// Don't show the activate license notice if we haven't set up AWS keys.
			return true;
		}

		return $pre;
	}

	/**
	 * Throw a nonce error if trying to update the plugin or addons
	 * with a missing or invalid license
	 *
	 * @param string     $action
	 * @param bool|false $result
	 *
	 * @return bool
	 */
	public function block_updates_with_invalid_license( $action, $result = false ) {
		if ( 'bulk-update-plugins' !== $action ) {
			return $result;
		}

		if ( ! isset( $_GET['plugins'] ) && ! isset( $_POST['checked'] ) ) {
			return $result;
		}

		if ( isset( $_GET['plugins'] ) ) {
			$plugins = explode( ',', stripslashes( $_GET['plugins'] ) );
		} elseif ( isset( $_POST['checked'] ) ) {
			$plugins = (array) $_POST['checked'];
		} else {
			// No plugins selected at all, move on
			return $result;
		}

		$plugins          = array_map( 'urldecode', $plugins );
		$our_plugins      = array_keys( $this->addons );
		$our_plugins[]    = $this->plugin->basename;
		$matching_plugins = array_intersect( $plugins, $our_plugins );

		if ( empty( $matching_plugins ) ) {
			// None of our addons or plugin are being updated
			return $result;
		}

		$licence_check = $this->is_licence_expired( true );

		foreach ( $plugins as $plugin ) {
			if ( in_array( $plugin, $our_plugins ) ) {
				$plugin_name   = $this->plugin->name;
				$parent_plugin = '';
				if ( isset( $this->addons[ $plugin ] ) ) {
					$plugin_name   = $this->addons[ $plugin ]['name'];
					$parent_plugin = ' ' . sprintf( __( 'for %s', 'wp-offload-ses' ), $this->plugin->name );
				}

				if ( isset( $licence_check['errors']['no_licence'] ) ) {
					$html = sprintf( __( '<strong>Activate Your License</strong> &mdash; You can only update %1$s with a valid license key%2$s.', 'wp-offload-ses' ), $plugin_name, $parent_plugin );
					$html .= '</p><p><a target="_parent" href="' . $this->admin_url( $this->plugin->settings_url_path ) . $this->plugin->settings_url_hash . '">' . _x( 'Activate', 'Activate license', 'wp-offload-ses' ) . '</a> | ';
					$html .= '<a target="_parent" href="' . $this->plugin->purchase_url . '">' . _x( 'Purchase', 'Purchase license', 'wp-offload-ses' ) . '</a>';
				} else if ( isset( $licence_check['errors']['subscription_expired'] ) ) {
					$html = sprintf( __( '<strong>Your License Has Expired</strong> &mdash; You can only update %1$s with a valid license key%2$s. Please visit <a href="%3$s" target="_parent">My Account</a> to renew your license and continue receiving plugin updates.', 'wp-offload-ses' ), $plugin_name, $parent_plugin, $this->plugin->account_url );
				} else {
					// License valid, move along
					return $result;
				}

				if ( isset( $_GET['plugins'] ) ) {
					$clean_plugin = addslashes( urlencode( $plugin ) );

					// Check for assortment of versions of plugin, with leading commas
					$needles = array(
						',' . $plugin,
						',' . $clean_plugin,
						$plugin,
						$clean_plugin,
					);

					// Remove plugin from the global var
					$_GET['plugins'] = str_replace( $needles, '', $_GET['plugins'] );

					if ( '' === $_GET['plugins'] ) {
						// No plugins, remove the var
						unset( $_GET['plugins'] );
					}

				} elseif ( isset( $_POST['checked'] ) ) {
					foreach ( $_POST['checked'] as $key => $checked_plugin ) {
						if ( in_array( $checked_plugin, array( $plugin, urlencode( $plugin ) ) ) ) {
							// Remove plugin from the global var
							unset( $_POST['checked'][ $key ] );
						}

						if ( empty( $_POST['checked'] ) ) {
							// No plugins, remove the var
							unset( $_POST['checked'] );
						}
					}
				}

				// Display license error notice
				$this->wposes->render_view( 'error-fatal', array( 'message' => $html ) );
			}
		}

		return $result;
	}

	/**
	 * Error log method
	 *
	 * @param mixed $error                The error message.
	 * @param bool  $additional_error_var Any additional info to log.
	 */
	public function log_error( $error, $additional_error_var = false ) {
		if ( false !== $additional_error_var ) {
			$error = new Error( 301, $error, $additional_error_var );
		} else {
			$error = new Error( 301, $error );
		}
	}

}
