;(($) => {

    $(document).ready(() => {
        $(document).on('change', '.dokan-cryptopay-network', function(e) {
            let currencies = JSON.parse($(this).val()).currencies;
            $('.dokan-cryptopay-currency').html(`
                ${currencies.map(currency => `<option value='${JSON.stringify(currency)}'>${currency.symbol}</option>`).join('')}
            `);
        });
    });

})(jQuery);