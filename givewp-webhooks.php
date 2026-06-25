<?php
/**
 * Plugin Name:       GiveWP Webhooks
 * Plugin URI:        https://github.com/your-org/givewp-webhooks-addon
 * Description:       Sends signed outbound webhooks to configured endpoints whenever a donation is completed in GiveWP.
 * Version:           @projectVersion@
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Your Org
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       givewp-webhooks
 * Domain Path:       /languages
 *
 * @package GiveWebhooks
 */

namespace GiveWebhooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'GIVE_WEBHOOKS_VERSION', '@projectVersion@' );
define( 'GIVE_WEBHOOKS_FILE', __FILE__ );
define( 'GIVE_WEBHOOKS_DIR', plugin_dir_path( __FILE__ ) );
define( 'GIVE_WEBHOOKS_URL', plugin_dir_url( __FILE__ ) );
define( 'GIVE_WEBHOOKS_BASENAME', plugin_basename( __FILE__ ) );

// Minimum GiveWP core version required (depends on Give\Donations\Models\Donation + give_complete_donation hook).
define( 'GIVE_WEBHOOKS_MIN_GIVE_VERSION', '2.20.0' );

require_once GIVE_WEBHOOKS_DIR . 'includes/class-bootstrap.php';
require_once GIVE_WEBHOOKS_DIR . 'includes/class-activation.php';

/**
 * Activation: only runs the actual table/secret setup if GiveWP is active & compatible;
 * otherwise we still allow activation but Bootstrap::init() will self-deactivate with a notice
 * (deactivating from inside register_activation_hook is unreliable, so we defer the real check
 * to plugins_loaded and bail out gracefully there).
 */
register_activation_hook( __FILE__, array( 'GiveWebhooks\\Activation', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GiveWebhooks\\Activation', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'GiveWebhooks\\Bootstrap', 'init' ), 20 );
