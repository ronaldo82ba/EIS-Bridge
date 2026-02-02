<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function configure(Request $request)
    {
        $vendor = $request->attributes->get('vendor');

        $vendor->update([
            'webhook_url'    => $request->input('webhook_url'),
            'webhook_secret' => $request->input('secret'),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Webhook configuration saved.',
        ]);
    }
}
