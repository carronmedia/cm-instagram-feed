import Swiper from 'swiper';
import { Navigation, Autoplay } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';

document.addEventListener("DOMContentLoaded", () => {
	const instagramSwipers = document.querySelectorAll(".cm-swiper-js-instagram");

	if (instagramSwipers.length === 0) return;

	instagramSwipers.forEach((slider, index) => {
		// Set default attributes if not already present
		slider.dataset.slidesToShow = slider.dataset.slidesToShow || '4';
		slider.dataset.mobileSlidesToShow = slider.dataset.mobileSlidesToShow || '1';
		slider.dataset.loop = slider.dataset.loop || 'false';
		slider.dataset.autoPlay = slider.dataset.autoPlay || 'false';
		slider.dataset.showDots = slider.dataset.showDots || 'false';
		slider.dataset.showArrows = slider.dataset.showArrows || 'false';
		slider.dataset.spaceBetween = slider.dataset.spaceBetween || '16';
		slider.dataset.mobileSpaceBetween = slider.dataset.mobileSpaceBetween || '8';
		console.log('Slider Data Attributes:', slider.dataset);
		const loop = slider.dataset.loop === 'true';
		const autoplay = slider.dataset.autoPlay === 'true';
		const showArrows = slider.dataset.showArrows === 'true';

		const prevArrow = slider.querySelector('.cm-swiper-button-prev');
		const nextArrow = slider.querySelector('.cm-swiper-button-next');

		const swiperOptions = {
			modules: [Navigation, Autoplay],
			loop: loop,
			grabCursor: true,
			freeMode: false,
			autoplay: autoplay
				? { delay: 3000, disableOnInteraction: false }
				: false,
			navigation: showArrows && prevArrow && nextArrow
				? {
					prevEl: prevArrow,
					nextEl: nextArrow
				}
				: false,
			breakpoints: {
				// On mobile, show partial next slide for visual hint
				0: {
					slidesPerView: parseFloat(slider.dataset.mobileSlidesToShow) + 0.15,
					spaceBetween: parseInt(slider.dataset.mobileSpaceBetween)
				},
				// On tablet, show 2 slides
				768: {
					slidesPerView: 2,
					spaceBetween: 12
				},
				// On desktop, show grid (disable swiper behavior visually)
				992: {
					slidesPerView: parseInt(slider.dataset.slidesToShow),
					spaceBetween: parseInt(slider.dataset.spaceBetween)
				}
			}
		};

		new Swiper(slider, swiperOptions);
	});
});