// profile.js - отдельный скрипт для страницы профиля
$(document).ready(function () {
    // Маска для телефона
    $('#phone').mask('+7 (000) 000-00-00');

    // Звездный рейтинг
    $('.star-rating .star').on('click', function () {
        let rating = $(this).data('value');
        let productId = $(this).closest('.review-form').data('product-id');

        $(this).closest('.star-rating').find('.star').each(function () {
            if ($(this).data('value') <= rating) {
                $(this).addClass('active');
            } else {
                $(this).removeClass('active');
            }
        });

        $(this).closest('.review-form').find('input[name="rating"]').val(rating);
    });

    // Добавление в избранное
    $('.favorite-btn').on('click', function (e) {
        e.preventDefault();
        let productId = $(this).data('product-id');
        let btn = $(this);

        $.ajax({
            url: 'toggle_favorite.php',
            method: 'POST',
            data: { product_id: productId },
            success: function (response) {
                if (response.success) {
                    if (response.action === 'added') {
                        btn.addClass('active').html('<i class="fas fa-heart"></i>');
                    } else {
                        btn.removeClass('active').html('<i class="far fa-heart"></i>');
                    }
                    showNotification(response.message, 'success');
                }
            }
        });
    });

    // Отправка отзыва
    $('.submit-review').on('click', function () {
        let productId = $(this).data('product-id');
        let rating = $(`#review-form-${productId} input[name="rating"]`).val();
        let qualities = $(`#review-form-${productId} select[name="qualities"]`).val();
        let comment = $(`#review-form-${productId} textarea[name="comment"]`).val();

        if (!rating) {
            showNotification('Пожалуйста, поставьте оценку', 'error');
            return;
        }

        $.ajax({
            url: 'submit_review.php',
            method: 'POST',
            data: { product_id: productId, rating: rating, qualities: qualities, comment: comment },
            success: function (response) {
                if (response.success) {
                    showNotification(response.message, 'success');
                    location.reload();
                } else {
                    showNotification(response.message, 'error');
                }
            }
        });
    });

    // Загрузка больше отзывов
    $('.load-more-reviews').on('click', function () {
        let productId = $(this).data('product-id');
        let offset = $(this).data('offset') || 1;
        let btn = $(this);

        $.ajax({
            url: 'load_reviews.php',
            method: 'POST',
            data: { product_id: productId, offset: offset },
            success: function (response) {
                if (response.html) {
                    $('.reviews-list').append(response.html);
                    btn.data('offset', offset + 1);
                    if (!response.has_more) {
                        btn.hide();
                    }
                }
            }
        });
    });
});

function showNotification(message, type) {
    const notification = $('<div>').addClass('cart-notification').css({
        position: 'fixed',
        top: '100px',
        right: '20px',
        background: type === 'success' ? '#4caf50' : '#f44336',
        color: 'white',
        padding: '12px 20px',
        borderRadius: '10px',
        zIndex: '9999'
    }).html(`<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`);

    $('body').append(notification);
    setTimeout(() => notification.remove(), 3000);
}