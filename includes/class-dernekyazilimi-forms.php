<?php
/**
 * The forms: shortcodes, blocks and their markup.
 *
 * @package Dernekyazilimi
 */

defined( 'ABSPATH' ) || exit;

/**
 * Donation, volunteer and membership forms.
 */
class Dernekyazilimi_Forms {

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
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Register assets, shortcodes and blocks.
	 */
	public function init() {
		wp_register_style( 'dernekyazilimi-forms', DERNEKYAZILIMI_URL . 'assets/css/forms.css', array(), DERNEKYAZILIMI_VERSION );
		wp_register_script(
			'dernekyazilimi-forms',
			DERNEKYAZILIMI_URL . 'assets/js/forms.js',
			array(),
			DERNEKYAZILIMI_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_localize_script(
			'dernekyazilimi-forms',
			'dernekyazilimiForms',
			array(
				'restUrl'      => esc_url_raw( rest_url( 'dernekyazilimi/v1/' ) ),
				'portalOrigin' => $this->client->portal_origin(),
				'i18n'         => array(
					'sending'      => __( 'Sending…', 'dernekyazilimi' ),
					'failed'       => __( 'The form could not be sent. Please check your connection and try again.', 'dernekyazilimi' ),
					'required'     => __( 'Please fill in this field.', 'dernekyazilimi' ),
					'codeSent'     => __( 'We sent a code by SMS. Enter it below.', 'dernekyazilimi' ),
					'codeNeeded'   => __( 'Enter the 6-digit code.', 'dernekyazilimi' ),
					'verified'     => __( 'Your phone number is verified.', 'dernekyazilimi' ),
					'verifyFirst'  => __( 'Please verify your phone number first.', 'dernekyazilimi' ),
					'sendAgain'    => __( 'Send again', 'dernekyazilimi' ),
					'existingNote' => __( 'This email already has a portal account. Sign in below to continue.', 'dernekyazilimi' ),
					'closePayment' => __( 'Close the payment window? If you have not finished paying, your donation will not be completed.', 'dernekyazilimi' ),
				),
			)
		);

		add_shortcode( 'dernekyazilimi_donate', array( $this, 'donate' ) );
		add_shortcode( 'dernekyazilimi_volunteer', array( $this, 'volunteer' ) );
		add_shortcode( 'dernekyazilimi_membership', array( $this, 'membership' ) );

		$this->blocks();
	}

	/**
	 * Blocks that draw the same forms.
	 */
	private function blocks() {
		wp_register_script( 'dernekyazilimi-blocks', DERNEKYAZILIMI_URL . 'assets/js/blocks.js', array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ), DERNEKYAZILIMI_VERSION, true );
		wp_localize_script(
			'dernekyazilimi-blocks',
			'dernekyazilimiBlocks',
			array(
				'donate'     => array(
					'title'       => __( 'Donation form', 'dernekyazilimi' ),
					'description' => __( 'Donation form of your association\'s portal.', 'dernekyazilimi' ),
				),
				'volunteer'  => array(
					'title'       => __( 'Volunteer form', 'dernekyazilimi' ),
					'description' => __( 'Volunteer registration form of your association\'s portal.', 'dernekyazilimi' ),
				),
				'membership' => array(
					'title'       => __( 'Membership application', 'dernekyazilimi' ),
					'description' => __( 'Membership application of your association\'s portal.', 'dernekyazilimi' ),
				),
				'labels'     => array(
					'settings' => __( 'Form settings', 'dernekyazilimi' ),
					'cause'    => __( 'Donation cause number (optional)', 'dernekyazilimi' ),
					'amount'   => __( 'Amount filled in at first (optional)', 'dernekyazilimi' ),
				),
			)
		);

		$blocks = array(
			'donate'     => array(
				'cause'  => array(
					'type'    => 'string',
					'default' => '',
				),
				'amount' => array(
					'type'    => 'string',
					'default' => '',
				),
			),
			'volunteer'  => array(),
			'membership' => array(),
		);
		foreach ( $blocks as $name => $attributes ) {
			register_block_type(
				'dernekyazilimi/' . $name,
				array(
					'api_version'           => 3,
					'attributes'            => $attributes,
					'editor_script_handles' => array( 'dernekyazilimi-blocks' ),
					'style_handles'         => array( 'dernekyazilimi-forms' ),
					'render_callback'       => array( $this, $name ),
					'supports'              => array(
						'html'  => false,
						'align' => array( 'wide', 'full' ),
					),
				)
			);
		}
	}

