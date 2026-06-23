document.addEventListener('DOMContentLoaded', () => {
    console.log('HRM365 Application Initialized');

    initializeMobileNavigation();
    initializeTablePagination();

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }
    
    // Add micro-animations for cards
    const cards = document.querySelectorAll('.card, .metric-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(10px)';
        card.style.transition = 'all 0.4s ease-out';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100 * index);
    });
});

function initializeMobileNavigation() {
    const sidebar = document.querySelector('.sidebar');
    const toggle = document.querySelector('.mobile-menu-toggle');

    if (!sidebar || !toggle) {
        return;
    }

    let overlay = document.querySelector('.mobile-sidebar-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'mobile-sidebar-overlay';
        document.body.appendChild(overlay);
    }

    function setOpen(isOpen) {
        document.body.classList.toggle('sidebar-open', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        toggle.innerHTML = isOpen ? '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
    }

    toggle.addEventListener('click', () => {
        setOpen(!document.body.classList.contains('sidebar-open'));
    });

    overlay.addEventListener('click', () => setOpen(false));

    sidebar.addEventListener('click', event => {
        if (event.target.closest('a.nav-item')) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 900) {
            setOpen(false);
        }
    });
}

function initializeTablePagination() {
    const pageSize = 10;
    const tables = document.querySelectorAll('.table');

    tables.forEach((table, tableIndex) => {
        if (table.dataset.noPagination === 'true' || table.classList.contains('js-paginated-table')) {
            return;
        }

        if (table.closest('#attendanceReportSection, #leavePayrollReportSection')) {
            return;
        }

        const tbody = table.querySelector('tbody');
        if (!tbody) {
            return;
        }

        const rows = Array.from(tbody.querySelectorAll(':scope > tr'));
        if (rows.length <= pageSize) {
            return;
        }

        table.classList.add('js-paginated-table');

        let currentPage = 1;
        const totalPages = Math.ceil(rows.length / pageSize);
        const pagination = document.createElement('div');
        pagination.className = 'table-pagination';
        pagination.setAttribute('data-table-pagination', String(tableIndex));

        const info = document.createElement('div');
        info.className = 'table-pagination-info';

        const actions = document.createElement('div');
        actions.className = 'table-pagination-actions';

        pagination.appendChild(info);
        pagination.appendChild(actions);

        const container = table.closest('.table-container');
        if (container && container.parentNode) {
            container.parentNode.insertBefore(pagination, container.nextSibling);
        } else {
            table.parentNode.insertBefore(pagination, table.nextSibling);
        }

        function makeButton(label, page, options = {}) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'table-page-button';
            button.innerHTML = label;
            button.disabled = Boolean(options.disabled);
            if (options.active) {
                button.classList.add('active');
            }
            button.addEventListener('click', () => {
                if (page < 1 || page > totalPages || page === currentPage) {
                    return;
                }
                currentPage = page;
                render();
            });
            return button;
        }

        function render() {
            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = startIndex + pageSize;

            rows.forEach((row, index) => {
                row.style.display = index >= startIndex && index < endIndex ? '' : 'none';
            });

            info.textContent = `Showing ${startIndex + 1}-${Math.min(endIndex, rows.length)} of ${rows.length}`;
            actions.innerHTML = '';
            actions.appendChild(makeButton('<i class="fas fa-chevron-left"></i>', currentPage - 1, { disabled: currentPage === 1 }));

            for (let page = 1; page <= totalPages; page++) {
                actions.appendChild(makeButton(String(page), page, { active: page === currentPage }));
            }

            actions.appendChild(makeButton('<i class="fas fa-chevron-right"></i>', currentPage + 1, { disabled: currentPage === totalPages }));
        }

        render();
    });
}
