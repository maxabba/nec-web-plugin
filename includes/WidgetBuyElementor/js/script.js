jQuery(document).ready(function ($) {
    var infoIcon = $('.custom-widget-info-icon');


    infoIcon.on('click', function () {

        //find the first parent that matches the selector .custom-widget
        var parent = $(this).closest('.custom-widget');
        //find the tooltip by class .custom-widget-tooltip
        var tooltip = parent.find('.custom-widget-tooltip');

        if (tooltip.is(':hidden')) {
            tooltip.slideDown();
        } else {
            tooltip.slideUp();
        }
    });
});
