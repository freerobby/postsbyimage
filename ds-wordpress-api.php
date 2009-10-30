<?php
/*
file:	ds-wordpress-api.php

desc:	DS Internal API for interfacing with Wordpress. This API provides generic Wordpress functionality that suits a variety of purposes. It also handles database queries so that if the Wordpress database schema changes, those changes need only be changed here, rather than in every individual plugin.

mod:	2007-11-11

ver:	0.2

author:	Robby Grossman

legal:	Copyright 2007 Digital Sublimity <http://www.digitalsublimity.com>
		All rights reserved.
		Unauthorized reuse of the code contained herein is strictly prohibited.

notes:	API implementation is based on the Wordpress 2.3 database taxonomy.
*/
?>
<?php

if ( ! defined ( 'DS_WORDPRESS_API' ) ) {
define ( 'DS_WORDPRESS_API', 1 );

// Add category reference to post
function ds_wp_AddTaxonomyToObject ( $oid, $tid ) {
	global $wpdb;
	$sql = 'INSERT INTO ' . $wpdb -> term_relationships . ' ( object_id, term_taxonomy_id ) VALUES ( ' . $oid . ', ' . $tid . ' )';
	$wpdb -> query ( $sql );
}

// Create Plugin Options
function ds_wp_CreatePluginOptions ( $opts, $vals, $descs ) {
	echo "Called <br />";
	// Add our options with their default values as defined.
	$num_opts = count ( $opts );
	// For every option we have defined...
	for ( $i = 0; $i < $num_opts; $i++ ) {
		// ... add it to the database.
		add_option (
			$opts [ $i ],
			$vals [ $i ],
			$descs [ $i ],
			'yes'
		);
		echo "added option number $i <br />";
	}
}

// Delete Plugin Options
function ds_wp_DeletePluginOptions ( $opts = NULL ) {
	// Remove any options that are passed from the database
	if ( $opts ) {
		$num_opts = count ( $opts );
		// For every option we have defined...
		for ( $i = 0; $i < $num_opts; $i++ ) {
			// ... delete it from the database.
			delete_option ( $opts [ $i ] );
		}
	}
	// If we received no options, ...
	else {
		// ... do nothing
	}
}

/*	Func: GetObjectIDsByTermTaxonomyID
	Desc: Returns all objects (posts or pages) that match a specific term taxonomy (category or tag).
	Params:
		1. $ttid (int) [IN]
			the term taxonomy id to match.
	Returns:
		array of integers whose values are the IDs of the objects that match.
*/
function ds_wp_GetObjectIDsByTermTaxonomyID ( $ttid = '*' ) {
	// Wordpress database interface class
	global $wpdb;
	$sql = 'SELECT DISTINCT object_id'
		. ' FROM ' . $wpdb -> term_relationships;
	if ( $ttid != '*' )
		$sql .= ' WHERE term_taxonomy_id=' . $ttid;
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

/*
function ds_wp_GetObjectTitle ( $objectid ) {
	global $wpdb;
	$sql = 'SELECT post_title FROM ' . $wpdb -> posts . ' WHERE ID = ' . $objectid;
	$content = $wpdb -> get_var ( $sql );
	return $content;
}
*/

/*
function ds_wp_GetTermID ( $term_name ) {
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
*/

function ds_wp_GetURLOfFirstImageInObject ( $objectid ) {
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

// Remove category reference from postq
function ds_wp_RemoveTaxonomyFromObject ( $oid, $tid ) {
	global $wpdb;
	$sql = 'DELETE FROM ' . $wpdb -> term_relationships . ' WHERE object_id = ' . $oid . ' AND term_taxonomy_id = ' . $tid;
	$wpdb -> query ( $sql );
}

// Provide htmlspecialchars_decode () in PHP4
if (!function_exists('htmlspecialchars_decode')) {
	function htmlspecialchars_decode ($str, $quote_style = ENT_COMPAT) {
	   return strtr($str, array_flip(get_html_translation_table(HTML_SPECIALCHARS, $quote_style)));
	}
}

} //if ( ! defined ( 'DS_WORDPRESS_API' ) ) {
?>
