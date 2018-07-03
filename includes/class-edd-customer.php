<?php
/**
 * Customer Object
 *
 * @package     EDD
 * @subpackage  Customers
 * @copyright   Copyright (c) 2018, Easy Digital Downloads, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.3
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * EDD_Customer Class.
 *
 * @since 2.3
 * @since 3.0 No longer extends EDD_DB_Customer.
 *
 * @property int $id
 * @property int $purchase_count
 * @property float $purchase_value
 * @property array $emails
 * @property string $name
 * @property string $status
 * @property string $date_created
 * @property string $payment_ids
 * @property int $user_id
 * @property string $notes
 */
class EDD_Customer extends \EDD\Database\Objects\Customer {

	/**
	 * Customer ID.
	 *
	 * @since 2.3
	 * @var int
	 */
	public $id = 0;

	/**
	 * The customer's purchase count
	 *
	 * @since 2.3
	 * @var int
	 */
	public $purchase_count = 0;

	/**
	 * Lifetime value of a customer.
	 *
	 * @since 2.3
	 * @var float
	 */
	public $purchase_value = 0;

	/**
	 * Customer's primary email.
	 *
	 * @since 2.3
	 * @var string
	 */
	public $email;

	/**
	 * Email addresses associated with customer.
	 *
	 * @since 2.6
	 * @var array
	 */
	protected $emails;

	/**
	 * Customer's name.
	 *
	 * @since 2.3
	 * @since 3.0 Visibility set to `protected`.
	 * @var string
	 */
	public $name;

	/**
	 * The customer's status
	 *
	 * @since 3.0
	 * @since 3.0 Visibility set to `protected`.
	 * @var string
	 */
	public $status;

	/**
	 * The customer's creation date
	 *
	 * @since 2.3
	 * @since 3.0 Visibility set to `protected`.
	 * @var string
	 */
	public $date_created;

	/**
	 * The payment IDs associated with the customer
	 *
	 * @since 2.3
	 * @var string
	 */
	protected $payment_ids;

	/**
	 * The user ID associated with the customer
	 *
	 * @since 2.3
	 * @since 3.0 Visibility set to `protected`.
	 * @var int
	 */
	public $user_id;

	/**
	 * Notes attached to the customer record.
	 *
	 * @since 2.3
	 * @var string
	 */
	protected $notes;

	/**
	 * Get things going
	 *
	 * @since 2.3
	 */
	public function __construct( $_id_or_email = false, $by_user_id = false ) {
		if ( false === $_id_or_email || ( is_numeric( $_id_or_email ) && absint( $_id_or_email ) !== (int) $_id_or_email ) ) {
			return false;
		}

		$by_user_id = is_bool( $by_user_id ) ? $by_user_id : false;

		if ( is_object( $_id_or_email ) ) {
			$customer = $_id_or_email;
		} else {
			if ( is_numeric( $_id_or_email ) ) {
				$field = $by_user_id ? 'user_id' : 'id';
			} else {
				$field = 'email';
			}

			$customer = edd_get_customer_by( $field, $_id_or_email );
		}

		if ( empty( $customer ) || ! is_object( $customer ) ) {
			return false;
		}

		$this->setup_customer( $customer );
	}

