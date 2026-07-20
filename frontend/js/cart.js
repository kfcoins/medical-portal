
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
                <div class="modal" style="padding: 0; overflow: hidden; border: none; background: white; border-radius: 16px; width: 90%; max-width: 450px; box-shadow: 0 20px 40px rgba(0,0,0,0.15);">
                    <div style="padding: 24px 32px; border-bottom: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center; background: #F8FAFC;">
                        <h3 style="font-family: 'Sora', sans-serif; font-size: 1.25rem; color: #0F1923; margin: 0; display: flex; align-items: center; gap: 10px;">
                            <div style="width: 36px; height: 36px; border-radius: 10px; background: #E8F5E9; color: #1A6349; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            Checkout
                        </h3>
                        <button class="close-cart" onclick="closeCheckoutModal()" style="background: #E2E8F0; border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #475569; transition: all 0.2s ease;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <form id="checkoutForm" onsubmit="handleCheckout(event)" style="padding: 32px; display: flex; flex-direction: column; gap: 24px; box-sizing: border-box; text-align: left;">
                        <div>
                            <label style="display: block; font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">Delivery Address <span style="color: #E53E3E;">*</span></label>
                            <textarea id="deliveryAddress" rows="3" required placeholder="Enter full delivery address, landmark or directions..." style="width: 100%; padding: 14px; border: 1.5px solid #E2E8F0; border-radius: 12px; font-family: inherit; font-size: 0.95rem; color: #0F1923; transition: all 0.2s ease; resize: none; background: #F8FAFC; outline: none; box-sizing: border-box;"></textarea>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">Payment Method <span style="color: #E53E3E;">*</span></label>
                            <div style="position: relative;">
                                <select id="paymentMethod" required style="width: 100%; padding: 14px; border: 1.5px solid #E2E8F0; border-radius: 12px; font-family: inherit; font-size: 0.95rem; color: #0F1923; transition: all 0.2s ease; background: #F8FAFC; appearance: none; outline: none; cursor: pointer; box-sizing: border-box;">
                                    <option value="cash">Pay on Delivery (Cash)</option>
                                    <option value="momo">Mobile Money</option>
                                    <option value="card">Card</option>
                                    <option value="nhis">NHIS (National Health Insurance Scheme)</option>
                                </select>
                                <div style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #64748B; pointer-events: none;">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                        </div>
                        <div style="background: #F8FAFC; border-radius: 12px; padding: 16px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #E2E8F0;">
                            <span style="color: #64748B; font-weight: 600; font-size: 0.95rem;">Total Amount</span>
                            <span id="checkoutTotalDisplay" style="font-weight: 800; font-size: 1.3rem; color: #1A6349;">GHS 0.00</span>
                        </div>
                        <button type="submit" id="confirmCheckoutBtn" style="width: 100%; padding: 16px; background: linear-gradient(135deg, #1A6349 0%, #134e3a 100%); color: white; border: none; border-radius: 12px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 10px 20px rgba(26, 99, 73, 0.2); display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 8px;">
                            <span>Place Order securely</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
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
    const existingItem = cart.find(item => String(item.medicine.id) === String(medicine.id));
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
    cart = cart.filter(item => String(item.medicine.id) !== String(id));
    saveCart();
    updateCartUI();
}

