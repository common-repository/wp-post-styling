<?php
// Set Default Options
$message = '';
if ( get_option( 'post-styling-initial') != '1' ) {
	update_option( 'jd-post-styling-screen', '1' );
	update_option( 'post-styling-initial', '1' );
	update_option( 'jd-post-styling-default', '1' );
	update_option( 'jd-post-styling-library', '0' );
	update_option( 'jd-post-styling-boxsize', '6' );
}

if ( isset($_POST['submit-type']) && $_POST['submit-type'] == 'options' ) {
	//UPDATE OPTIONS
	if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'wps-settings' ) ) {
		die;
	}
	update_option( 'jd-post-styling-screen', ( isset( $_POST['jd-post-styling-screen'] ) )?1:0 );
	update_option( 'jd-post-styling-mobile', ( isset( $_POST['jd-post-styling-mobile'] ) )?1:0 );
	update_option( 'jd-post-styling-print', ( isset( $_POST['jd-post-styling-print'] ) )?1:0 );
	update_option( 'jd-post-styling-default', ( isset( $_POST['jd-post-styling-default'] ) )?1:0 );
	update_option( 'jd-post-styling-library', ( isset( $_POST['jd-post-styling-library'] ) )?1:0 );
	update_option( 'jd-post-styling-boxsize', absint( $_POST['jd-post-styling-boxsize'] ) );
	$message = __("WP Post Styling Options Updated",'wp-post-styling');
}
if ( isset($_POST['submit-type']) && $_POST['submit-type'] == 'library' ) {
	if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'wps-edit' ) ) {
		die;
	}
	if ( ( ( ! isset( $_POST['jd_style_library_name'] ) || $_POST[ 'jd_style_library_name' ] == '') ||
			( ! isset( $_POST['jd_style_library_css'] ) || $_POST[ 'jd_style_library_css' ] == '') ||
			( ! isset( $_POST['jd_style_library_type'] ) || $_POST[ 'jd_style_library_type' ] == '')) && ! isset($_POST['delete_style']) ) {
		$message = '<ul>';
		if ( $_POST[ 'jd_style_library_name' ] == '' ) {
			$message .= '<li>' . __("Please enter a name for this Style Library record.",'wp-post-styling') . '</li>';
		}
		if ( $_POST[ 'jd_style_library_css' ] == '' ) {
			$message .= '<li>' . __("Please enter styling instructions for this Style Library record.",'wp-post-styling') . '</li>';
		}
		if ( $_POST[ 'jd_style_library_type' ] == '' ) {
			$message .= '<li>' . __("Please select a type for this Style Library record.",'wp-post-styling') . '</li>';
		}
		$message .= '</ul>';
	} else {
		if (isset($_POST['edit_style'])) {
			$id      = sanitize_text_field( $_POST['edit_style'] );
			$name    = sanitize_text_field( $_POST['jd_style_library_name'] );
			$css     = wp_kses_post( $_POST['jd_style_library_css'] );
			$libtype = sanitize_text_field( $_POST['jd_style_library_type'] );
			$results = wps_update_library_style( $id, $name, $css, $libtype );
			$type    = "update";
		} elseif (isset($_POST['delete_style'])) {
			$id      = sanitize_text_field( $_POST['delete_style'] );
			$results = wps_delete_library_style( $id );
			$type    = "delete";
		} else {
			$type    = "insert";
			$name    = sanitize_text_field( $_POST['jd_style_library_name'] );
			$css     = wp_kses_post( $_POST['jd_style_library_css'] );
			$libtype = sanitize_text_field( $_POST['jd_style_library_type'] );
			$results = wps_insert_new_library_style( $name, $css, $libtype );
		}
		if ( $results == TRUE ) {
			if ( $type == "update" ) {
				$message = __("WP Post Styling Library Updated",'wp-post-styling');
			} elseif ( $type == "delete" ) {
				$message = __("Record Deleted from WP Post Styling Library",'wp-post-styling');
			} elseif ( $type == "insert" ) {
				$message = __("Record Added to WP Post Styling Library",'wp-post-styling');
			}
		} else {
			$message = __("WP Post Styling Library Update Failed",'wp-post-styling');
		}
	}
}

// to see if checkboxes should be checked
if ( ! function_exists('wps_checkbox') ) {
	function wps_checkbox( $theFieldname ){
		if ( get_option( $theFieldname ) == '1'){
			echo 'checked="checked"';
		}
	}
}
if ( $message || isset( $_GET['delete_style'] ) ) {
	?>
	<div id="message" class="updated"><p>
	<?php
	if ( $message ) {
		echo wp_kses_post( $message );
	} else {
		$delete_style = (int) $_GET['delete_style'];
		$wpnonce = wp_nonce_field( 'wps-edit', '_wpnonce', true, false );

		esc_html_e("Are you sure you want to delete this record?",'wp-post-styling');
		?>
		<form method="post" action="?page=wp-post-styling/wp-post-styling.php">
		<div>
		<?php echo $wpnonce; ?>
		<input type="hidden" name="delete_style" value="<?php echo esc_attr( $delete_style ); ?>" />
		<input type="hidden" name="submit-type" value="library" />
		<input type="submit" name="submit" class="button-primary" value="<?php esc_html_e('Yes, delete it!',"wp-post-styling") ?>" />
		</div>
		</form>
		<?php
	}
	?>
	</p></div>
	<?php
}
?>
<div class="wrap" id="wp-post-styling">

