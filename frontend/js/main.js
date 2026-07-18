// ============================================
//   PharmaTrust GhanaANA â€” MAIN JAVASCRIPT
// ============================================

document.addEventListener('DOMContentLoaded', () => {

  // ===== AUTH STATE CHECK =====
  const userStr = localStorage.getItem('user');
  const token = localStorage.getItem('token');
  const navActions = document.querySelector('.nav-actions');

  if (userStr && token && navActions) {
    try {
      const user = JSON.parse(userStr);
      const name = user.first_name || user.firstName || 'User';
      let dashboardUrl = 'index.html';
      if (user.role === 'admin') dashboardUrl = 'admin-dashboard.html';
      else if (user.role === 'pharmacy') dashboardUrl = 'pharmacy-dashboard.html';
      else if (user.role === 'patient') dashboardUrl = 'patient-profile.html';

      navActions.innerHTML = `
        <div style="display: flex; align-items: center;">
          <a href="${dashboardUrl}" style="color: #0F1923; font-weight: 600; font-size: 0.95rem; display: flex; align-items: center; gap: 8px; text-decoration: none;">
            <i class="fas fa-user-circle" style="color: #2D9A6A; font-size: 1.4rem;"></i> 
            Hi, ${name}
          </a>
          <div style="width: 1px; height: 24px; background-color: #E2E8F0; margin: 0 24px;"></div>
          <button onclick="logoutUser()" style="background: none; border: none; color: #6B7D8C; font-size: 0.9rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; padding: 0; transition: color 0.2s;" onmouseover="this.style.color='#E53E3E';" onmouseout="this.style.color='#6B7D8C';">
            <i class="fas fa-sign-out-alt"></i> Logout
          </button>
        </div>
      `;
    } catch (e) {
      console.error('Error parsing user data', e);
    }
  }

  window.logoutUser = function() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    window.location.href = 'login.html';
  };
  window.logout = window.logoutUser;

  // ===== NAVBAR SCROLL =====
  const navbar = document.getElementById('navbar');
  const scrollTop = document.getElementById('scrollTop');
  window.addEventListener('scroll', () => {
    if (navbar) navbar.classList.toggle('scrolled', window.scrollY > 50);
    if (scrollTop) scrollTop.classList.toggle('visible', window.scrollY > 400);
  });

  // ===== HAMBURGER MENU =====
  const hamburger = document.getElementById('hamburger');
  const navLinks = document.getElementById('navLinks');
  if (hamburger && navLinks) {
    hamburger.addEventListener('click', () => {
      navLinks.classList.toggle('open');
    });
    navLinks.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => navLinks.classList.remove('open'));
    });
  }

  // ===== SCROLL TO TOP =====
  if (scrollTop) {
    scrollTop.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  // ===== COUNTER ANIMATION =====
  const counters = document.querySelectorAll('.stat-num[data-target]');
  const animateCounter = (el) => {
    const target = +el.dataset.target;
    const duration = 1800;
    const step = target / (duration / 16);
    let current = 0;
    const timer = setInterval(() => {
      current += step;
      if (current >= target) { el.textContent = target.toLocaleString(); clearInterval(timer); }
      else el.textContent = Math.floor(current).toLocaleString();
    }, 16);
  };
  const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        animateCounter(entry.target);
        counterObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.5 });
  counters.forEach(c => counterObserver.observe(c));

  // ===== SCROLL REVEAL =====
  const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry, i) => {
      if (entry.isIntersecting) {
        setTimeout(() => {
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
        }, i * 80);
        revealObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  const revealEls = document.querySelectorAll(
    '.service-card, .testimonial-card, .about-feature, .process-step'
  );
  revealEls.forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(24px)';
    el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    revealObserver.observe(el);
  });

  // ===== STORE LOGIC =====
  let medicines = [];
  let cart = JSON.parse(localStorage.getItem('cart')) || [];
  
  // Expose to window for inline onclicks
  window.fetchMedicines = fetchMedicines;

  async function fetchMedicines(category = 'all') {
    const grid = document.getElementById('medicinesGrid');
    if (!grid) return;
    
    // Check if category came from the dropdown
    const dropdown = document.getElementById('categoryFilter');
    const searchInput = document.getElementById('searchInput');
    let queryCat = category;
    let querySearch = '';
    if (dropdown) queryCat = dropdown.value;
    if (searchInput) querySearch = searchInput.value;
    
    grid.innerHTML = '<p style="grid-column: 1 / -1; text-align: center; color: #6B7D8C; padding: 40px 0;"><i class="fas fa-spinner fa-spin"></i> Loading medicines...</p>';
    
    try {
      const url = `../backend/api/medicines?category=${queryCat}&search=${encodeURIComponent(querySearch)}`;
      const res = await fetch(url);
      const data = await res.json();
      
      grid.innerHTML = '';
      if (!data.success || !data.medicines || data.medicines.length === 0) {
        grid.innerHTML = '<p style="grid-column: 1 / -1; text-align: center; color: #6B7D8C; padding: 40px 0;">No medicines found matching your criteria. Check back later!</p>';
        return;
      }
      
      medicines = data.medicines;
      window.loadedMedicines = medicines;
      
      medicines.forEach(med => {
        const outOfStock = med.stock_qty <= 0;
        const div = document.createElement('div');
        div.className = 'product-card';
        
        const pharmacyTag = med.pharmacy_name ? `<div class="pharmacy-tag">${med.pharmacy_name}</div>` : '';
        
        const imageHtml = med.image_url 
          ? `<div style="width:100%; height:160px; border-radius:8px; overflow:hidden; margin-bottom:12px;">
               <img src="${med.image_url}" style="width:100%; height:100%; object-fit:cover;" alt="${med.name}" />
             </div>`
          : `<div class="product-icon"><i class="fas fa-pills"></i></div>`;
        
        div.innerHTML = `
          ${pharmacyTag}
          ${imageHtml}
          <div class="product-title">${med.name}</div>
          <div class="product-cat">${med.category || 'Medicine'}</div>
          <div class="product-price">GHS ${parseFloat(med.price).toFixed(2)}</div>
          <button class="add-to-cart ${outOfStock ? 'out-of-stock-btn' : ''}" 
                  ${outOfStock ? 'disabled' : `onclick="addToCart('${med.id}')"`}>
            <i class="fas ${outOfStock ? 'fa-ban' : 'fa-cart-plus'}"></i> 
            ${outOfStock ? 'Out of Stock' : 'Add to Cart'}
          </button>
        `;
        
        div.style.opacity = '0';
        div.style.transform = 'translateY(24px)';
        div.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        
        grid.appendChild(div);
        revealObserver.observe(div);
      });
    } catch (err) {
      console.error('Fetch error:', err);
      grid.innerHTML = '<p style="grid-column: 1 / -1; text-align: center; color: #E53E3E; padding: 40px 0;">Failed to load medicines.</p>';
    }
  }

  // Initial cart render
  if (document.getElementById('cartWidget')) {
    renderCart();
  }

  // Ensure initial load without binding category buttons (since we replaced them with select)
  fetchMedicines();

  // ===== DYNAMIC FETCHING: AGENTS =====
  async function fetchAgents() {
    const grid = document.getElementById('agentsGrid');
    if (!grid) return;
    
    grid.innerHTML = '<p style="grid-column: 1 / -1; text-align: center; color: #6B7D8C; padding: 40px 0;"><i class="fas fa-spinner fa-spin"></i> Loading agents...</p>';
    
    try {
      const res = await fetch('../backend/api/agents');
      const data = await res.json();
      
      grid.innerHTML = '';
      if (!data.success || !data.agents || data.agents.length === 0) {
        grid.innerHTML = '<p style="grid-column: 1 / -1; text-align: center; color: #6B7D8C; padding: 40px 0;">No verified agents available yet. <a href="register.html" style="color:var(--green-600);font-weight:600;">Register to become the first!</a></p>';
        return;
      }
      
      data.agents.slice(0, 3).forEach(agent => {
        const div = document.createElement('div');
        div.className = 'agent-card';
        
        div.innerHTML = `
          <div class="agent-img-wrap">
            <img src="https://images.unsplash.com/photo-1594824476967-48c8b964273f?w=300&q=80" alt="${agent.pharmacy_name}"/>
            <div class="agent-verified"><i class="fas fa-check-circle"></i></div>
          </div>
          <div class="agent-info">
            <h4>${agent.first_name} ${agent.last_name}</h4>
            <span class="agent-region"><i class="fas fa-map-marker-alt"></i> ${agent.region || 'Ghana'}</span>
            <div class="agent-rating">
              <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
              <span>New Agent</span>
            </div>
            <div class="agent-tags">
              <span>Verified</span><span>${agent.agent_type || 'Agent'}</span>
            </div>
          </div>
        `;
        
        div.style.opacity = '0';
        div.style.transform = 'translateY(24px)';
        div.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        
        grid.appendChild(div);
        revealObserver.observe(div);
      });
    } catch (err) {
      console.error('Fetch agents error:', err);
      grid.innerHTML = '<p style="grid-column: 1 / -1; text-align: center; color: #E53E3E; padding: 40px 0;">Failed to load agents.</p>';
    }
  }

  fetchAgents();

  // ===== CONTACT FORM =====
  const contactForm = document.getElementById('contactForm');
  if (contactForm) {
    contactForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = contactForm.querySelector('button[type="submit"]');
      const originalText = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
      btn.disabled = true;
      
      try {
        const formData = new FormData(contactForm);
        const response = await fetch('../backend/api/contact', {
          method: 'POST',
          body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
          btn.innerHTML = '<i class="fas fa-check-circle"></i> Message Sent!';
          btn.style.background = '#2D9A6A';
          showToast('Your message was sent successfully!', 'success');
          setTimeout(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            btn.style.background = '';
            contactForm.reset();
          }, 3000);
        } else {
          showToast(data.message || 'Failed to send message.', 'error');
          btn.innerHTML = originalText;
          btn.disabled = false;
        }
      } catch (error) {
        console.error('Error:', error);
        showToast('An error occurred. Please try again later.', 'error');
        btn.innerHTML = originalText;
        btn.disabled = false;
      }
    });
  }

  // ===== ACTIVE NAV HIGHLIGHT =====
  const sections = document.querySelectorAll('section[id]');
  const navAnchors = document.querySelectorAll('.nav-links a');
  window.addEventListener('scroll', () => {
    let current = '';
    sections.forEach(s => {
      if (window.scrollY >= s.offsetTop - 120) current = s.id;
    });
    navAnchors.forEach(a => {
      a.style.color = a.getAttribute('href') === '#' + current ? 'var(--green-500)' : '';
    });
  });
});

