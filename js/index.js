function initSidebarBehavior() {
    const sideBar = document.querySelector('.sidebar');
    if (!sideBar || sideBar.dataset.bound === '1') {
        return;
    }

    sideBar.dataset.bound = '1';

    const sideLinks = sideBar.querySelectorAll('.side-menu li a:not(.logout)');
    sideLinks.forEach(item => {
        const li = item.parentElement;
        item.addEventListener('click', () => {
            sideLinks.forEach(i => {
                i.parentElement.classList.remove('active');
            })
            li.classList.add('active');
        })
    });

    const menuBar = document.querySelector('.content nav .bx.bx-menu');
    if (menuBar) {
        menuBar.addEventListener('click', () => {
            sideBar.classList.toggle('close');
            document.body.classList.toggle('sidebar-collapsed', sideBar.classList.contains('close'));
        });
    }

    if (window.innerWidth < 768) {
        sideBar.classList.add('close');
        document.body.classList.add('sidebar-collapsed');
    }
}

initSidebarBehavior();
window.initSidebarBehavior = initSidebarBehavior;

const searchBtn = document.querySelector('.content nav form .form-input button');
const searchBtnIcon = document.querySelector('.content nav form .form-input button .bx');
const searchForm = document.querySelector('.content nav form');

if (searchBtn && searchBtnIcon && searchForm) {
    searchBtn.addEventListener('click', function (e) {
        if (window.innerWidth < 576) {
            e.preventDefault;
            searchForm.classList.toggle('show');
            if (searchForm.classList.contains('show')) {
                searchBtnIcon.classList.replace('bx-search', 'bx-x');
            } else {
                searchBtnIcon.classList.replace('bx-x', 'bx-search');
            }
        }
    });
}

window.addEventListener('resize', () => {
    const sideBar = document.querySelector('.sidebar');
    if (sideBar) {
        if (window.innerWidth < 768) {
            sideBar.classList.add('close');
            document.body.classList.add('sidebar-collapsed');
        } else {
            sideBar.classList.remove('close');
            document.body.classList.remove('sidebar-collapsed');
        }
    }
    if (searchBtnIcon && searchForm && window.innerWidth > 576) {
        searchBtnIcon.classList.replace('bx-x', 'bx-search');
        searchForm.classList.remove('show');
    }
});

const toggler = document.getElementById('theme-toggle');

if (toggler) {
    toggler.addEventListener('change', function () {
        if (this.checked) {
            document.body.classList.add('dark');
        } else {
            document.body.classList.remove('dark');
        }
    });
}

let popup = document.getElementById("popup");

function openpopup(){
    if (popup) {
        popup.classList.add("open-popup");
    }
}
function closepopup(){
    if (popup) {
        popup.classList.remove("open-popup");
    }
}
