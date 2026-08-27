<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mo_openid_send_email( $user_id = '', $user_url = '' ) {
	if ( get_option( 'mo_openid_email_enable' ) == 1 ) {
		global $wpdb;
		$admin_mail = get_option( 'mo_openid_admin_email' );
		if ( '' === $user_id ) {
			$user_name = '##UserName##';
		} else {
			$mo_cache_key   = mo_openid_cache_key( 'wp_user_login_by_id:' . $user_id );
			$mo_cache_found = false;
			$user_name      = wp_cache_get( $mo_cache_key, MO_OPENID_CACHE_GROUP, false, $mo_cache_found );
			if ( false === $mo_cache_found ) {
				$user_name = $wpdb->get_var( $wpdb->prepare( "SELECT user_login FROM {$wpdb->users} WHERE ID = %d", $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange -- checking wp_users for the login of a known ID; cached below via wp_cache_set().
				wp_cache_set( $mo_cache_key, $user_name, MO_OPENID_CACHE_GROUP, 60 );
			}
		}
		$content    = get_option( 'mo_openid_register_email_message' );
		$subject    = '[' . get_bloginfo( 'name' ) . '] New User Registration - Social Login';
		$content    = str_replace( '##User Name##', $user_name, $content );
		$headers    = 'Content-Type: text/html';
		$a          = wp_mail( $admin_mail, $subject, $content, $headers );
	}
}
