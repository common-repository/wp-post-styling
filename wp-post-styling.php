<?php
/*
Plugin Name: WP Post Styling
Plugin URI: http://www.joedolson.com/articles/wp-post-styling/
Description: Allows you to define custom styles for any specific post or page on your WordPress site. Helps reduce clutter in your stylesheet.
Version: 1.3.2
Text Domain: wp-post-styling
Domain Path: /lang
Author: Joseph Dolson
Author URI: http://www.joedolson.com/
*/
/*  Copyright 2008-2022  Joseph C Dolson  (email : wp-post-styling@joedolson.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Enable internationalisation
add_action( 'plugins_loaded', 'wps_load_textdomain' );
function wps_load_textdomain() {
	load_plugin_textdomain( 'wp-post-styling', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
}

function wps_execute_upgrades() {
	// Upgrade post meta
	if ( get_option( 'wp_post_styling_version') ) {
		$version = get_option( 'wp_post_styling_version' );
	} else {
		$version = '1.2.2'; // could be anything less, but this is the first version with an upgrade routine.
	}
	if ( version_compare( $version, '1.2.3','<' ) ) {
		// update all post meta to match new format
		wps_fix_post_style_meta();
	}

	$wps_version = '1.3.2';
	update_option( 'wp_post_styling_version',$wps_version );
}

add_action( 'admin_enqueue_scripts', 'wps_css_editor' );
function wps_css_editor() {
	global $wp_version;
	if ( version_compare( $wp_version, '4.9',">=" ) ) {
		if ( 'settings_page_wp-post-styling/wp-post-styling' !== get_current_screen()->id ) {
			return;
		}

		// Enqueue code editor and settings for manipulating HTML.
		$settings = wp_enqueue_code_editor( array( 'type' => 'text/css' ) );

		// Bail if user disabled CodeMirror.
		if ( false === $settings ) {
			return;
		}

		wp_add_inline_script(
			'code-editor',
			sprintf(
				'jQuery( function() { wp.codeEditor.initialize( "jd_style_library_css", %s ); } );',
				wp_json_encode( $settings )
			)
		);
	}
}

function wps_insert_new_library_style( $name, $css, $type) {
	global $wpdb;
	$table_name = $wpdb->prefix . "post_styling_library";
	$insert  = array(
		'name' => $name,
		'css'  => $css,
		'type' => $type,
	);
	$formats = array( '%s', '%s', '%s' );
	$results = $wpdb->insert( $table_name, $insert, $formats );
	if ($results) {
		return TRUE;
	} else {
		return FALSE;
	}
}

function wps_update_library_style( $id, $name, $css, $type) {
	global $wpdb;
	$table_name = $wpdb->prefix . "post_styling_library";
	$data       = array(
		'name' => $name,
		'css'  => $css,
		'type' => $type,
	);

	$apply   = array( 'id' => $id );
	$formats = array( '%s', '%s', '%s' );
	$results = $wpdb->update( $table_name, $data, $apply, $formats, '%d' );

	if ($results) {
		return TRUE;
	} else {
		return FALSE;
	}
}

function wps_delete_library_style( $id ) {
	global $wpdb;
	$table_name = $wpdb->prefix . "post_styling_library";
	$results    = $wpdb->delete( $table_name, array( 'id' => $id ), '%d' );
	if ($results) {
		return TRUE;
	} else {
		return FALSE;
	}
}

function wps_create_post_styling_library_table() {
	// execute other upgrade related steps
	wps_execute_upgrades();

	global $wpdb;
	$post_styling_db_version = "1.0";
	$table_name = $wpdb->prefix . "post_styling_library";
	$sql = 'CREATE TABLE ' . $table_name . " (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  name tinytext NOT NULL,
	  css text NOT NULL,
	  type VARCHAR(32) NOT NULL,
	  UNIQUE KEY id (id)
	);";
    if ( $wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name ) {
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
	add_option( "post_styling_db_version", $post_styling_db_version );
    $installed_ver = get_option( "post_styling_db_version" );
    if ( $installed_ver != $post_styling_db_version ) {
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		update_option( "post_styling_db_version", $post_styling_db_version );
	}
}

function wps_post_style_library_selector( $library="screen", $selected='' ) {
	// select library items from database where library is $library
	if ( ! in_array( $library, array( 'screen', 'mobile', 'print' ), true ) ) {
		// Any value other than these three is invalid.
		return;
	}
	global $wpdb;
	$dbtable = $wpdb->prefix . 'post_styling_library';
	$results = $wpdb->get_results( $wpdb->prepare( 
		"SELECT `id`, `name`, `css`
		FROM `$dbtable`
		WHERE `type` = %s
		ORDER BY name ASC
		", $library ) );

	if (count($results)) {
		foreach ($results as $result) {
			if ( get_option( 'jd-post-styling-library' ) == 1 ) {
				$value = (int) $result->id;
				$checked = ( $selected == $value )?' selected="selected"':'';
			} else {
				$value = $result->css;
				$checked = '';
			}
			echo '<option value="'.esc_attr( $value ).'"'.$checked.'>'. ( esc_html( $result->name ) ) .'</option>'."\n";
		}
	} else {
		echo '<option value="none">'.__('Library is empty.','wp-post-styling').'</option>';
	}
}

function wps_post_style_data($id,$datatype) {
	// select library items from database where datatype is $datatype
	global $wpdb;
	$dbtable = $wpdb->prefix . 'post_styling_library';
	$datatype = esc_sql($datatype);
	$id = (int) $id;
	$results = $wpdb->get_results( $wpdb->prepare( "SELECT $datatype FROM $dbtable WHERE id = %d", $id ) );
	if (count($results)) {
		foreach ($results as $result) {
			return $result->{$datatype};
		}
	}
}

function wps_post_style_library_listing() {
	// select all library items from database
	global $wpdb;
	$table = "<table id=\"wp-style-library\" class=\"widefat striped\" summary=\"".__('Listing of CSS patterns in the Style Library.','wp-post-styling')."\">
<thead>\n<tr>\n	<th scope=\"col\">".__('Name','wp-post-styling')."</th>\n	<th scope=\"col\">".__('Styles','wp-post-styling')."</th>\n	<th scope=\"col\">".__('Type','wp-post-styling')."</th>\n	<th>".__('Delete','wp-post-styling')."</th>\n</tr>\n</thead>
<tbody>\n";
	$table_end = "</tbody>\n</table>";
	$dbtable = $wpdb->prefix . 'post_styling_library';
	$results = $wpdb->get_results( "SELECT `id`, `name`, `css`, `type` FROM `$dbtable` ORDER BY name ASC" );

	if (count($results)) {
		foreach ($results as $result) {
			$table .= "<tr>\n	<td><a href=\"?page=wp-post-styling/wp-post-styling.php&amp;edit_style=" . (int) $result->id ."\">" . esc_html( $result->name ) . "</a></td>\n	<td>". esc_html( stripcslashes( $result->css ) ) . "</td>\n	<td>" . esc_html( $result->type ) ."</td>\n	<td class='delete'>".'<a href="?page=wp-post-styling/wp-post-styling.php&amp;delete_style=' . (int) $result->id . '">Delete</a></td>'."\n".'</tr>'."\n";
		}
		$write_table = TRUE;
	} else {
		$table_values = '<p>'.__('Library is empty.','wp-post-styling').'</p>';
		$write_table = FALSE;
	}
	if ($write_table == TRUE) {
		echo wp_kses_post( $table );
		echo wp_kses_post( $table_end );
	} else {
		echo wp_kses_post( $table_values );
	}
	return;
}
add_action('admin_menu','wps_add_post_styling');


// Add custom field on Post & Page write/edit forms
function wps_add_post_styling_inner() {
	global $post;
	$post_id = $post;
	if ( is_object( $post_id ) ) {
		$post_id = $post_id->ID;
	} else {
		$post_id = $post_id;
	}

	$jd_style_this = get_post_meta($post_id, '_jd_style_this', true);
	if ( $jd_style_this == 'disable' ) {
		$selected = array(' checked="checked"','');
	} else {
		$selected = array('',' checked="checked"');
	}
	$jd_box_size = get_option('jd-post-styling-boxsize');
	if ($jd_box_size == '') {
		$jd_box_size = 6;
	}
	?>
	<?php if ( get_option( 'jd-post-styling-screen' ) == '1' ) { ?>
		<?php if ( get_option( 'jd-post-styling-library' ) != 1 ) { ?>
			<p>
			<label for="jd_post_styling_screen"><?php _e('Custom Screen Styles For This Post', 'wp-post-styling' ); ?></label>
				<br /><textarea name="jd_post_styling_screen" id="jd_post_styling_screen" rows="<?php echo absint( $jd_box_size ); ?>" cols="70"><?php echo esc_textarea( stripcslashes( get_post_meta( $post_id, '_jd_post_styling_screen', true ) ) ); ?></textarea>
			</p>
		<?php } ?>
		<p>
		<label for="jd_post_styling_screen_library"><?php _e('Custom Screen Style Library','wp-post-styling' ); ?></label><br /><select id="jd_post_styling_screen_library" name="jd_post_styling_screen_library">
		<option value="none"><?php _e( 'Select library style', 'wp-post-styling' ); ?></option>
		<?php wps_post_style_library_selector("screen", get_post_meta( $post_id, '_jd_post_styling_screen', true ) ); ?>
		</select>
		</p>
	<?php 
	}
	if ( get_option( 'jd-post-styling-mobile' ) == '1' ) {
		if ( get_option( 'jd-post-styling-library' ) != 1 ) {
	?>
			<p>
			<label for="jd_post_styling_mobile"><?php esc_html_e( 'Custom Mobile Styles For This Post', 'wp-post-styling' ); ?></label><br /><textarea name="jd_post_styling_mobile" id="jd_post_styling_mobile" rows="<?php echo absint( $jd_box_size ); ?>" cols="70"><?php echo esc_textarea( stripcslashes( get_post_meta( $post_id, '_jd_post_styling_mobile', true ) ) ); ?></textarea>
			</p>
	<?php
	}
	?>
		<p>
		<label for="jd_post_styling_mobile_library"><?php esc_html_e('Custom Mobile Style Library','wp-post-styling' ); ?></label><br /><select id="jd_post_styling_mobile_library" name="jd_post_styling_mobile_library">
		<option value="none"><?php esc_html_e( 'Select library style', 'wp-post-styling' ); ?></option>
		<?php wps_post_style_library_selector("mobile", get_post_meta( $post_id, '_jd_post_styling_mobile', true ) ); ?>
		</select>
		</p>
	<?php } ?>

	<?php if ( get_option( 'jd-post-styling-print' ) == '1' ) { ?>
		<?php if ( get_option( 'jd-post-styling-library' ) != 1 ) { ?>
			<p>
			<label for="jd_post_styling_print"><?php _e('Custom Print Styles For This Post', 'wp-post-styling') ?></label><br /><textarea name="jd_post_styling_print" id="jd_post_styling_print" rows="<?php echo absint( $jd_box_size ); ?>" cols="70"><?php echo esc_html( stripcslashes( get_post_meta( $post_id, '_jd_post_styling_print', true ) ) ); ?></textarea>
			</p>
		<?php } ?>
		<p>
		<label for="jd_post_styling_print_library"><?php _e('Custom Print Style Library','wp-post-styling' ); ?></label><br /><select id="jd_post_styling_print_library" name="jd_post_styling_print_library">
		<option value="none"><?php _e( 'Select library style', 'wp-post-styling' ); ?></option>
		<?php wps_post_style_library_selector("print", get_post_meta( $post_id, '_jd_post_styling_print', true ) ); ?>
		</select>
		</p>
	<?php } ?>
	<p><a href="http://www.joedolson.com/donate/"><?php _e('Support this plug-in!', 'wp-post-styling') ?></a> &raquo;
</p>
<p>
	<input type="radio" name="jd_style_this" value="disable"<?php echo $selected[0]; ?> id="jd_style_this" /> <label for="jd_style_this"><?php _e( 'Disable custom styles on this post', 'wp-post-styling' ); ?>.</label>
	<input type="radio" name="jd_style_this" value="enable"<?php echo $selected[1]; ?> id="jd_style_this_enable" /> <label for="jd_style_this_enable"><?php _e( 'Enable custom styles on this post', 'wp-post-styling' ); ?>.</label>
</p>
	<?php
	wp_nonce_field( 'wps-metabox', '_wps-nonce', true );
}

add_action('admin_menu','wps_add_outer_box');
function wps_add_outer_box() {
	if ( function_exists( 'add_meta_box' )) {
		if ( function_exists( 'get_post_types' ) ) {
			$post_types = get_post_types( array(), 'objects' );
			foreach ( $post_types as $post_type ) {
				if ( $post_type->show_ui ) {
					add_meta_box( 'poststyling_div','WP Post Styling', 'wps_add_post_styling_inner', $post_type->name, 'advanced' );
				}
			}
		} else {
			add_meta_box( 'poststyling_div','WP Post Styling', 'wps_add_post_styling_inner', 'post', 'advanced' );
			add_meta_box( 'poststyling_div','WP Post Styling', 'wps_add_post_styling_inner', 'page', 'advanced' );
		}
	}
}
// Post the custom styles into the post meta table
function wps_set_post_styling( $id ) {
	if ( isset( $_POST['jd_post_styling_screen_library'] ) || isset( $_POST['jd_post_styling_print_library'] ) || isset( $_POST['jd_post_styling_mobile'] ) ) {
		if ( ! isset( $_POST['_wps-nonce'] ) || ! wp_verify_nonce( $_POST['_wps-nonce'], 'wps-metabox' ) ) {
			die;
		}
		// consider: add option to pull styles by reference instead of from post meta.
		if ( isset($_POST['jd_post_styling_screen_library']) ) {
			$library = sanitize_text_field( $_POST[ 'jd_post_styling_screen_library' ] );
			$screen = ( isset( $_POST['jd_post_styling_screen'] ) ) ? wp_filter_nohtml_kses( $_POST[ 'jd_post_styling_screen' ] ) : (int) $library;
			if ( $library == "none" ) {
				if ( isset( $screen ) && ! empty( $screen ) ) {
					update_post_meta( $id, '_jd_post_styling_screen', $screen );
				}
			} else {
				update_post_meta( $id, '_jd_post_styling_screen', $library );
			}
		}
		if ( isset( $_POST['jd_post_styling_print_library'] ) ) {
			$print = wp_filter_nohtml_kses( $_POST[ 'jd_post_styling_print' ] );
			$library = sanitize_text_field( $_POST[ 'jd_post_styling_print_library' ] );
			if ( ! isset( $_POST['jd_post_styling_print']) ) {
				$screen = (int) $library;
			}
				if ($library == "none") {
					if (isset($print) && ! empty($print)) {
						update_post_meta( $id, '_jd_post_styling_print', $print );
					}
				} else {
					update_post_meta( $id, '_jd_post_styling_print', $library );
				}
		}
		if (isset($_POST['jd_post_styling_mobile'])) {
			$mobile = wp_filter_nohtml_kses( $_POST[ 'jd_post_styling_mobile' ] );
			$library = sanitize_text_field( $_POST[ 'jd_post_styling_mobile_library' ] );
			if ( ! isset( $_POST['jd_post_styling_mobile']) ) {
				$screen = (int) $library;
			}
				if ($library == "none") {
					if (isset($mobile) && ! empty($mobile)) {
						update_post_meta( $id, '_jd_post_styling_mobile', $mobile );
					}
				} else {
					update_post_meta( $id, '_jd_post_styling_mobile', $library );
				}
		}
		if ( isset( $_POST['jd_style_this'] ) ) {
			$jd_style_this = sanitize_text_field( $_POST[ 'jd_style_this' ] );
			if (isset($jd_style_this) && ! empty($jd_style_this)) {
				if ($jd_style_this == 'disable') {
					update_post_meta( $id, '_jd_style_this', 'disable');
				} elseif ( $jd_style_this == 'enable' ) {
					update_post_meta( $id, '_jd_style_this', 'enable');
				}
			}
		}
	}
}

function wps_enqueue_styles() {
	if ( is_singular() ) {
		wp_enqueue_style( 'wp-post-styling', plugins_url( 'css/wp-post-styling.css', __FILE__ ) );
	}
}
add_action( 'wp_enqueue_scripts', 'wps_enqueue_styles', 9 );

function wps_filter_css( $css, $type ) {
	if ( ! in_array( $type, array( 'screen', 'handheld', 'print' ), true ) ) {
		return;
	} else {
		if ( 'handheld' === $type ) {
			$breakpoint = apply_filters( 'wps_mobile_breakpoint', '640px' );
			$type       = 'screen and (max-width: ' . esc_html( $breakpoint ) . ')'; // Only apply on small viewports.
		}
		$css = '@media ' . $type . '{' . $css . '}';
		wp_add_inline_style( 'wp-post-styling', wp_filter_nohtml_kses( $css ) );
	}
}

function wps_post_post_styling() {
	if ( is_singular() ) {
		global $post;
		$id = $post->ID;
		if ( get_post_meta( $id, '_jd_style_this', TRUE ) == 'enable' ) {
			if ( get_post_meta( $id, '_jd_post_styling_screen', TRUE) != '') {
				$this_post_styles = get_post_meta( $id, '_jd_post_styling_screen', TRUE ) ;
				if ( get_option( 'jd-post-styling-library') == 1 )  {
					$this_post_styles = wps_post_style_data($this_post_styles,'css');
				}
				wps_filter_css( $this_post_styles, 'screen' );
			}
			if ( get_post_meta( $id, '_jd_post_styling_mobile', TRUE) != '' ) {
				$this_post_styles = get_post_meta( $id, '_jd_post_styling_mobile', TRUE );
				if ( get_option( 'jd-post-styling-library') == 1 ) {
					$this_post_styles = wps_post_style_data($this_post_styles,'css');
				}
				wps_filter_css( $this_post_styles, 'handheld' );
			}
			if ( get_post_meta( $id, '_jd_post_styling_print', TRUE) != '' ) {
				$this_post_styles = get_post_meta( $id, '_jd_post_styling_print', TRUE );
				if ( get_option( 'jd-post-styling-library') == 1 ) {
					$this_post_styles = wps_post_style_data($this_post_styles,'css');
				}
				wps_filter_css( $this_post_styles, 'print' );
			}
		}
	}
}

// Add the administrative settings to the "Settings" menu.

function wps_add_post_styling() {
	if ( function_exists( 'add_submenu_page' ) ) {
		 $plugin_page = add_options_page( 'WP Post Styling', 'WP Post Styling', 'edit_pages', __FILE__, 'wps_post_styling_manage_page' );
		 add_action( 'admin_head-'. $plugin_page, 'wps_admin_styles' );
	}
}

// Include the Manager page
function wps_post_styling_manage_page() {
    include( dirname(__FILE__).'/wp-post-styling-manager.php' );
}

// Add settings page.
function wps_post_styling_plugin_action($links, $file) {
	if ( $file == plugin_basename(dirname(__FILE__).'/wp-post-styling.php') ) {
		$links[] = "<a href='" . admin_url( 'options-general.php?page=wp-post-styling/wp-post-styling.php' ) . "'>" . __('Settings', 'wp-post-styling') . "</a>";
	}
	return $links;
}

function wps_fix_post_style_meta() {
	$args = array( 'numberposts' => -1 );
	$posts = get_posts( $args );
	if ($posts) {
		foreach ( $posts as $post ) {
			$post_id = $post->ID;
			$oldmeta = array('jd_post_styling_mobile','jd_post_styling_print','jd_post_styling_screen','jd_style_this');
			foreach ($oldmeta as $value) {
				$old_value = get_post_meta( $post_id,$value,true );
				update_post_meta( $post_id, "_$value", $old_value );
				delete_post_meta( $post_id, $value );
			}
		}
	}
}

function wps_admin_styles() {
	if ( $_GET['page'] == "wp-post-styling/wp-post-styling.php" ) {
		wp_enqueue_style( 'wps.styles', plugins_url( 'css/styles.css', __FILE__ ) );
	}
}

//Add Plugin Actions and Filters to WordPress
add_filter( 'plugin_action_links', 'wps_post_styling_plugin_action', 10, 2 );
add_action( 'save_post', 'wps_set_post_styling' );
add_action( 'wp_enqueue_scripts','wps_post_post_styling', 15 );
register_activation_hook( __FILE__,'wps_create_post_styling_library_table' );