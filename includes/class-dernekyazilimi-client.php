<?php
/**
 * Talks to the association's portal, server to server.
 *
 * @package Dernekyazilimi
 */

defined( 'ABSPATH' ) || exit;

/**
 * Client of the portal's site API (/api/site).
 */
class Dernekyazilimi_Client {

	const OPTION      = 'dernekyazilimi_settings';
	const CONFIG_KEY  = 'dernekyazilimi_config';
	const TEXTS_KEY   = 'dernekyazilimi_agreements';
	const CONFIG_TIME = 5 * MINUTE_IN_SECONDS;

	/**
	 * Address of the portal without a trailing slash, or an empty string.
	 *
	 * @return string
	 */
	public function portal_url() {
		$settings = get_option( self::OPTION, array() );
		$url      = defined( 'DERNEKYAZILIMI_PORTAL_URL' ) ? DERNEKYAZILIMI_PORTAL_URL : ( $settings['portal_url'] ?? '' );

		return untrailingslashit( esc_url_raw( (string) $url, array( 'https' ) ) );
	}

	/**
	 * Scheme and host of the portal, for checking frame messages.
	 *
	 * @return string
	 */
	public function portal_origin() {
		$parts = wp_parse_url( $this->portal_url() );
		if ( empty( $parts['host'] ) ) {
			return '';
		}

		return 'https://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
	}

	/**
	 * The API key made in the portal's organization settings.
	 *
	 * @return string
	 */
	public function api_key() {
		if ( defined( 'DERNEKYAZILIMI_API_KEY' ) ) {
			return (string) DERNEKYAZILIMI_API_KEY;
		}
		$settings = get_option( self::OPTION, array() );

		return (string) ( $settings['api_key'] ?? '' );
	}

	/**
	 * Whether the portal address and the key are set.
	 *
	 * @return bool
	 */
	public function configured() {
		return '' !== $this->portal_url() && '' !== $this->api_key();
	}

	/**
	 * What the portal says about its forms; cached for a few minutes.
	 *
	 * @param bool $fresh Skip the cache.
	 * @return array|WP_Error
	 */
	public function config( $fresh = false ) {
		if ( ! $this->configured() ) {
			return new WP_Error( 'dernekyazilimi_not_configured', __( 'The portal address and the API key are not set yet.', 'dernekyazilimi' ) );
		}

		$cached = $fresh ? false : get_transient( self::CONFIG_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = $this->request( 'GET', 'config' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== $response['status'] ) {
			return new WP_Error( 'dernekyazilimi_portal_error', $response['body']['message'] ?? __( 'The portal did not answer as expected.', 'dernekyazilimi' ), array( 'status' => $response['status'] ) );
		}

		set_transient( self::CONFIG_KEY, $response['body'], self::CONFIG_TIME );

		return $response['body'];
	}

	/**
	 * Forget the cached configuration.
	 */
	public function forget_config() {
		delete_transient( self::CONFIG_KEY );
		delete_transient( self::TEXTS_KEY );
	}

	/**
	 * Text of an agreement in force in the portal; cached for a few minutes.
	 *
	 * @param string $key Key of the agreement, e.g. "payment-terms".
	 * @return array|WP_Error Title, version, content (HTML) and address.
	 */
	public function agreement( $key ) {
		if ( ! $this->configured() ) {
			return new WP_Error( 'dernekyazilimi_not_configured', __( 'The portal address and the API key are not set yet.', 'dernekyazilimi' ) );
		}

		$cached = get_transient( self::TEXTS_KEY );
		$cached = is_array( $cached ) ? $cached : array();
		if ( isset( $cached[ $key ] ) ) {
			return $cached[ $key ];
		}

		$response = $this->request( 'GET', 'agreements/' . rawurlencode( $key ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 404 === $response['status'] ) {
			return new WP_Error( 'dernekyazilimi_no_agreement', __( 'The portal has no published agreement with this key.', 'dernekyazilimi' ) );
		}
		if ( 200 !== $response['status'] || ! isset( $response['body']['content'] ) ) {
			return new WP_Error( 'dernekyazilimi_portal_error', $response['body']['message'] ?? __( 'The portal did not answer as expected.', 'dernekyazilimi' ), array( 'status' => $response['status'] ) );
		}

		$cached[ $key ] = $response['body'];
		set_transient( self::TEXTS_KEY, $cached, self::CONFIG_TIME );

		return $response['body'];
	}

	/**
	 * Call the portal.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   Path under /api/site.
	 * @param array  $data   JSON body.
	 * @return array{status:int,body:array}|WP_Error
	 */
	public function request( $method, $path, $data = array() ) {
		$headers = array(
			'Accept'     => 'application/json',
			'X-Api-Key'  => $this->api_key(),
			'X-Site-Url' => home_url( '/', 'https' ),
		);

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$headers['X-Client-Ip'] = $ip;
		}
		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$headers['X-Client-User-Agent'] = substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 );
		}

		$args = array(
			'method'     => $method,
			'timeout'    => 20,
			'headers'    => $headers,
			'user-agent' => 'Dernekyazilimi-WordPress/' . DERNEKYAZILIMI_VERSION . '; ' . home_url( '/' ),
		);
		if ( 'GET' !== $method ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $data );
		}

		$response = wp_remote_request( $this->portal_url() . '/api/site/' . ltrim( $path, '/' ), $args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'dernekyazilimi_unreachable', __( 'The portal could not be reached. Please try again in a moment.', 'dernekyazilimi' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return array(
			'status' => (int) wp_remote_retrieve_response_code( $response ),
			'body'   => is_array( $body ) ? $body : array(),
		);
	}
}
