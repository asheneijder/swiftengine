<?php

namespace App\Services\SwiftParsers;

use App\Services\SwiftParserUtil;
use App\Services\SwiftCodeTranslator;

class Mt541Parser implements SwiftMessageParser
{
    public function parse(string $finContent): array
    {
        $block4 = SwiftParserUtil::getBlock($finContent, '4');
        if (!$block4) return [];

        $securityLines = SwiftParserUtil::getMultiLineTagValue($block4, '35B');
        $quantity = SwiftParserUtil::parseQuantity(SwiftParserUtil::getTagValue($block4, '36B', 'SETT'));
        
        // Amounts
        $dealPrice = SwiftParserUtil::parsePrice(SwiftParserUtil::getTagValue($block4, '90B', 'DEAL'));
        $settleAmt = SwiftParserUtil::parseCurrencyAmount(SwiftParserUtil::getTagValue($block4, '19A', 'SETT'));

        return [
            'Message Type' => 'MT541 (Receive Against Payment)',
            'Sender' => SwiftParserUtil::getSenderBic($finContent),
            'Receiver' => SwiftParserUtil::getReceiverBic($finContent),
            'Reference' => SwiftParserUtil::getTagValue($block4, '20C', 'SEME'),
            'Function' => SwiftCodeTranslator::translateFunction(SwiftParserUtil::getTagValue($block4, '23G')),
            
            'Place of Trade' => SwiftCodeTranslator::translatePlaceOfTrade(SwiftParserUtil::getTagValue($block4, '94B', 'TRAD')),
            'Trade Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '98A', 'TRAD')),
            'Settlement Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '98A', 'SETT')),
            
            'ISIN' => ltrim($securityLines[0] ?? '', 'ISIN '),
            'Security Name' => $securityLines[1] ?? null,
            'Quantity' => number_format((float)($quantity['quantity'] ?? 0)),
            
            // Financials
            'Deal Price' => trim(($dealPrice['currency'] ?? '') . ' ' . ($dealPrice['price'] ?? '')),
            'Settlement Amount' => trim(($settleAmt['currency'] ?? '') . ' ' . number_format((float)($settleAmt['amount'] ?? 0), 2)),
            
            // Parties (Note: Sample uses 95R for DEAG, 95P for others)
            'Seller' => SwiftParserUtil::getTagValue($block4, '95P', 'SELL') ?? SwiftParserUtil::getTagValue($block4, '95R', 'SELL'),
            'Delivering Agent' => SwiftParserUtil::getTagValue($block4, '95P', 'DEAG') ?? SwiftParserUtil::getTagValue($block4, '95R', 'DEAG'),
            'Place of Settlement' => SwiftParserUtil::getTagValue($block4, '95P', 'PSET'),
            'Safekeeping Account' => SwiftParserUtil::getTagValue($block4, '97A', 'SAFE'),
        ];
    }

    public function toCsv(array $data): string { return SwiftParserUtil::buildCsv($data); }
}