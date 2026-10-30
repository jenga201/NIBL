import './bootstrap';

const showElem = (el) => {
    el.classList.remove('hidden');
    el.classList.add('block');
};

const hideElem = (el) => {
    el.classList.remove('block');
    el.classList.add('hidden');
};

const toggleMobileMenu = () => {
    const mobileMenu = document.getElementById('mobile-menu');
    const menuCloseIco = document.getElementById('menu-close');
    const menuOpenIco = document.getElementById('menu-open');

    if (!mobileMenu || !menuCloseIco || !menuOpenIco) {
        return;
    }

    if (mobileMenu.classList.contains('hidden')) {
        showElem(mobileMenu);
        hideElem(menuOpenIco);
        showElem(menuCloseIco);
    } else {
        hideElem(mobileMenu);
        hideElem(menuCloseIco);
        showElem(menuOpenIco);
    }
};

const toggleDarkMode = () => {
    const head = document.documentElement;

    if (head.classList.contains('dark')) {
        head.classList.remove('dark');
        toggleDarkModeIcon(false);
        localStorage.setItem('darkMode', 'disabled');
    } else {
        head.classList.add('dark');
        toggleDarkModeIcon(true);
        localStorage.setItem('darkMode', 'enabled');
    }
};

const toggleDarkModeIcon = (isDarkModeEnabled) => {
    const lightModeIco = document.getElementById('light-mode-ico');
    const darkModeIco = document.getElementById('dark-mode-ico');

    if (!lightModeIco || !darkModeIco) {
        return;
    }

    if (!isDarkModeEnabled) {
        hideElem(lightModeIco);
        showElem(darkModeIco);
        return;
    }

    hideElem(darkModeIco);
    showElem(lightModeIco);
};

document.addEventListener('click', (event) => {
    if (event.target.matches('.mobile-menu-toggle')) {
        event.preventDefault();
        toggleMobileMenu();
    }

    if (event.target.matches('#toggle-dark-mode')) {
        event.preventDefault();
        toggleDarkMode();
    }
});

toggleDarkModeIcon(
    localStorage.darkMode === 'enabled' ||
    (!('darkMode' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches),
);
