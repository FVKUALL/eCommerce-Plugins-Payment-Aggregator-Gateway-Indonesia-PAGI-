<?php
/**
 * Standalone test: Verifikasi logika callback BDPay
 * Jalankan: php test-callback-verify.php
 *
 * Tidak butuh koneksi API nyata — hanya menguji parsing + status mapping.
 * Untuk uji signature RSA butuh public/private key pair.
 */

require_once __DIR__ . '/BDPaySignature.php';
require_once __DIR__ . '/BDPayCallbackVerifier.php';

echo "=== BDPay Callback Verification Test ===\n\n";

// Sample success payload (tanpa signature valid)
$successPayload = [
    'platRespCode'  => 'SUCCESS',
    'platRespMessage' => 'success',
    'platOrderNum'  => 'BCA_1234567890',
    'orderNum'      => '1001-1712345678',
    'name'          => 'Asmana',
    'email'         => 'user@bdpay.co.id',
    'payMoney'      => '10000',
    'platSign'      => '', // kosong → akan gagal jika requireSign=true & public key ada
];

echo "1) Parse payload\n";
$parsed = BDPayCallbackVerifier::parsePayload(json_encode($successPayload));
echo "   orderNum = " . ($parsed['orderNum'] ?? '-') . "\n";
echo "   status   = " . ($parsed['platRespCode'] ?? '-') . "\n\n";

echo "2) Verify TANPA public key + requireSign=false (dev mode)\n";
$v = BDPayCallbackVerifier::verify($successPayload, '', false);
echo "   valid      = " . ($v['valid'] ? 'YES' : 'NO') . "\n";
echo "   is_success = " . ($v['is_success'] ? 'YES' : 'NO') . "\n";
echo "   message    = " . $v['message'] . "\n";
echo "   order_id   = " . BDPayCallbackVerifier::extractOrderId($v['order_num']) . "\n\n";

echo "3) Verify DENGAN requireSign=true tanpa public key (harus gagal)\n";
$v2 = BDPayCallbackVerifier::verify($successPayload, '', true);
echo "   valid   = " . ($v2['valid'] ? 'YES' : 'NO') . "\n";
echo "   message = " . $v2['message'] . "\n\n";

echo "4) Failed status mapping\n";
$failPayload = array_merge($successPayload, ['platRespCode' => 'EXPIRED']);
$v3 = BDPayCallbackVerifier::verify($failPayload, '', false);
echo "   is_failed = " . ($v3['is_failed'] ? 'YES' : 'NO') . "\n";
echo "   message   = " . $v3['message'] . "\n\n";

echo "5) String-to-sign sample (untuk debug signature)\n";
$sample = [
    'merchantCode' => 'S2020198627344',
    'orderNum'     => 'T202007183457183',
    'payMoney'     => '10000',
    'method'       => 'QRIS',
];
echo "   values-only : " . BDPaySignature::buildStringToSign($sample) . "\n";
echo "   key=value&  : " . BDPaySignature::buildStringToSignKeyValue($sample) . "\n\n";

echo "6) HMAC generate (jika pakai secret pendek)\n";
$hmacSign = BDPaySignature::generate($sample, 'my-secret-key-12345', 'HMAC');
echo "   sign = " . $hmacSign . "\n\n";

echo "=== Selesai ===\n";
echo "Production: set Public Key platform + requireSign=true.\n";
echo "Callback harus selalu membalas body \"OK\" dengan HTTP 200 jika proses sukses.\n";
