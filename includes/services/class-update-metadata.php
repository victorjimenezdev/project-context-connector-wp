<?php
/**
 * Wrap core update transients for optional inclusion.
 *
 * @package ProjectContextConnector
 */

namespace PCC\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads update metadata from core transients.
 */
class Update_Metadata {

	/**
	 * Get plugin updates map keyed by plugin file.
	 *
	 * @return array
	 */
	public function plugin_updates() {
		$upd = get_site_transient( 'update_plugins' );
		if ( ! is_object( $upd ) || empty( $upd->response ) ) {
			return array();
		}
		$out = array();
		foreach ( $upd->response as $file => $obj ) {
			$out[ $file ] = array(
				'new_version' => isset( $obj->new_version ) ? (string) $obj->new_version : '',
				'url'         => isset( $obj->url ) ? (string) $obj->url : '',
				'package'     => isset( $obj->package ) ? (string) $obj->package : '',
				'slug'        => isset( $obj->slug ) ? (string) $obj->slug : '',
			);
		}
		return $out;
	}

	/**
	 * Get theme updates keyed by stylesheet (slug).
	 *
	 * @return array
	 */
	public function theme_updates() {
		$upd = get_site_transient( 'update_themes' );
		if ( ! is_object( $upd ) || empty( $upd->response ) ) {
			return array();
		}
		$out = array();
		foreach ( $upd->response as $stylesheet => $obj ) {
			$out[ $stylesheet ] = array(
				'new_version' => isset( $obj['new_version'] ) ? (string) $obj['new_version'] : '',
				'url'         => isset( $obj['url'] ) ? (string) $obj['url'] : '',
				'package'     => isset( $obj['package'] ) ? (string) $obj['package'] : '',
			);
		}
		return $out;
	}

	/**
	 * Get core update info (stable channel only).
	 *
	 * @return array
	 */
	public function core_updates() {
		$upd = get_site_transient( 'update_core' );
		$out = array(
			'updates' => array(),
		);
		if ( is_object( $upd ) && ! empty( $upd->updates ) && is_array( $upd->updates ) ) {
			foreach ( $upd->updates as $u ) {
				if ( isset( $u->response ) && 'upgrade' === $u->response ) {
					$out['updates'][] = array(
						'version' => isset( $u->version ) ? (string) $u->version : '',
						'package' => isset( $u->download ) ? (string) $u->download : '',
						'locale'  => isset( $u->locale ) ? (string) $u->locale : '',
						'current' => get_bloginfo( 'version' ),
					);
				}
			}
		}
		return $out;
	}
}
