<?php
require_once( 'wp-posts-date-alert.php' );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit();

/**
 * プラグインで保持しているオプション値の削除
 */
function wppda_delete_plugin() {
	global $wpdb;
	$wpdb->query("delete from {$wpdb->postmeta} where meta_key ='" . PostsDateAlert::n( 'is_display_alert' ) . "';");

	delete_option( PostsDateAlert::n( 'date' ) );
	delete_option( PostsDateAlert::n( 'alert' ) );
	delete_option( PostsDateAlert::n( 'use_type' ) );
	delete_option( PostsDateAlert::n( 'use_css' ) );
	delete_option( PostsDateAlert::n( 'alert_position' ) );
	delete_option( PostsDateAlert::n( 'use_wrapper' ) );
}

wppda_delete_plugin();
