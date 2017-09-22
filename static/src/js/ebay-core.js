if ( ReviewImproved.enabled() ) {
    (function($, root, undefined) {
        $.extend(UI, {
            autoCopySuggestionEnabled: function () {
                return false;
            }
        });
    })(jQuery, window);
}
