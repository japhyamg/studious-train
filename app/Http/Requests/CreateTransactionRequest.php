<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sender_first_name' => 'required|string|max:255',
            'sender_middle_name' => 'nullable|string|max:255',
            'sender_last_name' => 'required|string|max:255',
            'sender_account_no' => 'required|string|max:20',
            'sender_account_type' => 'nullable|string',
            'sender_nin' => 'nullable|string|max:11',
            'sender_bvn' => 'nullable|string|max:11',
            'beneficiary_first_name' => 'required|string|max:255',
            'beneficiary_middle_name' => 'nullable|string|max:255',
            'beneficiary_last_name' => 'required|string|max:255',
            'beneficiary_account_no' => 'required|string|max:20',
            'beneficiary_account_type' => 'nullable|string',
            'beneficiary_nin' => 'nullable|string|max:11',
            'beneficiary_bvn' => 'nullable|string|max:11',
            'amount' => 'required|numeric|min:0',
            'transaction_type' => 'required|string|in:credit,debit,Credit,Debit',
            'narration' => 'nullable|string',
            'channel' => 'required|string',
            'location' => 'nullable|string',
            'transaction_ref' => 'nullable|string',
            'transaction_datetime' => 'required|date',
        ];
    }
}
