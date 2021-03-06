<?php
/**
 * Encapsulates the public functionality of the plugin aka the login checking.
 *
 * @since 0.7
 */
class LLC_Public {

	/** @var $instance LLC_Public Stores the single plugin object instance. */
	protected static $instance;

	/** @var $options Array Stores the plugin's options. */
	protected $options = array();

	/** @var $geoInfo geoiprecord Stores the visitor's geo information. */
	protected $geoInfo;


	/**
	 * Initialize the class, set its properties and register needed WordPress Hooks.
	 *
	 * @since 0.7
	 */
	protected function __construct() {

		// we add an init hook for loading options
		add_action( 'init', array( $this, 'load_options' ) );

		// We add the authentication filter
		add_filter( 'wp_authenticate_user', array( $this, 'limit_login_countries' ), 31, 1 );

	}

	/**
	 * Returns and if necessary creates the plugin object instance
	 *
	 * @since 0.7
	 *
	 * @return Limit_Login_Countries
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Loads the plugin's options
	 *
	 * @since 0.7
	 *
	 * @return void
	 */
	public function load_options() {
		$this->options['geoip_database'] = get_option( 'llc_geoip_database_path' );
		$this->options['dnsbl']          = get_option( 'llc_dnsbl' );
		$this->options['dnsbl_v6']       = get_option( 'llc_dnsbl_v6' );
		$this->options['blacklist']      = 'whitelist' === get_option( 'llc_blacklist', 'whitelist' ) ? false : true;
		$this->options['countryList']    = explode( ',', get_option( 'llc_countries' ) );
	}

	/**
	 * WP_Authenticate_User filter callback function. Here the magic is done: we stop authentication if needed.
	 * This filter is registred with priority 31. We can prevent login by returning a WP_Error object.
	 *
	 * @since 0.7
	 * @see https://codex.wordpress.org/Plugin_API/Filter_Reference/wp_authenticate_user
	 *
	 * @param $user Mixed NULL: no authentication yet. WP_Error: another process has already failed the authentication. WP_User: another process has authenticated the user.
	 *
	 * @return Mixed We return WP_Error when the visitor's country is not allowed. In all other cases we pass on whatever we got via $user.
	 */
	public function limit_login_countries( $user ) {

		# set own error handler to gracefully handle errors triggered by geoip lookup
		set_error_handler(
			function ( $errno, $errstr, $errfile, $errline ) {
				// Having no whitespace here looks a bit messy but phpcs has an
				// issue with empty lines
				// https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards/issues/249
				if ( $errno & E_USER_ERROR ) {
					require_once( __DIR__ . '/../includes/LLC-Admin-Notice.class.php' );
					LLC_Admin_Notice::add_notice( $errstr, 'error' );
					error_log( "Fatal error: $errstr in $errfile on line $errline" );
					return true;
				} elseif ( $errno & ( E_WARNING | E_USER_WARNING ) ) {
					# we collect warnings too, so we won't need any @-operators.
					require_once( __DIR__ . '/../includes/LLC-Admin-Notice.class.php' );
					LLC_Admin_Notice::add_notice( $errstr, 'warning' );
					error_log( "Warning: $errstr in $errfile on line $errline" );
					return true;
				} elseif ( ( $errno & E_NOTICE ) and ( 'geoip.inc' === substr( $errfile, - strlen( 'geoip.inc' ) ) ) ) {
					# suppress notices usually preceding a E_USER_ERROR
					if ( error_reporting() & E_NOTICE ) {
						error_log( "Notice: $errstr in $errfile on line $errline" );
					}
					return true;
				}
				return false;
			}
		);

		// In these cases we don't throw an error, but pass on what we got:
		if (
			is_wp_error( $user )                            // there already is an authentication error
			or ( defined( 'LIMIT_LOGIN_COUNTRIES_OVERRIDE' ) and true === LIMIT_LOGIN_COUNTRIES_OVERRIDE )    // override constant is defined
			or empty( $this->options['countryList'] )       // there is no country set in options
			or ! $this->geo_look_up()                       // there is no geo info available
			or $this->is_allowed_country()                  // the user's country is allowed
		) {
			return $user;
		}

		# own error handling not needed anymore
		restore_error_handler();

		// we are still here, so we complain about the user's country
		// translators: %s stands for the country name the user tries to login from
		$user = new WP_Error( 'country_error', sprintf( __( 'Login not allowed from your country (%s)!', 'limit-login-countries' ), __( $this->geoInfo->country_name, 'limit-login-countries' ) ) );

		// we save the unsuccessful country code
		$log = get_option( 'limit_login_countries_log', array() );
		$log[ $this->geoInfo->country_code ] += 1;
		update_option( 'limit_login_countries_log', $log );

		return $user;
	}

	/**
	 * Look up of visitor's geo information.
	 * Done seperately from __construct() because we only need it when authenticating.
	 *
	 * @since 0.7
	 *
	 * @return bool Returns TRUE on success and FALSE if there is no geo information available (e.g. localhost) or an error occurred.
	 */
	protected function geo_look_up() {

		// we check whether geo info is already loaded
		if ( ! is_object( $this->geoInfo ) ) {

			if ($this->options['geoip_database']) {
				require_once( dirname( __DIR__ ) . '/includes/LLC-GeoIP-Tools.class.php' );
				$this->geoInfo = LLC_GeoIP_Tools::get_geo_info( $this->options['geoip_database'] );
			} else {
				require_once( dirname( __DIR__ ) . '/includes/LLC-DNSBL-Tools.class.php' );
				$this->geoInfo = LLC_DNSBL_Tools::get_geo_info( $this->options['dnsbl'], $this->options['dnsbl_v6'] );
			}
		}

		// return false if no info was found (e.g. localhost) or there was an error
		return ! ( null === $this->geoInfo or false === $this->geoInfo or '' === $this->geoInfo->country_code );
	}

	/**
	 * Checks whether visitor is allowed to login from his country.
	 *
	 * @since 0.7
	 *
	 * @return bool Returns TRUE if visitor's country is allowed to login, FALSE if not.
	 */
	protected function is_allowed_country() {

		return $this->options['blacklist'] xor in_array( $this->geoInfo->country_code, $this->options['countryList'] );
	}

}
