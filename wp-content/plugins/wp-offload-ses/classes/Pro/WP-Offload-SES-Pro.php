<?php
/**
 * Pro-specific functionality for WP Offload SES.
 *
 * @author  Delicious Brains
 * @package WP Offload SES
 */

namespace DeliciousBrains\WP_Offload_SES\Pro;

use DeliciousBrains\WP_Offload_SES\WP_Offload_SES;
use DeliciousBrains\WP_Offload_SES\Pro\Licences_Updates;
use DeliciousBrains\WP_Offload_SES\Pro\Reports_List_Table;
use DeliciousBrains\WP_Offload_SES\Email;
use DeliciousBrains\WP_Offload_SES\Pro\Pro_Health_Report;

/**
 * Class WP_Offload_SES
 *
 * @since 1.1
 */
class WP_Offload_SES_Pro extends WP_Offload_SES {

	/**
	 * The plugin slug.
	 *
	 * @var string
	 */
	protected $plugin_slug = 'wp-offload-ses';

	/**
	 * The Licences_Updates class.
	 *
	 * @var Licences_Updates
	 */
	private $licence;

	/**
	 * The Pro_Health_Report class.
	 *
	 * @var Pro_Health_Report
	 */
	private $health_report;

	/**
	 * The messages for email notices.
	 *
	 * @var array
	 */
	private $messages;

	/**
	 * Construct the parent class and initialize the plugin.
	 *
	 * @param string $plugin_file_path The plugin file path.
	 */
	public function __construct( $plugin_file_path ) {
		parent::__construct( $plugin_file_path );
	}

	/**
	 * Initialize the plugin.
	 *
	 * @param string $plugin_file_path The plugin file path.
	 */
	public function init( $plugin_file_path ) {
		// Load the plugin.
		parent::init( $plugin_file_path );

		// Load pro-specific classes.
		$this->licence       = new Licences_Updates( $this );
		$this->health_report = new Pro_Health_Report( $this );

		// Pro filters and action hooks.
		add_action( 'wp_ajax_wposes_get_email', array( $this, 'ajax_get_email' ) );
		add_action( 'wp_ajax_process_row_action', array( $this, 'ajax_process_row_action' ) );
		add_action( 'wp_ajax_wposes_reports_table', array( $this, 'ajax_reports_table' ) );
		add_filter( 'wposes_settings_sub_nav_tabs', array( $this, 'pro_sub_nav_tabs' ) );
	}

	/**
	 * Perform plugin upgrade routines.
	 *
	 * @param bool $skip_version_check If the version check should be skipped.
	 */
	public function upgrade_routines( $skip_version_check = false ) {
		$version = get_site_option( 'wposes_version', '0.0.0' );

		if ( $skip_version_check || version_compare( $version, $this->get_plugin_version(), '<' ) ) {
			parent::upgrade_routines( true );

			if ( ! $skip_version_check ) {
				update_site_option( 'wposes_version', $this->get_plugin_version() );
			}
		}
	}

	/**
	 * Enqueue any styles/scripts.
	 */
	public function plugin_load() {
		parent::plugin_load();
		$this->enqueue_script( 'wposes-licence', 'assets/js/pro/licence', array( 'jquery', 'underscore' ) );
		$this->enqueue_script( 'wposes-reports', 'assets/js/pro/reports', array( 'wposes-script' ) );
	}

	/**
	 * Sub nav tabs for the pro plugin.
	 *
	 * @param array $tabs The tabs for the subnav.
	 *
	 * @return array
	 */
	public function pro_sub_nav_tabs( $tabs ) {
		if ( ! is_multisite() || is_network_admin() ) {
			$tabs['licence'] = _x( 'License', 'Show the license tab', 'wp-offload-ses'  );
		}

		return $tabs;
	}

	/**
	 * Getter for Pro_Health_Report.
	 *
	 * @return Pro_Health_Report
	 */
	public function get_health_report() {
		return $this->health_report;
	}

	/**
	 * Check whether this is the free or pro version.
	 *
	 * @return bool
	 */
	public function is_pro() {
		return true;
	}

