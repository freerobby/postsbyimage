<?php
/**
 * Plugin Name: PostsByImage
 * Plugin URI: http://www.digitalsublimity.com/products/postsbyimage
 * Description: Builds a grid of post thumbnails that link back to their posts. Place [postsbyimage=] in a post or page. Optional semicolon-separated category names or term IDs select a subset.
 * Author: Digital Sublimity
 * Version: 1.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author URI: http://www.digitalsublimity.com
 *
 * @package PostsByImage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'POSTSBYIMAGE_GENERATETAG_START', '[postsbyimage=' );
define( 'POSTSBYIMAGE_GENERATETAG_STOP', ']' );
define( 'POSTSBYIMAGE_ARGSEPARATOR', ';' );

register_activation_hook( __FILE__, 'ds_pbi_install' );
add_action( 'admin_menu', 'ds_pbi_addpages' );
add_action( 'admin_init', 'ds_pbi_register_settings' );
add_action( 'admin_init', 'ds_pbi_handle_cache_rebuild' );
add_action( 'delete_post', 'ds_pbi_deleteimageofpost' );
add_filter( 'the_content', 'ds_pbi_parsecontent' );
add_action( 'save_post', 'ds_pbi_postsaved' );

/**
 * Historical defaults. add_option() does not overwrite values already stored.
 *
 * @return array<string, string>
 */
function ds_pbi_default_options() {
	return array(
		'ds_pbi_cachepath'           => '???',
		'ds_pbi_cacheurl'            => 'http://???',
		'ds_pbi_defaultcols'         => '2',
		'ds_pbi_thumbnailmaxwidth'   => '200',
		'ds_pbi_thumbnailmaxheight'  => '200',
	);
}

/**
 * Add options on first activation. Never deletes or resets them later.
 */
function ds_pbi_install() {
	foreach ( ds_pbi_default_options() as $key => $value ) {
		add_option( $key, $value );
	}
}

/**
 * Settings → PostsByImage.
 */
function ds_pbi_addpages() {
	add_options_page(
		'PostsByImage',
		'PostsByImage',
		'manage_options',
		'postsbyimage',
		'ds_pbi_options_page'
	);
}

/**
 * Register the five original option keys with the Settings API.
 */
function ds_pbi_register_settings() {
	$defaults = ds_pbi_default_options();

	$text_keys = array(
		'ds_pbi_cachepath',
		'ds_pbi_cacheurl',
	);
	foreach ( $text_keys as $key ) {
		register_setting(
			'ds_pbi_settings',
			$key,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'ds_pbi_sanitize_text',
				'default'           => $defaults[ $key ],
			)
		);
	}

	$number_keys = array(
		'ds_pbi_defaultcols',
		'ds_pbi_thumbnailmaxwidth',
		'ds_pbi_thumbnailmaxheight',
	);
	foreach ( $number_keys as $key ) {
		register_setting(
			'ds_pbi_settings',
			$key,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'ds_pbi_sanitize_positive_int',
				'default'           => $defaults[ $key ],
			)
		);
	}

	add_settings_section(
		'ds_pbi_main',
		'Settings',
		'ds_pbi_settings_section_intro',
		'postsbyimage'
	);

	add_settings_field(
		'ds_pbi_cachepath',
		'Cache directory',
		'ds_pbi_field_text',
		'postsbyimage',
		'ds_pbi_main',
		array(
			'key'         => 'ds_pbi_cachepath',
			'description' => 'Absolute filesystem directory. Thumbnails are stored as {directory}/{post ID}.jpg.',
		)
	);
	add_settings_field(
		'ds_pbi_cacheurl',
		'Cache URL',
		'ds_pbi_field_text',
		'postsbyimage',
		'ds_pbi_main',
		array(
			'key'         => 'ds_pbi_cacheurl',
			'description' => 'Public URL of that same directory. The gallery loads images from {URL}/{post ID}.jpg.',
		)
	);
	add_settings_field(
		'ds_pbi_defaultcols',
		'Columns',
		'ds_pbi_field_number',
		'postsbyimage',
		'ds_pbi_main',
		array(
			'key'         => 'ds_pbi_defaultcols',
			'description' => 'Number of columns in the front-end thumbnail grid.',
		)
	);
	add_settings_field(
		'ds_pbi_thumbnailmaxwidth',
		'Thumbnail max width',
		'ds_pbi_field_number',
		'postsbyimage',
		'ds_pbi_main',
		array(
			'key'         => 'ds_pbi_thumbnailmaxwidth',
			'description' => 'New thumbnails are fit inside this width. Existing files are not resized unless you overwrite them.',
		)
	);
	add_settings_field(
		'ds_pbi_thumbnailmaxheight',
		'Thumbnail max height',
		'ds_pbi_field_number',
		'postsbyimage',
		'ds_pbi_main',
		array(
			'key'         => 'ds_pbi_thumbnailmaxheight',
			'description' => 'New thumbnails are fit inside this height. Existing files are not resized unless you overwrite them.',
		)
	);
}

