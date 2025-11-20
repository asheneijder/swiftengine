<?php

namespace App\Services\SwiftParsers;

use App\Services\SwiftParserUtil;

class Mt940Parser implements SwiftMessageParser
{
    public function parse(string $finContent): array
    {
        $block4 = SwiftParserUtil::getBlock($finContent, '4');
        if (!$block4) return [];

        $sender = SwiftParserUtil::getSenderBic($finContent);
        $receiver = SwiftParserUtil::getReceiverBic($finContent);

        // --- Parse Specific Tags ---

        // Tag 20: Transaction Reference Number
        $ref = SwiftParserUtil::getTagValue($block4, '20');

        // Tag 21: Related Reference
        $relRef = SwiftParserUtil::getTagValue($block4, '21');

        // Tag 25: Account Identification
        $account = SwiftParserUtil::getTagValue($block4, '25');

        // Tag 28C: Statement Number / Sequence Number (e.g. 12345/1)
        $statementSeq = SwiftParserUtil::getTagValue($block4, '28C');

        // Tag 60F: Opening Balance (Initial) -> D/C + Date + Cur + Amount
        $openBalRaw = SwiftParserUtil::getTagValue($block4, '60F');
        // If 60F is missing, try 60M (Intermediate)
        if (!$openBalRaw) $openBalRaw = SwiftParserUtil::getTagValue($block4, '60M');
        $openingBalance = SwiftParserUtil::parseBalance($openBalRaw ?? '');

        // Tag 62F: Closing Balance (Booked)
        $closeBalRaw = SwiftParserUtil::getTagValue($block4, '62F');
        // If 62F is missing, try 62M
        if (!$closeBalRaw) $closeBalRaw = SwiftParserUtil::getTagValue($block4, '62M');
        $closingBalance = SwiftParserUtil::parseBalance($closeBalRaw ?? '');

        // Tag 64: Closing Available Balance (Optional)
        $availBalRaw = SwiftParserUtil::getTagValue($block4, '64');
        $closingAvail = SwiftParserUtil::parseBalance($availBalRaw ?? '');

        // Tag 61: Statement Lines (Transactions)
        // We grab all of them to calculate a summary
        $transactions = SwiftParserUtil::getAllTagValues($block4, '61');
        $txnSummary = count($transactions) . " Transaction(s)";
        
        // Optional: Create a mini-summary of Debit/Credit counts
        $debitCount = 0;
        $creditCount = 0;
        foreach ($transactions as $txn) {
            // Usually strictly structured, but simplistic check:
            // 61 format: ValDate EntryDate D/C Amount...
            // We look for the first alphabetical char which is typically D, C, RC, RD
            if (preg_match('/^[0-9]{6}[0-9]{0,4}(R?[DC])/', $txn, $m)) {
                if (str_contains($m[1], 'D')) $debitCount++;
                else $creditCount++;
            }
        }
        if (count($transactions) > 0) {
            $txnSummary .= " (CR: $creditCount, DR: $debitCount)";
        }

        // --- Construct Flat CSV Structure ---
        return [
            'Message Type' => 'MT940 (Customer Statement)',
            'Sender' => $sender,
            'Receiver' => $receiver,
            'Transaction Ref (20)' => $ref,
            'Related Ref (21)' => $relRef,
            'Account Identification (25)' => $account,
            'Statement/Seq (28C)' => $statementSeq,
            
            // Opening Balance Columns
            'Opening Bal Date' => $openingBalance['date'] ?? null,
            'Opening Bal Currency' => $openingBalance['currency'] ?? null,
            'Opening Bal Amount' => isset($openingBalance['amount']) 
                ? ($openingBalance['mark'] == 'Debit' ? '-' : '') . number_format((float)$openingBalance['amount'], 2, '.', '') 
                : null,

            // Closing Balance Columns
            'Closing Bal Date' => $closingBalance['date'] ?? null,
            'Closing Bal Currency' => $closingBalance['currency'] ?? null,
            'Closing Bal Amount' => isset($closingBalance['amount']) 
                ? ($closingBalance['mark'] == 'Debit' ? '-' : '') . number_format((float)$closingBalance['amount'], 2, '.', '') 
                : null,

            // Closing Available Columns
            'Avail Bal Date' => $closingAvail['date'] ?? null,
            'Avail Bal Amount' => isset($closingAvail['amount']) 
                ? ($closingAvail['mark'] == 'Debit' ? '-' : '') . number_format((float)$closingAvail['amount'], 2, '.', '') 
                : null,

            // Summary
            'Transaction Summary' => $txnSummary,
            
            // If you really want the full transaction list in one cell (JSON format)
            // 'Transactions (Raw)' => $transactions 
        ];
    }

    public function toCsv(array $data): string
    {
        return SwiftParserUtil::buildCsv($data);
    }
}