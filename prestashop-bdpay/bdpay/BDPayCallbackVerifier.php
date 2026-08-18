<?php
/**
 * Payment Aggregator & Gateway Indonesia (PAGI)
 *
 * @author    Wiriasto
 * @copyright 2026 Wiriasto
 * @license   GPL-2.0-or-later
 * @version   1.0
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

/**
 * BDPay Callback Verifier
 * Verifikasi notifikasi/callback dari BDPay Open API.
 *
 * Alur resmi (dari dokumentasi BDPay):
 * 1. Terima POST JSON (atau form) dari BDPay ke notifyUrl
 * 2. Verifikasi platSign dengan Public Key platform (RSA-SHA256)
 * 3. Cek platRespCode / status
 * 4. Update order hanya jika signature valid
 * 5. Selalu balas "OK" / HTTP 200 agar BDPay tidak retry berlebihan
 *
 * Docs: https://dc.bdpay.co.id/docs/
 */

require_once __DIR__ . '/BDPaySignature.php';

class BDPayCallbackVerifier
{
    /** Status sukses yang diterima */
    public const SUCCESS_STATUSES = ['SUCCESS', 'PAID', 'SETTLED', '00', 'SUCCESSFUL'];

    /** Status gagal / expired */
    public const FAIL_STATUSES = ['FAIL', 'FAILED', 'EXPIRED', 'CANCEL', 'CANCELLED', 'ERROR', 'UNKNOWN'];

    /**
     * Parse raw body (JSON atau form-urlencoded) menjadi array
     */
    public static function parsePayload($raw = null, array $post = []): array
    {
        if ($raw === null) {
            $raw = file_get_contents('php://input');
        }
        $data = [];
        if ($raw) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $data = $json;
            } elseif (is_string($raw) && strpos($raw, '=') !== false) {
                parse_str($raw, $data);
            }
        }
        if (empty($data) && !empty($post)) {
            $data = $post;
        }
        return is_array($data) ? $data : [];
    }

    /**
     * Verifikasi lengkap callback
     *
     * @param array  $data           Payload callback
     * @param string $publicKey      Platform public key (PEM atau base64)
     * @param bool   $requireSign    Wajib ada platSign (true = production recommended)
     * @return array{
     *   valid: bool,
     *   status: string,
     *   order_num: string,
     *   plat_order_num: string,
     *   amount: string,
     *   is_success: bool,
     *   is_failed: bool,
     *   message: string,
     *   data: array
     * }
     */
    public static function verify(array $data, string $publicKey = '', bool $requireSign = true): array
    {
        $result = [
            'valid'          => false,
            'status'         => '',
            'order_num'      => '',
            'plat_order_num' => '',
            'amount'         => '',
            'is_success'     => false,
            'is_failed'      => false,
            'message'        => '',
            'data'           => $data,
        ];

        if (empty($data)) {
            $result['message'] = 'Empty callback payload';
            return $result;
        }

        $result['order_num']      = (string) ($data['orderNum'] ?? $data['order_id'] ?? $data['order_num'] ?? '');
        $result['plat_order_num'] = (string) ($data['platOrderNum'] ?? $data['plat_order_num'] ?? '');
        $result['amount']         = (string) ($data['payMoney'] ?? $data['amount'] ?? $data['pay_money'] ?? '');
        $status = strtoupper((string) ($data['platRespCode'] ?? $data['status'] ?? $data['payStatus'] ?? ''));
        $result['status'] = $status;

        // 1. Signature verification
        $hasSign = !empty($data['platSign']) || !empty($data['sign']);
        if ($requireSign && $publicKey !== '') {
            if (!$hasSign) {
                $result['message'] = 'Missing platSign';
                return $result;
            }
            if (!BDPaySignature::verify($data, $publicKey)) {
                // Coba alternatif string-to-sign (key=value&)
                if (!self::verifyKeyValueStyle($data, $publicKey)) {
                    $result['message'] = 'Invalid platSign (signature mismatch)';
                    return $result;
                }
            }
        } elseif ($requireSign && $publicKey === '') {
            $result['message'] = 'Public key not configured – cannot verify signature';
            // Di development boleh lanjut; production harus set public key
            // Kita tetap tandai valid=false jika requireSign
            if ($requireSign) {
                return $result;
            }
        }

        // 2. Status check
        $result['is_success'] = in_array($status, self::SUCCESS_STATUSES, true);
        $result['is_failed']  = in_array($status, self::FAIL_STATUSES, true);

        if ($result['order_num'] === '') {
            $result['message'] = 'Missing orderNum';
            return $result;
        }

        $result['valid'] = true;
        $result['message'] = $result['is_success']
            ? 'Payment success'
            : ($result['is_failed'] ? 'Payment failed/expired' : 'Unknown status: ' . $status);

        return $result;
    }

    /**
     * Alternatif verifikasi jika BDPay memakai string key=value&
     */
    private static function verifyKeyValueStyle(array $params, string $publicKey): bool
    {
        $platSign = $params['platSign'] ?? $params['sign'] ?? '';
        if ($platSign === '') {
            return false;
        }
        $stringToSign = BDPaySignature::buildStringToSignKeyValue($params);
        $publicKey = self::normalizePublicKey($publicKey);
        $key = openssl_pkey_get_public($publicKey);
        if (!$key) {
            return false;
        }
        return openssl_verify($stringToSign, base64_decode($platSign), $key, OPENSSL_ALGO_SHA256) === 1;
    }

    private static function normalizePublicKey(string $key): string
    {
        $key = trim($key);
        if (strpos($key, '-----BEGIN') !== false) {
            return $key;
        }
        return "-----BEGIN PUBLIC KEY-----\n" .
            chunk_split(preg_replace('/\s+/', '', $key), 64, "\n") .
            "-----END PUBLIC KEY-----";
    }

    /**
     * Extract order ID dari orderNum format "123-timestamp" atau pure ID
     */
    public static function extractOrderId(string $orderNum): int
    {
        if (ctype_digit($orderNum)) {
            return (int) $orderNum;
        }
        $parts = explode('-', $orderNum);
        return (int) ($parts[0] ?? 0);
    }

    /**
     * Standard success response body untuk BDPay (supaya tidak retry)
     */
    public static function okResponse(): string
    {
        return 'OK';
    }
}
