<?php
/*
Plugin Name: PostsByImage

Plugin URI: http://www.digitalsublimity.com/products/postsbyimage

Description: PostsByImage is a plugin that creates a set of thumbnails for all posts containing images. Those thumbnails link to the respective posts from which they come, and they can be placed within a post or on a static page. Thumbnail sets can be created for all posts, or on a per-category basis. An artist, for example, might create a static page called "My Paintings", which would contain thumbnails/links to all posts in her "paintings" category.

Author: Digital Sublimity

Version: 1.0

Author URI: http://www.digitalsublimity.com
*/

////////////////////////////////////////////////////////////////////////////////
// Dependences
////////////////////////////////////////////////////////////////////////////////

// Wordpress Administrative Functions
require_once ( ABSPATH . '/wp-admin/admin-functions.php' );

////////////////////////////////////////////////////////////////////////////////
// Global Constant Declarations
////////////////////////////////////////////////////////////////////////////////
define ( 'POSTSBYIMAGE_GENERATETAG_START', '[postsbyimage=' ); // Start of PBI tag
define ( 'POSTSBYIMAGE_GENERATETAG_STOP', ']' ); // End of PBI tag
define ( 'POSTSBYIMAGE_ARGSEPARATOR', ';' ); // Separator of PBI tag

////////////////////////////////////////////////////////////////////////////////
// Wordpress Hook Declarations
////////////////////////////////////////////////////////////////////////////////

// Plugin activation
register_activation_hook ( __FILE__, 'ds_pbi_install' );
// Plugin deactivation
register_deactivation_hook ( __FILE__, 'ds_pbi_uninstall' );
// Add our page to the administration menu
add_action ( 'admin_menu', 'ds_pbi_addpages' );
// When a post is deleted, also delete its associated cached image
add_action ( 'delete_post', 'ds_pbi_deleteimageofpost' );
// Before content is displayed, let us look through it and make changes as necessary.
add_filter ( 'the_content', 'ds_pbi_parsecontent' ) ;
// When a post is saved, let us look at it so that we can update the cached image if need be.
add_action ( 'save_post', 'ds_pbi_postsaved' );

////////////////////////////////////////////////////////////////////////////////
// Plugin Options Definitions
////////////////////////////////////////////////////////////////////////////////

// Option keys
global $ds_pbi_options_names;
$ds_pbi_options_names = array
	(
	'ds_pbi_cachepath',
	'ds_pbi_cacheurl',
	'ds_pbi_defaultcols',
	'ds_pbi_thumbnailmaxwidth',
	'ds_pbi_thumbnailmaxheight'
	);
global $ds_pbi_options_vals;
$ds_pbi_options_vals = array
	(
	'???',
	'http://???',
	'2',
	'200',
	'200'
	);

////////////////////////////////////////////////////////////////////////////////
// Functions
////////////////////////////////////////////////////////////////////////////////

// Define our configuration pages
function ds_pbi_addpages () {
	// Add our menu under "options"
	add_options_page ( 'PostsByImage', 'PostsByImage', 'edit_plugins', __FILE__, 'ds_pbi_options_page');
}

function ds_pbi_deleteimageofpost ( $id ) {
	$fname = get_option ( 'ds_pbi_cachepath' ) . '/' . $id . '.' . 'jpg';
	if ( file_exists ( $fname ) )
		unlink ( $fname );
}

function ds_pbi_generateimagelinkshtml ( $ttid = '*' ) {
	// This is our html "stream" that we're generating:
	$content = '';
	// Each element of this array will be an image link:
	$htmls = ds_pbi_getimagelinks ( $ttid );
	if ( ! $htmls )
		return NULL;
	// Get the number of columns for our table.
	$cols = get_option ( 'ds_pbi_defaultcols' );
	// Keep track of which chronological table cell we're on.
	$thisCell = 0;
	// Start our HTML table.
	$content .= '<table width="100%" border="0">';
	foreach ( $htmls as $html ) {
		// Begin row if necessary
		if ( $thisCell % $cols == 0 ) {
			$content .= '<tr>';
		}
		// Create our column
		$content .= '<td>';
		$content .= $html;
		// End our column
		$content .= '</td>';
		// End our row if necessary
		if ( $thisCell % $cols == $cols - 1 ) {
			$content .= '</tr>';
		}
		// Increment our cell counter
		$thisCell++;
	}
	// End table
	$content .= '</table>';
	return $content;
}

// Generate any images that don't exist
function ds_pbi_generateimages ( $category = '*', $overwrite = true ) {
	global $wpdb;
	$ids = ds_pbi_GetObjectIDsByTermTaxonomyID ( $category );
	if ( ! $ids )
		return NULL;
	foreach ( $ids as $id ) {
		// For each object, regenerate the object's image.
		ds_pbi_regeneratepostimage ( $id );
	}
}

