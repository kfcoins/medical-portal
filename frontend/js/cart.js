
// Global Cart Logic
let cart = []; // Array of { medicine: obj, quantity: 1 }

document.addEventListener('DOMContentLoaded', () => {
    // Inject Cart Sidebar HTML if not exists
    if (!document.getElementById('cartSidebar')) {
        const cartHtml = `
            <!-- Cart Sidebar -->
            <div class="overlay" id="cartOverlay" onclick="toggleCart()"></div>
            <div class="cart-sidebar" id="cartSidebar">
                <div class="cart-header">
                <h3>Your Cart</h3>
                <button class="close-cart" onclick="toggleCart()">&times;</button>
                </div>
                <div class="cart-body" id="cartItemsContainer">
                <div style="text-align: center; color: var(--text-muted); padding: 40px 0;">Cart is empty.</div>
                </div>
                <div class="cart-footer">
                <div class="cart-summary">
                    <span>Total:</span>
                    <span id="cartTotal">GHS 0.00</span>
                </div>
                <button class="btn-checkout" onclick="openCheckoutModal()">Proceed to Checkout</button>
                </div>
            </div>

            <!-- Checkout Modal -->
            <div class="modal-overlay" id="checkoutModal">
                <div class="modal">
                <div style="display: flex; justify-content: space-between; margin-bottom: 24px;">
                    <h3 style="font-family: 'Sora'; font-size: 1.25rem;">Checkout</h3>
                    <button class="close-cart" onclick="closeCheckoutModal()">&times;</button>
                </div>
                <form id="checkoutForm" onsubmit="handleCheckout(event)">
                    <div class="form-group">
                    <label>Delivery Address</label>
                    <textarea class="form-control" id="deliveryAddress" rows="3" required placeholder="Enter full delivery address or directions"></textarea>
                    </div>
                    <div class="form-group">
                    <label>Payment Method</label>
                    <select class="form-control" id="paymentMethod" required>
                        <option value="cash">Pay on Delivery (Cash)</option>
                        <option value="momo">Mobile Money (Coming Soon)</option>
                        <option value="card">Card (Coming Soon)</option>
                    </select>
                    </div>
                    <button type="submit" class="btn-checkout" id="confirmCheckoutBtn" style="margin-top: 16px;">Place Order</button>
                </form>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', cartHtml);
    }

    // Load cart from local storage if exists
    const savedCart = localStorage.getItem('cart');
    if (savedCart) {
        try {
            cart = JSON.parse(savedCart);
            updateCartUI();
        } catch(e) {}
    }
});

function addToCart(medicine) {
    const existingItem = cart.find(item => item.medicine.id === medicine.id);
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({ medicine, quantity: 1 });
    }
    saveCart();
    updateCartUI();
    
    // Simple feedback
    if (event && event.target && event.target.tagName === 'BUTTON') {
        const btn = event.target;
        const origText = btn.textContent;
        btn.textContent = 'Added!';
        btn.style.background = '#38A169';
        setTimeout(() => {
            btn.textContent = origText;
            btn.style.background = 'var(--primary)';
        }, 1000);
    }
}

function removeFromCart(id) {
    cart = cart.filter(item => item.medicine.id !== id);
    saveCart();
    updateCartUI();
}

function changeQuantity(id, delta) {
    const item = cart.find(item => item.medicine.id === id);
    if (item) {
        item.quantity += delta;
        if (item.quantity <= 0) {
            removeFromCart(id);
        } else {
            saveCart();
            updateCartUI();
        }
    }
}

function saveCart() {
    localStorage.setItem('cart', JSON.stringify(cart));
}

function updateCartUI() {
    // patient-store.html has id="cartCount", injected ones have "cartBadge". Just find whichever is inside a cart button
    let cartCountEl = document.getElementById('cartBadge') || document.getElementById('cartCount');
    
    // Actually we can update ALL cart badges across the DOM just in case
    const allBadges = document.querySelectorAll('#cartBadge, #cartCount, .cart-badge');
    const totalQty = cart.reduce((sum, item) => sum + item.quantity, 0);
    
    allBadges.forEach(el => {
        el.textContent = totalQty;
        el.style.display = totalQty > 0 ? 'flex' : 'none';
    });
    
    const container = document.getElementById('cartItemsContainer');
    const totalEl = document.getElementById('cartTotal');
    
    if (!container || !totalEl) return;
    
    if (cart.length === 0) {
        container.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px 0;">Cart is empty.</div>';
        totalEl.textContent = 'GHS 0.00';
        return;
    }

    let total = 0;
    container.innerHTML = cart.map(item => {
        const itemTotal = item.medicine.price * item.quantity;
        total += itemTotal;
        return `
        <div class="cart-item">
            <img src="${item.medicine.image_url ? item.medicine.image_url : 'https://via.placeholder.com/60?text=M'}" class="cart-item-img" onerror="this.src='https://via.placeholder.com/60'">
            <div class="cart-item-info">
            <div class="cart-item-name">${item.medicine.name}</div>
            <div class="cart-item-pharmacy">${item.medicine.pharmacy_name || 'System'}</div>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div class="cart-item-price">GHS ${item.medicine.price.toFixed(2)}</div>
                <div class="cart-item-actions">
                <button class="qty-btn" onclick="changeQuantity('${item.medicine.id}', -1)">-</button>
                <span style="font-size: 0.9rem; font-weight: 500; width: 20px; text-align: center;">${item.quantity}</span>
                <button class="qty-btn" onclick="changeQuantity('${item.medicine.id}', 1)">+</button>
                <button class="remove-btn" onclick="removeFromCart('${item.medicine.id}')"><i class="fas fa-trash"></i></button>
                </div>
            </div>
            </div>
        </div>
        `;
    }).join('');
    
    totalEl.textContent = `GHS ${total.toFixed(2)}`;
}

function toggleCart() {
    const sidebar = document.getElementById('cartSidebar');
    let overlay = document.getElementById('cartOverlay');
    if(!overlay) overlay = document.getElementById('overlay'); // patient-store.html uses overlay
    
    if(sidebar && overlay) {
        if (sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
        } else {
            sidebar.classList.add('open');
            overlay.classList.add('open');
            updateCartUI(); // Ensure UI is up to date when opened
        }
    }
}

function openCheckoutModal() {
    if (cart.length === 0) {
        alert("Your cart is empty!");
        return;
    }
    toggleCart(); // close sidebar
    const checkoutModal = document.getElementById('checkoutModal');
    if(checkoutModal) checkoutModal.classList.add('active');
}

function closeCheckoutModal() {
    const checkoutModal = document.getElementById('checkoutModal');
    if(checkoutModal) checkoutModal.classList.remove('active');
}

async function handleCheckout(e) {
    e.preventDefault();
    
    const paymentMethod = document.getElementById('paymentMethod').value;
    if (paymentMethod !== 'cash') {
        alert('Currently, only Pay on Delivery (Cash) is supported for demo purposes.');
        return;
    }

    const btn = document.getElementById('confirmCheckoutBtn');
    btn.textContent = 'Processing...';
    btn.disabled = true;

    const payload = {
        deliveryAddress: document.getElementById('deliveryAddress').value,
        paymentMethod: paymentMethod,
        items: cart.map(i => ({
            medicine_id: i.medicine.id,
            quantity: i.quantity,
            unitPrice: i.medicine.price
        }))
    };
    
    const token = localStorage.getItem('token');

    try {
        const response = await fetch('../backend/index.php?route=orders/checkout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify(payload)
        });
        
        const data = await response.json();
        if(data.success) {
            cart = [];
            saveCart();
            updateCartUI();
            closeCheckoutModal();
            alert('Order placed successfully! Redirecting to orders page...');
            window.location.href = 'patient-orders.html';
        } else {
            alert('Checkout failed: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        alert('Checkout failed due to network error.');
    } finally {
        if(btn) {
            btn.textContent = 'Place Order';
            btn.disabled = false;
        }
    }
}
