<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction' => ['required', 'array'],
            'transaction.transaction_id' => ['required', 'string', 'max:128'],
            'transaction.merchant_code' => ['required', 'string', 'max:64'],
            'transaction.branch_code' => ['required', 'string', 'max:64'],
            'transaction.pos_device_id' => ['required', 'string', 'max:64'],
            'transaction.totals' => ['required', 'array'],
            'transaction.totals.net' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => 'validation_error',
            'message' => 'The transaction payload is invalid.',
            'fields' => $validator->errors()->toArray(),
        ], 422));
    }
}
