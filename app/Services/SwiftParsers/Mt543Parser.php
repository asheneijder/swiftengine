<?php

namespace App\Services\SwiftParsers;

use App\Services\SwiftParserUtil;

class Mt543Parser implements SwiftMessageParser
{
    public function parse(string $finContent): array
    {
        $block2 = SwiftParserUtil::getBlock($finContent, '2');
        $block4 = SwiftParserUtil::getBlock($finContent, '4');
        if (!$block2 || !$block4) return [];

        $sender = substr($block2, 4, 11);
        $receiver = substr($block2, 19, 11);
        $originalReceiver = substr($block2, 8, 11); // O543...[RECEIVER]...

        $securityLines = SwiftParserUtil::getMultiLineTagValue($block4, '35B');
        $price = SwiftParserUtil::parsePrice(SwiftParserUtil::getTagValue($block4, '90B', 'DEAL'));
        $quantity = SwiftParserUtil::parseQuantity(SwiftParserUtil::getTagValue($block4, '36B', 'SETT'));
        $settlementAmount = SwiftParserUtil::parseCurrencyAmount(SwiftParserUtil::getTagValue($block4, '19A', 'SETT'));

        return [
            'Message Type' => 'MT543 (Instruction to Buy Securities)',
            'Sender (From)' => $sender,
            'Receiver (Copy To)' => $receiver,
            'Original Receiver (Custodian)' => $originalReceiver,
            'Sender\'s Reference' => SwiftParserUtil::getTagValue($block4, '20C', 'SEME'),
            'Message Function' => SwiftParserUtil::getTagValue($block4, '23G'),
            'Trade Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '98A', 'TRAD')),
            'Settlement Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '98A', 'SETT')),
            'Security (ISIN)' => ltrim($securityLines[0] ?? '', 'ISIN '),
            'Security Name' => $securityLines[1] ?? null,
            'Quantity' => ($quantity['quantity'] ?? '') . ' Units',
            'Dealing Price' => ($price['currency'] ?? '') . ' ' . ($price['price'] ?? ''),
            'Total Settlement Amount' => ($settlementAmount['currency'] ?? '') . ' ' . number_format((float)($settlementAmount['amount'] ?? 0), 2),
            'Buyer' => SwiftParserUtil::getTagValue($block4, '95P', 'BUYR'),
            'Safekeeping Account' => SwiftParserUtil::getTagValue($block4, '97A', 'SAFE'),
            'Place of Settlement' => SwiftParserUtil::getTagValue($block4, '95P', 'PSET'),
        ];
    }

    public function toCsv(array $data): string
    {
        return SwiftParserUtil::buildCsv($data);
    }
}