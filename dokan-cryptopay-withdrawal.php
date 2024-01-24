<?php

declare(strict_types=1);

// @phpcs:disable PSR1.Files.SideEffects
// @phpcs:disable PSR12.Files.FileHeader
// @phpcs:disable Generic.Files.LineLength

/**
 * Plugin Name: Dokan - CryptoPay Withdrawal
 * Version:     1.0.1
 * Plugin URI:  https://beycanpress.com/cryptopay
 * Description: Add custom cryptocurrency withdrawal method to Dokan plugin
 * Author:      BeycanPress LLC
 * Author URI:  https://beycanpress.com
 * License:     GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: dokan-cryptopay
 * Tags: Cryptopay, Cryptocurrency, WooCommerce, WordPress, MetaMask, Trust, Binance, Wallet, Ethereum, Bitcoin, Binance smart chain, Payment, Plugin, Gateway, Moralis, Converter, API, coin market cap, CMC
 * Requires at least: 5.0
 * Tested up to: 6.4.2
 * Requires PHP: 8.1
*/

use BeycanPress\CryptoPay\Loader;
use BeycanPress\CryptoPay\Helpers;
use BeycanPress\CryptoPayLite\Loader as LiteLoader;

define('DOKAN_CRYPTOPAY_FILE', __FILE__);
define('DOKAN_CRYPTOPAY_VERSION', '1.0.1');
define('DOKAN_CRYPTOPAY_URL', plugin_dir_url(__FILE__));

add_action('plugins_loaded', function (): void {
    require_once __DIR__ . '/classes/DokanCryptoPayWithdrawal.php';
    load_plugin_textdomain('dokan-cryptopay', false, basename(__DIR__) . '/languages');

    if (!function_exists('dokan')) {
        add_action('admin_notices', function (): void {
            $class = 'notice notice-error';
            $message = sprintf(esc_html__('Dokan - CryptoPay Withdrawal: This plugin is an extra feature plugin so it cannot do anything on its own. It needs Dokan to work. You can buy download Dokan by %s.', 'dokan-cryptopay'), '<a href="https://wordpress.org/plugins/dokan-lite/" target="_blank">' . esc_html__('clicking here', 'dokan-cryptopay') . '</a>');
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
        });
        return;
    }

    if ((class_exists(Loader::class) || class_exists(LiteLoader::class))) {
        if (class_exists(Loader::class)) {
            if (version_compare(Helpers::getProp('pluginVersion'), '2.1.0', '<')) {
                add_action('admin_notices', function (): void {
                    $class = 'notice notice-error';
                    $message = esc_html__('Dokan - CryptoPay Withdrawal requires CryptoPay version 2.1.0 or higher. Please update CryptoPay.', 'cryptopay');
                    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
                });
            } else {
                $gateway = new DokanCryptoPayWithdrawal(esc_html__('CryptoPay', 'dokan-cryptopay'), 'cryptopay');
            }
        } elseif (class_exists(LiteLoader::class)) {
            $gateway = new DokanCryptoPayWithdrawal(esc_html__('CryptoPay Lite', 'dokan-cryptopay'), 'cryptopay_lite');
        }

        if (isset($_GET['page']) && $_GET['page'] === 'dokan') {
            add_action('admin_enqueue_scripts', function (): void {
                wp_enqueue_script('dokan-cryptopay', plugin_dir_url(__FILE__) . 'assets/js/main.js', ['jquery'], DOKAN_CRYPTOPAY_VERSION, true);
                wp_localize_script('dokan-cryptopay', 'DokanCryptoPay', [
                    'currency' => get_woocommerce_currency(),
                    'apiUrl' => home_url('/wp-json/dokan/v1/withdraw/')
                ]);

                wp_enqueue_style('dokan-cryptopay', plugin_dir_url(__FILE__) . 'assets/css/main.css', [], DOKAN_CRYPTOPAY_VERSION);
            });
            add_action('admin_footer', function () use ($gateway): void {
                echo '
                    <div class="dokan-cryptopay-modal">
                        <div class="dokan-cryptopay-modal-content">
                            ' . $gateway->runCryptoPay() . '
                        </div>
                    </div>
                ';
            });
        }
    } else {
        add_action('admin_notices', function (): void {
            $class = 'notice notice-error';
            $message = sprintf(esc_html__('Dokan - CryptoPay Withdrawal: This plugin is an extra feature plugin so it cannot do anything on its own. It needs CryptoPay to work. You can buy CryptoPay by %s.', 'dokan-cryptopay'), '<a href="https://beycanpress.com/product/cryptopay-all-in-one-cryptocurrency-payments-for-wordpress/?utm_source=wp_org_addons&utm_medium=dokan" target="_blank">' . esc_html__('clicking here', 'dokan-cryptopay') . '</a>');
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
        });
    }
});
