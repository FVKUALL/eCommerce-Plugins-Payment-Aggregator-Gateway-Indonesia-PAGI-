<?php
namespace BDPay\Payment\Model\Config\Source;
class SignAlgo implements \Magento\Framework\Data\OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'RSA', 'label' => __('RSA-SHA256 (recommended)')],
            ['value' => 'HMAC', 'label' => __('HMAC-SHA256')],
        ];
    }
}
