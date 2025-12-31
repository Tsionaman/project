// ==================== GLOBAL VARIABLES ====================
let currentUser = null;
let userCart = [];
let userWishlist = [];


// ==================== INITIALIZATION ====================
document.addEventListener('DOMContentLoaded', function() {
    console.log("Jimmy Furniture - Initializing...");
    
    // Load from localStorage FIRST for immediate display
    loadLocalCart();
    loadLocalWishlist();
    updateCartCount();
    updateWishlistCount();
    
    // Initialize user session
    initUserSession();
    
    // Setup event listeners
    setupEventListeners();
    
    // Initialize hero slider
    initHeroSlider();
    
    // Initialize search
    initSearch();
    
    console.log("Cart initialized:", userCart.length, "items");
    console.log("Wishlist initialized:", userWishlist.length, "items");
    console.log("Initialization complete!");
});

// ==================== ENHANCED AUTHENTICATION FUNCTIONS ====================
async function initUserSession() {
    try {
        const response = await fetch('/jimmy_furniture/api/check_auth.php');
        const data = await response.json();
        
        if (data.authenticated) {
            currentUser = data.user;
            localStorage.setItem('jimmy_user', JSON.stringify(data.user));
            
            // Load user-specific data from server
            await loadUserData();
            
            // Update UI
            updateAuthUI();
            
            // Show welcome if just logged in
            if (localStorage.getItem('just_logged_in')) {
                showWelcomeMessage(data.user.name);
                localStorage.removeItem('just_logged_in');
            }
        } else {
            currentUser = null;
            localStorage.removeItem('jimmy_user');
            updateAuthUI();
        }
    } catch (error) {
        console.error("Auth check failed:", error);
        currentUser = null;
        updateAuthUI();
    }
}



// Load user-specific data from server
async function loadUserData() {
    console.log("loadUserData called for user:", currentUser?.email);
    console.log("User role:", currentUser?.role);
    if (!currentUser) {
        // For guests, already loaded from localStorage in init
        return;
    }
    
    try {
        // Load user's cart from server
        const cartResponse = await fetch(`/jimmy_furniture/api/cart.php`);
        
        if (cartResponse.ok) {
            const cartData = await cartResponse.json();
            
            if (cartData.success && Array.isArray(cartData.data)) {
                userCart = cartData.data.map(item => ({
                    id: item.product_id,
                    cart_item_id: item.id,
                    name: item.name,
                    price: parseFloat(item.price),
                    image: item.image_url,
                    quantity: item.quantity,
                    stock_quantity: item.stock_quantity
                }));
            } else {
                userCart = [];
            }
        } else {
            userCart = [];
        }
        
        // Load user's wishlist from server
        // const wishlistResponse = await fetch(`/jimmy_furniture/api/wishlist.php?user_id=${currentUser.id}`);
        const wishlistResponse = await fetch('/jimmy_furniture/api/wishlist.php', {
            credentials: 'include'
        });
        
        if (wishlistResponse.ok) {
            const wishlistData = await wishlistResponse.json();
            
            if (wishlistData.success && Array.isArray(wishlistData.data)) {
                userWishlist = wishlistData.data.map(item => ({
                    id: item.product_id,
                    name: item.name,
                    price: parseFloat(item.price),
                    image: item.image_url,
                    added_at: item.added_at
                }));
            } else {
                userWishlist = [];
            }
        } else {
            userWishlist = [];
        }
        
        // Update UI
        updateCartCount();
        updateWishlistCount();
        updateCartUI(); 
        
    } catch (error) {
        console.error("Error loading user data:", error);
        // Keep existing local data if API fails
    }
}

// ==================== CART FUNCTIONS ====================
function loadLocalCart() {
    const savedCart = localStorage.getItem('jimmy_cart');
    try {
        userCart = savedCart ? JSON.parse(savedCart) : [];
        if (!Array.isArray(userCart)) userCart = [];
        console.log("Loaded local cart:", userCart.length, "items");
    } catch (e) {
        console.error("Error loading cart from localStorage:", e);
        userCart = [];
    }
}

