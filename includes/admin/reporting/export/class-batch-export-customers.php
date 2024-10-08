<?php
/**
 * Batch Customers Export Class
 *
 * This class handles customer export
 *
 * @package     EDD
 * @subpackage  Admin/Reports
 * @copyright   Copyright (c) 2015, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.4
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * EDD_Batch_Customers_Export Class
 *
 * @since 2.4
 */
class EDD_Batch_Customers_Export extends EDD_Batch_Export {

	/**
	 * Our export type. Used for export-type specific filters/actions
	 *
	 * @var string
	 * @since 2.4
	 */
	public $export_type = 'customers';

	/**
	 * Set the CSV columns
	 *
	 * @since 2.4
	 * @return array $cols All the columns
	 */
	public function csv_cols() {

		return array(
			'id'           => __( 'ID', 'easy-digital-downloads' ),
			'user_id'      => __( 'User ID', 'easy-digital-downloads' ),
			'name'         => __( 'Name', 'easy-digital-downloads' ),
			'email'        => __( 'Email', 'easy-digital-downloads' ),
			'purchases'    => __( 'Number of Purchases', 'easy-digital-downloads' ),
			'amount'       => __( 'Customer Value', 'easy-digital-downloads' ),
			'payment_ids'  => __( 'Payment IDs', 'easy-digital-downloads' ),
			'date_created' => __( 'Date Created', 'easy-digital-downloads' ),
		);
	}

	/**
	 * Get the Export Data
	 *
	 * @since 2.4
	 *   Database API
	 * @global object $edd_logs EDD Logs Object
	 * @return array $data The data for the CSV file
	 */
	public function get_data() {

		$data = array();

		if ( ! empty( $this->download ) ) {

			// Export customers of a specific product
			global $edd_logs;

			$args = array(
				'post_parent'    => absint( $this->download ),
				'log_type'       => 'sale',
				'posts_per_page' => 30,
				'paged'          => $this->step
			);

			if( null !== $this->price_id ) {
				$args['meta_query'] = array(
					array(
						'key'   => '_edd_log_price_id',
						'value' => (int) $this->price_id
					)
				);
			}

			$logs = $edd_logs->get_connected_logs( $args );

			if ( $logs ) {
				foreach ( $logs as $log ) {

					$payment_id  = get_post_meta( $log->ID, '_edd_log_payment_id', true );
					$customer_id = edd_get_payment_customer_id( $payment_id );
					$customer    = new EDD_Customer( $customer_id );

					$data[] = array(
						'id'           => $customer->id,
						'user_id'      => $customer->user_id,
						'name'         => $customer->name,
						'email'        => $customer->email,
						'purchases'    => $customer->purchase_count,
						'amount'       => edd_format_amount( $customer->purchase_value ),
						'payment_ids'  => $customer->payment_ids,
						'date_created' => $customer->date_created,
					);
				}
			}

		} else {

			// Export all customers
			$offset    = 30 * ( $this->step - 1 );
			$customers = EDD()->customers->get_customers( array( 'number' => 30, 'offset' => $offset ) );

			$i = 0;

			foreach ( $customers as $customer ) {

				$data[ $i ] = array(
					'id'           => $customer->id,
					'user_id'      => $customer->user_id,
					'name'         => $customer->name,
					'email'        => $customer->email,
					'purchases'    => $customer->purchase_count,
					'amount'       => edd_format_amount( $customer->purchase_value ),
					'payment_ids'  => $customer->payment_ids,
					'date_created' => $customer->date_created,
				);

				$i++;
			}
		}

		$data = apply_filters( 'edd_export_get_data', $data );
		$data = apply_filters( 'edd_export_get_data_' . $this->export_type, $data );

		return $data;
	}

	/**
	 * Return the calculated completion percentage
	 *
	 * @since 2.4
	 * @return int
	 */
	public function get_percentage_complete() {

		$percentage = 0;

		// We can't count the number when getting them for a specific download
		if( empty( $this->download ) ) {

			$total = EDD()->customers->count();

			if( $total > 0 ) {

				$percentage = ( ( 30 * $this->step ) / $total ) * 100;

			}

		}

		if( $percentage > 100 ) {
			$percentage = 100;
		}

		return $percentage;
	}

	/**
	 * Set the properties specific to the Customers export
	 *
	 * @since 2.4.2
	 * @param array $request The Form Data passed into the batch processing
	 */
	public function set_properties( $request ) {
		$this->start    = isset( $request['start'] )            ? sanitize_text_field( $request['start'] ) : '';
		$this->end      = isset( $request['end']  )             ? sanitize_text_field( $request['end']  )  : '';
		$this->download = isset( $request['download']         ) ? absint( $request['download']         )   : null;
		$this->price_id = ! empty( $request['edd_price_option'] ) && 0 !== $request['edd_price_option'] ? absint( $request['edd_price_option'] )   : null;
	}
}
