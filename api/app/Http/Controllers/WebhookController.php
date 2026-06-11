<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Support\UrlSecurity;
use Illuminate\Validation\ValidationException;

class WebhookController extends Controller
{
    public function configure(Request $request)
    {
        $vendor = $request->attributes->get('vendor');
        $validated = $request->validate([
            'webhook_url' => ['nullable', 'url', 'max:2048'],
            'secret' => ['required_with:webhook_url', 'nullable', 'string', 'min:8', 'max:255'],
        ]);

        $url = (string) ($validated['webhook_url'] ?? '');
        if ($url !== '' && ! UrlSecurity::isAllowedPublicHttpsUrl($url)) {
            throw ValidationException::withMessages([
                'webhook_url' => 'Webhook URL must be an HTTPS endpoint with a public host.',
            ]);
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
}
