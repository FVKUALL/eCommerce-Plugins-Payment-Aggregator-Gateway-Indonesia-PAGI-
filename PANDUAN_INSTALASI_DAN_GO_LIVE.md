# Panduan Lengkap Instalasi & Go Live — Payment Aggregator & Gateway Indonesia (PAGI)

**Versi:** 1.0  
**Developer:** Wiriasto © 2026  
**License:** GPL-2.0-or-later  
**Fitur:** Virtual Account · QRIS · e-Wallet (DANA / OVO)  
**CMS yang didukung:** WooCommerce · OpenCart · Bagisto · PrestaShop · Magento 2

---

## Daftar Isi

1. [Daftar Merchant BDPay](#1-daftar-merchant-bdpay)
2. [Persiapan Kredensial](#2-persiapan-kredensial)
3. [Struktur Paket Plugin](#3-struktur-paket-plugin)
4. [Instalasi per CMS](#4-instalasi-per-cms)
5. [Konfigurasi Callback URL](#5-konfigurasi-callback-url)
6. [Uji Sandbox](#6-uji-sandbox)
7. [Go Live (Production)](#7-go-live-production)
8. [Troubleshooting](#8-troubleshooting)
9. [Kontak & Referensi](#9-kontak--referensi)

---

## 1. Daftar Merchant BDPay

Sebelum memasang plugin, Anda **wajib** memiliki akun merchant BDPay.

### URL resmi BDPay

| Keperluan | Alamat URL |
|-----------|------------|
| **Website resmi** | https://bdpay.co.id |
| **Payment Gateway / solusi bisnis** | https://bdpay.co.id/payment-gateway |
| **Lisensi & sertifikasi** | https://bdpay.co.id/license |
| **Dokumentasi teknis Open API** | https://dc.bdpay.co.id/docs/ |
| **Dokumentasi API (alternatif)** | https://document.bdpay.co.id/docs/api/ |
| **Open API (Sandbox)** | https://dev-openapi.bdpay.co.id |
| **Open API (Production)** | https://openapi.bdpay.co.id |

### Cara mendaftar merchant

1. Buka **https://bdpay.co.id** atau **https://bdpay.co.id/payment-gateway**
2. Klik tombol **Join now** / hubungi tim BDPay (biasanya melalui formulir atau CS)
3. Siapkan dokumen umum:
   - KTP / identitas penanggung jawab
   - NPWP (jika ada)
   - NIB / legalitas usaha (untuk badan usaha)
   - Rekening bank atas nama usaha / pemilik
   - Data usaha (nama toko, alamat, kategori)
4. Minta akses **Sandbox** + **Merchant Code**, **Private Key / Secret Key**, dan **Public Key platform**
5. Setelah disetujui, Anda akan mendapat kredensial Sandbox untuk development
6. Setelah testing sukses, ajukan aktivasi **Production**

> **Catatan:** Proses onboarding BDPay biasanya melibatkan kontak langsung dengan tim bisnis/teknis mereka (bukan self-service penuh seperti beberapa gateway lain). Simpan email CS / WA yang diberikan.

**Pembaruan:** Proses onboarding BDPay melalui self-service penuh gunakan alamat: **https://dev-admin.bdpay.co.id**

**Perusahaan:** PT Berkah Digital Pembayaran  
**Alamat:** Gedung Royal Square Lt. 3A, Jl. Raya Menganti No.479, Surabaya, Jawa Timur 60227  
**Email support (referensi package):** support@bdpay.co.id

---

## 2. Persiapan Kredensial

Setelah akun merchant aktif, kumpulkan data berikut:

| Field di plugin | Keterangan |
|-----------------|------------|
| **Merchant Code** | Kode unik merchant (contoh: `S2020198627344`) |
| **Secret Key / Private Key** | Key untuk membuat signature request (PEM RSA atau secret HMAC) |
| **Public Key** | Public key **platform BDPay** untuk verifikasi `platSign` di callback |
| **Environment** | `sandbox` atau `production` |
| **Signature Algorithm** | `RSA` (direkomendasikan) atau `HMAC` |

Simpan key dengan aman. Jangan commit ke Git publik.

---

## 3. Struktur Paket Plugin

```
bdpay-plugins/
├── PANDUAN_INSTALASI_DAN_GO_LIVE.md   ← file ini
├── README.md
├── shared/                            ← helper signature + callback verifier
│   ├── BDPaySignature.php
│   ├── BDPayCallbackVerifier.php
│   └── test-callback-verify.php
├── woocommerce-bdpay/                 ← Plugin WordPress/WooCommerce
├── opencart-bdpay/                    ← Extension OpenCart
├── bagisto-bdpay/                     ← Package Bagisto (Laravel)
├── prestashop-bdpay/                  ← Module PrestaShop
└── magento-bdpay/                     ← Module Magento 2
```

Setiap folder CMS sudah berisi kode siap pakai (signature + callback verification).

---

## 4. Instalasi per CMS

### 4.1 WooCommerce (WordPress)

1. Zip folder `woocommerce-bdpay` (atau gunakan yang sudah disediakan).
2. WordPress Admin → **Plugins → Add New → Upload Plugin**.
3. Aktifkan **BDPay Payment Gateway**.
4. **WooCommerce → Settings → Payments → BDPay**.
5. Isi:
   - Enable = Ya
   - Environment = Sandbox
   - Merchant Code, Secret Key, Public Key
   - Centang metode: QRIS, VA bank, DANA, OVO
6. Salin **Callback URL** yang ditampilkan (contoh: `https://tokoanda.com/wp-json/bdpay/v1/callback`).
7. Simpan.

### 4.2 OpenCart

1. Extract isi `opencart-bdpay` ke root instalasi OpenCart (merge folder `admin/` dan `catalog/`).
2. Admin → **Extensions → Extensions → Payments**.
3. Cari **BDPay** → **Install** → **Edit**.
4. Isi status, environment, merchant code, secret key, public key, metode, order status.
5. Callback: `https://tokoanda.com/index.php?route=extension/payment/bdpay/callback`
6. Save & enable.

### 4.3 Bagisto

1. Letakkan package di `packages/Webkul/BDPay` (atau sesuaikan namespace) **atau** install via Composer path repository mengarah ke folder `bagisto-bdpay`.
2. Daftarkan service provider di `config/app.php` / `bootstrap/providers.php` jika belum otomatis.
3. Jalankan:
   ```bash
   php artisan config:cache
   php artisan route:list | grep bdpay
   ```
4. Admin Bagisto → **Configuration → Sales → Payment Methods → BDPay**.
5. Isi kredensial.
6. Callback: `https://tokoanda.com/bdpay/callback`

### 4.4 PrestaShop

1. Zip folder dalam `prestashop-bdpay/bdpay` (modul bernama `bdpay`).
2. Back Office → **Modules → Module Manager → Upload a module**.
3. Install & Configure **BDPay**.
4. Isi Environment, Merchant Code, Secret/Private Key, Public Key, algoritma signature, metode aktif.
5. Callback: `https://tokoanda.com/module/bdpay/callback`  
   (atau format pretty URL sesuai setting PrestaShop Anda)

### 4.5 Magento 2 / Adobe Commerce

1. Copy folder `BDPay` ke `app/code/BDPay` (hasilnya: `app/code/BDPay/Payment/`).
2. Jalankan:
   ```bash
   bin/magento module:enable BDPay_Payment
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   bin/magento cache:flush
   ```
3. Admin → **Stores → Configuration → Sales → Payment Methods → BDPay Payment Gateway**.
4. Isi Enabled, Environment, Merchant Code, Secret Key, Public Key, Signature Algorithm.
5. Callback: `https://tokoanda.com/bdpay/payment/callback`

---

## 5. Konfigurasi Callback URL

Di dashboard merchant BDPay, set **Notify URL / Callback URL** sesuai CMS:

| CMS | Contoh Callback URL |
|-----|---------------------|
| WooCommerce | `https://domain.com/wp-json/bdpay/v1/callback` |
| OpenCart | `https://domain.com/index.php?route=extension/payment/bdpay/callback` |
| Bagisto | `https://domain.com/bdpay/callback` |
| PrestaShop | `https://domain.com/module/bdpay/callback` |
| Magento 2 | `https://domain.com/bdpay/payment/callback` |

**Syarat:** URL harus HTTPS (production), dapat diakses publik, dan mengembalikan body `OK` + HTTP 200 jika sukses.

Plugin sudah memverifikasi `platSign` (jika Public Key diisi) sebelum mengubah status order.

---

## 6. Uji Sandbox

1. Set Environment = **Sandbox** di plugin.
2. Buat order test dengan metode QRIS / VA / e-Wallet.
3. Selesaikan pembayaran di halaman/QR yang diberikan BDPay (ikuti instruksi Sandbox mereka).
4. Pastikan:
   - Callback masuk (cek log server / order note)
   - Status order berubah menjadi **paid / processing**
5. Opsional — uji helper lokal:
   ```bash
   cd shared
   php test-callback-verify.php
   ```

Jika signature gagal: cocokkan format Private/Public Key (PEM) dan coba mode string-to-sign alternatif di `BDPaySignature.php` (`buildStringToSignKeyValue`).

---

## 7. Go Live (Production)

Checklist sebelum live:

- [ ] Merchant Production sudah disetujui BDPay
- [ ] Merchant Code, Private Key, Public Key **production** diisi di plugin
- [ ] Environment diganti ke **Production**
- [ ] Callback URL production terdaftar di dashboard BDPay
- [ ] HTTPS aktif di seluruh toko
- [ ] Test 1 transaksi kecil (QRIS/VA) di production
- [ ] Cek settlement / pencairan dana di dashboard BDPay
- [ ] Backup database & file plugin

Setelah Environment = Production, plugin **memaksa** verifikasi signature (lebih aman).

---

## 8. Troubleshooting

| Masalah | Solusi |
|---------|--------|
| Signature invalid | Pastikan Private Key benar (PEM). Coba algoritma RSA vs HMAC. Hubungi BDPay untuk contoh string-to-sign resmi. |
| Callback tidak masuk | Cek firewall, HTTPS, URL di dashboard BDPay, log server (`error_log`). |
| Order tidak update | Pastikan `orderNum` format `ID-timestamp` masih bisa di-extract; cek meta `_bdpay_order_num`. |
| QR/VA kosong | Response field mungkin berbeda nama (`vaNumber` / `qrString`). Sesuaikan parsing di API class. |
| SSL/cURL error | Pastikan server punya CA bundle up-to-date. |

---

## 9. Kontak & Referensi

- **Website BDPay:** https://bdpay.co.id  
- **Payment Gateway page:** https://bdpay.co.id/payment-gateway  
- **Dokumentasi API:** https://dc.bdpay.co.id/docs/  
- **Support (referensi):** support@bdpay.co.id  
- **Kantor:** Gedung Royal Square Lt. 3A, Jl. Raya Menganti No.479, Surabaya

---

**Selamat go live.**  
Setelah kredensial resmi diterima dari BDPay, sesuaikan signature jika dokumentasi internal mereka berbeda sedikit dari helper default — helper sudah disiapkan agar mudah diganti.
