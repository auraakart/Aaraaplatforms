<?php
/**
 * WCFMu plugin core
 *
 * Customer multiple delivery addresses (lat/lng, primary, REST API).
 * Primary address syncs to WC billing_* / shipping_* usermeta.
 *
 * @author  Aaraakart
 * @package wcfmu/core
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WCFMu_Customer_Addresses {

	const DB_VERSION = '1.1.0';
	const TABLE      = 'customer_addresses';

	// REST namespace — endpoints live at /wp-json/addresses/ with customer_id and
	// addr_id passed as query parameters.
	const REST_NS = 'addresses';

	public function __construct() {
		// This class is instantiated on wcfm_init (during `init`), which is AFTER
		// plugins_loaded has fired — so hooking plugins_loaded here would register
		// a callback for a hook that already ran, and the table would never be
		// created. Run it now when plugins_loaded is already done, otherwise defer.
		if ( did_action( 'plugins_loaded' ) ) {
			$this->maybe_create_table();
		} else {
			add_action( 'plugins_loaded', [ $this, 'maybe_create_table' ], 20 );
		}

		add_action( 'end_wcfm_customers_manage_form', [ $this, 'render_section' ] );

		add_action( 'wp_ajax_wcfmu_aca_save',   [ $this, 'ajax_save' ] );
		add_action( 'wp_ajax_wcfmu_aca_delete', [ $this, 'ajax_delete' ] );

		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Customer-facing address book on My Account → Addresses. Replaces the
		// default billing/shipping edit-address screen (no shipping) with a
		// multiple-address manager that supports add / edit / delete and setting
		// a primary. wp_loaded runs after WooCommerce registers its own endpoint
		// callback, so remove_action() below is guaranteed to find it.
		add_action( 'wp_loaded', [ $this, 'take_over_myaccount_addresses' ], 20 );
		add_action( 'wp_ajax_wcfmu_aca_my_save',    [ $this, 'ajax_my_save' ] );
		add_action( 'wp_ajax_wcfmu_aca_my_delete',  [ $this, 'ajax_my_delete' ] );
		add_action( 'wp_ajax_wcfmu_aca_my_primary', [ $this, 'ajax_my_primary' ] );
	}

	/**
	 * Replace WooCommerce's billing/shipping edit-address screen with our own
	 * multiple-address manager on the storefront My Account page.
	 *
	 * @return void
	 */
	public function take_over_myaccount_addresses(): void {
		if ( is_admin() ) {
			return;
		}
		remove_action( 'woocommerce_account_edit-address_endpoint', 'woocommerce_account_edit_address' );
		add_action( 'woocommerce_account_edit-address_endpoint', [ $this, 'render_myaccount_addresses' ] );
	}

	// ── DB ────────────────────────────────────────────────────────────────────

	public function maybe_create_table(): void {
		// Verify the table actually exists, not just the version option. On a
		// database seeded from another site the option can be present while the
		// table is not (e.g. this vendor DB), which left the option guard
		// short-circuiting creation and every insert failing with
		// "Table ... doesn't exist". Existence is the real signal.
		if ( $this->table_exists() && get_option( 'wcfmu_aca_db_version' ) === self::DB_VERSION ) {
			return;
		}

		if ( $this->table_exists() ) {
			// Already there but on an older schema — add any missing columns.
			$this->maybe_add_columns();
		} else {
			$this->create_table();
		}

		update_option( 'wcfmu_aca_db_version', self::DB_VERSION );
	}

	/**
	 * Add columns introduced after the first release to an existing table.
	 *
	 * Idempotent: each column is added only when missing, so it is safe to run
	 * on every schema-version bump and across every environment.
	 *
	 * @return void
	 */
	private function maybe_add_columns(): void {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

		$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 ); // phpcs:ignore WordPress.DB

		if ( ! in_array( 'name', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN name VARCHAR(100) NOT NULL DEFAULT '' AFTER customer_id" ); // phpcs:ignore WordPress.DB
		}
		if ( ! in_array( 'mobile_number', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN mobile_number VARCHAR(20) NOT NULL DEFAULT '' AFTER name" ); // phpcs:ignore WordPress.DB
		}
	}

	/**
	 * Whether the addresses table exists in the current database.
	 *
	 * @return bool
	 */
	private function table_exists(): bool {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table; // phpcs:ignore WordPress.DB
	}

	private function create_table(): void {
		global $wpdb;
		$table   = $wpdb->prefix . self::TABLE;
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( "CREATE TABLE IF NOT EXISTS {$table} (
			id          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
			customer_id BIGINT UNSIGNED   NOT NULL,
			name        VARCHAR(100)      NOT NULL DEFAULT '',
			mobile_number VARCHAR(20)     NOT NULL DEFAULT '',
			label       VARCHAR(100)      NOT NULL DEFAULT 'Home',
			address_1   VARCHAR(255)      NOT NULL DEFAULT '',
			address_2   VARCHAR(255)      NOT NULL DEFAULT '',
			city        VARCHAR(100)      NOT NULL DEFAULT '',
			state       VARCHAR(100)      NOT NULL DEFAULT 'TN',
			postcode    VARCHAR(20)       NOT NULL DEFAULT '',
			country     VARCHAR(2)        NOT NULL DEFAULT 'IN',
			latitude    DECIMAL(10,8)     DEFAULT NULL,
			longitude   DECIMAL(11,8)     DEFAULT NULL,
			is_primary  TINYINT(1)        NOT NULL DEFAULT 0,
			created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_customer (customer_id),
			KEY idx_primary  (customer_id, is_primary)
		) {$charset};" );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	public function get_addresses( int $customer_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE customer_id = %d ORDER BY is_primary DESC, id ASC",
				$customer_id
			),
			ARRAY_A
		) ?: [];
	}

	public function set_primary( int $addr_id, int $customer_id ): void {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

		$wpdb->update( $table, [ 'is_primary' => 0 ], [ 'customer_id' => $customer_id ] );
		$wpdb->update( $table, [ 'is_primary' => 1 ], [ 'id' => $addr_id, 'customer_id' => $customer_id ] );

		$addr = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND customer_id = %d", $addr_id, $customer_id ),
			ARRAY_A
		);
		if ( ! $addr ) return;

		foreach ( [ 'billing', 'shipping' ] as $type ) {
			update_user_meta( $customer_id, "{$type}_address_1", $addr['address_1'] );
			update_user_meta( $customer_id, "{$type}_address_2", $addr['address_2'] );
			update_user_meta( $customer_id, "{$type}_city",      $addr['city'] );
			update_user_meta( $customer_id, "{$type}_state",     $addr['state'] );
			update_user_meta( $customer_id, "{$type}_postcode",  $addr['postcode'] );
			update_user_meta( $customer_id, "{$type}_country",   $addr['country'] );
		}
	}

	/**
	 * Coerce an incoming country value to a valid 2-letter ISO code.
	 *
	 * The `country` column is VARCHAR(2), so a full country name ("India") or any
	 * value longer than two characters makes the insert fail. Accept either a
	 * 2-letter code (e.g. "IN") or a full country name (e.g. "India") and resolve
	 * it against WooCommerce's country list; fall back to "IN" when it can't be
	 * matched, so a stray value never breaks saving the address.
	 *
	 * @param mixed $value Raw country value from the request.
	 * @return string A 2-letter uppercase country code.
	 */
	private function normalize_country( $value ): string {
		$value = strtoupper( trim( sanitize_text_field( (string) $value ) ) );

		if ( '' === $value ) {
			return 'IN';
		}

		$countries = ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_countries() : array();

		// Already a valid 2-letter code.
		if ( 2 === strlen( $value ) && ( empty( $countries ) || isset( $countries[ $value ] ) ) ) {
			return $value;
		}

		// A full country name — map it back to its code.
		foreach ( $countries as $code => $name ) {
			if ( strtoupper( $name ) === $value ) {
				return $code;
			}
		}

		// Unknown value: keep the first two letters only if plausible, else default.
		return 2 === strlen( $value ) ? $value : 'IN';
	}

	private function extract_fields( array $raw, int $customer_id ): array {
		return [
			'customer_id'   => $customer_id,
			'name'          => sanitize_text_field( $raw['name']          ?? '' ),
			'mobile_number' => sanitize_text_field( $raw['mobile_number'] ?? '' ),
			'label'       => sanitize_text_field( $raw['label']     ?? 'Home' ),
			'address_1'   => sanitize_text_field( $raw['address_1'] ?? '' ),
			'address_2'   => sanitize_text_field( $raw['address_2'] ?? '' ),
			'city'        => sanitize_text_field( $raw['city']      ?? '' ),
			'state'       => sanitize_text_field( $raw['state']     ?? 'TN' ),
			'postcode'    => sanitize_text_field( $raw['postcode']  ?? '' ),
			'country'     => $this->normalize_country( $raw['country'] ?? 'IN' ),
			'latitude'    => is_numeric( $raw['latitude']  ?? '' ) ? (float) $raw['latitude']  : null,
			'longitude'   => is_numeric( $raw['longitude'] ?? '' ) ? (float) $raw['longitude'] : null,
			'is_primary'  => ! empty( $raw['is_primary'] ) ? 1 : 0,
		];
	}

	/**
	 * Extract only the fields actually present in the request, for partial (PATCH)
	 * updates — so omitted fields keep their stored value instead of being reset
	 * to a default. customer_id is intentionally excluded: it is the row's owner,
	 * not something an update may change.
	 *
	 * @param array $raw Incoming JSON/body params.
	 * @return array Sanitised column => value for only the provided fields.
	 */
	private function extract_provided_fields( array $raw ): array {
		$out = [];
		foreach ( [ 'name', 'mobile_number', 'label', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'latitude', 'longitude', 'is_primary' ] as $key ) {
			if ( ! array_key_exists( $key, $raw ) ) {
				continue;
			}
			switch ( $key ) {
				case 'country':
					$out[ $key ] = $this->normalize_country( $raw[ $key ] );
					break;
				case 'latitude':
				case 'longitude':
					$out[ $key ] = is_numeric( $raw[ $key ] ) ? (float) $raw[ $key ] : null;
					break;
				case 'is_primary':
					$out[ $key ] = ! empty( $raw[ $key ] ) ? 1 : 0;
					break;
				default:
					$out[ $key ] = sanitize_text_field( $raw[ $key ] );
			}
		}
		return $out;
	}

	private function ensure_one_primary( int $customer_id ): void {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		$has = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE customer_id = %d AND is_primary = 1", $customer_id
		) );
		if ( $has ) return;
		$first = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE customer_id = %d ORDER BY id ASC LIMIT 1", $customer_id
		) );
		if ( $first ) $this->set_primary( $first, $customer_id );
	}

	// ── View ──────────────────────────────────────────────────────────────────

	public function render_section( int $customer_id ): void {
		if ( ! $customer_id ) return;
		$addresses = $this->get_addresses( $customer_id );
		?>
		<div class="wcfm_clearfix"></div>

		<div class="page_collapsible" id="wcfm_customer_addresses_head" style="cursor:pointer;">
			<label class="wcfmfa fa-map-marker-alt" style="margin-right:6px;"></label>
			<?php _e( 'Delivery Addresses', 'wc-frontend-manager-ultimate' ); ?><span></span>
		</div>

		<div class="wcfm-container">
			<div id="wcfm_customer_addresses_expander" class="wcfm-content" style="padding:16px 20px;">
				<div id="wcfmu-aca-list">
					<?php foreach ( $addresses as $addr ) $this->render_card( $addr ); ?>
				</div>
				<button type="button" id="wcfmu-aca-add" class="button" style="margin-top:10px;">
					+ <?php _e( 'Add Address', 'wc-frontend-manager-ultimate' ); ?>
				</button>
				<div id="wcfmu-aca-global-msg" style="margin-top:8px;font-size:13px;"></div>
			</div>
		</div>

		<div class="wcfm_clearfix"></div>

		<?php wp_nonce_field( 'wcfmu_aca_nonce_action', 'wcfmu_aca_nonce' ); ?>
		<input type="hidden" id="wcfmu_aca_customer_id" value="<?php echo esc_attr( $customer_id ); ?>" />

		<style>
		/*
		 * .wcfm-tabWrap uses overflow:hidden + the right panel is position:absolute,
		 * so its height is driven only by the left nav items — content gets clipped.
		 * overflow:visible lets the right panel grow past the wrap boundary,
		 * and JS below adjusts min-height so the page scrollbar reaches it.
		 */
		#wcfm_customer_manage_form .wcfm-tabWrap {
			overflow: visible !important;
		}
		.wcfmu-aca-save-btn {
			background: #2563eb !important;
			border-color: #1d4ed8 !important;
			color: #fff !important;
		}
		.wcfmu-aca-save-btn:hover { background: #1d4ed8 !important; }
		.wcfmu-aca-delete-btn {
			background: #dc2626 !important;
			border-color: #b91c1c !important;
			color: #fff !important;
		}
		.wcfmu-aca-delete-btn:hover { background: #b91c1c !important; }
		</style>

		<script>
		jQuery(function($) {
			var customerId = $('#wcfmu_aca_customer_id').val();
			var nonce      = $('#wcfmu_aca_nonce').val();

			// WCFM's own page_collapsible handler manages expand/collapse — no duplicate handler needed.

			// Expand tabWrap height to fit our absolutely-positioned content panel.
			function fitTabWrap() {
				var $wrap    = $('#wcfm_customer_addresses_expander').closest('.wcfm-tabWrap');
				var $content = $('#wcfm_customer_addresses_expander');
				if ( !$wrap.length || !$content.is(':visible') ) return;
				var needed = $content.outerHeight(true) + $content.position().top + 32;
				var leftH  = 0;
				$wrap.find('.page_collapsible').each(function() { leftH += $(this).outerHeight(true); });
				$wrap.css('min-height', Math.max(needed, leftH) + 'px');
			}

			// Run on tab reveal (WCFM fires a click that shows the expander).
			$(document).on('click', '#wcfm_customer_addresses_head', function() {
				setTimeout(fitTabWrap, 50);
			});

			$('#wcfmu-aca-add').on('click', function() {
				var $card = $( blankCardHtml() );
				$('#wcfmu-aca-list').append( $card );
				bindCard( $card );
				setTimeout(fitTabWrap, 50);
			});

			$('#wcfmu-aca-list .wcfmu-aca-card').each(function() {
				bindCard( $(this) );
			});

			function blankCardHtml() {
				return '<div class="wcfmu-aca-card" data-id="0" style="border:1px solid #ddd;padding:14px;margin-bottom:14px;background:#fff;border-radius:4px;">' +
					fieldsHtml('','','','TN','','IN','','','Home') +
					actionsHtml(false) +
				'</div>';
			}

			function fieldsHtml(a1,a2,city,state,postcode,country,lat,lng,label) {
				state   = state   || 'TN';
				country = country || 'IN';
				return '<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-start;">' +
					field('Label',         'wcfmu-aca-label',    label,    'min-width:140px;') +
					field('Address Line 1','wcfmu-aca-addr1',    a1,       'flex:1;min-width:200px;') +
					field('Address Line 2','wcfmu-aca-addr2',    a2,       'flex:1;min-width:200px;') +
					field('City',          'wcfmu-aca-city',     city,     'min-width:120px;') +
					field('State',         'wcfmu-aca-state',    state,    'min-width:120px;') +
					field('Postcode',      'wcfmu-aca-postcode', postcode, 'min-width:90px;') +
					field('Country',       'wcfmu-aca-country',  country,  'min-width:90px;') +
					field('Latitude',      'wcfmu-aca-lat',      lat,      'min-width:130px;','e.g. 11.0168') +
					field('Longitude',     'wcfmu-aca-lng',      lng,      'min-width:130px;','e.g. 76.9558') +
				'</div>';
			}

			function field(lbl, cls, val, wrap, placeholder) {
				wrap = wrap || 'min-width:120px;';
				var ph = placeholder ? ' placeholder="' + placeholder + '"' : '';
				return '<div style="' + wrap + '">' +
					'<label style="display:block;font-size:12px;color:#555;margin-bottom:3px;">' + lbl + '</label>' +
					'<input type="text" class="wcfm-text ' + cls + '" value="' + $('<div>').text(val||'').html() + '"' + ph + ' style="width:100%;" />' +
				'</div>';
			}

			function actionsHtml(isPrimary) {
				var indicator = isPrimary
					? '<strong style="color:#1e3a8a;">&#10003; Primary</strong>'
					: '<label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;"><input type="radio" class="wcfmu-aca-primary-radio" name="wcfmu_aca_primary" /> Set as Primary</label>';
				return '<div style="margin-top:10px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">' +
					'<span class="wcfmu-aca-primary-indicator">' + indicator + '</span>' +
					'<button type="button" class="button wcfmu-aca-save-btn">Save</button>' +
					'<button type="button" class="button wcfmu-aca-delete-btn">Delete</button>' +
					'<span class="wcfmu-aca-card-msg" style="font-size:12px;"></span>' +
				'</div>';
			}

			function bindCard($card) {
				$card.find('.wcfmu-aca-save-btn').on('click', function() {
					var $c       = $(this).closest('.wcfmu-aca-card');
					var id       = parseInt($c.data('id')) || 0;
					var $msg     = $c.find('.wcfmu-aca-card-msg');
					var addr1    = $.trim($c.find('.wcfmu-aca-addr1').val());
					if (!addr1) { $msg.text('Address Line 1 is required.').css('color','#dc2626'); return; }

					var isPrimary = $c.find('.wcfmu-aca-primary-radio').is(':checked') ? 1 : 0;

					$(this).prop('disabled', true);
					$msg.text('Saving…').css('color','#6b7280');

					$.post(wcfm_params.ajax_url, {
						action:      'wcfmu_aca_save',
						wcfmu_aca_nonce: nonce,
						customer_id: customerId,
						address_id:  id,
						label:       $c.find('.wcfmu-aca-label').val(),
						address_1:   addr1,
						address_2:   $c.find('.wcfmu-aca-addr2').val(),
						city:        $c.find('.wcfmu-aca-city').val(),
						state:       $c.find('.wcfmu-aca-state').val(),
						postcode:    $c.find('.wcfmu-aca-postcode').val(),
						country:     $c.find('.wcfmu-aca-country').val(),
						latitude:    $c.find('.wcfmu-aca-lat').val(),
						longitude:   $c.find('.wcfmu-aca-lng').val(),
						is_primary:  isPrimary
					}, function(resp) {
						$c.find('.wcfmu-aca-save-btn').prop('disabled', false);
						if (resp.success) {
							$msg.text('Saved.').css('color','#16a34a');
							if (id === 0) $c.data('id', resp.data.id);
							if (isPrimary) {
								$('#wcfmu-aca-list .wcfmu-aca-card').each(function() {
									if ($(this)[0] === $c[0]) return;
									$(this).find('.wcfmu-aca-primary-indicator').html(
										'<label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;">' +
										'<input type="radio" class="wcfmu-aca-primary-radio" name="wcfmu_aca_primary" /> Set as Primary</label>'
									);
									$(this).css('border-color','#ddd');
								});
								$c.find('.wcfmu-aca-primary-indicator').html('<strong style="color:#1e3a8a;">&#10003; Primary</strong>');
								$c.css('border-color','#1e3a8a');
							}
						} else {
							$msg.text(resp.data || 'Error saving.').css('color','#dc2626');
						}
					}).fail(function() { $c.find('.wcfmu-aca-save-btn').prop('disabled', false); });
				});

				$card.find('.wcfmu-aca-delete-btn').on('click', function() {
					var $c = $(this).closest('.wcfmu-aca-card');
					var id = parseInt($c.data('id')) || 0;
					if (id === 0) { $c.remove(); return; }
					if (!confirm('Delete this address?')) return;

					var $msg = $c.find('.wcfmu-aca-card-msg');
					$msg.text('Deleting…').css('color','#6b7280');
					$.post(wcfm_params.ajax_url, {
						action:          'wcfmu_aca_delete',
						wcfmu_aca_nonce: nonce,
						customer_id:     customerId,
						address_id:      id
					}, function(resp) {
						if (resp.success) {
							$c.slideUp(200, function() { $c.remove(); fitTabWrap(); });
						} else {
							$msg.text(resp.data || 'Error deleting.').css('color','#dc2626');
						}
					});
				});
			}
		});
		</script>
		<?php
	}

	private function render_card( array $addr ): void {
		$is_primary = (int) $addr['is_primary'];
		$border     = $is_primary ? '#1e3a8a' : '#ddd';
		$fields = [
			[ 'Label',         'wcfmu-aca-label',    $addr['label'],              'min-width:140px;' ],
			[ 'Address Line 1','wcfmu-aca-addr1',    $addr['address_1'],          'flex:1;min-width:200px;' ],
			[ 'Address Line 2','wcfmu-aca-addr2',    $addr['address_2'],          'flex:1;min-width:200px;' ],
			[ 'City',          'wcfmu-aca-city',     $addr['city'],               'min-width:120px;' ],
			[ 'State',         'wcfmu-aca-state',    $addr['state']    ?: 'TN',   'min-width:120px;' ],
			[ 'Postcode',      'wcfmu-aca-postcode', $addr['postcode'],            'min-width:90px;' ],
			[ 'Country',       'wcfmu-aca-country',  $addr['country']  ?: 'IN',   'min-width:90px;' ],
			[ 'Latitude',      'wcfmu-aca-lat',      $addr['latitude'],            'min-width:130px;', 'e.g. 11.0168' ],
			[ 'Longitude',     'wcfmu-aca-lng',      $addr['longitude'],           'min-width:130px;', 'e.g. 76.9558' ],
		];
		?>
		<div class="wcfmu-aca-card" data-id="<?php echo esc_attr( $addr['id'] ); ?>"
			style="border:1px solid <?php echo esc_attr( $border ); ?>;padding:14px;margin-bottom:14px;background:#fff;border-radius:4px;">
			<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-start;">
				<?php foreach ( $fields as $f ) : ?>
				<div style="<?php echo esc_attr( $f[3] ); ?>">
					<label style="display:block;font-size:12px;color:#555;margin-bottom:3px;"><?php echo esc_html( $f[0] ); ?></label>
					<input type="text" class="wcfm-text <?php echo esc_attr( $f[1] ); ?>"
						value="<?php echo esc_attr( $f[2] ?? '' ); ?>"
						<?php if ( ! empty( $f[4] ) ) echo 'placeholder="' . esc_attr( $f[4] ) . '"'; ?>
						style="width:100%;" />
				</div>
				<?php endforeach; ?>
			</div>
			<div style="margin-top:10px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
				<span class="wcfmu-aca-primary-indicator">
					<?php if ( $is_primary ) : ?>
						<strong style="color:#1e3a8a;">&#10003; Primary</strong>
					<?php else : ?>
						<label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;">
							<input type="radio" class="wcfmu-aca-primary-radio" name="wcfmu_aca_primary" />
							<?php _e( 'Set as Primary', 'wc-frontend-manager-ultimate' ); ?>
						</label>
					<?php endif; ?>
				</span>
				<button type="button" class="button wcfmu-aca-save-btn"><?php _e( 'Save', 'wc-frontend-manager-ultimate' ); ?></button>
				<button type="button" class="button wcfmu-aca-delete-btn"><?php _e( 'Delete', 'wc-frontend-manager-ultimate' ); ?></button>
				<span class="wcfmu-aca-card-msg" style="font-size:12px;"></span>
			</div>
		</div>
		<?php
	}

	// ── AJAX ──────────────────────────────────────────────────────────────────

	public function ajax_save(): void {
		if ( ! check_ajax_referer( 'wcfmu_aca_nonce_action', 'wcfmu_aca_nonce', false ) ) {
			wp_send_json_error( 'Invalid nonce.' );
		}

		$customer_id = absint( $_POST['customer_id'] ?? 0 );
		$address_id  = absint( $_POST['address_id']  ?? 0 );
		if ( ! $customer_id ) { wp_send_json_error( 'Invalid customer.' ); }

		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		$data  = $this->extract_fields( $_POST, $customer_id );

		if ( $address_id ) {
			$wpdb->update( $table, $data, [ 'id' => $address_id, 'customer_id' => $customer_id ] );
		} else {
			$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE customer_id = %d", $customer_id ) );
			if ( $count === 0 ) $data['is_primary'] = 1;
			$wpdb->insert( $table, $data );
			$address_id = (int) $wpdb->insert_id;
		}

		if ( $data['is_primary'] ) {
			$this->set_primary( $address_id, $customer_id );
		} else {
			$this->ensure_one_primary( $customer_id );
		}

		wp_send_json_success( [ 'id' => $address_id ] );
	}

	public function ajax_delete(): void {
		if ( ! check_ajax_referer( 'wcfmu_aca_nonce_action', 'wcfmu_aca_nonce', false ) ) {
			wp_send_json_error( 'Invalid nonce.' );
		}

		$customer_id = absint( $_POST['customer_id'] ?? 0 );
		$address_id  = absint( $_POST['address_id']  ?? 0 );
		if ( ! $customer_id || ! $address_id ) { wp_send_json_error( 'Invalid params.' ); }

		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE customer_id = %d", $customer_id ) );
		if ( $count <= 1 ) { wp_send_json_error( 'At least one address must remain.' ); }

		$was_primary = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT is_primary FROM {$table} WHERE id = %d AND customer_id = %d", $address_id, $customer_id
		) );
		$wpdb->delete( $table, [ 'id' => $address_id, 'customer_id' => $customer_id ] );

		if ( $was_primary ) {
			$this->ensure_one_primary( $customer_id );
		}

		wp_send_json_success();
	}

	// ── My Account (customer-facing) ────────────────────────────────────────────

	/**
	 * Front-end nonce action for the customer address book.
	 */
	const MY_NONCE = 'wcfmu_aca_my';

	/**
	 * On first view, seed the address book from the customer's existing
	 * WooCommerce billing address so they don't start with an empty list.
	 *
	 * @param int $customer_id Customer id.
	 * @return void
	 */
	private function maybe_seed_from_wc( int $customer_id ): void {
		if ( $this->get_addresses( $customer_id ) ) {
			return;
		}
		$address_1 = get_user_meta( $customer_id, 'billing_address_1', true );
		if ( '' === (string) $address_1 ) {
			return;
		}

		global $wpdb;
		$user = get_userdata( $customer_id );
		$wpdb->insert(
			$wpdb->prefix . self::TABLE,
			[
				'customer_id'   => $customer_id,
				'name'          => trim( get_user_meta( $customer_id, 'billing_first_name', true ) . ' ' . get_user_meta( $customer_id, 'billing_last_name', true ) ),
				'mobile_number' => (string) get_user_meta( $customer_id, 'billing_phone', true ),
				'label'         => 'Home',
				'address_1'     => (string) $address_1,
				'address_2'     => (string) get_user_meta( $customer_id, 'billing_address_2', true ),
				'city'          => (string) get_user_meta( $customer_id, 'billing_city', true ),
				'state'         => (string) ( get_user_meta( $customer_id, 'billing_state', true ) ?: 'TN' ),
				'postcode'      => (string) get_user_meta( $customer_id, 'billing_postcode', true ),
				'country'       => $this->normalize_country( get_user_meta( $customer_id, 'billing_country', true ) ?: 'IN' ),
				'is_primary'    => 1,
			]
		);
	}

	/**
	 * Render the customer's address book on My Account → Addresses.
	 *
	 * @param string $type Endpoint type (billing|shipping) — ignored; we show the
	 *                     full address book regardless.
	 * @return void
	 */
	public function render_myaccount_addresses( $type = '' ): void {
		$customer_id = get_current_user_id();
		if ( ! $customer_id ) {
			return;
		}

		$this->maybe_seed_from_wc( $customer_id );
		$addresses = $this->get_addresses( $customer_id );
		$labels    = [ 'Home', 'Work', 'Other' ];
		?>
		<div class="aca-book" id="aca-book">
			<p class="aca-intro"><?php esc_html_e( 'Manage your delivery addresses. Set one as primary — it is used by default for your orders and deliveries.', 'wc-frontend-manager-ultimate' ); ?></p>

			<div id="aca-list">
				<?php
				if ( $addresses ) {
					foreach ( $addresses as $addr ) {
						$this->render_myaccount_card( $addr, $labels );
					}
				}
				?>
			</div>

			<button type="button" class="button aca-btn aca-btn--add" id="aca-add">+ <?php esc_html_e( 'Add New Address', 'wc-frontend-manager-ultimate' ); ?></button>
			<div id="aca-global-msg" class="aca-msg"></div>
		</div>

		<?php wp_nonce_field( self::MY_NONCE, 'aca_nonce' ); ?>

		<style>
			.aca-book { max-width: 720px; }
			.aca-intro { color: #64748b; font-size: 13.5px; margin: 0 0 16px; }
			.aca-card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 16px; background: #fff; }
			.aca-card.is-primary { border-color: #1e3a8a; box-shadow: 0 0 0 1px #1e3a8a inset; }
			.aca-grid { display: flex; flex-wrap: wrap; gap: 12px; }
			.aca-f { display: flex; flex-direction: column; }
			.aca-f label { font-size: 12px; color: #475569; margin-bottom: 4px; }
			.aca-f input, .aca-f select { padding: 9px 10px; border: 1px solid #cbd5e1; border-radius: 8px; width: 100%; box-sizing: border-box; }
			.aca-f--full { flex: 1 1 100%; }
			.aca-f--half { flex: 1 1 calc(50% - 6px); min-width: 160px; }
			.aca-f--third { flex: 1 1 calc(33.33% - 8px); min-width: 120px; }
			.aca-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 14px; }
			.aca-primary-wrap { margin-right: auto; font-size: 13px; }
			.aca-primary-flag { color: #1e3a8a; font-weight: 700; }
			.aca-primary-set { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; color: #334155; }
			.aca-btn { border-radius: 8px; cursor: pointer; font-weight: 600; padding: 9px 16px; border: 1px solid transparent; }
			.aca-btn--add { background: #1e3a8a; color: #fff; }
			.aca-btn--save { background: #16a34a; color: #fff; }
			.aca-btn--delete { background: #fff; color: #dc2626; border-color: #dc2626; }
			.aca-btn[disabled] { opacity: .6; cursor: default; }
			.aca-msg { font-size: 12.5px; margin-top: 6px; }
			.aca-card-msg { font-size: 12.5px; }
		</style>

		<script>
		( function ( $ ) {
			var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
			var nonce   = $( '#aca_nonce' ).val();
			var labels  = <?php echo wp_json_encode( $labels ); ?>;

			function esc( s ) { return $( '<div>' ).text( s == null ? '' : s ).html(); }

			function labelOptions( sel ) {
				var out = '';
				labels.forEach( function ( l ) {
					out += '<option value="' + esc( l ) + '"' + ( l === sel ? ' selected' : '' ) + '>' + esc( l ) + '</option>';
				} );
				return out;
			}

			function cardHtml( a ) {
				a = a || {};
				var isPrimary = parseInt( a.is_primary ) === 1;
				return '<div class="aca-card' + ( isPrimary ? ' is-primary' : '' ) + '" data-id="' + ( parseInt( a.id ) || 0 ) + '">' +
					'<div class="aca-grid">' +
						f( 'Name', 'name', a.name, 'half' ) +
						f( 'Mobile Number', 'mobile_number', a.mobile_number, 'half' ) +
						'<div class="aca-f aca-f--third"><label>Label</label><select class="aca-label">' + labelOptions( a.label || 'Home' ) + '</select></div>' +
						f( 'Address Line 1', 'address_1', a.address_1, 'full' ) +
						f( 'Address Line 2', 'address_2', a.address_2, 'full' ) +
						f( 'City', 'city', a.city, 'third' ) +
						f( 'State', 'state', a.state || 'TN', 'third' ) +
						f( 'Postcode', 'postcode', a.postcode, 'third' ) +
					'</div>' +
					'<input type="hidden" class="aca-country" value="' + esc( a.country || 'IN' ) + '" />' +
					'<input type="hidden" class="aca-lat" value="' + esc( a.latitude ) + '" />' +
					'<input type="hidden" class="aca-lng" value="' + esc( a.longitude ) + '" />' +
					'<div class="aca-actions">' +
						'<span class="aca-primary-wrap">' + primaryHtml( isPrimary ) + '</span>' +
						'<button type="button" class="button aca-btn aca-btn--save">Save</button>' +
						'<button type="button" class="button aca-btn aca-btn--delete">Delete</button>' +
						'<span class="aca-card-msg"></span>' +
					'</div>' +
				'</div>';
			}

			function f( lbl, cls, val, size ) {
				return '<div class="aca-f aca-f--' + size + '"><label>' + lbl + '</label>' +
					'<input type="text" class="aca-' + cls + '" value="' + esc( val ) + '" /></div>';
			}

			function primaryHtml( isPrimary ) {
				return isPrimary
					? '<span class="aca-primary-flag">&#10003; Primary address</span>'
					: '<label class="aca-primary-set"><input type="radio" class="aca-primary-radio" name="aca_primary" /> Set as primary</label>';
			}

			function collect( $c ) {
				return {
					address_id:    parseInt( $c.data( 'id' ) ) || 0,
					name:          $c.find( '.aca-name' ).val(),
					mobile_number: $c.find( '.aca-mobile_number' ).val(),
					label:         $c.find( '.aca-label' ).val(),
					address_1:     $.trim( $c.find( '.aca-address_1' ).val() ),
					address_2:     $c.find( '.aca-address_2' ).val(),
					city:          $c.find( '.aca-city' ).val(),
					state:         $c.find( '.aca-state' ).val(),
					postcode:      $c.find( '.aca-postcode' ).val(),
					country:       $c.find( '.aca-country' ).val(),
					latitude:      $c.find( '.aca-lat' ).val(),
					longitude:     $c.find( '.aca-lng' ).val(),
					is_primary:    $c.find( '.aca-primary-radio' ).is( ':checked' ) ? 1 : 0
				};
			}

			$( document ).on( 'click', '#aca-add', function () {
				$( '#aca-list' ).append( cardHtml( {} ) );
			} );

			$( document ).on( 'click', '.aca-btn--save', function () {
				var $c   = $( this ).closest( '.aca-card' );
				var $msg = $c.find( '.aca-card-msg' );
				var data = collect( $c );
				if ( ! data.address_1 ) { $msg.text( 'Address Line 1 is required.' ).css( 'color', '#dc2626' ); return; }

				$( this ).prop( 'disabled', true );
				$msg.text( 'Saving…' ).css( 'color', '#6b7280' );

				$.post( ajaxUrl, $.extend( { action: 'wcfmu_aca_my_save', nonce: nonce }, data ), function ( resp ) {
					$c.find( '.aca-btn--save' ).prop( 'disabled', false );
					if ( resp && resp.success ) {
						$msg.text( 'Saved.' ).css( 'color', '#16a34a' );
						if ( resp.data && resp.data.reload ) { window.location.reload(); }
					} else {
						$msg.text( ( resp && resp.data ) ? resp.data : 'Error saving.' ).css( 'color', '#dc2626' );
					}
				} ).fail( function () {
					$c.find( '.aca-btn--save' ).prop( 'disabled', false );
					$msg.text( 'Error saving.' ).css( 'color', '#dc2626' );
				} );
			} );

			// Instantly switch primary for an already-saved address.
			$( document ).on( 'change', '.aca-primary-radio', function () {
				var $c = $( this ).closest( '.aca-card' );
				var id = parseInt( $c.data( 'id' ) ) || 0;
				if ( 0 === id ) { return; } // new card — set on Save.
				var $msg = $c.find( '.aca-card-msg' );
				$msg.text( 'Updating…' ).css( 'color', '#6b7280' );
				$.post( ajaxUrl, { action: 'wcfmu_aca_my_primary', nonce: nonce, address_id: id }, function ( resp ) {
					if ( resp && resp.success ) { window.location.reload(); }
					else { $msg.text( ( resp && resp.data ) ? resp.data : 'Error.' ).css( 'color', '#dc2626' ); }
				} );
			} );

			$( document ).on( 'click', '.aca-btn--delete', function () {
				var $c = $( this ).closest( '.aca-card' );
				var id = parseInt( $c.data( 'id' ) ) || 0;
				if ( 0 === id ) { $c.remove(); return; }
				if ( ! window.confirm( 'Delete this address?' ) ) { return; }

				var $msg = $c.find( '.aca-card-msg' );
				$msg.text( 'Deleting…' ).css( 'color', '#6b7280' );
				$.post( ajaxUrl, { action: 'wcfmu_aca_my_delete', nonce: nonce, address_id: id }, function ( resp ) {
					if ( resp && resp.success ) {
						window.location.reload();
					} else {
						$msg.text( ( resp && resp.data ) ? resp.data : 'Error deleting.' ).css( 'color', '#dc2626' );
					}
				} );
			} );
		} )( jQuery );
		</script>
		<?php
	}

	/**
	 * Render one saved address card (server-rendered; JS clones for new ones).
	 *
	 * @param array $addr   Address row.
	 * @param array $labels Available labels.
	 * @return void
	 */
	private function render_myaccount_card( array $addr, array $labels ): void {
		$is_primary = (int) $addr['is_primary'] === 1;
		?>
		<div class="aca-card <?php echo $is_primary ? 'is-primary' : ''; ?>" data-id="<?php echo esc_attr( $addr['id'] ); ?>">
			<div class="aca-grid">
				<div class="aca-f aca-f--half"><label><?php esc_html_e( 'Name', 'wc-frontend-manager-ultimate' ); ?></label>
					<input type="text" class="aca-name" value="<?php echo esc_attr( $addr['name'] ); ?>" /></div>
				<div class="aca-f aca-f--half"><label><?php esc_html_e( 'Mobile Number', 'wc-frontend-manager-ultimate' ); ?></label>
					<input type="text" class="aca-mobile_number" value="<?php echo esc_attr( $addr['mobile_number'] ); ?>" /></div>
				<div class="aca-f aca-f--third"><label><?php esc_html_e( 'Label', 'wc-frontend-manager-ultimate' ); ?></label>
					<select class="aca-label">
						<?php foreach ( $labels as $l ) : ?>
							<option value="<?php echo esc_attr( $l ); ?>" <?php selected( $addr['label'], $l ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
						<?php if ( $addr['label'] && ! in_array( $addr['label'], $labels, true ) ) : ?>
							<option value="<?php echo esc_attr( $addr['label'] ); ?>" selected><?php echo esc_html( $addr['label'] ); ?></option>
						<?php endif; ?>
					</select>
				</div>
				<div class="aca-f aca-f--full"><label><?php esc_html_e( 'Address Line 1', 'wc-frontend-manager-ultimate' ); ?></label>
					<input type="text" class="aca-address_1" value="<?php echo esc_attr( $addr['address_1'] ); ?>" /></div>
				<div class="aca-f aca-f--full"><label><?php esc_html_e( 'Address Line 2', 'wc-frontend-manager-ultimate' ); ?></label>
					<input type="text" class="aca-address_2" value="<?php echo esc_attr( $addr['address_2'] ); ?>" /></div>
				<div class="aca-f aca-f--third"><label><?php esc_html_e( 'City', 'wc-frontend-manager-ultimate' ); ?></label>
					<input type="text" class="aca-city" value="<?php echo esc_attr( $addr['city'] ); ?>" /></div>
				<div class="aca-f aca-f--third"><label><?php esc_html_e( 'State', 'wc-frontend-manager-ultimate' ); ?></label>
					<input type="text" class="aca-state" value="<?php echo esc_attr( $addr['state'] ?: 'TN' ); ?>" /></div>
				<div class="aca-f aca-f--third"><label><?php esc_html_e( 'Postcode', 'wc-frontend-manager-ultimate' ); ?></label>
					<input type="text" class="aca-postcode" value="<?php echo esc_attr( $addr['postcode'] ); ?>" /></div>
			</div>
			<input type="hidden" class="aca-country" value="<?php echo esc_attr( $addr['country'] ?: 'IN' ); ?>" />
			<input type="hidden" class="aca-lat" value="<?php echo esc_attr( $addr['latitude'] ); ?>" />
			<input type="hidden" class="aca-lng" value="<?php echo esc_attr( $addr['longitude'] ); ?>" />
			<div class="aca-actions">
				<span class="aca-primary-wrap">
					<?php if ( $is_primary ) : ?>
						<span class="aca-primary-flag">&#10003; <?php esc_html_e( 'Primary address', 'wc-frontend-manager-ultimate' ); ?></span>
					<?php else : ?>
						<label class="aca-primary-set"><input type="radio" class="aca-primary-radio" name="aca_primary" /> <?php esc_html_e( 'Set as primary', 'wc-frontend-manager-ultimate' ); ?></label>
					<?php endif; ?>
				</span>
				<button type="button" class="button aca-btn aca-btn--save"><?php esc_html_e( 'Save', 'wc-frontend-manager-ultimate' ); ?></button>
				<button type="button" class="button aca-btn aca-btn--delete"><?php esc_html_e( 'Delete', 'wc-frontend-manager-ultimate' ); ?></button>
				<span class="aca-card-msg"></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Shared guard for the customer-facing AJAX: verify nonce + login. Returns
	 * the current user id (the only customer a shopper may ever act on).
	 *
	 * @return int
	 */
	private function my_guard(): int {
		if ( ! check_ajax_referer( self::MY_NONCE, 'nonce', false ) ) {
			wp_send_json_error( 'Invalid session. Please refresh the page.' );
		}
		$customer_id = get_current_user_id();
		if ( ! $customer_id ) {
			wp_send_json_error( 'Please sign in and try again.' );
		}
		return $customer_id;
	}

	/**
	 * AJAX (customer): create/update one of the current user's addresses.
	 *
	 * @return void
	 */
	public function ajax_my_save(): void {
		$customer_id = $this->my_guard();

		global $wpdb;
		$table      = $wpdb->prefix . self::TABLE;
		$address_id = absint( $_POST['address_id'] ?? 0 );
		$data       = $this->extract_fields( $_POST, $customer_id );

		if ( '' === $data['address_1'] ) {
			wp_send_json_error( 'Address Line 1 is required.' );
		}

		if ( $address_id ) {
			// Only touch a row that actually belongs to this customer.
			$owned = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE id = %d AND customer_id = %d", $address_id, $customer_id
			) );
			if ( ! $owned ) {
				wp_send_json_error( 'Address not found.' );
			}
			$wpdb->update( $table, $data, [ 'id' => $address_id, 'customer_id' => $customer_id ] );
		} else {
			$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE customer_id = %d", $customer_id ) );
			if ( 0 === $count ) {
				$data['is_primary'] = 1;
			}
			$wpdb->insert( $table, $data );
			$address_id = (int) $wpdb->insert_id;
		}

		if ( $data['is_primary'] ) {
			$this->set_primary( $address_id, $customer_id );
		} else {
			$this->ensure_one_primary( $customer_id );
		}

		wp_send_json_success( [ 'id' => $address_id, 'reload' => true ] );
	}

	/**
	 * AJAX (customer): delete one of the current user's addresses.
	 *
	 * @return void
	 */
	public function ajax_my_delete(): void {
		$customer_id = $this->my_guard();
		$address_id  = absint( $_POST['address_id'] ?? 0 );
		if ( ! $address_id ) {
			wp_send_json_error( 'Invalid address.' );
		}

		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE customer_id = %d", $customer_id ) );
		if ( $count <= 1 ) {
			wp_send_json_error( 'At least one address must remain.' );
		}

		$was_primary = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT is_primary FROM {$table} WHERE id = %d AND customer_id = %d", $address_id, $customer_id
		) );
		$wpdb->delete( $table, [ 'id' => $address_id, 'customer_id' => $customer_id ] );

		if ( $was_primary ) {
			$this->ensure_one_primary( $customer_id );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX (customer): set one of the current user's addresses as primary.
	 *
	 * @return void
	 */
	public function ajax_my_primary(): void {
		$customer_id = $this->my_guard();
		$address_id  = absint( $_POST['address_id'] ?? 0 );
		if ( ! $address_id ) {
			wp_send_json_error( 'Invalid address.' );
		}

		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		$owned = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE id = %d AND customer_id = %d", $address_id, $customer_id
		) );
		if ( ! $owned ) {
			wp_send_json_error( 'Address not found.' );
		}

		$this->set_primary( $address_id, $customer_id );
		wp_send_json_success();
	}

	// ── REST API ──────────────────────────────────────────────────────────────

	/**
	 * Keep PHP notices out of our JSON responses.
	 *
	 * Old WCFM code emits deprecation notices on modern PHP; with display_errors
	 * on, those get printed into the response body and break JSON parsing — the
	 * "response is empty / won't parse" symptom in an API client. Scoped strictly
	 * to our namespace so global error visibility is unchanged.
	 *
	 * @param mixed           $result  Dispatch short-circuit.
	 * @param WP_REST_Server  $server  Server instance.
	 * @param WP_REST_Request $request Current request.
	 * @return mixed
	 */
	public function quiet_errors( $result, $server, $request ) {
		if ( preg_match( '#^/' . self::REST_NS . '(/|$)#', (string) $request->get_route() ) ) {
			// phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed
			@ini_set( 'display_errors', '0' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		return $result;
	}

	public function register_rest_routes(): void {
		$ns = self::REST_NS;

		add_filter( 'rest_pre_dispatch', [ $this, 'quiet_errors' ], 10, 3 );
		add_filter( 'rest_endpoints', [ $this, 'alias_base_route' ] );

		// Base collection: /wp-json/addresses/  — customer_id (and addr_id, where
		// relevant) travel as query parameters, e.g. ?customer_id=7&addr_id=3.
		register_rest_route( $ns, '/', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rest_list' ],
				'permission_callback' => [ $this, 'rest_permission' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_create' ],
				'permission_callback' => [ $this, 'rest_permission' ],
			],
			[
				'methods'             => 'PUT, PATCH',
				'callback'            => [ $this, 'rest_update' ],
				'permission_callback' => [ $this, 'rest_permission' ],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'rest_delete' ],
				'permission_callback' => [ $this, 'rest_permission' ],
			],
		] );

		// /wp-json/addresses/primary?customer_id=7&addr_id=3
		register_rest_route( $ns, '/primary', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'rest_set_primary' ],
			'permission_callback' => [ $this, 'rest_permission' ],
		] );
	}

	/**
	 * Serve the CRUD handlers at the bare namespace root.
	 *
	 * register_rest_route( ns, '/' ) keys the route as "/addresses/" (trailing
	 * slash), but WordPress untrailingslashit's the request path (rest-api.php),
	 * so /wp-json/addresses/ is served as "/addresses" — a key owned by the
	 * auto-generated namespace index, which just lists routes. Copying our
	 * handlers onto that key makes the base URL hit the CRUD instead of the
	 * index, and it works with or without the trailing slash.
	 *
	 * @param array $endpoints All registered REST endpoints.
	 * @return array
	 */
	public function alias_base_route( $endpoints ) {
		$slashed = '/' . self::REST_NS . '/';
		$bare    = '/' . self::REST_NS;
		if ( isset( $endpoints[ $slashed ] ) ) {
			$endpoints[ $bare ] = $endpoints[ $slashed ];
		}
		return $endpoints;
	}

	/**
	 * Read and validate the customer_id query parameter.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return int|WP_Error
	 */
	private function req_customer_id( WP_REST_Request $req ) {
		$id = (int) $req->get_param( 'customer_id' );
		if ( $id <= 0 ) {
			return new WP_Error( 'aaraakart_missing_customer', __( 'customer_id query parameter is required.', 'wc-frontend-manager-ultimate' ), [ 'status' => 400 ] );
		}
		return $id;
	}

	/**
	 * Read and validate the addr_id query parameter.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return int|WP_Error
	 */
	private function req_addr_id( WP_REST_Request $req ) {
		$id = (int) $req->get_param( 'addr_id' );
		if ( $id <= 0 ) {
			return new WP_Error( 'aaraakart_missing_addr', __( 'addr_id query parameter is required.', 'wc-frontend-manager-ultimate' ), [ 'status' => 400 ] );
		}
		return $id;
	}

	/**
	 * Authorise the request with a WooCommerce REST API consumer key/secret.
	 *
	 * The addresses API is consumer-key based, not JWT: the mobile app / server
	 * presents a WooCommerce API key (ck_… / cs_…) and this verifies it against
	 * the woocommerce_api_keys table exactly the way WooCommerce's own REST auth
	 * does — SHA-256 HMAC on the key, constant-time compare on the secret, and a
	 * read vs read/write permission check against the HTTP method.
	 *
	 * Keys may be sent as HTTP Basic auth (username = ck_…, password = cs_…) or as
	 * ?consumer_key=…&consumer_secret=… query parameters, matching WooCommerce.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return bool|WP_Error
	 */
	public function rest_permission( WP_REST_Request $request ) {
		list( $consumer_key, $consumer_secret ) = $this->get_consumer_credentials();

		if ( '' === $consumer_key || '' === $consumer_secret ) {
			return new WP_Error(
				'aaraakart_missing_key',
				__( 'Consumer key and secret are required.', 'wc-frontend-manager-ultimate' ),
				[ 'status' => 401 ]
			);
		}

		$user = $this->get_api_key_user( $consumer_key );
		if ( ! $user || ! hash_equals( (string) $user->consumer_secret, $consumer_secret ) ) {
			return new WP_Error(
				'aaraakart_invalid_key',
				__( 'Invalid consumer key or secret.', 'wc-frontend-manager-ultimate' ),
				[ 'status' => 401 ]
			);
		}

		if ( ! $this->key_permits_method( (string) $user->permissions, $request->get_method() ) ) {
			return new WP_Error(
				'aaraakart_key_forbidden',
				__( 'This API key does not have permission for this operation.', 'wc-frontend-manager-ultimate' ),
				[ 'status' => 401 ]
			);
		}

		// Act as the key's owner so any downstream capability checks resolve.
		wp_set_current_user( (int) $user->user_id );

		return true;
	}

	/**
	 * Pull consumer key/secret from Basic auth or query parameters.
	 *
	 * @return array{0:string,1:string}
	 */
	private function get_consumer_credentials(): array {
		$key    = '';
		$secret = '';

		if ( ! empty( $_SERVER['PHP_AUTH_USER'] ) && ! empty( $_SERVER['PHP_AUTH_PW'] ) ) {
			$key    = sanitize_text_field( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) );
			$secret = sanitize_text_field( wp_unslash( $_SERVER['PHP_AUTH_PW'] ) );
		} elseif ( ! empty( $_GET['consumer_key'] ) && ! empty( $_GET['consumer_secret'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$key    = sanitize_text_field( wp_unslash( $_GET['consumer_key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$secret = sanitize_text_field( wp_unslash( $_GET['consumer_secret'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}

		return [ $key, $secret ];
	}

	/**
	 * Look up an API key row by its (hashed) consumer key.
	 *
	 * @param string $consumer_key The raw ck_… key.
	 * @return object|null
	 */
	private function get_api_key_user( string $consumer_key ) {
		global $wpdb;

		$hashed = wc_api_hash( sanitize_text_field( $consumer_key ) );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT key_id, user_id, permissions, consumer_secret FROM {$wpdb->prefix}woocommerce_api_keys WHERE consumer_key = %s",
				$hashed
			)
		); // phpcs:ignore WordPress.DB
	}

	/**
	 * Whether a key's read/write scope covers the request method.
	 *
	 * @param string $permissions read|write|read_write.
	 * @param string $method      HTTP method.
	 * @return bool
	 */
	private function key_permits_method( string $permissions, string $method ): bool {
		$is_read = in_array( strtoupper( $method ), [ 'GET', 'HEAD', 'OPTIONS' ], true );

		if ( $is_read ) {
			return in_array( $permissions, [ 'read', 'read_write' ], true );
		}
		return in_array( $permissions, [ 'write', 'read_write' ], true );
	}

	public function rest_list( WP_REST_Request $req ) {
		$customer_id = $this->req_customer_id( $req );
		if ( is_wp_error( $customer_id ) ) return $customer_id;

		$rows = array_map( [ $this, 'format_row' ], $this->get_addresses( $customer_id ) );
		return new WP_REST_Response( array_values( $rows ), 200 );
	}

	public function rest_create( WP_REST_Request $req ) {
		global $wpdb;
		$customer_id = $this->req_customer_id( $req );
		if ( is_wp_error( $customer_id ) ) return $customer_id;

		$table       = $wpdb->prefix . self::TABLE;
		$raw         = array_merge( (array) $req->get_json_params(), (array) $req->get_body_params() );
		$data        = $this->extract_fields( $raw, $customer_id );

		// The customer must exist, or we would attach an address to a phantom user.
		if ( ! get_userdata( $customer_id ) ) {
			return new WP_Error( 'aaraakart_no_customer', __( 'Customer not found.', 'wc-frontend-manager-ultimate' ), [ 'status' => 404 ] );
		}
		if ( '' === $data['address_1'] ) {
			return new WP_Error( 'aaraakart_missing_field', __( 'address_1 is required.', 'wc-frontend-manager-ultimate' ), [ 'status' => 400 ] );
		}

		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE customer_id = %d", $customer_id ) );
		if ( 0 === $count ) $data['is_primary'] = 1;

		// $wpdb->insert returns false on failure — surface why instead of returning
		// a 201 with an empty body (the usual "response is empty" symptom: the
		// insert failed, so the read-back by insert_id found nothing).
		$ok = $wpdb->insert( $table, $data, $this->column_formats( $data ) );
		if ( false === $ok ) {
			return new WP_Error(
				'aaraakart_insert_failed',
				__( 'Could not save the address.', 'wc-frontend-manager-ultimate' ),
				[ 'status' => 500, 'db_error' => $wpdb->last_error ]
			);
		}

		$id = (int) $wpdb->insert_id;
		if ( $data['is_primary'] ) $this->set_primary( $id, $customer_id );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		return new WP_REST_Response( $this->format_row( $row ), 201 );
	}

	/**
	 * $wpdb format specifiers matching a data row, so NULL lat/lng are written as
	 * SQL NULL rather than coerced to 0, and numeric columns aren't quoted oddly.
	 *
	 * @param array $data Row data.
	 * @return array
	 */
	private function column_formats( array $data ): array {
		$formats = [];
		foreach ( $data as $key => $value ) {
			if ( in_array( $key, [ 'customer_id', 'is_primary' ], true ) ) {
				$formats[] = '%d';
			} elseif ( in_array( $key, [ 'latitude', 'longitude' ], true ) ) {
				$formats[] = '%f';
			} else {
				$formats[] = '%s';
			}
		}
		return $formats;
	}

	/**
	 * Normalise a DB row for output: ints as ints, coords as strings or null.
	 *
	 * @param array|null $row Raw row.
	 * @return array|null
	 */
	private function format_row( $row ) {
		if ( ! is_array( $row ) ) {
			return $row;
		}
		$row['id']          = (int) $row['id'];
		$row['customer_id'] = (int) $row['customer_id'];
		$row['is_primary']  = (int) $row['is_primary'];
		return $row;
	}

	public function rest_update( WP_REST_Request $req ) {
		global $wpdb;
		$customer_id = $this->req_customer_id( $req );
		if ( is_wp_error( $customer_id ) ) return $customer_id;
		$addr_id = $this->req_addr_id( $req );
		if ( is_wp_error( $addr_id ) ) return $addr_id;

		$table = $wpdb->prefix . self::TABLE;
		$raw   = array_merge( (array) $req->get_json_params(), (array) $req->get_body_params() );
		$data  = $this->extract_provided_fields( $raw );

		if ( ! empty( $data ) ) {
			$wpdb->update( $table, $data, [ 'id' => $addr_id, 'customer_id' => $customer_id ], $this->column_formats( $data ), [ '%d', '%d' ] );
		}
		if ( ! empty( $data['is_primary'] ) ) $this->set_primary( $addr_id, $customer_id );

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND customer_id = %d", $addr_id, $customer_id ),
			ARRAY_A
		);
		return $row
			? new WP_REST_Response( $this->format_row( $row ), 200 )
			: new WP_REST_Response( [ 'error' => 'Not found' ], 404 );
	}

	public function rest_delete( WP_REST_Request $req ) {
		global $wpdb;
		$customer_id = $this->req_customer_id( $req );
		if ( is_wp_error( $customer_id ) ) return $customer_id;
		$addr_id = $this->req_addr_id( $req );
		if ( is_wp_error( $addr_id ) ) return $addr_id;

		$table = $wpdb->prefix . self::TABLE;

		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE customer_id = %d", $customer_id ) );
		if ( $count <= 1 ) {
			return new WP_REST_Response( [ 'error' => 'At least one address must remain.' ], 400 );
		}

		$was_primary = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT is_primary FROM {$table} WHERE id = %d AND customer_id = %d", $addr_id, $customer_id
		) );
		$wpdb->delete( $table, [ 'id' => $addr_id, 'customer_id' => $customer_id ] );

		if ( $was_primary ) $this->ensure_one_primary( $customer_id );

		return new WP_REST_Response( null, 204 );
	}

	public function rest_set_primary( WP_REST_Request $req ) {
		global $wpdb;
		$customer_id = $this->req_customer_id( $req );
		if ( is_wp_error( $customer_id ) ) return $customer_id;
		$addr_id = $this->req_addr_id( $req );
		if ( is_wp_error( $addr_id ) ) return $addr_id;

		$table = $wpdb->prefix . self::TABLE;

		$this->set_primary( $addr_id, $customer_id );

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND customer_id = %d", $addr_id, $customer_id ),
			ARRAY_A
		);
		return $row
			? new WP_REST_Response( $this->format_row( $row ), 200 )
			: new WP_REST_Response( [ 'error' => 'Not found' ], 404 );
	}
}
