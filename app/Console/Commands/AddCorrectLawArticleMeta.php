<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AddCorrectLawArticleMeta extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:add-correct-law-article-meta';

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
        $mappingPath = storage_path('app/tagged/mappping.json');
        $mappingJson = file_get_contents($mappingPath);
        $mappingArray = json_decode($mappingJson, true);

        $lawMetaArray = [
//            storage_path('app/reposss/Clanci2/ZKP') => storage_path('app/tagged/zkp.metadata.json'),
//            storage_path('app/reposss/Clanci2/ZSZD') => storage_path('app/tagged/zszd.metadata.json'),
//            storage_path('app/reposss/Clanci2/EKLJP') => storage_path('app/tagged/ekljp.metadata.json'),
//            storage_path('app/reposss/Clanci2/Ustav') => storage_path('app/tagged/ustav.metadata.json'),
//            storage_path('app/reposss/Clanci2/PrekrsajniZakon') => storage_path('app/tagged/Prekršajni_zakon.metadata.json'),
            storage_path('app/reposss/Clanci2/KazneniZakon') => storage_path('app/tagged/kazneniZakon.metadata.json'),
        ];

        foreach ($lawMetaArray as $lawPath => $metaPath) {
            $this->info("Processing: {$lawPath}");
            $lawMeta = json_decode(file_get_contents($metaPath), true);
            $pathContentsArray = scandir($lawPath);
            $pathContentsArray = array_filter($pathContentsArray, function ($item) {
                return Str::endsWith($item, '.pdf');
            });
            $progressBar = $this->output->createProgressBar(count($pathContentsArray));
            $progressBar->start();
            foreach ($pathContentsArray as $path) {
                $progressBar->advance();
                $pathExploded = explode('clanak-', $path);
                $articleNumber = Str::before($pathExploded[1] ?? '', '.pdf');
                $lawData = reset($lawMeta['law']);
                $articleTemplate = [
                    'law_code' => $lawData['law_code'] ?? null,
                    'law_code_alias' => $lawData['law_code_alias'] ?? null,
                    'citations' => [
                        [
                            'clanak' => $articleNumber,
                            'stavci' => [],
                            'tocke' => [],
                        ]
                    ]
                ];

                $articleFileMeta = array_merge($lawMeta, ['law' => [$articleTemplate]]);
                $articleFileMeta['file_name'] = basename($path);
                $articleMetaPath = storage_path('app/tagged/'. Str::replace('.pdf', '', basename($path)) .'.metadata.json');
                file_put_contents($articleMetaPath, json_encode($articleFileMeta, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
                $mappingArray[] = [
                    'file_path' => $lawPath.'/'.$path,
                    'file_id' => null,
                    'file_name' => basename($path),
                    'file_response' => null,
                    'file_response_metadata' => $articleMetaPath,
                ];
            }
            $progressBar->finish();
            file_put_contents($mappingPath, json_encode($mappingArray, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        }


        return Command::SUCCESS;
    }
}