/**
 * Intro copy for the settings section.
 */
function ds_pbi_settings_section_intro() {
	echo '<p>Place <code>[postsbyimage=]</code> in a post or page to show every published post that already has a thumbnail. Filter with semicolon-separated category names or term IDs, for example <code>[postsbyimage=Available]</code>, <code>[postsbyimage=5]</code>, or <code>[postsbyimage=Available;Sold]</code>.</p>';
}

/**
 * Text setting field.
 *
 * @param array<string, string> $args Field args.
 */
function ds_pbi_field_text( $args ) {
	$key   = $args['key'];
	$value = get_option( $key, '' );
	printf(
		'<input type="text" class="regular-text" id="%1$s" name="%1$s" value="%2$s" />',
		esc_attr( $key ),
		esc_attr( is_string( $value ) ? $value : '' )
	);
	if ( ! empty( $args['description'] ) ) {
		printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
	}
}

/**
 * Positive integer setting field.
 *
 * @param array<string, string> $args Field args.
 */
function ds_pbi_field_number( $args ) {
	$key   = $args['key'];
	$value = get_option( $key, '' );
	printf(
		'<input type="number" min="1" step="1" class="small-text" id="%1$s" name="%1$s" value="%2$s" />',
		esc_attr( $key ),
		esc_attr( (string) ds_pbi_positive_int( $value, 1 ) )
	);
	if ( ! empty( $args['description'] ) ) {
		printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
	}
}

/**
 * @param mixed $value Raw option value.
 * @return string
 */
function ds_pbi_sanitize_text( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	return trim( wp_strip_all_tags( $value ) );
}

/**
 * @param mixed $value Raw option value.
 * @return string
 */
function ds_pbi_sanitize_positive_int( $value ) {
	return (string) ds_pbi_positive_int( $value, 1 );
}

/**
 * @param mixed $value Raw value.
 * @param int   $fallback Used when the value is not a positive integer.
 * @return int
 */
function ds_pbi_positive_int( $value, $fallback ) {
	$number = absint( $value );
	if ( $number < 1 ) {
		return (int) $fallback;
	}
	return $number;
}

/**
 * Options screen. Markup lives in postsbyimage-options.php.
 */
function ds_pbi_options_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	require_once plugin_dir_path( __FILE__ ) . 'postsbyimage-options.php';
	ds_pbi_render_options_page();
}

/**
 * Rebuild missing thumbnails. Overwrite is optional and off by default.
 */
