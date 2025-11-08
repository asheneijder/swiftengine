<?php

namespace App\Services;

/**
 * Translates cryptic SWIFT codes into human-readable strings.
 */
class SwiftCodeTranslator
{
    /**
     * A private helper to format the translation.
     * Returns "Translation (CODE)" or just the code if no translation is found.
     */
    private static function translate(?string $code, array $map): string
    {
        if (empty($code)) {
            return '';
        }
        $translation = $map[$code] ?? null;
        return $translation ? "$translation ($code)" : $code;
    }

    /**
     * Translates Tag 23G (Message Function).
     */
    public static function translateFunction(?string $code): string
    {
        $map = [
            'NEWM' => 'New Message',
            'CANC' => 'Cancellation',
            'REPL' => 'Replace',
            'DUPL' => 'Duplicate',
        ];
        return self::translate($code, $map);
    }

    /**
     * Translates Tag 22F::SETR (Settlement Transaction Type).
     */
    public static function translateSettlementType(?string $code): string
    {
        $map = [
            'TRAD' => 'Trade',
            'TRAN' => 'Transfer',
            'CORP' => 'Corporate Action',
            'PORT' => 'Portfolio Transfer',
            'COLL' => 'Collateral',
        ];
        return self::translate($code, $map);
    }

    /**
     * Translates Tag 94B::TRAD (Place of Trade).
     */
    public static function translatePlaceOfTrade(?string $code): string
    {
        if (empty($code)) {
            return '';
        }

        $parts = explode('/', $code);
        $typeCode = $parts[0] ?? '';
        $location = $parts[1] ?? '';

        $typeMap = [
            'EXCH' => 'On Exchange',
            'OTCO' => 'Over The Counter',
            'PRIM' => 'Primary Market',
        ];
        
        $translatedType = $typeMap[$typeCode] ?? $typeCode;

        return $location ? "$translatedType ($location)" : $translatedType;
    }

    /**
     * Translates Tag 22F::PAYM (Payment Status).
     */
    public static function translatePaymentStatus(?string $code): string
    {
        $map = [
            'FREE' => 'Free of Payment',
            'APMT' => 'Against Payment',
        ];
        return self::translate($code, $map);
    }

    /**
     * Translates Tag 23B (Bank Operation Code) for MT103.
     */
    public static function translateBankOpCode(?string $code): string
    {
        $map = [
            'CRED' => 'Credit',
            'CRTS' => 'Credit Transfer',
            'SPAY' => 'SwiftPay',
            'SSTD' => 'Standard',
        ];
        return self::translate($code, $map);
    }

    /**
     * Translates Tag 71A (Charges) for MT103.
     */
    public static function translateCharges(?string $code): string
    {
        $map = [
            'OUR' => 'Sender pays all charges',
            'BEN' => 'Beneficiary pays all charges',
            'SHA' => 'Shared charges',
        ];
        return self::translate($code, $map);
    }
}