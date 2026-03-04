$(document).ready(function(){
	$('.bxslider').bxSlider({
	  controls: true,
	  adaptiveHeight: true
	 });

    $('.pre_toform').click(function (evt) {
        evt.preventDefault();
        $("html, body").animate({scrollTop: $('#order_form1').offset().top}, 1000);
        return false;
    });
});