function changeQuantity(id, delta) {
    const item = cart.find(item => String(item.medicine.id) === String(id));
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
        const itemTotal = parseFloat(item.medicine.price) * item.quantity;
        total += itemTotal;
        const nhisTag = (item.medicine.nhis_listed && item.medicine.nhis_listed == 1) ? `<span style="display:inline-block; background:#d1fae5; color:#065f46; font-size:0.65rem; padding:2px 6px; border-radius:8px; font-weight:700; margin-left:8px; vertical-align:middle; text-transform:uppercase;"><i class="fas fa-shield-alt"></i> NHIS</span>` : '';
        return `
        <div class="cart-item">
            <img src="${item.medicine.image_url ? item.medicine.image_url : 'https://via.placeholder.com/60?text=M'}" class="cart-item-img" onerror="this.src='https://via.placeholder.com/60'">
            <div class="cart-item-info">
            <div class="cart-item-name">${item.medicine.name}${nhisTag}</div>
            <div class="cart-item-pharmacy">${item.medicine.pharmacy_name || 'System'}</div>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div class="cart-item-price">GHS ${parseFloat(item.medicine.price).toFixed(2)}</div>
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
        if (window.showToast) window.showToast("Your cart is empty!", 'error');
        return;
    }
    toggleCart(); // close sidebar
    const checkoutModal = document.getElementById('checkoutModal');
    if(checkoutModal) {
        checkoutModal.classList.add('active');
        const checkoutTotalDisplay = document.getElementById('checkoutTotalDisplay');
        if (checkoutTotalDisplay) {
            let total = cart.reduce((sum, item) => sum + parseFloat(item.medicine.price) * item.quantity, 0);
            checkoutTotalDisplay.textContent = `GHS ${total.toFixed(2)}`;
        }

        // Enforce Pay on Delivery availability
        const paymentMethodSelect = document.getElementById('paymentMethod');
        if (paymentMethodSelect) {
            const cashOption = Array.from(paymentMethodSelect.options).find(opt => opt.value === 'cash');
            const hasDisabledPOD = cart.some(item => {
                const pod = item.medicine.allow_pay_on_delivery;
                return pod == 0 || pod === "0" || pod === false || pod === null || pod === undefined;
            });
            
            let warningMsg = document.getElementById('podWarning');
            if (!warningMsg) {
                warningMsg = document.createElement('div');
                warningMsg.id = 'podWarning';
                warningMsg.style.color = '#E53E3E';
                warningMsg.style.fontSize = '0.8rem';
                warningMsg.style.marginTop = '6px';
                paymentMethodSelect.parentNode.appendChild(warningMsg);
            }
            
            if (hasDisabledPOD) {
                if (cashOption) {
                    cashOption.disabled = true;
                    cashOption.innerHTML = 'Pay on Delivery (Disabled)';
                }
                if (paymentMethodSelect.value === 'cash') {
                    paymentMethodSelect.value = 'momo';
                }
                warningMsg.textContent = '* One or more items in your cart do not support Pay on Delivery.';
            } else {
                if (cashOption) {
                    cashOption.disabled = false;
                    cashOption.innerHTML = 'Pay on Delivery (Cash)';
                }
                warningMsg.textContent = '';
            }
        }
    }
}

function closeCheckoutModal() {
    const checkoutModal = document.getElementById('checkoutModal');
    if(checkoutModal) checkoutModal.classList.remove('active');
}

async function handleCheckout(e) {
    e.preventDefault();
    
    const paymentMethod = document.getElementById('paymentMethod').value;
    const deliveryAddress = document.getElementById('deliveryAddress').value;

    const btn = document.getElementById('confirmCheckoutBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    btn.disabled = true;

    if (paymentMethod === 'card' || paymentMethod === 'momo') {
        if (typeof PaystackPop === 'undefined') {
            if (window.showToast) window.showToast('Paystack could not be loaded. Please check your internet connection.', 'error');
            btn.innerHTML = originalText;
            btn.disabled = false;
            return;
        }

        const user = JSON.parse(localStorage.getItem('user')) || {};
        const total = cart.reduce((sum, item) => sum + parseFloat(item.medicine.price) * item.quantity, 0);

        fetch('/Mansro/backend/index.php?route=config')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.config.paystackPublicKey) {
                    const handler = PaystackPop.setup({
                        key: data.config.paystackPublicKey,
                        email: user.email || 'guest@example.com',
                        amount: total * 100, // Paystack uses pesewas
                        currency: 'GHS',
                        ref: 'ORD_' + Math.floor((Math.random() * 1000000000) + 1),
                        callback: function(response) {
                            submitCartOrder(deliveryAddress, paymentMethod, response.reference, originalText);
                        },
                        onClose: function() {
                            if (window.showToast) window.showToast('Payment window closed.', 'info');
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        }
                    });
                    handler.openIframe();
                } else {
                    if (window.showToast) window.showToast('Could not load payment configuration.', 'error');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            })
            .catch(err => {
                console.error(err);
                if (window.showToast) window.showToast('Error loading payment configuration.', 'error');
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
    } else {
        // Cash payment
        submitCartOrder(deliveryAddress, paymentMethod, null, originalText);
    }
}

async function submitCartOrder(deliveryAddress, paymentMethod, reference, originalBtnText) {
    const payload = {
        deliveryAddress: deliveryAddress,
        paymentMethod: paymentMethod,
        paymentReference: reference,
        items: cart.map(i => ({
            medicine_id: i.medicine.id,
            quantity: i.quantity,
            unitPrice: i.medicine.price
        }))
    };
    
    const token = localStorage.getItem('token');
    const btn = document.getElementById('confirmCheckoutBtn');

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
            if (window.showToast) window.showToast('Order placed successfully! Redirecting to orders page...', 'success');
            setTimeout(() => {
                window.location.href = 'patient-orders.html';
            }, 1500);
        } else {
            if (window.showToast) window.showToast('Checkout failed: ' + (data.message || 'Unknown error'), 'error');
            if(btn) {
                btn.innerHTML = originalBtnText || 'Place Order';
                btn.disabled = false;
            }
        }
    } catch (error) {
        if (window.showToast) window.showToast('Checkout failed due to network error.', 'error');
        if(btn) {
            btn.innerHTML = originalBtnText || 'Place Order';
            btn.disabled = false;
        }
    }
}
