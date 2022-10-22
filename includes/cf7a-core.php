<?php

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    CF7_AntiSpam
 * @subpackage CF7_AntiSpam/includes
 * @author     Codekraft Studio <info@codekraft.it>
 */
class CF7_AntiSpam {

	/**
	 * The ID of this plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * The options of this plugin.
	 *
	 * @since    0.1.0
	 * @access   public
	 * @var      array    $options    options of this plugin.
	 */
	private $options;


	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    0.1.0
	 * @access   protected
	 * @var      CF7_AntiSpam_Loader   $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The constructor function is called when the plugin is loaded. It sets the version, plugin name, and options. It loads
	 * the dependencies, sets the locale, updates the plugin, and loads the admin and frontend areas
	 */
	public function __construct() {
		if ( defined( 'CF7ANTISPAM_VERSION' ) ) {
			$this->version = CF7ANTISPAM_VERSION;
		} else {
			$this->version = '0.0.1';
		}

		$this->plugin_name = CF7ANTISPAM_NAME;
		$this->options     = $this->get_options(); /* the plugin options */

		/* the php files */
		$this->load_dependencies();

		/* the i18n */
		$this->set_locale();

		if ( empty( $this->options['cf7a_version'] ) || $this->version !== $this->options['cf7a_version'] ) {

			/* the php files */
			$this->update();

			if ( get_transient( 'cf7a_activation' ) ) {
				if ( defined( 'FLAMINGO_VERSION' ) ) {
					$filters = new CF7_AntiSpam_filters();
					$filters->cf7a_flamingo_on_install();
				}
				delete_transient( 'cf7a_activation' );
			}
		}

		/* the admin area */
		$this->load_admin();

		/* the frontend area */
		$this->load_frontend();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the CF7_AntiSpam_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    0.2.4
	 * @access   private
	 */
	private function update() {

		require_once CF7ANTISPAM_PLUGIN_DIR . '/includes/cf7a-activator.php';
		do_action( 'cf7a_update' );
		CF7_AntiSpam_Activator::update_options();

	}

	/**
	 * It loads the plugin's dependencies
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once CF7ANTISPAM_PLUGIN_DIR . '/includes/cf7a-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once CF7ANTISPAM_PLUGIN_DIR . '/includes/cf7a-i18n.php';

		/**
		 * The class responsible for defining frontend functionality
		 * of the plugin.
		 */
		require_once CF7ANTISPAM_PLUGIN_DIR . '/includes/cf7a-frontend.php';

		/**
		 * The class responsible for defining antispam functionality and the related Geo-ip utility
		 * of the plugin.
		 */
		require_once CF7ANTISPAM_PLUGIN_DIR . '/includes/cf7a-antispam.php';
		require_once CF7ANTISPAM_PLUGIN_DIR . '/includes/cf7a-antispam-geoip.php';

		/**
		 * The class responsible for defining admin backend functionality
		 * of the plugin.
		 */
		require_once CF7ANTISPAM_PLUGIN_DIR . '/admin/admin-display.php';
		require_once CF7ANTISPAM_PLUGIN_DIR . '/admin/admin-tools.php';
		require_once CF7ANTISPAM_PLUGIN_DIR . '/admin/admin.php';

		$this->loader = new CF7_AntiSpam_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the CF7_AntiSpam_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    0.1.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new CF7_AntiSpam_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 */
	private function load_admin() {

		/* the spam filter */
		$plugin_antispam = new CF7_AntiSpam_filters();

		/* the spam filter */
		add_filter( 'wpcf7_spam', array( $plugin_antispam, 'cf7a_spam_filter' ), 8, 1 );

		if ( defined( 'FLAMINGO_VERSION' ) ) {
			/* if flamingo is defined the mail will be analyzed after flamingo has stored */
			add_action( 'wpcf7_after_flamingo', array( $plugin_antispam, 'cf7a_flamingo_store_additional_data' ), 11, 1 );
			/* remove honeypot fields before store into database */
			add_action( 'wpcf7_after_flamingo', array( $plugin_antispam, 'cf7a_flamingo_remove_honeypot' ), 12, 1 );
		}

		if ( is_admin() ) {

			/* the GeoIP2 database */
			new CF7_Antispam_Geoip();

			/* the admin area */
			$plugin_admin = new CF7_AntiSpam_Admin( $this->get_plugin_name(), $this->get_version() );

			$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
			$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

			/* if flamingo is enabled use submitted spam / ham to feed d8 */
			if ( defined( 'FLAMINGO_VERSION' ) ) {
				add_action( 'load-flamingo_page_flamingo_inbound', array( $plugin_antispam, 'cf7a_d8_flamingo_classify' ), 9, 0 );
				add_filter( 'manage_flamingo_inbound_posts_columns', array( $plugin_antispam, 'flamingo_columns' ) );
				add_action( 'manage_flamingo_inbound_posts_custom_column', array( $plugin_antispam, 'flamingo_d8_column' ), 10, 2 );
				add_action( 'manage_flamingo_inbound_posts_custom_column', array( $plugin_antispam, 'flamingo_resend_column' ), 11, 2 );
			}
		}
	}

	/**
	 * Register all the hooks related to the frontend area functionality
	 * of the plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 */
	private function load_frontend() {
		if ( ! is_admin() ) {
			$plugin_frontend = new CF7_AntiSpam_Frontend( $this->get_plugin_name(), $this->get_version() );

			$this->loader->add_action( 'wp_footer', $plugin_frontend, 'enqueue_scripts' );

		}
	}

	/**
	 * Run the loader to execute all the hooks with WordPress.
	 *
	 * @since    0.1.0
	 */
	public function run() {
		if ( defined( 'WPCF7_VERSION' ) ) {
			$this->loader->run();
		} else {
			add_action(
				'admin_notices',
				function () {
					printf(
						'<div class="notice notice-info"><p>%s<a href="%s">%s</a>%s</p></div>',
						esc_html__( 'CF7 AntiSpam need ', 'cf7-antispam' ),
						esc_url( 'https://wordpress.org/plugins/contact-form-7/' ),
						esc_html__( 'Contact Form 7', 'cf7-antispam' ),
						esc_html__( ' installed and enabled in order to work.', 'cf7-antispam' )
					);
				}
			);
		}
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    CF7_AntiSpam_Loader Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * CF7 AntiSpam options
	 *
	 * @return array the plugin options
	 */
	public static function get_options() {
		return get_option( 'cf7a_options' );
	}

	/**
	 * CF7 AntiSpam update options function
	 *
	 * @since 0.4.0
	 *
	 * @param  array $options the plugin options.
	 * @return bool
	 */
	public static function update_options( $options ) {
		return update_option( 'cf7a_options', $options );
	}

	/**
	 * CF7 AntiSpam update a function to a single option
	 *
	 * @since 0.4.0
	 *
	 * @param string $option the option that you need to change.
	 * @param mixed  $value the new option value.
	 *
	 * @return bool
	 */
	public static function update_option( $option, $value ) {

		$options = self::get_options();

		if ( isset( $options[ $option ] ) ) {
			if ( is_string( $value ) ) {
				/* if the value is a string sanitize and replace the option */
				$options[ $option ] = sanitize_text_field( $value );
			} elseif ( is_array( $value ) ) {
				/* if the value is an array sanitize each element then merge into option */
				$new_values = array();
				foreach ( $value as $array_value ) {
					$new_values[] = sanitize_text_field( trim( (string) $array_value ) );
				}
				$options[ $option ] = array_unique( array_merge( $options[ $option ], $new_values ) );
			}

			return self::update_options( $options );
		}

		return false;
	}
}
