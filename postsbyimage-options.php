<?php
/**
 * Settings → PostsByImage screen.
 *
 * @package PostsByImage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Print the settings form and the cache rebuild control.
 */
function ds_pbi_render_options_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$user_id = get_current_user_id();
	$summary = get_transient( 'ds_pbi_rebuild_summary_' . $user_id );
	if ( is_string( $summary ) && '' !== $summary ) {
		delete_transient( 'ds_pbi_rebuild_summary_' . $user_id );
		echo '<div class="notice notice-success"><p>' . esc_html( $summary ) . '</p></div>';
	}

	echo '<div class="wrap">';
	echo '<h1>PostsByImage</h1>';
	settings_errors();

	echo '<form action="options.php" method="post">';
	settings_fields( 'ds_pbi_settings' );
	do_settings_sections( 'postsbyimage' );
	submit_button( 'Save Changes' );
	echo '</form>';

	echo '<h2>Cache</h2>';
	echo '<p>Rebuild writes missing thumbnails for published posts. Files already in the cache directory stay as they are unless you overwrite them.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'ds_pbi_rebuild_cache' );
	echo '<p><label><input type="checkbox" name="ds_pbi_overwrite" value="1" /> Overwrite existing thumbnails</label></p>';
	submit_button( 'Rebuild cache', 'secondary', 'ds_pbi_rebuild_cache', false );
	echo '</form>';
	echo '</div>';
}
