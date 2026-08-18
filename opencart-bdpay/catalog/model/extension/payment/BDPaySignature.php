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
 * BDPay Signature Helper
 * Based on BDPay Open API documentation patterns:
 * - Sort parameters by ASCII key order
 * - Concatenate non-empty values (exclude 'sign' / 'platSign')
 * - Sign with RSA-SHA256 using merchant private key (PKCS#1 or PKCS#8)
 * - Fallback HMAC-SHA256 if only secret key is provided
 *
 * Example sign from docs starts with "MIGfMA0GCSqGSI..." (RSA style)
 * Docs: https://dc.bdpay.co.id/docs/  &  https://document.bdpay.co.id/docs/api/
 */

class BDPaySignature
{
    /**
     * Generate request signature
     *
     * @param array  $params     Request body parameters
     * @param string $privateKey Merchant private key (PEM) OR secret key for HMAC
     * @param string $algo       'RSA' (default) or 'HMAC'
     * @return string Base64 signature
     */
    public static function generate(array $params, string $privateKey, string $algo = 'RSA'): string
    {
        $stringToSign = self::buildStringToSign($params);

        if (strtoupper($algo) === 'HMAC' || self::isHmacKey($privateKey)) {
            return hash_hmac('sha256', $stringToSign, $privateKey);
        }

        return self::rsaSign($stringToSign, $privateKey);
    }

    /**
     * Verify response / callback signature (platSign)
     *
     * @param array  $params    Response data including platSign
     * @param string $publicKey Platform public key (PEM)
     * @return bool
     */
    public static function verify(array $params, string $publicKey): bool
    {
        $platSign = $params['platSign'] ?? $params['sign'] ?? '';
        if ($platSign === '') {
            return false;
        }

        unset($params['platSign'], $params['sign']);
        $stringToSign = self::buildStringToSign($params);

        $publicKey = self::normalizePem($publicKey, 'PUBLIC');
        $key = openssl_pkey_get_public($publicKey);
        if (!$key) {
            return false;
        }

        $result = openssl_verify($stringToSign, base64_decode($platSign), $key, OPENSSL_ALGO_SHA256);
        return $result === 1;
    }

    /**
     * Build canonical string to sign
     * Rules commonly used by BDPay-style gateways:
     * 1. Remove sign / platSign
     * 2. Sort keys ASCII ascending
     * 3. Concatenate values of non-empty parameters (no key= value&)
     */
    public static function buildStringToSign(array $params): string
    {
        unset($params['sign'], $params['platSign']);

        // Remove empty / null
        $filtered = array_filter($params, function ($v) {
            return $v !== '' && $v !== null;
        });

        ksort($filtered, SORT_STRING);

        // Concatenate only values (common for RSA gateways matching the docs style)
        return implode('', array_map('strval', $filtered));
    }

    /**
     * Alternative string builder (key=value& style) – keep for compatibility
     */
    public static function buildStringToSignKeyValue(array $params): string
    {
        unset($params['sign'], $params['platSign']);
        $filtered = array_filter($params, function ($v) {
            return $v !== '' && $v !== null;
        });
        ksort($filtered, SORT_STRING);

        $parts = [];
        foreach ($filtered as $k => $v) {
            $parts[] = $k . '=' . $v;
        }
        return implode('&', $parts);
    }

    private static function rsaSign(string $data, string $privateKeyPem): string
    {
        $privateKeyPem = self::normalizePem($privateKeyPem, 'PRIVATE');
        $key = openssl_pkey_get_private($privateKeyPem);
        if (!$key) {
            // Try as raw base64 PKCS#8 without headers
            $key = openssl_pkey_get_private("-----BEGIN PRIVATE KEY-----\n" .
                chunk_split(trim($privateKeyPem), 64, "\n") .
                "-----END PRIVATE KEY-----");
        }
        if (!$key) {
            throw new RuntimeException('Invalid BDPay private key. Check PEM format.');
        }

        $signature = '';
        $ok = openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256);
        if (!$ok) {
            throw new RuntimeException('openssl_sign failed: ' . openssl_error_string());
        }
        return base64_encode($signature);
    }

    private static function normalizePem(string $key, string $type = 'PRIVATE'): string
    {
        $key = trim($key);
        if (strpos($key, '-----BEGIN') !== false) {
            return $key;
        }
        // Assume base64 body
        $header = $type === 'PUBLIC' ? 'PUBLIC KEY' : 'PRIVATE KEY';
        return "-----BEGIN {$header}-----\n" .
            chunk_split(preg_replace('/\s+/', '', $key), 64, "\n") .
            "-----END {$header}-----";
    }

    private static function isHmacKey(string $key): bool
    {
        // Heuristic: short keys or no PEM markers → treat as HMAC secret
        return strlen($key) < 200 && strpos($key, '-----BEGIN') === false;
    }
}