	/**
	 * [dernekyazilimi_donate cause="3" amount="250"]
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function donate( $atts ) {
		$atts   = shortcode_atts(
			array(
				'cause'  => '',
				'amount' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'dernekyazilimi_donate'
		);
		$config = $this->section( 'donation', __( 'Online donations are not accepted at the moment.', 'dernekyazilimi' ) );
		if ( is_string( $config ) ) {
			return $config;
		}

		$donation = $config['donation'];
		$id       = wp_unique_id( 'dy-' );
		$cause    = absint( $atts['cause'] );
		$amount   = is_numeric( $atts['amount'] ) ? (float) $atts['amount'] : '';
		$methods  = (array) ( $donation['methods'] ?? array() );

		ob_start();
		$this->open( 'donation', 'donation' );
		if ( ! empty( $donation['intro'] ) ) {
			echo '<div class="dy-intro">' . wp_kses_post( $donation['intro'] ) . '</div>';
		}
		?>
		<form class="dy-form" novalidate>
			<?php $this->honeypot(); ?>
			<div class="dy-field" data-dy-field="amount">
				<label for="<?php echo esc_attr( $id ); ?>-amount"><?php esc_html_e( 'Amount (TL)', 'dernekyazilimi' ); ?> <span class="dy-required" aria-hidden="true">*</span></label>
				<?php if ( ! empty( $donation['fixed_only'] ) && ! empty( $donation['amounts'] ) ) : ?>
					<div class="dy-amounts">
						<?php foreach ( (array) $donation['amounts'] as $fixed ) : ?>
							<label class="dy-check"><input type="radio" name="amount" value="<?php echo esc_attr( (int) $fixed ); ?>" <?php checked( (float) $amount, (float) $fixed ); ?> required> <span><?php echo esc_html( number_format_i18n( (int) $fixed ) ); ?> TL</span></label>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
				<?php if ( ! empty( $donation['amounts'] ) ) : ?>
					<div class="dy-amounts">
						<?php foreach ( (array) $donation['amounts'] as $suggested ) : ?>
							<button type="button" class="dy-amount wp-element-button is-style-outline" data-dy-amount="<?php echo esc_attr( (int) $suggested ); ?>"><?php echo esc_html( number_format_i18n( (int) $suggested ) ); ?> TL</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<input type="number" inputmode="decimal" id="<?php echo esc_attr( $id ); ?>-amount" name="amount" min="<?php echo esc_attr( (int) ( $donation['minimum'] ?? 1 ) ); ?>" step="0.01" value="<?php echo esc_attr( $amount ); ?>" required>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $donation['causes'] ) ) : ?>
				<div class="dy-field" data-dy-field="cause_id">
					<label for="<?php echo esc_attr( $id ); ?>-cause"><?php esc_html_e( 'Donation cause', 'dernekyazilimi' ); ?></label>
					<select id="<?php echo esc_attr( $id ); ?>-cause" name="cause_id">
						<option value=""><?php esc_html_e( 'General donation', 'dernekyazilimi' ); ?></option>
						<?php foreach ( (array) $donation['causes'] as $item ) : ?>
							<option value="<?php echo esc_attr( (int) $item['id'] ); ?>" <?php selected( $cause, (int) $item['id'] ); ?>><?php echo esc_html( $item['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<div class="dy-row">
				<?php
				$this->input( $id, 'name', __( 'Full name', 'dernekyazilimi' ), 'text', true, 'name' );
				$this->input( $id, 'email', __( 'Email', 'dernekyazilimi' ), 'email', true, 'email' );
				?>
			</div>
			<div class="dy-row">
				<?php $this->input( $id, 'phone', __( 'Phone', 'dernekyazilimi' ), 'tel', false, 'tel' ); ?>
			</div>

			<div class="dy-field" data-dy-field="message">
				<label for="<?php echo esc_attr( $id ); ?>-message"><?php esc_html_e( 'Your message (optional)', 'dernekyazilimi' ); ?></label>
				<textarea id="<?php echo esc_attr( $id ); ?>-message" name="message" rows="2" maxlength="1000"></textarea>
			</div>

			<label class="dy-check"><input type="checkbox" name="hide_name" value="1"> <span><?php esc_html_e( 'I do not want my name to be mentioned as a donor', 'dernekyazilimi' ); ?></span></label>

			<fieldset class="dy-field" data-dy-field="method">
				<legend><?php esc_html_e( 'Payment method', 'dernekyazilimi' ); ?> <span class="dy-required" aria-hidden="true">*</span></legend>
				<?php foreach ( $methods as $index => $method ) : ?>
					<label class="dy-check"><input type="radio" name="method" value="<?php echo esc_attr( $method['key'] ); ?>" <?php checked( 0, $index ); ?> required> <span><?php echo esc_html( $method['label'] ); ?></span></label>
				<?php endforeach; ?>
				<p class="dy-hint"><?php esc_html_e( 'Your card details are entered on the secure page of the payment provider, not on this site.', 'dernekyazilimi' ); ?></p>
			</fieldset>

			<?php $this->privacy( $config ); ?>

			<p class="dy-message" role="alert" hidden></p>
			<p class="dy-actions"><button type="submit" class="dy-submit wp-element-button"><?php esc_html_e( 'Donate', 'dernekyazilimi' ); ?></button></p>
		</form>
		<?php
		$this->frame( __( 'Donation', 'dernekyazilimi' ), true );
		$this->close();

		return ob_get_clean();
	}

	/**
	 * [dernekyazilimi_volunteer]
	 *
	 * @return string
	 */
	public function volunteer() {
		$config = $this->section( 'volunteer', __( 'Volunteer registration is closed at the moment.', 'dernekyazilimi' ) );
		if ( is_string( $config ) ) {
			return $config;
		}

		return $this->account( $config, 'volunteer', $config['registration']['label'] ?? __( 'Register', 'dernekyazilimi' ) );
	}

