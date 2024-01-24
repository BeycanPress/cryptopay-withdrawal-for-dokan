<?php

declare(strict_types=1);

// @phpcs:disable Generic.Files.InlineHTML
// @phpcs:disable Generic.Files.LineLength

use BeycanPress\CryptoPay\Helpers;
use BeycanPress\CryptoPay\Payment;
use BeycanPress\CryptoPay\PluginHero\Hook;
use BeycanPress\CryptoPayLite\Services as LiteServices;
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
     * @var array<mixed>
     */
    private array $currentNetwork;

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
        add_filter('dokan_get_seller_active_withdraw_methods', [$this, 'activePaymentMethods']);
        add_filter('dokan_withdraw_withdrawable_payment_methods', [$this, 'withdrawPaymentMethods']);
        add_filter('dokan_payment_settings_required_fields', [$this, 'addWithdrawInPaymentMethodList'], 10, 2);
        add_filter('dokan_withdraw_method_additional_info', [$this, 'addWithdrawMethodAdditionalInfo'], 10, 2);

        // Actions
        if (isset($_GET['page']) && $_GET['page'] == 'dokan') {
            add_action('admin_print_footer_scripts', [$this, 'withdrawDetails'], 99);
        }

        if ($this->key == 'cryptopay') {
            Helpers::registerIntegration($this->key);
            Hook::addFilter('apply_discount_' . $this->key, '__return_false');
            Hook::addFilter('receiver_' . $this->key, function (string $receiver, object $data) {
                return $data->params->dokanCrytpoPayDetails->address;
            }, 10, 2);
        } else {
            LiteServices::registerAddon($this->key);
            LiteHook::addFilter('apply_discount_' . $this->key, '__return_false');
            LiteHook::addFilter('receiver_' . $this->key, function (string $receiver, object $data) {
                return $data->params->dokanCrytpoPayDetails->address;
            }, 10, 2);
        }
    }

    /**
     * @param array<string,array<string,mixed>> $methods
     * @return array<string,array<string,mixed>>
     */
    public function addWithdrawMethod(array $methods): array
    {
        $methods[$this->key] = array(
            'title'    => $this->title,
            'callback' => [$this, 'userSettingForm'],
        );

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

        if ($networkItem['code'] == 'evmBased') {
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
     * @param array<mixed> $args
     * @return void
     */
    public function userSettingForm(array $args): void
    {
        $settings = isset($args['payment'][$this->key]) ? $args['payment'][$this->key] : [];
        $network = isset($settings['network']) ? json_decode($settings['network']) : (object) [];
        $currency = isset($settings['currency']) ? json_decode($settings['currency']) : (object) [];
        $address = isset($settings['address']) ? $settings['address'] : '';

        if ($this->key == 'cryptopay') {
            $networks = Helpers::getNetworks()->toArray();
        } else {
            $networks = LiteServices::getNetworks();
        }

        ?>
            <div class="dokan-form-group">
                <div>
                    <label>
                        <?php esc_html_e('Payment network', 'dokan-cryptopay'); ?>
                    </label>
                </div>
                <div class="dokan-w12">
                    <select name="settings[<?php echo esc_attr($this->key) ?>][network]" class="dokan-form-control dokan-cryptopay-network">
                        <?php foreach ($networks as $networkItem) : ?>
                            <option value='<?php echo json_encode($networkItem) ?>' <?php echo $this->isSelected($network, $networkItem) ? 'selected' : ''; ?>>
                                <?php echo esc_html($networkItem['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="dokan-form-group">
                <div>
                    <label>
                        <?php esc_html_e('Payment currency', 'dokan-cryptopay'); ?>
                    </label>
                </div>
                <div class="dokan-w12">
                    <select name="settings[<?php echo esc_attr($this->key) ?>][currency]" class="dokan-form-control dokan-cryptopay-currency">
                        <?php foreach ($this->currentNetwork['currencies'] as $currencyItem) : ?>
                            <option value='<?php echo json_encode($currencyItem) ?>' <?php echo isset($currency->symbol) && $currencyItem['symbol'] == $currency->symbol ? 'selected' : ''; ?>>
                                <?php echo esc_html($currencyItem['symbol']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="dokan-form-group">
                <div>
                    <label>
                        <?php esc_html_e('Address', 'dokan-cryptopay'); ?>
                    </label>
                </div>
                <div class="dokan-w12">
                    <input value="<?php echo esc_attr($address); ?>" name="settings[<?php echo esc_attr($this->key) ?>][address]" class="dokan-form-control" type="text">
                </div>
            </div>
            <script>
                jQuery(document).on('change', '.dokan-cryptopay-network', function(e) {
                    let currencies = JSON.parse(jQuery(this).val()).currencies;
                    jQuery('.dokan-cryptopay-currency').html(`
                        ${currencies.map(currency => `<option value='${JSON.stringify(currency)}'>${currency.symbol}</option>`).join('')}
                    `);
                });
            </script>
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
                $dokanSettings['payment'][$this->key]['network'] = json_encode($network);
                $dokanSettings['payment'][$this->key]['user_id'] = dokan_get_current_user_id();
            }
        }

        return $dokanSettings;
    }

    /**
     * @param array<string> $activePaymentMethods
     * @return array<string>
     */
    public function activePaymentMethods(array $activePaymentMethods): array
    {
        $settings = get_user_meta(dokan_get_current_user_id(), 'dokan_profile_settings');
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
     * @return void
     */
    public function withdrawDetails(): void
    {
        ?>
            <script>
                function jsonStringToObject(str) {
                    try {
                        return JSON.parse(str);
                    } catch (e) {
                        return null;
                    }
                }
                function getCustomPaymentDetails(details, method, data) {
                    const url = window.location.href;
                    const regex = /[?&]status=(\w+)/;
                    const match = url.match(regex);
                    const status = match ? match[1] : null;

                    if (data[method] !== undefined) {
                        if ('<?php echo esc_js($this->key) ?>' === method) {
                            let anotherDetails;
                            let network = jsonStringToObject(data[method].network);
                            let currency = jsonStringToObject(data[method].currency);
                            if (network && network) {
                                anotherDetails = `
                                <p>
                                    <label><?php echo esc_html__('Network:'); ?></label>
                                    ${network.name}
                                </p>
                                <p>
                                    <label><?php echo esc_html__('Currency:'); ?></label>
                                    ${currency.symbol}
                                </p>
                                <p>
                                    <label><?php echo esc_html__('Address:'); ?></label>
                                    ${data[method].address}
                                </p>
                                <br>
                            `;
                            } else {
                                anotherDetails = '';
                            }
                            details = status == 'pending' ? anotherDetails + `
                            <button title="<?php echo sprintf(esc_attr__('Pay with %s', 'dokan-cryptopay'), $this->title) ?>" class="button button-small pay-with-cryptopay" data-key="<?php echo esc_attr($this->key); ?>" data-details='${JSON.stringify(data[method])}'>
                                <?php echo sprintf(esc_html__('Pay with %s', 'dokan-cryptopay'), $this->title) ?>
                            </button>
                            ` : anotherDetails;
                        }
                    }

                    return details;
                }

                dokan.hooks.addFilter('dokan_get_payment_details', 'getCustomPaymentDetails', getCustomPaymentDetails, 33, 3);
            </script>
        <?php
    }

    /**
     * @return void
     */
    public function runCryptoPay(): void
    {
        if ($this->key == 'cryptopay') {
            echo (new Payment($this->key))->setConfirmation(false);
        } else {
            echo LiteServices::preparePaymentProcess($this->key, false);
        }
    }
}
