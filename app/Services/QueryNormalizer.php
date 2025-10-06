<?php

namespace App\Services;

class QueryNormalizer
{
    // regex obrasci (prilagođeni za HR kontekst)
    private const RE_IMEI   = '/(?<!\d)(\d{14,16})(?!\d)/';
    private const RE_IMSI   = '/(?<!\d)(\d{14,15})(?!\d)/';
    private const RE_ICCID  = '/(?<!\d)(\d{19,22})(?!\d)/';
    private const RE_MSISDN = '/\+?\d{8,15}/';
    private const RE_CASEID = '/\b[A-Z][a-zA-Z]?-?\d{1,5}\/\d{4}\b/u';
    private const RE_CITATI = '/(čl\.?\s*\d+[a-z]?(?:\s*st\.?\s*\d+)?(?:\s*t\.?\s*\d+)?)\s*(ZKP|Ustav\s*RH|EKLJP)/iu';

    public function normalize(string $text, array $opts = []): array
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text));

        $caseId = $this->firstMatch(self::RE_CASEID, $clean);
        $related = $this->allMatches(self::RE_CASEID, $clean);
        $related = array_values(array_unique(array_filter($related, fn($v) => $v !== $caseId)));

        $imeis   = $this->allMatches(self::RE_IMEI, $clean);
        $imsis   = $this->allMatches(self::RE_IMSI, $clean);
        $iccids  = $this->allMatches(self::RE_ICCID, $clean);
        $msisdns = $this->allMatches(self::RE_MSISDN, $clean);

        // citati (čl. X st. Y ZKP/…)
        preg_match_all(self::RE_CITATI, $clean, $m, PREG_SET_ORDER);
        $citati = [];
        foreach ($m as $hit) {
            $citati[] = trim($hit[0]);
        }

        // Problem: prva rečenica ili fallback
        $problem = $opts['problem'] ?? mb_substr($clean, 0, 200);
        if (str_contains($problem, '.')) {
            $problem = trim(mb_substr($problem, 0, mb_strpos($problem, '.') + 1));
        }

        // Heuristike za ključne riječi i kategorije
        $kw = $this->keywordsFrom($clean, $imeis, $msisdns);
        $cats = $this->categoriesFrom($clean);

        $targetStores = $opts['target_stores'] ?? []; // npr. ["Pp-2343/2025","Su-2423/2025","ZAKONIK","Authorities_HR","ArgBank_Pretresi_HR"]

        $rq = [
            'problem'           => $problem,
            'jurisdikcija'      => 'HR',
            'vrste_dokumenata'  => ['dokument_predmeta','zakon','presuda','primjer'],
            'ključne_riječi'    => array_values(array_unique($kw)),
            'kategorije_povrede'=> $cats,
            'datumi'            => $opts['datumi'] ?? ['od' => '2018-01-01', 'do' => date('Y-m-d')],
            'članci_prioritet'  => $citati ?: ($opts['članci_prioritet'] ?? []),
            'case_id'           => $caseId,
            'related_cases'     => $related,
            'identifikatori'    => [
                'device' => array_filter([
                    'tip'    => $this->guessDeviceTip($clean),
                    'marka'  => $this->guessBrand($clean),
                    'model'  => $this->guessModel($clean),
                    'imei'   => $imeis,
                    'imsi'   => $imsis,
                    'iccid'  => $iccids,
                    'msisdn' => $msisdns,
                    'serijski_broj' => null
                ])
            ],
            'target_stores'     => $targetStores,
            'limit'             => $opts['limit'] ?? 6,
            'preferencije'      => $opts['preferencije'] ?? ['statuti'=>true,'presude'=>true,'argbank'=>true,'službeni_izvor'=>true],
            'jezik'             => 'hr',
            'napomena'          => $opts['napomena'] ?? 'tražiti zapisnik/potvrdu o oduzimanju, nalog za pretragu informatičkih uređaja, forenzički nalaz, račun/kupnja',
            'pitanja_za_korisnika' => $this->followups($clean, $imeis, $msisdns, $caseId)
        ];

        return $rq;
    }

    private function firstMatch(string $re, string $text): ?string
    {
        return preg_match($re, $text, $m) ? $m[0] : null;
    }
    private function allMatches(string $re, string $text): array
    {
        preg_match_all($re, $text, $m);
        return array_values(array_unique($m[0] ?? []));
    }

    private function keywordsFrom(string $text, array $imeis, array $msisdns): array
    {
        $core = ['mobitel','mobilni telefon','smartphone','oduzimanje','privremeno oduzimanje','izuzimanje','zapisnik','potvrda','forenzičko izvješće','nalog za pretragu','informatički uređaj','digitalni dokazi','chain-of-custody'];
        if ($imeis) $core[] = 'IMEI';
        if ($msisdns) $core[] = 'MSISDN';
        // jednostavan sinonimizer
        if (stripos($text, 'iPhone') !== false) $core[] = 'iPhone';
        if (stripos($text, 'Samsung') !== false) $core[] = 'Samsung';
        return $core;
    }

    private function categoriesFrom(string $text): array
    {
        $map = [
            'formal' => 'Formalni elementi',
            'posebn' => 'Posebnost',
            'osnov'  => 'Osnovanost',
            'hitn'   => 'Hitnost / vremenska opravdanost',
            'cilj'   => 'Cilj pretresa',
            'zakon'  => 'Zakonitost postupanja',
            'forenzi' => 'Zakonitost postupanja',
            'noćn'   => 'Hitnost / vremenska opravdanost',
            'preširok' => 'Posebnost',
        ];
        $out = [];
        foreach ($map as $needle => $cat) {
            if (stripos($text, $needle) !== false) $out[$cat] = true;
        }
        // default minimalni set
        if (!$out) {
            $out['Zakonitost postupanja'] = true;
        }
        return array_keys($out);
    }

    private function guessDeviceTip(string $text): ?string
    {
        if (stripos($text, 'mobitel') !== false || stripos($text, 'telefon') !== false || stripos($text, 'smartphone') !== false) return 'mobitel';
        return null;
    }
    private function guessBrand(string $text): ?string
    {
        foreach (['Apple','iPhone','Samsung','Xiaomi','Huawei','Google','Pixel','OnePlus'] as $b) {
            if (stripos($text, $b) !== false) return $b;
        }
        return null;
    }
    private function guessModel(string $text): ?string
    {
        if (preg_match('/iPhone\s+(?:\d{1,2}|[A-Za-z0-9\s\+]+)/i', $text, $m)) return trim($m[0]);
        if (preg_match('/Samsung\s+Galaxy\s+[A-Za-z0-9\+\s\-]+/i', $text, $m)) return trim($m[0]);
        return null;
    }

    private function followups(string $text, array $imeis, array $msisdns, ?string $caseId): array
    {
        $qs = [];
        if (!$caseId) $qs[] = 'Molim točan broj predmeta (npr. Pp-2343/2025).';
        if (!$imeis)  $qs[] = 'Imate li IMEI brojeve uređaja?';
        if (!$msisdns) $qs[] = 'Koji je telefonski broj (MSISDN) uređaja?';
        $qs[] = 'Možete li priložiti potvrdu o oduzimanju ili zapisnik o pretrazi?';
        return array_slice($qs, 0, 4);
    }
}
