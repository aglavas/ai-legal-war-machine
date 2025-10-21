<?php

return [
    'operations' => [
        'sudovi' => [
            'args' => [],
            'default_selection' => ['id', 'sudNaziv', 'sudOznaka', 'razina', 'prod'],
        ],
        'vrstaUpisnika' => [
            'args' => [],
            'default_selection' => ['id', 'naziv', 'oznaka'],
        ],
        'predmet' => [
            'args' => [
                'sud' => 'BigInteger',
                'oznakaBroj' => 'String',
            ],
            'default_selection' => [
                'broj', 'id', 'datumArhiviranja', 'datumZalbe', 'datumDodjele', 'datumDonosenjaOdluke',
                'datumOsnivanja', 'datumOtpreme', 'datumOvrsnosti', 'nazivUj', 'oznakaBroj', 'prekrsajni',
                'spisIzvanSuda', 'spisNaVisemSudu', 'upisnikNaziv', 'upisnikOznaka', 'vrstaOdluke',
                'vrstaPredmeta', 'lastUpdateTime',
                'rocista' => ['odgoda', 'plPocetak', 'plZavrsetak', 'stPocetak', 'stZavrsetak', 'sobanaziv', 'sobaoznaka', 'vrstaRadnje'],
                'pismena' => ['datum', 'vrsta', 'tip', 'podnositelj', 'prilozi', 'predmetId'],
                'povezaniPredmeti' => ['opis', 'vezaniOznaka', 'vezaniOznakaBroj', 'datumVeze', 'tip'],
                'vjecnici' => ['ime', 'vrsta'],
                'stranke' => ['naziv', 'nazivuloge'],
            ],
        ],
        'posljednjeAzuriranje' => [
            'args' => [],
            'default_selection' => null, // scalar result
        ],
    ],
];
