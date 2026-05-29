document.addEventListener('DOMContentLoaded', () => {
    // Мобильное меню
    const mobileToggle = document.querySelector('.mobile-menu-toggle');
    const nav = document.querySelector('.nav');

    if (mobileToggle && nav) {
        mobileToggle.addEventListener('click', () => {
            nav.classList.toggle('active');
        });

        document.addEventListener('click', (e) => {
            if (!nav.contains(e.target) && !mobileToggle.contains(e.target) && nav.classList.contains('active')) {
                nav.classList.remove('active');
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 920) {
                nav.classList.remove('active');
            }
        });
    }

    // Анимация появления элементов
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.product-card, .advantage-card').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'all 0.4s ease-out';
        observer.observe(el);
    });
});

// ========== АКТИВНОЕ СОСТОЯНИЕ ПУНКТОВ МЕНЮ С СОХРАНЕНИЕМ ==========
(function () {
    // Функция для установки активного пункта меню на основе текущей страницы
    function setActiveMenuItem() {
        const currentUrl = window.location.pathname;
        const currentPage = currentUrl.split('/').pop() || 'index.php';

        // Получаем все ссылки
        const navLinks = document.querySelectorAll('.nav-links a');
        const authLinks = document.querySelectorAll('.auth-icons a');
        const logo = document.querySelector('.logo');
        const loginIcon = document.querySelector('.auth-icons .login-icon');
        const userIcon = document.querySelector('.auth-icons .user-icon');

        // Удаляем класс active у всех
        navLinks.forEach(link => link.classList.remove('active'));
        if (logo) logo.classList.remove('active');
        if (loginIcon) loginIcon.classList.remove('active');
        if (userIcon) userIcon.classList.remove('active');

        // Добавляем active в зависимости от страницы
        switch (currentPage) {
            case 'index.php':
                if (logo) logo.classList.add('active');
                break;
            case 'catalog.php':
                document.querySelectorAll('.nav-links a').forEach(link => {
                    if (link.getAttribute('href') === 'catalog.php') {
                        link.classList.add('active');
                    }
                });
                break;
            case 'contacts.php':
                document.querySelectorAll('.nav-links a').forEach(link => {
                    if (link.getAttribute('href') === 'contacts.php') {
                        link.classList.add('active');
                    }
                });
                break;
            case 'profile.php':
                if (userIcon) userIcon.classList.add('active');
                break;
            case 'auth.php':
            case 'register.php':
                if (loginIcon) loginIcon.classList.add('active');
                break;
        }
    }

    // Сохраняем активный пункт в localStorage при клике
    function addClickHandlers() {
        // Логотип
        const logo = document.querySelector('.logo');
        if (logo) {
            logo.addEventListener('click', function () {
                localStorage.setItem('activeMenu', 'logo');
            });
        }

        // Ссылки навигации
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', function () {
                const href = this.getAttribute('href');
                localStorage.setItem('activeMenu', href);
            });
        });

        // Иконка пользователя (профиль)
        const userIcon = document.querySelector('.auth-icons .user-icon');
        if (userIcon) {
            userIcon.addEventListener('click', function () {
                localStorage.setItem('activeMenu', 'profile');
            });
        }

        // Иконка входа
        const loginIcon = document.querySelector('.auth-icons .login-icon');
        if (loginIcon) {
            loginIcon.addEventListener('click', function () {
                localStorage.setItem('activeMenu', 'auth');
            });
        }
    }

    // Восстанавливаем активное состояние из localStorage
    function restoreFromStorage() {
        const savedMenu = localStorage.getItem('activeMenu');
        if (!savedMenu) return;

        // Очищаем все active классы
        document.querySelectorAll('.nav-links a, .auth-icons a, .logo, .auth-icons .login-icon, .auth-icons .user-icon').forEach(el => {
            if (el) el.classList.remove('active');
        });

        // Восстанавливаем по сохраненному значению
        switch (savedMenu) {
            case 'logo':
                const logo = document.querySelector('.logo');
                if (logo) logo.classList.add('active');
                break;
            case 'catalog.php':
                document.querySelectorAll('.nav-links a').forEach(link => {
                    if (link.getAttribute('href') === 'catalog.php') {
                        link.classList.add('active');
                    }
                });
                break;
            case 'contacts.php':
                document.querySelectorAll('.nav-links a').forEach(link => {
                    if (link.getAttribute('href') === 'contacts.php') {
                        link.classList.add('active');
                    }
                });
                break;
            case 'profile':
                const userIcon = document.querySelector('.auth-icons .user-icon');
                if (userIcon) userIcon.classList.add('active');
                break;
            case 'auth':
                const loginIcon = document.querySelector('.auth-icons .login-icon');
                if (loginIcon) loginIcon.classList.add('active');
                break;
        }
    }

    // Запускаем при загрузке
    setActiveMenuItem();
    addClickHandlers();
    restoreFromStorage();
})();