function saveLocalCart() {
    try {
        localStorage.setItem('jimmy_cart', JSON.stringify(userCart));
        console.log('Cart saved to localStorage:', userCart.length, "items");
        updateCartCount();
    } catch (error) {
        console.error('Error saving cart to localStorage:', error);
    }
}

// Enhanced add to cart function
async function addToCart(productId, name, price, image) {

    if (!currentUser) {
        const index = userCart.findIndex(i => i.id == productId);
        if (index > -1) {
            userCart[index].quantity += 1;
        } else {
            userCart.push({
                id: productId,
                name,
                price: Number(price),
                image,
                quantity: 1
            });
        }
        localStorage.setItem('jimmy_cart', JSON.stringify(userCart));
        updateCartCount();
        showToast(`${name} added to cart`);
        return;
    }

    const res = await fetch('/jimmy_furniture/api/cart.php', {
        method: 'POST',
        credentials: 'include',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ product_id: productId, quantity: 1 })
    });

    const data = await res.json();
    if (data.success) {
        await loadUserData();
        updateCartCount();
        showToast(`${name} added to cart`);
    }
}

// Update cart count in UI
function updateCartCount() {
    const total = userCart.reduce((s, i) => s + (i.quantity || 1), 0);

    // Header icon
    const headerCount = document.getElementById('cart-count');
    if (headerCount) {
        headerCount.textContent = total;
        headerCount.style.display = total > 0 ? 'inline' : 'none';
    }

    // Dropdown badge
    document.querySelectorAll('.cart-count').forEach(el => {
        el.textContent = total;
    });
}



