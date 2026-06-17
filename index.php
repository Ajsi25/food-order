<!DOCTYPE html>
<html lang="sq">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Order - Porosi Online</title>
    <link rel="icon" href="data:,">
    <link rel="preconnect" href="https://i.pinimg.com">
    <link rel="preload" as="image" href="https://i.pinimg.com/736x/ec/ac/25/ecac25b0c126a4131635d1a6ae76f3e0.jpg" fetchpriority="high">
    <link rel="stylesheet" href="frontend/css/style.css">
</head>

<body>
    <nav class="navbar">
        <div class="container nav-inner">
            <a href="#" class="logo-link">
                <span class="logo">FoodOrder</span>
            </a>

            <div class="nav-links" id="navLinks">
                <a href="#menu">Menu</a>
                <a href="#contact-section">Kontakt</a>
                <button class="cart-btn" onclick="toggleCart()">
                    Shporta <span class="cart-count" id="cartCount">0</span>
                </button>
            </div>

            <button class="hamburger" id="hamburgerBtn" onclick="toggleMenu()" aria-label="Hap menune">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-bg"></div>
        <div class="container hero-content">
            <h1>Porosit Ushqimin Tënd</h1>
            <p>Ushqim i shijshëm, i dorëzuar shpejt deri te dera jote.</p>
            <a href="#menu" class="btn-hero">Shiko Menunë</a>
        </div>
    </section>

    <section id="menu" class="menu-section">
        <div class="container">
            <div class="search-bar">
                <input type="text" id="searchInput" placeholder="Kërko ushqim...">
            </div>

            <div class="categories" id="categoriesContainer"></div>

            <div class="food-grid" id="foodGrid"></div>
        </div>
    </section>

    <section id="order-section" class="order-section" style="display:none">
        <div class="container">
            <h2>Detajet e Porosisë</h2>

            <div class="order-layout">
                <div class="order-summary" id="orderSummary"></div>

                <form class="order-form" id="orderForm">
                    <div class="form-group">
                        <label>Emri Juaj *</label>
                        <input type="text" id="customerName" required placeholder="p.sh. Arjona Hoxha">
                    </div>

                    <div class="form-group">
                        <label>Numri i Telefonit *</label>
                        <input type="tel" id="customerPhone" required placeholder="+355 6X XXX XXXX">
                    </div>

                    <div class="form-group">
                        <label>Adresa *</label>
                        <input type="text" id="customerAddress" required placeholder="Rruga, Numri, Qyteti">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Mënyra e Pagesës *</label>
                            <select id="paymentMethod" required>
                                <option value="Cash">Cash në dorëzim</option>
                                <option value="Card">Kartë bankare</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Orari i Dorëzimit</label>
                            <input type="time" id="deliveryTime">
                        </div>
                    </div>

                    <div class="card-payment-box" id="cardPaymentBox" style="display:none">
                        <div class="form-group">
                            <label>Emri në kartë *</label>
                            <input type="text" id="cardName" placeholder="p.sh. Ajsi Tota">
                        </div>

                        <div class="form-group">
                            <label>Numri i kartës *</label>
                            <input type="text" id="cardNumber" inputmode="numeric" maxlength="19" placeholder="4242 4242 4242 4242">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Skadenca *</label>
                                <input type="text" id="cardExpiry" maxlength="5" placeholder="MM/YY">
                            </div>

                            <div class="form-group">
                                <label>CVV *</label>
                                <input type="password" id="cardCvv" inputmode="numeric" maxlength="4" placeholder="123">
                            </div>
                        </div>

                        <p class="payment-note">Pagesa me kartë është demo për projektin dhe nuk kryen transaksion real.</p>
                    </div>

                    <div class="form-group">
                        <label>Shënime</label>
                        <textarea id="customerNotes" rows="3" placeholder="Alergjitë, preferencat..."></textarea>
                    </div>

                    <button type="submit" class="btn-submit">Konfirmo Porosinë</button>
                </form>
            </div>
        </div>
    </section>

    <section class="contact-section" id="contact-section">
        <div class="container contact-grid">
            <div>
                <span class="section-kicker">Kontakt</span>
                <h2>Jemi gati për porosinë tënde</h2>
                <p>Për pyetje rreth porosive, dërgesës ose ndryshimeve në menu mund të na kontaktosh gjatë orarit të punës.</p>
            </div>

            <div class="contact-card">
                <div><strong>Telefon</strong><span>+355 69 000 0000</span></div>
                <div><strong>Email</strong><span>info@foodorder.test</span></div>
                <div><strong>Orari</strong><span>Çdo ditë, 10:00 - 23:00</span></div>
            </div>
        </div>
    </section>

    <div class="cart-overlay" id="cartOverlay" onclick="toggleCart()"></div>

    <aside class="cart-sidebar" id="cartSidebar">
        <div class="cart-header">
            <h3>Shporta</h3>
            <button onclick="toggleCart()">X</button>
        </div>

        <div class="cart-items" id="cartItems"></div>

        <div class="cart-footer">
            <div class="cart-total" id="cartTotal">Totali: 0 L</div>
            <button class="btn-checkout" id="checkoutBtn" onclick="goToOrder()" disabled>Vazhdo me Porosinë</button>
        </div>
    </aside>

    <div class="modal-overlay" id="successModal">
        <div class="modal-box">
            <div class="success-icon">OK</div>
            <h2>Porosia u vendos!</h2>
            <p>Faleminderit! Porosia juaj u regjistrua me sukses. Do ju kontaktojmë së shpejti.</p>
            <button class="btn-submit" onclick="resetOrder()">Porosit Sërish</button>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>FoodOrder &copy; 2026 - Të gjitha të drejtat e rezervuara</p>
        </div>
    </footer>

    <script type="module" src="frontend/js/firebase-config.js"></script>
    <script src="frontend/js/app.js" defer></script>
</body>

</html>
