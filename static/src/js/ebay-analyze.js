
$.extend( UI, {

    setTranslateButtonEvent: function () {},
    setFocusEvent: function () {},
    getProjectInfo: function () {}

});

$(function() {
    $( '.mergebtn, .splitbtn' ).removeClass( 'disabled' ) ;
});

// override this function, we don't need outsource feature in this page.
function precomputeOutsourceQuotes() {};
