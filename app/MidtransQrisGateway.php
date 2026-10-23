<?php

namespace App;

use Midtrans\Config;
use Midtrans\CoreApi;
use Midtrans\Transaction;
use RuntimeException;

class MidtransQrisGateway
{
    private function configure(?string $idempotencyKey = null): void
    {
        $key = config('services.midtrans.server_key');
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('Midtrans sandbox server key is not configured.');
        }

        Config::$serverKey = $key;
        Config::$isProduction = false;
        Config::$isSanitized = false;
        Config::$paymentIdempotencyKey = $idempotencyKey;
        Config::$appendNotifUrl = null;
        Config::$overrideNotifUrl = null;
        Config::$curlOptions = [CURLOPT_HTTPHEADER => [], CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15];
    }

    /** @param array<string, mixed> $payload */
    public function charge(array $payload, string $idempotencyKey): object
    {
        $this->configure($idempotencyKey);

        try {
            return CoreApi::charge($payload);
        } finally {
            Config::$paymentIdempotencyKey = null;
            Config::$curlOptions = [];
        }
    }

    /** @return object|array<string, mixed> */
    public function status(string $reference): object|array
    {
        $this->configure();

        try {
            return Transaction::status($reference);
        } finally {
            Config::$curlOptions = [];
        }
    }
}
