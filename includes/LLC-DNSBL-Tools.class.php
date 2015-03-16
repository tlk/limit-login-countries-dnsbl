<?php

/**
 * Class LLC_DNSBL_Tools Contains helper functions for handling the DNSBL
 * lookups.
 *
 *
 * @package Limit Login Countries
 * @author: Dirk Weise
 * @author: Thomas L. Kjeldsen
 * @since 0.7-dnsbl
 */
class LLC_DNSBL_Tools {

	/**
	 * The constructor is declared private to make sure this helper class
	 * cannot be instantiated.
	 *
	 * @since 0.7-dnsbl
	 */
	private function __construct() {
	}

	/**
	 * Look up visitor geo information in GeoIP database.
	 *
	 * @since 0.7-dnsbl
	 *
	 * @param $geoIPDatabase
	 *
	 * @return geoiprecord|null|false Returns geoiprecord on sucess, NULL if no
	 *                                geo info is available and FALSE on error.
	 */
	public static function get_geo_info( $dnsbl, $dnsbl_v6 ) {

		if ( self::is_ip_v4() ) {
			$geoInfo = self::dnsbl_country_lookup( $dnsbl, $_SERVER['REMOTE_ADDR'] );
		} elseif ( self::is_ip_v6() ) {
			$geoInfo = self::dnsbl_country_lookup_v6( $dnsbl_v6, $_SERVER['REMOTE_ADDR'] );
		} else {
			$geoInfo = false;
			trigger_error( 'Invalid IP address in $_SERVER[\'REMOTE_ADDR\']: [' . $_SERVER['REMOTE_ADDR'] . ']', E_USER_WARNING );
		}

		return $geoInfo;
	}

	/**
	 * Check if user's IP address is v4.
	 *
	 * @since 0.7-dnsbl
	 *
	 * @return bool True if user's IP address is IPv4, false otherwise.
	 */
	public static function is_ip_v4() {
		return false !== filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
	}

	/**
	 * Check if user's IP address is v6.
	 *
	 * @since 0.7-dnsbl
	 *
	 * @return bool True if user's IP address is IPv6, false otherwise.
	 */
	public static function is_ip_v6() {
		return false !== filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 );
	}

	public static function dnsbl_country_lookup($dnsbl, $addr) {
		if ($dnsbl == null || $addr == null) {
			return 0;
		}

		$rip = implode('.',array_reverse(explode(".",$addr)));
		return self::_get_record("$rip.$dnsbl.");
	}

	public static function dnsbl_country_lookup_v6($dnsbl, $addr) {
		if ($dnsbl == null || $addr == null) {
			return 0;
		}

		$addr = inet_pton($addr);
		$unpack = unpack('H*hex', $addr);
		$hex = $unpack['hex'];
		$rip = implode('.', array_reverse(str_split($hex)));
		return self::_get_record("$rip.$dnsbl.");
	}

	public static function _get_record($host) {

		$result = dns_get_record($host);

		foreach($result as $r) {
			if ($r['type'] === 'TXT') {
				$country = $r['txt'];
				break;
			}
		}

		if (!$country) {
			return null;
		}

		if (preg_match('/CC=([[:alpha:]]{2}) /', $country, $matches)) {
			$country = $matches[1];
		}

		if (preg_match('/^[[:alpha:]]{2}$/', $country)) {
			$country = strtoupper($country);
		} else {
			return null;
		}

		require_once( dirname( __DIR__ ) . '/includes/LLC-GeoIP-Countries.class.php' );
		$gc = new LLC_GeoIP_Countries();

		return (object) array(
			'country_code' => $country,
			'country_name' => $gc->country_data[$country],
		);
	}

}
