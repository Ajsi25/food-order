# 🍽️ Food Order Website
**PHP + Firebase Firestore**

---

## 📁 Struktura e Projektit

```
food-order/
├── config/
│   ├── firebase.php       # Konfigurimi Firebase + helper functions
│   └── auth.php           # Sesionet dhe autentikimi
├── admin/
│   ├── css/admin.css
│   ├── js/admin.js
│   ├── partials/sidebar.php
│   ├── login.php          # Hyrja e adminit
│   ├── dashboard.php      # Paneli kryesor me statistika
│   ├── admins.php         # CRUD adminë
│   ├── categories.php     # CRUD kategori
│   ├── foods.php          # CRUD ushqime
│   ├── orders.php         # Menaxhimi i porosive
│   └── logout.php
├── api/
│   ├── foods.php          # GET foods (me kërkim + filtrim)
│   ├── categories.php     # GET categories
│   └── orders.php         # POST order / GET orders
├── frontend/
│   ├── css/style.css
│   └── js/app.js
├── uploads/               # Imazhet e ushqimeve
└── index.php              # Faqja kryesore (frontend)
```

---

## ⚙️ Setup

### 1. Firebase Console
1. Shko te [console.firebase.google.com](https://console.firebase.google.com)
2. Krijo projekt të ri
3. Aktivizo **Firestore Database** (mode: test)
4. Shko te **Project Settings → General** dhe kopjo:
   - `Project ID`
   - `Web API Key`

### 2. Konfiguro `config/firebase.php`
```php
define('FIREBASE_PROJECT_ID', 'your-project-id');  // ← ndrysho këtu
define('FIREBASE_API_KEY',    'your-api-key');      // ← ndrysho këtu
```

### 3. Koleksionet Firebase (krijohen automatikisht)
- `admins`
- `categories`
- `foods`
- `orders`

### 4. Shto adminin e parë
Drejtpërdrejt nga Firebase Console → Firestore → shto dokument në koleksionin `admins`:
```
name:       "Admin"
email:      "admin@test.com"
password:   [hash i gjeneruar nga PHP]
created_at: "2024-01-01 00:00:00"
```

Për të gjeneruar hash-in e fjalëkalimit, ekzekuto këtë PHP:
```php
echo password_hash('fjalekalimi123', PASSWORD_BCRYPT);
```

### 5. XAMPP / Server lokal
- Vendos projektin te `htdocs/food-order/`
- Hap: `http://localhost/food-order/`
- Admin: `http://localhost/food-order/admin/login.php`

---

## 🔗 API Endpoints

| Metoda | URL | Përshkrimi |
|--------|-----|------------|
| GET | `/api/categories.php` | Të gjitha kategoritë |
| GET | `/api/foods.php` | Të gjitha ushqimet |
| GET | `/api/foods.php?category=ID` | Ushqime sipas kategorisë |
| GET | `/api/foods.php?search=burger` | Kërkim ushqimesh |
| POST | `/api/orders.php` | Krijo porosi të re |
| GET | `/api/orders.php` | Të gjitha porositë |

### Shembull POST `/api/orders.php`
```json
{
  "customer_name": "Arjona Hoxha",
  "phone": "+355 69 123 4567",
  "address": "Rruga e Elbasanit, Nr.5, Tiranë",
  "notes": "Pa qepë",
  "items": [
    { "id": "abc123", "name": "Burger", "price": 350, "qty": 2 },
    { "id": "def456", "name": "Pica", "price": 500, "qty": 1 }
  ]
}
```

---

## ✅ Funksionalitetet

### Frontend
- [x] Shfaqja e kategorive dhe ushqimeve nga Firebase
- [x] Kërkim i ushqimeve në kohë reale
- [x] Filtrim sipas kategorisë
- [x] Shportë (localStorage)
- [x] Formular porosie me validim
- [x] Dizajn responsive (mobile-friendly)

### Admin Panel
- [x] Login me autentikim (bcrypt)
- [x] Dashboard me statistika
- [x] CRUD i plotë për adminë
- [x] CRUD i plotë për kategori
- [x] CRUD i plotë për ushqime (me ngarkimin e imazheve)
- [x] Menaxhimi i porosive + ndryshimi i statusit

### Backend / API
- [x] REST API me format JSON
- [x] Lidhja me Firebase Firestore (REST)
- [x] Validim i të dhënave
- [x] CORS headers
