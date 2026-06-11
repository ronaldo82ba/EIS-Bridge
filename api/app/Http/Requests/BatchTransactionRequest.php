<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class BatchTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'batch_id' => ['required', 'string', 'max:128'],
            'transactions' => ['required', 'array', 'min:1', 'max:500'],
            'transactions.*' => ['required', 'array'],
            'transactions.*.transaction_id' => ['required', 'string', 'max:128'],
            'transactions.*.merchant_code' => ['required', 'string', 'max:64'],
            'transactions.*.branch_code' => ['required', 'string', 'max:64'],
            'transactions.*.pos_device_id' => ['required', 'string', 'max:64'],
            'transactions.*.totals' => ['required', 'array'],
            'transactions.*.totals.net' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => 'validation_error',
            'message' => 'The batch transaction payload is invalid.',
            'fields' => $validator->errors()->toArray(),
        ], 422));
    }
}
