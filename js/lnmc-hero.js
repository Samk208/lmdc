/**
 * LNMC Homepage Hero Slider Controller
 * Vanilla JS, zero dependencies, accessible, performant.
 * File budget: <= 6 KB minified.
 */

(function () {
    'use strict';

    function initHeroSlider() {
        var hero = document.querySelector('.lnmc-hero');
        if (!hero) return;

        var slides = hero.querySelectorAll('.lnmc-hero__slide');
        var dots = hero.querySelectorAll('.lnmc-hero__dot');
        var cards = hero.querySelectorAll('.lnmc-hero__card');
        var prevBtn = hero.querySelector('.lnmc-hero__arrow--prev');
        var nextBtn = hero.querySelector('.lnmc-hero__arrow--next');
        var pauseBtn = hero.querySelector('.lnmc-hero__pause');
        var video = hero.querySelector('.lnmc-hero__video');

        if (!slides.length) return;

        var currentIndex = 0;
        var totalSlides = slides.length;
        var timer = null;
        var intervalMs = 7000;
        var isUserPaused = false;
        var isHovered = false;
        var isFocused = false;

        var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var isSaveData = Boolean(navigator.connection && navigator.connection.saveData);

        function updateDeck(nextActiveIndex) {
            if (!cards.length) return;
            // Cards preview the next slides in sequence
            var offset = 1;
            cards.forEach(function (card) {
                var targetIdx = (nextActiveIndex + offset) % totalSlides;
                var targetSlideNum = targetIdx + 1;
                card.setAttribute('data-go', targetSlideNum);
                var slideEl = slides[targetIdx];
                if (slideEl) {
                    var titleEl = slideEl.querySelector('.lnmc-hero__title');
                    var badgeEl = slideEl.querySelector('.lnmc-hero__badge');
                    var numEl = card.querySelector('.lnmc-hero__card-num');
                    var labelEl = card.querySelector('.lnmc-hero__card-label');
                    if (numEl) numEl.textContent = targetSlideNum < 10 ? '0' + targetSlideNum : targetSlideNum;
                    if (labelEl) labelEl.textContent = badgeEl ? badgeEl.textContent : (titleEl ? titleEl.textContent : '');
                    
                    var thumb = card.querySelector('.lnmc-hero__card-thumb img');
                    var slideImg = slideEl.querySelector('.lnmc-hero__img') || slideEl.querySelector('img');
                    if (thumb && slideImg) {
                        thumb.src = slideImg.currentSrc || slideImg.src;
                    }
                }
                offset++;
            });
        }

        function handleVideo(slideIndex, shouldPlay) {
            if (!video) return;
            var isSlide3 = (slideIndex === 2); // 0-indexed slide 3
            var isDesktop = window.innerWidth >= 1024;
            var canPlayVideo = isDesktop && !prefersReducedMotion && !isSaveData;

            if (isSlide3 && shouldPlay && canPlayVideo && !isUserPaused && !document.hidden) {
                // Video ready to play
                var playPromise = video.play();
                if (playPromise !== undefined) {
                    playPromise.catch(function () {
                        // Autoplay prevented by browser policy; poster image remains visible
                    });
                }
            } else {
                if (!video.paused) {
                    video.pause();
                }
            }
        }

        function goToSlide(index) {
            if (index < 0) {
                index = totalSlides - 1;
            } else if (index >= totalSlides) {
                index = 0;
            }

            slides[currentIndex].classList.remove('is-active');
            slides[currentIndex].setAttribute('aria-hidden', 'true');
            if (dots[currentIndex]) {
                dots[currentIndex].classList.remove('is-active');
                dots[currentIndex].setAttribute('aria-selected', 'false');
            }

            handleVideo(currentIndex, false);

            currentIndex = index;

            slides[currentIndex].classList.add('is-active');
            slides[currentIndex].removeAttribute('aria-hidden');
            if (dots[currentIndex]) {
                dots[currentIndex].classList.add('is-active');
                dots[currentIndex].setAttribute('aria-selected', 'true');
            }

            updateDeck(currentIndex);
            handleVideo(currentIndex, true);
        }

        function nextSlide() {
            goToSlide(currentIndex + 1);
        }

        function prevSlide() {
            goToSlide(currentIndex - 1);
        }

        function startTimer() {
            if (prefersReducedMotion || isUserPaused || isHovered || isFocused || document.hidden) {
                return;
            }
            stopTimer();
            timer = setInterval(nextSlide, intervalMs);
        }

        function stopTimer() {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        }

        // Card Deck clicks
        cards.forEach(function (card) {
            card.addEventListener('click', function () {
                var target = parseInt(card.getAttribute('data-go'), 10);
                if (!isNaN(target)) {
                    goToSlide(target - 1);
                    startTimer();
                }
            });
        });

        // Navigation dots
        dots.forEach(function (dot) {
            dot.addEventListener('click', function () {
                var target = parseInt(dot.getAttribute('data-go'), 10);
                if (!isNaN(target)) {
                    goToSlide(target - 1);
                    startTimer();
                }
            });
        });

        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                nextSlide();
                startTimer();
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                prevSlide();
                startTimer();
            });
        }

        // Pause/Resume button
        if (pauseBtn) {
            pauseBtn.addEventListener('click', function () {
                isUserPaused = !isUserPaused;
                pauseBtn.setAttribute('aria-pressed', isUserPaused ? 'true' : 'false');
                if (isUserPaused) {
                    stopTimer();
                    handleVideo(currentIndex, false);
                } else {
                    startTimer();
                    handleVideo(currentIndex, true);
                }
            });
        }

        // Hover events
        hero.addEventListener('mouseenter', function () {
            isHovered = true;
            stopTimer();
        });
        hero.addEventListener('mouseleave', function () {
            isHovered = false;
            startTimer();
        });

        // Focus events
        hero.addEventListener('focusin', function () {
            isFocused = true;
            stopTimer();
        });
        hero.addEventListener('focusout', function () {
            isFocused = false;
            startTimer();
        });

        // Keyboard navigation
        hero.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                prevSlide();
                startTimer();
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                nextSlide();
                startTimer();
            }
        });

        // Document visibility change (tab backgrounding)
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopTimer();
                handleVideo(currentIndex, false);
            } else {
                startTimer();
                handleVideo(currentIndex, true);
            }
        });

        // Initialize first slide and timer
        updateDeck(0);
        handleVideo(0, true);
        startTimer();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHeroSlider);
    } else {
        initHeroSlider();
    }
})();
