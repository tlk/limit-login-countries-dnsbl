<?php

/**
 * Class LLC_Options_Page Contains helper functions for the plugin's settings
 * page.
 *
 * This class encapsulates display and change of our plugin's options.
 *
 * @package Limit Login Countries
 * @author: Dirk Weise
 * @since:  0.3
 *
 */
class LLC_Options_Page {

	/**
	 * The constructor is declared private to make sure this helper class cannot
	 * be instantiated.
	 *
	 * @since 0.3
	 */
	private function __construct() {
	}

	/**
	 * Check the current settings and display errors.
	 * Fired on admin_init hook.
	 *
	 * @see   LLC_Admin::__construct()
	 * @since 0.3
	 */
	public static function check_settings() {
		// TODO: check if admin will be locked out after logout
	}

	/**
	 * Return link to the options page.
	 *
	 * @since 0.7
	 *
	 * @return string
	 */
	public static function get_link() {

		return sprintf( '<a href="' . admin_url( 'options-general.php?page=%s' ) . '">%s</a>', 'limit-login-countries', __( 'Settings', 'limit-login-countries' ) );
	}

	/**
	 * Registers all our settings with WP's settings API.
	 * Callback function for WP's admin_init hook.
	 *
	 * @see LLC_Admin::__construct()
	 * @see http://codex.wordpress.org/Settings_API
	 * @since 0.3
	 */
	public static function register_settings() {

		// we register all our settings
		register_setting( 'limit-login-countries', 'llc_blacklist', array( 'LLC_Options_PAGE', 'blacklist_validate' ) );
		register_setting( 'limit-login-countries', 'llc_countries', array( 'LLC_Options_PAGE', 'countries_validate' ) );
		register_setting( 'limit-login-countries', 'llc_dnsbl', array( 'LLC_Options_PAGE', 'dnsbl_validate' ) );
		register_setting( 'limit-login-countries', 'llc_dnsbl_v6', array( 'LLC_Options_PAGE', 'dnsbl_validate' ) );

		// we add settings sections
		add_settings_section( 'llc-general', __( 'General Settings', 'limit-login-countries' ), array(
			'LLC_Options_Page',
			'general_settings_callback',
		), 'limit-login-countries' );

		// we add settings to our settings sections
		add_settings_field( 'llc_blacklist', __( 'Act as:', 'limit-login-countries' ), array(
			'LLC_Options_Page',
			'blacklist_callback',
		), 'limit-login-countries', 'llc-general', array( 'label_for' => 'llc_blacklist' ) );

		// we figure out the appropriate label
		if ( 'whitelist' === get_option( 'llc_blacklist', 'whitelist' ) ) {
			$label = __( 'Exclusive list of allowed countries:', 'limit-login-countries' );
		} else {
			$label = __( 'Exclusive list of rejected countries:', 'limit-login-countries' );
		}
		add_settings_field( 'llc_countries', $label, array(
			'LLC_Options_Page',
			'countries_callback',
		), 'limit-login-countries', 'llc-general', array( 'label_for' => 'llc_countries' ) );

		add_settings_field( 'llc_dnsbl', __( 'DNSBL:', 'limit-login-countries' ), array(
			'LLC_Options_Page',
			'dnsbl_callback',
		), 'limit-login-countries', 'llc-general', array( 'label_for' => 'llc_dnsbl' ) );
		add_settings_field( 'llc_dnsbl_v6', __( 'DNSBL (ipv6):', 'limit-login-countries' ), array(
			'LLC_Options_Page',
			'dnsbl_v6_callback',
		), 'limit-login-countries', 'llc-general', array( 'label_for' => 'llc_dnsbl_v6' ) );
	}

	public static function general_settings_callback() {

		$r = '<p>' . __( 'Here you configure from which countries admin area logins are allowed.', 'limit-login-countries' ) . '</p>';
		$r .= '<p><em>' . sprintf( __( '<strong>Remember:</strong> In case you lock yourself out of WP\'s admin area you can disable the country check by adding %s to your <code>wp-config.php</code> file.', 'limit-login-countries' ), '<code>define(\'LIMIT_LOGIN_COUNTRIES_OVERRIDE\', TRUE);</code>' ) . '</em></p>';

		echo $r;
	}

	public static function blacklist_callback() {

		$s = get_option( 'llc_blacklist', 'whitelist' );

		if ( 'whitelist' === $s ) {
			$ws = ' selected="selected"';
			$bs = '';
		} else {
			$ws = '';
			$bs = ' selected="selected"';
		}

		$r = '';

		$r .= '<select id="llc_blacklist" name="llc_blacklist">';
		$r .= '<option value="whitelist"' . $ws . '>' . __( 'Whitelist', 'limit-login-countries' ) . '</option>';
		$r .= '<option value="blacklist"' . $bs . '>' . __( 'Blacklist', 'limit-login-countries' ) . '</option>';
		$r .= '</select>';

		$r .= '<ul>';
		$r .= '<li>' . __( '<em>Whitelist</em> means login is allowed from the countries listed below only.', 'limit-login-countries' ) . '</li>';
		$r .= '<li>' . __( '<em>Blacklist</em> means login is not allowed from the countries listed below only.', 'limit-login-countries' ) . '</li>';
		$r .= '</ul>';

		echo $r;
	}