function ds_pbi_handle_cache_rebuild() {
	if ( empty( $_POST['ds_pbi_rebuild_cache'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'ds_pbi_rebuild_cache' );

	$overwrite = ! empty( $_POST['ds_pbi_overwrite'] );
	$summary   = ds_pbi_regenerateimagecache( $overwrite );
	set_transient( 'ds_pbi_rebuild_summary_' . get_current_user_id(), $summary, MINUTE_IN_SECONDS );

	wp_safe_redirect( admin_url( 'options-general.php?page=postsbyimage' ) );
	exit;
}

/**
 * Delete {cachepath}/{post_id}.jpg when the post is deleted.
 *
 * @param int $post_id Post ID.
 */
function ds_pbi_deleteimageofpost( $post_id ) {
	$path = ds_pbi_thumbnail_path( $post_id );
	if ( '' !== $path && is_file( $path ) ) {
		unlink( $path );
	}
}

/**
 * Replace PostsByImage tags in post content. This stays a the_content string replace.
 *
 * @param string $content Post content.
 * @return string
 */
function ds_pbi_parsecontent( $content ) {
	if ( ! is_string( $content ) || '' === $content ) {
		return $content;
	}

	$start  = POSTSBYIMAGE_GENERATETAG_START;
	$stop   = POSTSBYIMAGE_GENERATETAG_STOP;
	$offset = 0;

	while ( false !== ( $tag_startpos = strpos( $content, $start, $offset ) ) ) {
		$tag_endpos = strpos( $content, $stop, $tag_startpos + strlen( $start ) );
		if ( false === $tag_endpos ) {
			break;
		}

		$inner = substr(
			$content,
			$tag_startpos + strlen( $start ),
			$tag_endpos - ( $tag_startpos + strlen( $start ) )
		);
		$replacement = ds_pbi_render_tag( $inner );
		$content     = substr( $content, 0, $tag_startpos ) . $replacement . substr( $content, $tag_endpos + strlen( $stop ) );
		$offset      = $tag_startpos + strlen( $replacement );
	}

	return $content;
}

/**
 * Render one tag body (the text between [postsbyimage= and ]).
 *
 * An empty body means every published post. Otherwise each semicolon-separated
 * piece is a category name, slug, or term_id.
 *
 * @param string $data Tag argument string.
 * @return string
 */
function ds_pbi_render_tag( $data ) {
	$args = array_map( 'trim', explode( POSTSBYIMAGE_ARGSEPARATOR, (string) $data ) );
	$args = array_values(
		array_filter(
			$args,
			static function ( $arg ) {
				return '' !== $arg;
			}
		)
	);

	if ( empty( $args ) ) {
		return ds_pbi_generateimagelinkshtml( 0 );
	}

	$html = '';
	foreach ( $args as $arg ) {
		$term_id = ds_pbi_resolve_category( $arg );
		if ( $term_id < 1 ) {
			continue;
		}
		$html .= ds_pbi_generateimagelinkshtml( $term_id );
	}
	return $html;
}

/**
 * Resolve a category name, slug, or term_id. Returns 0 when it does not match a category.
 *
 * Numeric values are term_id values looked up with get_term(). They are not used as term_taxonomy_id.
 *
 * @param string $arg Tag argument.
 * @return int
 */
function ds_pbi_resolve_category( $arg ) {
	$arg = trim( (string) $arg );
	if ( '' === $arg ) {
		return 0;
	}

	if ( ctype_digit( $arg ) ) {
		$term = get_term( (int) $arg, 'category' );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}
		return 0;
	}

	$term = get_term_by( 'name', $arg, 'category' );
	if ( ! $term ) {
		$term = get_term_by( 'slug', sanitize_title( $arg ), 'category' );
	}
	if ( $term && ! is_wp_error( $term ) ) {
		return (int) $term->term_id;
	}
	return 0;
}

/**
 * Published post IDs, optionally limited to one category term_id.
 *
 * @param int $term_id Category term_id, or 0 for every published post.
 * @return int[]
 */
function ds_pbi_get_published_post_ids( $term_id = 0 ) {
	$args = array(
		'post_type'              => 'post',
		'post_status'            => 'publish',
		'posts_per_page'         => -1,
		'fields'                 => 'ids',
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'no_found_rows'          => true,
		'ignore_sticky_posts'    => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	);

	$term_id = (int) $term_id;
	if ( $term_id > 0 ) {
		$args['tax_query'] = array(
			array(
				'taxonomy'         => 'category',
				'field'            => 'term_id',
				'terms'            => array( $term_id ),
				'include_children' => false,
			),
		);
	}

	$ids = get_posts( $args );
	if ( ! is_array( $ids ) ) {
		return array();
	}
	return array_map( 'intval', $ids );
}

/**
 * Linked thumbnail grid for a category, or for every published post when $term_id is 0.
 *
 * @param int|string $term_id Category term_id, or 0 / '*' for all published posts.
 * @return string
 */
function ds_pbi_generateimagelinkshtml( $term_id = 0 ) {
	if ( '*' === $term_id || '' === $term_id || null === $term_id ) {
		$term_id = 0;
	}

	$ids = ds_pbi_get_published_post_ids( (int) $term_id );
	if ( empty( $ids ) ) {
		return '';
	}

	$cols = ds_pbi_positive_int( get_option( 'ds_pbi_defaultcols', 2 ), 2 );
	$items = array();

	foreach ( $ids as $id ) {
		$path = ds_pbi_thumbnail_path( $id );
		if ( '' === $path || ! is_file( $path ) ) {
			continue;
		}
		$url = ds_pbi_thumbnail_url( $id );
		if ( '' === $url ) {
			continue;
		}
		$items[] = sprintf(
			'<a class="ds-pbi-link" href="%1$s"><img class="ds-pbi-thumb" src="%2$s" alt="%3$s" style="max-width:100%%;height:auto;" /></a>',
			esc_url( get_permalink( $id ) ),
			esc_url( $url ),
			esc_attr( get_the_title( $id ) )
		);
	}

	if ( empty( $items ) ) {
		return '';
	}

	return '<div class="ds-pbi-grid" style="display:grid;grid-template-columns:repeat(' . $cols . ',minmax(0,1fr));gap:1em;">' . implode( '', $items ) . '</div>';
}

/**
 * Rebuild thumbnails for published posts.
 *
 * Existing {id}.jpg files are left untouched when $overwrite is false, including when the source image is unchanged.
 *
 * @param bool $overwrite Replace thumbnails that already exist.
 * @return string Short summary for the settings screen.
 */
function ds_pbi_regenerateimagecache( $overwrite = false ) {
	$ids    = ds_pbi_get_published_post_ids( 0 );
	$counts = array(
		'wrote'     => 0,
		'unchanged' => 0,
		'skipped'   => 0,
		'failed'    => 0,
	);

	foreach ( $ids as $id ) {
		$result = ds_pbi_regeneratepostimage( $id, (bool) $overwrite );
		if ( isset( $counts[ $result ] ) ) {
			$counts[ $result ]++;
		}
	}

	return sprintf(
		'Examined %1$d published posts. Wrote %2$d thumbnails, left %3$d unchanged, skipped %4$d with no local image, failed %5$d.',
		count( $ids ),
		$counts['wrote'],
		$counts['unchanged'],
		$counts['skipped'],
		$counts['failed']
	);
}

/**
 * Regenerate one post thumbnail when that post is saved.
 *
 * @param int $post_id Post ID.
 */
function ds_pbi_postsaved( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	$post = get_post( $post_id );
	if ( ! is_object( $post ) || ! isset( $post->post_type ) || 'post' !== $post->post_type ) {
		return;
	}
	if ( isset( $post->post_status ) && 'auto-draft' === $post->post_status ) {
		return;
	}

	ds_pbi_regeneratepostimage( $post_id, true );
}

/**
 * Write {cachepath}/{id}.jpg for one post.
 *
 * When the file already exists and $overwrite is false, it is not read or rewritten.
 *
 * @param int  $post_id Post ID.
 * @param bool $overwrite Replace an existing thumbnail.
 * @return string wrote|unchanged|skipped|failed
 */
function ds_pbi_regeneratepostimage( $post_id, $overwrite = true ) {
	$post_id = (int) $post_id;
	$dest    = ds_pbi_thumbnail_path( $post_id );
	if ( '' === $dest ) {
		return 'failed';
	}

	$exists = is_file( $dest );
	if ( $exists && ! $overwrite ) {
		return 'unchanged';
	}

	$source = ds_pbi_source_image_path( $post_id );
	if ( '' === $source || ! is_readable( $source ) ) {
		// Leave an existing jpeg in place. delete_post is what removes it.
		return 'skipped';
	}

	$dir = dirname( $dest );
	if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
		return 'failed';
	}
	if ( ! function_exists( 'wp_get_image_editor' ) ) {
		return 'failed';
	}

	$editor = wp_get_image_editor( $source );
	if ( is_wp_error( $editor ) ) {
		return 'failed';
	}

	$max_width  = ds_pbi_positive_int( get_option( 'ds_pbi_thumbnailmaxwidth', 200 ), 200 );
	$max_height = ds_pbi_positive_int( get_option( 'ds_pbi_thumbnailmaxheight', 200 ), 200 );
	$resized    = $editor->resize( $max_width, $max_height, false );
	if ( is_wp_error( $resized ) && 'error_getting_dimensions' !== $resized->get_error_code() ) {
		return 'failed';
	}

	$tmp   = $dir . '/' . $post_id . '.new.jpg';
	$saved = $editor->save( $tmp, 'image/jpeg' );
	if ( is_wp_error( $saved ) || ! is_array( $saved ) || empty( $saved['path'] ) || ! is_file( $saved['path'] ) ) {
		if ( is_file( $tmp ) ) {
			unlink( $tmp );
		}
		return 'failed';
	}

	$written = $saved['path'];
	if ( $written !== $dest && ! rename( $written, $dest ) ) {
		if ( is_file( $written ) ) {
			unlink( $written );
		}
		return 'failed';
	}

	return 'wrote';
}

