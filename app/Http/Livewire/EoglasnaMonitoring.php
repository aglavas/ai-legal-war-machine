<?php

namespace App\Http\Livewire;

use App\Models\EoglasnaKeyword;
use App\Models\EoglasnaKeywordMatch;
use App\Models\EoglasnaNotice;
use App\Models\EoglasnaOsijekMonitoring;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class EoglasnaMonitoring extends Component
{
    use WithPagination;

    public string $tab = 'osijek'; // osijek|keywords|activity

    // Filters
    public string $searchOsijek = '';
    public string $searchKeyword = '';

    // Keyword form
    public bool $showKeywordModal = false;
    public array $editingKeyword = [];

    protected $paginationTheme = 'tailwind';

    protected $queryString = [
        'tab' => ['except' => 'osijek'],
        'searchOsijek' => ['except' => ''],
        'searchKeyword' => ['except' => ''],
    ];

    public function createKeyword(): void
    {
        $this->resetValidation();
        $this->editingKeyword = [
            'id' => null,
            'query' => '',
            'scope' => 'notice',
            'deep_scan' => false,
            'enabled' => true,
            'notes' => '',
        ];
        $this->showKeywordModal = true;
    }

    public function editKeyword(int $id): void
    {
        $this->resetValidation();
        $model = EoglasnaKeyword::findOrFail($id);
        $this->editingKeyword = $model->only(['id','query','scope','deep_scan','enabled','notes']);
        $this->showKeywordModal = true;
    }

    public function saveKeyword(): void
    {
        $rules = [
            'editingKeyword.query' => ['required','string','min:2'],
            'editingKeyword.scope' => ['required', Rule::in(['notice','court','institution','court_legal_bankruptcy','court_natural_bankruptcy'])],
            'editingKeyword.deep_scan' => ['boolean'],
            'editingKeyword.enabled' => ['boolean'],
            'editingKeyword.notes' => ['nullable','string'],
        ];
        $this->validate($rules);

        if (empty($this->editingKeyword['id'])) {
            EoglasnaKeyword::create($this->editingKeyword);
        } else {
            $model = EoglasnaKeyword::findOrFail($this->editingKeyword['id']);
            $model->update($this->editingKeyword);
        }

        $this->showKeywordModal = false;
    }

    public function deleteKeyword(int $id): void
    {
        EoglasnaKeyword::whereKey($id)->delete();
    }

    public function render()
    {
        // Osijek items
        $osijekQuery = EoglasnaOsijekMonitoring::query()->orderByDesc('date_published');
        if ($this->searchOsijek !== '') {
            $term = '%' . str_replace(' ', '%', $this->searchOsijek) . '%';
            $osijekQuery->where(function($q) use ($term) {
                $q->where('title', 'like', $term)
                  ->orWhere('case_number', 'like', $term)
                  ->orWhere('court_name', 'like', $term)
                  // include parsed participant columns
                  ->orWhere('name', 'like', $term)
                  ->orWhere('last_name', 'like', $term)
                  ->orWhere('oib', 'like', $term)
                  ->orWhere('city', 'like', $term)
                  ->orWhere('street', 'like', $term);
            });
        }
        $osijekItems = $osijekQuery->paginate(15, ['*'], 'osijek');

        // Keywords
        $kwQuery = EoglasnaKeyword::query()->orderByDesc('enabled')->orderBy('query');
        if ($this->searchKeyword !== '') {
            $term = '%' . str_replace(' ', '%', $this->searchKeyword) . '%';
            $kwQuery->where('query', 'like', $term);
        }
        $keywords = $kwQuery->paginate(15, ['*'], 'kw');

        // Recent keyword activity: join matches -> notices -> keyword
        $activity = EoglasnaKeywordMatch::query()
            ->select([
                'eoglasna_keyword_matches.*',
                'eoglasna_keywords.query as keyword_query',
                'eoglasna_keywords.scope as keyword_scope',
                'eoglasna_notices.title as notice_title',
                'eoglasna_notices.date_published as notice_date_published',
                'eoglasna_notices.case_number as notice_case_number',
                'eoglasna_notices.public_url as notice_public_url',
            ])
            ->leftJoin('eoglasna_keywords', 'eoglasna_keywords.id', '=', 'eoglasna_keyword_matches.keyword_id')
            ->leftJoin('eoglasna_notices', 'eoglasna_notices.uuid', '=', 'eoglasna_keyword_matches.notice_uuid')
            ->orderByDesc('eoglasna_keyword_matches.matched_at')
            ->paginate(15, ['*'], 'activity');

        return view('livewire.eoglasna-monitoring', [
            'osijekItems' => $osijekItems,
            'keywords' => $keywords,
            'activity' => $activity,
        ]);
    }
}