	/**
	 * [dernekyazilimi_membership]
	 *
	 * @return string
	 */
	public function membership() {
		$config = $this->section( 'membership', __( 'Membership applications are closed at the moment.', 'dernekyazilimi' ) );
		if ( is_string( $config ) ) {
			return $config;
		}

		return $this->account( $config, 'membership', __( 'Continue to the application', 'dernekyazilimi' ) );
	}

	/**
	 * The registration form: who the person is, the verified phone, the
	 * communication permissions.
	 *
	 * @param array  $config Portal configuration.
	 * @param string $kind   "volunteer" or "membership".
	 * @param string $submit Label of the submit button.
	 * @return string
	 */
	private function account( array $config, $kind, $submit ) {
		$id = wp_unique_id( 'dy-' );

		ob_start();
		$this->open( 'account', $kind );
		?>
		<form class="dy-form" novalidate>
			<?php $this->honeypot(); ?>
			<?php if ( 'membership' === $kind ) : ?>
				<p class="dy-hint"><?php esc_html_e( 'First we open your portal account; the application form follows right after. If you already have an account, enter its email and you will be asked to sign in.', 'dernekyazilimi' ); ?></p>
			<?php endif; ?>
			<div class="dy-row">
				<?php
				$this->input( $id, 'name', __( 'First name', 'dernekyazilimi' ), 'text', true, 'given-name' );
				$this->input( $id, 'surname', __( 'Last name', 'dernekyazilimi' ), 'text', true, 'family-name' );
				?>
			</div>
			<div class="dy-row">
				<?php $this->input( $id, 'email', __( 'Email', 'dernekyazilimi' ), 'email', true, 'email' ); ?>
				<div class="dy-field" data-dy-field="phone_number">
					<label for="<?php echo esc_attr( $id ); ?>-phone_number"><?php esc_html_e( 'Mobile phone', 'dernekyazilimi' ); ?> <span class="dy-required" aria-hidden="true">*</span></label>
					<div class="dy-inline">
						<input type="tel" id="<?php echo esc_attr( $id ); ?>-phone_number" name="phone_number" autocomplete="tel" placeholder="05xx xxx xx xx" required>
						<button type="button" class="dy-send-code wp-element-button is-style-outline"><?php esc_html_e( 'Send code', 'dernekyazilimi' ); ?></button>
					</div>
					<p class="dy-hint dy-phone-state"><?php esc_html_e( 'We verify your number with a code sent by SMS.', 'dernekyazilimi' ); ?></p>
				</div>
			</div>
			<div class="dy-field dy-code" data-dy-field="code" hidden>
				<label for="<?php echo esc_attr( $id ); ?>-code"><?php esc_html_e( 'Verification code', 'dernekyazilimi' ); ?></label>
				<div class="dy-inline">
					<input type="text" id="<?php echo esc_attr( $id ); ?>-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}">
					<button type="button" class="dy-verify-code wp-element-button is-style-outline"><?php esc_html_e( 'Verify', 'dernekyazilimi' ); ?></button>
				</div>
			</div>

			<?php if ( ! empty( $config['registration']['consents'] ) ) : ?>
				<fieldset class="dy-field">
					<legend><?php esc_html_e( 'Communication permissions', 'dernekyazilimi' ); ?></legend>
					<?php foreach ( (array) $config['registration']['consents'] as $channel => $label ) : ?>
						<label class="dy-check"><input type="checkbox" name="consents[<?php echo esc_attr( $channel ); ?>]" value="1"> <span><?php echo esc_html( $label ); ?></span></label>
					<?php endforeach; ?>
				</fieldset>
			<?php endif; ?>

			<?php $this->privacy( $config ); ?>

			<p class="dy-message" role="alert" hidden></p>
			<p class="dy-actions"><button type="submit" class="dy-submit wp-element-button"><?php echo esc_html( $submit ); ?></button></p>
		</form>
		<div class="dy-done" hidden>
			<p><strong><?php esc_html_e( 'Your registration is complete. Thank you!', 'dernekyazilimi' ); ?></strong></p>
			<p><?php esc_html_e( 'We sent you an email with a link to set your password for the portal.', 'dernekyazilimi' ); ?></p>
		</div>
		<?php
		$this->frame( __( 'Membership application', 'dernekyazilimi' ) );
		$this->close();

		return ob_get_clean();
	}