/**
 * Local source image for a post.
 *
 * Featured image first (thumbnail size, then large, then the original file).
 * Otherwise the first <img> in post_content. Off-site images are skipped.
 *
 * @param int $post_id Post ID.
 * @return string Filesystem path, or '' when there is nothing local to thumbnail.
 */
function ds_pbi_source_image_path( $post_id ) {
	$featured = ds_pbi_featured_image_path( $post_id );
	if ( '' !== $featured ) {
		return $featured;
	}

	$post = get_post( $post_id );
	if ( ! is_object( $post ) ) {
		return '';
	}
	return ds_pbi_content_image_path( $post );
}

/**
 * @param int $post_id Post ID.
 * @return string
 */
function ds_pbi_featured_image_path( $post_id ) {
	$attachment_id = (int) get_post_thumbnail_id( $post_id );
	if ( $attachment_id <= 0 ) {
		return '';
	}
	return ds_pbi_local_attachment_path( $attachment_id, array( 'thumbnail', 'large', 'full' ) );
}

/**
 * Resolve a local file for an attachment size, then the original file.
 *
 * @param int      $attachment_id Attachment ID.
 * @param string[] $sizes Size names. "full" means the attached original file.
 * @return string
 */
function ds_pbi_local_attachment_path( $attachment_id, $sizes ) {
	$attachment_id = (int) $attachment_id;
	if ( $attachment_id <= 0 ) {
		return '';
	}

	$original = get_attached_file( $attachment_id );
	$original = is_string( $original ) ? $original : '';
	$meta     = wp_get_attachment_metadata( $attachment_id );
	$dir      = '' !== $original ? dirname( $original ) : '';

	foreach ( $sizes as $size ) {
		if ( 'full' === $size ) {
			if ( '' !== $original && is_readable( $original ) ) {
				return $original;
			}
			continue;
		}
		if ( '' === $dir || ! is_array( $meta ) || empty( $meta['sizes'][ $size ]['file'] ) || ! is_string( $meta['sizes'][ $size ]['file'] ) ) {
			continue;
		}
		$candidate = $dir . '/' . $meta['sizes'][ $size ]['file'];
		if ( is_readable( $candidate ) ) {
			return $candidate;
		}
	}

	return '';
}

