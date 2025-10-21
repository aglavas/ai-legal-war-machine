<?php

namespace App\Http\Livewire;

use App\GraphQL\AutoDiscovery\Exceptions\GraphQLQueryException;
use App\GraphQL\AutoDiscovery\GraphQLAutoClient;
use Carbon\Carbon;
use Livewire\Component;

class EpredmetWidget extends Component
{
    public int|string $sud = 5107;
    public string $oznakaBroj = 'Pp Prz-74/2025';

    public array|null $data = null;
    public ?string $error = null;
    public bool $loading = false;
    public ?float $tookMs = null;

    // Labels for key dates in the response
    public array $dateLabels = [
        'datumDodjele' => 'Dodjela',
        'datumDonosenjaOdluke' => 'Odluka',
        'datumOtpreme' => 'Otprema',
        'datumOvrsnosti' => 'Ovršnost',
        'datumZalbe' => 'Žalba',
        'datumArhiviranja' => 'Arhiviranje',
    ];

    public function mount(): void
    {
        // Auto-fetch initial case so the dashboard shows data immediately
        $this->fetch();
    }

    public function updated($property): void
    {
        // Clear previous errors when editing inputs
        $this->error = null;
    }

    public function fetch(): void
    {
        $this->validate([
            'sud' => 'required',
            'oznakaBroj' => 'required|string|min:2',
        ]);

        $this->loading = true;
        $this->error = null;
        $this->tookMs = null;

        $start = microtime(true);
        try {
            /** @var GraphQLAutoClient $client */
            $client = app(GraphQLAutoClient::class);
            $resp = $client->run('predmet', [
                'sud' => is_numeric($this->sud) ? (int) $this->sud : $this->sud,
                'oznakaBroj' => $this->oznakaBroj,
            ]);
            $this->data = is_array($resp) ? $resp : [];
            $this->normalizeData();
        } catch (GraphQLQueryException $e) {
            $this->error = $e->getMessage();
            $this->data = null;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            $this->data = null;
        } finally {
            $this->tookMs = round((microtime(true) - $start) * 1000, 1);
            $this->loading = false;
        }
    }

    private function normalizeData(): void
    {
        if (!is_array($this->data)) {
            return;
        }
        // Normalize pismena dates and sort desc by datum
        $pismenaArray = $this->data['pismena'] ?? [];
        if (is_array($pismenaArray) && !empty($pismenaArray)) {
            $pismenaArray = collect($pismenaArray)
                ->map(function ($pisma) {
                    $datumPismena = $pisma['datum'] ?? null;
                    if ($datumPismena) {
                        try {
                            $pisma['datum'] = Carbon::parse($datumPismena)->format('Y-m-d H:i:s');
                        } catch (\Throwable $e) {
                            // leave as-is if parsing fails
                        }
                    }
                    return $pisma;
                })
                ->sortByDesc(function ($pisma) {
                    return $pisma['datum'] ?? null;
                })
                ->values()
                ->toArray();
            $this->data['pismena'] = $pismenaArray;
        }
    }

    public function render()
    {
        return view('livewire.epredmet-widget');
    }
}
