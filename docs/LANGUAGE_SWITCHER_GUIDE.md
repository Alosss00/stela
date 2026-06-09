# Language Switcher Documentation / Dokumentasi Pengalihan Bahasa

## Fitur / Features
- Tombol untuk switch antara Bahasa Indonesia dan English
- Button to switch between Indonesian and English
- Menyimpan preferensi bahasa di localStorage (persistent)
- Language preference saved in localStorage (persistent)
- Desain yang modern dan responsif
- Modern and responsive design
- **Terintegrasi di header - di sebelah kanan tanggal**
- **Integrated in header - next to the date on the right**

## Posisi Tombol / Button Position
- **Halaman dengan Header (Dashboard, Reports, dll)**: Tombol berada di topbar header, di sebelah kanan tanggal
- **Pages with Header (Dashboard, Reports, etc)**: Button is in the topbar header, to the right of the date
- **Halaman Login**: Tombol berada di pojok kanan atas (fixed position)
- **Login Page**: Button is in the top-right corner (fixed position)

## Cara Menggunakan / How to Use

### 1. Include CSS dan JavaScript di halaman Anda

```html
<!-- Di dalam <head> -->
<link rel="stylesheet" href="css/language-switcher.css">

<!-- Sebelum </body> -->
<script src="js/language-switcher.js"></script>
```

### 2. Tambahkan Tombol Language Switcher

**Untuk halaman login (index.php) - Fixed Position:**
```html
<div class="language-switcher">
    <button id="languageToggle" type="button">
        <span class="flag-icon">🇬🇧</span> EN
    </button>
</div>
```

**Untuk halaman dashboard/admin (sudah otomatis ada di header.php):**
Tombol sudah terintegrasi di `includes/header.php` di bagian topbar, sehingga semua halaman yang menggunakan header.php otomatis memiliki tombol language switcher di sebelah kanan tanggal.

```html
<!-- Di topbar-right, sebelah kanan tanggal -->
<div class="topbar-right">
    <span class="date"><?php echo date('d F Y'); ?></span>
    <button id="languageToggle" class="language-toggle-btn" type="button">
        <span class="flag-icon">🇬🇧</span> EN
    </button>
</div>
```

### 3. Tambahkan Atribut data-lang pada Elemen yang Perlu Diterjemahkan

```html
<!-- Untuk teks biasa -->
<h1 data-lang="welcome">Welcome to</h1>
<p data-lang="login-subtitle">Login below to get started.</p>

<!-- Untuk placeholder input -->
<input type="text" placeholder="E-mail Address" data-lang="email-placeholder">

<!-- Untuk button -->
<button data-lang="login-button">Login</button>
```

### 4. Menambahkan Terjemahan Baru

Edit file `js/language-switcher.js` dan tambahkan key baru di objek `translations`:

```javascript
const translations = {
    en: {
        'your-new-key': 'English Text',
        // ... other translations
    },
    id: {
        'your-new-key': 'Teks Indonesia',
        // ... other translations
    }
};
```

## Fungsi yang Tersedia / Available Functions

### changeLanguage(lang)
Mengganti bahasa ke 'en' atau 'id'
```javascript
changeLanguage('en'); // Switch to English
changeLanguage('id'); // Switch to Indonesian
```

### toggleLanguage()
Toggle antara bahasa saat ini ke bahasa lainnya
```javascript
toggleLanguage(); // Switch between EN <-> ID
```

### getCurrentLanguage()
Mendapatkan bahasa yang sedang aktif
```javascript
const lang = getCurrentLanguage(); // Returns 'en' or 'id'
```

## Customisasi Tombol / Button Customization

### Mengubah Warna Tombol di Topbar
Edit file `css/language-switcher.css` untuk tombol di header:

```css
.language-toggle-btn {
    background: rgba(255, 195, 0, 0.15);
    border-color: #FFC300;
    color: #FFC300;
}

.language-toggle-btn:hover {
    background: #FFC300;
    color: #37474F;
}
```

### Mengubah Warna Tombol di Login Page
Edit file `css/language-switcher.css` untuk tombol di login page:

```css
.login-page #languageToggle {
    background: rgba(255, 255, 255, 0.95);
    border-color: #FF8C00;
    color: #FF8C00;
}
```

## Contoh Implementasi / Implementation Example

**File `includes/header.php`** sudah diupdate dengan tombol language switcher di topbar (sebelah kanan tanggal).

**File `index.php`** (login page) memiliki tombol language switcher di pojok kanan atas dengan fixed position.

Anda bisa melihat:
- Tombol language switcher di topbar header (sebelah kanan tanggal) untuk semua halaman dashboard
- Tombol language switcher di pojok kanan atas untuk login page
- Atribut data-lang pada teks yang diterjemahkan
- Include CSS dan JS yang diperlukan

## Tips
- Bahasa default adalah Bahasa Indonesia (`id`)
- Preferensi bahasa disimpan di localStorage browser
- Ketika user kembali, bahasa yang terakhir dipilih akan otomatis digunakan
- Tombol akan menampilkan bendera dan kode bahasa yang akan dipilih jika diklik
- Tombol di header terintegrasi dengan warna topbar (dark background dengan accent kuning/orange)
- **Tombol otomatis ada di SEMUA halaman** yang menggunakan `includes/header.php`

## Browser Support
- Chrome, Firefox, Safari, Edge (versi terbaru)
- Requires ES6 support
- LocalStorage support required