	/**
	 * The portal configuration when the form can be shown, otherwise the
	 * notice to show instead.
	 *
	 * @param string $key    Section of the configuration.
	 * @param string $closed Notice for visitors when the form is closed.
	 * @return array|string
	 */
	private function section( $key, $closed ) {
		$config = $this->client->config();

		if ( is_wp_error( $config ) || ! isset( $config[ $key ] ) ) {
			// Visitors see nothing broken; people who can fix it see why.
			if ( ! current_user_can( 'manage_options' ) ) {
				return '';
			}
			$reason = is_wp_error( $config ) ? $config->get_error_message() : __( 'This module is off in the portal.', 'dernekyazilimi' );

			return '<p class="dernekyazilimi-notice"><strong>Dernek Yazılımı:</strong> ' . esc_html( $reason ) . ' <a href="' . esc_url( admin_url( 'options-general.php?page=dernekyazilimi' ) ) . '">' . esc_html__( 'Settings', 'dernekyazilimi' ) . '</a></p>';
		}

		if ( empty( $config[ $key ]['open'] ) ) {
			return '<p class="dernekyazilimi-notice">' . esc_html( $closed ) . '</p>';
		}

		return $config;
	}

	/**
	 * Start of a form's wrapper; loads the assets.
	 *
	 * @param string $form "donation" or "account".
	 * @param string $kind "donation", "volunteer" or "membership".
	 */
	private function open( $form, $kind ) {
		wp_enqueue_style( 'dernekyazilimi-forms' );
		wp_enqueue_script( 'dernekyazilimi-forms' );

		printf( '<div class="dernekyazilimi" data-dy-form="%1$s" data-dy-kind="%2$s">', esc_attr( $form ), esc_attr( $kind ) );
	}