function ds_pbi_getimagelinks ( $ttid = '*' ) {
	$ids = ds_pbi_GetObjectIDsByTermTaxonomyID ( $ttid );
	$htmls = array ();
	if ( ! $ids )
		return NULL;
	foreach ( $ids as $id ) {
		// jpg only, for now
		$filename = get_option ( 'ds_pbi_cachepath' ) . '/' . $id . '.jpg';
		if ( file_exists ( $filename ) ) {
			$thisOne = '<a href="' . get_permalink ( $id ) . '"><img src="' . get_option ( 'ds_pbi_cacheurl' ) . '/' . $id . '.jpg' . '"></img></a>';
			$htmls [] = $thisOne;
		}
	}
	return $htmls;
}

function ds_pbi_install () {
	// Add our options with their default values as defined.
	global $ds_pbi_options_names;
	global $ds_pbi_options_vals;
	$num_opts = count($ds_pbi_options_names);
	for ($i = 0; $i < $num_opts; $i++) {
		add_option($ds_pbi_options_names[$i], $ds_pbi_options_vals[$i]);
	}
}

// Define our options page
function ds_pbi_options_page () {
	include  ( 'postsbyimage-options.php' );
}

// Break into: pre-tag, tag, post-tag
function ds_pbi_parsecontent ( $content ) {
	// Find start of tag
	while ( $tag_startpos = strpos ( $content, POSTSBYIMAGE_GENERATETAG_START ) ) {
		// If no tag, return original content.
		if ( ! $tag_startpos )
			return $content;
		// Find end of tag
		$tag_endpos = strpos ( $content, POSTSBYIMAGE_GENERATETAG_STOP, $tag_startpos );
		// Tag = start of tag through end of tag
		$content_tag = substr ( $content, $tag_startpos, $tag_endpos - $tag_startpos + 1);
		// Pre = all content before tag
		$content_pre = substr ( $content, 0, $tag_startpos );
		// Post = all content after tag
		$content_post = substr ( $content, $tag_endpos + 1 );
		
		$newcontent_tag = '';
		$data = substr ( $content_tag, strlen ( POSTSBYIMAGE_GENERATETAG_START), strlen ( $content_tag ) - strlen ( POSTSBYIMAGE_GENERATETAG_START ) - strlen ( POSTSBYIMAGE_GENERATETAG_STOP ) );
		$args = explode ( POSTSBYIMAGE_ARGSEPARATOR, $data );
		// If no category is specified, use default (all categories).
		if ( count ( $args ) == 0 || ( count ( $args ) == 1 && $args [ 0 ] == '' ) ) { // no category given
			$newcontent_tag = ds_pbi_generateimagelinkshtml ();
		}
		else { // generate for each category
			foreach ( $args as $arg ) {
				// If argument is a string, ...
				if ( ! is_numeric ($arg) ) {
					// Then assume it is the name of a category.
					// Convert from string to ID.
					$arg = ds_pbi_GetTermID ( $arg );
				}
				// Look up the category name and use the ID instead.
				$newcontent_tag .= ds_pbi_generateimagelinkshtml ( $arg );
			}
		}
		// Return pre-tag, processed tag, post-tag
		$content = $content_pre . $newcontent_tag . $content_post;
	}
	return $content;
}

// Called when a post is saved (created or edited)
function ds_pbi_postsaved ( $id ) {
	// Regenerate the post's thumbnail.
	ds_pbi_regeneratepostimage ( $id );
}

function ds_pbi_regenerateimagecache () {
	// Warn user that image generation may require a lot of time.
	echo 'About to regenerate your image cache... this could take a while!<br />';
	// Get all posts.
	$ids = ds_pbi_GetObjectIDsByTermTaxonomyID ();
	// Report to user the number of posts we need to examine.
	echo 'Looks like you have ' . count ( $ids ) . ' posts that need to be examined!<br />';
	$done = 1;
	// For every post we look at...
	echo 'Looking at post ';
	foreach ( $ids as $id ) {
		// ... tell the user we're looking at it.
		echo $done;
		echo '... ';
		// Simulate a post edit by directly calling our postsaved () handler. This handler will automatically regenerate the image for that post.
		ds_pbi_postsaved ( $id );
		// Increment the counter for number of posts we've edited.
		$done++;
	}
	// Report success to the user.
	echo '<br /> Success: Cache updated!<br />';
}

