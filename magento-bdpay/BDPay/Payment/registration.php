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
\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'BDPay_Payment',
    __DIR__
);
