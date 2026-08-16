<?php
/**
 * Deliveries PDF view.
 *
 * Lists the delivery run on the left and shows the printable sheet in an iframe
 * on the right. Selecting an order swaps the iframe to that order alone; the
 * default frame holds every order in the current filter.
 *
 * @package wcfmd/views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $WCFMd;

$pdf = isset( $WCFMd->deliveries_pdf ) ? $WCFMd->deliveries_pdf : new WCFMd_Deliveries_PDF();

// phpcs:disable WordPress.Security.NonceVerification -- read-only filters.
$status       = isset( $_GET['delivery_status'] ) ? sanitize_key( wp_unslash( $_GET['delivery_status'] ) ) : 'pending';
$status       = in_array( $status, array( 'pending', 'delivered' ), true ) ? $status : '';
$requested    = isset( $_GET['delivery_boy'] ) ? absint( $_GET['delivery_boy'] ) : 0;
// phpcs:enable

$delivery_boy = $pdf->viewable_delivery_boy( $requested );

if ( false === $delivery_boy ) {
	echo '<div class="wcfm-message"><p>' . esc_html__( 'You are not allowed to view deliveries.', 'wc-frontend-manager-delivery' ) . '</p></div>';
	return;
}

$rows   = $pdf->get_delivery_orders( $delivery_boy, $status );
$orders = array();
foreach ( $rows as $row ) {
	$summary = $pdf->order_summary( $row );
	if ( $summary ) {
		$orders[] = $summary;
	}
}

$frame_args = array( 'status' => $status );
if ( $delivery_boy && ! ( function_exists( 'wcfm_is_delivery_boy' ) && wcfm_is_delivery_boy() ) ) {
	$frame_args['delivery_boy'] = $delivery_boy;
}

$all_url      = WCFMd_Deliveries_PDF::url( $frame_args );
$download_url = WCFMd_Deliveries_PDF::url( array_merge( $frame_args, array( 'download' => 1 ) ) );
$base_url     = function_exists( 'get_wcfm_deliveries_pdf_url' ) ? get_wcfm_deliveries_pdf_url() : '';

$tabs = array(
	'pending'   => __( 'Pending', 'wc-frontend-manager-delivery' ),
	'delivered' => __( 'Delivered', 'wc-frontend-manager-delivery' ),
	''          => __( 'All', 'wc-frontend-manager-delivery' ),
);
?>

<div class="collapse wcfm-collapse" id="wcfm_deliveries_pdf_listing">
	<div class="wcfm-page-headig">
		<span class="wcfmfa fa-file-pdf text_dropbox"></span>
		<span class="wcfm-page-heading-text"><?php esc_html_e( 'Deliveries PDF', 'wc-frontend-manager-delivery' ); ?></span>
	</div>

	<div class="wcfm-collapse-content">
		<div class="wcfmd-pdf">

			<div class="wcfmd-pdf__toolbar">
				<div class="wcfmd-pdf__tabs">
					<?php foreach ( $tabs as $key => $label ) : ?>
						<a class="wcfmd-pdf__tab <?php echo ( $key === $status ) ? 'is-active' : ''; ?>"
							href="<?php echo esc_url( add_query_arg( 'delivery_status', $key ? $key : 'all', $base_url ) ); ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					<?php endforeach; ?>
				</div>
				<div class="wcfmd-pdf__actions">
					<a class="wcfmd-pdf__btn" href="<?php echo esc_url( $all_url ); ?>" target="_blank" rel="noopener">
						<?php esc_html_e( 'Open in new tab', 'wc-frontend-manager-delivery' ); ?>
					</a>
					<a class="wcfmd-pdf__btn wcfmd-pdf__btn--primary" href="<?php echo esc_url( $download_url ); ?>" style="display:none;" >
						<?php esc_html_e( 'Download PDF', 'wc-frontend-manager-delivery' ); ?>
					</a>

				</div>
			</div>

			<div class="wcfmd-pdf__split">

				<div class="wcfmd-pdf__list">
					<div class="wcfmd-pdf__listhead">
						<?php
						printf(
							/* translators: %d: number of orders */
							esc_html( _n( '%d order', '%d orders', count( $orders ), 'wc-frontend-manager-delivery' ) ),
							count( $orders )
						);
						?>
					</div>

					<?php if ( empty( $orders ) ) : ?>
						<p class="wcfmd-pdf__empty"><?php esc_html_e( 'No deliveries in this view.', 'wc-frontend-manager-delivery' ); ?></p>
					<?php else : ?>
						<button type="button" class="wcfmd-pdf__item is-active" data-src="<?php echo esc_url( $all_url ); ?>">
							<strong><?php esc_html_e( 'All orders', 'wc-frontend-manager-delivery' ); ?></strong>
							<span><?php esc_html_e( 'Full run sheet', 'wc-frontend-manager-delivery' ); ?></span>
						</button>

						<?php foreach ( $orders as $order ) : ?>
							<?php $one = WCFMd_Deliveries_PDF::url( array_merge( $frame_args, array( 'order_id' => $order['order_id'] ) ) ); ?>
							<button type="button" class="wcfmd-pdf__item" data-src="<?php echo esc_url( $one ); ?>">
								<strong>#<?php echo esc_html( $order['number'] ); ?> — <?php echo esc_html( $order['customer'] ); ?></strong>
								<span><?php echo esc_html( $order['address'] ); ?></span>
								<em class="wcfmd-pdf__status wcfmd-pdf__status--<?php echo esc_attr( $order['status'] ); ?>">
									<?php echo esc_html( ucfirst( $order['status'] ) ); ?>
								</em>
							</button>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<div class="wcfmd-pdf__viewer">
					<iframe id="wcfmd_pdf_frame" src="<?php echo esc_url( $all_url ); ?>"
						title="<?php esc_attr_e( 'Delivery run sheet', 'wc-frontend-manager-delivery' ); ?>"></iframe>
				</div>

			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
	jQuery( function ( $ ) {
		$( document ).on( 'click', '.wcfmd-pdf__item', function () {
			$( '.wcfmd-pdf__item' ).removeClass( 'is-active' );
			$( this ).addClass( 'is-active' );
			$( '#wcfmd_pdf_frame' ).attr( 'src', $( this ).data( 'src' ) );
		} );
	} );
</script>
