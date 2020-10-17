<?php
/**
 * Email job that is ran via cron.
 *
 * DEPRECRATED
 *
 * @author  Delicious Brains
 * @package WP Offload SES
 */

namespace DeliciousBrains\WP_Offload_SES\Pro\Queue\Jobs;

use DeliciousBrains\WP_Offload_SES\Queue\Jobs\Email_Job as Job;

/**
 * Class Email_Job
 *
 * @since 1.0.0
 * @deprecated 1.4.1
 */
class Email_Job extends Job {

	/**
	 * The ID of the email to send.
	 *
	 * @var int
	 */
	public $email_id;

	/**
	 * The ID of the subsite sending the email.
	 *
	 * @var int
	 */
	public $subsite_id;

	/**
	 * Pass any necessary data to the job.
	 *
	 * @param int $email_id   The ID of the email to send.
	 * @param int $subsite_id The ID of the subsite sending the email.
	 */
	public function __construct( $email_id, $subsite_id ) {
		parent::__construct( $email_id, $subsite_id );
	}

}