// ===== GLOBAL CSS INJECTION =====
const style = document.createElement('style');
style.textContent = `
  @keyframes fadeIn { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }

  #toast-container { position: fixed; top: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 12px; pointer-events: none; }
  .toast { background: #FFFFFF; border-left: 4px solid #2D9A6A; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); padding: 16px 20px; display: flex; align-items: center; gap: 16px; min-width: 300px; max-width: 400px; transform: translateX(120%); opacity: 0; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); pointer-events: auto; font-family: 'Inter', sans-serif; }
  .toast.show { transform: translateX(0); opacity: 1; }
  .toast-icon { font-size: 1.5rem; flex-shrink: 0; }
  .toast.success { border-left-color: #2D9A6A; }
  .toast.success .toast-icon { color: #2D9A6A; }
  .toast.error { border-left-color: #E53E3E; }
  .toast.error .toast-icon { color: #E53E3E; }
  .toast.info { border-left-color: #3182CE; }
  .toast.info .toast-icon { color: #3182CE; }
  .toast-content { flex-grow: 1; }
  .toast-title { font-weight: 700; font-family: 'Sora', sans-serif; font-size: 0.95rem; color: #0F1923; margin-bottom: 4px; }
  .toast-message { font-size: 0.85rem; color: #6B7D8C; line-height: 1.4; }
  .toast-close { color: #6B7D8C; font-size: 1.1rem; cursor: pointer; transition: color 0.2s; background: none; border: none; padding: 4px; }
  .toast-close:hover { color: #0F1923; }
`;
document.head.appendChild(style);