	/**
	 * Check if the plugin has a valid license.
	 *
	 * @param bool $skip_transient_check True if license transient should be skipped.
	 *
	 * @return bool
	 */
	public function is_valid_licence( $skip_transient_check = false ) {
		if ( is_null( $this->licence ) ) {
			return false;
		}

		return $this->licence->is_valid_licence( $skip_transient_check );
	}

	/**
	 * Retrieve all the email action related notice messages.
	 *
	 * @return array
	 */
	public function get_messages() {
		if ( is_null( $this->messages ) ) {
			$this->messages = array(
				'resend' => array(
					'success' => __( 'Email successfully added to the queue.', 'wp-offload-ses' ),
					'partial' => __( 'Emails added to the queue with some errors.', 'wp-offload-ses' ),
					'error'   => __( 'There were errors when adding the email to the queue.', 'wp-offload-ses' ),
				),
				'cancel' => array(
					'success' => __( 'Email successfully removed from the queue.', 'wp-offload-ses' ),
					'partial' => __( 'Emails removed from the queue with some errors.', 'wp-offload-ses' ),
					'error'   => __( 'There were errors when removing the email from the queue.', 'wp-offload-ses' ),
				),
				'delete' => array(
					'success' => __( 'Email deleted sucessfully.', 'wp-offload-ses' ),
					'partial' => __( 'Emails deleted with some errors.', 'wp-offload-ses' ),
					'error'   => __( 'There were errors deleting the email.', 'wp-offload-ses' ),
				),
			);
		}

		return $this->messages;
	}

	/**
	 * Get a specific email action message.
	 *
	 * @param string $action The type of action, e.g. send, resend, cancel.
	 * @param string $result If the action has resulted in success, partial sucess, error.
	 *
	 * @return string|bool
	 */
	public function get_message( $action, $result ) {
		$messages = $this->get_messages();

		if ( isset( $messages[ $action ][ $result ] ) ) {
			return $messages[ $action ][ $result ];
		}

		return false;
	}

	/**
	 * Get the notice after an action has been performed.
	 *
	 * @param string $action The action that was performed.
	 * @param int    $count  The number of items that were processed.
	 * @param int    $errors The number of errors.
	 *
	 * @return string|bool
	 */
	public function get_email_action_notice( $action, $count = 0, $errors = 0 ) {
		$result = 'success';

		if ( $errors > 0 ) {
			$result = 'error';

			if ( $count > 0 ) {
				$result = 'partial';
			}
		}

		$class   = 'success' === $result ? 'notice-success' : 'notice-error';
		$message = $this->get_message( $action, $result );

		if ( ! $message ) {
			return false;
		}

		$message = sprintf( '<div class="notice wposes-notice %s is-dismissible"><p>%s</p><button type="button" class="notice-dismiss"></button></div>', $class, $message );

		return $message;
	}

	/**
	 * Resend an email with the provided email ID.
	 *
	 * @param int $email_id The ID of the email to resend.
	 *
	 * @return bool
	 */
	public function resend_email( $email_id ) {
		if ( ! $email_id ) {
			return false;
		}

		$email = $this->get_email_log()->get_email( $email_id );

		if ( ! $email ) {
			return false;
		}

		if ( 'cancelled' === $email['email_status'] ) {
			$update         = $this->get_email_log()->update_email( $email_id, 'email_status', 'queued' );
			$email_to_queue = $email_id;
		} else {
			$new_email = array(
				'to'          => $email['email_to'],
				'subject'     => $email['email_subject'],
				'message'     => $email['email_message'],
				'headers'     => $email['email_headers'],
				'attachments' => $email['email_attachments'],
				'subsite_id'  => $email['subsite_id'],
				'parent'      => $email_id,
			);

			$new_email      = $this->get_email_log()->log_email( $new_email );
			$email_to_queue = $new_email;
			$attachments    = $this->get_attachments()->get_attachments_by_email( $email_id );

			// Add attachment meta for new email
			if ( $new_email && ! empty( $attachments ) ) {
				foreach ( $attachments as $attachment ) {
					$this->get_attachments()->add_attachment_to_email( $new_email, $attachment['id'], $attachment['filename'] );
				}
			}
		}

		if ( ! $email_to_queue ) {
			return false;
		}

		$queue = $this->get_email_queue()->process_email( $email_to_queue, $email['subsite_id'] );

		if ( ! $queue ) {
			return false;
		}

		if ( 'failed' === $email['email_status'] ) {
			// Log this as a manual retry.
			$this->get_email_log()->update_email(
				$email_id,
				'manual_retries',
				(int) ++ $email['manual_retries']
			);
		}

		return true;
	}

