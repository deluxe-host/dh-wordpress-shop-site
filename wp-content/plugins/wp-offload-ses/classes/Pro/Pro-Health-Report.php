<?php
/**
 * Email Health Report for WP Offload SES.
 *
 * @author  Delicious Brains
 * @package WP Offload SES
 */

namespace DeliciousBrains\WP_Offload_SES\Pro;

use DeliciousBrains\WP_Offload_SES\WP_Offload_SES;
use DeliciousBrains\WP_Offload_SES\Health_Report;

/**
 * Class Pro_Health_Report.
 *
 * @since 1.3.0
 */
class Pro_Health_Report extends Health_Report {

	/**
	 * The most records we should show in a table.
	 *
	 * @var int
	 */
	protected $table_limit = 10;

	/**
	 * Constructs the Pro_Health_Report class.
	 *
	 * @param WP_Offload_SES $wp_offload_ses The main WP Offload SES class.
	 */
	public function __construct( WP_Offload_SES $wp_offload_ses ) {
		parent::__construct( $wp_offload_ses );
	}

	/**
	 * Gets the available report frequencies.
	 *
	 * @return array
	 */
	public function get_available_frequencies() {
		$frequencies          = parent::get_available_frequencies();
		$frequencies['daily'] = __( 'Daily', 'wp-offload-ses' );

		return $frequencies;
	}

	/**
	 * Gets the available report recipients.
	 *
	 * @return array
	 */
	public function get_available_recipients() {
		$recipients           = parent::get_available_recipients();
		$recipients['custom'] = __( 'Custom', 'wp-offload-ses' );

		return $recipients;
	}

	/**
	 * Gets the recipients of the report.
	 *
	 * @return array
	 */
	public function get_recipients() {
		if ( $this->is_network_report ) {
			$recipients_setting = $this->wposes->settings->get_network_setting( 'health-report-recipients', 'site-admins' );
		} else {
			$recipients_setting = $this->wposes->settings->get_setting( 'health-report-recipients' );
		}

		if ( 'custom' !== $recipients_setting ) {
			return parent::get_recipients();
		}

		if ( $this->is_network_report ) {
			$recipients = $this->wposes->settings->get_network_setting( 'health-report-custom-recipients', '' );
		} else {
			$recipients = $this->wposes->settings->get_setting( 'health-report-custom-recipients', '' );
		}

		$recipients = str_getcsv( $recipients );
		$recipients = array_map( 'trim', $recipients );

		return $recipients;
	}

	/**
	 * Gets an array of the emails sent, used to populate
	 * the table in the health report.
	 *
	 * @return array
	 */
	public function get_sent_emails() {
		$subsite_sql = '';

		if ( $this->is_subsite_report() ) {
			$subsite_id  = (int) get_current_blog_id();
			$subsite_sql = "AND emails.subsite_id = {$subsite_id}";
		}

		$query = $this->database->prepare(
			"SELECT emails.email_subject AS subject,
				COUNT(DISTINCT emails.email_id) AS emails_sent,
				(SELECT SUM({$this->emails_table}.email_open_count) FROM {$this->emails_table} WHERE {$this->emails_table}.email_subject = subject) AS open_count,
				SUM(clicks.email_click_count) AS click_count
			FROM {$this->emails_table} emails
			LEFT JOIN {$this->clicks_table} clicks ON emails.email_id = clicks.email_id
			WHERE emails.email_created >= %s
			AND emails.email_created <= %s
			{$subsite_sql}
			AND emails.email_status = 'sent'
			GROUP BY subject
			ORDER BY emails_sent DESC
			LIMIT {$this->table_limit}",
			$this->get_report_start_date(),
			$this->get_report_end_date()
		);

		return $this->database->get_results( $query, ARRAY_A );
	}

	/**
	 * Gets an array of emails that failed,
	 * used to populate the table in the health report.
	 *
	 * @return array
	 */
	public function get_failed_emails() {
		$subsite_sql = '';

		if ( $this->is_subsite_report() ) {
			$subsite_id  = (int) get_current_blog_id();
			$subsite_sql = "AND original_emails.subsite_id = {$subsite_id}";
		}

		$query = $this->database->prepare(
			"SELECT original_emails.email_id AS id,
				original_emails.email_subject AS subject,
				original_emails.email_created AS date,
				original_emails.email_to AS recipient
			FROM {$this->emails_table} original_emails
			LEFT JOIN {$this->emails_table} retried_emails
			ON original_emails.email_id = retried_emails.email_parent
			WHERE original_emails.email_status = 'failed'
			AND COALESCE( retried_emails.email_status, '' ) != 'sent'
			{$subsite_sql}
			AND original_emails.email_created >= %s
			AND original_emails.email_created <= %s
			LIMIT {$this->table_limit}",
			$this->get_report_start_date(),
			$this->get_report_end_date()
		);

		$failed_emails = $this->database->get_results( $query, ARRAY_A );

		return array_map(
			function( $value ) {
				$id               = (int) $value['id'];
				$value['actions'] = $this->get_view_link( $id ) . '&nbsp;' . $this->get_retry_link( $id );
				return $value;
			},
			$failed_emails
		);
	}

