<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
function mo_sl($string){
	 return esc_attr( __( $string, 'miniorange-login-openid' ) ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- mo_sl() is this plugin's i18n wrapper; callers always pass literal strings.
 }