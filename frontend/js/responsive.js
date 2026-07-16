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
    
    const tables = document.querySelectorAll('table');
    tables.forEach(table => {
        if (!table.parentElement.classList.contains('table-container') && !table.parentElement.classList.contains('table-responsive')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-container';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
    });

    updateGlobalBadges();

    // Global Notification WebSocket
    const globalToken = localStorage.getItem('token');
    if (globalToken) {
        // Prevent setting up if we are on the messages page (which has its own wsConn)
        // Actually, it's safer to just let it run or check window location.
        if (!window.location.pathname.includes('messages.html')) {
            const notifWs = new WebSocket('ws://localhost:8081?token=' + globalToken);
            notifWs.onmessage = function(e) {
                try {
                    const payload = JSON.parse(e.data);
                    if (payload.type === 'message') {
                        updateGlobalBadges();
                    }
                } catch(err) {
                    console.error('WebSocket payload error:', err);
                }
            };
            notifWs.onerror = function() {
                // Silently handle errors for background notif socket
            };
        }
    }
});

async function updateGlobalBadges() {
    // 1. Cart Badge (for patient pages)
    const cartBadge = document.getElementById('cartBadge');
    if (cartBadge) {
        const cart = JSON.parse(localStorage.getItem('cart')) || [];
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        cartBadge.textContent = totalItems;
        cartBadge.style.display = totalItems > 0 ? 'flex' : 'none';
    }
    
    // Also update cartCount if it exists (patient-store uses cartCount)
    const cartCount = document.getElementById('cartCount');
    if (cartCount) {
        const cart = JSON.parse(localStorage.getItem('cart')) || [];
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        cartCount.textContent = totalItems;
        cartCount.style.display = totalItems > 0 ? 'inline-block' : 'none';
    }

    // 2. Notification Badge (unread messages)
    const notifBadge = document.getElementById('notifBadge');
    if (notifBadge) {
        const token = localStorage.getItem('token');
        if (token) {
            try {
                const res = await fetch('../backend/index.php?route=messages/conversations', {
                    headers: { 'Authorization': 'Bearer ' + token }
                });
                const data = await res.json();
                if (data.success && data.conversations) {
                    const totalUnread = data.conversations.reduce((sum, c) => sum + parseInt(c.unread_count || 0), 0);
                    notifBadge.textContent = totalUnread;
                    notifBadge.style.display = totalUnread > 0 ? 'flex' : 'none';
                }
            } catch (err) {
                console.error('Error fetching unread count:', err);
            }
        }
    }
}

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
