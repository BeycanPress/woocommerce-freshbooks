(($) => {

    $(document).ready(() => {
        $(document).on('click', '.connect-to-freshbooks', (e) => {
            window.location.href= WCFB.fbUrl;
        });
    });

})(jQuery);