// ===== GLOBAL TOAST NOTIFICATION =====
window.showToast = function(message, type = 'success', title = null) {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `toast ${type}`;

  let iconClass = 'fa-check-circle';
  if (type === 'error') iconClass = 'fa-exclamation-circle';
  if (type === 'info') iconClass = 'fa-info-circle';

  let defaultTitle = 'Success';
  if (type === 'error') defaultTitle = 'Error';
  if (type === 'info') defaultTitle = 'Notice';

  const displayTitle = title || defaultTitle;

  toast.innerHTML = `
    <div class="toast-icon">
      <i class="fas ${iconClass}"></i>
    </div>
    <div class="toast-content">
      <div class="toast-title">${displayTitle}</div>
      <div class="toast-message">${message}</div>
    </div>
    <button class="toast-close"><i class="fas fa-times"></i></button>
  `;

  container.appendChild(toast);

  // Trigger animation after DOM insertion
  // Use setTimeout to ensure the browser registers the initial CSS state before transitioning
  setTimeout(() => {
    toast.classList.add('show');
  }, 10);

  const closeBtn = toast.querySelector('.toast-close');
  
  const removeToast = () => {
    toast.classList.remove('show');
    setTimeout(() => {
      if (toast.parentNode) {
        toast.parentNode.removeChild(toast);
      }
    }, 400); // Wait for transition to finish
  };

  closeBtn.addEventListener('click', removeToast);

  // Auto remove after 4.5 seconds
  setTimeout(() => {
    if (toast.parentNode) {
      removeToast();
    }
  }, 4500);
};

