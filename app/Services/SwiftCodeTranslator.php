<?php

namespace App\Services;

use Illuminate\Support\Str;

class SwiftCodeTranslator
{
    private static function cleanCode(?string $code): ?string
    {
        if ($code == null) return null;
        if (str_contains($code, '/')) return Str::before($code, '/');
        return trim($code);
    }

    /**
     * Translate MT Message Type to its description.
     * Covers common Category 5 messages (Vol 2 & 3).
     */
    public static function translateMessageType(string $type): string
    {
        return match ($type) {
            '103' => 'Single Customer Credit Transfer',
            '202' => 'General Financial Institution Transfer',
            '210' => 'Notice to Receive',
            // Category 5 Vol 2 (519-543)
            '519' => 'Modification of Client Details',
            '527' => 'Triparty Collateral Instruction',
            '535' => 'Statement of Holdings',
            '536' => 'Statement of Transactions',
            '537' => 'Statement of Pending Transactions',
            '540' => 'Receive Free',
            '541' => 'Receive Against Payment',
            '542' => 'Deliver Free',
            '543' => 'Deliver Against Payment',
            // Category 5 Vol 3 (544-567)
            '544' => 'Receive Free Confirmation',
            '545' => 'Receive Against Payment Confirmation',
            '546' => 'Deliver Free Confirmation',
            '547' => 'Deliver Against Payment Confirmation',
            '548' => 'Settlement Status and Processing Advice',
            '558' => 'Triparty Collateral Status and Processing Advice',
            '564' => 'Corporate Action Notification',
            '565' => 'Corporate Action Instruction',
            '566' => 'Corporate Action Confirmation',
            '567' => 'Corporate Action Status and Processing Advice',
            // Reporting
            '940' => 'Customer Statement Message',
            '950' => 'Statement Message',
            default => 'Unknown Message Type (' . $type . ')',
        };
    }

    public static function translateFunction(?string $code): ?string
    {
        $code = self::cleanCode($code);
        return match ($code) {
            'NEWM' => 'New Message',
            'CANC' => 'Cancellation Request',
            'PREA' => 'Preadvice',
            'REPL' => 'Replacement',
            'DUPL' => 'Duplicate',
            'RECO' => 'Reconciliation Only',
            'CODU' => 'Copy Duplicate',
            'COPY' => 'Copy',
            'RMDR' => 'Reminder',
            default => $code,
        };
    }

    /**
     * Field 22F::PAYM (Payment Indicator)
     */
    public static function translatePaymentStatus(?string $code): ?string
    {
        $code = self::cleanCode($code);
        return match ($code) {
            'APMT' => 'Against Payment',
            'FREE' => 'Free of Payment',
            default => $code,
        };
    }

    /**
     * Field 22F::SETR (Settlement Transaction Type)
     * Expanded to include standard ISO 15022 codes found in guides.
     */
    public static function translateSettlementType(?string $code): ?string
    {
        $code = self::cleanCode($code);
        return match ($code) {
            'BSBK' => 'Buy Sell Back',
            'BYIY' => 'Buy In',
            'CLAI' => 'Market Claim',
            'CNCB' => 'Central Bank Collateral Operation',
            'COLI' => 'Collateral In',
            'COLO' => 'Collateral Out',
            'CONV' => 'DR Conversion',
            'CORP' => 'Corporate Action',
            'ETFT' => 'Exchange Traded Funds',
            'FCTA' => 'Factor Update',
            'INSP' => 'Move of Stock',
            'INTT' => 'Interest Payment',
            'ISSU' => 'Issuance',
            'MKUP' => 'Market Up',
            'NETT' => 'Netting',
            'NSYN' => 'Non Syndicated',
            'OWNE' => 'External Account Transfer',
            'OWNI' => 'Internal Account Transfer',
            'PAIR' => 'Pair-Off',
            'PLAC' => 'Placement',
            'PORT' => 'Portfolio Move',
            'REAL' => 'Realignment',
            'REDI' => 'Withdrawal',
            'REDM' => 'Redemption (Funds)',
            'RELE' => 'DR Release/Cancellation',
            'REPU' => 'Repo',
            'RODE' => 'Return of Delivery Without Matching',
            'RVPO' => 'Reverse Repo',
            'SBBK' => 'Sell Buy Back',
            'SECB' => 'Securities Borrowing',
            'SECL' => 'Securities Lending',
            'SLIY' => 'Sell In',
            'SUBS' => 'Subscription (Funds)',
            'SWIF' => 'Switch From',
            'SWIT' => 'Switch To',
            'SYND' => 'Syndicated',
            'TBAC' => 'TBA Closing',
            'TRAD' => 'Trade',
            'TRAN' => 'Transfer',
            'TRPO' => 'Triparty Repo',
            'TRVO' => 'Triparty Reverse Repo',
            'TURN' => 'Turnaround',
            default => $code,
        };
    }

    /**
     * Field 94B::TRAD (Place of Trade)
     */
    public static function translatePlaceOfTrade(?string $code): ?string
    {
        $code = self::cleanCode($code);
        return match ($code) {
            'EXCH' => 'Stock Exchange',
            'OTCO' => 'Over The Counter',
            'PRIM' => 'Primary Market',
            'SECM' => 'Secondary Market',
            'VARI' => 'Various',
            'BLOC' => 'Block Trade',
            default => $code,
        };
    }

    public static function translateBankOpCode(?string $code): ?string
    {
        $code = self::cleanCode($code);
        return match ($code) {
            'CRED' => 'Creditor Payment',
            'CRTS' => 'Test/Credit Transfer System',
            'SPAY' => 'SWIFT Payment',
            'SPRI' => 'Priority',
            'SSTD' => 'Standard',
            'TREA' => 'Treasury Payment',
            default => $code,
        };
    }

    public static function translateCharges(?string $code): ?string
    {
        $code = self::cleanCode($code);
        return match ($code) {
            'BEN' => 'Beneficiary',
            'OUR' => 'Our',
            'SHA' => 'Shared',
            default => $code,
        };
    }
}