/**
 * First content image: attachment ID from class wp-image-N, otherwise a file under wp-content/uploads.
 *
 * @param object $post Post object.
 * @return string
 */
function ds_pbi_content_image_path( $post ) {
	$content = isset( $post->post_content ) ? $post->post_content : '';
	if ( ! is_string( $content ) || '' === $content ) {
		return '';
	}
	if ( ! preg_match( '/<img\b[^>]*>/i', $content, $tag_match ) ) {
		return '';
	}

	$tag           = $tag_match[0];
	$attachment_id = 0;
	if ( preg_match( '/\bwp-image-(\d+)\b/', $tag, $id_match ) ) {
		$attachment_id = (int) $id_match[1];
	}
	if ( $attachment_id > 0 ) {
		$path = ds_pbi_local_attachment_path( $attachment_id, array( 'full' ) );
		if ( '' !== $path ) {
			return $path;
		}
	}

	$src = '';
	if ( preg_match( '/\ssrc\s*=\s*(["\'])([^"\']+)\1/i', $tag, $src_match ) ) {
		$src = $src_match[2];
	} elseif ( preg_match( '/\ssrc\s*=\s*([^\s>]+)/i', $tag, $src_match ) ) {
		$src = trim( $src_match[1], "\"'" );
	}

	return ds_pbi_resolve_uploads_path( $src );
}