// ===== CART & CHECKOUT LOGIC =====
window.toggleCart = function() {
  document.getElementById('cartWidget').classList.toggle('active');
};

window.addToCart = function(id) {
  // Try finding the medicine in the current loaded medicines
  let med = null;
  if (typeof window.loadedMedicines !== 'undefined') {
    med = window.loadedMedicines.find(m => String(m.id) === String(id));
  }
  
  if (!med) {
    showToast('Item not found', 'error');
    return;
  }

  // Load latest from localStorage
  let cart = JSON.parse(localStorage.getItem('cart')) || [];
  const existing = cart.find(item => String(item.medicine.id) === String(id));
  
  if (existing) {
    if (existing.quantity < med.stock_qty) {
      existing.quantity++;
      showToast(`${med.name} quantity increased`, 'info');
    } else {
      showToast('Maximum stock reached for this item', 'error');
    }
  } else {
    cart.push({ medicine: med, quantity: 1 });
    showToast(`${med.name} added to cart`, 'success');
  }
  
  localStorage.setItem('cart', JSON.stringify(cart));
  window.renderCart();
  
  if (!document.getElementById('cartWidget').classList.contains('active')) {
    toggleCart();
  }
};

window.updateQty = function(id, delta) {
  let cart = JSON.parse(localStorage.getItem('cart')) || [];
  const item = cart.find(i => String(i.medicine.id) === String(id));
  if (!item) return;

  item.quantity += delta;
  if (item.quantity <= 0) {
    cart = cart.filter(i => String(i.medicine.id) !== String(id));
  } else if (item.quantity > item.medicine.stock_qty) {
    item.quantity = item.medicine.stock_qty;
    showToast('Max stock reached', 'error');
  }
  
  localStorage.setItem('cart', JSON.stringify(cart));
  window.renderCart();
};

window.renderCart = function() {
  let cart = JSON.parse(localStorage.getItem('cart')) || [];
  const badge = document.getElementById('cartBadge');
  const itemsContainer = document.getElementById('cartItems');
  const totalEl = document.getElementById('cartTotal');

  if (!badge || !itemsContainer || !totalEl) return;

  const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
  badge.textContent = totalItems;

  if (cart.length === 0) {
    itemsContainer.innerHTML = `<div style="text-align: center; color: var(--text-muted, #6B7D8C); margin-top: 20px;">Your cart is empty</div>`;
    totalEl.textContent = 'GHS 0.00';
    return;
  }

  let totalAmount = 0;
  itemsContainer.innerHTML = cart.map(item => {
    const itemTotal = item.quantity * parseFloat(item.medicine.price);
    totalAmount += itemTotal;
    return `
      <div class="cart-item">
        <div class="cart-item-info">
          <div class="cart-item-title">${item.medicine.name}</div>
          <div class="cart-item-price">GHS ${parseFloat(item.medicine.price).toFixed(2)}</div>
        </div>
        <div class="cart-item-qty">
          <button class="qty-btn" onclick="updateQty('${item.medicine.id}', -1)"><i class="fas fa-minus" style="font-size: 0.7rem;"></i></button>
          <span style="font-weight: 600;">${item.quantity}</span>
          <button class="qty-btn" onclick="updateQty('${item.medicine.id}', 1)"><i class="fas fa-plus" style="font-size: 0.7rem;"></i></button>
        </div>
      </div>
    `;
  }).join('');

  totalEl.textContent = `GHS ${totalAmount.toFixed(2)}`;
};

