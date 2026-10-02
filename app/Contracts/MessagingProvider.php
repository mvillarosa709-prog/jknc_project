<?php

namespace App\Contracts;

use App\Models\MessageEntry;

interface MessagingProvider
{
    public function ingest(array $payload): void;

    public function send(MessageEntry $entry): void;
}