// Update cart UI (for modal)
function updateCartUI() {
    const cartList = document.getElementById('cart-items-list');
    const emptyCartMsg = document.getElementById('empty-cart-msg');
    const cartTotalElement = document.getElementById('cart-total');
    
    if (!cartList) return;
    
    if (userCart.length === 0) {
        cartList.innerHTML = '';
        if (emptyCartMsg) emptyCartMsg.style.display = 'block';
        if (cartTotalElement) cartTotalElement.textContent = '$0.00';
        return;
    }
    
    if (emptyCartMsg) emptyCartMsg.style.display = 'none';
    
    let total = 0;
    let html = '';
    
    userCart.forEach((item, index) => {
        const itemPrice = typeof item.price === 'string' ? parseFloat(item.price) : item.price || 0;
        const itemQuantity = item.quantity || 1;
        const itemTotal = itemPrice * itemQuantity;
        total += itemTotal;
        
        const minusDisabled = itemQuantity <= 1 ? 'disabled' : '';
        const minusStyle = itemQuantity <= 1 ? 'opacity: 0.5; cursor: not-allowed;' : '';
        
        html += `
            <div class="cart-item-row" data-index="${index}">
                <img src="${item.image || 'https://via.placeholder.com/60'}" 
                     alt="${item.name}" 
                     style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                <div style="flex: 1; margin-left: 15px;">
                    <h4 style="margin: 0; font-size: 14px;">${item.name}</h4>
                    <p style="margin: 0; color: #b8860b; font-weight: bold;">Birr${itemPrice.toFixed(2)}</p>
                    <div style="display: flex; align-items: center; gap: 10px; margin-top: 5px;">
                        <button onclick="updateCartQuantity(${index}, ${itemQuantity - 1})" 
                                style="background: #f0f0f0; border: none; width: 25px; height: 25px; border-radius: 50%; cursor: pointer; ${minusStyle}"
                                ${minusDisabled}>
                            -
                        </button>
                        <span style="font-size: 14px; min-width: 20px; text-align: center;">${itemQuantity}</span>
                        <button onclick="updateCartQuantity(${index}, ${itemQuantity + 1})" 
                                style="background: #f0f0f0; border: none; width: 25px; height: 25px; border-radius: 50%; cursor: pointer;">
                            +
                        </button>
                    </div>
                </div>
                <div>
                    <p style="margin: 0; color: #333; font-weight: bold;">Birr${itemTotal.toFixed(2)}</p>
                    <button onclick="removeFromCart(${index})" 
                            style="background: none; border: none; color: #ff4757; cursor: pointer; padding: 5px;">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    cartList.innerHTML = html;
    if (cartTotalElement) cartTotalElement.textContent = `Birr${total.toFixed(2)}`;
}

// Update cart quantity
async function updateCartQuantity(index, newQuantity) {
    if (newQuantity < 1) {
        removeFromCart(index);
        return;
    }
    
    if (index < 0 || index >= userCart.length) {
        console.error('Invalid cart index:', index);
        return;
    }
    
    const item = userCart[index];
    
    if (currentUser) {
        // Update on server for logged-in users
        try {
            const response = await fetch('/jimmy_furniture/api/cart.php', {
                method: 'PUT',
                credentials: 'include',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    item_id: item.cart_item_id || item.id,
                    quantity: newQuantity
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                await loadUserData();
                showToast("Quantity updated");
            } else {
                showToast(data.error || "Failed to update quantity");
            }
        } catch (error) {
            console.error('Error updating quantity:', error);
            showToast("Network error. Please try again.");
        }
    } else {
        // Update local storage for guests
        userCart[index].quantity = newQuantity;
        saveLocalCart();
        updateCartUI();
        updateCartCount();
        showToast("Quantity updated");
    }
}

// Remove item from cart
async function removeFromCart(index) {
    if (index < 0 || index >= userCart.length) {
        console.error('Invalid cart index:', index);
        return;
    }
    
    const item = userCart[index];
    
    if (currentUser) {
        // Remove from server for logged-in users
        try {
            const response = await fetch('/jimmy_furniture/api/cart.php', {
                method: 'DELETE',
                credentials: 'include',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    item_id: item.cart_item_id || item.id
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                await loadUserData();
                showToast(`${item.name} removed from cart`);
            } else {
                showToast(data.error || "Failed to remove item");
            }
        } catch (error) {
            console.error('Error removing item:', error);
            showToast("Network error. Please try again.");
        }
    } else {
        // Remove from local storage for guests
        const removedItem = userCart.splice(index, 1)[0];
        saveLocalCart();
        updateCartUI();
        updateCartCount();
        showToast(`${removedItem.name} removed from cart`);
    }
}

// ==================== WISHLIST FUNCTIONS ====================
function loadLocalWishlist() {
    const savedWishlist = localStorage.getItem('jimmy_wishlist');
    try {
        userWishlist = savedWishlist ? JSON.parse(savedWishlist) : [];
        if (!Array.isArray(userWishlist)) userWishlist = [];
        console.log("Loaded local wishlist:", userWishlist.length, "items");
        console.log("loadUserData called for user:", currentUser?.email);
    console.log("User role:", currentUser?.role);
    } catch (e) {
        console.error("Error loading wishlist from localStorage:", e);
        userWishlist = [];
    }
}

function saveLocalWishlist() {
    try {
        localStorage.setItem('jimmy_wishlist', JSON.stringify(userWishlist));
        console.log('Wishlist saved to localStorage:', userWishlist.length, "items");
        updateWishlistCount();
    } catch (error) {
        console.error('Error saving wishlist to localStorage:', error);
    }
}

// Enhanced wishlist function
async function toggleWishlist(productId, name, price, image) {

    if (!currentUser) {
        const index = userWishlist.findIndex(i => i.id == productId);
        if (index > -1) {
            userWishlist.splice(index, 1);
            showToast(`${name} removed from wishlist`);
        } else {
            userWishlist.push({ id: productId, name, price, image });
            showToast(`${name} added to wishlist`);
        }
        localStorage.setItem('jimmy_wishlist', JSON.stringify(userWishlist));
        updateWishlistCount();
        return;
    }

    const res = await fetch('/jimmy_furniture/api/wishlist.php', {
        method: 'POST',
        credentials: 'include',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ product_id: productId })
    });

    const data = await res.json();
    if (data.success) {
        await loadUserData();
        updateWishlistCount();
        showToast(
            data.action === 'added'
                ? `${name} added to wishlist`
                : `${name} removed from wishlist`
        );
    }
}

// Update wishlist count in UI
function updateWishlistCount() {
    const count = userWishlist.length;

    const wishBadge = document.querySelector('.wishlist-count');
    if (wishBadge) wishBadge.textContent = count;
}

// Check if item is in wishlist
function isInWishlist(productId) {
    return userWishlist.some(item => item.id == productId);
}

// profile for implementation if needed but not important
function viewProfile() {
    window.location.href = 'profile.html';
}

// heroslider
function initHeroSlider() {
    const slider = document.querySelector('.slider-track');
    if (!slider) return; // important: office page has no slider

    let index = 0;
    const slides = slider.children;

    setInterval(() => {
        index = (index + 1) % slides.length;
        slider.style.transform = `translateX(-${index * 100}%)`;
    }, 5000);
}

// ==================== AUTH MODAL FUNCTIONS ====================
function toggleAuth() {
    const modal = document.getElementById('auth-modal');
    if (modal) {
        modal.style.display = modal.style.display === 'flex' ? 'none' : 'flex';
    }
}

function switchTab(tab) {
    const loginForm = document.getElementById('login-form');
    const signupForm = document.getElementById('signup-form');
    const loginBtn = document.getElementById('loginBtn');
    const signupBtn = document.getElementById('signupBtn');
    
    if (!loginForm || !signupForm) return;
    
    if (tab === 'login') {
        loginForm.style.display = 'block';
        signupForm.style.display = 'none';
        if (loginBtn) loginBtn.classList.add('active');
        if (signupBtn) signupBtn.classList.remove('active');
    } else {
        loginForm.style.display = 'none';
        signupForm.style.display = 'block';
        if (signupBtn) signupBtn.classList.add('active');
        if (loginBtn) loginBtn.classList.remove('active');
    }
}

// Enhanced login function
async function handleLogin(event) {
    event.preventDefault();
    
    const email = document.getElementById('login-email').value;
    const password = document.getElementById('login-password').value;
    
    console.log("Login attempt for:", email);
    
    if (!email || !password) {
        showToast("Please enter both email and password");
        return;
    }
    
    try {
        console.log("Sending login request...");
        const response = await fetch('/jimmy_furniture/api/login.php', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email, password })
        });
        
        console.log("Login response status:", response.status);
        const data = await response.json();
        console.log("Login response data:", data);
        
        if (data.success) {
            console.log("Login successful, user:", data.user);
            console.log("User role:", data.user.role); // Add this
            
            currentUser = data.user;
            localStorage.setItem('jimmy_user', JSON.stringify(data.user));
            localStorage.setItem('just_logged_in', 'true');
            
            // IMPORTANT: Clear local cart/wishlist since we'll load from server
            localStorage.removeItem('jimmy_cart');
            localStorage.removeItem('jimmy_wishlist');
            
            console.log("Calling loadUserData...");
            // Load user data from server
            await loadUserData();
            console.log("loadUserData completed");
            
            console.log("Calling updateAuthUI...");
            // Update UI
            updateAuthUI();
            console.log("updateAuthUI completed");
            
            // Close modal and clear form
            toggleAuth();
            document.getElementById('login-form').reset();
            
            // Show welcome message
            console.log("Showing welcome message...");
            showWelcomeMessage(data.user.name);
            console.log("Login flow completed");
            
        } else {
            console.log("Login failed:", data.error);
            showToast(data.error || "Login failed");
        }
    } catch (error) {
        console.error('Login error:', error);
        showToast("Network error. Please try again.");
    }
}

// Enhanced registration function
async function handleRegister(event) {
    event.preventDefault();
    
    const name = document.getElementById('signup-name').value;
    const email = document.getElementById('signup-email').value;
    const password = document.getElementById('signup-password').value;
    const confirm = document.getElementById('signup-confirm').value;
    
    if (!name || !email || !password || !confirm) {
        showToast("Please fill in all fields");
        return;
    }
    
    if (password !== confirm) {
        showToast("Passwords do not match!");
        return;
    }
    
    if (password.length < 6) {
        showToast("Password must be at least 6 characters!");
        return;
    }
    
    try {
        const response = await fetch('/jimmy_furniture/api/register.php', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ name, email, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast("Registration successful! Please login.");
            switchTab('login');
            document.getElementById('signup-form').reset();
        } else {
            showToast(data.error || "Registration failed");
        }
    } catch (error) {
        console.error('Registration error:', error);
        showToast("Network error. Please try again.");
    }
}

// Enhanced logout function
async function logout() {
    if (confirm("Are you sure you want to logout?")) {
        try {
            const response = await fetch('/jimmy_furniture/api/logout.php');
            const data = await response.json();
            
            if (data.success) {
                currentUser = null;
                localStorage.removeItem('jimmy_user');
                
                // Switch back to local storage
                loadLocalCart();
                loadLocalWishlist();
                updateAuthUI();
                
                showToast("You have been logged out.");
            }
        } catch (error) {
            console.error('Logout error:', error);
            showToast("Logout failed. Please try again.");
        }
    }
}

// ==================== ENHANCED UI FUNCTIONS ====================
function updateAuthUI() {
    console.log("updateAuthUI called");
    console.log("Current user:", currentUser);
    console.log("User role:", currentUser?.role);
    console.log("Is courier check:", currentUser?.role === 'courier');
    const authContainer = document.getElementById('auth-status-container');
    if (!authContainer) return;
    
    if (currentUser) {
        
        const isAdmin = currentUser.role === 'admin';
        
        
        const isCourier = currentUser.role === 'courier'; 
        
        // User is logged in - show enhanced profile
        authContainer.innerHTML = `
            <div class="user-dropdown">
                <div class="user-profile-pill" id="user-profile-trigger">
                    <i class="fas fa-user-circle"></i>
                    <span class="user-name">${currentUser.name.split(' ')[0]}</span>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                    ${isAdmin ? '<span class="admin-badge">ADMIN</span>' : ''}
                    ${isCourier ? '<span class="admin-badge" style="background:#28a745;">COURIER</span>' : ''}
                </div>
                <div class="user-dropdown-menu" id="user-dropdown-menu" style="display: none;">
                    <div class="dropdown-header">
                        <strong>${currentUser.name}</strong>
                        <small>${currentUser.email}</small>
                        ${isAdmin ? '<div class="role-badge">Administrator</div>' : ''}
                        ${isCourier ? '<div class="role-badge">Courier</div>' : ''}
                    </div>

                    ${isCourier ? `
                        <a href="courier-panel.html" class="dropdown-item">
                            <i class="fas fa-truck"></i> Courier Panel
                        </a>
                        <div class="dropdown-divider"></div>
                    ` : ''}
                    
                   ${isAdmin ? `
                        <a href="admin-panel.html" class="dropdown-item">
                            <i class="fas fa-cog"></i> Admin Panel
                        </a>
                        <div class="dropdown-divider"></div>
                    ` : ''}
                    
                    <a href="#" class="dropdown-item" onclick="viewProfile()">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                    <a href="my-orders.html" class="dropdown-item">
                        <i class="fas fa-box"></i> My Orders
                    </a>
                    <a href="#" class="dropdown-item" onclick="toggleWishlistModal()">
                        <i class="fas fa-heart"></i> Wishlist
                        <span class="badge wishlist-count">${userWishlist.length}</span>
                    </a>
                    <a href="#" class="dropdown-item" onclick="toggleCartModal()">
                        <i class="fas fa-shopping-cart"></i> Cart
                        <span class="badge cart-count">${userCart.reduce((sum, item) => sum + (item.quantity || 1), 0)}</span>
                    </a>
                    <a href="#" class="dropdown-item" onclick="viewAddresses()">
                        <i class="fas fa-map-marker-alt"></i> Addresses
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item logout" onclick="logout()">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        `;
        
        // Add click event to the trigger after creating the HTML
        setTimeout(() => {
            const trigger = document.getElementById('user-profile-trigger');
            if (trigger) {
                trigger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleUserDropdown();
                });
            }
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                const dropdown = document.getElementById('user-dropdown-menu');
                const trigger = document.getElementById('user-profile-trigger');
                
                if (dropdown && dropdown.style.display === 'block' && 
                    !dropdown.contains(e.target) && 
                    !(trigger && trigger.contains(e.target))) {
                    dropdown.style.display = 'none';
                }
            });
        }, 100);
    } else {
        // User is not logged in
        authContainer.innerHTML = `
            <button class="action-btn" id="user-btn" onclick="toggleAuth()">
                <i class="far fa-user"></i>
                <span class="action-label">Account</span>
            </button>
        `;
    }
    
    // Update cart and wishlist counts
    updateCartCount();
    updateWishlistCount();
}

