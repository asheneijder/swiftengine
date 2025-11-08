<?php

namespace App\Services\SwiftParsers;

use App\Services\SwiftParserUtil;
use App\Services\SwiftCodeTranslator;

class Mt547Parser implements SwiftMessageParser
{
    public function parse(string $finContent): array
    {
        $block4 = SwiftParserUtil::getBlock($finContent, '4');
        if (!$block4) return [];

        $sender = SwiftParserUtil::getSenderBic($finContent);
        $receiver = SwiftParserUtil::getReceiverBic($finContent);

        $securityLines = SwiftParserUtil::getMultiLineTagValue($block4, '35B');
        $quantity = SwiftParserUtil::parseQuantity(SwiftParserUtil::getTagValue($block4, '36B', 'ESTT'));
        $settlementAmount = SwiftParserUtil::parseCurrencyAmount(SwiftParserUtil::getTagValue($block4, '19A', 'ESTT'));
        $func = SwiftParserUtil::getTagValue($block4, '23G');

        return [
            'Message Type' => 'MT547 (Settlement Confirmation, Against Payment)',
            'Sender' => $sender,
            'Receiver' => $receiver,
            'Sender\'s Reference' => SwiftParserUtil::getTagValue($block4, '20C', 'SEME'),
            'Message Function' => SwiftCodeTranslator::translateFunction($func),
            'Linked Reference' => SwiftParserUtil::getTagValue($block4, '20C', 'RELA'),
            'Preparation Timestamp' => SwiftParserUtil::getTagValue($block4, '98C', 'PREP'),
            'Settlement Transaction Type' => SwiftCodeTranslator::translateSettlementType(SwiftParserUtil::getTagValue($block4, '22F', 'SETR')),
            'Trade Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '98A', 'TRAD')),
            'Effective Settlement Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '98A', 'ESET')),
            'Security (ISIN)' => ltrim($securityLines[0] ?? '', 'ISIN '),
            'Security Name' => $securityLines[1] ?? null,
            'Settled Quantity' => number_format((float)($quantity['quantity'] ?? 0)) . ' Units',
            'Settled Amount' => ($settlementAmount['currency'] ?? '') . ' ' . number_format((float)($settlementAmount['amount'] ?? 0), 2, '.', ','),
            'Safekeeping Account' => SwiftParserUtil::getTagValue($block4, '97A', 'SAFE'),
            'Value Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '98A', 'VALU')),
            'Delivering Agent' => SwiftParserUtil::getTagValue($block4, '95P', 'DEAG'),
            'Receiving Agent' => SwiftParserUtil::getTagValue($block4, '95P', 'REAG'),
        ];
    }

    public function toCsv(array $data): string
    {
        return SwiftParserUtil::buildCsv($data);
    }
}