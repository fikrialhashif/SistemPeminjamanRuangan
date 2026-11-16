<?php

namespace App\Console\Commands;

use App\Models\Resource;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateQrToken extends Command
{
    protected $signature = 'qr:generate {resourceId}';

    protected $description = 'Generate a QR token for a resource';

    public function handle(): int
    {
        $resource = Resource::findOrFail($this->argument('resourceId'));

        $token = Str::random(64);
        $ttl = config('booking.qr_ttl_seconds');

        cache()->put("qr_token:{$resource->id}", $token, $ttl);

        $this->info("Resource: {$resource->name}");
        $this->info("Token: {$token}");
        $this->info("TTL: {$ttl}s");

        return self::SUCCESS;
    }
}