/**
 * Map an uploads URL (any scheme or host) to a readable file under the uploads directory.
 *
 * @param string $url Image URL or root-relative path.
 * @return string
 */
function ds_pbi_resolve_uploads_path( $url ) {
	if ( ! is_string( $url ) || '' === $url ) {
		return '';
	}

	$url = html_entity_decode( $url, ENT_QUOTES, 'UTF-8' );
	if ( ! function_exists( 'wp_get_upload_dir' ) ) {
		return '';
	}

	$uploads = wp_get_upload_dir();
	if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
		return '';
	}

	$url_path = wp_parse_url( $url, PHP_URL_PATH );
	if ( ! is_string( $url_path ) || '' === $url_path ) {
		return '';
	}

	$bases = array();
	$upload_base = wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );
	if ( is_string( $upload_base ) && '' !== $upload_base ) {
		$bases[ untrailingslashit( $upload_base ) ] = $uploads['basedir'];
	}
	$content_uploads = wp_parse_url( content_url( 'uploads' ), PHP_URL_PATH );
	if ( is_string( $content_uploads ) && '' !== $content_uploads ) {
		$bases[ untrailingslashit( $content_uploads ) ] = WP_CONTENT_DIR . '/uploads';
	}

	foreach ( $bases as $base_path => $base_dir ) {
		$prefix = trailingslashit( $base_path );
		if ( 0 !== strpos( $url_path, $prefix ) && $url_path !== $base_path ) {
			continue;
		}
		$relative = ltrim( substr( $url_path, strlen( $base_path ) ), '/' );
		$file     = rtrim( $base_dir, '/\\' ) . '/' . $relative;
		if ( is_readable( $file ) ) {
			return $file;
		}
	}

	return '';
}

/**
 * @param int $post_id Post ID.
 * @return string
 */
function ds_pbi_thumbnail_path( $post_id ) {
	$dir = get_option( 'ds_pbi_cachepath', '' );
	if ( ! is_string( $dir ) ) {
		return '';
	}
	$dir = rtrim( $dir, "/\\" );
	if ( '' === $dir ) {
		return '';
	}
	return $dir . '/' . (int) $post_id . '.jpg';
}

/**
 * @param int $post_id Post ID.
 * @return string
 */
function ds_pbi_thumbnail_url( $post_id ) {
	$base = get_option( 'ds_pbi_cacheurl', '' );
	if ( ! is_string( $base ) ) {
		return '';
	}
	$base = rtrim( $base, '/' );
	if ( '' === $base ) {
		return '';
	}
	return $base . '/' . (int) $post_id . '.jpg';
}
