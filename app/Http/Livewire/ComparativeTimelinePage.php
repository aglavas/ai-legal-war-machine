<?php

namespace App\Http\Livewire;

use Livewire\Component;

class ComparativeTimelinePage extends Component
{
    public string $dataTopJs;
    public string $dataBottomJs;

    public function mount(): void
    {
        $dataTop = [
            'title' => [ 'text' => [ 'headline' => 'Lažirani timeline' ] ],
            'events' => [
                [
                    'start_date' => ['year'=>2025,'month'=>6,'day'=>9,'hour'=>10,'minute'=>55,'display_date'=>'09.06.2025 08:00'],
                    'end_date'   => ['year'=>2025,'month'=>6,'day'=>9,'hour'=>11,'minute'=>0,'display_date'=>'09.06.2025 11:00'],
                    'text' => [
                        'headline' => 'Zahtjev policije za pretragu — Pp Prz-74/2025',
                        'text' => '<h1>Nulti Dokument</h1><p>Policijska uprava osječko-baranjska, Sektor kriminalističke policije, Služba organiziranog kriminaliteta, podnosi obrazloženi zahtjev Općinskom sudu u Osijeku za izdavanje naredbe za pretragu doma i drugih prostorija kojima se koristi Andrija Glavaš, Primorska 5, Osijek (klasa: NK-214-05/25-01/1155, urbroj: 511-07-11-25-2, 9. lipnja 2025.).</p><p>U zahtjevu se navodi osnovana sumnja na počinjenje prekršaja prema Zakonu o suzbijanju zlouporabe droga (npr. posjedovanje tvari poput "amfetamin-speed", THC i dr.).</p><details><summary>Pravne reference</summary><ul><li>Zakon o suzbijanju zlouporabe droga — čl. 64 st. 3 i/ili čl. 54 st. 3</li><li>Zakon o kaznenom postupku (NN 152/08, 76/09, 80/11, 121/11, 91/12, 143/12, 56/13, 145/13, 152/14, 70/17, 126/19, 130/20, 80/22, 36/24, 72/25)</li></ul></details>'
                    ],
                    'media' => [
                        'url' => asset('storage/docs/timeline_graphql.pdf'),
                        'caption' => 'Epredmet GraphQL podatci - nulti dokument',
                        'thumbnail' => '', 'alt' => 'Epredmet GraphQL podatci - nulti dokument', 'title' => 'Epredmet GraphQL podatci - nulti dokument',
                        'link' => asset('storage/docs/timeline_graphql.pdf'), 'link_target' => '_blank'
                    ],
                    'group' => 'Krivotvoreni Timeline', 'autolink' => true, 'unique_id' => 'pp-prz-74-2025-zahtjev-pu', 'display_date' => '09.06.2025',
                ],
                [
                    'start_date' => ['year'=>2025,'month'=>6,'day'=>9,'hour'=>11,'minute'=>0],
                    'end_date'   => ['year'=>2025,'month'=>6,'day'=>9,'hour'=>12,'minute'=>40],
                    'text' => [
                        'headline' => 'Pretraga doma i drugih prostorija (Osijek, Primorska 5)',
                        'text' => '<p>Po naredbi Općinskog suda u Osijeku, Prekršajni odjel broj Pp Prz-74/2025-2, izvršena je pretraga doma i drugih prostorija. Pronađeno i oduzeto (POPOP):</p><ul><li>Hašiš, PE vrećica, 18,00 g (POPOP 01422485)</li><li>Konoplja, 2 cvjetna vrha, staklenka, 2,09 g, >0,3% THC (POPOP 01422485)</li><li>Konoplja, usitnjeni cvjetni vrhovi, PE vrećica, 2,35 g, >0,3% THC (POPOP 01422485)</li><li>Konoplja, više cvjetnih vrhova, PE vrećica, 21,4 g, >0,3% THC (POPOP 01422486)</li><li>MDMA, bijela grumenasta materija, PE vrećica, 0,38 g (POPOP 01422486)</li><li>Amfetamin „speed“, PE vrećica, 3 g (POPOP 01422486)</li><li>Psilocibin gljive, staklenka, 6,9 g (POPOP 01422488)</li><li>Digitalna vaga „digital scala“, 0,01–100 g, s tragovima konoplje (POPOP 01422487)</li><li>Digitalna vaga „On balance CJ-20 Scale“, 0,001–20 g, s priborom (POPOP 01422487)</li><li>Automatska puška M70 AB, ser. br. 669991; 2 spremnika s 60 kom. streljiva 7,62 mm; dodatnih 80 kom. streljiva 7,62 mm; bajonet; pribor za čišćenje (POPOP 01422489)</li><li>Dodatna 3 spremnika s 89 kom. streljiva 7,62 mm; 2 kutije s 80 kom. streljiva 7,62 mm (POPOP 01422490)</li></ul><details><summary>Pravne reference</summary><ul><li>Naredba: Općinski sud Osijek, Prekršajni odjel, Pp Prz-74/2025-2</li><li>KZ — čl. 190. st. 2. (Neovlaštena proizvodnja i promet drogama)</li><li>KZ — čl. 331. st. 1. i st. 3. (Nedozvoljeno posjedovanje, izrada i nabavljanje oružja i eksplozivnih tvari)</li></ul></details>'
                    ],
                    'media' => [
                        'url' => asset('storage/docs/NaredbaPretresPpPrz74.pdf'),
                        'caption' => 'Naredba za pretragu doma', 'thumbnail'=>'', 'alt'=>'Naredba za pretragu doma', 'title'=>'Naredba za pretragu doma',
                        'link' => asset('storage/docs/NaredbaPretresPpPrz74.pdf'), 'link_target'=>'_blank'
                    ],
                    'group' => 'Krivotvoreni Timeline', 'display_date' => '09.06.2025., 11:00–12:40', 'autolink'=>true,
                    'unique_id' => 'pretraga-doma-i-drugih-prostorija-osijek-primorska-5-2025-06-09-1100-1240'
                ],
                [
                    'start_date' => ['year'=>2025,'month'=>6,'day'=>9,'hour'=>9,'minute'=>0,'display_date'=>'9. lipnja 2025.'],
                    'end_date'   => ['year'=>2025,'month'=>6,'day'=>12,'hour'=>0,'minute'=>0,'display_date'=>'12. lipnja 2025.'],
                    'text' => [
                        'headline' => 'Naredba suda za pretragu doma i prostorija — Pp Prz-74/2025',
                        'text' => '<p>Općinski sud u Osijeku, po sutkinji Dunji Bertok, izdaje naredbu za pretragu doma i drugih prostorija kojima se koristi Andrija Glavaš (OIB: 25041200286), na adresi Primorska 5, Osijek, po zahtjevu PU osječko-baranjske (9. lipnja 2025.).</p><p>I. Na temelju čl. 159 st. 1 t. 1 Prekršajnog zakona, u vezi s čl. 240 ZKP-a, odobrava se pretraga doma i drugih prostorija.</p><p>II. Izvršenje se povjerava policijskim službenicima PU osječko-baranjske (Sektor kriminalističke policije, Služba organiziranog kriminaliteta) koji su dužni postupati prema čl. 247–260 ZKP-a i primjerak zapisnika dostaviti sudu u roku od 3 dana (poziv na broj: Pp Prz-74/2025).</p><p>III. Rok za izvršenje naredbe: 3 dana od trenutka izdavanja.</p><p>IV. Osoba kod koje se obavlja pretraga mora biti upoznata da prije početka pretrage ima pravo izvijestiti branitelja.</p><p>UPUTA O PRAVNOM LIJEKU: Protiv ove naredbe žalba nije dopuštena.</p><details><summary>Pravne reference</summary><ul><li>Prekršajni zakon — čl. 159 st. 1 t. 1 (NN 107/07, 39/13, 157/13, 110/15, 70/17, 118/18, 114/22)</li><li>Zakon o kaznenom postupku — čl. 240; čl. 247–260 (NN 152/08, 76/09, 80/11, 121/11, 91/12, 143/12, 56/13, 145/13, 152/14, 70/17, 126/19, 130/20, 80/22, 36/24, 72/25)</li><li>Zakon o suzbijanju zlouporabe droga — čl. 54 st. 3 i/ili čl. 64 st. 3</li></ul></details>'
                    ],
                    'media' => [
                        'url' => asset('storage/docs/e-Predmet.pdf'),
                        'caption' => 'E-predmet - stanje preteksta', 'thumbnail'=>'', 'alt'=>'E-predmet - stanje preteksta', 'title'=>'E-predmet - stanje preteksta',
                        'link' => asset('storage/docs/e-Predmet.pdf'), 'link_target'=>'_blank'
                    ],
                    'group' => 'Krivotvoreni Timeline', 'display_date'=>'9.–12. lipnja 2025.', 'autolink'=>true,
                    'unique_id' => 'pp-prz-74-2025-naredba-pretrage'
                ],
                [
                    'start_date' => ['year'=>2025,'month'=>6,'day'=>9,'hour'=>12,'minute'=>0],
                    'text' => [
                        'headline' => 'Preliminarno ispitivanje uzoraka i nalazi',
                        'text' => '<p>Po obavljenom vaganju provedeno je preliminarno ispitivanje uzoraka sljedećim testovima:</p><ol><li>M.M.C. International B.V. General Screening / Multi Party Drugs Test</li><li>M.M.C. International B.V. Cannabis Test</li><li>M.M.C. International B.V. Crystal Meth/XTC Test (Meth)</li><li>M.M.C. International B.V. Amphetamines/MDMA</li><li>M.M.C. International B.V. Opiates/Amphetamines Test</li></ol><p>Rezultati ukazuju na osnove sumnje u prisutnost: konoplje s &gt;0,3% THC (više uzoraka), MDMA, amfetamina ("speed") te psilocibinskih gljiva. Privremeno oduzeti predmeti bit će proslijeđeni na pohranu u Centar za forenzična ispitivanja, istraživanja i vještačenja "Ivan Vučetić" u Zagrebu.</p><p><em>Napomena:</em> Točnu vrstu tvari, masu i udio djelatne tvari moguće je odrediti isključivo vještačenjem u CFIIV "Ivan Vučetić".</p><details><summary>Pravne i predmetne reference</summary><ul><li>Službena zabilješka sastavljena u PU osječko-baranjskoj: 09.06.2025.</li><li>Predmeti proslijeđeni: CFIIV "Ivan Vučetić" (Zagreb)</li></ul></details>'
                    ],
                    'group' => 'Krivotvoreni Timeline', 'display_date'=>'09.06.2025',
                    'media' => [
                        'url' => asset('storage/docs/SluzbenaZabiljeskaIspitivanjeMaterije.pdf'),
                        'caption' => 'Ispitivanje uzoraka poljskim testovima', 'thumbnail'=>'', 'alt'=>'Ispitivanje uzoraka poljskim testovima', 'title'=>'Ispitivanje uzoraka poljskim testovima',
                        'link' => asset('storage/docs/SluzbenaZabiljeskaIspitivanjeMaterije.pdf'), 'link_target'=>'_blank'
                    ],
                    'autolink'=>true, 'unique_id'=>'preliminarno-ispitivanje-uzoraka-i-nalazi-2025-06-09'
                ],
                [
                    'start_date' => ['year'=>2025,'month'=>6,'day'=>9,'hour'=>12,'minute'=>50],
                    'end_date'   => ['year'=>2025,'month'=>6,'day'=>9,'hour'=>13,'minute'=>15],
                    'text' => [
                        'headline' => 'Privremeno oduzimanje mobilnog uređaja (POPOP 01422491)',
                        'text' => '<p>Istoga dana, u prostorijama PU Osječko-baranjske, privremeno je oduzet mobilni telefon marke „Huawei nova 9 SE“ (IMEI: 8679090622498823 i 867909063998821; pozivni brojevi: 098/965 5609, 095/584 6314).</p><details><summary>Pravne reference</summary><ul><li>POPOP ser. br. 01422491</li><li>ZKP — odredbe o privremenom oduzimanju predmeta</li></ul></details> <p>Provedena dokazna radnja privremenog oduzimanja predmeta bez naloga. Izdana potvrda o oduzimanju: br. 01 922 437. Popis privremeno oduzetih predmeta priložen uz zapisnik.</p><details><summary>Pravne reference</summary><ul><li>Članak 261. Zakona o kaznenom postupku (ZKP)</li><li>Članak 212. ZKP</li><li>Članak 85. stavak 1. i 7. ZKP (prava prisutnih osoba)</li><li>Članak 206.f ZKP (tajnost izvida)</li><li>Članak 213. stavak 3. ZKP (nejavnost istraživanja / tajnost)</li><li>Članak 231. stavak 2. ZKP (nejavnost istrage / tajnost)</li></ul></details>'
                    ],
                    'group' => 'Krivotvoreni Timeline', 'display_date'=>'09. 06. 2025, 12:50–13:15',
                    'media' => [
                        'url' => asset('storage/docs/ZapisnikOduzimanjeMobitelaBezNaloga.pdf'),
                        'caption' => 'Oduzimanje mobitela bez naloga', 'thumbnail'=>'', 'alt'=>'Oduzimanje mobitela bez naloga', 'title'=>'Oduzimanje mobitela bez naloga',
                        'link' => asset('storage/docs/ZapisnikOduzimanjeMobitelaBezNaloga.pdf'), 'link_target'=>'_blank'
                    ],
                    'autolink'=>true, 'unique_id'=>'privremeno-oduzimanje-mobilnog-uredaja-popop-01422491-2025-06-09'
                ],
                [
                    'start_date' => ['year'=>2025,'month'=>6,'day'=>9,'hour'=>14,'minute'=>35,'display_date'=>'09.06.2025., 14:35'],
                    'text' => [
                        'headline' => 'Pouka o pravima osumnjičenika — Andrija Glavaš',
                        'text' => '<p>Temeljem članka 208.a st. 1 i 2 Zakona o kaznenom postupku (ZKP), osumnjičenik Andrija Glavaš (rođen 14.10.1989. u Osijeku) poučen je o svojim pravima. Mjesto: Osijek. Vrijeme: 09.06.2025. u 14:35 sati. Policijski službenik: Aleksandar Sitarić. Predmet: 511-07-11-K-51/25.</p><ul><li>Pravo na branitelja</li><li>Pravo na tumačenje i prevođenje</li><li>Pravo da nije dužan iskazivati niti odgovarati na pitanja</li><li>Pravo da u svakom trenutku može napustiti policijske prostorije, osim u slučaju uhićenja</li></ul><details><summary>Pravne reference</summary><ul><li>Zakon o kaznenom postupku — čl. 208.a st. 1 i 2</li><li>Zakon o kaznenom postupku — čl. 8 (tumačenje i prevođenje)</li><li>Zakon o kaznenom postupku — čl. 108 (napuštanje policijskih prostorija)</li></ul></details>'
                    ],
                    'media' => [
                        'url' => asset('storage/docs/PoukaOPravimaOsumnjicenikaPpPrz74.pdf'),
                        'caption' => 'Antidatirana pouka', 'thumbnail'=>'', 'alt'=>'Antidatirana pouka', 'title'=>'Antidatirana pouka',
                        'link' => asset('storage/docs/PoukaOPravimaOsumnjicenikaPpPrz74.pdf'), 'link_target'=>'_blank'
                    ],
                    'group' => 'Krivotvoreni Timeline', 'display_date'=>'09.06.2025., 14:35', 'autolink'=>true, 'unique_id'=>'pouka-o-pravima-osumnjicenika-andrija-glavas-2025-06-09'
                ],
                [
                    'start_date' => ['year'=>2025,'month'=>6,'day'=>9,'hour'=>14,'minute'=>45],
                    'end_date'   => ['year'=>2025,'month'=>6,'day'=>9,'hour'=>15,'minute'=>12],
                    'display_date' => '9. 6. 2025., 14:45–15:12', 'group'=>'Krivotvoreni Timeline',
                    'text' => [
                        'headline' => 'Ispitivanje i AV snimanje osumnjičenika – PU osječko-baranjska',
                        'text' => '<p>U prostorijama PU osječko-baranjske započinje audio-video snimanje i ispitivanje osumnjičenika Andrije Glavaša. Prisotni: policijski službenik Aleksandar Simović, zapisničar Mate Surać, stručna osoba za tehničko snimanje Miroslav Pandurević i osumnjičenik. Osobe su upozorene na tajnost izvida i mogućnost korištenja snimke kao dokaza.</p><p>Osumnjičenik potvrđuje da razumije jezik postupka, prima pisanu pouku o pravima, te se najprije odriče prava na branitelja uz upozorenje o posljedicama, uz napomenu da to pravo može zatražiti u bilo kojem trenutku.</p><p>Tereti se za: neovlaštenu proizvodnju i promet drogama te nedozvoljeno posjedovanje, izradu i nabavljanje oružja i eksplozivnih tvari. Pretraga je pronašla više vrsta droga i oružje.</p><details><summary>Pravne reference</summary><ul><li>Zakon o kaznenom postupku – čl. 22 st. 1 (podatci pri prvom ispitivanju, pravna pouka)</li><li>Kazneni zakon – čl. 190 st. 2 (neovlaštena proizvodnja i promet drogama)</li><li>Kazneni zakon – čl. 31 st. 1 i 3 (nedozvoljeno posjedovanje, izrada i nabavljanje oružja i eksplozivnih tvari); u jednom navodu spomenut i čl. 301 st. 1 i 3</li></ul></details><details><summary>Oduzeti/pronađeni predmeti (sažetak)</summary><ul><li>Hašiš: ukupno 18 g</li><li>Konoplja/cvjetni vrhovi: 2,09 g; 2,35 g; 21,4 g (sadržaj THC > 0,3%)</li><li>MDMA: ~0,36 g</li><li>Amfetamin (speed): ~3 g</li><li>Psilocibinske gljive: ~6,9 g</li><li>Digitalne vage: 2 kom (0,01–100 g; 0,001–20 g) s tragovima biljne materije</li><li>Automatska puška M70 AB, 5 spremnika, ~309 kom streljiva 7,62 mm, bajunet, pribor za čišćenje</li></ul></details><p>Osumnjičenik u obrani navodi da marihuanu konzumira rekreativno (ne bavi se prodajom), a pušku je zadržao iz znatiželje nakon što je prijatelj preminuo. Na kraju se odriče prava na pregled/reprodukciju snimke i čitanje zapisnika; ispitivanje završava u 15:12.</p>'
                    ],
                    'media' => [
                        'url' => asset('storage/docs/iznudjenaIzjava.pdf'),
                        'caption' => 'Iznudjena izjava', 'thumbnail'=>'', 'alt'=>'Iznudjena izjava', 'title'=>'Iznudjena izjava',
                        'link' => asset('storage/docs/iznudjenaIzjava.pdf'), 'link_target'=>'_blank'
                    ],
                    'autolink'=> true,
                    'unique_id'=> 'iznudjeno-lazno-priznanje-2025-06-09'
                ],
            ],
        ];

        $dataBottom = [
            'title' => [ 'text' => [ 'headline' => 'Pravi Timeline' ] ],
            'events' => [
                [
                    'start_date' => ['year'=>2025,'month'=>6,'day'=>9,'hour'=>8,'minute'=>0,'display_date'=>'09.06.2025 08:00'],
                    'end_date'   => ['year'=>2025,'month'=>6,'day'=>9,'hour'=>10,'minute'=>0,'display_date'=>'09.06.2025 10:00'],
                    'text' => [
                        'headline' => 'Zahtjev policije za pretragu — Pp Prz-74/2025',
                        'text' => '<h1>Nulti Dokument</h1><p>Policijska uprava osječko-baranjska, Sektor kriminalističke policije, Služba organiziranog kriminaliteta, podnosi obrazloženi zahtjev Općinskom sudu u Osijeku za izdavanje naredbe za pretragu doma i drugih prostorija kojima se koristi Andrija Glavaš, Primorska 5, Osijek (klasa: NK-214-05/25-01/1155, urbroj: 511-07-11-25-2, 9. lipnja 2025.).</p><p>U zahtjevu se navodi osnovana sumnja na počinjenje prekršaja prema Zakonu o suzbijanju zlouporabe droga (npr. posjedovanje tvari poput "amfetamin-speed", THC i dr.).</p><details><summary>Pravne reference</summary><ul><li>Zakon o suzbijanju zlouporabe droga — čl. 64 st. 3 i/ili čl. 54 st. 3</li><li>Zakon o kaznenom postupku (NN 152/08, 76/09, 80/11, 121/11, 91/12, 143/12, 56/13, 145/13, 152/14, 70/17, 126/19, 130/20, 80/22, 36/24, 72/25)</li></ul></details>'
                    ],
                    'media' => [
                        'url' => asset('storage/docs/timeline_graphql.pdf'),
                        'caption' => 'Epredmet GraphQL podatci - nulti dokument', 'thumbnail'=>'', 'alt'=>'Epredmet GraphQL podatci - nulti dokument', 'title'=>'Epredmet GraphQL podatci - nulti dokument',
                        'link' => asset('storage/docs/timeline_graphql.pdf'), 'link_target'=>'_blank'
                    ],
                    'group' => 'Pravi Timeline', 'autolink'=>true, 'unique_id'=>'pp-prz-74-2025-zahtjev-pu', 'display_date'=>'09.06.2025'
                ],
                [
                    'start_date' => ['year'=>2025,'month'=>6,'day'=>9,'hour'=>10,'minute'=>10,'display_date'=>'09.06.2025 10:10'],
                    'end_date'   => ['year'=>2025,'month'=>6,'day'=>9,'hour'=>11,'minute'=>0,'display_date'=>'09.06.2025 11:00'],
                    'text' => [
                        'headline' => 'Pravi početak pretresa',
                        'text' => '<h1>Pretres</h1><p>Oni su zapravo došli oko 10:10, ja sam taman radio. Tj. bio sam na pozivu, čuo sam njihovo lupanje po vratima ali nisam mogao odmah reagirati zbog poziva u kojemu sam bio. U međuvremenu šaljem poruku ženi da vidim zna li ona tko lupa, tj. da li je nekoga/nešto naručivala.</p><p>Ona odgovara da nezna. Osim lupanja počeli su me zvati i na mobitel s skrivenog broja. Poziv završava i odlazim vidjeti tko je. Nakon što sam otvorio vrata, pretres je faktički već počeo a ja sam kidnapiran iz radne sobe i laptopa (kidnapiran s posla). Ovo se dogodilo oko cca 10:25 vidljivo na Facebook Messengeru, gdje vlada radijska tišina do 11:13.</p><details><summary>Pravne reference</summary><ul><li>Zakon o suzbijanju zlouporabe droga — čl. 64 st. 3 i/ili čl. 54 st. 3</li><li>Zakon o kaznenom postupku (NN 152/08, 76/09, 80/11, 121/11, 91/12, 143/12, 56/13, 145/13, 152/14, 70/17, 126/19, 130/20, 80/22, 36/24, 72/25)</li></ul></details>'
                    ],
                    'media' => [
                        'url' => asset('storage/docs/messenger.png'),
                        'caption' => 'Pretres prije početka pretresa', 'thumbnail'=>'', 'alt'=>'Pretres prije početka pretresa', 'title'=>'Pretres prije početka pretresa',
                        'link' => asset('storage/docs/messenger.png'), 'link_target'=>'_blank'
                    ],
                    'group' => 'Pravi Timeline', 'autolink'=>true, 'unique_id'=>'pp-prz-74-2025-pocetak-pretresa', 'display_date'=>'09.06.2025 10:10'
                ],
                [
                    'start_date' => ['year'=>2025,'month'=>6,'day'=>9,'hour'=>10,'minute'=>40,'display_date'=>'09.06.2025 10:40'],
                    'end_date'   => ['year'=>2025,'month'=>6,'day'=>9,'hour'=>10,'minute'=>55,'display_date'=>'09.06.2025 10:55'],
                    'text' => [
                        'headline' => 'Izbacivanje iz vlastitog stana te samostalni ulazak K-9 tima (vodić i pas), prije dolaska svjedoka',
                        'text' => '<h1>Neformalni početak</h1><p>Nakon otvaranja vrata, sami su se pustili unutra, te su obznanili kako imaju nalog za pretres. U stanu je bio i moj pas Božo - ovo ih je znatno omelo, nisu računali na još jednog psa. Tako da sam bio primoran (bez mog pristanka) izači van stana s njima i Božom, iz razloga što su oni doveli K-9 tim za traženje droge (daleko pretjerano ako se mene pita, radi količina za osobnu uporabu). Krenuli smo van, prvo njih 4 a zadnji ja i Božo. Kako sam izašao iz stana, primjertio sam da desno od vrata čovjek s psom. On se malo stisnio uza zid, kako bi interakcija pas VS pas prošla glatko. Te čim smo mi izašli on je brže bolje ušao u stan i zatvorio vrata. Od ovog trenutka on je faktički potpuno sam u stanu. Mi smo izašli ispred, te sam tada prvi puta čuo spomen na danas već famozne "pakete". Doduše tada je bio samo "paket" u jednini. Također valja istaknuti da oni čak ni tada nisu bili u stanju jasno i glasno artikulirati taj paket. Više su aludirali na naručivanje paketa, nego što su direktno pitali/komentirali. Sveukupno smo vani bili 10-ak minuta. Dovoljno za 2 cigare zapaliti. Usred toga, jedan od njih je išao u potragu za svjedocima. Prvo se popeo na kat iznad te je tamo zamolio susjedu da bude svjedok, ona se samo spustila na kat ispod te pretpostavljam ušla u stan u kojemu nije bilo mene ili supruge (tj. vlasnika stana). U stanu je vidjela nepoznatog muškarca s psom koji je bio potpuno samu u stanu cca 5-10 minuta. Drugi svjedok je nađen na kraju ulice, nakon susjede. Te je drugi svjedok prošao pored nas i vidio da stojimo vani. Ovo sve svjedoci mogu i potvrditi. Kada je K-9 bio gotovo, nazvao je jednog od policajaca na mobitel i javio mu da \'ima svega\'. Pretpostavljam da je koristeči psa pronašao veliku teglu u kojoj je bilo 20g marihuane u jednoj vrečici i 18g hašiša u drugoj. To je vrlo vjerovatno, i to zbog korištenja psa te snažnog mirisa već spomenuthi substanci. Kada sam se ja vratio svjedoci su već bili unutra.</p><details></details>'
                    ],
                    'media' => [
                        'url' => asset('storage/docs/NaknadnaPouka.pdf'),
                        'caption' => 'Odricanje od branitelja bez vremena, potpisano u policijskoj postaji a piše Primorska 5',
                        'thumbnail'=>'', 'alt'=>'Odricanje od branitelja bez vremena, potpisano u policijskoj postaji a piše Primorska 5', 'title'=>'Odricanje od branitelja bez vremena, potpisano u policijskoj postaji a piše Primorska 5',
                        'link' => asset('storage/docs/NaknadnaPouka.pdf'), 'link_target'=>'_blank'
                    ],
                    'group' => 'Pravi Timeline', 'autolink'=>true, 'unique_id'=>'pp-prz-74-2025-k-9-pas-mater-sam-u-stanu', 'display_date'=>'09.06.2025 10:40'
                ],
                [
                    'start_date' => ['year'=>2025,'month'=>6,'day'=>9,'hour'=>11,'minute'=>0,'display_date'=>'09.06.2025 11:00'],
                    'end_date'   => ['year'=>2025,'month'=>6,'day'=>9,'hour'=>13,'minute'=>45,'display_date'=>'09.06.2025 13:45'],
                    'text' => [
                        'headline' => 'Pretres sa svim detaljima (čak i onima koje žele sakriti)',
                        'text' => '<h1>Neformalni početak</h1><p>Nakon otvaranja vrata, sami su se pustili unutra, te su obznanili kako imaju nalog za pretres. U stanu je bio i moj pas Božo - ovo ih je znatno omelo, nisu računali na još jednog psa. Tako da sam bio primoran (bez mog pristanka) izači van stana s njima i Božom, iz razloga što su oni doveli K-9 tim za traženje droge (daleko pretjerano ako se mene pita, radi količina za osobnu uporabu). Krenuli smo van, prvo njih 4 a zadnji ja i Božo. Kako sam izašao iz stana, primjertio sam da desno od vrata čovjek s psom. On se malo stisnio uza zid, kako bi interakcija pas VS pas prošla glatko. Te čim smo mi izašli on je brže bolje ušao u stan i zatvorio vrata. Od ovog trenutka on je faktički potpuno sam u stanu. Mi smo izašli ispred, te sam tada prvi puta čuo spomen na danas već famozne "pakete". Doduše tada je bio samo "paket" u jednini. Također valja istaknuti da oni čak ni tada nisu bili u stanju jasno i glasno artikulirati taj paket. Više su aludirali na naručivanje paketa, nego što su direktno pitali/komentirali. Sveukupno smo vani bili 10-ak minuta. Dovoljno za 2 cigare zapaliti. Usred toga, jedan od njih je išao u potragu za svjedocima. Prvo se popeo na kat iznad te je tamo zamolio susjedu da bude svjedok, ona se samo spustila na kat ispod te pretpostavljam ušla u stan u kojemu nije bilo mene ili supruge (tj. vlasnika stana). U stanu je vidjela nepoznatog muškarca s psom koji je bio potpuno samu u stanu cca 5-10 minuta. Drugi svjedok je nađen na kraju ulice, nakon susjede. Te je drugi svjedok prošao pored nas i vidio da stojimo vani. Ovo sve svjedoci mogu i potvrditi. Kada je K-9 bio gotovo, nazvao je jednog od policajaca na mobitel i javio mu da \'ima svega\'. Pretpostavljam da je koristeči psa pronašao veliku teglu u kojoj je bilo 20g marihuane u jednoj vrečici i 18g hašiša u drugoj. To je vrlo vjerovatno, i to zbog korištenja psa te snažnog mirisa već spomenuthi substanci. Kada sam se ja vratio svjedoci su već bili unutra.</p><details></details>'
                    ],
                    'media' => [
                        'url' => asset('storage/docs/NaknadnaPouka.pdf'),
                        'caption' => 'Odricanje od branitelja bez vremena, potpisano u policijskoj postaji a piše Primorska 5',
                        'thumbnail'=>'', 'alt'=>'Odricanje od branitelja bez vremena, potpisano u policijskoj postaji a piše Primorska 5', 'title'=>'Odricanje od branitelja bez vremena, potpisano u policijskoj postaji a piše Primorska 5',
                        'link' => asset('storage/docs/NaknadnaPouka.pdf'), 'link_target'=>'_blank'
                    ],
                    'group' => 'Pravi Timeline', 'autolink'=>true, 'unique_id'=>'pp-prz-74-2025-k-9-pas-mater-sam-u-stanu', 'display_date'=>'09.06.2025 10:40'
                ],
            ],
        ];

        $this->dataTopJs = json_encode($dataTop, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $this->dataBottomJs = json_encode($dataBottom, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }

    public function render()
    {
        return view('livewire.comparative-timeline-page');
    }
}

