<?php

namespace App\Services\SwiftParsers;

use App\Services\SwiftParserUtil;

class Mt103Parser implements SwiftMessageParser
{
    public function parse(string $finContent): array
    {
        $block2 = SwiftParserUtil::getBlock($finContent, '2');
        $block4 = SwiftParserUtil::getBlock($finContent, '4');
        if (!$block4) return [];

        $sender = substr($block2, 4, 11); // Assuming O103[SENDER_BIC]...
        $receiver = substr($block2, 19, 11); // Assuming O103...[RECEIVER_BIC]...

        $currencyAmount = SwiftParserUtil::parseCurrencyAmount(SwiftParserUtil::getTagValue($block4, '32A'));
        $payerLines = SwiftParserUtil::getMultiLineTagValue($block4, '50K');
        $beneficiaryLines = SwiftParserUtil::getMultiLineTagValue($block4, '59');

        return [
            'Message Type' => 'MT103 (Single Customer Credit Transfer)',
            'Sender' => $sender,
            'Receiver (Copy To)' => $receiver,
            'Sender\'s Reference' => SwiftParserUtil::getTagValue($block4, '20'),
            'Value Date' => $currencyAmount['date'] ?? null,
            'Amount' => ($currencyAmount['currency'] ?? '') . ' ' . ($currencyAmount['amount'] ?? ''),
            'Payer (Ordering Customer)' => implode(' | ', $payerLines),
            'Intermediary Bank' => SwiftParserUtil::getTagValue($block4, '57A'),
            'Payee (Beneficiary)' => implode(' | ', $beneficiaryLines),
            'Payment Details' => SwiftParserUtil::getTagValue($block4, '70'),
            'Charge Code' => SwiftParserUtil::getTagValue($block4, '71A'),
        ];
    }

    public function toCsv(array $data): string
    {
        return SwiftParserUtil::buildCsv($data);
    }
}