	public static function blacklist_validate( $input ) {

		$output = get_option( 'llc_blacklist', 'whitelist' );
		if ( 'whitelist' === $input or 'blacklist' === $input ) {
			$output = $input;
		} else {
			add_settings_error( 'llc_blacklist', 'llc-invalid-value', __( 'Invalid value. You must select either whitelist or blacklist.', 'limit-login-countries' ) );
		}

		return $output;
	}

	public static function countries_callback() {

		$setting = esc_attr( get_option( 'llc_countries' ) );
		$r  = "<input type='text' id='llc_countries' name='llc_countries' value='$setting'>";
		$r .= "<div id='llc_test' />";

		$r .= '<ul>';
		$r .= '<li>' . __( 'List of 2-digit country codes.', 'limit-login-countries' ) . '</li>';
		$r .= '<li class="no-js">' . __( 'Use a comma as delimiter.', 'limit-login-countries' ) . '</li>';
		$r .= '<li>' . __( 'If list is empty, no login restriction applies.', 'limit-login-countries' ) . '</li>';
		$r .= '</ul>';

		echo $r;

		$llc_countries_label['whitelist'] = __( 'Exclusive list of allowed countries:', 'limit-login-countries' );
		$llc_countries_label['blacklist'] = __( 'Exclusive list of rejected countries:', 'limit-login-countries' );
		wp_localize_script( 'limit-login-countries', 'LLC_COUNTRIES_LABEL', $llc_countries_label );

		require_once( dirname( dirname( __DIR__ ) ) . '/includes/LLC-GeoIP-Countries.class.php' );
		$gc = new LLC_GeoIP_Countries();
		$gc->wp_localize_country_codes();
	}

	public static function countries_validate( $input ) {

		$countries = array_unique( explode( ',', trim( strtoupper( preg_replace( '/[^,a-zA-Z]/', '', $input ) ), ',' ) ) );
		require_once( dirname( dirname( __DIR__ ) ) . '/includes/LLC-GeoIP-Countries.class.php' );
		$gc = new LLC_GeoIP_Countries();

		$output = array_filter( $countries, function ( $var ) use ( $gc ) {
			return in_array( $var, $gc->country_codes );
		} );

		return implode( ',', $output );
	}

	public static function dnsbl_validate( $input ) {
		// TODO
		return $input;
	}

	public static function dnsbl_callback() {
		//$dnsbl = esc_attr(get_option( 'llc_dnsbl', 'zz.countries.nerd.dk' )); zz.countries.nerd.dk returns "UK" instead of "GB"
		$dnsbl = esc_attr(get_option( 'llc_dnsbl', 'all.ascc.dnsbl.bit.nl' ));
		echo '<input id=llc_dnsbl name=llc_dnsbl type=text value="'.$dnsbl.'" size=60>';
	}

	public static function dnsbl_v6_callback() {
		$dnsbl = esc_attr(get_option( 'llc_dnsbl_v6', 'all.v6.ascc.dnsbl.bit.nl' ));
		echo '<input id=llc_dnsbl_v6 name=llc_dnsbl_v6 type=text value="'.$dnsbl.'" size=60>';
	}



	/**
	 * Adds our options page to the admin area.
	 * Callback function for WP's hook 'admin_menu'.
	 *
	 * @see   LLC_Admin::__construct()
	 * @since 0.3
	 */
	public static function settings_menu() {

		add_options_page(
			__( 'Limit Login Countries Options', 'limit-login-countries' ),
			// translators: this is the menu title for the plugin's settings page in the WordPress admin area.
			__( 'Login Countries', 'limit-login-countries' ),
			'manage_options',
			'limit-login-countries',
			array( 'LLC_Options_Page', 'settings_page' )
		);
	}

	/**
	 * Prints the actual settings page.
	 * Callback function for add_option_page
	 *
	 * @see   LLC_Options_Page::settings_menu()
	 * @since 0.3
	 *
	 * @return bool
	 */
	public static function settings_page() {

		// we make sure the current user has sufficient capabilities to fiddle with our options
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'limit-login-countries' ) );
		}?>

		<div class="wrap" id="llc-options-page">
			<div id="icon-options-general" class="icon32"><br></div><?php // The icon is outdated with MP6 in WP 4.0 but we keep it for backwards compatibility. ?>
			<h2><?php
				echo __( 'Settings', 'limit-login-countries' ) . '&nbsp;&rsaquo;&nbsp;';
				// translators: This translation of the plugin name is used as the title of the plugin's settings page in the WordPress Admin area
				echo __( 'Limit Login Countries (DNSBL)', 'limit-login-countries' );
				?></h2>
			<form action="<?php echo admin_url( 'options.php' ); ?>" method="post"><?php
				settings_fields( 'limit-login-countries' );
				do_settings_sections( 'limit-login-countries' );
				submit_button();
				?>
			</form>
		</div>
		<?php
		return true;
	}
}