// Toggle user dropdown
function toggleUserDropdown() {
    const dropdown = document.getElementById('user-dropdown-menu');
    if (!dropdown) return;
    
    if (dropdown.style.display === 'none' || dropdown.style.display === '') {
        dropdown.style.display = 'block';
    } else {
        dropdown.style.display = 'none';
    }
}

// ==================== MODAL FUNCTIONS ====================
function toggleCartModal() {
    const cartModal = document.getElementById('cart-modal');
    if (!cartModal) return;
    
    const isOpening = cartModal.style.display !== 'flex';
    cartModal.style.display = isOpening ? 'flex' : 'none';
    
    if (isOpening) {
        // Refresh cart data before showing modal
        if (!currentUser) {
            loadLocalCart();
        }
        updateCartUI();
    }
}

function toggleWishlistModal() {
    const wishlistModal = document.getElementById('wishlist-modal');
    if (!wishlistModal) return;
    
    const isOpening = wishlistModal.style.display !== 'flex';
    wishlistModal.style.display = isOpening ? 'flex' : 'none';
    
    if (isOpening) {
        // Refresh wishlist data before showing modal
        if (!currentUser) {
            loadLocalWishlist();
        }
        // Note: renderWishlistModal function would need to be defined elsewhere
    }
}

function setupEventListeners() {
    // Login form
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        
        loginForm.addEventListener('submit', handleLogin);
    }

    
    // Signup form
    const signupForm = document.getElementById('signup-form');
    if (signupForm) {
        signupForm.addEventListener('submit', handleRegister);
    }

    const checkoutBtn = document.getElementById('checkout-btn');
    if (checkoutBtn) {
    checkoutBtn.addEventListener('click', showCheckout);
    }
    
    // Modal close buttons
    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.modal').style.display = 'none';
        });
    });
    
    // Close modals on outside click
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });
}

