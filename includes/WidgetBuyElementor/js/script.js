jQuery(document).ready(function ($) {
    var infoIcon = $('.custom-widget-info-icon');
    var tooltip = $('.custom-widget-tooltip');

    infoIcon.on('click', function () {
        if (tooltip.is(':hidden')) {
            tooltip.slideDown();
        } else {
            tooltip.slideUp();
        }
    });
});
