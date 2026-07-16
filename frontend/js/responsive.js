document.addEventListener('DOMContentLoaded', () => {
    // 1. Inject Hamburger Button if not present
    const topHeader = document.querySelector('.top-header');
    if (topHeader && !document.querySelector('.mobile-menu-btn')) {
        const titleDiv = topHeader.querySelector('.header-title');
        
        const hamburgerBtn = document.createElement('button');
        hamburgerBtn.className = 'mobile-menu-btn';
        hamburgerBtn.innerHTML = '<i class="fas fa-bars"></i>';
        hamburgerBtn.onclick = toggleSidebar;
        
        if (titleDiv) {
            topHeader.insertBefore(hamburgerBtn, titleDiv);
        } else {
            topHeader.prepend(hamburgerBtn);
        }
    }
    
    // 2. Inject Sidebar Overlay if not present
    const body = document.body;
    if (!document.querySelector('.sidebar-overlay')) {
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        overlay.onclick = toggleSidebar;
        body.appendChild(overlay);
    }
    
    // 2.5 Inject Close Button into sidebar header
    const sidebarHeader = document.querySelector('.sidebar-header');
    if (sidebarHeader && !document.querySelector('.close-sidebar-btn')) {
        const closeBtn = document.createElement('button');
        closeBtn.className = 'close-sidebar-btn';
        closeBtn.innerHTML = '<i class="fas fa-times"></i>';
        closeBtn.onclick = toggleSidebar;
        sidebarHeader.appendChild(closeBtn);
    }
    
    // 3. Ensure Table Containers have overflow-x on mobile
    const tables = document.querySelectorAll('table');
    tables.forEach(table => {
        if (!table.parentElement.classList.contains('table-container') && !table.parentElement.classList.contains('table-responsive')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-container';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
    });
});

function toggleSidebar() {
    if (window.innerWidth > 768) {
        document.body.classList.toggle('sidebar-collapsed');
        return;
    }

    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar && overlay) {
        const isOpen = sidebar.classList.contains('open');
        if (isOpen) {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        } else {
            sidebar.classList.add('open');
            overlay.classList.add('active');
        }
    }
}
