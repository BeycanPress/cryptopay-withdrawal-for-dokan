(($) => {

    $(document).ready(() => {
        let CryptoPayApp;
        const modal = $(".dokan-cryptopay-modal");

        $(window).on('click', function(e) {
            if (e.target == modal[0]) {
                modal.hide();
                if (CryptoPayApp.reset) {
                    CryptoPayApp.reset();
                }
            }
        });

        $(document).on('click', '.pay-with-cryptopay', function(e) {
            let key = $(this).data('key');
            let parent = $(this).closest('tr');
            let details = $(this).data('details');
            let currency = DokanCryptoPay.currency;
            let approve = parent.find(".actions .button-group button:eq(0)");
            let amount = parseFloat(parent.find(".amount div").text().replace(/[^a-zA-Z0-9.,]/g, "").trim());
            
            details = {
                receiver: details.address,
                network: JSON.parse(details.network),
                currency: JSON.parse(details.currency),
            }

            modal.show();

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
                    CryptoPayApp.reStart(order, params);
                }

                CryptoPayApp.store.config.set('networks', [details.network]);

                window.CryptoPayApp.events.add('transactionReceived', ({transaction}) => {
                    approve.trigger('click');
                    cpHelpers.successPopup(window.CryptoPayLang.transactionSent, `
                        <a href="${transaction.getUrl()}" target="_blank">
                            ${window.CryptoPayLang.openInExplorer}
                        </a>
                    `).then(() => {
                        modal.hide();
                    });
                });
            } else if (key == 'cryptopay_lite') {
                CryptoPayLite.networks = [
                    details.network,
                ];

                CryptoPayApp = CryptoPayLite.startPayment({
                    amount,
                    currency,
                }, {
                    receiver: details.receiver,
                });
    
                CryptoPayLite.hooks.transactionSent = (n, tx) => {
                    approve.trigger('click');
                    cpHelpers.successPopup(CryptoPayLite.lang.transactionSent, `
                        <a href="${tx.getUrl()}" target="_blank">
                            ${CryptoPayLite.lang.openInExplorer}
                        </a>
                    `).then(() => {
                        modal.hide();
                        CryptoPayApp.reset();
                    });
                }
            }
            
        });
    });

})(jQuery);