document.addEventListener('DOMContentLoaded', function () {
    var swiper = new Swiper('.swiper-container', {
        slidesPerView: 1,
        spaceBetween: 10,
        loop: true,
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
            enabled: false,
        },
        navigation: {
            nextEl: '.swiper-navigation .swiper-button-next',
            prevEl: '.swiper-navigation .swiper-button-prev',
        },
    });

    window.showthedefault = function () {
        //show the swiper container
        document.querySelector('#main_swiper_div').style.display = 'block';
    }

    document.getElementById('copy-button').addEventListener('click', function () {
        var activeSlide = document.querySelector('.swiper-slide-active .slide-content');
        if (activeSlide) {
            var textEditor = document.getElementById('text-editor');
            if (textEditor) {
                //remove all style attributes from the text
                var text = activeSlide.innerHTML;
                var div = document.createElement('div');
                div.innerHTML = text;
                var elements = div.querySelectorAll('*');
                for (var i = 0; i < elements.length; i++) {
                    elements[i].removeAttribute('style');
                }
                textEditor.innerHTML = div.innerHTML;
            }
        }
    });
});
