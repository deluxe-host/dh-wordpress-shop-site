<?php
/**
 * Displays the WP Offload SES reports list table.
 *
 * @package WP Offload SES
 * @author  Delicious Brains
 */

namespace DeliciousBrains\WP_Offload_SES\Pro;

use DeliciousBrains\WP_Offload_SES\WP_Offload_SES;
use DeliciousBrains\WP_Offload_SES\Email_Log;
use DeliciousBrains\WP_Offload_SES\Email_Events;
use DeliciousBrains\WP_Offload_SES\Utils;
use \DateTime;

// TODO: we may want to include our own version of this class.
if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once( ABSPATH . '/wp-admin/includes/wp-list-table.php' );
}

/**
 * Class Reports_List_Table
 *
 * @since 1.0.0
 */
class Reports_List_Table extends \WP_List_Table {

	/**
	 * The WordPress database object.
	 *
	 * @var \WPDB
	 */
	private $database;

	/**
	 * The emails table.
	 *
	 * @var string
	 */
	private $emails_table;

	/**
	 * The clicks table.
	 *
	 * @var string
	 */
	private $clicks_table;

	/**
	 * Constructs the Reports_List_Table class.
	 */
	public function __construct() {
		global $wpdb;

		// Set the current screen if doing AJAX to prevent notices.
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			set_current_screen( 'options-general' );
		}

		$this->screen       = get_current_screen();
		$this->database     = $wpdb;
		$this->clicks_table = $this->database->base_prefix . 'oses_clicks';
		$this->emails_table = $this->database->base_prefix . 'oses_emails';

