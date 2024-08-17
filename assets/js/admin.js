(($) => {

    $(document).ready(() => {
        let CryptoPayApp;
        const __ = wp.i18n.__;

        $(document).on('click', '.pay-with-cryptopay', async function() {
            let key = $(this).data('key');
            let parent = $(this).closest('tr');
            let details = $(this).data('details');
            let currency = DokanCryptoPay.currency;
            let helpers = window.cpHelpers ?? window.cplHelpers;
            let approve = parent.find(".actions .button-group button:eq(0)");
            const modal = window.CryptoPayApp?.modal ?? window.CryptoPayLiteApp?.modal
            let amount = parseFloat(parent.find(".amount div").text().replace(/[^a-zA-Z0-9.,]/g, "").trim());
            
            details = {
                receiver: details.address,
                network: JSON.parse(details.network),
                currency: JSON.parse(details.currency),
            }

            if (key == 'dokan_cryptopay') {
                let order = {
                    amount,
                    currency,
                }

                let params = {
                    receiver: details.receiver,
                }

                if (!CryptoPayApp) {
                    CryptoPayApp = window.CryptoPayApp.start(order, params);
                } else {
                    await CryptoPayApp.reStart(order, params);
                }

                modal.open();

                CryptoPayApp.store.config.set('networks', [details.network]);

                window.CryptoPayApp.events.add('transactionReceived', ({transaction}) => {
                    approve.trigger('click');
                    $(this).prop('disabled', true)
                    $(this).text(__('Processing...'))
                    helpers.successPopup(window.CryptoPayLang.transactionSent, `
                        <a href="${transaction.getUrl()}" target="_blank">
                            ${window.CryptoPayLang.openInExplorer}
                        </a>
                    `).then(() => {
                        modal.close();
                    });
                }, 'dokan');
            } else if (key == 'dokan_cryptopay_lite') {
                let order = {
                    amount,
                    currency,
                }

                let params = {
                    receiver: details.receiver,
                }
    
                if (!CryptoPayApp) {
                    CryptoPayApp = window.CryptoPayLiteApp.start(order, params);
                } else {
                    await CryptoPayApp.reStart(order, params);
                }

                modal.open();

                CryptoPayApp.store.config.set('networks', [details.network]);

                window.CryptoPayLiteApp.events.add('transactionReceived', ({transaction}) => {
                    approve.trigger('click');
                    $(this).prop('disabled', true)
                    $(this).text(__('Processing...'))
                    helpers.successPopup(window.CryptoPayLiteLang.transactionSent, `
                        <a href="${transaction.getUrl()}" target="_blank">
                            ${window.CryptoPayLiteLang.openInExplorer}
                        </a>
                    `).then(() => {
                        modal.close();
                    });
                }, 'dokan');
            }
            
        });
    });

})(jQuery);