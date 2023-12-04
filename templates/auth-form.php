<?php // phpcs:ignore Squiz.Commenting.FileComment.Missing

$login_errors = new \WP_Error();

login_header(
	/* translators: client */
	sprintf( __( 'Authorize %s', 'scrobbble' ), $api_key ),
	'',
	$login_errors
);
?>
<form method="post" action="<?php echo esc_url( $url ); ?>">
	<div class="client-info">
		<?php /* translators: client */ ?>
		<strong><?php printf( esc_html__( '%s wants to scrobble to your site.', 'scrobbble' ), esc_html( $api_key ) ); ?></strong>
	</div>

	<div class="user-info">
		<?php
		echo get_avatar( $current_user->ID, '48' );
		printf(
			/* translators: 1. User Display Name 2. User Nice Name */
			esc_html__( 'The app will use credentials of %1$s (%2$s). You can revoke access at any time.', 'scrobbble' ),
			'<strong>' . esc_html( $current_user->display_name ) . '</strong>',
			esc_html( $current_user->user_nicename )
		);
		?>
	</div>

	<p class="submit">
		<input type="hidden" name="api_key" value="<?php echo esc_attr( $api_key ); ?>" />
		<input type="hidden" name="cb" value="<?php echo esc_attr( $cb ); ?>" />

		<button name="wp-submit" value="authorize" class="button button-primary button-large"><?php esc_html_e( 'Approve', 'scrobbble' ); ?></button>
		<a name="wp-submit" value="cancel" class="button button-large" href="<?php echo esc_url( home_url() ); ?>"><?php esc_html_e( 'Cancel', 'scrobbble' ); ?></a>
	</p>
</form>
<?php

login_footer();
