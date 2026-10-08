<?php
/**
 * REST endpoints the forms post to; they pass the answers on to the portal
 * with the API key, which never reaches the browser.
 *
 * @package Dernekyazilimi
 */

defined( 'ABSPATH' ) || exit;

/**
 * /wp-json/dernekyazilimi/v1/*
 */
class Dernekyazilimi_Rest {

	const REQUESTS_PER_MINUTE = 20;

	/**
	 * Portal client.
	 *
	 * @var Dernekyazilimi_Client
	 */
	private $client;

	/**
	 * Constructor.
	 *
	 * @param Dernekyazilimi_Client $client Portal client.
	 */
	public function __construct( Dernekyazilimi_Client $client ) {
		$this->client = $client;
	}

	/**
	 * Hook into WordPress.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Register the routes. They are public like the forms themselves; the
	 * portal validates every value and limits requests per visitor.
	 */
	public function routes() {
		$routes = array(
			'/donations'    => 'donation',
			'/phone/send'   => 'phone_send',
			'/phone/verify' => 'phone_verify',
			'/accounts'     => 'account',
		);
		foreach ( $routes as $route => $method ) {
			register_rest_route(
				'dernekyazilimi/v1',
				$route,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, $method ),
					'permission_callback' => array( $this, 'allowed' ),
				)
			);
		}
	}

	/**
	 * Public forms: stop bots that fill the hidden field and visitors who
	 * send too many requests.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function allowed( WP_REST_Request $request ) {
		if ( '' !== (string) $request->get_param( 'website' ) ) {
			return new WP_Error( 'dernekyazilimi_rejected', __( 'The form could not be sent.', 'dernekyazilimi' ), array( 'status' => 400 ) );
		}

		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key   = 'dernekyazilimi_rl_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= self::REQUESTS_PER_MINUTE ) {
			return new WP_Error( 'dernekyazilimi_too_many', __( 'Too many requests. Please wait a minute and try again.', 'dernekyazilimi' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * Start a donation.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function donation( WP_REST_Request $request ) {
		$cause = absint( $request->get_param( 'cause_id' ) );

		return $this->forward(
			'donations',
			array(
				'amount'        => str_replace( ',', '.', sanitize_text_field( (string) $request->get_param( 'amount' ) ) ),
				'cause_id'      => $cause ? $cause : null,
				'name'          => sanitize_text_field( (string) $request->get_param( 'name' ) ),
				'email'         => sanitize_email( (string) $request->get_param( 'email' ) ),
				'phone'         => sanitize_text_field( (string) $request->get_param( 'phone' ) ),
				'message'       => sanitize_textarea_field( (string) $request->get_param( 'message' ) ),
				'hide_name'     => rest_sanitize_boolean( $request->get_param( 'hide_name' ) ),
				'method'        => sanitize_text_field( (string) $request->get_param( 'method' ) ),
				'agreement'     => rest_sanitize_boolean( $request->get_param( 'agreement' ) ) ? true : null,
				'payment_terms' => rest_sanitize_boolean( $request->get_param( 'payment_terms' ) ) ? true : null,
			),
			array( 'uuid', 'method', 'reference', 'frame_url', 'result_url' )
		);
	}

	/**
	 * Send the verification code to a phone.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function phone_send( WP_REST_Request $request ) {
		return $this->forward( 'phone-verifications', array( 'phone_number' => $this->phone( $request ) ), array( 'status' ) );
	}

	/**
	 * Check the verification code.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function phone_verify( WP_REST_Request $request ) {
		return $this->forward(
			'phone-verifications/verify',
			array(
				'phone_number' => $this->phone( $request ),
				'code'         => preg_replace( '/\D/', '', (string) $request->get_param( 'code' ) ),
			),
			array( 'status' )
		);
	}

	/**
	 * Open an account: a volunteer registration, or the first step of a
	 * membership application that continues in a frame of the portal.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function account( WP_REST_Request $request ) {
		$consents = array();
		$given    = $request->get_param( 'consents' );
		foreach ( array( 'email', 'sms', 'whatsapp' ) as $channel ) {
			$consents[ $channel ] = is_array( $given ) && ! empty( $given[ $channel ] );
		}

		return $this->forward(
			'accounts',
			array(
				'name'         => sanitize_text_field( (string) $request->get_param( 'name' ) ),
				'surname'      => sanitize_text_field( (string) $request->get_param( 'surname' ) ),
				'email'        => sanitize_email( (string) $request->get_param( 'email' ) ),
				'phone_number' => $this->phone( $request ),
				'consents'     => $consents,
				'agreement'    => rest_sanitize_boolean( $request->get_param( 'agreement' ) ) ? true : null,
				'continue'     => 'membership' === $request->get_param( 'form' ) ? 'membership' : null,
			),
			array( 'created', 'continue_url' )
		);
	}

	/**
	 * Digits of the phone number, with the country code.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string
	 */
	private function phone( WP_REST_Request $request ) {
		$digits = preg_replace( '/\D/', '', (string) $request->get_param( 'phone_number' ) );
		if ( 11 === strlen( $digits ) && '0' === $digits[0] ) {
			return '9' . $digits;
		}
		if ( 10 === strlen( $digits ) && '5' === $digits[0] ) {
			return '90' . $digits;
		}

		return $digits;
	}

	/**
	 * Send to the portal and hand its answer to the form.
	 *
	 * @param string   $path Path under /api/site.
	 * @param array    $data Values.
	 * @param string[] $keep Keys of a successful answer the form may see.
	 * @return WP_REST_Response
	 */
	private function forward( $path, array $data, array $keep ) {
		if ( ! $this->client->configured() ) {
			return new WP_REST_Response( array( 'message' => __( 'This form is not set up yet.', 'dernekyazilimi' ) ), 503 );
		}

		$response = $this->client->request( 'POST', $path, $data );
		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response( array( 'message' => $response->get_error_message() ), 502 );
		}

		$status = $response['status'];
		$body   = $response['body'];

		if ( $status >= 200 && $status < 300 ) {
			$answer = array_intersect_key( $body, array_flip( $keep ) );
			foreach ( array( 'frame_url', 'result_url', 'continue_url' ) as $key ) {
				if ( isset( $answer[ $key ] ) ) {
					$answer[ $key ] = esc_url_raw( (string) $answer[ $key ], array( 'https' ) );
				}
			}

			return new WP_REST_Response( $answer, 200 );
		}

		if ( 422 === $status || 429 === $status ) {
			$message = 429 === $status ? __( 'Too many requests. Please wait a minute and try again.', 'dernekyazilimi' ) : sanitize_text_field( (string) ( $body['message'] ?? '' ) );
			$errors  = array();
			foreach ( (array) ( $body['errors'] ?? array() ) as $field => $messages ) {
				$errors[ sanitize_key( $field ) ] = array_map( 'sanitize_text_field', (array) $messages );
			}

			return new WP_REST_Response(
				array(
					'message' => $message,
					'errors'  => $errors,
				),
				$status
			);
		}

		// Wrong key, site not allowed, portal error: nothing the visitor can fix.
		return new WP_REST_Response( array( 'message' => __( 'The form cannot be sent right now. Please try again later.', 'dernekyazilimi' ) ), 502 );
	}
}
