<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OpenAI Logs</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    <link title="timeline-styles" rel="stylesheet"
          href="https://cdn.knightlab.com/libs/timeline3/latest/css/timeline.css">

    <!-- 2 -->
    <script src="https://cdn.knightlab.com/libs/timeline3/latest/js/timeline.js"></script>
    @livewireStyles
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; }
        .container { margin: 2rem auto; padding: 0 1rem; }
    </style>
    <style>
        /* Stack with tiny seam between timenavs */
        #tl-compare-stack {
            display: flex; flex-direction: column; gap: 4px;
        }
        .tl-vcol { position: relative; background: #fff; border: 1px solid #e5e7eb; }
        .tl-vcol.top { border-radius: 12px 12px 0 0; }
        .tl-vcol.bottom { border-radius: 0 0 12px 12px; }

        /* Timeline boxes */
        .tl-embed { width: 100%; height: 40vh; min-height: 320px; }

        /* Remove extra spacing/branding to bring them closer visually */
        #tl-compare-stack .tl-embed .tl-attribution { display: none !important; }
        #tl-compare-stack .tl-embed .vco-navigation { margin: 0 !important; }
        #tl-compare-stack .tl-embed .tl-credit { display: none !important; }
        #tl-compare-stack .tl-embed .tl-timenav { border: 0 !important; }

        /* Crosshair overlay (visual only) */
        .sync-overlay { pointer-events: none; position: absolute; inset: 0; }
        .sync-line {
            position: absolute; top: 0; bottom: 0; width: 2px;
            background: #0ea5e9; opacity: .9; transform: translateX(-1px);
            left: 50%;
        }
        .sync-tooltip {
            position: absolute; left: 50%; transform: translateX(-50%);
            top: 6px; background: #0ea5e9; color: #fff;
            font: 12px/1.2 ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
            padding: 3px 6px; border-radius: 4px; white-space: nowrap;
            box-shadow: 0 4px 10px rgba(2,6,23,.15);
        }
    </style>
</head>
<body class="bg-slate-50">
<div class="container">
    <div id="tl-compare-stack">
        <!-- Top timeline -->
        <div class="tl-vcol top" id="col-top">
            <div id="timeline-top" class="tl-embed"></div>
            <div class="sync-overlay" aria-hidden="true">
                <div class="sync-line"></div>
                <div class="sync-tooltip"></div>
            </div>
        </div>

        <!-- Bottom timeline -->
        <div class="tl-vcol bottom" id="col-bottom">
            <div id="timeline-bottom" class="tl-embed"></div>
            <div class="sync-overlay" aria-hidden="true">
                <div class="sync-line"></div>
                <div class="sync-tooltip"></div>
            </div>
        </div>
    </div>
</div>

@livewireScripts
<script type="text/javascript">
    // make_the_json() is some javascript function you've written
    // which creates the appropriate JSON configuration
    var timeline_json = JSON.parse(JSON.stringify({
    "scale": "human",
        "title": {
        "text": {
            "headline": "Predmet Pp Prz-74/2025 – kronologija događaja",
                "text": "Kronološki pregled ključnih datuma od naredbe za pretragu 9. lipnja 2025. do naknadnih pravnih radnji."
        },
        "background": {
            "color": "#0f9bd1"
        },
        "autolink": true,
            "unique_id": "title-slide"
    },
    "events": [
        {
            "start_date": {
                "year": 2025,
                "month": 6,
                "day": 9,
                "display_date": "9. lipnja 2025."
            },
            "text": {
                "headline": "Zahtjev policije za pretragu — Pp Prz-74/2025",
                "text": "<p>Policijska uprava osječko-baranjska, Sektor kriminalističke policije, Služba organiziranog kriminaliteta, podnosi obrazloženi zahtjev Općinskom sudu u Osijeku za izdavanje naredbe za pretragu doma i drugih prostorija kojima se koristi Andrija Glavaš, Primorska 5, Osijek (klasa: NK-214-05/25-01/1155, urbroj: 511-07-11-25-2, 9. lipnja 2025.).</p><p>U zahtjevu se navodi osnovana sumnja na počinjenje prekršaja prema Zakonu o suzbijanju zlouporabe droga (npr. posjedovanje tvari poput \"amfetamin-speed\", THC i dr.).</p><details><summary>Pravne reference</summary><ul><li>Zakon o suzbijanju zlouporabe droga — čl. 64 st. 3 i/ili čl. 54 st. 3</li><li>Zakon o kaznenom postupku (NN 152/08, 76/09, 80/11, 121/11, 91/12, 143/12, 56/13, 145/13, 152/14, 70/17, 126/19, 130/20, 80/22, 36/24, 72/25)</li></ul></details>"
            },
            "group": "Pp Prz-74",
            "autolink": true,
            "unique_id": "pp-prz-74-2025-zahtjev-pu"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 6,
                "day": 9,
                "display_date": "9. lipnja 2025."
            },
            "end_date": {
                "year": 2025,
                "month": 6,
                "day": 12,
                "display_date": "12. lipnja 2025."
            },
            "text": {
                "headline": "Naredba suda za pretragu doma i prostorija — Pp Prz-74/2025",
                "text": "<p>Općinski sud u Osijeku, po sutkinji Dunji Bertok, izdaje naredbu za pretragu doma i drugih prostorija kojima se koristi Andrija Glavaš (OIB: 25041200286), na adresi Primorska 5, Osijek, po zahtjevu PU osječko-baranjske (9. lipnja 2025.).</p><p>I. Na temelju čl. 159 st. 1 t. 1 Prekršajnog zakona, u vezi s čl. 240 ZKP-a, odobrava se pretraga doma i drugih prostorija.</p><p>II. Izvršenje se povjerava policijskim službenicima PU osječko-baranjske (Sektor kriminalističke policije, Služba organiziranog kriminaliteta) koji su dužni postupati prema čl. 247–260 ZKP-a i primjerak zapisnika dostaviti sudu u roku od 3 dana (poziv na broj: Pp Prz-74/2025).</p><p>III. Rok za izvršenje naredbe: 3 dana od trenutka izdavanja.</p><p>IV. Osoba kod koje se obavlja pretraga mora biti upoznata da prije početka pretrage ima pravo izvijestiti branitelja.</p><p>UPUTA O PRAVNOM LIJEKU: Protiv ove naredbe žalba nije dopuštena.</p><details><summary>Pravne reference</summary><ul><li>Prekršajni zakon — čl. 159 st. 1 t. 1 (NN 107/07, 39/13, 157/13, 110/15, 70/17, 118/18, 114/22)</li><li>Zakon o kaznenom postupku — čl. 240; čl. 247–260 (NN 152/08, 76/09, 80/11, 121/11, 91/12, 143/12, 56/13, 145/13, 152/14, 70/17, 126/19, 130/20, 80/22, 36/24, 72/25)</li><li>Zakon o suzbijanju zlouporabe droga — čl. 54 st. 3 i/ili čl. 64 st. 3</li></ul></details>"
            },
            "group": "Pp Prz-74",
            "display_date": "9.–12. lipnja 2025.",
            "autolink": true,
            "unique_id": "pp-prz-74-2025-naredba-pretrage"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 6,
                "day": 9,
                "hour": 11,
                "minute": 0
            },
            "end_date": {
                "year": 2025,
                "month": 6,
                "day": 9,
                "hour": 12,
                "minute": 40
            },
            "text": {
                "headline": "Pretraga doma i drugih prostorija (Osijek, Primorska 5)",
                "text": "<p>Po naredbi Općinskog suda u Osijeku, Prekršajni odjel broj Pp Prz-74/2025-2, izvršena je pretraga doma i drugih prostorija. Pronađeno i oduzeto (POPOP):</p><ul><li>Hašiš, PE vrećica, 18,00 g (POPOP 01422485)</li><li>Konoplja, 2 cvjetna vrha, staklenka, 2,09 g, >0,3% THC (POPOP 01422485)</li><li>Konoplja, usitnjeni cvjetni vrhovi, PE vrećica, 2,35 g, >0,3% THC (POPOP 01422485)</li><li>Konoplja, više cvjetnih vrhova, PE vrećica, 21,4 g, >0,3% THC (POPOP 01422486)</li><li>MDMA, bijela grumenasta materija, PE vrećica, 0,38 g (POPOP 01422486)</li><li>Amfetamin „speed“, PE vrećica, 3 g (POPOP 01422486)</li><li>Psilocibin gljive, staklenka, 6,9 g (POPOP 01422488)</li><li>Digitalna vaga „digital scala“, 0,01–100 g, s tragovima konoplje (POPOP 01422487)</li><li>Digitalna vaga „On balance CJ-20 Scale“, 0,001–20 g, s priborom (POPOP 01422487)</li><li>Automatska puška M70 AB, ser. br. 669991; 2 spremnika s 60 kom. streljiva 7,62 mm; dodatnih 80 kom. streljiva 7,62 mm; bajonet; pribor za čišćenje (POPOP 01422489)</li><li>Dodatna 3 spremnika s 89 kom. streljiva 7,62 mm; 2 kutije s 80 kom. streljiva 7,62 mm (POPOP 01422490)</li></ul><details><summary>Pravne reference</summary><ul><li>Naredba: Općinski sud Osijek, Prekršajni odjel, Pp Prz-74/2025-2</li><li>KZ — čl. 190. st. 2. (Neovlaštena proizvodnja i promet drogama)</li><li>KZ — čl. 331. st. 1. i st. 3. (Nedozvoljeno posjedovanje, izrada i nabavljanje oružja i eksplozivnih tvari)</li></ul></details>"
            },
            "group": "Pp Prz-74",
            "display_date": "09.06.2025., 11:00–12:40",
            "autolink": true,
            "unique_id": "pretraga-doma-i-drugih-prostorija-osijek-primorska-5-2025-06-09-1100-1240"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 6,
                "day": 9
            },
            "text": {
                "headline": "Preliminarno ispitivanje uzoraka i nalazi",
                "text": "<p>Po obavljenom vaganju provedeno je preliminarno ispitivanje uzoraka sljedećim testovima:</p><ol><li>M.M.C. International B.V. General Screening / Multi Party Drugs Test</li><li>M.M.C. International B.V. Cannabis Test</li><li>M.M.C. International B.V. Crystal Meth/XTC Test (Meth)</li><li>M.M.C. International B.V. Amphetamines/MDMA</li><li>M.M.C. International B.V. Opiates/Amphetamines Test</li></ol><p>Rezultati ukazuju na osnove sumnje u prisutnost: konoplje s &gt;0,3% THC (više uzoraka), MDMA, amfetamina (\"speed\") te psilocibinskih gljiva. Privremeno oduzeti predmeti bit će proslijeđeni na pohranu u Centar za forenzična ispitivanja, istraživanja i vještačenja \"Ivan Vučetić\" u Zagrebu.</p><p><em>Napomena:</em> Točnu vrstu tvari, masu i udio djelatne tvari moguće je odrediti isključivo vještačenjem u CFIIV \"Ivan Vučetić\".</p><details><summary>Pravne i predmetne reference</summary><ul><li>Službena zabilješka sastavljena u PU osječko-baranjskoj: 09.06.2025.</li><li>Predmeti proslijeđeni: CFIIV \"Ivan Vučetić\" (Zagreb)</li></ul></details>"
            },
            "group": "Pp Prz-74",
            "display_date": "09.06.2025"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 6,
                "day": 9,
                "hour": 12,
                "minute": 50
            },
            "end_date": {
                "year": 2025,
                "month": 6,
                "day": 9,
                "hour": 13,
                "minute": 15
            },
            "text": {
                "headline": "Privremeno oduzimanje mobilnog uređaja (POPOP 01422491)",
                "text": "<p>Istoga dana, u prostorijama PU Osječko-baranjske, privremeno je oduzet mobilni telefon marke „Huawei nova 9 SE“ (IMEI: 8679090622498823 i 867909063998821; pozivni brojevi: 098/965 5609, 095/584 6314).</p><details><summary>Pravne reference</summary><ul><li>POPOP ser. br. 01422491</li><li>ZKP — odredbe o privremenom oduzimanju predmeta</li></ul></details> <p>Provedena dokazna radnja privremenog oduzimanja predmeta bez naloga. Izdana potvrda o oduzimanju: br. 01 922 437. Popis privremeno oduzetih predmeta priložen uz zapisnik.</p><details><summary>Pravne reference</summary><ul><li>Članak 261. Zakona o kaznenom postupku (ZKP)</li><li>Članak 212. ZKP</li><li>Članak 85. stavak 1. i 7. ZKP (prava prisutnih osoba)</li><li>Članak 206.f ZKP (tajnost izvida)</li><li>Članak 213. stavak 3. ZKP (nejavnost istraživanja / tajnost)</li><li>Članak 231. stavak 2. ZKP (nejavnost istrage / tajnost)</li></ul></details>"
            },
            "group": "Pp Prz-74",
            "display_date": "09. 06. 2025, 12:50–13:15",
            "autolink": true,
            "unique_id": "privremeno-oduzimanje-mobilnog-uredaja-popop-01422491-2025-06-09"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 6,
                "day": 9,
                "hour": 14,
                "minute": 35,
                "display_date": "09.06.2025., 14:35"
            },
            "text": {
                "headline": "Pouka o pravima osumnjičenika — Andrija Glavaš",
                "text": "<p>Temeljem članka 208.a st. 1 i 2 Zakona o kaznenom postupku (ZKP), osumnjičenik Andrija Glavaš (rođen 14.10.1989. u Osijeku) poučen je o svojim pravima. Mjesto: Osijek. Vrijeme: 09.06.2025. u 14:35 sati. Policijski službenik: Aleksandar Sitarić. Predmet: 511-07-11-K-51/25.</p><ul><li>Pravo na branitelja</li><li>Pravo na tumačenje i prevođenje</li><li>Pravo da nije dužan iskazivati niti odgovarati na pitanja</li><li>Pravo da u svakom trenutku može napustiti policijske prostorije, osim u slučaju uhićenja</li></ul><details><summary>Pravne reference</summary><ul><li>Zakon o kaznenom postupku — čl. 208.a st. 1 i 2</li><li>Zakon o kaznenom postupku — čl. 8 (tumačenje i prevođenje)</li><li>Zakon o kaznenom postupku — čl. 108 (napuštanje policijskih prostorija)</li></ul></details>"
            },
            "group": "KP-DO-731",
            "display_date": "09.06.2025., 14:35",
            "autolink": true,
            "unique_id": "pouka-o-pravima-osumnjicenika-andrija-glavas-2025-06-09"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 6,
                "day": 9,
                "hour": 14,
                "minute": 45
            },
            "end_date": {
                "year": 2025,
                "month": 6,
                "day": 9,
                "hour": 15,
                "minute": 12
            },
            "display_date": "9. 6. 2025., 14:45–15:12",
            "group": "KP-DO-731",
            "text": {
                "headline": "Ispitivanje i AV snimanje osumnjičenika – PU osječko-baranjska",
                "text": "<p>U prostorijama PU osječko-baranjske započinje audio-video snimanje i ispitivanje osumnjičenika Andrije Glavaša. Prisotni: policijski službenik Aleksandar Simović, zapisničar Mate Surać, stručna osoba za tehničko snimanje Miroslav Pandurević i osumnjičenik. Osobe su upozorene na tajnost izvida i mogućnost korištenja snimke kao dokaza.</p><p>Osumnjičenik potvrđuje da razumije jezik postupka, prima pisanu pouku o pravima, te se najprije odriče prava na branitelja uz upozorenje o posljedicama, uz napomenu da to pravo može zatražiti u bilo kojem trenutku.</p><p>Tereti se za: neovlaštenu proizvodnju i promet drogama te nedozvoljeno posjedovanje, izradu i nabavljanje oružja i eksplozivnih tvari. Pretraga je pronašla više vrsta droga i oružje.</p><details><summary>Pravne reference</summary><ul><li>Zakon o kaznenom postupku – čl. 22 st. 1 (podatci pri prvom ispitivanju, pravna pouka)</li><li>Kazneni zakon – čl. 190 st. 2 (neovlaštena proizvodnja i promet drogama)</li><li>Kazneni zakon – čl. 31 st. 1 i 3 (nedozvoljeno posjedovanje, izrada i nabavljanje oružja i eksplozivnih tvari); u jednom navodu spomenut i čl. 301 st. 1 i 3</li></ul></details><details><summary>Oduzeti/pronađeni predmeti (sažetak)</summary><ul><li>Hašiš: ukupno 18 g</li><li>Konoplja/cvjetni vrhovi: 2,09 g; 2,35 g; 21,4 g (sadržaj THC > 0,3%)</li><li>MDMA: ~0,36 g</li><li>Amfetamin (speed): ~3 g</li><li>Psilocibinske gljive: ~6,9 g</li><li>Digitalne vage: 2 kom (0,01–100 g; 0,001–20 g) s tragovima biljne materije</li><li>Automatska puška M70 AB, 5 spremnika, ~309 kom streljiva 7,62 mm, bajunet, pribor za čišćenje</li></ul></details><p>Osumnjičenik u obrani navodi da marihuanu konzumira rekreativno (ne bavi se prodajom), a pušku je zadržao iz znatiželje nakon što je prijatelj preminuo. Na kraju se odriče prava na pregled/reprodukciju snimke i čitanje zapisnika; ispitivanje završava u 15:12.</p>"
            }
        },
        {
            "start_date": {
                "year": 2025,
                "month": 6,
                "day": 11
            },
            "text": {
                "headline": "Izvješće o izvršenim intervencijama.",
                "text": "E-spis sadrži i „Izvješće o izvršenim intervencijama” od 11.06.2025., što zatvara inicijalni policijski ciklus nakon pretrage."
            },
            "group": "Pp Prz-74",
            "unique_id": "ev-20250611-1"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 6,
                "day": 30
            },
            "text": {
                "headline": "Podnošenje kaznene prijave DORH-u Osijek (K-51/2025)",
                "text": "<p>PU Osječko-baranjska, Služba organiziranog kriminaliteta, Odjel kriminaliteta droga podnosi kaznenu prijavu protiv Andrije Glavaša temeljem čl. 207. st. 4. ZKP-a (broj: 511-07-11-K-51/2025).</p><p>Prijava obuhvaća sumnju na: (1) <strong>Neovlaštenu proizvodnju i promet drogama</strong> iz čl. 190. st. 2. KZ-a; (2) <strong>Nedozvoljeno posjedovanje, izradu i nabavljanje oružja i eksplozivnih tvari</strong> iz čl. 331. st. 1. i st. 3. KZ-a. Ujedno se traži nalog za daktiloskopsko i biološko vještačenje, toksikološko vještačenje te nalog za pretragu privremeno oduzetog mobilnog uređaja.</p><details><summary>Pravne reference</summary><ul><li>ZKP — čl. 207. st. 4.</li><li>KZ — čl. 190. st. 2.; čl. 331. st. 1. i 3.</li><li>Naredba suda: Pp Prz-74/2025-2</li><li>Upućivanje na vještačenje: Centar „Ivan Vučetić“, Zagreb</li></ul></details>"
            },
            "group": "KP-DO-731",
            "display_date": "30.06.2025.",
            "autolink": true,
            "unique_id": "podnosenje-kaznene-prijave-dorh-osijek-2025-06-30"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 7,
                "day": 3
            },
            "text": {
                "headline": "Zaprimanje kaznene prijave u DORH-u Osijek",
                "text": "<p>Na naslovnici dokumenta evidentiran je pečat o zaprimanju s datumom 03.07.2025.</p><details><summary>Pravne reference</summary><ul><li>Predmet: broj 511-07-11-K-51/2025</li></ul></details>"
            },
            "group": "KP-DO-731",
            "display_date": "03.07.2025.",
            "autolink": true,
            "unique_id": "zaprimanje-kaznene-prijave-dorh-osijek-2025-07-03"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 7,
                "day": 10
            },
            "text": {
                "headline": "Arhiviranje Pp Prz spisa.",
                "text": "Spis Pp Prz-74/2025 arhiviran je 10.07.2025., što je kasnije postalo temelj za tvoj zahtjev za uvid po PZ 150 i ZKP 184/5."
            },
            "group": "Pp Prz-74",
            "unique_id": "ev-20250710-1"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 7,
                "day": 14
            },
            "text": {
                "headline": "Zahtjev poslan preporučenom poštom (RG686837381HR)",
                "text": "<p>Istovjetan zahtjev za pristup osobnim podacima poslan je preporučenom poštom s povratnicom (broj pošiljke RG686837381HR) na adresu: HP – Hrvatska pošta d.d., Jurišićeva 13, 10000 Zagreb. Pošiljka je sadržavala popratni dopis, ispunjeni obrazac te presliku stare osobne iskaznice.</p><details><summary>Pravne reference</summary><ul><li>Čl. 15. GDPR – pravo na pristup</li><li>Čl. 12. ZP‑GDPR (NN 42/18)</li></ul></details>"
            },
            "group": "Slavonska 8",
            "display_date": "14. srpnja 2025."
        },
        {
            "start_date": {
                "year": 2025,
                "month": 7,
                "day": 18,
                "hour": 12,
                "minute": 52
            },
            "text": {
                "headline": "E‑mail zahtjev za pristup osobnim podacima upućen HP‑u",
                "text": "<p>Podnesen e‑mail zahtjev Službeniku za zaštitu podataka HP‑a za dostavu popisa svih pošiljaka adresiranih na: ANDRIJA GLAVAŠ, SLAVONSKA ULICA 8, 31000 OSIJEK, za razdoblje od 1.12.2024. do dana zaprimanja zahtjeva.</p><ul><li>Traženi podaci: datum/vrijeme zaprimanja, broj/barkod, pošiljatelj, vrsta usluge, status uručenja, identifikator/potpis primatelja, napomene kurira.</li><li>Razlog: sumnja na zlouporabu osobnih podataka i adrese (mogući elementi kaznenog djela).</li><li>Način dostave: e‑poštom (PDF/CSV) i po potrebi preporučeno.</li><li>Dostavljeni dokazi identiteta: preslika važeće i stare osobne iskaznice; fotografija računa za preporučenu pošiljku i povratnice.</li></ul><details><summary>Pravne reference</summary><ul><li>Čl. 15. GDPR</li><li>Čl. 12. ZP‑GDPR (NN 42/18)</li><li>Čl. 146. st. 1. KZ – neovlaštena uporaba osobnih podataka (kontekst)</li><li>Čl. 12. st. 3. GDPR – rok 30 dana</li></ul></details>"
            },
            "group": "Slavonska 8",
            "display_date": "18. srpnja 2025. u 12:52"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 8,
                "day": 6,
                "hour": 9,
                "minute": 51
            },
            "text": {
                "headline": "HP produljuje rok za odgovor (čl. 12. st. 3. GDPR)",
                "text": "<p>HP – Hrvatska pošta d.d., kao voditelj obrade, obavještava Andriju Glavaša da će rok za dostavu odgovora biti produljen zbog objektivnih razloga, kako bi se osigurale potpune i točne informacije te izbjegla nepotpuna dostava.</p><details><summary>Pravne reference</summary><ul><li>Čl. 12. st. 3. GDPR – mogućnost produljenja roka do dodatna dva mjeseca</li></ul></details>"
            },
            "group": "Slavonska 8",
            "display_date": "6. kolovoza 2025. u 09:51"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 8,
                "day": 11
            },
            "text": {
                "headline": "Pouka o pravima osumnjičeniku (ODO Osijek)",
                "text": "<p>Općinsko državno odvjetništvo u Osijeku izdalo je osumnjičeniku pouku o pravima na temelju čl. 239 st. 2 t. 2 ZKP/08.</p><p>Sažetak pouke:</p><ul><li>Pravo na šutnju i neodgovaranje na pitanja; ne inkriminirati se.</li><li>Pravo uvida u spis nakon ispitivanja ili dostave rješenja/obavijesti prema čl. 184 st. 4 i 5 te čl. 213 st. 2 ZKP/08.</li><li>Pravo služiti se svojim jezikom i pravo na besplatno prevođenje (čl. 8 ZKP/08).</li><li>Pravo na branitelja po izboru; mogućnost branitelja po službenoj dužnosti ili na teret proračuna ovisno o imovinskom stanju.</li><li>Ako je uhićen: pravo na privremenu pravnu pomoć na teret proračuna; mogućnost naknadnog obvezivanja na snošenje troškova prema čl. 72.a ZKP/08.</li></ul><details><summary>Pravne reference</summary><ul><li>ZKP/08: čl. 239 st. 2 t. 2; čl. 184 st. 4 i 5; čl. 213 st. 2; čl. 8; čl. 72.a (NN 152/08, 76/09, 80/11, 91/12 – odluka Ustavnog suda, 143/12, 56/13, 145/13, 152/14, 70/17, 126/19, 80/22, 36/24, 72/25)</li><li>Kazneni zakon: čl. 190 st. 2; čl. 331 st. 1</li></ul></details>"
            },
            "group": "KP-DO-731",
            "display_date": "11. kolovoza 2025.",
            "unique_id": "kp-do-731-2025-2025-08-11-pouka-o-pravima"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 8,
                "day": 11,
                "display_date": "11. kolovoza 2025."
            },
            "text": {
                "headline": "Izdavanje poziva okrivljeniku",
                "text": "<p>Općinsko državno odvjetništvo u Osijeku izdalo je poziv okrivljeniku Andriji Glavašu (kazneni predmet: KP-DO-731/2025; broj spisa: KP-DO-731/2025-6) na prvo ispitivanje.</p><details><summary>Pravne reference</summary><ul><li>Članak 190. stavak 2. Kaznenog zakona/11 i dr.</li><li>Članak 175. stavak 1. Zakona o kaznenom postupku (dovedbeni nalog)</li><li>Članak 5. stavak 1. Zakona o kaznenom postupku (pravo na obranu)</li><li>Članak 96. u vezi članka 175. stavak 5. Zakona o kaznenom postupku (dostava)</li><li>Članak 147. stavak 1. Zakona o kaznenom postupku (troškovi)</li></ul></details>"
            },
            "group": "KP-DO-731",
            "display_date": "11. kolovoza 2025.",
            "autolink": true,
            "unique_id": "poziv-na-ispitivanje-izdan-2025-08-11"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 8,
                "day": 20,
                "hour": 10,
                "minute": 0,
                "display_date": "20. kolovoza 2025., 10:00"
            },
            "text": {
                "headline": "Prvo ispitivanje zakazano",
                "text": "<p>Prvo ispitivanje zakazano za 20. kolovoza 2025. nije održano jer je poziv uručen 21. kolovoza 2025.; odvjetnik je po primitku zatražio novi termin.</p>"
            },
            "group": "KP-DO-731",
            "display_date": "20. kolovoza 2025.",
            "autolink": true,
            "unique_id": "prvo-ispitivanje-zakazano-2025-08-20"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 8,
                "day": 21
            },
            "text": {
                "headline": "Poziv uručen",
                "text": "<p>Prema povratnici, poziv je uručen 21. kolovoza 2025., dan nakon zakazanog termina.</p>"
            },
            "group": "KP-DO-731",
            "display_date": "21. kolovoza 2025.",
            "autolink": true,
            "unique_id": "poziv-urucen-2025-08-21"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 8,
                "day": 25,
                "hour": 0,
                "minute": 0,
                "display_date": "25.08.2025"
            },
            "end_date": {
                "year": 2025,
                "month": 8,
                "day": 25,
                "hour": 0,
                "minute": 0,
                "display_date": "25.08.2025"
            },
            "text": {
                "headline": "KP-DO-731 — Zahtjev za ograničeni uvid i preslike (čl. 184.a ZKP)",
                "text": "<p>Podnesak prema ODO Osijek za ograničeni uvid i izdavanje preslika (naredba za pretragu, zapisnik, potvrde o oduzimanju, lanac čuvanja, kaznena prijava). Pravna osnova: ZKP čl. 184.a. Referenca na poziv KP-DO-731/2025-6 (11.08.2025) i KLASA: NK-214-05/25-01/1155; URBROJ: 511-07-11-25-2.   </p>"
            },
            "media": {
                "url": "",
                "caption": "",
                "credit": "",
                "thumbnail": "",
                "alt": "",
                "title": "",
                "link": "",
                "link_target": ""
            },
            "group": "KP-DO-731",
            "display_date": "25.08.2025",
            "background": {
                "url": "",
                "alt": "",
                "color": ""
            },
            "autolink": true,
            "unique_id": "kp-do-731-2025-zahtjev-za-uvid-2025-08-25"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 8,
                "day": 25,
                "hour": 0,
                "minute": 0,
                "display_date": "25.08.2025"
            },
            "end_date": {
                "year": 2025,
                "month": 8,
                "day": 25,
                "hour": 0,
                "minute": 0,
                "display_date": "25.08.2025"
            },
            "text": {
                "headline": "Pp Prz-74 — Zahtjev za uvid u spis i preslike",
                "text": "<p>Zahtjev Općinskom sudu u Osijeku za uvid u Pp Prz-74/2025 i izdavanje preslika (naredba za pretragu, zapisnik, oduzimanje, povratnice, kazalo spisa, e-Spis). Referenca: KLASA: NK-214-05/25-01/1155; URBROJ: 511-07-11-25-2.  </p>"
            },
            "media": {
                "url": "",
                "caption": "",
                "credit": "",
                "thumbnail": "",
                "alt": "",
                "title": "",
                "link": "",
                "link_target": ""
            },
            "group": "Pp Prz-74",
            "display_date": "25.08.2025",
            "background": {
                "url": "",
                "alt": "",
                "color": ""
            },
            "autolink": true,
            "unique_id": "pp-prz-74-2025-zahtjev-za-uvid-2025-08-25"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 8,
                "day": 25
            },
            "group": "Pp Prz-74",
            "text": {
                "headline": "Zahtjev za uvid i preslike Pp Prz-74/2025 (OS Osijek).",
                "text": "Podnio si formalni zahtjev za uvid/preslike („jezgre” spisa: policijski zahtjev, naredba, zapisnik, potvrde, kazalo, povratnice)."
            },
            "unique_id": "ev-20250825-1"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 8,
                "day": 27,
                "hour": 8,
                "minute": 17
            },
            "text": {
                "headline": "Obavijest o terminu uvida u spis",
                "text": "<p>Pošiljatelj: Denis Rajtek (Zamjenik općinskog državnog odvjetnika). Predmet: \"Uvid u spis\". U e-mailu je naveden termin i mjesto uvida.</p>"
            },
            "group": "KP-DO-731",
            "autolink": true,
            "unique_id": "obavijest-o-terminu-uvida-u-spis-2025-08-27-0817"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 8,
                "day": 27,
                "display_date": "27. kolovoza 2025."
            },
            "text": {
                "headline": "Pouka o pravima i rješenje o provođenju istrage (Kis-DO-122/2025)",
                "text": "<p>Općinsko državno odvjetništvo u Osijeku donijelo je rješenje o provođenju istrage te okrivljeniku dostavilo pouku o pravima.</p><p>Mjesto: Osijek.</p><ul><li>Kao okrivljenik niste dužni iznijeti svoju obranu niti odgovarati na pitanja.</li><li>Imate pravo uvida u spis (čl. 184. st. 4 i 5. ZKP).</li><li>Imate pravo služiti se svojim jezikom ili jezikom koji razumijete te pravo na besplatno usmeno i pisano prevođenje.</li></ul><details><summary>Pravne reference</summary><ul><li>Zakon o kaznenom postupku (ZKP), čl. 239 st. 2 t. 3</li><li>ZKP, čl. 184 st. 4 i 5</li><li>ZKP, čl. 8</li></ul></details>"
            },
            "group": "KP-DO-731",
            "display_date": "27. kolovoza 2025.",
            "autolink": true,
            "unique_id": "pouka-o-pravima-i-rjesenje-o-provodjenju-istrage-kis-do-122-2025-2025-08-27"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 8,
                "day": 28,
                "hour": 15,
                "minute": 36
            },
            "text": {
                "headline": "Žurnost, parcijalna dostava i ograničenje obrade – dopis Andrije Glavaša",
                "text": "<p>Upućen dopis radi žurnosti, parcijalne (rolling) dostave i ograničenja obrade (litigation hold). Zatražene su dvije faze dostave i konkretizacija razloga produljenja, uz potvrdu datuma zaprimanja prethodnih zahtjeva.</p><ul><li>Faza 1 (do 4.9.2025.): tablični popis pošiljaka (broj/barkod, datum/vrijeme, vrsta, status).</li><li>Faza 2 (do 11.9.2025.): dokazi o uručenju (identifikator/ime, potpis/digitalni ID, napomene, skenovi AR/povratnica).</li><li>Ograničenje obrade i očuvanje svih relevantnih zapisa sukladno čl. 18. st. 1. toč. (c) GDPR.</li><li>Prihvaćanje redakcije osobnih podataka trećih zbog poštanske tajne, uz pravo na potpune vlastite podatke i metapodatke.</li></ul><details><summary>Pravne reference</summary><ul><li>Čl. 12. st. 1.–3. GDPR – transparentnost, olakšavanje ostvarivanja prava, rokovi</li><li>Čl. 15. GDPR – pravo na pristup</li><li>Smjernice EDPB 01/2022 – pravo na pristup</li><li>Čl. 18. st. 1. toč. (c) GDPR – ograničenje obrade radi pravnih zahtjeva</li><li>Zakon o poštanskim uslugama – poštanska tajna</li></ul></details>"
            },
            "group": "Slavonska 8",
            "display_date": "28. kolovoza 2025. u 15:36"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 8,
                "day": 29,
                "hour": 9,
                "minute": 30
            },
            "text": {
                "headline": "Uvid u spis – termin",
                "text": "<p>Uvid u spis moguć je 29. kolovoza 2025. u 9:30 u prostorijama Općinskog državnog odvjetništva u Osijeku (Hrvatske Republike 43/III).</p>"
            },
            "autolink": true,
            "group": "KP-DO-731",
            "unique_id": "uvid-u-spis-termin-2025-08-29-0930"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 8,
                "day": 29
            },
            "text": {
                "headline": "Podnošenje zahtjeva za uvid i preslike",
                "text": "<p>Podnesen je zahtjev Policijskoj upravi za uvid, dostavu preslika i očuvanje podataka u vezi s pretragom doma od 9. lipnja 2025.</p><ol><li>Zapisnik o pretrazi doma (početak/završetak, popis svih prisutnih i njihove uloge).</li><li>Službena zabilješka/izvješće o angažiranju K‑9 (vodič, oznaka jedinice/psa, dolazak/odlazak, prostorije, opis detekcije).</li><li>Operativne evidencije (OKC/operativno dežurstvo) vezane uz upućivanje K‑9 tima i dolazak specijalista za oružje; vrijeme i sastav ekipe.</li><li>Popis svih policijskih službenika koji su bili na adresi (identitet/oznaka, ustrojstvena jedinica, uloga; dolazak/odlazak).</li><li>Potvrda/zapisnik o privremenom oduzimanju puške (opis, serijski broj/kalibar, vrijeme, tko je preuzeo, mjesto pohrane) i zabilješka specijalista za oružje.</li><li>Foto i/ili audio‑video dokumentacija pretrage (ako postoji).</li><li>Plan/radni nalog pregleda prostora ili zapovijed o angažiranju K‑9 i specijalista za oružje.</li><li>Svi obrađeni osobni podaci (zabilješke, izvješća, OKC evidencije, popisi prisutnih i dr.) te obrazloženje eventualnih ograničenja pristupa.</li><li>Hitno očuvanje svih povezanih zapisa (radijske/telefonske komunikacije OKC‑a, zaduženja i kretanja ekipa, interni dnevnici) do izvršenog uvida.</li></ol><details><summary>Pravne reference</summary><ul><li>Zakon o kaznenom postupku (ZKP) — zapisnik o pretrazi, nazočnost svjedoka, potvrda o oduzetom.</li><li>Pravilnik o načinu postupanja policijskih službenika — pisano izvješće o obavljenom poslu/primijenjenoj ovlasti i plan pregleda objekata/prostora.</li><li>Zakon o policijskim poslovima i ovlastima (ZOP/O) — sredstva prisile (uklj. službeni pas) i obveze izvješćivanja.</li><li>Zakon NN 68/2018 (Direktiva 2016/680) — pravo na pristup osobnim podacima u roku od 30 dana.</li></ul></details><p>Mjesto i datum: Osijek, 29. kolovoza 2025.</p>"
            },
            "group": "KP-DO-731",
            "display_date": "29. kolovoza 2025.",
            "autolink": true,
            "unique_id": "zahtjev-za-uvid-i-preslike-2025-08-29"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 8,
                "day": 29,
                "hour": 14,
                "minute": 15,
                "display_date": "29. 8. 2025, 14:15"
            },
            "text": {
                "headline": "Požurnica: uvid u spis Pp Prz-74/2025 (podnesak od 26.08.2025.)",
                "text": "<p>Požurnica upućena e‑poštom radi ubrzanja postupanja po zahtjevu za uvid u spis Pp Prz-74/2025. Predloženi termini za uvid:</p><ul><li>četvrtak 5.9.2025. (09:00–14:00)</li><li>ponedjeljak 9.9.2025. (11:00–16:00)</li></ul><p>U prilogu su dostavljene preslike osobne iskaznice (prednja i stražnja) te podnesak „ZahtjevZaUvidUSpis.pdf”.</p><details><summary>Pravne reference</summary><ul><li>Sudski poslovnik, čl. 44 st. 2</li></ul></details>"
            },
            "group": "Su‑1717",
            "autolink": true,
            "unique_id": "pozurnica-uvid-u-spis-pp-prz-74-2025-2025-08-29-1415"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 9,
                "day": 1,
                "display_date": "1. rujna 2025."
            },
            "text": {
                "headline": "Žurni predstavak i zahtjev za uvid/preslike",
                "text": "<p>Podnesen je žurni predstavak i zahtjev Predsjedniku Općinskog suda u Osijeku radi uvida u spis Pp Prz-74/2025 i izdavanja preslika.</p><ul><li>Traži se da sud u roku od 3 radna dana odredi termin uvida.</li><li>Traži se odobrenje uvida i izdavanje preslika ili, ako postoji ograničenje, formalno rješenje s razlozima i pravnim lijekom.</li></ul><p>Naglašeno je da se radi o hitnoj dokaznoj radnji te pravo na uvid u zapisnik o pretrazi u roku od 30 dana.</p><details><summary>Pravne reference</summary><ul><li>Zakon o kaznenom postupku (ZKP) — pravo na uvid u zapisnik o pretrazi u roku od 30 dana.</li></ul></details><details><summary>Prilozi</summary><ul><li>Zahtjev za uvid/preslike od 26.08.2025.</li><li>Požurnica od 29.08.2025.</li><li>Preslika osobne iskaznice.</li><li>Ispis e-Predmeta po Pp Prz-74/2025.</li></ul></details>"
            },
            "group": "Su‑1717",
            "autolink": true,
            "unique_id": "pp-prz-74-2025-zurni-predstavak-i-zahtjev-2025-09-01"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 9,
                "day": 2,
                "display_date": "2. rujna 2025."
            },
            "text": {
                "headline": "Sud zaprimio podnesak",
                "text": "<p>Općinski sud u Osijeku zaprimio je podnesak (urudžbeni pečat: „Zaprimljeno”).</p>"
            },
            "group": "Su‑1717",
            "autolink": true,
            "unique_id": "pp-prz-74-2025-zaprimanje-podneska-2025-09-02"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 9,
                "day": 2
            },
            "text": {
                "headline": "MUP obavijest: podnesena kaznena prijava",
                "text": "<p>PU Osječko-baranjska obavještava da je protiv Andrije Glavaša podnesena kaznena prijava pod brojem <strong>511-07-11-K-51/2025</strong> Općinskom državnom odvjetništvu u Osijeku.</p><details><summary>Pravne reference</summary><ul><li>Kazneni zakon (KZ): čl. 190 st. 2 — Neovlaštena proizvodnja i promet drogama</li><li>Kazneni zakon (KZ): čl. 331 st. 1 — Nedozvoljeno posjedovanje, izrada i nabavljanje oružja i eksplozivnih tvari</li></ul></details><p>Za daljnje obavijesti i pismena potrebno se obratiti Općinskom državnom odvjetništvu u Osijeku, s pozivom na navedeni broj predmeta.</p>"
            },
            "group": "KP-DO-731",
            "display_date": "2. 9. 2025.",
            "autolink": true,
            "unique_id": "mup-obavijest-podnesena-kaznena-prijava-2025-09-02"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 9,
                "day": 3,
                "hour": 0,
                "minute": 0,
                "display_date": "03.09.2025"
            },
            "end_date": {
                "year": 2025,
                "month": 9,
                "day": 3,
                "hour": 0,
                "minute": 0,
                "display_date": "03.09.2025"
            },
            "text": {
                "headline": "Pp Prz-74/2025-7 — Odbijenica za uvid",
                "text": "<p>Općinski sud u Osijeku obavještava: uvid i preslike se uskraćuju jer podnositelj „nije stranka” (PZ 108); pozivanje na tajnost izvida (ZKP 206.f) i osnovanu sumnju iz čl. 54 st. 3 ZSZD. </p>"
            },
            "media": {
                "url": "",
                "caption": "",
                "credit": "",
                "thumbnail": "",
                "alt": "",
                "title": "",
                "link": "",
                "link_target": ""
            },
            "group": "Su‑1717",
            "display_date": "03.09.2025",
            "background": {
                "url": "",
                "alt": "",
                "color": ""
            },
            "autolink": true,
            "unique_id": "pp-prz-74-2025-odbijenica-2025-09-03"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 9,
                "day": 4,
                "hour": 0,
                "minute": 0,
                "display_date": "04.09.2025"
            },
            "end_date": {
                "year": 2025,
                "month": 9,
                "day": 4,
                "hour": 0,
                "minute": 0,
                "display_date": "04.09.2025"
            },
            "text": {
                "headline": "Pp Prz-74 — Ponovljeni zahtjev predsjednici suda",
                "text": "<p>Ponovljeni zahtjev za PUNI ili djelomični uvid (foto-elaborat u izvornom formatu, potvrde o oduzimanju, popis prisutnih), uz traženje formalnog rješenja s obrazloženjem. </p>"
            },
            "media": {
                "url": "",
                "caption": "",
                "credit": "",
                "thumbnail": "",
                "alt": "",
                "title": "",
                "link": "",
                "link_target": ""
            },
            "group": "Su‑1717",
            "display_date": "04.09.2025",
            "background": {
                "url": "",
                "alt": "",
                "color": ""
            },
            "autolink": true,
            "unique_id": "pp-prz-74-2025-ponovljeni-zahtjev-predsjednici-2025-09-04"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 9,
                "day": 5,
                "hour": 12,
                "minute": 49,
                "display_date": "5. rujna 2025. u 12:49"
            },
            "text": {
                "headline": "Za predsjednicu suda - Ponovljeni zahtjev za uvid i preslikavanje spisa Pp Prz-74/2025",
                "text": "<p>E-mail od Slavice Dorušak (Ured predsjednika suda) upućen Andriji Glavašu, s kopijom Dunji Bertok i Mateji Mihačić, kojim se potvrđuje da je predsjednica Suda upoznata sa sadržajem dopisa dostavljenog od sutkinje Dunje Bertok te da je navedeno dostavljeno uz suglasnost predsjednice Suda.</p><p><em>Općinski sud u Osijeku – Ured predsjednika suda</em></p>"
            },
            "media": {
                "url": "https://mail.google.com/mail/u/0/?ik=922182dfbf&view=pt&search=all&permmsgid=msg-f:1842420784550137235&simpl=msg-f:1842420784550137235",
                "caption": "Snimka zaslona poruke e-pošte (Gmail)",
                "title": "Gmail – Za predsjednicu suda: Ponovljeni zahtjev"
            },
            "group": "Su‑1717",
            "autolink": true,
            "unique_id": "za-predsjednicu-suda-ponovljeni-zahtjev-za-uvid-i-preslikavanje-spisa-pp-prz-74-2025-2025-09-05-1249"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 9,
                "day": 9,
                "hour": 0,
                "minute": 0,
                "display_date": "09.09.2025"
            },
            "end_date": {
                "year": 2025,
                "month": 9,
                "day": 9,
                "hour": 0,
                "minute": 0,
                "display_date": "09.09.2025"
            },
            "text": {
                "headline": "Su-1717 — Zahtjev za donošenje rješenja (upravni nadzor)",
                "text": "<p>Traži se formalno rješenje predsjednice suda o uvidu u arhivirani Pp Prz-74/2025, uz pozivanje na PZ 150/1‑4, PZ 159/5 te ZKP 183, 184/5 i 206.f; Sudski poslovnik 44/2. </p>"
            },
            "media": {
                "url": "",
                "caption": "",
                "credit": "",
                "thumbnail": "",
                "alt": "",
                "title": "",
                "link": "",
                "link_target": ""
            },
            "group": "Su‑1717",
            "display_date": "09.09.2025",
            "background": {
                "url": "",
                "alt": "",
                "color": ""
            },
            "autolink": true,
            "unique_id": "su-1717-2025-zahtjev-za-formalnim-rjesenjem-2025-09-09"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 9,
                "day": 12
            },
            "text": {
                "headline": "Zahtjev za upravni nadzor Županijskom sudu",
                "text": "<p>Podnošenje zahtjeva za upravni nadzor Županijskom sudu u Osijeku (Predsjedniku suda, nadzor sudske uprave) radi nadzora u predmetu Su‑1717 i mjera prema Općinskom sudu u Osijeku.</p><details><summary>Tražene mjere (sažetak)</summary><ol><li>Provesti izvanredni nadzor nad postupanjem OS u predmetu Su‑1717.</li><li>Naložiti donošenje formalnog rješenja u roku 3 radna dana, uz in camera pregled i (ako treba) djelomični uvid s mjerama zaštite; taksativno navesti privremeno uskraćene dijelove, pravnu osnovu i rok uskrate; odrediti termin uvida/dearhiviranja.</li><li>Evidentirati sva pismena u e‑Predmet (osobito e‑mail od 5.9.2025.) uz službenu bilješku.</li><li>Zatražiti pisano očitovanje predsjednice suda o razlozima nedonošenja rješenja i mjerama prevencije.</li><li>Definirati „jezgru“: policijski Zahtjev NK‑214‑05/25‑01/1155, Naredba Pp Prz‑74/2025‑2, zapisnik o pretrazi s potvrdama, foto‑elaborat (izvorni JPEG/RAW), te logovi (bodycam, CAD/radio‑log, K‑9/stručnjaci) ako postoje.</li><li>Naložiti dostavu preslika u elektronički ovjerenom PDF‑u (kvalificirani e‑potpis/pečat) ili ovjerenih papirnatih preslika; hitna potvrda zaprimanja s urudžbenim brojem.</li><li>Subsidiarno: in camera pregled i djelomični uvid; ili dostava redigirane „jezgre“ DORH‑u/kaznenom sudu radi sudske kontrole, uz omogućavanje uvida kroz kazneni spis.</li></ol></details><details><summary>Pravne reference</summary><ul><li>PZ čl. 150 st. 1–5; PZ čl. 150 st. 2–3; PZ čl. 159 st. 5; PZ čl. 108</li><li>ZKP čl. 183; čl. 184 st. 5; čl. 206.f</li><li>Sudski poslovnik čl. 44 st. 2</li><li>Zakon o sudovima čl. 31</li></ul></details>"
            },
            "display_date": "12.09.2025",
            "group": "Su‑1717",
            "unique_id": "2025-09-12-zahtjev-za-upravni-nadzor"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 9,
                "day": 17,
                "hour": 9,
                "minute": 12
            },
            "text": {
                "headline": "Županijski sud u Osijeku potvrđuje pravilnost postupanja Općinskog suda",
                "text": "<p>Županijski sud u Osijeku obavještava podnositelja da je pregledom dokumentacije utvrđeno kako je postupanje Općinskog suda u Osijeku pravilno i zakonito. Budući da prekršajni postupak još nije pokrenut i da su u tijeku izvidi, pravo na razgledavanje, prepisivanje i preslikavanje spisa nastupa tek nakon pokretanja postupka; stoga „formalno“ rješenje o zahtjevu nije potrebno.</p><details><summary>Pravne reference</summary><ul><li>Prekršajni zakon, čl. 150 st. 1 (Narodne novine 107/07, 39/13, 157/13, 110/15, 70/17, 118/18, 114/22)</li></ul></details>"
            },
            "group": "Su‑1717",
            "display_date": "17. rujna 2025. u 09:12",
            "autolink": true
        },
        {
            "start_date": {
                "year": 2025,
                "month": 9,
                "day": 22
            },
            "text": {
                "headline": "Nalog za kombinirano daktiloskopsko, DNA i toksikološko vještačenje",
                "text": "<p>Općinsko državno odvjetništvo u Osijeku (Kis-DO-122/2025-7) izdaje nalog za kombinirano daktiloskopsko, biološko-molekularno (DNA) i toksikološko vještačenje predmeta oduzetih od okr. Andrije Glavaša. Vještačenje se povjerava Centru za forenzička ispitivanja, istraživanja i vještačenja „Ivan Vučetić“ u Zagrebu.</p><p>Stručnjaku Centra nalaže se utvrditi postojanje otisaka prstiju pogodnih za vještačenje na ambalaži/predmetima, utvrditi prisutnost bioloških tragova (DNA) te, ako su nađeni, usporediti ih s tragovima pohranjenim u bazi podataka Centra. Vještačenje treba započeti odmah po preuzimanju tvari, a pisani nalaz i mišljenje dostaviti u roku od 30 dana od dana preuzimanja.</p><details><summary>Pravne reference</summary><ul><li>Zakon o kaznenom postupku: čl. 309 st. 1 i 2 u svezi čl. 308</li><li>Kazneni zakon: čl. 190 st. 2 i dr.</li></ul></details>"
            },
            "group": "KP-DO-731",
            "display_date": "22. rujna 2025.",
            "autolink": true,
            "unique_id": "evt-nalog-za-vjestacenje-2025-09-22"
        },
        //kuriri
        {
            "start_date": {
                "year": 2025,
                "month": 7,
                "day": 14,
                "hour": 18,
                "minute": 37,
                "display_date": "14. srpnja 2025., 18:37"
            },
            "group": "Slavonska 8",
            "text": {
                "headline": "Zahtjev podnesen e-poštom voditelju obrade",
                "text": "<p>Andrija Glavaš podnosi <strong>Zahtjev za pristup osobnim podacima</strong>.</p><p>Podaci podnositelja: Primorska ulica 5, 31000 Osijek; OIB: 25041200286; e-mail: aglavas11@gmail.com; tel.: 098/969-5609.</p><p>Primatelj: Overseas Trade Co LTD d.o.o., Zastavnice 38a, 10251 Hrvatski Leskovac, Hrvatska.</p><p>Predmet: Zahtjev za pristup osobnim podacima – čl. 15. GDPR / čl. 12. ZP-GDPR.</p><p>Poslano na: privacy@overseas.hr; u kopiji: V. Cvitković (V.Cvitkovic@overseas.hr), R. Pejanović (renata.pejanovic@gmail.com).</p><p>Način dostave odgovora zatražen: e-poštom (PDF) i, po potrebi, preporučenom poštom.</p><p>Prilozi: preslika osobne iskaznice (obostrano) — datoteke: SBH284e FO25071411220_0001.jpg (48 KB), SBH284e FO25071411220_0002.jpg (55 KB).</p><details><summary>Pravne reference</summary><ul><li>Čl. 15. GDPR (EU 2016/679)</li><li>Čl. 12. ZP-GDPR (NN 42/18)</li><li>Čl. 12 st. 3. GDPR – rok za odgovor</li><li>KZ RH: čl. 146 st. 1 (navedeno u obrazloženju zahtjeva)</li></ul></details>"
            }
        },
        {
            "start_date": {
                "year": 2025,
                "month": 7,
                "day": 14,
                "hour": 20,
                "minute": 57,
                "display_date": "14. srpnja 2025., 20:57"
            },
            "group": "Slavonska 8",
            "text": {
                "headline": "Zahtjev proslijeđen službeniku za zaštitu podataka",
                "text": "<p>Valentina Cvitković prosljeđuje zahtjev na adresu službenika za zaštitu podataka (povjerenik@overseas.hr).</p><p>Napomena o sigurnosti e-pošte i pravni disclaimer uključeni u poruci.</p>"
            }
        },
        {
            "start_date": {
                "year": 2025,
                "month": 7,
                "day": 18,
                "hour": 18,
                "minute": 2,
                "display_date": "18. srpnja 2025., 18:02"
            },
            "group": "Slavonska 8",
            "text": {
                "headline": "Potvrda zaprimanja i rok za odgovor (30 dana)",
                "text": "<p>Službenik za zaštitu osobnih podataka (Renata Pejanović) potvrđuje evidentiranje zahtjeva i najavljuje odgovor u roku od 30 dana.</p><p>Kontakt: Marka Stančića 13, 10000 Zagreb; Tel: 7898-936; Mob: 098-714-699; e-mail: renata.pejanovic@gmail.com; povjerenik@overseas.hr.</p><details><summary>Pravne reference</summary><ul><li>Čl. 12 st. 3. GDPR – rok za informiranje/odgovor</li></ul></details>"
            }
        },
        {
            "start_date": {
                "year": 2025,
                "month": 8,
                "day": 2,
                "hour": 17,
                "minute": 30,
                "display_date": "2. kolovoza 2025., 17:30"
            },
            "group": "Slavonska 8",
            "text": {
                "headline": "Najava dostave podataka i upit o slanju lozinke",
                "text": "<p>DPO obavješćuje da će tražene informacije biti dostavljene e-poštom kao zaštićene datoteke te traži potvrdu preferiranog kanala za dostavu lozinke (npr. SMS, druga e-mail adresa).</p>"
            }
        },
        {
            "start_date": {
                "year": 2025,
                "month": 8,
                "day": 2,
                "hour": 17,
                "minute": 36,
                "display_date": "2. kolovoza 2025., 17:36"
            },
            "group": "Slavonska 8",
            "text": {
                "headline": "Suglasnost: lozinka putem SMS-a",
                "text": "<p>Podnositelj potvrđuje da lozinku želi zaprimiti putem SMS poruke na broj +385 98 969 5609.</p>"
            }
        },

        {
            "start_date": {
                "year": 2025,
                "month": 7,
                "day": 14,
                "hour": 13,
                "minute": 8,
                "display_date": "14. srpnja 2025., 13:08"
            },
            "group": "Slavonska 8",
            "text": {
                "headline": "Zahtjev za pristup osobnim podacima upućen GLS Croatia",
                "text": "<p>Andrija Glavaš podnio je zahtjev Službeniku za zaštitu podataka (GLS Croatia d.o.o.) za pristup osobnim podacima koji se na njega odnose, s fokusom na pošiljke upućene na: „Andrija Glavaš, Slavonska ulica 8, 31000 Osijek”. Tražene su informacije za razdoblje od 1. prosinca 2024. do dana zaprimanja zahtjeva.</p><ul><li>Datum i vrijeme zaprimanja u sustav</li><li>Broj pošiljke / barkôd</li><li>Naziv ili šifra pošiljatelja</li><li>Vrsta usluge (npr. paket, R‑pismo, pouzeće)</li><li>Način uručenja (dostavljeno, preusmjereno, vraćeno, uništeno)</li><li>Identifikator ili potpis osobe koja je preuzela pošiljku</li><li>Napomene kurira (ako postoje)</li></ul><p>Način dostave odgovora zatražen: e‑poštom (PDF) i, po potrebi, preporučeno poštom na adresu prebivališta.</p><details><summary>Pravne reference</summary><ul><li>Opća uredba o zaštiti podataka (EU) 2016/679 — čl. 15 (pravo ispitanika na pristup)</li><li>Zakon o provedbi Opće uredbe o zaštiti podataka (NN 42/18) — čl. 12</li><li>GDPR — čl. 12 st. 3 (rok za odgovor 30 dana)</li><li>Kazneni zakon RH — čl. 146 st. 1 (povreda tajnosti pisama i drugih pošiljaka)</li></ul></details><p>Razlog: osnovana sumnja na zlouporabu osobnih podataka i adrese od strane nepoznate osobe.</p><p>Privitci: preslika osobne iskaznice (obostrano).</p>"
            },
            "autolink": true,
            "unique_id": "zahtjev-za-pristup-osobnim-podacima-poslan-gls-croatia-2025-07-14-1308"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 7,
                "day": 15,
                "hour": 10,
                "minute": 34,
                "display_date": "15. srpnja 2025., 10:34"
            },
            "group": "Slavonska 8",
            "text": {
                "headline": "Odgovor GLS Croatia: Nema podataka za Slavonsku 8; upit o provjeri Primorske 5",
                "text": "<p>GLS Croatia (Službenik za zaštitu podataka) je odgovorio da u svojoj bazi nije pronašao nikakve podatke za adresu: „Andrija Glavaš, Slavonska ulica 8, 31000 Osijek”. Ujedno su pitali treba li izvršiti provjeru i za adresu prebivališta: „Primorska ulica 5, 31000 Osijek”.</p>"
            },
            "autolink": true,
            "unique_id": "gls-nema-zapisa-za-slavonsku-8-predlozena-provjera-primorske-5-2025-07-15-1034"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 7,
                "day": 15,
                "hour": 11,
                "minute": 7,
                "display_date": "15. srpnja 2025., 11:07"
            },
            "group": "Slavonska 8",
            "text": {
                "headline": "Podnositelj: Nije potrebna provjera Primorske 5; informacija o Slavonskoj 8 korisna",
                "text": "<p>Andrija Glavaš zahvaljuje na brzoj provjeri te potvrđuje da za adresu „Primorska ulica 5” nije potrebna dodatna provjera, jer ondje uredno prima očekivane pošiljke. Informacija da za „Slavonsku ulicu 8” nema podataka korisna je za daljnje postupanje. Napominje da je slične dopise poslao i ostalim kuririma te HP‑u.</p>"
            },
            "autolink": true,
            "unique_id": "podnositelj-nije-potrebna-provjera-primorske-5-informacija-korisna-2025-07-15-1107"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 7,
                "day": 14,
                "hour": 15,
                "minute": 8,
                "display_date": "14. srpnja 2025., 15:08"
            },
            "text": {
                "headline": "Zahtjev za pristup osobnim podacima (čl. 15. GDPR)",
                "text": "<p>Podnositelj: Andrija Glavaš (Primorska ulica 5, 31000 Osijek; OIB: 25041200286). Primatelj: DHL International d.o.o., Utinjska ulica 40, 10000 Zagreb. Predmet: zahtjev za pristup osobnim podacima sukladno GDPR-u i ZP-GDPR.</p><p>Traži se dostava:</p><ul><li>Popisa svih pošiljaka adresiranih na: „Andrija Glavaš, Slavonska ulica 8, 31000 Osijek” za razdoblje 1.12.2024.–14.7.2025.</li><li>Za svaku pošiljku: datum i vrijeme zaprimanja, broj pošiljke/barkôd, pošiljatelj (naziv/šifra), vrsta usluge, način uručenja (dostavljeno/preusmjereno/vraćeno/uništeno), identifikator/potpis primatelja, napomene kurira.</li></ul><p>Razlog: sumnja na zlouporabu osobnih podataka i adrese; podaci su potrebni radi zaštite prava pred policijom, DORH-om i sudom. Traženi odgovor: e-poštom (PDF) i, po potrebi, preporučenom poštom. Prilog: preslika osobne iskaznice (obostrano). Očekivani rok: 30 dana.</p><details><summary>Pravne reference</summary><ul><li>Opća uredba o zaštiti podataka (EU) 2016/679 – čl. 15; čl. 12 st. 3.</li><li>Zakon o provedbi Opće uredbe o zaštiti podataka (NN 42/2018) – čl. 12.</li><li>Kazneni zakon RH – čl. 146 st. 1.</li></ul></details>"
            },
            "group": "Slavonska 8",
            "autolink": true,
            "unique_id": "zahtjev-za-pristup-osobnim-podacima-2025-07-14-1508"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 7,
                "day": 16,
                "hour": 16,
                "minute": 3,
                "display_date": "16. srpnja 2025., 16:03"
            },
            "text": {
                "headline": "Odgovor DHL-a: Nema pronađenih pošiljaka prema kriteriju",
                "text": "<p>DPO DHL Express Hrvatska (Zdravko Petranović) potvrđuje da je izvršena pretraga u globalnom alatu za izvještavanje „C360”, uključujući sve DHL Express produkte i GHOST pošiljke (testne/kreirane a ne poslane tovarne listove). Nije pronađen niti jedan zapis koji odgovara navedenom kriteriju.</p><p>Pružena je poveznica na politiku privatnosti i poziv na dodatna pitanja ili nove informacije koje bi mogle pomoći.</p>"
            },
            "media": {
                "url": "https://mydhl.express.dhl/content/dam/downloads/global/privacy-notice/express_privacy_notice-v1.2c_hr_hr.pdf",
                "caption": "DHL Express – Obavijest o privatnosti (PDF)",
                "credit": "DHL Express",
                "link": "https://www.dhl.hr",
                "link_target": "_blank"
            },
            "group": "Slavonska 8",
            "autolink": true,
            "unique_id": "odgovor-dhl-nema-zapisa-2025-07-16-1603"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 7,
                "day": 14,
                "hour": 15,
                "minute": 3,
                "display_date": "14. srpnja 2025. u 15:03"
            },
            "text": {
                "headline": "Zahtjev za pristup osobnim podacima poslan DPD-u",
                "text": "<p>Ispitanik podnosi zahtjev DPD Croatia d.o.o. (zastitapodataka@dpd.hr) za pristup osobnim podacima.</p><p>Zahtjev obuhvaća:</p><ul><li>Popis svih pošiljaka adresiranih na: „Andrija Glavaš, Slavonska ulica 8, 31000 Osijek” u razdoblju od 1.12.2024. do dana zaprimanja zahtjeva.</li><li>Za svaku pošiljku: datum/vrijeme zaprimanja, broj/barkôd, naziv/šifra pošiljatelja, vrsta usluge, način uručenja, identifikator/potpis primatelja, napomene kurira.</li></ul><p>Navedena je sumnja na zlouporabu osobnih podataka i adrese (potencijalni elementi kaznenog djela); traženo je dostavljanje odgovora elektronički (PDF) i, po potrebi, preporučenom poštom. U privitku su dostavljene 2 slike osobne iskaznice (radi identifikacije).</p><details><summary>Pravne reference</summary><ul><li>GDPR – čl. 15 (EU 2016/679)</li><li>Zakon o provedbi GDPR-a (ZP‑GDPR) – čl. 12 (NN 42/18)</li><li>GDPR – čl. 12 st. 3 (rok odgovora 30 dana)</li><li>Kazneni zakon RH – čl. 146 st. 1</li></ul></details>"
            },
            "group": "Slavonska 8",
            "autolink": true,
            "unique_id": "zahtjev-za-pristup-osobnim-podacima-poslan-dpd-u-2025-07-14-1503"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 8,
                "day": 7,
                "hour": 9,
                "minute": 16,
                "display_date": "7. kolovoza 2025. u 09:16"
            },
            "text": {
                "headline": "DPD dostavlja odgovor na zahtjev (PDF)",
                "text": "<p>DPD (Službenik za zaštitu podataka, Boris Stanković) šalje odgovor na zahtjev na traženu e‑mail adresu u .pdf formatu.</p><ul><li>Prilog: “DPD - odgovor ispitaniku AG 782025.pdf”.</li></ul><p>Odgovor dostavljen unutar roka od 30 dana (GDPR čl. 12 st. 3).</p>"
            },
            "group": "Slavonska 8",
            "autolink": true,
            "unique_id": "dpd-odgovor-na-zahtjev-2025-08-07-0916"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 9,
                "day": 3,
                "hour": 14,
                "minute": 0,
                "display_date": "3. rujna 2025., 14:00"
            },
            "group": "Slavonska 8",
            "text": {
                "headline": "HP: Dostava traženih podataka (RAR) – lozinka naknadno",
                "text": "<p>HP dostavlja tražene podatke u privitku <em>A.G.dostava podataka.rar</em> (50K). Lozinka za pristup najavljena u zasebnoj poruci.</p>"
            },
            "display_date": "3. rujna 2025., 14:00"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 9,
                "day": 3,
                "hour": 14,
                "minute": 15,
                "display_date": "3. rujna 2025., 14:15"
            },
            "group": "Slavonska 8",
            "text": {
                "headline": "HP: Ponovno dostavljeni podaci (RAR) – lozinka u zasebnom mailu",
                "text": "<p>HP ponovno šalje poruku s istim privitkom te potvrđuje da će lozinka biti poslana u drugom mailu. Adresa: Poštanska 9, 10410 Velika Gorica.</p>"
            },
            "display_date": "3. rujna 2025., 14:15"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 5,
                "day": 26
            },
            "text": {
                "headline": "Prebivalište: Primorska 5, Osijek",
                "text": "<p>Prijavljeno prebivalište na adresi: Osijek, Osijek, Primorska 5.</p>"
            },
            "group": "Slavonska 8",
            "display_date": "od 26.05.2025"
        },
        {
            "start_date":{
                "year":2024,
                "month":9,
                "day":29
            },
            "text":{
                "headline":"Predugovor potpisan i isplata kapare",
                "text":"<p>Kupac je isplatio 5.000,00 EUR u gotovini na dan potpisa Predugovora (kapara).</p>"
            },
            "group":"Slavonska 8"
        },
        {
            "start_date":{
                "year":2024,
                "month":10,
                "day":28
            },
            "text":{
                "headline":"Ugovor o kupoprodaji nekretnine potpisan",
                "text":"<p>Prodavatelj LIDIJA GLAVAŠ (OIB 81855360864) i kupac ANDREJ GUNGL (OIB 44682372607) sklopili su Ugovor o kupoprodaji.</p><p>Nekretnina: k.o. 320668 Osijek, zk. uložak 4007, kčbr 8717/4; adresa Slavonska 8, Osijek — ukupno 604 m² (dvorište 417 m², kuća 135 m², pomoćna zgrada 52 m²).</p><ul><li>Kupoprodajna cijena: 115.000,00 EUR (kapara 5.000,00 EUR po Predugovoru).</li><li>Uknjižba prava vlasništva po isplati cjelokupne cijene; prodavatelj izdaje tabularnu izjavu.</li><li>Prodavatelj jamči da nekretnina nije opterećena teretima; eventualne terete snosi i uklanja prodavatelj.</li><li>Suprug prodavateljice Tomislav Glavaš daje suglasnost za prodaju i uknjižbu.</li><li>Porez na promet nekretnina i troškove provedbe snosi kupac.</li><li>Posjed: kupac stupa u posjed po isplati ukupne cijene; prodavatelj podmiruje sve dospjele režije do predaje.</li><li>Energ. certifikat: P_855_2015_10088_SZ1.</li><li>Nadležnost suda: Općinski sud u Osijeku.</li></ul><details><summary>Pravne reference</summary><ul><li>Pravilnik o energetskim pregledima građevina i energetskom certificiranju zgrada</li></ul></details>"
            },
            "group":"Slavonska 8"
        },
        {
            "start_date":{
                "year":2024,
                "month":10,
                "day":28
            },
            "end_date":{
                "year":2024,
                "month":11,
                "day":27
            },
            "text":{
                "headline":"Rok za isplatu ostatka cijene (kredit PBZ)",
                "text":"<p>Ostatak od 110.000,00 EUR isplaćuje se po realizaciji kredita kod Privredne banke Zagreb d.d., u roku 30 dana od sklapanja Ugovora.</p><p>Uplata na račun prodavatelja: IBAN HR2823900013221869305 (Privredna banka Zagreb d.d.). Po odobrenju kredita PBZ isplaćuje izravno na račun prodavatelja.</p>"
            },
            "display_date":"28.10.2024 – 27.11.2024",
            "group":"Slavonska 8"
        },
        {
            "start_date":{
                "year":2024,
                "month":11,
                "day":5
            },
            "text":{
                "headline":"Javnobilježnička ovjera potpisa",
                "text":"<p>Javni bilježnik Vjenceslav Arambašić (Osijek, Kapucinska 17) potvrdio je istinitost potpisa stranaka: LIDIJA GLAVAŠ, TOMISLAV GLAVAŠ (suprug prodavateljice) i ANDREJ GUNGL. Broj: OV-14084/2024.</p><details><summary>Pravne i troškovne reference</summary><ul><li>ZJP — tar. st. 4.: javnobilježnička pristojba 3,99 EUR</li><li>Javnobilježnička nagrada: 17,00 EUR + PDV 2,99 EUR</li></ul></details>"
            },
            "group":"Slavonska 8"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 4,
                "day": 25,
                "display_date": "25. travnja 2025"
            },
            "group": "Slavonska 8",
            "text": {
                "headline": "Pismo kod Gunglovih i promjena osobne",
                "text": "<p>Andrija, danas su mi Gunglovi poslali poruku da tvoja pošta stiže na njihovu adresu - Slavonsku 8. Hitno mjenjaj osobnu!</p>"
            },
            "autolink": true,
            "unique_id": "slavonska-8-posta-osobna"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 4,
                "day": 25
            },
            "group": "Slavonska 8",
            "text": {
                "headline": "Obavijest o poštanskoj pošiljci",
                "text": "<p>Poruka: \"U sandučiću našem je bilo nešto za sina vašeg… poštar ubacio…\"</p>"
            },
            "unique_id": "obavijest-o-postanskoj-posiljci-2025-04-25"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 4,
                "day": 25
            },
            "group": "Slavonska 8",
            "text": {
                "headline": "Najava promjene adrese (osobna ističe u svibnju)",
                "text": "<p>Hvala! U svibnju mu ističe osobna iskaznica pa će promijeniti adresu. Već je to trebao napraviti! Ispričavamo se!</p>"
            },
            "unique_id": "najava-promjene-adrese-osobna-istice-u-svibnju-2025-04-25"
        },
        {
            "start_date": {
                "year": 2025,
                "month": 4,
                "day": 25
            },
            "group": "Slavonska 8",
            "text": {
                "headline": "Potvrda i informacija o poštaru",
                "text": "<p>Ma nema problema… reko idem javit da sam ubacila… jer poštar ne ide popodne 🥱</p>"
            },
            "unique_id": "potvrda-i-informacija-o-postaru-2025-04-25"
        }



    ]
    }));
    console.log(timeline_json);


    const dataTop = {
        title: { text: { headline: "Lažirani timeline" } },
        events: [
            {
                "start_date":{
                    "year":2025,
                    "month":6,
                    "day":9,
                    "hour": 8,
                    "minute":0,
                    "display_date":"09.06.2025 08:00"
                },
                "end_date":{
                    "year":2025,
                    "month":6,
                    "day":9,
                    "hour":11,
                    "minute":0,
                    "display_date":"09.06.2025 11:00"
                },
                "text":{
                    "headline":"Zahtjev policije za pretragu — Pp Prz-74/2025",
                    "text":"<h1>Nulti Dokument</h1><p>Policijska uprava osječko-baranjska, Sektor kriminalističke policije, Služba organiziranog kriminaliteta, podnosi obrazloženi zahtjev Općinskom sudu u Osijeku za izdavanje naredbe za pretragu doma i drugih prostorija kojima se koristi Andrija Glavaš, Primorska 5, Osijek (klasa: NK-214-05/25-01/1155, urbroj: 511-07-11-25-2, 9. lipnja 2025.).</p><p>U zahtjevu se navodi osnovana sumnja na počinjenje prekršaja prema Zakonu o suzbijanju zlouporabe droga (npr. posjedovanje tvari poput \"amfetamin-speed\", THC i dr.).</p><details><summary>Pravne reference</summary><ul><li>Zakon o suzbijanju zlouporabe droga — čl. 64 st. 3 i/ili čl. 54 st. 3</li><li>Zakon o kaznenom postupku (NN 152/08, 76/09, 80/11, 121/11, 91/12, 143/12, 56/13, 145/13, 152/14, 70/17, 126/19, 130/20, 80/22, 36/24, 72/25)</li></ul></details>"
                },
                "media":{
                    "url":"{{ asset('storage/docs/timeline_graphql.pdf') }}",
                    "caption": "Epredmet GraphQL podatci - nulti dokument",
                    "thumbnail":"",
                    "alt": "Epredmet GraphQL podatci - nulti dokument",
                    "title": "Epredmet GraphQL podatci - nulti dokument",
                    "link":"{{ asset('storage/docs/timeline_graphql.pdf') }}",
                    "link_target":"_blank"
                },
                "group": "Krivotvoreni Timeline",
                "autolink":true,
                "unique_id":"pp-prz-74-2025-zahtjev-pu",
                "display_date":"09.06.2025",
            },
            {
                "start_date": {
                    "year": 2025,
                    "month": 6,
                    "day": 9,
                    "hour": 11,
                    "minute": 0
                },
                "end_date": {
                    "year": 2025,
                    "month": 6,
                    "day": 9,
                    "hour": 12,
                    "minute": 40
                },
                "text": {
                    "headline": "Pretraga doma i drugih prostorija (Osijek, Primorska 5)",
                    "text": "<p>Po naredbi Općinskog suda u Osijeku, Prekršajni odjel broj Pp Prz-74/2025-2, izvršena je pretraga doma i drugih prostorija. Pronađeno i oduzeto (POPOP):</p><ul><li>Hašiš, PE vrećica, 18,00 g (POPOP 01422485)</li><li>Konoplja, 2 cvjetna vrha, staklenka, 2,09 g, >0,3% THC (POPOP 01422485)</li><li>Konoplja, usitnjeni cvjetni vrhovi, PE vrećica, 2,35 g, >0,3% THC (POPOP 01422485)</li><li>Konoplja, više cvjetnih vrhova, PE vrećica, 21,4 g, >0,3% THC (POPOP 01422486)</li><li>MDMA, bijela grumenasta materija, PE vrećica, 0,38 g (POPOP 01422486)</li><li>Amfetamin „speed“, PE vrećica, 3 g (POPOP 01422486)</li><li>Psilocibin gljive, staklenka, 6,9 g (POPOP 01422488)</li><li>Digitalna vaga „digital scala“, 0,01–100 g, s tragovima konoplje (POPOP 01422487)</li><li>Digitalna vaga „On balance CJ-20 Scale“, 0,001–20 g, s priborom (POPOP 01422487)</li><li>Automatska puška M70 AB, ser. br. 669991; 2 spremnika s 60 kom. streljiva 7,62 mm; dodatnih 80 kom. streljiva 7,62 mm; bajonet; pribor za čišćenje (POPOP 01422489)</li><li>Dodatna 3 spremnika s 89 kom. streljiva 7,62 mm; 2 kutije s 80 kom. streljiva 7,62 mm (POPOP 01422490)</li></ul><details><summary>Pravne reference</summary><ul><li>Naredba: Općinski sud Osijek, Prekršajni odjel, Pp Prz-74/2025-2</li><li>KZ — čl. 190. st. 2. (Neovlaštena proizvodnja i promet drogama)</li><li>KZ — čl. 331. st. 1. i st. 3. (Nedozvoljeno posjedovanje, izrada i nabavljanje oružja i eksplozivnih tvari)</li></ul></details>"
                },
                "media":{
                    "url":"{{ asset('storage/docs/NaredbaPretresPpPrz74.pdf') }}",
                    "caption": "Naredba za pretragu doma",
                    "thumbnail":"",
                    "alt": "Naredba za pretragu doma",
                    "title": "Naredba za pretragu doma",
                    "link":"{{ asset('storage/docs/NaredbaPretresPpPrz74.pdf') }}",
                    "link_target":"_blank"
                },
                "group": "Krivotvoreni Timeline",
                "display_date": "09.06.2025., 11:00–12:40",
                "autolink": true,
                "unique_id": "pretraga-doma-i-drugih-prostorija-osijek-primorska-5-2025-06-09-1100-1240"
            },
            {
                "start_date": {
                    "year": 2025,
                    "month": 6,
                    "day": 9,
                    "hour": 9,
                    "minute": 0,
                    "display_date": "9. lipnja 2025."
                },
                "end_date": {
                    "year": 2025,
                    "month": 6,
                    "day": 12,
                    "hour": 0,
                    "minute": 0,
                    "display_date": "12. lipnja 2025."
                },
                "text": {
                    "headline": "Naredba suda za pretragu doma i prostorija — Pp Prz-74/2025",
                    "text": "<p>Općinski sud u Osijeku, po sutkinji Dunji Bertok, izdaje naredbu za pretragu doma i drugih prostorija kojima se koristi Andrija Glavaš (OIB: 25041200286), na adresi Primorska 5, Osijek, po zahtjevu PU osječko-baranjske (9. lipnja 2025.).</p><p>I. Na temelju čl. 159 st. 1 t. 1 Prekršajnog zakona, u vezi s čl. 240 ZKP-a, odobrava se pretraga doma i drugih prostorija.</p><p>II. Izvršenje se povjerava policijskim službenicima PU osječko-baranjske (Sektor kriminalističke policije, Služba organiziranog kriminaliteta) koji su dužni postupati prema čl. 247–260 ZKP-a i primjerak zapisnika dostaviti sudu u roku od 3 dana (poziv na broj: Pp Prz-74/2025).</p><p>III. Rok za izvršenje naredbe: 3 dana od trenutka izdavanja.</p><p>IV. Osoba kod koje se obavlja pretraga mora biti upoznata da prije početka pretrage ima pravo izvijestiti branitelja.</p><p>UPUTA O PRAVNOM LIJEKU: Protiv ove naredbe žalba nije dopuštena.</p><details><summary>Pravne reference</summary><ul><li>Prekršajni zakon — čl. 159 st. 1 t. 1 (NN 107/07, 39/13, 157/13, 110/15, 70/17, 118/18, 114/22)</li><li>Zakon o kaznenom postupku — čl. 240; čl. 247–260 (NN 152/08, 76/09, 80/11, 121/11, 91/12, 143/12, 56/13, 145/13, 152/14, 70/17, 126/19, 130/20, 80/22, 36/24, 72/25)</li><li>Zakon o suzbijanju zlouporabe droga — čl. 54 st. 3 i/ili čl. 64 st. 3</li></ul></details>"
                },
                "media":{
                    "url":"{{ asset('storage/docs/e-Predmet.pdf') }}",
                    "caption": "E-predmet - stanje preteksta",
                    "thumbnail":"",
                    "alt": "E-predmet - stanje preteksta",
                    "title": "E-predmet - stanje preteksta",
                    "link":"{{ asset('storage/docs/e-Predmet.pdf') }}",
                    "link_target":"_blank"
                },
                "group": "Krivotvoreni Timeline",
                "display_date": "9.–12. lipnja 2025.",
                "autolink": true,
                "unique_id": "pp-prz-74-2025-naredba-pretrage"
            },
            {
                "start_date": {
                    "year": 2025,
                    "month": 6,
                    "day": 9,
                    "hour": 12,
                    "minute": 0
                },
                "text": {
                    "headline": "Preliminarno ispitivanje uzoraka i nalazi",
                    "text": "<p>Po obavljenom vaganju provedeno je preliminarno ispitivanje uzoraka sljedećim testovima:</p><ol><li>M.M.C. International B.V. General Screening / Multi Party Drugs Test</li><li>M.M.C. International B.V. Cannabis Test</li><li>M.M.C. International B.V. Crystal Meth/XTC Test (Meth)</li><li>M.M.C. International B.V. Amphetamines/MDMA</li><li>M.M.C. International B.V. Opiates/Amphetamines Test</li></ol><p>Rezultati ukazuju na osnove sumnje u prisutnost: konoplje s &gt;0,3% THC (više uzoraka), MDMA, amfetamina (\"speed\") te psilocibinskih gljiva. Privremeno oduzeti predmeti bit će proslijeđeni na pohranu u Centar za forenzična ispitivanja, istraživanja i vještačenja \"Ivan Vučetić\" u Zagrebu.</p><p><em>Napomena:</em> Točnu vrstu tvari, masu i udio djelatne tvari moguće je odrediti isključivo vještačenjem u CFIIV \"Ivan Vučetić\".</p><details><summary>Pravne i predmetne reference</summary><ul><li>Službena zabilješka sastavljena u PU osječko-baranjskoj: 09.06.2025.</li><li>Predmeti proslijeđeni: CFIIV \"Ivan Vučetić\" (Zagreb)</li></ul></details>"
                },
                "group": "Krivotvoreni Timeline",
                "display_date": "09.06.2025",
                "media":{
                    "url":"{{ asset('storage/docs/SluzbenaZabiljeskaIspitivanjeMaterije.pdf') }}",
                    "caption": "Ispitivanje uzoraka poljskim testovima",
                    "thumbnail":"",
                    "alt": "Ispitivanje uzoraka poljskim testovima",
                    "title": "Ispitivanje uzoraka poljskim testovima",
                    "link":"{{ asset('storage/docs/SluzbenaZabiljeskaIspitivanjeMaterije.pdf') }}",
                    "link_target":"_blank"
                },
                "autolink": true,
                "unique_id": "preliminarno-ispitivanje-uzoraka-i-nalazi-2025-06-09"
            },
            {
                "start_date": {
                    "year": 2025,
                    "month": 6,
                    "day": 9,
                    "hour": 12,
                    "minute": 50
                },
                "end_date": {
                    "year": 2025,
                    "month": 6,
                    "day": 9,
                    "hour": 13,
                    "minute": 15
                },
                "text": {
                    "headline": "Privremeno oduzimanje mobilnog uređaja (POPOP 01422491)",
                    "text": "<p>Istoga dana, u prostorijama PU Osječko-baranjske, privremeno je oduzet mobilni telefon marke „Huawei nova 9 SE“ (IMEI: 8679090622498823 i 867909063998821; pozivni brojevi: 098/965 5609, 095/584 6314).</p><details><summary>Pravne reference</summary><ul><li>POPOP ser. br. 01422491</li><li>ZKP — odredbe o privremenom oduzimanju predmeta</li></ul></details> <p>Provedena dokazna radnja privremenog oduzimanja predmeta bez naloga. Izdana potvrda o oduzimanju: br. 01 922 437. Popis privremeno oduzetih predmeta priložen uz zapisnik.</p><details><summary>Pravne reference</summary><ul><li>Članak 261. Zakona o kaznenom postupku (ZKP)</li><li>Članak 212. ZKP</li><li>Članak 85. stavak 1. i 7. ZKP (prava prisutnih osoba)</li><li>Članak 206.f ZKP (tajnost izvida)</li><li>Članak 213. stavak 3. ZKP (nejavnost istraživanja / tajnost)</li><li>Članak 231. stavak 2. ZKP (nejavnost istrage / tajnost)</li></ul></details>"
                },
                "group": "Krivotvoreni Timeline",
                "display_date": "09. 06. 2025, 12:50–13:15",
                "media":{
                    "url":"{{ asset('storage/docs/ZapisnikOduzimanjeMobitelaBezNaloga.pdf') }}",
                    "caption": "Oduzimanje mobitela bez naloga",
                    "thumbnail":"",
                    "alt": "Oduzimanje mobitela bez naloga",
                    "title": "Oduzimanje mobitela bez naloga",
                    "link":"{{ asset('storage/docs/ZapisnikOduzimanjeMobitelaBezNaloga.pdf') }}",
                    "link_target":"_blank"
                },
                "autolink": true,
                "unique_id": "privremeno-oduzimanje-mobilnog-uredaja-popop-01422491-2025-06-09"
            },
            {
                "start_date": {
                    "year": 2025,
                    "month": 6,
                    "day": 9,
                    "hour": 14,
                    "minute": 35,
                    "display_date": "09.06.2025., 14:35"
                },
                "text": {
                    "headline": "Pouka o pravima osumnjičenika — Andrija Glavaš",
                    "text": "<p>Temeljem članka 208.a st. 1 i 2 Zakona o kaznenom postupku (ZKP), osumnjičenik Andrija Glavaš (rođen 14.10.1989. u Osijeku) poučen je o svojim pravima. Mjesto: Osijek. Vrijeme: 09.06.2025. u 14:35 sati. Policijski službenik: Aleksandar Sitarić. Predmet: 511-07-11-K-51/25.</p><ul><li>Pravo na branitelja</li><li>Pravo na tumačenje i prevođenje</li><li>Pravo da nije dužan iskazivati niti odgovarati na pitanja</li><li>Pravo da u svakom trenutku može napustiti policijske prostorije, osim u slučaju uhićenja</li></ul><details><summary>Pravne reference</summary><ul><li>Zakon o kaznenom postupku — čl. 208.a st. 1 i 2</li><li>Zakon o kaznenom postupku — čl. 8 (tumačenje i prevođenje)</li><li>Zakon o kaznenom postupku — čl. 108 (napuštanje policijskih prostorija)</li></ul></details>"
                },
                "media":{
                    "url":"{{ asset('storage/docs/PoukaOPravimaOsumnjicenikaPpPrz74.pdf') }}",
                    "caption": "Antidatirana pouka",
                    "thumbnail":"",
                    "alt": "Antidatirana pouka",
                    "title": "Antidatirana pouka",
                    "link":"{{ asset('storage/docs/PoukaOPravimaOsumnjicenikaPpPrz74.pdf') }}",
                    "link_target":"_blank"
                },
                "group": "Krivotvoreni Timeline",
                "display_date": "09.06.2025., 14:35",
                "autolink": true,
                "unique_id": "pouka-o-pravima-osumnjicenika-andrija-glavas-2025-06-09"
            },
            {
                "start_date": {
                    "year": 2025,
                    "month": 6,
                    "day": 9,
                    "hour": 14,
                    "minute": 45
                },
                "end_date": {
                    "year": 2025,
                    "month": 6,
                    "day": 9,
                    "hour": 15,
                    "minute": 12
                },
                "display_date": "9. 6. 2025., 14:45–15:12",
                "group": "Krivotvoreni Timeline",
                "text": {
                    "headline": "Ispitivanje i AV snimanje osumnjičenika – PU osječko-baranjska",
                    "text": "<p>U prostorijama PU osječko-baranjske započinje audio-video snimanje i ispitivanje osumnjičenika Andrije Glavaša. Prisotni: policijski službenik Aleksandar Simović, zapisničar Mate Surać, stručna osoba za tehničko snimanje Miroslav Pandurević i osumnjičenik. Osobe su upozorene na tajnost izvida i mogućnost korištenja snimke kao dokaza.</p><p>Osumnjičenik potvrđuje da razumije jezik postupka, prima pisanu pouku o pravima, te se najprije odriče prava na branitelja uz upozorenje o posljedicama, uz napomenu da to pravo može zatražiti u bilo kojem trenutku.</p><p>Tereti se za: neovlaštenu proizvodnju i promet drogama te nedozvoljeno posjedovanje, izradu i nabavljanje oružja i eksplozivnih tvari. Pretraga je pronašla više vrsta droga i oružje.</p><details><summary>Pravne reference</summary><ul><li>Zakon o kaznenom postupku – čl. 22 st. 1 (podatci pri prvom ispitivanju, pravna pouka)</li><li>Kazneni zakon – čl. 190 st. 2 (neovlaštena proizvodnja i promet drogama)</li><li>Kazneni zakon – čl. 31 st. 1 i 3 (nedozvoljeno posjedovanje, izrada i nabavljanje oružja i eksplozivnih tvari); u jednom navodu spomenut i čl. 301 st. 1 i 3</li></ul></details><details><summary>Oduzeti/pronađeni predmeti (sažetak)</summary><ul><li>Hašiš: ukupno 18 g</li><li>Konoplja/cvjetni vrhovi: 2,09 g; 2,35 g; 21,4 g (sadržaj THC > 0,3%)</li><li>MDMA: ~0,36 g</li><li>Amfetamin (speed): ~3 g</li><li>Psilocibinske gljive: ~6,9 g</li><li>Digitalne vage: 2 kom (0,01–100 g; 0,001–20 g) s tragovima biljne materije</li><li>Automatska puška M70 AB, 5 spremnika, ~309 kom streljiva 7,62 mm, bajunet, pribor za čišćenje</li></ul></details><p>Osumnjičenik u obrani navodi da marihuanu konzumira rekreativno (ne bavi se prodajom), a pušku je zadržao iz znatiželje nakon što je prijatelj preminuo. Na kraju se odriče prava na pregled/reprodukciju snimke i čitanje zapisnika; ispitivanje završava u 15:12.</p>"
                },
                "media":{
                    "url":"{{ asset('storage/docs/iznudjenaIzjava.pdf') }}",
                    "caption": "Iznudjena izjava",
                    "thumbnail":"",
                    "alt": "Iznudjena izjava",
                    "title": "Iznudjena izjava",
                    "link":"{{ asset('storage/docs/iznudjenaIzjava.pdf') }}",
                    "link_target":"_blank"
                },
                "autolink": true,
                "unique_id": "iznudjeno-lazno-priznanje-2025-06-09"
            }
        ]
    };
    const dataBottom = {
        title: { text: { headline: "Pravi Timeline" } },
        events: [
            {
                "start_date":{
                    "year":2025,
                    "month":6,
                    "day":9,
                    "hour": 8,
                    "minute":0,
                    "display_date":"09.06.2025 08:00"
                },
                "end_date":{
                    "year":2025,
                    "month":6,
                    "day":9,
                    "hour":10,
                    "minute":0,
                    "display_date":"09.06.2025 10:00"
                },
                "text":{
                    "headline":"Zahtjev policije za pretragu — Pp Prz-74/2025",
                    "text":"<h1>Nulti Dokument</h1><p>Policijska uprava osječko-baranjska, Sektor kriminalističke policije, Služba organiziranog kriminaliteta, podnosi obrazloženi zahtjev Općinskom sudu u Osijeku za izdavanje naredbe za pretragu doma i drugih prostorija kojima se koristi Andrija Glavaš, Primorska 5, Osijek (klasa: NK-214-05/25-01/1155, urbroj: 511-07-11-25-2, 9. lipnja 2025.).</p><p>U zahtjevu se navodi osnovana sumnja na počinjenje prekršaja prema Zakonu o suzbijanju zlouporabe droga (npr. posjedovanje tvari poput \"amfetamin-speed\", THC i dr.).</p><details><summary>Pravne reference</summary><ul><li>Zakon o suzbijanju zlouporabe droga — čl. 64 st. 3 i/ili čl. 54 st. 3</li><li>Zakon o kaznenom postupku (NN 152/08, 76/09, 80/11, 121/11, 91/12, 143/12, 56/13, 145/13, 152/14, 70/17, 126/19, 130/20, 80/22, 36/24, 72/25)</li></ul></details>"
                },
                "media":{
                    "url":"{{ asset('storage/docs/timeline_graphql.pdf') }}",
                    "caption": "Epredmet GraphQL podatci - nulti dokument",
                    "thumbnail":"",
                    "alt": "Epredmet GraphQL podatci - nulti dokument",
                    "title": "Epredmet GraphQL podatci - nulti dokument",
                    "link":"{{ asset('storage/docs/timeline_graphql.pdf') }}",
                    "link_target":"_blank"
                },
                "group":"Pravi Timeline",
                "autolink":true,
                "unique_id":"pp-prz-74-2025-zahtjev-pu",
                "display_date":"09.06.2025",
            },
            {
                "start_date":{
                    "year":2025,
                    "month":6,
                    "day":9,
                    "hour": 10,
                    "minute":10,
                    "display_date":"09.06.2025 10:10"
                },
                "end_date":{
                    "year":2025,
                    "month":6,
                    "day":9,
                    "hour":11,
                    "minute":0,
                    "display_date":"09.06.2025 11:00"
                },
                "text":{
                    "headline":"Pravi početak pretresa",
                    "text":"<h1>Pretres</h1><p>Oni su zapravo došli oko 10:10, ja sam taman radio. Tj. bio sam na pozivu, čuo sam njihovo lupanje po vratima ali nisam mogao odmah reagirati zbog poziva u kojemu sam bio. U međuvremenu šaljem poruku ženi da vidim zna li ona tko lupa, tj. da li je nekoga/nešto naručivala.</p><p>Ona odgovara da nezna. Osim lupanja počeli su me zvati i na mobitel s skrivenog broja. Poziv završava i odlazim vidjeti tko je. Nakon što sam otvorio vrata, pretres je faktički već počeo a ja sam kidnapiran iz radne sobe i laptopa (kidnapiran s posla). Ovo se dogodilo oko cca 10:25 vidljivo na Facebook Messengeru, gdje vlada radijska tišina do 11:13.</p><details><summary>Pravne reference</summary><ul><li>Zakon o suzbijanju zlouporabe droga — čl. 64 st. 3 i/ili čl. 54 st. 3</li><li>Zakon o kaznenom postupku (NN 152/08, 76/09, 80/11, 121/11, 91/12, 143/12, 56/13, 145/13, 152/14, 70/17, 126/19, 130/20, 80/22, 36/24, 72/25)</li></ul></details>"
                },
                "media":{
                    "url":"{{ asset('storage/docs/messenger.png') }}",
                    "caption": "Pretres prije početka pretresa",
                    "thumbnail":"",
                    "alt": "Pretres prije početka pretresa",
                    "title": "Pretres prije početka pretresa",
                    "link":"{{ asset('storage/docs/messenger.png') }}",
                    "link_target":"_blank"
                },
                "group":"Pravi Timeline",
                "autolink":true,
                "unique_id":"pp-prz-74-2025-pocetak-pretresa",
                "display_date":"09.06.2025 10:10",
            },
            {
                "start_date":{
                    "year":2025,
                    "month":6,
                    "day":9,
                    "hour": 10,
                    "minute":40,
                    "display_date":"09.06.2025 10:40"
                },
                "end_date":{
                    "year":2025,
                    "month":6,
                    "day":9,
                    "hour":10,
                    "minute":55,
                    "display_date":"09.06.2025 10:55"
                },
                "text":{
                    "headline": "Izbacivanje iz vlastitog stana te samostalni ulazak K-9 tima (vodić i pas), prije dolaska svjedoka",
                    "text":"<h1>Neformalni početak</h1><p>Nakon otvaranja vrata, sami su se pustili unutra, te su obznanili kako imaju nalog za pretres. U stanu je bio i moj pas Božo - ovo ih je znatno omelo, nisu računali na još jednog psa. Tako da sam bio primoran (bez mog pristanka) izači van stana s njima i Božom, iz razloga što su oni doveli K-9 tim za traženje droge (daleko pretjerano ako se mene pita, radi količina za osobnu uporabu). Krenuli smo van, prvo njih 4 a zadnji ja i Božo. Kako sam izašao iz stana, primjertio sam da desno od vrata čovjek s psom. On se malo stisnio uza zid, kako bi interakcija pas VS pas prošla glatko. Te čim smo mi izašli on je brže bolje ušao u stan i zatvorio vrata. Od ovog trenutka on je faktički potpuno sam u stanu. Mi smo izašli ispred, te sam tada prvi puta čuo spomen na danas već famozne \"pakete\". Doduše tada je bio samo \"paket\" u jednini. Također valja istaknuti da oni čak ni tada nisu bili u stanju jasno i glasno artikulirati taj paket. Više su aludirali na naručivanje paketa, nego što su direktno pitali/komentirali. Sveukupno smo vani bili 10-ak minuta. Dovoljno za 2 cigare zapaliti. Usred toga, jedan od njih je išao u potragu za svjedocima. Prvo se popeo na kat iznad te je tamo zamolio susjedu da bude svjedok, ona se samo spustila na kat ispod te pretpostavljam ušla u stan u kojemu nije bilo mene ili supruge (tj. vlasnika stana). U stanu je vidjela nepoznatog muškarca s psom koji je bio potpuno samu u stanu cca 5-10 minuta. Drugi svjedok je nađen na kraju ulice, nakon susjede. Te je drugi svjedok prošao pored nas i vidio da stojimo vani. Ovo sve svjedoci mogu i potvrditi. Kada je K-9 bio gotovo, nazvao je jednog od policajaca na mobitel i javio mu da 'ima svega'. Pretpostavljam da je koristeči psa pronašao veliku teglu u kojoj je bilo 20g marihuane u jednoj vrečici i 18g hašiša u drugoj. To je vrlo vjerovatno, i to zbog korištenja psa te snažnog mirisa već spomenuthi substanci. Kada sam se ja vratio svjedoci su već bili unutra.</p><details></details>"
                },
                "media":{
                    "url":"{{ asset('storage/docs/NaknadnaPouka.pdf') }}",
                    "caption": "Odricanje od branitelja bez vremena, potpisano u policijskoj postaji a piše Primorska 5",
                    "thumbnail":"",
                    "alt": "Odricanje od branitelja bez vremena, potpisano u policijskoj postaji a piše Primorska 5",
                    "title": "Odricanje od branitelja bez vremena, potpisano u policijskoj postaji a piše Primorska 5",
                    "link":"{{ asset('storage/docs/NaknadnaPouka.pdf') }}",
                    "link_target":"_blank"
                },
                "group":"Pravi Timeline",
                "autolink":true,
                "unique_id":"pp-prz-74-2025-k-9-pas-mater-sam-u-stanu",
                "display_date":"09.06.2025 10:40",
            },
            {
                "start_date":{
                    "year":2025,
                    "month":6,
                    "day":9,
                    "hour": 11,
                    "minute":0,
                    "display_date":"09.06.2025 11:00"
                },
                "end_date":{
                    "year":2025,
                    "month":6,
                    "day":9,
                    "hour":13,
                    "minute":45,
                    "display_date":"09.06.2025 13:45"
                },
                "text":{
                    "headline": "Pretres sa svim detaljima (čak i onima koje žele sakriti)",
                    "text":"<h1>Neformalni početak</h1><p>Nakon otvaranja vrata, sami su se pustili unutra, te su obznanili kako imaju nalog za pretres. U stanu je bio i moj pas Božo - ovo ih je znatno omelo, nisu računali na još jednog psa. Tako da sam bio primoran (bez mog pristanka) izači van stana s njima i Božom, iz razloga što su oni doveli K-9 tim za traženje droge (daleko pretjerano ako se mene pita, radi količina za osobnu uporabu). Krenuli smo van, prvo njih 4 a zadnji ja i Božo. Kako sam izašao iz stana, primjertio sam da desno od vrata čovjek s psom. On se malo stisnio uza zid, kako bi interakcija pas VS pas prošla glatko. Te čim smo mi izašli on je brže bolje ušao u stan i zatvorio vrata. Od ovog trenutka on je faktički potpuno sam u stanu. Mi smo izašli ispred, te sam tada prvi puta čuo spomen na danas već famozne \"pakete\". Doduše tada je bio samo \"paket\" u jednini. Također valja istaknuti da oni čak ni tada nisu bili u stanju jasno i glasno artikulirati taj paket. Više su aludirali na naručivanje paketa, nego što su direktno pitali/komentirali. Sveukupno smo vani bili 10-ak minuta. Dovoljno za 2 cigare zapaliti. Usred toga, jedan od njih je išao u potragu za svjedocima. Prvo se popeo na kat iznad te je tamo zamolio susjedu da bude svjedok, ona se samo spustila na kat ispod te pretpostavljam ušla u stan u kojemu nije bilo mene ili supruge (tj. vlasnika stana). U stanu je vidjela nepoznatog muškarca s psom koji je bio potpuno samu u stanu cca 5-10 minuta. Drugi svjedok je nađen na kraju ulice, nakon susjede. Te je drugi svjedok prošao pored nas i vidio da stojimo vani. Ovo sve svjedoci mogu i potvrditi. Kada je K-9 bio gotovo, nazvao je jednog od policajaca na mobitel i javio mu da 'ima svega'. Pretpostavljam da je koristeči psa pronašao veliku teglu u kojoj je bilo 20g marihuane u jednoj vrečici i 18g hašiša u drugoj. To je vrlo vjerovatno, i to zbog korištenja psa te snažnog mirisa već spomenuthi substanci. Kada sam se ja vratio svjedoci su već bili unutra.</p><details></details>"
                },
                "media":{
                    "url":"{{ asset('storage/docs/NaknadnaPouka.pdf') }}",
                    "caption": "Odricanje od branitelja bez vremena, potpisano u policijskoj postaji a piše Primorska 5",
                    "thumbnail":"",
                    "alt": "Odricanje od branitelja bez vremena, potpisano u policijskoj postaji a piše Primorska 5",
                    "title": "Odricanje od branitelja bez vremena, potpisano u policijskoj postaji a piše Primorska 5",
                    "link":"{{ asset('storage/docs/NaknadnaPouka.pdf') }}",
                    "link_target":"_blank"
                },
                "group":"Pravi Timeline",
                "autolink":true,
                "unique_id":"pp-prz-74-2025-k-9-pas-mater-sam-u-stanu",
                "display_date":"09.06.2025 10:40",
            },
        ]
    };

    // Options — reuse your own, with one key difference for the bottom timeline
    const commonOptions = {
        hash_bookmark: false,
        timenav_height_percentage: 26,
        initial_zoom: 6,
        default_bg_color: '#f9fafb',
        scale_factor: 1.2,
        font: 'Merriweather-NewsCycle'
    };
    const optionsTop = { ...commonOptions, timenav_position: 'bottom' };
    const optionsBottom = { ...commonOptions, timenav_position: 'top' };

    // Instantiate timelines
    const TL_INST = {
        top: new TL.Timeline('timeline-top', dataTop, optionsTop),
        bottom: new TL.Timeline('timeline-bottom', dataBottom, optionsBottom)
    };

    // Helpers for dates
    function eventStartDate(ev) {
        const s = ev.start_date || {};
        return new Date(s.year || 0, (s.month || 1) - 1, s.day || 1, s.hour || 0, s.minute || 0, s.second || 0, s.millisecond || 0);
    }
    function nearestIndex(events, targetDate) {
        if (!events || !events.length) return -1;
        const t = targetDate.getTime();
        let best = -1, bestDiff = Infinity;
        for (let i = 0; i < events.length; i++) {
            const dt = eventStartDate(events[i]).getTime();
            const diff = Math.abs(dt - t);
            if (diff < bestDiff) { bestDiff = diff; best = i; }
        }
        return best;
    }

    // Shared time range for crosshair
    const allTimes = [
        ...dataTop.events.map(eventStartDate).map(d => d.getTime()),
        ...dataBottom.events.map(eventStartDate).map(d => d.getTime())
    ].filter(Number.isFinite);
    const unionMin = Math.min(...allTimes);
    const unionMax = Math.max(...allTimes);
    const unionSpan = Math.max(1, unionMax - unionMin);

    function dateToRatio(date) { return (date.getTime() - unionMin) / unionSpan; }
    function ratioToDate(r) { return new Date(Math.round(unionMin + Math.max(0, Math.min(1, r)) * unionSpan)); }

    // Crosshair overlays
    const overlays = {
        top: document.querySelector('#col-top .sync-overlay'),
        bottom: document.querySelector('#col-bottom .sync-overlay')
    };
    function formatDate(d) {
        try { return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }); }
        catch { return d.toISOString().slice(0, 10); }
    }
    function setMarker(side, date) {
        const ov = overlays[side]; if (!ov) return;
        const line = ov.querySelector('.sync-line');
        const tip = ov.querySelector('.sync-tooltip');
        const w = ov.clientWidth || 1;
        const x = Math.max(0, Math.min(1, dateToRatio(date))) * w;
        line.style.left = x + 'px';
        tip.style.left = x + 'px';
        tip.textContent = formatDate(date);
    }
    function setMarkersBoth(date) {
        setMarker('top', date);
        setMarker('bottom', date);
        lastMarkerDate = date;
    }

    // Hover-to-open details on both timelines
    const stackEl = document.getElementById('tl-compare-stack');
    let lastMarkerDate = new Date((unionMin + unionMax) / 2);
    let syncing = false;
    const currentIndex = { top: -1, bottom: -1 };

    function gotoIfChanged(side, idx) {
        if (idx < 0) return;
        if (currentIndex[side] !== idx) {
            currentIndex[side] = idx;
            TL_INST[side].goTo(idx);
        }
    }

    function previewBothAtDate(date) {
        const idxTop = nearestIndex(dataTop.events, date);
        const idxBottom = nearestIndex(dataBottom.events, date);
        syncing = true; // prevent recursive on('change') bounce
        gotoIfChanged('top', idxTop);
        gotoIfChanged('bottom', idxBottom);
        syncing = false;
        setMarkersBoth(date);
    }

    // Only “seek on hover” when the pointer is over either timenav
    function isOverTimenav(target) {
        return !!(target && (target.closest('.tl-timenav') || target.matches('.tl-timenav')));
    }

    // Throttle hover seeking to keep it smooth
    let rafPending = false, lastHoverEvent = null;
    function onPointerMove(e) {
        lastHoverEvent = e;
        if (!rafPending) {
            rafPending = true;
            requestAnimationFrame(() => {
                rafPending = false;
                if (!lastHoverEvent || !isOverTimenav(lastHoverEvent.target)) {
                    // Always move the marker so user sees alignment, but avoid seeking slides
                    const rect = stackEl.getBoundingClientRect();
                    const ratio = (lastHoverEvent.clientX - rect.left) / rect.width;
                    setMarkersBoth(ratioToDate(ratio));
                    return;
                }
                const rect = stackEl.getBoundingClientRect();
                const ratio = (lastHoverEvent.clientX - rect.left) / rect.width;
                const date = ratioToDate(ratio);
                previewBothAtDate(date);
            });
        }
    }

    stackEl.addEventListener('mousemove', onPointerMove, { passive: true, capture: true });
    stackEl.addEventListener('mouseleave', () => lastHoverEvent = null, { capture: true });

    // Keep both timelines synchronized when user navigates (click/drag) either one
    function onSlideChange(source, payload) {
        if (syncing) return;
        const data = source === 'top' ? dataTop : dataBottom;
        const other = source === 'top' ? 'bottom' : 'top';

        // Find current date from payload or index
        let date = null;
        if (typeof payload?.index === 'number') {
            const ev = data.events[payload.index];
            if (ev) date = eventStartDate(ev);
        }
        if (!date && payload?.unique_id) {
            const ev = (data.events || []).find(e =>
                e.unique_id === payload.unique_id || e.id === payload.unique_id || e.slug === payload.unique_id
            );
            if (ev) date = eventStartDate(ev);
        }
        if (!date) return;

        // Sync the other timeline to nearest event and update crosshair
        syncing = true;
        const idxOther = nearestIndex((other === 'top' ? dataTop : dataBottom).events, date);
        gotoIfChanged(other, idxOther);
        syncing = false;
        setMarkersBoth(date);
    }

    TL_INST.top.on('change', (payload) => onSlideChange('top', payload));
    TL_INST.bottom.on('change', (payload) => onSlideChange('bottom', payload));
    TL_INST.top.on('loaded', () => setMarkersBoth(lastMarkerDate));
    TL_INST.bottom.on('loaded', () => setMarkersBoth(lastMarkerDate));

    // Keep crosshair aligned on resize
    window.addEventListener('resize', () => { if (lastMarkerDate) setMarkersBoth(lastMarkerDate); });



</script>
</body>
</html>