window.openCheckout = function() {
  let cart = JSON.parse(localStorage.getItem('cart')) || [];
  if (cart.length === 0) {
    showToast('Your cart is empty', 'error');
    return;
  }
  
  const token = localStorage.getItem('token');
  const user = JSON.parse(localStorage.getItem('user'));
  
  if (!token || !user || user.role !== 'patient') {
    // Redirect to login if not logged in
    window.location.href = 'login.html?redirect=checkout';
    return;
  }
  
  const checkoutModal = document.getElementById('checkoutModal');
  if (checkoutModal) {
    if (user) {
        const emailEl = document.getElementById('checkoutEmail');
        const phoneEl = document.getElementById('checkoutPhone');
        const regionEl = document.getElementById('checkoutRegion');
        if (emailEl) emailEl.value = user.email || '';
        if (phoneEl) phoneEl.value = user.phone || '';
        if (regionEl && user.region) regionEl.value = user.region;
    }
    checkoutModal.classList.add('active');
    document.getElementById('cartWidget').classList.remove('active');
  }
};

window.closeCheckout = function() {
  const checkoutModal = document.getElementById('checkoutModal');
  if (checkoutModal) checkoutModal.classList.remove('active');
};

window.selectPayment = function(method) {
  document.getElementById('paymentMethod').value = method;
  document.getElementById('pay-card').classList.remove('selected');
  document.getElementById('pay-cash').classList.remove('selected');
  document.getElementById(`pay-${method}`).classList.add('selected');
  document.getElementById('placeOrderBtn').textContent = method === 'card' ? 'Pay & Place Order' : 'Place Order (Cash)';
};

// Checkout Form Submit
const checkoutForm = document.getElementById('checkoutForm');
if (checkoutForm) {
  checkoutForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    const method = document.getElementById('paymentMethod').value;
    
    // Check if new form fields exist, otherwise fallback for backward compatibility
    const phoneEl = document.getElementById('checkoutPhone');
    let address = "";
    
    if (phoneEl) {
      const phone = phoneEl.value;
      const email = document.getElementById('checkoutEmail').value;
      const region = document.getElementById('checkoutRegion').value;
      const city = document.getElementById('checkoutCity').value;
      const street = document.getElementById('checkoutStreet').value;
      
      address = `📞 Phone: ${phone}\n📧 Email: ${email}\n\n📍 Delivery Location:\n${street}\n${city}, ${region}`;
    } else {
      const addressEl = document.getElementById('deliveryAddress');
      address = addressEl ? addressEl.value : "No address provided";
    }

    const totalAmount = cart.reduce((sum, item) => sum + (item.quantity * parseFloat(item.medicine.price)), 0);

    const user = JSON.parse(localStorage.getItem('user'));

    if (method === 'card') {
      if (typeof PaystackPop === 'undefined') {
        showToast('Paystack could not be loaded. Please check your internet connection.', 'error');
        return;
      }
      
      fetch('/Mansro/backend/index.php?route=config')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.config.paystackPublicKey) {
                const handler = PaystackPop.setup({
                    key: data.config.paystackPublicKey,
                    email: user.email || 'guest@example.com',
                    amount: totalAmount * 100,
                    currency: 'GHS',
                    ref: 'ORD_' + Math.floor((Math.random() * 1000000000) + 1),
                    callback: function(response) {
                        submitOrder(address, method, response.reference);
                    },
                    onClose: function(){
                        showToast('Payment window closed.', 'info');
                    }
                });
                handler.openIframe();
            } else {
                showToast('Could not load payment configuration.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error loading payment configuration.', 'error');
        });
    } else {
      submitOrder(address, method, null);
    }
  });
}

async function submitOrder(address, method, reference) {
  let cart = JSON.parse(localStorage.getItem('cart')) || [];
  const token = localStorage.getItem('token');
  const btn = document.getElementById('placeOrderBtn');
  const originalText = btn.textContent;
  
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
  btn.disabled = true;

  const payload = {
    items: cart.map(i => ({ medicine_id: i.medicine.id, quantity: i.quantity })),
    deliveryAddress: address,
    paymentMethod: method,
    reference: reference
  };

  try {
    const res = await fetch('../backend/api/orders/checkout', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + token
      },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    
    if (data.success) {
      showToast('Order placed successfully!', 'success');
      localStorage.removeItem('cart');
      window.renderCart();
      window.closeCheckout();
      
      setTimeout(() => {
        window.location.href = 'patient-orders.html';
      }, 2000);
    } else {
      showToast(data.message || 'Failed to place order', 'error');
    }
  } catch (err) {
    console.error(err);
    showToast('An error occurred during checkout', 'error');
  } finally {
    btn.textContent = originalText;
    btn.disabled = false;
  }
}