// ==================== UTILITY FUNCTIONS ====================
function showToast(message) {
    console.log("Showing toast:", message);
    console.log("loadUserData called for user:", currentUser?.email);
    console.log("User role:", currentUser?.role);
   
    const toastDiv = document.createElement('div');
    toastDiv.className = 'simple-toast';
    toastDiv.textContent = message;
    toastDiv.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #333;
        color: white;
        padding: 12px 24px;
        border-radius: 4px;
        z-index: 10000;
        animation: toastSlideIn 0.3s ease-out;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        font-family: 'Inter', sans-serif;
    `;
    
    // Remove existing toast
    const existingToast = document.querySelector('.simple-toast');
    if (existingToast) existingToast.remove();
    
    document.body.appendChild(toastDiv);
    
    // Auto remove
    setTimeout(() => {
        toastDiv.style.animation = 'toastSlideOut 0.3s ease-out';
        setTimeout(() => toastDiv.remove(), 300);
    }, 3000);
}


function showCheckout() {
    if (userCart.length === 0) {
        showToast("Your cart is empty");
        return;
    }

    // Close cart modal
    const cartModal = document.getElementById('cart-modal');
    if (cartModal) cartModal.style.display = 'none';

    // Open checkout modal
    const checkoutModal = document.getElementById('checkout-modal');
    if (checkoutModal) checkoutModal.style.display = 'flex';

    // Populate checkout summary
    populateCheckoutSummary();
    
    // Pre-fill form if user is logged in
    if (currentUser) {
        document.getElementById('checkout-firstname').value = currentUser.name.split(' ')[0] || '';
        document.getElementById('checkout-lastname').value = currentUser.name.split(' ').slice(1).join(' ') || '';
        document.getElementById('checkout-email').value = currentUser.email || '';
        document.getElementById('checkout-phone').value = currentUser.phone || '';
        
        // Make email field read-only for logged-in users
        document.getElementById('checkout-email').readOnly = true;
    } else {
        // Clear form for guests
        document.getElementById('checkout-form').reset();
        document.getElementById('checkout-email').readOnly = false;
    }
}
window.showCheckout = showCheckout;

function populateCheckoutSummary() {
    const summaryContainer = document.querySelector('.summary-items');
    if (!summaryContainer) return;

    let subtotal = 0;
    summaryContainer.innerHTML = '';

    userCart.forEach(item => {
        const itemTotal = item.price * item.quantity;
        subtotal += itemTotal;

        summaryContainer.innerHTML += `
            <div class="summary-item">
                <span>${item.name} × ${item.quantity}</span>
                <span>Birr${itemTotal.toFixed(2)}</span>
            </div>
        `;
    });

    const shipping = subtotal > 500 ? 0 : 49.99;
    const discount = userCart.length >= 4 ? subtotal * 0.15 : 0;
    const total = subtotal + shipping - discount;

    document.querySelector('.total-row span:last-child').textContent = `Birr${total.toFixed(2)}`;
}

const checkoutForm = document.getElementById('checkout-form');
if (checkoutForm) {
    checkoutForm.addEventListener('submit', placeOrder);
}

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}
async function placeOrder(event) {
    event.preventDefault();

    // Get form values
    const firstName = document.getElementById('checkout-firstname').value.trim();
    const lastName = document.getElementById('checkout-lastname').value.trim();
    const email = document.getElementById('checkout-email').value.trim();
    const phone = document.getElementById('checkout-phone').value.trim();
    const address = document.getElementById('checkout-address').value.trim();
    const paymentMethod = document.querySelector('input[name="payment"]:checked')?.value;

    // Validation
    if (!firstName || !lastName || !email || !phone || !address || !paymentMethod) {
        showToast("Please complete all required fields");
        return;
    }

    if (!validateEmail(email)) {
        showToast("Please enter a valid email address");
        return;
    }

    try {
        // Prepare order data
        const orderData = {
            name: `${firstName} ${lastName}`,
            email: email,
            phone: phone,
            shipping_address: address,
            payment_method: paymentMethod
        };

        // If user is guest, send cart items from local storage
        if (!currentUser) {
            orderData.cart_items = userCart.map(item => ({
                product_id: item.id,
                quantity: item.quantity || 1
            }));
        }

        const response = await fetch('/jimmy_furniture/api/order.php', {
            method: 'POST',
            credentials: 'include', // Important for session
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(orderData)
        });

        const data = await response.json();

        if (!data.success) {
            showToast(data.error || "Order failed");
            return;
        }

        showToast(`Order ${data.order_number} created successfully!`);
        
        // Store order info for payment
        window.currentOrder = {
            id: data.order_id,
            number: data.order_number,
            amount: data.total_amount,
            payment_method: paymentMethod,
            is_guest: data.is_guest || false,
            email: email
        };

        // Clear cart after successful order
        if (currentUser) {
            // For logged-in users, cart is cleared on server
            userCart = [];
        } else {
            // For guests, clear local cart
            userCart = [];
            localStorage.removeItem('jimmy_cart');
        }
        
        updateCartCount();
        updateCartUI();

        // Handle payment
        if (paymentMethod === 'chapa') {
            await initiateChapaPayment();
        } else if (paymentMethod === 'cod') {
            showToast("Order placed successfully! Cash on Delivery selected.");
            // Close checkout modal
            document.getElementById('checkout-modal').style.display = 'none';
        } else {
            showToast("Payment method not implemented yet");
        }

    } catch (err) {
        console.error(err);
        showToast("Network error. Please try again.");
    }
}
window.placeOrder = placeOrder;

function goToPaymentStep() {
    // Update checkout step UI
    document.querySelectorAll('.checkout-step').forEach(step => {
        step.classList.remove('active');
    });

    document.querySelector('.checkout-step.payment')?.classList.add('active');

    showToast("Proceeding to payment...");
}


async function initiateChapaPayment() {
    if (!window.currentOrder) {
        showToast("Order not found");
        return;
    }

    try {
        const response = await fetch('/jimmy_furniture/api/payments/chapa_init.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order_id: window.currentOrder.id,
                order_number: window.currentOrder.number,
                amount: window.currentOrder.amount,
                email: window.currentOrder.email || (currentUser ? currentUser.email : ''),
                is_guest: window.currentOrder.is_guest || false
            })
        });

        const data = await response.json();

        if (!data.success) {
            showToast("Payment initialization failed");
            return;
        }

        // Redirect to Chapa checkout
        window.location.href = data.checkout_url;

    } catch (err) {
        console.error(err);
        showToast("Payment service unavailable");
    }
}

window.initiateChapaPayment = initiateChapaPayment;


function viewOrders() {
    window.location.href = 'my-orders.html';
}

function viewAddresses() {
    window.location.href = 'addresses.html';
}

// REPLACE the existing initSearch function in x.js with this:

function initSearch() {
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) return;

    console.log('Search initialized');
    searchInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase().trim();// REPLACE the existing initSearch function in x.js with this:

function initSearch() {
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) return;

    console.log('Search initialized');
    searchInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase().trim();
        
        const products = document.querySelectorAll('.product-card');

        products.forEach(product => {
            const productText = product.textContent || product.innerText;
            if (productText.toLowerCase().includes(searchTerm)) {
                product.style.display = ""; // Show
            } else {
                product.style.display = "none"; // Hide
            }
        });
    });
}
        const products = document.querySelectorAll('.product-card');

        products.forEach(product => {
            // Get the text content of the card (title, price, description)
            const productText = product.textContent || product.innerText;

            // Show or Hide based on match
            if (productText.toLowerCase().includes(searchTerm)) {
                product.style.display = ""; // Show
            } else {
                product.style.display = "none"; // Hide
            }
        });
    });
}

function showWelcomeMessage(name) {
    showToast(`Welcome back, ${name}!`);
     console.log("Current user:", currentUser);
    console.log("User role:", currentUser?.role);
    console.log("Is courier check:", currentUser?.role === 'courier');
}
// ==================== GLOBAL FUNCTIONS ====================
// Make all functions globally accessible
window.toggleAuth = toggleAuth;
window.switchTab = switchTab;
window.logout = logout;
window.toggleWishlistModal = toggleWishlistModal;
window.toggleUserDropdown = toggleUserDropdown;
window.viewProfile = viewProfile;
window.viewOrders = viewOrders;
window.viewAddresses = viewAddresses;
window.addToCart = addToCart;
window.toggleWishlist = toggleWishlist;
window.updateCartQuantity = updateCartQuantity;
window.removeFromCart = removeFromCart;
window.toggleCartModal = toggleCartModal;

// Initialize on page load
window.addEventListener('load', function() {
    loadLocalCart();     
    loadLocalWishlist();   
    updateCartCount();    
    updateWishlistCount(); 
});