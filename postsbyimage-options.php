<?php
// If the form was just submitted, ...
if ( isset ( $_POST [ 'submitted' ] ) ) {
	// Update the local path of the cache folder.
	update_option ( 'ds_pbi_cachepath', htmlspecialchars ( $_POST [ 'cachepath' ], ENT_QUOTES ) );
	// Update the URL of the cache folder.
	update_option ( 'ds_pbi_cacheurl', htmlspecialchars ( $_POST [ 'cacheurl' ], ENT_QUOTES ) );
	// Update the number of default columns to use for a display table.
	update_option ( 'ds_pbi_defaultcols', htmlspecialchars ( $_POST [ 'defaultcols' ], ENT_QUOTES ) );
	// Update the maximum width of a thumbnail
	update_option ( 'ds_pbi_thumbnailmaxwidth', htmlspecialchars ( $_POST [ 'thumbnailmaxwidth' ], ENT_QUOTES ) );
	// Update the maximum height of a thumbnail
	update_option ( 'ds_pbi_thumbnailmaxheight', htmlspecialchars ( $_POST [ 'thumbnailmaxheight' ], ENT_QUOTES ) );
?>
	<div id="message" class="updated fade">
		<p>
			<strong>
				Options saved!
			</strong>
		</p>
	</div>
<?php
}

// If the user requested that we run autoconfigure, ...
if ( isset ( $_POST [ 'autoconfigure' ] ) ) {
?>
	<div id="message" class="updated fade">
		<p>
			<strong>
				Running Autoconfigure... <br />
<?php
	// Use default location
	echo 'Creating cache folder... ';
	$default_cachepath = ABSPATH . get_option ( 'upload_path' ) . '/postsbyimage_cache';
	// Try to make the directory, and add perms 777.
	// (Not the most secure way but oh well).
	mkdir ( $default_cachepath, 0777 );
	$success = is_dir ( $default_cachepath );
	// If the folder was not created, ...
	$success = true;
	if ( ! $success ) {
		echo 'FAIL <br />';
	}
	else {
		echo '<br />';
		// Create Options
		echo 'Configuring plugin... <br />';
		update_option ( 'ds_pbi_cachepath', htmlspecialchars ( $default_cachepath ), ENT_QUOTES );
		update_option ( 'ds_pbi_cacheurl', htmlspecialchars ( get_option ( 'siteurl' ) . '/' . get_option ( 'upload_path' ) . '/postsbyimage_cache' ), ENT_QUOTES );
		
		// Generate cache
		ds_pbi_regenerateimagecache ();
		echo 'Autoconfigure complete!';
	}
?>
			</strong>
		</p>
	</div>
<?php
}

// If the cache was just regenerated
// Eventually, this should be made the official trigger.
if ( isset ( $_POST [ 'regenerateimagecache' ] ) ) {
?>
	<div id="message" class="updated fade">
		<p>
			<strong>
				Cache regeneration request received! <br />
				<?php ds_pbi_regenerateimagecache (); ?>
			</strong>
		</p>
	</div>
<?php
}
?>

<!-- Beginning of Options Page -->
<div class="wrap">
	<h2>
		Automatic Configuration
	</h2>
	<form action="<?php echo $_SERVER ['REQUEST_URI']; ?>" method="post">
<!-- End of Beginning of Options Page -->
<h3>
	Auto-Configure
</h3>
<p>
	Autoconfigure is the method of setup recommended for most users. It works on most shared hosting providers. Autoconfigure will do the following, in order:
	<ol>
		<li>
			Create a cache directory in your uploads folder.
		</li>
		<li>
			Configure PostsByImage to use the newly-created cache directory.
		</li>
		<li>
			Generate the image cache for your existing posts and pages.
		</li>
	</ol>
	<input type="submit" name="autoconfigure" value="Run Autoconfigure" />
</p>

<h2>
	Image Cache
</h2>
<h3>
	Image Cache Generation
</h3>
<p>
	Clicking the button below will regenerate your image cache. You should only have to do this once (immediately after installing the PostsByImage plugin). Please note that this process can take a lot of time--if your server is slow or if you have a lot of posts it may take several minutes!
	<br />
	<input type="submit" name="regenerateimagecache" value="Regenerate Image Cache" />
</p>

<h3>
	Image Cache Storage
</h3>
<p>
	Generated images will be stored on your server in the directory below.
	<br />
	<input type="text" name="cachepath" value="<?php echo stripslashes ( get_option ( 'ds_pbi_cachepath' ) ); ?>" size="<?php echo strlen ( stripslashes ( get_option ( 'ds_pbi_cachepath' ) ) ); ?>" />
	<br />
</p>

<h3>
	Image Cache Accessibility
</h3>
<p>
	Images stored in your cache are accessible in the URL specified below.
	<br />
	<input type="text" name="cacheurl" value="<?php echo stripslashes ( get_option ( 'ds_pbi_cacheurl' ) ); ?>" size="<?php echo strlen ( stripslashes ( get_option ( 'ds_pbi_cacheurl' ) ) ); ?>" />
</p>

<h2>
	Display Settings
</h2>
<h3>
	Columns per Table
</h3>
<p>
	Number of columns in your display table:
	<input type="text" name="defaultcols" value="<?php echo stripslashes ( get_option ( 'ds_pbi_defaultcols' ) ); ?>" size="3" />
</p>
<h3>
	Thumbnail Dimensions
</h3>
<p>
	<em>
		Note: Changing these values will only affect newly-generated thumbnails.
		<br />
		To change the size of existing thumbnails, regenerate your image cache after modifying these settings.
	</em>
	<table border="0">
		<tr>
			<td>
				Max width of a generated wide thumbnail:
			</td>
			<td>
				<input type="text" name="thumbnailmaxwidth" value="<?php echo stripslashes ( get_option ( 'ds_pbi_thumbnailmaxwidth' ) ); ?>" size="3" />
			</td>
		</tr>
		<tr>
			<td>
				Max height of a generated tall thumbnail:
			</td>
			<td>
				<input type="text" name="thumbnailmaxheight" value="<?php echo stripslashes ( get_option ( 'ds_pbi_thumbnailmaxheight' ) ); ?>" size="3" />
			</td>
		</tr>
	</table>
</p>

<input type="submit" name="submitted" value="Update Options &raquo;" />
	</form>
</div>
