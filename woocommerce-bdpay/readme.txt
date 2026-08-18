=== Payment Aggregator & Gateway Indonesia (PAGI) ===
Contributors: Wiriasto @2026
Tags: payment, gateway, indonesia, qris, virtual account, dana, ovo, bdpay
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 1.0
License: GPLv2 or later

Payment Gateway Indonesia untuk Virtual Account, QRIS, DANA, OVO via BDPay Open API.

== Description ==

Plugin resmi-style untuk menerima pembayaran di WooCommerce menggunakan BDPay:

* Virtual Account (BCA, Mandiri, BNI, BRI, Permata, CIMB, Danamon, dll)
* QRIS (semua e-Wallet & bank)
* e-Wallet DANA & OVO
* Alfamart (opsional)

Fitur:
* Form settings admin lengkap (Merchant Code, Key, Environment)
* Pilihan metode di checkout
* Callback/Webhook otomatis update status order
* Support Sandbox & Production

== Installation ==

1. Upload folder `woocommerce-bdpay` ke `/wp-content/plugins/`
2. Aktifkan plugin
3. WooCommerce → Settings → Payments → BDPay → isi kredensial dari dashboard BDPay
4. Salin Callback URL ke dashboard BDPay
5. Aktifkan metode yang diinginkan

== Configuration ==

Daftar merchant di https://bdpay.co.id
Minta akses Sandbox + dokumentasi signature yang akurat.
Sesuaikan fungsi generate_signature() di class-bdpay-api.php sesuai dokumentasi resmi.

== Changelog ==

= 1.0.0 =
* Initial release
* Support VA, QRIS, e-Wallet
* REST callback endpoint
