<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

// @phpcs:disable PSR1.Files.SideEffects
// @phpcs:disable Generic.Files.InlineHTML
// @phpcs:disable Generic.Files.LineLength
// @phpcs:disable WordPress.Security.NonceVerification.Recommended

use BeycanPress\CryptoPay\Helpers;
use BeycanPress\CryptoPay\Payment;
use BeycanPress\CryptoPay\PluginHero\Hook;
// Lite
use BeycanPress\CryptoPayLite\Settings\EvmChains;
use BeycanPress\CryptoPayLite\Helpers as LiteHelpers;
use BeycanPress\CryptoPayLite\Payment as LitePayment;
use BeycanPress\CryptoPayLite\PluginHero\Hook as LiteHook;

// @phpcs:ignore
class DokanCryptoPayWithdrawal
{
    /**
     * @var string
     */
    private string $title;

    /**
     * @var string
     */
    private string $key;

    /**
     * @var array<mixed>|null
     */
    private ?array $networks = null;

    /**
     * @var array<mixed>|null
     */
    private ?array $currentNetwork = null;

    /**
     * @param string $title
     * @param string $key
     */
    public function __construct(string $title, string $key)
    {
        $this->key = $key;
        $this->title = $title;

        // Filters
        add_filter('dokan_withdraw_methods', [$this, 'addWithdrawMethod']);
        add_filter('dokan_withdraw_method_icon', [$this, 'addMethodIcon'], 10, 2);
        add_filter('dokan_store_profile_settings_args', [$this, 'saveSettings'], 10, 2);
        add_filter('dokan_withdraw_withdrawable_payment_methods', [$this, 'withdrawPaymentMethods']);
        add_filter('dokan_get_seller_active_withdraw_methods', [$this, 'activePaymentMethods'], 10, 2);
        add_filter('dokan_payment_settings_required_fields', [$this, 'addWithdrawInPaymentMethodList'], 10, 2);
        add_filter('dokan_withdraw_method_additional_info', [$this, 'addWithdrawMethodAdditionalInfo'], 10, 2);

        if ('dokan_cryptopay' == $this->key) {
            Helpers::registerIntegration($this->key);
            Hook::addFilter('mode_' . $this->key, fn () => 'network');
            Hook::addFilter('apply_discount_' . $this->key, '__return_false');
            Hook::addFilter('receiver_' . $this->key, function (string $receiver, object $data) {
                if ($data->getParams()->get('receiver')) {
                    return $data->getParams()->get('receiver');
                }

                return $receiver;
            }, 10, 2);
        } else {
            LiteHelpers::registerIntegration($this->key);
            LiteHook::addFilter('mode_' . $this->key, fn () => 'network');
            LiteHook::addFilter('apply_discount_' . $this->key, '__return_false');
            LiteHook::addFilter('receiver_' . $this->key, function (string $receiver, object $data) {
                if ($data->getParams()->get('receiver')) {
                    return $data->getParams()->get('receiver');
                }

                return $receiver;
            }, 10, 2);
        }
    }

    /**
     * @param array<string,array<string,mixed>> $methods
     * @return array<string,array<string,mixed>>
     */
    public function addWithdrawMethod(array $methods): array
    {
        $methods[$this->key] = [
            'title'    => $this->title,
            'callback' => [$this, 'userSettingForm'],
        ];

        return $methods;
    }

    /**
     * @param string $methodIcon
     * @param string $methodKey
     * @return string
     */
    public function addMethodIcon(string $methodIcon, string $methodKey): string
    {
        if ($methodKey == $this->key) {
            $methodIcon = DOKAN_CRYPTOPAY_URL . 'assets/images/icon.svg';
        }

        return $methodIcon;
    }

    /**
     * @param object $network
     * @param array<mixed> $networkItem
     * @return boolean
     */
    private function isSelected(object $network, array $networkItem): bool
    {
        if ($this->currentNetwork) {
            return false;
        }

        if (!isset($network->code)) {
            $this->currentNetwork = $networkItem;
            return false;
        }

        if ('evmchains' == $networkItem['code']) {
            $res = $networkItem['id'] == $network->id;
        } else {
            $res = $networkItem['code'] == $network->code;
        }

        if ($res) {
            $this->currentNetwork = $networkItem;
        }

        return $res;
    }

    /**
     * @return array<mixed>
     */
    private function getNetworksById(): array
    {
        if (LiteHelpers::getTestnetStatus()) {
            $networks = file_get_contents(DOKAN_CRYPTOPAY_PATH . '/resources/testnets.json');
        } else {
            $networks = file_get_contents(DOKAN_CRYPTOPAY_PATH . '/resources/mainnets.json');
        }

        $networks = json_decode($networks, true);

        return array_filter($networks, function ($network) {
            return in_array($network['id'], EvmChains::getNetworks());
        });
    }