	/**
	 * Given the customer data, let's set the variables
	 *
	 * @since  2.3
	 *
	 * @param  object $customer Customer object.
	 * @return bool True if the object was setup correctly, false otherwise.
	 */
	private function setup_customer( $customer ) {
		if ( ! is_object( $customer ) ) {
			return false;
		}

		foreach ( $customer as $key => $value ) {
			switch ( $key ) {
				case 'purchase_value':
					$this->$key = floatval( $value );
					break;
				case 'purchase_count':
					$this->$key = absint( $value );
					break;
				default:
					$this->$key = $value;
					break;
			}
		}

		// Customer ID and email are the only things that are necessary, make sure they exist
		if ( ! empty( $this->id ) && ! empty( $this->email ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Magic __get method to dispatch a call to retrieve a protected property.
	 *
	 * @since 3.0
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function __get( $key = '' ) {
		switch ( $key ) {
			case 'emails':
				$emails   = (array) edd_get_customer_meta( $this->id, 'additional_email', false );
				$emails[] = $this->email;
				return $emails;
			case 'payment_ids':
				$payment_ids = $this->get_payment_ids();
				$payment_ids = implode( ',', $payment_ids );
				return $payment_ids;
			default:
				return isset( $this->{$key} )
					? $this->{$key}
					: edd_get_customer_meta( $this->id, $key );
		}
	}

	/**
	 * Magic __set method to dispatch a call to update a protected property.
	 *
	 * @since 3.0
	 *
	 * @param string $key   Property name.
	 * @param mixed  $value Property value.
	 *
	 * @return mixed Return value of setter being dispatched to.
	 */
	public function __set( $key, $value ) {
		$key = sanitize_key( $key );

		// Only real properties can be saved.
		$keys = array_keys( get_class_vars( get_called_class() ) );

		if ( ! in_array( $key, $keys, true ) ) {
			return false;
		}

		// Dispatch to setter method if value needs to be sanitized.
		if ( method_exists( $this, 'set_' . $key ) ) {
			return call_user_func( array( $this, 'set_' . $key ), $key, $value );
		} else {
			$this->{$key} = $value;
		}
	}

	/**
	 * Creates a customer based on class vars.
	 *
	 * @since 2.3
	 *
	 * @param  array  $data Array of attributes for a customer
	 * @return mixed        False if not a valid creation, Customer ID if user is found or valid creation
	 */
	public function create( $data = array() ) {
		if ( 0 !== $this->id || empty( $data ) ) {
			return false;
		}

		$defaults = array(
			'payment_ids' => '',
		);

		$args = wp_parse_args( $data, $defaults );
		$args = $this->sanitize_columns( $args );

		if ( empty( $args['email'] ) || ! is_email( $args['email'] ) ) {
			return false;
		}

		/**
		 * Fires before a customer is created
		 *
		 * @param array $args Contains customer information such as payment ID, name, and email.
		 */
		do_action( 'edd_customer_pre_create', $args );

		$created = false;

		// Add the customer
		$customer_id = edd_add_customer( $args );

		// The DB class 'add' implies an update if the customer being asked to be created already exists
		if ( ! empty( $customer_id ) ) {

			// Maybe add payments
			if ( ! empty( $args['payment_ids'] ) && is_array( $args['payment_ids'] ) ) {
				$payment_ids = implode( ',', array_unique( array_values( $args['payment_ids'] ) ) );
				foreach ( $payment_ids as $payment_id ) {
					edd_add_customer_meta( $customer_id, 'payment_id', $payment_id );
				}
			}

			// We've successfully added/updated the customer, reset the class vars with the new data
			$customer = edd_get_customer( $customer_id );

			// Setup the customer data with the values from DB
			$this->setup_customer( $customer );

			$created = $this->id;
		}

		/**
		 * Fires after a customer is created
		 *
		 * @param int   $created If created successfully, the customer ID.  Defaults to false.
		 * @param array $args Contains customer information such as payment ID, name, and email.
		 */
		do_action( 'edd_customer_post_create', $created, $args );

		return $created;
	}

	/**
	 * Update a customer record.
	 *
	 * @since 2.3
	 *
	 * @param array $data Array of data attributes for a customer (checked via whitelist)
	 * @return bool True if update was successful, false otherwise.
	 */
	public function update( $data = array() ) {
		if ( empty( $data ) ) {
			return false;
		}

		$data = $this->sanitize_columns( $data );

		do_action( 'edd_customer_pre_update', $this->id, $data );

		$updated = false;

		if ( edd_update_customer( $this->id, $data ) ) {
			$customer = edd_get_customer( $this->id );
			$this->setup_customer( $customer );

			$updated = true;
		}

		do_action( 'edd_customer_post_update', $updated, $this->id, $data );

		return $updated;
	}

	/**
	 * Attach an email address to the customer.
	 *
	 * @since 2.6
	 *
	 * @param string $email The email address to remove from the customer.
	 * @param bool   $primary Allows setting the email added as the primary.
	 *
	 * @return bool True if the email was added successfully, false otherwise.
	 */
	public function add_email( $email = '', $primary = false ) {
		if ( ! is_email( $email ) ) {
			return false;
		}

		// Bail if email exists in the universe.
		if ( $this->email_exists( $email ) ) {
			return false;
		}

		do_action( 'edd_customer_pre_add_email', $email, $this->id, $this );

		// Update is used to ensure duplicate emails are not added.
		$ret = (bool) edd_add_customer_email_address( array(
			'customer_id' => $this->id,
			'email'       => $email
		) );

		do_action( 'edd_customer_post_add_email', $email, $this->id, $this );

		if ( $ret && true === $primary ) {
			$this->set_primary_email( $email );
		}

		return $ret;
	}

	/**
	 * Remove an email address from the customer.
	 *
	 * @since 2.6
	 *
	 * @param string $email The email address to remove from the customer.
	 * @return bool True if the email was removed successfully, false otherwise.
	 */
	public function remove_email( $email = '' ) {
		if ( ! is_email( $email ) ) {
			return false;
		}

		do_action( 'edd_customer_pre_remove_email', $email, $this->id, $this );

		$ret = (bool) edd_delete_customer_meta( $this->id, 'additional_email', $email );

		do_action( 'edd_customer_post_remove_email', $email, $this->id, $this );

		return $ret;
	}

	/**
	 * Check if an email address already exists somewhere in the known universe
	 * of WordPress Users, EDD Customers, or additional customer email addresses.
	 *
	 * @since 3.0
	 *
	 * @param string $email Email address to check.
	 * @return boolean True if assigned to existing customer, false otherwise.
	 */
	public function email_exists( $email = '' ) {

		// Bail if not an email address
		if ( ! is_email( $email ) ) {
			return false;
		}

		// Return true if found in users table
		if ( email_exists( $email ) ) {
			return true;
		}

		// Return true if found in customers table.
		if ( edd_get_customer_by( 'email', $email ) ) {
			return true;
		}

		// Query all customer email addresses.
		$count = edd_count_customer_email_addresses( array(
			'customer_id' => $this->id,
			'email'       => $email,
		) );

		// Return true if found in additional email addresses
		if ( 0 < $count ) {
			return true;
		}

		// Not found
		return false;
	}

	/**
	 * Set an email address as the customer's primary email.
	 *
	 * This will move the customer's previous primary email to an additional email.
	 *
	 * @since 2.6
	 * @param string $new_primary_email The email address to remove from the customer.
	 * @return bool True if the email was set as primary successfully, false otherwise.
	 */
	public function set_primary_email( $new_primary_email = '' ) {
		if ( ! is_email( $new_primary_email ) ) {
			return false;
		}

		do_action( 'edd_customer_pre_set_primary_email', $new_primary_email, $this->id, $this );

		$existing = new EDD_Customer( $new_primary_email );

		if ( $existing->id > 0 && (int) $existing->id !== (int) $this->id ) {

			// This email belongs to another customer
			return false;
		}

		$old_email = $this->email;

		// Update customer record with new email
		$update = $this->update( array( 'email' => $new_primary_email ) );

		// Remove new primary from list of additional emails
		$remove = $this->remove_email( $new_primary_email );

		// Add old email to additional emails list
		$add = $this->add_email( $old_email );

		$ret = $update && $remove && $add;

		if ( $ret ) {
			$this->email = $new_primary_email;

			$payment_ids = $this->get_payment_ids();

			if ( $payment_ids ) {

				// Update payment emails to primary email.
				foreach ( $payment_ids as $payment_id ) {
					edd_update_payment_meta( $payment_id, 'email', $new_primary_email );
				}
			}
		}

		do_action( 'edd_customer_post_set_primary_email', $new_primary_email, $this->id, $this );

		return $ret;
	}

	/**
	 * Get the payment ids of the customer in an array.
	 *
	 * @since 2.6
	 *
	 * @return array An array of payment IDs for the customer, or an empty array if none exist.
	 */
	public function get_payment_ids() {

		// Bail if no customer
		if ( empty( $this->id ) ) {
			return array();
		}

		return array_map( 'absint', (array) edd_get_customer_meta( $this->id, 'payment_id' ) );
	}

	/**
	 * Get an array of EDD_Payment objects from the payment_ids attached to the customer.
	 *
	 * @since 2.6
	 *
	 * @param  array|string  $status A single status as a string or an array of statuses.
	 * @return array An array of EDD_Payment objects or an empty array.
	 */
	public function get_payments( $status = array() ) {

		$payments = array();

		$payment_ids = $this->get_payment_ids();
		if ( ! empty( $payment_ids ) ) {
			foreach ( $payment_ids as $payment_id ) {
				$payment = new EDD_Payment( $payment_id );

				if ( empty( $status ) || ( is_array( $status ) && in_array( $payment->status, $status, true ) ) || $status === $payment->status ) {
					$payments[] = $payment;
				}
			}
		}

		return $payments;
	}

	/**
	 * Attach payment to the customer then triggers increasing statistics.
	 *
	 * @since 2.3
	 *
	 * @param int  $payment_id   The payment ID to attach to the customer.
	 * @param bool $update_stats For backwards compatibility, if we should increase the stats or not.
	 *
	 * @return bool True if the attachment was successfully, false otherwise.
	 */
	public function attach_payment( $payment_id = 0, $update_stats = true ) {

		// Bail if no payment ID
		if ( empty( $payment_id ) ) {
			return false;
		}

		// Get payment
		$payment = edd_get_payment( $payment_id );

		// Bail if payment does not exist
		if ( empty( $payment ) ) {
			return false;
		}

		// Get all previous payment IDs
		$payments = $this->get_payment_ids();

		// Bail if already attached
		if ( in_array( $payment_id, $payments, true ) ) {
			return true;
		}

		do_action( 'edd_customer_pre_attach_payment', $payment->ID, $this->id, $this );

		$added    = edd_add_customer_meta( $this->id, 'payment_id', $payment_id );
		$attached = ! empty( $added );

		if ( ! empty( $attached ) ) {

			// We added this payment successfully, increment the stats
			if ( ! empty( $update_stats ) ) {

				if ( ! empty( $payment->total ) ) {
					$this->increase_value( $payment->total );
				}

				$this->increase_purchase_count();
			}
		}

		do_action( 'edd_customer_post_attach_payment', $attached, $payment->ID, $this->id, $this );

		return $attached;
	}

	/**
	 * Remove a payment from this customer, then triggers reducing stats
	 *
	 * @since 2.3
	 *
	 * @param integer $payment_id   The Payment ID to remove.
	 * @param bool    $update_stats For backwards compatibility, if we should increase the stats or not.
	 *
	 * @return bool $detached True if removed successfully, false otherwise.
	 */
	public function remove_payment( $payment_id = 0, $update_stats = true ) {

		// Bail if no payment ID
		if ( empty( $payment_id ) ) {
			return false;
		}

		// Get payment
		$payment = edd_get_payment( $payment_id );

		// Bail if payment does not exist
		if ( empty( $payment ) ) {
			return false;
		}

		// Get all previous payment IDs
		$payments = $this->get_payment_ids();

		// Bail if already attached
		if ( ! in_array( $payment_id, $payments, true ) ) {
			return true;
		}

		// Don't update stats when published or revoked
		if ( 'publish' !== $payment->status && 'revoked' !== $payment->status ) {
			$update_stats = false;
		}

		do_action( 'edd_customer_pre_remove_payment', $payment->ID, $this->id, $this );

		$deleted  = edd_delete_customer_meta( $this->id, 'payment_id', $payment_id );
		$detached = ! empty( $deleted );

		if ( ! empty( $detached ) ) {

			// We added this payment successfully, increment the stats
			if ( ! empty( $update_stats ) ) {

				if ( ! empty( $payment->total ) ) {
					$this->decrease_value( $payment->total );
				}

				$this->decrease_purchase_count();
			}
		}

		do_action( 'edd_customer_post_remove_payment', $detached, $payment->ID, $this->id, $this );

		return $detached;
	}

	/**
	 * Increase the purchase count of a customer.
	 *
	 * @since 2.3
	 *
	 * @param int $count The number to increment purchase count by. Default 1.
	 * @return int New purchase count.
	 */
	public function increase_purchase_count( $count = 1 ) {

		// Make sure it's numeric and not negative
		if ( ! is_numeric( $count ) || absint( $count ) !== $count ) {
			return false;
		}

		$new_total = (int) $this->purchase_count + (int) $count;

		do_action( 'edd_customer_pre_increase_purchase_count', $count, $this->id, $this );

		if ( $this->update( array( 'purchase_count' => $new_total ) ) ) {
			$this->purchase_count = $new_total;
		}

		do_action( 'edd_customer_post_increase_purchase_count', $this->purchase_count, $count, $this->id, $this );

		return $this->purchase_count;
	}

	/**
	 * Decrease the customer's purchase count.
	 *
	 * @since 2.3
	 *
	 * @param int $count The number to decrement purchase count by. Default 1.
	 * @return mixed New purchase count if successful, false otherwise.
	 */
	public function decrease_purchase_count( $count = 1 ) {

		// Make sure it's numeric and not negative
		if ( ! is_numeric( $count ) || absint( $count ) !== $count ) {
			return false;
		}

		$new_total = (int) $this->purchase_count - (int) $count;

		if ( $new_total < 0 ) {
			$new_total = 0;
		}

		do_action( 'edd_customer_pre_decrease_purchase_count', $count, $this->id, $this );

		if ( $this->update( array( 'purchase_count' => $new_total ) ) ) {
			$this->purchase_count = $new_total;
		}

		do_action( 'edd_customer_post_decrease_purchase_count', $this->purchase_count, $count, $this->id, $this );

		return $this->purchase_count;
	}

	/**
	 * Increase the customer's lifetime value.
	 *
	 * @since 2.3
	 *
	 * @param float $value The value to increase by.
	 * @return mixed New lifetime value if successful, false otherwise.
	 */
	public function increase_value( $value = 0.00 ) {
		$value     = floatval( apply_filters( 'edd_customer_increase_value', $value, $this ) );
		$new_value = floatval( $this->purchase_value ) + $value;

		do_action( 'edd_customer_pre_increase_value', $value, $this->id, $this );

		if ( $this->update( array( 'purchase_value' => $new_value ) ) ) {
			$this->purchase_value = $new_value;
		}

		do_action( 'edd_customer_post_increase_value', $this->purchase_value, $value, $this->id, $this );

		return $this->purchase_value;
	}

	/**
	 * Decrease a customer's lifetime value.
	 *
	 * @since 2.3
	 *
	 * @param float $value The value to decrease by.
	 * @return mixed New lifetime value if successful, false otherwise.
	 */
	public function decrease_value( $value = 0.00 ) {
		$value = apply_filters( 'edd_customer_decrease_value', $value, $this );

		$new_value = floatval( $this->purchase_value ) - $value;

		if ( $new_value < 0 ) {
			$new_value = 0.00;
		}

		do_action( 'edd_customer_pre_decrease_value', $value, $this->id, $this );

		if ( $this->update( array( 'purchase_value' => $new_value ) ) ) {
			$this->purchase_value = $new_value;
		}

		do_action( 'edd_customer_post_decrease_value', $this->purchase_value, $value, $this->id, $this );

		return $this->purchase_value;
	}

	/**
	 * Get the parsed notes for a customer as an array.
	 *
	 * @since 2.3
	 * @since 3.0 Use the new Notes component & API.
	 *
	 * @param integer $length The number of notes to get.
	 * @param integer $paged What note to start at.
	 *
	 * @return array The notes requested.
	 */
	public function get_notes( $length = 20, $paged = 1 ) {

		// Number
		$length = is_numeric( $length )
			? absint( $length )
			: 20;

		// Offset
		$offset = is_numeric( $paged ) && ( 1 !== $paged )
			? ( ( absint( $paged ) - 1 ) * $length )
			: 0;

		// Return the paginated notes for back-compat
		return edd_get_notes( array(
			'object_id'   => $this->id,
			'object_type' => 'customer',
			'number'      => $length,
			'offset'      => $offset,
			'order'       => 'asc',
		) );
	}

	/**
	 * Get the total number of notes we have after parsing.
	 *
	 * @since 2.3
	 * @since 3.0 Use the new Notes component & API.
	 *
	 * @return int The number of notes for the customer.
	 */
	public function get_notes_count() {
		return edd_count_notes( array(
			'object_id'   => $this->id,
			'object_type' => 'customer',
		) );
	}

	/**
	 * Add a customer note.
	 *
	 * @since 2.3
	 * @since 3.0 Use the new Notes component & API
	 *
	 * @param string $note The note to add
	 * @return string|boolean The new note if added successfully, false otherwise
	 */
	public function add_note( $note = '' ) {

		// Bail if note content is empty
		$note = trim( $note );
		if ( empty( $note ) ) {
			return false;
		}

		/**
		 * Filter the note of a customer before it's added
		 *
		 * @since 2.3
		 * @since 3.0 No longer includes the datetime stamp
		 *
		 * @param string $note The content of the note to add
		 * @return string
		 */
		$note = apply_filters( 'edd_customer_add_note_string', $note );

		/**
		 * Allow actions before a note is added
		 *
		 * @since 2.3
		 */
		do_action( 'edd_customer_pre_add_note', $note, $this->id, $this );

		// Try to add the note
		edd_add_note( array(
			'user_id'     => 0, // Authored by System/Bot
			'object_id'   => $this->id,
			'object_type' => 'customer',
			'content'     => wp_kses( stripslashes( $note ), array() ),
		) );

		/**
		 * Allow actions after a note is added
		 *
		 * @since 3.0 Changed to an empty string since notes were moved out
		 */
		do_action( 'edd_customer_post_add_note', '', $note, $this->id, $this );

		// Return the formatted note, so we can test, as well as update any displays
		return $note;
	}

	/**
	 * Retrieve customer meta field for a customer.
	 *
	 * @since 2.6
	 *
	 * @param string  $key    Optional. The meta key to retrieve. By default, returns data for all keys. Default empty.
	 * @param bool    $single Optional, default is false. If true, return only the first value of the specified meta_key.
	 *                        This parameter has no effect if meta_key is not specified.
	 *
	 * @return mixed Will be an array if $single is false. Will be value of meta data field if $single is true.
	 */
	public function get_meta( $key = '', $single = true ) {
		return edd_get_customer_meta( $this->id, $key, $single );
	}

	/**
	 * Add meta data field to a customer.
	 *
	 * @since 2.6
	 *
	 * @param string $meta_key   Meta data name.
	 * @param mixed  $meta_value Meta data value. Must be serializable if non-scalar.
	 * @param bool   $unique     Optional. Whether the same key should not be added. Default false.
	 *
	 * @return int|false Meta ID on success, false on failure.
	 */
	public function add_meta( $meta_key = '', $meta_value = '', $unique = false ) {
		return edd_add_customer_meta( $this->id, $meta_key, $meta_value, $unique );
	}

	/**
	 * Update customer meta field based on customer ID.
	 *
	 * Use the $prev_value parameter to differentiate between meta fields with the
	 * same key and order ID.
	 *
	 * If the meta field for the order does not exist, it will be added.
	 *
	 * @since 2.6
	 *
	 * @param string $meta_key   Meta data key.
	 * @param mixed  $meta_value Meta data value. Must be serializable if non-scalar.
	 * @param mixed  $prev_value Optional. Previous value to check before removing. Default empty.
	 *
	 * @return int|bool Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public function update_meta( $meta_key = '', $meta_value = '', $prev_value = '' ) {
		return edd_update_customer_meta( $this->id, $meta_key, $meta_value, $prev_value );
	}

	/**
	 * Remove meta data matching criteria from a customer.
	 *
	 * You can match based on the key, or key and value. Removing based on key and value, will keep from removing duplicate
	 * meta data with the same key. It also allows removing all meta data matching key, if needed.
	 *
	 * @since 2.6
	 *
	 * @param string $meta_key   Meta data name.
	 * @param mixed  $meta_value Optional. Meta data value. Must be serializable if non-scalar. Default empty.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function delete_meta( $meta_key = '', $meta_value = '' ) {
		return edd_delete_customer_meta( $this->id, $meta_key, $meta_value );
	}

	/**
	 * Sanitize the data for update/create.
	 *
	 * @since 2.3
	 *
	 * @param array $data The data to sanitize.
	 * @return array The sanitized data, based off column defaults.
	 */
	private function sanitize_columns( $data = array() ) {
		$default_values = array();

		foreach ( $data as $key => $type ) {

			// Only sanitize data that we were provided
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}

			switch ( $type ) {
				case '%s':
					if ( 'email' === $key ) {
						$data[ $key ] = sanitize_email( $data[ $key ] );
					} else {
						$data[ $key ] = sanitize_text_field( $data[ $key ] );
					}
					break;

				case '%d':
					if ( ! is_numeric( $data[ $key ] ) || absint( $data[ $key ] ) !== (int) $data[ $key ] ) {
						$data[ $key ] = $default_values[ $key ];
					} else {
						$data[ $key ] = absint( $data[ $key ] );
					}
					break;

				case '%f':
					// Convert what was given to a float
					$value = floatval( $data[ $key ] );

					if ( ! is_float( $value ) ) {
						$data[ $key ] = $default_values[ $key ];
					} else {
						$data[ $key ] = $value;
					}
					break;

				default:
					$data[ $key ] = sanitize_text_field( $data[ $key ] );
					break;
			}
		}

		return $data;
	}

	/**
	 * Retrieve all of the IP addresses used by the customer.
	 *
	 * @since 3.0
	 *
	 * @return array Array of objects containing IP address.
	 */
	public function get_ips() {
		return edd_get_orders( array(
			'customer_id' => $this->id,
			'fields'      => 'ip',
			'groupby'     => 'ip',
		) );
	}
}