	/**
	 * Gets the number of emails that were retried automatically
	 * and successfully sent.
	 *
	 * @return int
	 */
	public function get_total_retried_and_sent() {
		$subsite_sql = '';

		if ( $this->is_subsite_report() ) {
			$subsite_id  = (int) get_current_blog_id();
			$subsite_sql = "AND emails.subsite_id = {$subsite_id}";
		}

		$query = $this->database->prepare(
			"SELECT COUNT(*)
			FROM {$this->emails_table} emails
			WHERE emails.auto_retries > 0
			{$subsite_sql}
			AND emails.email_status = 'sent'
			AND emails.email_created >= %s
			AND emails.email_created <= %s",
			$this->get_report_start_date(),
			$this->get_report_end_date()
		);

		return (int) $this->database->get_var( $query );
	}

	/**
	 * Gets the number of emails that were retried manually and
	 * successfully sent.
	 *
	 * @return int
	 */
	public function get_total_manually_retried_and_sent() {
		$subsite_sql = '';

		if ( $this->is_subsite_report() ) {
			$subsite_id  = (int) get_current_blog_id();
			$subsite_sql = "AND original_emails.subsite_id = {$subsite_id}";
		}

		$query = $this->database->prepare(
			"SELECT COUNT(DISTINCT(retried_emails.email_parent))
			FROM {$this->emails_table} retried_emails
			INNER JOIN {$this->emails_table} original_emails
			ON retried_emails.email_parent = original_emails.email_id
			WHERE retried_emails.email_parent > 0
			{$subsite_sql}
			AND retried_emails.email_status = 'sent'
			AND retried_emails.email_created >= %s
			AND retried_emails.email_created <= %s
			AND original_emails.email_status = 'failed'",
			$this->get_report_start_date(),
			$this->get_report_end_date()
		);

		return (int) $this->database->get_var( $query );
	}

	/**
	 * Gets the number of emails that failed that weren't
	 * successfully retried (automatically or manually).
	 *
	 * @return int
	 */
	public function get_total_email_failures() {
		$subsite_sql = '';

		if ( $this->is_subsite_report() ) {
			$subsite_id  = (int) get_current_blog_id();
			$subsite_sql = "AND original_emails.subsite_id = {$subsite_id}";
		}

		$query = $this->database->prepare(
			"SELECT COUNT(*)
			FROM {$this->emails_table} original_emails
			LEFT JOIN {$this->emails_table} retried_emails
			ON original_emails.email_id = retried_emails.email_parent
			WHERE original_emails.email_status = 'failed'
			{$subsite_sql}
			AND COALESCE( retried_emails.email_status, '' ) != 'sent'
			AND original_emails.email_created >= %s
			AND original_emails.email_created <= %s",
			$this->get_report_start_date(),
			$this->get_report_end_date()
		);

		return (int) $this->database->get_var( $query );
	}

	/**
	 * Gets the name of the plugin as used in the health report.
	 *
	 * @return string
	 */
	public function get_plugin_name() {
		return __( 'WP Offload SES', 'wp-offload-ses' );
	}

	/**
	 * Gets the link to view an email.
	 *
	 * @param int $email_id The ID of the email to view.
	 *
	 * @return string
	 */
	public function get_view_link( $email_id ) {
		$args = array(
			'hash'       => 'activity',
			'view-email' => (int) $email_id,
		);

		$method = $this->is_subsite_report() ? 'self' : 'network';

		return sprintf(
			'<a href="%1$s">%2$s</a>',
			$this->wposes->get_plugin_page_url( $args, $method ),
			__( 'View', 'wp-offload-ses' )
		);
	}

	/**
	 * Gets the link to retry an email.
	 *
	 * @param int $email_id The ID of the email to retry.
	 *
	 * @return string
	 */
	public function get_retry_link( $email_id ) {
		$args = array(
			'hash'        => 'activity',
			'retry-email' => (int) $email_id,
		);

		$method = $this->is_subsite_report() ? 'self' : 'network';

		return sprintf(
			'<a href="%1$s">%2$s</a>',
			$this->wposes->get_plugin_page_url( $args, $method ),
			__( 'Retry', 'wp-offload-ses' )
		);
	}

}
