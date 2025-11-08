<?php

namespace App\Services\SwiftParsers;

use App\Services\SwiftParserUtil;
use App\Services\SwiftCodeTranslator;

class Mt103Parser implements SwiftMessageParser
{
    public function parse(string $finContent): array
    {
        $block4 = SwiftParserUtil::getBlock($finContent, '4');
        if (!$block4) return [];

        $sender = SwiftParserUtil::getSenderBic($finContent);
        $receiver = SwiftParserUtil::getReceiverBic($finContent);

        $currencyAmount = SwiftParserUtil::parseCurrencyAmount(SwiftParserUtil::getTagValue($block4, '32A'));
        
        $payerLines = SwiftParserUtil::getMultiLineTagValue($block4, '50K');
        $payerAccount = ltrim($payerLines[0] ?? '', '/');
        $payerName = $payerLines[1] ?? '';
        $payerDisplay = $payerName . ' (Account: ' . $payerAccount . ')';
        
        $beneLines = SwiftParserUtil::getMultiLineTagValue($block4, '59');
        $beneAccount = ltrim($beneLines[0] ?? '', '/');
        $beneName = $beneLines[1] ?? '';
        $beneDisplay = $beneName . ' (Account: ' . $beneAccount . ')';

        return [
            'Message Type' => 'MT103 (Single Customer Credit Transfer)',
            'Sender' => $sender,
            'Receiver (Copy To)' => $receiver,
            'Sender\'s Reference' => SwiftParserUtil::getTagValue($block4, '20'),
            'Bank Operation Code' => SwiftCodeTranslator::translateBankOpCode(SwiftParserUtil::getTagValue($block4, '23B')),
            'Value Date' => $currencyAmount['date'] ?? null,
            'Amount' => ($currencyAmount['currency'] ?? '') . ' ' . number_format((float)($currencyAmount['amount'] ?? 0), 2, '.', ','),
            'Ordering Institution' => SwiftParserUtil::getTagValue($block4, '52A'),
            'Payer (Ordering Customer)' => $payerDisplay,
            'Intermediary Bank' => SwiftParserUtil::getTagValue($block4, '57A'),
            'Payee (Beneficiary)' => $beneDisplay,
            'Payment Details' => SwiftParserUtil::getTagValue($block4, '70'),
            'Charge Code' => SwiftCodeTranslator::translateCharges(SwiftParserUtil::getTagValue($block4, '71A')),
        ];
    }

    public function toCsv(array $data): string
    {
        return SwiftParserUtil::buildCsv($data);
    }
}