	/**
	 * Cancel an email with the provided email id.
	 *
	 * @param int $email_id The ID of the email to cancel.
	 *
	 * @return bool
	 */
	public function cancel_email( $email_id ) {
		if ( ! $email_id ) {
			return false;
		}

		$cancel = $this->get_email_queue()->cancel_email( $email_id );

		if ( ! $cancel ) {
			return false;
		}

		$update = $this->get_email_log()->update_email( $email_id, 'email_status', 'cancelled' );

		if ( ! $update ) {
			return false;
		}

		return true;
	}

	/**
	 * Delete an email with the provided email id.
	 *
	 * @param int $email_id The ID of the email to cancel.
	 *
	 * @return bool
	 */
	public function delete_email( $email_id ) {
		if ( ! $email_id ) {
			return false;
		}

		// Cancel the email and remove click events if necessary.
		$this->get_email_queue()->cancel_email( $email_id );
		$this->get_email_events()->delete_links_by_email( $email_id );
		$this->get_attachments()->delete_email_attachments( $email_id );

		// Delete the actual email.
		$delete = $this->get_email_log()->delete_email( $email_id );

		if ( ! $delete ) {
			return false;
		}

		return true;
	}

	/**
	 * Get information about an email with the provided email ID.
	 */
	public function ajax_get_email() {
		check_ajax_referer( 'wposes-activity-nonce', 'wposes_activity_nonce' );

		$email_id = (int) $_POST['email_id'];
		$email    = $this->get_email_log()->get_email( $email_id );

		if ( ! $email ) {
			wp_send_json_error();
		}

		$click_data = $this->get_email_events()->get_email_click_data( $email_id );

		$email_data = array(
			'id'           => $email_id,
			'status'       => $email['email_status'],
			'status_i18n'  => $this->get_email_status_i18n( $email['email_status'] ),
			'open_count'   => $email['email_open_count'],
			'last_opened'  => $email['email_last_open_date'],
			'click_count'  => $click_data['email_click_count'],
			'last_clicked' => $click_data['email_last_click_date'],
			'sent'         => $email['email_sent'],
		);

		$email = new Email(
			$email['email_to'],
			$email['email_subject'],
			$email['email_message'],
			$email['email_headers'],
			$email['email_attachments']
		);

		ob_start();
		$email->view( $email_data );
		$html = ob_get_clean();

		wp_send_json_success( $html );
	}

	/**
	 * Process a row action via AJAX.
	 */
	public function ajax_process_row_action() {
		check_ajax_referer( 'wposes-activity-nonce', 'wposes_activity_nonce' );

		$email_id = filter_input( INPUT_POST, 'email_id', FILTER_VALIDATE_INT );
		$action   = filter_input( INPUT_POST, 'row_action' );
		$actions  = array( 'resend', 'cancel', 'delete' );
		$method   = $action . '_email';

		if ( ! $email_id || ! in_array( $action, $actions, true ) ) {
			wp_send_json_error();
		}

		$result = $this->$method( $email_id );

		if ( ! $result ) {
			wp_send_json_error( $this->get_email_action_notice( $action, 0, 1 ) );
		}

		wp_send_json_success( $this->get_email_action_notice( $action ) );
	}

	/**
	 * Display the reports table over AJAX.
	 */
	public function ajax_reports_table() {
		$reports_table = new Reports_List_Table();
		$reports_table->load();
		$reports_table->ajax_response();
	}

}