    /**
     * @param mixed $args
     * @return void
     */
    public function userSettingForm(mixed $args): void
    {
        if (!is_array($args)) {
            $args = [];
        }

        $settings = isset($args['payment'][$this->key]) ? $args['payment'][$this->key] : [];
        $network = isset($settings['network']) ? json_decode($settings['network']) : (object) [];
        $currency = isset($settings['currency']) ? json_decode($settings['currency']) : (object) [];
        $address = isset($settings['address']) ? $settings['address'] : '';

        if ('dokan_cryptopay' == $this->key) {
            $this->networks = Helpers::getNetworks()->toArray();
        } else {
            $this->networks = $this->getNetworksById();
        }

        wp_enqueue_script('dokan-cryptopay', plugin_dir_url(__FILE__) . 'assets/js/main.js', ['jquery'], DOKAN_CRYPTOPAY_VERSION, true);

        ?>
            <div class="dokan-form-group">
                <div>
                    <label>
                        <?php esc_html_e('Payment network', 'cryptopay-withdrawal-for-dokan'); ?>
                    </label>
                </div>
                <div class="dokan-w12">
                    <select name="settings[<?php echo esc_attr($this->key) ?>][network]" class="dokan-form-control dokan-cryptopay-network">
                        <?php foreach ($this->networks as $networkItem) : ?>
                            <option value='<?php echo wp_json_encode($networkItem) ?>' <?php echo esc_attr($this->isSelected($network, $networkItem) ? 'selected' : ''); ?>>
                                <?php echo esc_html($networkItem['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="dokan-form-group">
                <div>
                    <label>
                        <?php esc_html_e('Payment currency', 'cryptopay-withdrawal-for-dokan'); ?>
                    </label>
                </div>
                <div class="dokan-w12">
                    <select name="settings[<?php echo esc_attr($this->key) ?>][currency]" class="dokan-form-control dokan-cryptopay-currency">
                        <?php
                        if (!$this->currentNetwork) {
                            $this->currentNetwork = $this->networks[0];
                        }
                        foreach ($this->currentNetwork['currencies'] as $currencyItem) : ?>
                            <option value='<?php echo wp_json_encode($currencyItem) ?>' <?php echo esc_attr(isset($currency->symbol) && $currencyItem['symbol'] == $currency->symbol ? 'selected' : ''); ?>>
                                <?php echo esc_html($currencyItem['symbol']) ?>
                            </option>
                        <?php endforeach;
                        ?>
                    </select>
                </div>
            </div>
            <div class="dokan-form-group">
                <div>
                    <label>
                        <?php esc_html_e('Address', 'cryptopay-withdrawal-for-dokan'); ?>
                    </label>
                </div>
                <div class="dokan-w12">
                    <input value="<?php echo esc_attr($address); ?>" name="settings[<?php echo esc_attr($this->key) ?>][address]" class="dokan-form-control" type="text">
                </div>
            </div>
        <?php
    }

    /**
     * @param array<mixed> $dokanSettings
     * @param string $storeId
     * @return array<mixed>
     */
    public function saveSettings(array $dokanSettings, string $storeId): array
    {
        /** @var array $postData */
        $postData = wp_unslash($_POST);
        if (wp_verify_nonce($postData['_wpnonce'], 'dokan_payment_settings_nonce')) {
            if (isset($postData['settings'][$this->key])) {
                $dokanSettings['payment'][$this->key] = array_map('sanitize_text_field', $postData['settings'][$this->key]);
                $network = json_decode($dokanSettings['payment'][$this->key]['network']);
                $network->currencies = [json_decode($dokanSettings['payment'][$this->key]['currency'])];
                $dokanSettings['payment'][$this->key]['network'] = wp_json_encode($network);
                $dokanSettings['payment'][$this->key]['user_id'] = dokan_get_current_user_id();
            }
        }

        return $dokanSettings;
    }

    /**
     * @param array<string> $activePaymentMethods
     * @param int $vendorId
     * @return array<string>
     */
    public function activePaymentMethods(array $activePaymentMethods, int $vendorId): array
    {
        $settings = get_user_meta($vendorId ? $vendorId : dokan_get_current_user_id(), 'dokan_profile_settings');
        $settings = isset($settings[0]['payment'][$this->key]) ? $settings[0]['payment'][$this->key] : [];

        if (isset($settings['address']) && !empty($settings['address'])) {
            array_push($activePaymentMethods, $this->key);
        }

        return $activePaymentMethods;
    }

    /**
     * @param array<string> $paymentMethods
     * @return array<string>
     */
    public function withdrawPaymentMethods(array $paymentMethods): array
    {
        $settings = get_user_meta(dokan_get_current_user_id(), 'dokan_profile_settings');
        $settings = isset($settings[0]['payment'][$this->key]) ? $settings[0]['payment'][$this->key] : [];

        if (isset($settings['address']) && !empty($settings['address'])) {
            return array_merge($paymentMethods, [$this->key]);
        }

        return $paymentMethods;
    }

    /**
     * @param array<string> $requiredFields
     * @param string $paymentMethodId
     * @return array<string>
     */
    public function addWithdrawInPaymentMethodList(array $requiredFields, string $paymentMethodId): array
    {
        if ($this->key == $paymentMethodId) {
            $requiredFields = ['address'];
        }

        return $requiredFields;
    }

    /**
     * @param string $methodInfo
     * @param string $methodKey
     * @return string
     */
    public function addWithdrawMethodAdditionalInfo(string $methodInfo, string $methodKey): string
    {
        if ($this->key == $methodKey) {
            $settings       = get_user_meta(dokan_get_current_user_id(), 'dokan_profile_settings');
            $paymentMethods = !empty($settings[0]['payment']) ? $settings[0]['payment'] : [];
            $cryptoPay      = isset($paymentMethods[$methodKey]) ? $paymentMethods[$methodKey] : [];
            if (isset($cryptoPay['network'])) {
                $network    = json_decode($cryptoPay['network'], true);
                $methodInfo = empty($cryptoPay['address']) ? '' : sprintf('( %1$s - %2$s )', $network['name'], $cryptoPay['address']);
            }
        }

        return $methodInfo;
    }

    /**
     * @return string
     */
    public function runCryptoPay(): string
    {
        if ('dokan_cryptopay' == $this->key) {
            return (new Payment($this->key))->setConfirmation(false)->modal();
        } else {
            return (new LitePayment($this->key))->setConfirmation(false)->modal();
        }
    }
}
