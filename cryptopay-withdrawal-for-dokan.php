<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

// @phpcs:disable PSR1.Files.SideEffects
// @phpcs:disable PSR12.Files.FileHeader
// @phpcs:disable Generic.Files.LineLength
// @phpcs:disable WordPress.Security.NonceVerification.Recommended

/**
 * Plugin Name: CryptoPay Withdrawal for Dokan
 * Version:     1.0.8
 * Plugin URI:  https://beycanpress.com/cryptopay/
 * Description: Add custom cryptocurrency withdrawal method to Dokan plugin
 * Author:      BeycanPress LLC
 * Author URI:  https://beycanpress.com
 * License:     GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: cryptopay-withdrawal-for-dokan
 * Tags: Cryptopay, Cryptocurrency, WooCommerce, WordPress, MetaMask, Trust, Binance, Wallet, Ethereum, Bitcoin, Binance smart chain, Payment, Plugin, Gateway, Moralis, Converter, API, coin market cap, CMC
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 8.1
 */

use BeycanPress\CryptoPay\Loader;
use BeycanPress\CryptoPay\Helpers;
use BeycanPress\CryptoPayLite\Loader as LiteLoader;

define('DOKAN_CRYPTOPAY_FILE', __FILE__);
define('DOKAN_CRYPTOPAY_VERSION', '1.0.8');
define('DOKAN_CRYPTOPAY_URL', plugin_dir_url(__FILE__));
define('DOKAN_CRYPTOPAY_PATH', plugin_dir_path(__FILE__));

add_action('init', function (): void {
    load_plugin_textdomain('cryptopay-withdrawal-for-dokan', false, basename(__DIR__) . '/languages');
});

add_action('plugins_loaded', function (): void {
    require_once __DIR__ . '/classes/DokanCryptoPayWithdrawal.php';

    if (!function_exists('dokan')) {
        add_action('admin_notices', function (): void {
            $class = 'notice notice-error';
            // translators: %s: Dokan plugin URL
            $message = sprintf(esc_html__('CryptoPay Withdrawal for Dokan: This plugin is an extra feature plugin so it cannot do anything on its own. It needs Dokan to work. You can buy download Dokan by %s.', 'cryptopay-withdrawal-for-dokan'), '<a href="' . admin_url('plugin-install.php?s=Dokan&tab=search&type=term') . '">' . esc_html__('clicking here', 'cryptopay-withdrawal-for-dokan') . '</a>');
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
        });
        return;
    }

    if ((class_exists(Loader::class) || class_exists(LiteLoader::class))) {
        if (class_exists(Loader::class)) {
            if (version_compare(Helpers::getProp('pluginVersion'), '2.1.0', '<')) {
                add_action('admin_notices', function (): void {
                    $class = 'notice notice-error';
                    $message = esc_html__('CryptoPay Withdrawal for Dokan requires CryptoPay version 2.1.0 or higher. Please update CryptoPay.', 'cryptopay-withdrawal-for-dokan');
                    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
                });
            } else {
                $gateway = new DokanCryptoPayWithdrawal('CryptoPay', 'dokan_cryptopay');
            }
        } elseif (class_exists(LiteLoader::class)) {
            $gateway = new DokanCryptoPayWithdrawal('CryptoPay Lite', 'dokan_cryptopay_lite');
        }

        // This a WordPress page detection
        if (isset($_GET['page']) && 'dokan' === $_GET['page']) {
            add_action('admin_enqueue_scripts', function (): void {
                wp_enqueue_script('dokan-cryptopay', plugin_dir_url(__FILE__) . 'assets/js/admin.js', ['jquery', 'wp-i18n'], DOKAN_CRYPTOPAY_VERSION, true);
                wp_localize_script('dokan-cryptopay', 'DokanCryptoPay', [
                    'currency' => get_woocommerce_currency(),
                    'apiUrl' => home_url('/wp-json/dokan/v1/withdraw/'),
                    'key' => class_exists(Loader::class) ? 'dokan_cryptopay' : 'dokan_cryptopay_lite',
                    'title' => class_exists(Loader::class) ? 'CryptoPay' : 'CryptoPay Lite',
                ]);
            });
            add_action('admin_footer', function () use ($gateway): void {
                echo wp_kses(
                    $gateway->runCryptoPay(),
                    [
                        'div' => [
                            'id' => [],
                            'class' => [],
                            'style' => [],
                            'data-*' => [],
                            'data-loading' => []
                        ],
                    ]
                );
            });
        }
    } else {
        add_action('admin_notices', function (): void {
            $class = 'notice notice-error';
            // translators: %s: CryptoPay plugin URL
            $message = sprintf(esc_html__('CryptoPay Withdrawal for Dokan: This plugin is an extra feature plugin so it cannot do anything on its own. It needs CryptoPay to work. You can buy CryptoPay by %s.', 'cryptopay-withdrawal-for-dokan'), '<a href="https://beycanpress.com/product/cryptopay-all-in-one-cryptocurrency-payments-for-wordpress/?utm_source=wp_org_addons&utm_medium=dokan" target="_blank">' . esc_html__('clicking here', 'cryptopay-withdrawal-for-dokan') . '</a>');
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
        });
    }
});