// Check if we came back from login with a redirect to checkout
document.addEventListener('DOMContentLoaded', () => {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('redirect') === 'checkout') {
    const isAuthPage = window.location.pathname.includes('login') || window.location.pathname.includes('register');
    if (!isAuthPage) {
      // Small delay to ensure everything is initialized
      setTimeout(() => {
        if (typeof window.openCheckout === 'function') {
          window.openCheckout();
        }
        // Remove redirect param from URL cleanly
        window.history.replaceState({}, document.title, window.location.pathname);
      }, 500);
    }
  }
});

// Update Pharmacy Sidebar Orders Counter globally
document.addEventListener('DOMContentLoaded', async () => {
    const userStr = localStorage.getItem('user');
    const token = localStorage.getItem('token');
    
    if (userStr && token) {
        try {
            const user = JSON.parse(userStr);
            // Only execute for pharmacy role and if the sidebar element exists
            if (user.role === 'pharmacy') {
                const counters = document.querySelectorAll('.sidebarOrdersCounter');
                if (counters.length > 0) {
                    const response = await fetch('/Mansro/backend/index.php?route=orders/my', {
                        headers: { 'Authorization': 'Bearer ' + token }
                    });
                    
                    if (response.ok) {
                        const data = await response.json();
                        if (data.success && data.orders) {
                            // Count active orders (not completed, not cancelled, not delivered)
                            const activeOrders = data.orders.filter(o => 
                                o.status !== 'completed' && 
                                o.status !== 'cancelled' && 
                                o.status !== 'delivered'
                            );
                            
                            counters.forEach(counter => {
                                if (activeOrders.length > 0) {
                                    counter.textContent = activeOrders.length;
                                    counter.style.display = 'inline-block';
                                } else {
                                    counter.style.display = 'none';
                                }
                            });
                        }
                    }
                }
            }
        } catch (e) {
            console.error('Error fetching pharmacy sidebar counter:', e);
        }
    }
});

// Services Slider Logic
document.addEventListener('DOMContentLoaded', () => {
    const servicesGrid = document.getElementById('servicesGrid');
    const servicesPrev = document.getElementById('servicesPrev');
    const servicesNext = document.getElementById('servicesNext');

    if (servicesGrid && servicesPrev && servicesNext) {
        // Scroll amount should be roughly the width of one card plus gap
        const scrollAmount = 344; // 320px width + 24px gap
        
        servicesNext.addEventListener('click', () => {
            servicesGrid.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        });
        
        servicesPrev.addEventListener('click', () => {
            servicesGrid.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        });
    }
});

// Agents Slider Logic (Manual + Auto)
document.addEventListener('DOMContentLoaded', () => {
    const agentsGrid = document.getElementById('agentsGrid');
    const agentsPrev = document.getElementById('agentsPrev');
    const agentsNext = document.getElementById('agentsNext');

    if (agentsGrid && agentsPrev && agentsNext) {
        const scrollAmount = 324; // 300px width + 24px gap
        let autoScrollInterval;

        const startAutoScroll = () => {
            autoScrollInterval = setInterval(() => {
                if (agentsGrid.scrollLeft + agentsGrid.clientWidth >= agentsGrid.scrollWidth - 10) {
                    agentsGrid.scrollTo({ left: 0, behavior: 'smooth' });
                } else {
                    agentsGrid.scrollBy({ left: scrollAmount, behavior: 'smooth' });
                }
            }, 3000);
        };

        const stopAutoScroll = () => clearInterval(autoScrollInterval);

        agentsNext.addEventListener('click', () => {
            agentsGrid.scrollBy({ left: scrollAmount, behavior: 'smooth' });
            stopAutoScroll();
            startAutoScroll();
        });
        
        agentsPrev.addEventListener('click', () => {
            agentsGrid.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
            stopAutoScroll();
            startAutoScroll();
        });

        agentsGrid.addEventListener('mouseenter', stopAutoScroll);
        agentsGrid.addEventListener('mouseleave', startAutoScroll);
        agentsGrid.addEventListener('touchstart', stopAutoScroll);
        agentsGrid.addEventListener('touchend', startAutoScroll);

        startAutoScroll();
    }
});