// Regenerate the image of a single post.
function ds_pbi_regeneratepostimage ( $id ) {
	// Delete old image affiliated with post.
	ds_pbi_deleteimageofpost ( $id );
	// Get first image from object, if existent.
	$image_path = ds_pbi_GetURLOfFirstImageInObject ( $id );
	// If an image was found, ...
	if ( $image_path ) {
		// Get the name of the existing file.
		$filename_existing = ABSPATH . substr ( $image_path, strlen ( get_option ( 'siteurl' ) ) + 1, strlen ( $image_path ) - strlen ( get_option ( 'siteurl' ) ) - 1 );
		if ( is_readable ( $filename_existing ) ) {
			$info = pathinfo ( $filename_existing );
			$filename_new = get_option ( 'ds_pbi_cachepath' ) . '/' . $id . '.jpg'; // $info [ 'extension' ];
			$image_attr = getimagesize( $filename_existing );
			$image_width = $image_attr[0];
			$image_height = $image_attr[1];
			$maxside = get_option ( 'ds_pbi_thumbnailmaxwidth' );
			if ( $image_height > $image_width ) {
				$maxside = get_option ( 'ds_pbi_thumbnailmaxheight' );
			}
			if ( ! file_exists ( $filename_new ) ) {
				$thumb = wp_create_thumbnail ( $filename_existing, $maxside );
				if ( file_exists ( $thumb ) ) {
					rename ( $thumb, $filename_new );
				}
				else {
					// thumbnail creation failed!
					// Assume it's hosted off-site.
					echo 'Image hosted off-site!';
				}
			}
		}
	}
}

function ds_pbi_uninstall () {
	// Remove options from database on uninstall.
	//global $ds_pbi_options_names;
	//$num_opts = count($ds_pbi_options_names);
	//for ($i = 0; $i < $num_opts; $i++) {
	//	delete_option($ds_pbi_options_names[$i], $ds_pbi_options_vals[$i]);
	//}
}

/*	Func: GetObjectIDsByTermTaxonomyID
	Desc: Returns all published objects (posts or pages) that match a specific term taxonomy (category or tag).
	Params:
		1. $ttid (int) [IN]
			the term taxonomy id to match.
	Returns:
		array of integers whose values are the IDs of the objects that match.
*/
function ds_pbi_GetObjectIDsByTermTaxonomyID ( $ttid = '*' ) {
	// Wordpress database interface class
	global $wpdb;
	$sql = 'SELECT DISTINCT TR.object_id'
		. ' FROM ' . $wpdb -> term_relationships . ' TR, ' . $wpdb -> posts . ' P'
		. ' WHERE TR.object_id=P.ID AND P.post_status="publish"';
	if ( $ttid != '*' )
		$sql .= ' AND term_taxonomy_id=' . $ttid;
	// Get the results from our query.
	$sql_results = $wpdb -> get_results ( $sql, ARRAY_A );
	// Put our results into an array.
	$return_ids = array ();
	if ( ! $sql_results ) {
		$return_ids = NULL;
	}
	else {
		foreach ( $sql_results as $this_sql_result )
			$return_ids [] = $this_sql_result [ 'object_id' ];
	}
	// Return the array that we generated.
	return $return_ids;
}

/*	Func: ds_pbi_GetTermID
	Desc: Returns the ID of a term (category) based on its name.
	Params:
		1. $term_name (string) [IN]
			the name of the term whose ID we need
	Returns:
		integer ID of term
*/
function ds_pbi_GetTermID ( $term_name ) {
	// Wordpress database interface class
	global $wpdb;
	// Get the id based on the term name.
	// SQL string compares are not case-sensitive.
	$sql .= 'SELECT term_id'
		. ' FROM ' . $wpdb -> terms
		. ' WHERE name="' . $term_name . '"';
	$content = $wpdb -> get_var ( $sql );
	// TODO: Make sure we got valid results; else return -1, perhaps.
	return $content;
}

function ds_pbi_GetURLOfFirstImageInObject ( $objectid ) {
	// Wordpress database interface class
	global $wpdb;
	// Grab content from the database where it looks like we have an image
	$sql = 'SELECT post_content'
		. ' FROM ' . $wpdb -> posts
		. ' WHERE ID=' . $objectid . ' AND post_content LIKE "%<img%src%"';
	$content = $wpdb -> get_var ( $sql );
	// Define more explicitly what an image pattern is.
	$pattern = '/<img.*src\s*=\s*"([^"]*)"/iU';
	// If we have a match, ...
	if ( preg_match ( $pattern, $content, $matches ) ) {
		// ... return it!
		return $matches [ 1 ];
	}
	// If we don't have a match, ...
	else {
		// ... return null.
		return NULL;
	}
}

?>