	/**
	 * End of a form's wrapper.
	 */
	private function close() {
		echo '</div>';
	}

	/**
	 * A text input with its label.
	 *
	 * @param string $id           Prefix of the element id.
	 * @param string $name         Field name.
	 * @param string $label        Label.
	 * @param string $type         Input type.
	 * @param bool   $required     Whether it must be filled in.
	 * @param string $autocomplete Autocomplete token.
	 */
	private function input( $id, $name, $label, $type, $required, $autocomplete ) {
		echo '<div class="dy-field" data-dy-field="' . esc_attr( $name ) . '">';
		echo '<label for="' . esc_attr( $id . '-' . $name ) . '">' . esc_html( $label );
		if ( $required ) {
			echo ' <span class="dy-required" aria-hidden="true">*</span>';
		}
		echo '</label>';
		echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id . '-' . $name ) . '" name="' . esc_attr( $name ) . '" autocomplete="' . esc_attr( $autocomplete ) . '"' . ( $required ? ' required' : '' ) . '>';
		echo '</div>';
	}

	/**
	 * The privacy policy checkbox, while the portal has a policy in force.
	 *
	 * @param array $config Portal configuration.
	 */
	private function privacy( array $config ) {
		if ( empty( $config['privacy']['url'] ) ) {
			return;
		}
		$link = '<a href="' . esc_url( $config['privacy']['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $config['privacy']['title'] ?? __( 'privacy policy', 'dernekyazilimi' ) ) . '</a>';
		echo '<div class="dy-field" data-dy-field="agreement"><label class="dy-check"><input type="checkbox" name="agreement" value="1" required> <span>';
		/* translators: %s: link to the privacy policy. */
		echo wp_kses_post( sprintf( __( 'I have read and accept: %s', 'dernekyazilimi' ), $link ) );
		echo ' <span class="dy-required" aria-hidden="true">*</span></span></label></div>';
	}

	/**
	 * A field people do not see; bots that fill it are turned away.
	 */
	private function honeypot() {
		echo '<div class="dy-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
	}

	/**
	 * Where the portal's page is shown after the form. With a modal, the card
	 * payment opens in a dialog over the page and the result returns here.
	 *
	 * @param string $title Title of the frame for screen readers.
	 * @param bool   $modal Whether to add the payment dialog.
	 */
	private function frame( $title, $modal = false ) {
		?>
		<div class="dy-frame" hidden>
			<p class="dy-note" hidden></p>
			<iframe title="<?php echo esc_attr( $title ); ?>" allow="payment" referrerpolicy="strict-origin-when-cross-origin"></iframe>
			<p class="dy-hint dy-fallback"><?php esc_html_e( 'Page not showing?', 'dernekyazilimi' ); ?> <a href="#" target="_blank" rel="noopener"><?php esc_html_e( 'Open it in a new tab', 'dernekyazilimi' ); ?></a></p>
		</div>
		<?php if ( $modal ) : ?>
			<?php $label = wp_unique_id( 'dy-modal-' ); ?>
			<dialog class="dy-modal" aria-labelledby="<?php echo esc_attr( $label ); ?>">
				<div class="dy-modal-head">
					<strong id="<?php echo esc_attr( $label ); ?>"><?php esc_html_e( 'Secure payment', 'dernekyazilimi' ); ?></strong>
					<button type="button" class="dy-modal-close" aria-label="<?php esc_attr_e( 'Close', 'dernekyazilimi' ); ?>">&times;</button>
				</div>
				<div class="dy-modal-body"></div>
			</dialog>
			<?php
		endif;
	}
}