		/**
		 * Construct the WP_List_Table parent class.
		 *
		 * @param array
		 */
		parent::__construct(
			array(
				'singular' => 'report',
				'plural'   => 'reports',
				'ajax'     => true,
				'screen'   => $this->screen,
			)
		);
	}

	/**
	 * Get the column names for the table.
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'subject'     => __( 'Subject', 'wp-offload-ses' ),
			'emails_sent' => __( 'Emails Sent', 'wp-offload-ses' ),
			'open_count'  => __( 'Open Count', 'wp-offload-ses' ),
			'click_count' => __( 'Click Count', 'wp-offload-ses' ),
		);

		return $columns;
	}

	/**
	 * Get the sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'subject'     => array( 'subject', false ),
			'emails_sent' => array( 'emails_sent', false ),
			'open_count'  => array( 'open_count', false ),
			'click_count' => array( 'click_count', false ),
		);

		return $sortable_columns;
	}

	/**
	 * Return the individual data for each column.
	 *
	 * @param array  $item        The report for the subject.
	 * @param string $column_name The column to display.
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		if ( 'click_count' === $column_name && null === $item['click_count'] ) {
			return 0;
		}

		return $item[ $column_name ];
	}

	/**
	 * Gets the total number of items in the table.
	 *
	 * @return int
	 */
	public function get_total_items() {
		$query       = "SELECT $this->emails_table.email_subject FROM $this->emails_table GROUP BY $this->emails_table.email_subject";
		$total_items = $this->database->query( $query );

		if ( false === $total_items ) {
			$total_items = 0;
		}

		return $total_items;
	}

	/**
	 * Gets the orderby used in SQL.
	 *
	 * @return string
	 */
	public function get_orderby() {
		$orderby = 'emails_sent';

		if ( isset( $_REQUEST['orderby'] ) ) {
			$sortable = array_keys( $this->get_sortable_columns() );

			if ( in_array( $_REQUEST['orderby'], $sortable ) ) {
				$orderby = $_REQUEST['orderby'];
			}
		}

		return $orderby;
	}

	/**
	 * Gets the order used in SQL.
	 *
	 * @return string
	 */
	public function get_order() {
		$order = 'DESC';

		if ( isset( $_REQUEST['order'] ) && 'asc' === $_REQUEST['order'] ) {
			$order = 'ASC';
		}

		return $order;
	}

	/**
	 * Converts the WordPress time format into the MySQL DATETIME format.
	 *
	 * @param string $date The provided date string.
	 * @param string $time Optional time parameter.
	 *
	 * @return string|bool
	 */
	private function convert_date_format( $date, $time = '00:00:00' ) {
		$format = get_option( 'date_format' );
		$date   = DateTime::createFromFormat( $format, $date );

		if ( ! $date ) {
			return false;
		}

		return $date->format( 'Y-m-d ' . $time );
	}

	/**
	 * Get the WHERE used in SQL.
	 *
	 * @return string
	 */
	public function get_where() {
		$where  = array();
		$from   = ! empty( $_REQUEST['from'] ) ? $this->convert_date_format( $_REQUEST['from'] ) : false;
		$to     = ! empty( $_REQUEST['to'] ) ? $this->convert_date_format( $_REQUEST['to'], '23:59:59' ) : false;
		$search = ! empty( $_REQUEST['search'] ) ? $_REQUEST['search'] : false;

		// Build the WHERE queries (we may be searching multiple things here).
		$where[] = "($this->emails_table.email_status = 'sent')";

		if ( is_multisite() && ! Utils::is_network_admin() ) {
			$subsite_id = get_current_blog_id();
			$where[]    = $this->database->prepare( "($this->emails_table.subsite_id = %d)", $subsite_id );
		}

		if ( $from && $to ) {
			$where[] = $this->database->prepare( "($this->emails_table.email_created BETWEEN '%s' AND '%s')", $from, $to );
		} elseif ( $from ) {
			$where[] = $this->database->prepare( "$this->emails_table.email_created > '%s'", $from );
		} elseif ( $to ) {
			$where[] = $this->database->prepare( "$this->emails_table.email_created < '%s'", $to );
		}

		if ( $search ) {
			$search  = '%' . $this->database->esc_like( $search ) . '%';
			$where[] = $this->database->prepare( "($this->emails_table.email_subject LIKE %s)", $search );
		}

		// Glue it all back together.
		if ( ! empty( $where ) ) {
			$where = 'WHERE ' . implode( ' AND ', $where );
		} else {
			$where = '';
		}

		return $where;
	}

	/**
	 * Get the data used to populate the table.
	 *
	 * @param int $current_page The current page of the list table.
	 * @param int $per_page     The number of items to return per page.
	 *
	 * @return array
	 */
	public function get_data( $current_page, $per_page ) {
		$offset  = ( $current_page - 1 ) * $per_page;
		$count   = $per_page;
		$orderby = $this->get_orderby();
		$order   = $this->get_order();
		$where   = $this->get_where();

		$query = "SELECT
					{$this->emails_table}.email_subject AS subject,
					COUNT({$this->emails_table}.email_id) AS emails_sent,
					SUM({$this->emails_table}.email_open_count) AS open_count,
					SUM({$this->clicks_table}.click_count) AS click_count
				FROM {$this->emails_table}
				LEFT JOIN (
					SELECT {$this->clicks_table}.email_id, SUM({$this->clicks_table}.email_click_count) AS click_count
					FROM {$this->clicks_table}
					GROUP BY {$this->clicks_table}.email_id
				) {$this->clicks_table} ON {$this->clicks_table}.email_id = {$this->emails_table}.email_id
				$where
				GROUP BY subject
				ORDER BY $orderby $order
				LIMIT $offset, $count";

		$results = $this->database->get_results( $query, ARRAY_A );

		return $results;
	}

	/**
	 * Display the no items message
	 */
	public function no_items() {
		_e( 'No reports found. Check back after sending some emails!', 'wp-offload-ses' );
	}

	/**
	 * Set the necessary args for the table.
	 */
	public function prepare_items() {
		$per_page              = 20;
		$current_page          = $this->get_pagenum();
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$total_items           = $this->get_total_items();
		$this->items           = $this->get_data( $current_page, $per_page );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
				'orderby'     => ! empty( $_REQUEST['orderby'] ) && '' != $_REQUEST['orderby'] ? $_REQUEST['orderby'] : 'emails_sent',
				'order'       => ! empty( $_REQUEST['order'] ) && '' != $_REQUEST['order'] ? $_REQUEST['order'] : 'desc',
			)
		);
	}

	/**
	 * Display the table.
	 */
	public function display() {
		wp_nonce_field( 'wposes-reports-nonce', 'wposes_reports_nonce' );

		$order   = ! empty( $this->_pagination_args['order'] ) ? $this->_pagination_args['order'] : 'desc';
		$orderby = ! empty( $this->_pagination_args['orderby'] ) ? $this->_pagination_args['orderby'] : 'emails_sent';

		echo '<input type="hidden" id="order" name="order" value="' . esc_attr( $order ) . '" />';
		echo '<input type="hidden" id="orderby" name="orderby" value="' . esc_attr( $orderby ) . '" />';

		parent::display();
	}

	public function extra_tablenav( $which = 'top' ) {
		if ( 'top' !== $which || ! $this->has_items() ) {
			return;
		}
		?>
		<form id="wposes-reports-form">
			<label for="wposes-reports-from"><?php _e( 'From', 'wp-offload-ses' ); ?></label>
			<input id="wposes-reports-from" type="text" name="from">
			<label for="wposes-reports-to"><?php _e( 'To', 'wp-offload-ses' ); ?></label>
			<input id="wposes-reports-to" type="text" name="to">
			<?php $this->search_box( __( 'Search' ), 'wposes-reports-search' ); ?>
		</form>
		<?php
	}

	/**
	 * Display the table over AJAX.
	 */
	public function ajax_response() {
		check_ajax_referer( 'wposes-reports-nonce', 'wposes_reports_nonce' );
		$this->prepare_items();

		extract( $this->_args );
		extract( $this->_pagination_args, EXTR_SKIP );

		ob_start();
		if ( ! empty( $_REQUEST['no_placeholder'] ) ) {
			$this->display_rows();
		} else {
			$this->display_rows_or_placeholder();
		}
		$rows = ob_get_clean();

		ob_start();
		$this->print_column_headers();
		$headers = ob_get_clean();

		ob_start();
		$this->pagination( 'top' );
		$pagination_top = ob_get_clean();

		ob_start();
		$this->pagination( 'bottom' );
		$pagination_bottom = ob_get_clean();

		$response                         = array( 'rows' => $rows );
		$response['pagination']['top']    = $pagination_top;
		$response['pagination']['bottom'] = $pagination_bottom;
		$response['column_headers']       = $headers;

		if ( isset( $total_items ) ) {
			$response['total_items_i18n'] = sprintf( _n( '1 item', '%s items', $total_items ), number_format_i18n( $total_items ) );
		}

		if ( isset( $total_pages ) ) {
			$response['total_pages']      = $total_pages;
			$response['total_pages_i18n'] = number_format_i18n( $total_pages );
		}

		die( wp_json_encode( $response ) );
	}

}
