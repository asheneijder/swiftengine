<?php

namespace App\Services\SwiftParsers;

use App\Services\SwiftParserUtil;
use App\Services\SwiftCodeTranslator;

class Mt543Parser implements SwiftMessageParser
{
    public function parse(string $finContent): array
    {
        $block4 = SwiftParserUtil::getBlock($finContent, '4');
        if (!$block4) return [];

        $sender = SwiftParserUtil::getSenderBic($finContent);
        $receiver = SwiftParserUtil::getReceiverBic($finContent);
        // PSET is often found in sequence E1, but logic depends on your SwiftParserUtil::getTagValue specific implementation for finding tags in blocks.
        // Assuming getTagValue searches the block string.
        $originalReceiver = SwiftParserUtil::getTagValue($block4, '95P', 'PSET'); 

        $securityLines = SwiftParserUtil::getMultiLineTagValue($block4, '35B');
        $price = SwiftParserUtil::parsePrice(SwiftParserUtil::getTagValue($block4, '90B', 'DEAL'));
        $quantity = SwiftParserUtil::parseQuantity(SwiftParserUtil::getTagValue($block4, '36B', 'SETT'));
        $settlementAmount = SwiftParserUtil::parseCurrencyAmount(SwiftParserUtil::getTagValue($block4, '19A', 'SETT'));
        
        $func = SwiftParserUtil::getTagValue($block4, '23G');

        return [
            'Message Type' => 'MT543 (Receive Against Payment)',
            'Sender (From)' => $sender,
            'Receiver (Copy To)' => $receiver,
            'Original Receiver (Custodian)' => $originalReceiver,
            'Sender Reference' => SwiftParserUtil::getTagValue($block4, '20C', 'SEME'),
            'Message Function' => SwiftCodeTranslator::translateFunction($func),
            // 22F qualifiers depend on the message sequence, searching globally in block 4 might pick the first one found.
            'Payment Status' => SwiftCodeTranslator::translatePaymentStatus(SwiftParserUtil::getTagValue($block4, '22F', 'PAYM')),
            'Settlement Transaction Type' => SwiftCodeTranslator::translateSettlementType(SwiftParserUtil::getTagValue($block4, '22F', 'SETR')),
            'Place of Trade' => SwiftCodeTranslator::translatePlaceOfTrade(SwiftParserUtil::getTagValue($block4, '94B', 'TRAD')),
            'Trade Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '98A', 'TRAD')),
            'Settlement Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '98A', 'SETT')),
            'Security (ISIN)' => ltrim($securityLines[0] ?? '', 'ISIN '),
            'Security Name' => $securityLines[1] ?? null,
            'Quantity' => $quantity['quantity'] ?? 0, // Keep raw number for CSV, formatting can happen in UI or export
            'Dealing Price' => ($price['currency'] ?? '') . ' ' . ($price['price'] ?? ''),
            'Total Settlement Amount' => ($settlementAmount['currency'] ?? '') . ' ' . ($settlementAmount['amount'] ?? ''),
            'Buyer' => SwiftParserUtil::getTagValue($block4, '95P', 'BUYR'),
            'Safekeeping Account' => SwiftParserUtil::getTagValue($block4, '97A', 'SAFE'),
            'Receiving Agent' => SwiftParserUtil::getTagValue($block4, '95P', 'REAG'),
            'Place of Settlement' => $originalReceiver,
        ];
    }

    public function toCsv(array $data): string
    {
        // This method is less used now as we aggregate in the Service/Command, 
        // but kept for interface compatibility.
        return SwiftParserUtil::buildCsv($data);
    }
}