<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class SeedCases extends Command
{
    protected $signature = 'cases:seed {--truncate} {--connection=}';
    protected $description = 'Seed cases table with three linked Croatian matters (Pp Prz-74/2025, Su-1717/2025, KP-DO-731/2025).';

    public function handle(): int
    {
        $conn = $this->option('connection') ?: config('database.default');
        $table = DB::connection($conn)->table('cases');

        if ($this->option('truncate')) {
            $this->warn("Truncating cases on connection [{$conn}]...");

            $table->truncate();
        }

        $now = Carbon::now();

        // Helper to JSON-encode tags safely (works if column is text or json/jsonb)
        $json = static function (array $tags): string {
        return json_encode($tags, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        };

        $cases = [
            [
                'id' => 1,
                'case_number' => 'Pp Prz-74/2025',
                'title' => 'Naredba za pretragu – Pp Prz-74/2025',
                'client_name' => 'Andrija Glavaš',
                'opponent_name'=> 'MUP PU osječko-baranjska (SOKO)',
                'court' => 'Općinski sud u Osijeku – Prekršajni odjel',
                'jurisdiction' => 'HR',
                'judge' => 'sutkinja Dunja Bertok',
                'filing_date' => Carbon::parse('2025-06-09')->toDateString(),
                'status' => 'arhiviran (10.07.2025)',
                'tags' => $json(['prekršaj','naredba za pretragu','ZSZĐ čl.54 st.3','ZKP 247-260','tajnost izvida']),
                'description' => 'Naredba za pretragu doma i drugih prostorija izdana 09.06.2025.; osnova: ZSZĐ 54/3; izvršenje po ZKP 247–260; jezgrovni dokumenti u arhiviranom spisu.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'case_number' => 'Su-1717/2025',
                'title' => 'Sudska uprava – uvid u spis Pp Prz-74/2025',
                'client_name' => 'Andrija Glavaš',
                'opponent_name'=> 'Općinski sud u Osijeku (Ured predsjednice suda)',
                'court' => 'Općinski sud u Osijeku – Sudska uprava',
                'jurisdiction' => 'HR',
                'judge' => null, // upravni predmet pri predsjednici suda
                'filing_date' => Carbon::parse('2025-09-01')->toDateString(),
                'status' => 'u tijeku',
                'tags' => $json(['upravni predmet','uvid u spis','PZ 150/1-4','ZKP 183','ZKP 184/5','tajnost izvida 206.f']),
                'description' => 'Zahtjev za uvid/preslike arhiviranog Pp Prz-74/2025; traži se in camera pregled i dostava redigirane jezgre.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'case_number' => 'KP-DO-731/2025',
                'title' => 'Kazneni predmet – KP-DO-731/2025 (KIS-DO-122)',
                'client_name' => 'Andrija Glavaš',
                'opponent_name'=> 'Općinsko državno odvjetništvo u Osijeku',
                'court' => 'Općinsko državno odvjetništvo u Osijeku',
                'jurisdiction' => 'HR',
                'judge' => null, // u fazi DORH izvida/istrage nema suca raspravnog
                'filing_date' => Carbon::parse('2025-06-30')->toDateString(), // datum KP (K-51/2025) prebačeno u KP-DO-731
                'status' => 'izvidi/istraga',
                'tags' => $json(['kazneni predmet','K-51/2025','KIS-DO-122','KP-DO-73*','dokazi iz Pp Prz-74/2025']),
                'description' => 'Kaznena prijava proizašla iz pretrage po Pp Prz-74/2025; predmet je u fazi izvida/istrage (bez optužnice).',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        ];

        // Laravel 8+: upsert available
        DB::connection($conn)->table('cases')->upsert($cases, ['case_number'],
            [
                'title','client_name','opponent_name','court','jurisdiction','judge',
                'filing_date','status','tags','description','updated_at'
            ]
        );

        $this->info('Cases table seeded/updated successfully on connection: '.$conn);
        return Command::SUCCESS;
    }
}
