<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Support\UrlSecurity;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Validator as ValidatorFacade;

class WebhookController extends Controller
{
    public function configure(Request $request)
    {
        $vendor = $request->attributes->get('vendor');

        $validator = ValidatorFacade::make($request->all(), [
            'webhook_url' => ['nullable', 'url', 'max:2048'],
            'secret' => ['required_with:webhook_url', 'nullable', 'string', 'min:8', 'max:255'],
        ]);
        if ($validator->fails()) {
            $this->throwValidationError('The webhook payload is invalid.', $validator);
        }

        $validated = $validator->validated();

        $url = (string) ($validated['webhook_url'] ?? '');
        if ($url !== '' && ! UrlSecurity::isAllowedPublicHttpsUrl($url)) {
            throw new HttpResponseException(response()->json([
                'error' => 'validation_error',
                'message' => 'The webhook payload is invalid.',
                'details' => [
                    'webhook_url' => ['Webhook URL must be an HTTPS endpoint with a public host.'],
                ],
            ], 422));
        }

        $vendor->update([
            'webhook_url'    => $validated['webhook_url'] ?? null,
            'webhook_secret' => $validated['secret'] ?? null,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Webhook configuration saved.',
        ]);
    }

    private function throwValidationError(string $message, Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'error' => 'validation_error',
            'message' => $message,
            'details' => $validator->errors()->toArray(),
        ], 422));
    }
}
