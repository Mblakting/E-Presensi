<?php

namespace App\Competition\Http;

use Illuminate\Http\Response;
use RuntimeException;

class LivenessScriptController
{
    public function __invoke(): Response
    {
        $path = app_path('Competition/Resources/liveness.js');
        $script = file_get_contents($path);
        if ($script === false) {
            throw new RuntimeException('Competition liveness script could not be read.');
        }

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
