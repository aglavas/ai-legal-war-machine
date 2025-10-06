<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Process\Process;

class BulkFileTagger extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vs:bulk-tagger';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filesPath = "/reposss/Fileovi";
        $filesPathArray = scandir($filesPath);

        foreach ($filesPathArray as $folder) {
            if (in_array($folder, ['.', '..', 'KP-DO-731'])) {
                continue;
            }

            $isDir = is_dir($filesPath . '/' . $folder);

            if (!$isDir) {
                continue;
            }

            $subFolder = scandir($filesPath . '/' . $folder);
            foreach ($subFolder as $key => $fileName) {
                if (in_array($fileName, ['.', '..'])) {
                    continue;
                }
                $mappingJson = file_get_contents(storage_path('app/tagged/mappping.json'));
                $mappingArray = json_decode($mappingJson, true);
                $fileNameArray = collect($mappingArray)->pluck('file_name')->toArray();
                $this->info(json_encode($fileNameArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));


                if (in_array($fileName, $fileNameArray)) {
                    continue;
                }

                $fileExists = file_exists($filesPath . '/' . $folder . '/' . $fileName);

                if (!$fileExists) {
                    continue;
                }

                $filePath = $filesPath . '/' . $folder . '/' . $fileName;
                $filePath = escapeshellarg($filePath);

                echo shell_exec("php artisan ai:tag $filePath --store");
            }
            $this->info("Završena obrada foldera: " . $folder);
        }

        dd('stop');
    }

    /**
     * Upis JSON-a u XMP dc:Description polje PDF-a.
     *
     * @param string $pdfIn Putanja ulaznog PDF-a
     * @param string|array $json Sadržaj JSON-a (string ili PHP array koji će se json_encode-ati)
     * @param string|null $pdfOut Ako null, radi in-place (-overwrite_original); inače zapisuje u novi PDF (-o)
     * @param int $timeoutSec Timeout za exiftool poziv
     * @return string Putanja rezultirajućeg PDF-a
     */
    private function embedJsonToPdfXmpDescription(string $pdfIn, $json, ?string $pdfOut = null, int $timeoutSec = 120): string
    {
        $this->ensureExiftoolAvailable($timeoutSec);

        if (is_array($json)) {
            $json = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        // Validacija JSON-a
        json_decode($json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Neispravan JSON za XMP dc:Description: ' . json_last_error_msg());
        }

        $tmpJson = tempnam(sys_get_temp_dir(), 'pdfmeta_') . '.json';
        file_put_contents($tmpJson, $json);

        try {
            $args = [
                $this->exiftoolBin(),
                '-charset', 'UTF8',
                '-n',                    // numeričke vrijednosti bez formatiranja (bezveze ovdje, ali benigno)
                '-P',                    // očuvaj datume file sustava
                'XMP:Label=AI_Metadata',
                'XMP:MetadataDate=now',
                'XMP-dc:Description<=' . $tmpJson, // ključ: čitaj JSON iz datoteke
            ];

            if ($pdfOut) {
                $args[] = '-o';
                $args[] = $pdfOut;
            } else {
                $args[] = '-overwrite_original';
            }

            $args[] = $pdfIn;

            $this->runProcess($args, $timeoutSec);

            return $pdfOut ?: $pdfIn;
        } finally {
            @unlink($tmpJson);
        }
    }

    /**
     * Upis JSON-a u custom XMP namespace (npr. XMP-ai:MetadataJSON), ExifTool config je obavezan.
     * Ako $configPath nije proslijeđen, dinamički će se generirati privremeni config s namespace-om.
     *
     * @param string $pdfIn Putanja ulaznog PDF-a
     * @param string|array $json Sadržaj JSON-a (string ili array)
     * @param string|null $pdfOut Ako null, radi in-place; inače -o novi PDF
     * @param string|null $configPath Putanja do .config (ExifTool user-defined) ili null za auto-generiranje
     * @param string $tagName Puni XMP tag, default XMP-ai:MetadataJSON
     * @param string $namespaceUrl URL namespace-a u configu
     * @param int $timeoutSec Timeout
     * @return string Putanja rezultirajućeg PDF-a
     */
    private function embedJsonToPdfCustomXmp(
        string $pdfIn,
               $json,
        ?string $pdfOut = null,
        ?string $configPath = null,
        string $tagName = 'XMP-ai:MetadataJSON',
        string $namespaceUrl = 'http://example.com/ns/ai/1.0/',
        int $timeoutSec = 120
    ): string {
        $this->ensureExiftoolAvailable($timeoutSec);

        if (is_array($json)) {
            $json = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        json_decode($json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Neispravan JSON za custom XMP tag: ' . json_last_error_msg());
        }

        $tmpJson = tempnam(sys_get_temp_dir(), 'pdfmeta_') . '.json';
        file_put_contents($tmpJson, $json);

        $tmpCfg = null;
        if (! $configPath) {
            $tmpCfg = tempnam(sys_get_temp_dir(), 'exifcfg_') . '.config';
            $configPath = $tmpCfg;
            file_put_contents($configPath, $this->buildExiftoolAiConfig($namespaceUrl));
        }

        try {
            $args = [
                $this->exiftoolBin(),
                '-config', $configPath,
                '-charset', 'UTF8',
                '-n',
                '-P',
                'XMP:Label=AI_Metadata',
                'XMP:MetadataDate=now',
                $tagName . '<=' . $tmpJson, // npr. XMP-ai:MetadataJSON<=/tmp/file.json
            ];

            if ($pdfOut) {
                $args[] = '-o';
                $args[] = $pdfOut;
            } else {
                $args[] = '-overwrite_original';
            }

            $args[] = $pdfIn;

            $this->runProcess($args, $timeoutSec);

            return $pdfOut ?: $pdfIn;
        } finally {
            @unlink($tmpJson);
            if ($tmpCfg) {
                @unlink($tmpCfg);
            }
        }
    }

    /**
     * (Opcionalno) Čitanje natrag JSON-a iz dc:Description.
     */
    private function readJsonFromPdfXmpDescription(string $pdfPath, int $timeoutSec = 60): ?string
    {
        $this->ensureExiftoolAvailable($timeoutSec);

        $args = [
            $this->exiftoolBin(),
            '-charset', 'UTF8',
            '-s', '-s', '-s',                  // samo vrijednost
            '-XMP-dc:Description',
            $pdfPath,
        ];

        [$code, $out, $err] = $this->runProcess($args, $timeoutSec, false);

        if ($code !== 0) {
            return null;
        }
        $val = trim($out);
        return $val !== '' ? $val : null;
    }

    /**
     * Pomoćne: pokretanje exiftool-a i provjera dostupnosti.
     */
    private function exiftoolBin(): string
    {
        return env('EXIFTOOL_BIN', 'exiftool'); // po želji u .env: EXIFTOOL_BIN=/usr/bin/exiftool
    }

    private function ensureExiftoolAvailable(int $timeoutSec = 10): void
    {
        $args = [$this->exiftoolBin(), '-ver'];
        [$code, $out, $err] = $this->runProcess($args, $timeoutSec, false);
        if ($code !== 0) {
            throw new RuntimeException("ExifTool nije dostupan ili nije u PATH-u. Pokušani binarij: {$this->exiftoolBin()}");
        }
    }

    /**
     * Wrapper oko Symfony Process-a.
     *
     * @return array{0:int,1:string,2:string} [exitCode, stdout, stderr]
     */
    private function runProcess(array $args, int $timeoutSec, bool $throwOnError = true): array
    {
        $process = new Process($args, null, null, null, $timeoutSec);
        $process->run();

        $code = $process->getExitCode();
        $out  = $process->getOutput();
        $err  = $process->getErrorOutput();

        if ($throwOnError && $code !== 0) {
            throw new RuntimeException("ExifTool greška (exit={$code}): " . ($err ?: $out));
        }

        return [$code, $out, $err];
    }

    /**
     * Dinamički kreira ExifTool user-defined config s AI namespace-om.
     * Možeš ga i trajno spremiti u project i proslijediti kao $configPath.
     */
    private function buildExiftoolAiConfig(string $namespaceUrl): string
    {
        return <<<'CFG'
%Image::ExifTool::UserDefined = (
  'Image::ExifTool::XMP::Main' => {
    ai => { SubDirectory => { TagTable => 'Image::ExifTool::UserDefined::AIMeta' } },
  },
);
%Image::ExifTool::UserDefined::AIMeta = (
  GROUPS    => { 0 => 'XMP', 1 => 'XMP-ai', 2 => 'Document' },
  NAMESPACE => { 'ai' => 'REPLACE_NAMESPACE' },
  WRITABLE  => 'string',
  'MetadataJSON' => { Writable => 'string' },
);
1;
CFG;
    }
}
