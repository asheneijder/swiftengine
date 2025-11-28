<?php

namespace App\Services\SwiftParsers;

use App\Services\SwiftParserUtil;
use App\Services\SwiftMxParserUtil;
use App\Services\SwiftCodeTranslator;

class MxPacs008Parser implements SwiftMessageParser
{
    public function parse(string $content): array
    {
        $xml = SwiftMxParserUtil::parseXml($content);
        if (!$xml) return [];

        // --- 1. Navigate to the core document ---
        $doc = $xml->Document->FIToFICstmrCdtTrf 
             ?? $xml->Body->Document->FIToFICstmrCdtTrf 
             ?? null;

        if (!$doc) return [];

        // Initialize all fields to ensure consistent CSV columns
        $data = [
            'Msg_Id' => null,
            'Creation_DtTm' => null,
            'Nb_Of_Txs' => null,
            'Sttlm_Method' => null,
            'Clearing_System' => null,
            'Instr_Id' => null,
            'EndToEnd_Id' => null,
            'Tx_Id' => null,
            'UETR' => null,
            'Clr_Sys_Ref' => null,
            'Pmt_Method' => null,
            'Svc_Level' => null,
            'Category_Purp' => null,
            'Amount' => null,
            'Currency' => null,
            'Value_Date' => null,
            'Charge_Bearer' => null,
            'Dbtr_Name' => null,
            'Dbtr_Acct' => null,
            'Dbtr_Address' => null,
            'Dbtr_Country' => null,
            'Dbtr_ID' => null,
            'DbtrAgt_BIC' => null,
            'DbtrAgt_Name' => null,
            'IntrmyAgt1_BIC' => null,
            'IntrmyAgt1_Acct' => null,
            'CdtrAgt_BIC' => null,
            'CdtrAgt_Name' => null,
            'CdtrAgt_Acct' => null,
            'Cdtr_Name' => null,
            'Cdtr_Acct' => null,
            'Cdtr_Address' => null,
            'Cdtr_Country' => null,
            'Remit_Unstruct' => null,
            'Remit_Struct' => null,
            'Instr_Inf' => null,
        ];
        
        // --- 2. Group Header (GrpHdr) ---
        $grpHdr = $doc->GrpHdr;
        $data['Msg_Id']             = SwiftMxParserUtil::get($grpHdr->MsgId);
        $data['Creation_DtTm']      = SwiftMxParserUtil::get($grpHdr->CreDtTm);
        $data['Nb_Of_Txs']          = SwiftMxParserUtil::get($grpHdr->NbOfTxs);
        $data['Sttlm_Method']       = SwiftMxParserUtil::get($grpHdr->SttlmInf?->SttlmMtd);
        $data['Clearing_System']    = SwiftMxParserUtil::get($grpHdr->SttlmInf?->ClrSys?->Prtry);

        // --- 3. Transaction Info (CdtTrfTxInf) ---
        // Takes the first transaction found
        $tx = $doc->CdtTrfTxInf[0] ?? $doc->CdtTrfTxInf;

        if ($tx) {
            // -- IDs --
            $data['Instr_Id']       = SwiftMxParserUtil::get($tx->PmtId->InstrId);
            $data['EndToEnd_Id']    = SwiftMxParserUtil::get($tx->PmtId->EndToEndId);
            $data['Tx_Id']          = SwiftMxParserUtil::get($tx->PmtId->TxId);
            $data['UETR']           = SwiftMxParserUtil::get($tx->PmtId->UETR);
            $data['Clr_Sys_Ref']    = SwiftMxParserUtil::get($tx->PmtId->ClrSysRef);

            // -- Payment Details --
            $data['Pmt_Method']     = SwiftMxParserUtil::get($tx->PmtTpInf?->LclInstrm?->Prtry); 
            $data['Svc_Level']      = SwiftMxParserUtil::get($tx->PmtTpInf?->SvcLvl?->Cd);       
            
            // Translate Purpose Code
            $rawPurpose             = SwiftMxParserUtil::get($tx->PmtTpInf?->CtgyPurp?->Cd);
            $data['Category_Purp']  = SwiftCodeTranslator::translatePurposeCode($rawPurpose); 
            
            // -- Amount & Date --
            $data['Amount']         = SwiftMxParserUtil::get($tx->IntrBkSttlmAmt);
            $data['Currency']       = (string)($tx->IntrBkSttlmAmt['Ccy'] ?? '');
            $data['Value_Date']     = SwiftMxParserUtil::get($tx->IntrBkSttlmDt);
            $data['Charge_Bearer']  = SwiftMxParserUtil::get($tx->ChrgBr);

            // -- Ordering Customer (Debtor) --
            $data['Dbtr_Name']      = SwiftMxParserUtil::get($tx->Dbtr->Nm);
            $data['Dbtr_Acct']      = SwiftMxParserUtil::get($tx->DbtrAcct?->Id?->IBAN) 
                                      ?? SwiftMxParserUtil::get($tx->DbtrAcct?->Id?->Othr?->Id);
            $data['Dbtr_Address']   = SwiftMxParserUtil::formatAddress($tx->Dbtr?->PstlAdr);
            $data['Dbtr_Country']   = SwiftMxParserUtil::get($tx->Dbtr?->PstlAdr?->Ctry);
            $data['Dbtr_ID']        = SwiftMxParserUtil::get($tx->Dbtr?->Id?->OrgId?->AnyBIC) 
                                      ?? SwiftMxParserUtil::get($tx->Dbtr?->Id?->PrvtId?->Othr?->Id);

            // -- Ordering Institution (Debtor Agent) --
            $data['DbtrAgt_BIC']    = SwiftMxParserUtil::get($tx->DbtrAgt?->FinInstnId?->BICFI);
            $data['DbtrAgt_Name']   = SwiftMxParserUtil::get($tx->DbtrAgt?->FinInstnId?->Nm);

            // -- Intermediaries --
            $data['IntrmyAgt1_BIC'] = SwiftMxParserUtil::get($tx->IntrmyAgt1?->FinInstnId?->BICFI);
            $data['IntrmyAgt1_Acct']= SwiftMxParserUtil::get($tx->IntrmyAgt1Acct?->Id?->Othr?->Id);

            // -- Beneficiary Institution (Creditor Agent) --
            $data['CdtrAgt_BIC']    = SwiftMxParserUtil::get($tx->CdtrAgt?->FinInstnId?->BICFI);
            $data['CdtrAgt_Name']   = SwiftMxParserUtil::get($tx->CdtrAgt?->FinInstnId?->Nm);
            $data['CdtrAgt_Acct']   = SwiftMxParserUtil::get($tx->CdtrAgtAcct?->Id?->Othr?->Id);

            // -- Beneficiary Customer (Creditor) --
            $data['Cdtr_Name']      = SwiftMxParserUtil::get($tx->Cdtr->Nm);
            $data['Cdtr_Acct']      = SwiftMxParserUtil::get($tx->CdtrAcct?->Id?->IBAN) 
                                      ?? SwiftMxParserUtil::get($tx->CdtrAcct?->Id?->Othr?->Id);
            $data['Cdtr_Address']   = SwiftMxParserUtil::formatAddress($tx->Cdtr?->PstlAdr);
            $data['Cdtr_Country']   = SwiftMxParserUtil::get($tx->Cdtr?->PstlAdr?->Ctry);
            
            // -- Remittance Info --
            $data['Remit_Unstruct'] = SwiftMxParserUtil::get($tx->RmtInf?->Ustrd);
            $data['Remit_Struct']   = SwiftMxParserUtil::get($tx->RmtInf?->Strd?->AddtlRmtInf);

            // -- Purpose / Details --
            $data['Instr_Inf']      = SwiftMxParserUtil::get($tx->InstrForCdtrAgt?->InstrInf);
        }

        // --- Return Metadata for Service ---
        $appHdr = $xml->AppHdr ?? $xml->Body->AppHdr ?? null;
        $sender = SwiftMxParserUtil::get($appHdr->Fr->FIId->FinInstnId->BICFI) ?? 'UNKNOWN';
        $receiver = SwiftMxParserUtil::get($appHdr->To->FIId->FinInstnId->BICFI) ?? 'UNKNOWN';

        return [
            'type' => 'MX pacs.008',
            'Sender' => $sender,
            'Receiver' => $receiver,
            'Creation Date' => $data['Creation_DtTm'],
            ...$data 
        ];
    }

    public function toCsv(array $data): string
    {
        return SwiftParserUtil::buildCsv($data);
    }
}