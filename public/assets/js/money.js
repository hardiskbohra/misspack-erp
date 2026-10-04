/* ==========================================================================
   MONEY — the rupee format, in the browser
   --------------------------------------------------------------------------
   The same contract as App\Helpers\CommonHelper, so a figure typed into a
   cost head reads the same as the figure the server prints beside it:

       misspackFormat.inr(150000)        -> "₹1,50,000"
       misspackFormat.inr(123456.5)      -> "₹1,23,456.50"
       misspackFormat.amount(1200,'USD') -> "USD 1,200.00"

   Lakh/crore grouping, and paise only when the amount really has them.
   ========================================================================== */
'use strict';

window.misspackFormat = (function () {
    var paiseOf = function (value) {
        return Math.round(Math.abs(Number(value) || 0) * 100);
    };

    /* the last three digits, then twos all the way up: 150000 => 1,50,000 */
    var groupIndian = function (digits) {
        if (digits.length <= 3) {
            return digits;
        }

        var lastThree = digits.slice(-3);
        var rest = digits.slice(0, -3);

        return rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + lastThree;
    };

    var inr = function (value, symbol) {
        var paise = paiseOf(value);
        var rupees = Math.floor(paise / 100);
        paise = paise % 100;

        var sign = Number(value) < 0 && (rupees > 0 || paise > 0) ? '-' : '';
        var decimals = paise > 0 ? '.' + String(paise).padStart(2, '0') : '';

        return sign + (symbol || '₹') + groupIndian(String(rupees)) + decimals;
    };

    return {
        inr: inr,

        amount: function (value, currency) {
            var code = String(currency || 'INR').trim().toUpperCase() || 'INR';

            return code === 'INR'
                ? inr(value)
                : code + ' ' + (Number(value) || 0).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
        },
    };
})();