<h1><?php esc_html_e( 'WP Post Styling', 'wp-post-styling' ); ?></h1>

<div class="postbox-container" style="width:70%">
<div class="metabox-holder">
	<div class="meta-box-sortables">
		<div class="postbox">
		<h2 class='hndle'><?php esc_html_e("WP Post Styling Settings", 'wp-post-styling' ); ?></h2>
		<div class="inside">
			<p>
			<?php esc_html_e( "This plugin adds up to three style fields to your posting interface for adding custom styles. Usually, you'll only need custom screen styles, but you can also choose to add mobile or print media styles for each post, if your default style sheets don't cover this.", 'wp-post-styling' ); ?>
			</p>
			<p>
			<?php esc_html_e( "Note that the styles you assign a given post using this plugin will only apply to that post's individual post page, and will <em>not</em> be applied on any archive pages.", 'wp-post-styling' ); ?>
			</p>
		</div>
		</div>
	</div>

	<div class="meta-box-sortables">
		<div class="postbox">
		<h2 class='hndle'><?php esc_html_e("WP Post Styling Settings", 'wp-post-styling' ); ?></h2>
		<div id="post-styling-library" class="inside post-styling-library">
		<form method="post" action="<?php esc_url( admin_url( 'options-general.php?page=wp-post-styling/wp-post-styling.php' ) ); ?>">
		<?php
		wp_nonce_field( 'wps-edit', '_wpnonce', true );

		if (isset($_GET['edit_style'])) { 
			$id   = (int) $_GET['edit_style'];
			$name = wps_post_style_data( $id, 'name' );
			$css  = wps_post_style_data( $id, 'css' );
			echo "<div><input type='hidden' name='edit_style' value='" . esc_attr( $id ) . "' /></div>";
		}  else {
			$name = $css = '';
		}
		?>
			<fieldset>
			<legend><?php if (! isset($_GET['edit_style'])) {
				esc_html_e('Add a Custom Style','wp-post-styling');
			} else {
				esc_html_e('Edit Custom Style','wp-post-styling');
			}
			?></legend>
			<p>
			<label for="jd_style_library_name"><?php esc_html_e('Style Name','wp-post-styling' ); ?></label><br /><input type="text" name="jd_style_library_name" id="jd_style_library_name" value="<?php echo esc_attr( $name ); ?>" size="40" />
			</p>
			<p>
			<label for="jd_style_library_css"><?php esc_html_e('CSS','wp-post-styling' ); ?></label><br /><textarea name="jd_style_library_css" id="jd_style_library_css" rows="20" cols="50"><?php echo esc_textarea( stripcslashes( $css ) ); ?></textarea>
			</p>
			<p>
			<label for="jd_style_library_type"><?php _e('Library Type','wp-post-styling' ); ?></label>
			<select name="jd_style_library_type" id="jd_style_library_type">
				<?php
					$id = ( isset( $_GET['edit_style'] ) ) ? (int) $_GET['edit_style'] : false;
					$type = wps_post_style_data( $id, 'type' );
				?>
				<option value="screen"<?php selected( 'screen', $type ); ?>><?php esc_html_e('Screen' ); ?></option>
				<option value="mobile"<?php selected( 'mobile', $type ); ?>><?php esc_html_e('Mobile' ); ?></option>
				<option value="print"<?php selected( 'print', $type ); ?>><?php esc_html_e('Print' ); ?></option>
			</select>
			</p>
			</fieldset>
			<div>
			<input type="hidden" name="submit-type" value="library" />
			</div>
		<p>
		<input type="submit" name="submit" class="button-primary" value="<?php if (! isset($_GET['edit_style'])) { esc_html_e('Add to WP Post Styling Library','wp-post-styling'); } else { esc_html_e('Update WP Post Styling Library','wp-post-styling'); }?>" />
		</p>
		</form>
		<?php if ( isset($_GET['edit_style']) ) {
			echo "<p><a href=\"?page=wp-post-styling/wp-post-styling.php\">";
			esc_html_e( 'Add New Style','wp-post-styling' );
			echo "</a></p>";
		} ?>
