<?php

namespace App\Services;

use Exception;
use DOMDocument;
use InvalidArgumentException;
use App\Models\FlaggedCase;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class XMLExportService
{
    private $dom;

    public function __construct()
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;
    }

    public function generateBulk($data)
    {
        $zip = new \ZipArchive();
        $datePath = now()->format('Y/m/d');
        $directory = public_path("reports/{$datePath}");

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $zipFileName = $directory . '/bulk_reports_' . date('Y-m-d_H-i-s') . '.zip';

        if ($zip->open($zipFileName, \ZipArchive::CREATE) === true) {
            foreach ($data as $case) {
                $this->dom = new DOMDocument('1.0', 'UTF-8');
                $this->dom->formatOutput = true;

                $reportData = $this->buildReportData($case);
                $filePath = $this->generate($reportData, $case->report_type);

                if (file_exists($filePath)) {
                    $zip->addFile($filePath, basename($filePath));
                } else {
                    Log::warning("File not created for case {$case->id}");
                }
            }
            $zip->close();
        }

        return $zipFileName;
    }

    /**
     * Build report data from a FlaggedCase
     */
    public function buildReportData(FlaggedCase $case): array
    {
        $transaction = $case->transaction;
        $senderIsCustomer = is_customer($transaction?->sender_account_no);
        $beneficiaryIsCustomer = is_customer($transaction?->beneficiary_account_no);

        $senderCustomer = $senderIsCustomer ? Customer::where('account_number', $transaction->sender_account_no)->first() : null;
        $beneficiaryCustomer = $beneficiaryIsCustomer ? Customer::where('account_number', $transaction->beneficiary_account_no)->first() : null;

        $rentityId = getBusinessDetails('rentity_id') ?? '12345';
        $institutionCode = getBusinessDetails('institution_code') ?? '';

        return [
            'case_id' => $case->slug,
            'rentity_id' => $rentityId,
            'submission_code' => 'E',
            'report_code' => $case->report_type ?? 'STR',
            'report_date' => now()->format('Y-m-d\TH:i:s'),
            'currency_code_local' => 'NGN',
            'reason' => 'Transaction flagged based on internal rules.',
            'action' => 'Reported as ' . ($case->report_type ?? 'STR'),
            'transactionnumber' => $transaction?->transaction_ref ?? $case->slug,
            'transaction_is_suspicious' => $case->report_type === 'STR' ? '1' : '0',
            'transaction_description' => $case->transaction_rule?->description ?? 'Suspicious Transaction',
            'date_transaction' => $transaction?->transaction_datetime?->format('Y-m-d\TH:i:s') ?? now()->format('Y-m-d\TH:i:s'),
            'transmode_code' => $this->mapChannelToTransmode($transaction?->channel),
            'amount_local' => number_format((float)($transaction?->amount ?? 0), 2, '.', ''),
            'sender' => [
                'is_customer' => $senderIsCustomer,
                'from_funds_code' => $this->mapChannelToFundsCode($transaction?->channel),
                'institution_code' => $institutionCode,
                'account' => $transaction?->sender_account_no ?? '',
                'currency_code' => 'NGN',
                'account_name' => $transaction?->sender_name ?? '',
                'client_number' => $senderCustomer?->bvn ?? '',
                'personal_account_type' => '---',
                'account_type' => '---',
                'from_country' => 'NG',
            ],
            'receiver' => [
                'is_customer' => $beneficiaryIsCustomer,
                'to_funds_code' => $this->mapChannelToFundsCode($transaction?->channel),
                'institution_code' => $institutionCode,
                'account' => $transaction?->beneficiary_account_no ?? '',
                'currency_code' => 'NGN',
                'account_name' => $transaction?->beneficiary_name ?? '',
                'client_number' => $beneficiaryCustomer?->bvn ?? '',
                'personal_account_type' => '---',
                'account_type' => '---',
                'to_country' => 'NG',
            ],
            'from_country' => 'NG',
            'indicator_code' => $case->nfiu_indicator?->code ?? '',
            'indicator_description' => $case->nfiu_indicator?->description ?? '',
        ];
    }

    /**
     * Map channel to conduction_type code
     * Uses '---' (unknown) since the official XSD template only defines '---'
     * The FIU must configure additional codes in their goAML Lookup Master
     */
    /**
     * Map channel to NFIU Transaction Mode codes
     * From NFIU Lookup Master document:
     *   A = Inbranch/Office, B = ATM, C = Electronic Transaction, K = Cash, T = Courier
     */
    /**
     * Map channel to transmode_code
     * The NFIU XSD template only allows '---' in conduction_type enum.
     * NFIU Lookup Master defines: A=Inbranch, B=ATM, C=Electronic, K=Cash, T=Courier
     * But these are only valid AFTER the FIU configures them in the XSD via Lookup Master.
     * For XSD validation compliance, we use '---'.
     */
    private function mapChannelToTransmode(?string $channel): string
    {
        return '---';
    }

    /**
     * Map channel to NFIU Fund Type codes
     * From NFIU Lookup Master:
     *   A=Deposit, B=Electronic funds transfer, E=Bank draft, K=Cash, P=Cheque, etc.
     */
    /**
     * Map channel to funds_code
     * The NFIU XSD template only allows '---' in funds_type enum.
     * NFIU Lookup Master defines: A=Deposit, B=EFT, K=Cash, P=Cheque, etc.
     * But these are only valid AFTER the FIU configures them in the XSD via Lookup Master.
     * For XSD validation compliance, we use '---'.
     */
    private function mapChannelToFundsCode(?string $channel): string
    {
        return '---';
    }

    public function generate($data, $type)
    {
        $type = strtoupper($type);

        if (!in_array($type, ['STR', 'CTR', 'EFT', 'IFT', 'TFR', 'BCR', 'UTR', 'AIF', 'SAR'])) {
            throw new InvalidArgumentException("Unsupported report type: $type");
        }

        $xml = $this->createReport($data, $type);

        $datePath = now()->format('Y/m/d');
        $directory = "reports/{$datePath}";
        $fileName = strtolower($type) . '_report_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.xml';

        Storage::disk('local')->put("{$directory}/{$fileName}", $xml);

        if (isset($data['case_id'])) {
            $case = FlaggedCase::where('slug', $data['case_id'])->first();
            if ($case) activity()->performedOn($case)->log('Case ' . $case->slug . ' exported to ' . $type . ' XML');
        }

        return Storage::disk('local')->path("{$directory}/{$fileName}");
    }

    /**
     * Create goAML 5.0.2 compliant XML report
     *
     * Element order strictly follows the official UNODC XSD sequence:
     * transaction: transactionnumber, transaction_is_suspicious, internal_ref_number,
     *              transaction_location, transaction_description, date_transaction,
     *              transmode_code, amount_local, t_from/t_from_my_client, t_to/t_to_my_client
     */
    private function createReport(array $data, string $type): string
    {
        $root = $this->dom->createElement('report');
        $root->setAttribute('xmlns:xsi', 'https://www.w3.org/2001/XMLSchema-instance');
        $this->dom->appendChild($root);

        // Report header — order matches XSD
        $this->addElement($root, 'schema_version', '5.0.2');
        $this->addElement($root, 'rentity_id', $data['rentity_id']);
        $this->addElement($root, 'submission_code', $data['submission_code']);
        $this->addElement($root, 'report_code', $data['report_code'] ?? $type);
        // XSD uses xs:choice between submission_date and report_date
        $this->addElement($root, 'report_date', $data['report_date']);
        $this->addElement($root, 'currency_code_local', $data['currency_code_local']);

        // Reason & action (optional per XSD, but useful for STR)
        if (!empty($data['reason'])) {
            $this->addElement($root, 'reason', $data['reason']);
        }
        if (!empty($data['action'])) {
            $this->addElement($root, 'action', $data['action']);
        }

        // Transaction — element order matches XSD xs:sequence exactly
        $transaction = $this->dom->createElement('transaction');
        $root->appendChild($transaction);

        // 1. transactionnumber (required)
        $this->addElement($transaction, 'transactionnumber', $data['transactionnumber']);

        // 2. transaction_is_suspicious (optional, boolean)
        if (isset($data['transaction_is_suspicious'])) {
            $this->addElement($transaction, 'transaction_is_suspicious', $data['transaction_is_suspicious']);
        }

        // 3. transaction_description (optional, BEFORE date_transaction per XSD)
        if (!empty($data['transaction_description'])) {
            $this->addElement($transaction, 'transaction_description', $data['transaction_description']);
        }

        // 4. date_transaction (required)
        $this->addElement($transaction, 'date_transaction', $data['date_transaction']);

        // 5. transmode_code (required) — use '---' for XSD compliance
        $this->addElement($transaction, 'transmode_code', $data['transmode_code'] ?? '---');

        // 6. amount_local (required)
        $this->addElement($transaction, 'amount_local', $data['amount_local']);

        // 7. From (sender) — t_from_my_client or t_from
        $this->appendParty($transaction, $data['sender'] ?? [], 'from', $data['from_country'] ?? 'NG');

        // 8. To (receiver) — t_to_my_client or t_to
        $this->appendParty($transaction, $data['receiver'] ?? [], 'to', $data['receiver']['to_country'] ?? 'NG');

        // Report indicators — uses simple string value, NO code attribute
        // The official XSD defines report_indicator_type as xs:string enum {'-'}
        $indicators = $this->dom->createElement('report_indicators');
        $root->appendChild($indicators);

        // The NFIU XSD template only allows '-' in report_indicator_type enum.
        // The actual NFIU indicator code is stored in the case but cannot be used
        // in the XML until the FIU configures the codes in their XSD via Lookup Master.
        // For XSD validation compliance, we use '-'.
        $this->addElement($indicators, 'indicator', '-');

        return $this->dom->saveXML();
    }

    private function appendParty($transaction, array $party, string $direction, string $country): void
    {
        $isCustomer = $party['is_customer'] ?? false;
        $prefix = $direction;
        $instCode = trim($party['institution_code'] ?? '');
        if (empty($instCode)) $instCode = '---';

        if ($isCustomer) {
            $node = $this->dom->createElement("t_{$prefix}_my_client");
            $transaction->appendChild($node);

            $this->addElement($node, "{$prefix}_funds_code", $party["{$prefix}_funds_code"] ?? '---');

            $account = $this->dom->createElement("{$prefix}_account");
            $node->appendChild($account);

            // XSD sequence: institution_name(opt) -> institution_code|swift(choice) -> ... -> account
            $this->addElement($account, 'institution_name', $instCode);
            $this->addElement($account, 'institution_code', $instCode);
            $this->addElement($account, 'account', $party['account'] ?? '');
            $this->addElement($account, 'currency_code', $party['currency_code'] ?? 'NGN');
            if (!empty($party['account_name'])) {
                $this->addElement($account, 'account_name', $party['account_name']);
            }
            if (!empty($party['client_number'])) {
                $this->addElement($account, 'client_number', $party['client_number']);
            }
            $this->addElement($account, 'personal_account_type', $party['personal_account_type'] ?? '---');

            $this->addElement($node, "{$prefix}_country", $party["{$prefix}_country"] ?? $country);
        } else {
            $node = $this->dom->createElement("t_{$prefix}");
            $transaction->appendChild($node);

            $this->addElement($node, "{$prefix}_funds_code", $party["{$prefix}_funds_code"] ?? '---');

            $account = $this->dom->createElement("{$prefix}_account");
            $node->appendChild($account);

            // XSD sequence: institution_name(opt) -> institution_code|swift(choice) -> ... -> account
            $this->addElement($account, 'institution_name', $instCode);
            $this->addElement($account, 'institution_code', $instCode);
            $this->addElement($account, 'account', $party['account'] ?? '');
            $this->addElement($account, 'currency_code', $party['currency_code'] ?? 'NGN');
            if (!empty($party['account_name'])) {
                $this->addElement($account, 'account_name', $party['account_name']);
            }

            $this->addElement($node, "{$prefix}_country", $country);
        }
    }

    private function addElement($parent, $name, $value, array $attributes = [])
    {
        $element = $this->dom->createElement($name);
        foreach ($attributes as $attrName => $attrValue) {
            $element->setAttribute($attrName, $attrValue);
        }
        $element->appendChild($this->dom->createTextNode($value ?? ''));
        $parent->appendChild($element);
    }

    /**
     * Validate XML against goAML XSD schema
     */
    public function validateXML(string $xmlPath, ?string $xsdPath = null)
    {
        if (!$xsdPath) {
            // Check uploaded XSD first, then default
            $uploadedXsd = Storage::disk('local')->path('xsd/goAML.xsd');
            if (file_exists($uploadedXsd)) {
                $xsdPath = $uploadedXsd;
            } else {
                $xsdPath = Storage::disk('local')->path('goAMLSchema_5.0.2.xsd');
            }
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        if (!$dom->load($xmlPath)) {
            return ['valid' => false, 'errors' => ['Failed to load XML file.']];
        }

        if (!file_exists($xsdPath)) {
            return ['valid' => false, 'errors' => ['XSD schema file not found at: ' . $xsdPath]];
        }

        $isValid = $dom->schemaValidate($xsdPath);

        if ($isValid) {
            return ['valid' => true, 'errors' => []];
        }

        $errors = [];
        foreach (libxml_get_errors() as $error) {
            $errors[] = trim($error->message) . " (Line {$error->line})";
        }
        libxml_clear_errors();

        return ['valid' => false, 'errors' => $errors];
    }
}