</div>
</div>
</div>

	<div class="meta-box-sortables">
		<div class="postbox">
		<h2 class='hndle'><?php esc_html_e('General Settings','wp-post-styling' ); ?></h2>
		<div id="post-styling-library-settings" class="inside post-styling-library">
		<form method="post" action="<?php esc_url( admin_url( 'options-general.php?page=wp-post-styling/wp-post-styling.php' ) ); ?>">
		<?php wp_nonce_field( 'wps-settings', '_wpnonce', true ); ?>
		<fieldset>
			<legend><?php esc_html_e('WordPress Post Styling Options','wp-post-styling' ); ?></legend>
			<p>
				<input type="checkbox" name="jd-post-styling-screen" id="wps-screen" value="1" <?php wps_checkbox('jd-post-styling-screen' ); ?> />
				<label for="wps-screen"><?php esc_html_e('Add Custom Screen Styles','wp-post-styling' ); ?></label>
			</p>
			<p>
				<input type="checkbox" name="jd-post-styling-mobile" id="wps-mobile" value="1" <?php wps_checkbox('jd-post-styling-mobile' ); ?> />
				<label for="wps-mobile"><?php esc_html_e('Add Custom Mobile Styles','wp-post-styling' ); ?></label>
			</p>
			<p>
				<input type="checkbox" name="jd-post-styling-print" id="wps-print" value="1" <?php wps_checkbox('jd-post-styling-print' ); ?> />
				<label for="wps-print"><?php esc_html_e('Add Custom Print Styles','wp-post-styling' ); ?></label>
			</p>
			<p>
				<input type="checkbox" name="jd-post-styling-default" id="wps-default" value="disable" <?php wps_checkbox('jd-post-styling-default' ); ?> />
				<label for="wps-default"><?php esc_html_e('Disable Custom Styles as default condition','wp-post-styling' ); ?></label>
			</p>
			<p>
				<input type="checkbox" name="jd-post-styling-library" id="wps-library" value="disable" <?php wps_checkbox('jd-post-styling-library' ); ?> />
				<label for="wps-library"><?php esc_html_e('Pull Post Styles Directly from Library','wp-post-styling' ); ?></label>
			</p>
			<p>
				<input type="text" name="jd-post-styling-boxsize" id="wps-boxsize" value="<?php echo esc_attr( get_option('jd-post-styling-boxsize') ); ?>" size="3" />
				<label for="wps-boxsize"><?php esc_html_e('Size of custom style text box (in lines.)','wp-post-styling' ); ?></label>
			</p>
		</fieldset>
		<div><input type="hidden" name="submit-type" value="options" /></div>
		<p><input type="submit" name="submit" class="button-primary" value="<?php esc_attr_e('Save WP Post Styling Options', 'wp-post-styling' ); ?>" /></p>
	</form>
</div>
</div>
</div>

	<div class="meta-box-sortables">
		<div class="postbox">
			<h2 class='hndle'><?php esc_html_e('Your Style Library','wp-post-styling' ); ?></h2>
			<div id="post-styling-library-listing" class="inside post-styling-entries">

			<?php wps_post_style_library_listing(); ?>
			<?php
				if ( get_option( 'jd-post-styling-library' ) != '1' ) {
				?>
					<p>
					<?php esc_html_e('Note: editing the styles in your style library will not effect any previously published posts using those styles.','wp-post-styling' ); ?>
					</p>
				<?php
				}
			?>
			</div>
		</div>
	</div>
</div>

</div>

<div class="postbox-container" style="width:25%">
	<div class="metabox-holder">
		<div class="meta-box-sortables">
			<div class="postbox">
				<h2 class='hndle'><?php esc_html_e("Resources", 'wp-post-styling' ); ?></h2>
				<div class="inside resources">
					<p>
						<a href="https://twitter.com/intent/follow?screen_name=joedolson" class="twitter-follow-button"
						   data-size="small" data-related="joedolson">Follow @joedolson</a>
						<script>!function (d, s, id) {
								var js, fjs = d.getElementsByTagName(s)[0];
								if (!d.getElementById(id)) {
									js = d.createElement(s);
									js.id = id;
									js.src = "https://platform.twitter.com/widgets.js";
									fjs.parentNode.insertBefore(js, fjs);
								}
							}(document, "script", "twitter-wjs");</script>
					</p>
				</div>
			</div>
		</div>

		<div class="meta-box-sortables">
			<div class="postbox">
				<h2 class='hndle'><?php esc_html_e('Try my other plugins','wp-post-styling' ); ?></h2>
				<div id="support" class="inside resources">
					<ul>
						<li><span class='dashicons dashicons-twitter' aria-hidden="true"></span> <a href="https://wordpress.org/plugins/wp-to-twitter/">WP to Twitter</a></li>
						<li><span class='dashicons dashicons-calendar-alt' aria-hidden="true"></span> <a href="https://wordpress.org/plugins/my-calendar/">My Calendar</a></li>
						<li><span class='dashicons dashicons-tickets' aria-hidden="true"></span> <a href="https://wordpress.org/plugins/my-tickets/">My Tickets</a></li>
						<li><span class='dashicons dashicons-universal-access-alt' aria-hidden="true"></span> <a href="https://wordpress.org/plugins/wp-accessibility/">WP Accessibility</a></li>
						<li><span class='dashicons dashicons-wordpress' aria-hidden="true"></span> <a href="http://profiles.wordpress.org/users/joedolson/#content-plugins"><?php _e('And even more...','wp-post-styling' ); ?></a></li>
					</ul>
				</div>
			</div>
		</div>
	</div>
</div>
</div>
