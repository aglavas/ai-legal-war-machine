<div class="w-full">
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="px-4 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="rounded-xl bg-sky-100 p-2 text-sky-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M4 6h16v2H4zM4 11h10v2H4zM4 16h16v2H4z"/></svg>
                </div>
                <div>
                    <div class="text-base font-semibold">e‑Predmet – GraphQL</div>
                    <div class="text-xs text-slate-500">Lookup court case and render GraphQL response</div>
                </div>
            </div>
            @if ($tookMs)
                <div class="text-xs text-slate-500">{{ $tookMs }} ms</div>
            @endif
        </div>

        <div class="px-4 py-4">
            <form wire:submit.prevent="fetch" class="grid gap-3 md:grid-cols-12 items-end">
                <div class="md:col-span-3">
                    <label for="sud" class="block text-xs font-medium text-slate-600">Sud ID</label>
                    <input id="sud" type="text" wire:model.defer="sud" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-sky-500" placeholder="5107">
                    @error('sud')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="md:col-span-6">
                    <label for="oznakaBroj" class="block text-xs font-medium text-slate-600">Oznaka/Broj</label>
                    <input id="oznakaBroj" type="text" wire:model.defer="oznakaBroj" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-sky-500" placeholder="Pp Prz-74/2025">
                    @error('oznakaBroj')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="md:col-span-3 flex gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-white hover:bg-sky-700 disabled:opacity-50" wire:loading.attr="disabled">
                        <svg wire:loading class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg"><circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4"></circle><path class="opacity-75" d="M4 12a8 8 0 018-8" stroke-width="4" stroke-linecap="round"></path></svg>
                        <span>Fetch</span>
                    </button>
                    <button type="button" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-slate-700 hover:bg-slate-50" wire:click="$set('data', null)">Clear</button>
                </div>
            </form>

            @if ($error)
                <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-rose-700 text-sm">
                    <div class="font-semibold mb-1">Error</div>
                    <div>{{ $error }}</div>
                    <div class="mt-2 text-xs text-rose-700/80">Tip: ensure GRAPHQL_ENDPOINT and GRAPHQL_TOKEN are set, or that fallback hints match actual API.</div>
                </div>
            @endif

            <div class="mt-4" wire:loading.delay>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-slate-600 text-sm">Loading…</div>
            </div>

            @if ($data)
                <div class="mt-4 grid gap-4">
                    <!-- Summary grid -->
                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="text-xs uppercase text-slate-500">Oznaka/Broj</div>
                            <div class="font-semibold">{{ $data['oznakaBroj'] ?? '—' }}</div>
                        </div>
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="text-xs uppercase text-slate-500">Upisnik</div>
                            <div class="font-semibold">{{ ($data['upisnikNaziv'] ?? null) ? ($data['upisnikNaziv'] . ' (' . ($data['upisnikOznaka'] ?? '—') . ')') : '—' }}</div>
                        </div>
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="text-xs uppercase text-slate-500">Vrsta predmeta</div>
                            <div class="font-semibold">{{ $data['vrstaPredmeta'] ?? '—' }}</div>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="text-xs uppercase text-slate-500">Vrsta odluke</div>
                            <div class="font-medium">{{ $data['vrstaOdluke'] ?? '—' }}</div>
                        </div>
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="text-xs uppercase text-slate-500">Spis status</div>
                            <div class="text-sm">Visi sud: <span class="font-medium">{{ ($data['spisNaVisemSudu'] ?? false) ? 'da' : 'ne' }}</span> · Izvan suda: <span class="font-medium">{{ ($data['spisIzvanSuda'] ?? false) ? 'da' : 'ne' }}</span></div>
                        </div>
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="text-xs uppercase text-slate-500">Last update</div>
                            @if(!empty($data['lastUpdateTime'] ?? null))
                                <div class="text-sm"><span class="font-medium">{{ \Carbon\Carbon::parse($data['lastUpdateTime'])->format('Y-m-d H:i:s') }}</span></div>
                            @else
                                <div class="text-sm"><span class="font-medium">-</span></div>
                            @endif
                        </div>
                    </div>

                    <!-- Dates panel -->
                    <div class="rounded-2xl border border-slate-200">
                        <div class="px-4 py-2 border-b border-slate-100 font-semibold">Ključni datumi</div>
                        <div class="p-4 grid gap-3 md:grid-cols-3 text-sm">
                            @foreach ($dateLabels as $k => $label)
                                <div class="rounded-lg border border-slate-200 p-3">
                                    <div class="text-xs uppercase text-slate-500">{{ $label }}</div>
                                    @if (empty($data[$k] ?? null))
                                        <div class="font-medium">—</div>
                                    @else
                                        <div class="font-medium">{{ Carbon\Carbon::parse($data[$k])->format('Y-m-d H:i:s') }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if (!empty($data['rocista'] ?? []))
                        <div class="rounded-2xl border border-slate-200 overflow-x-auto">
                            <div class="px-4 py-2 border-b border-slate-100 font-semibold">Ročišta</div>
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50">
                                <tr class="text-left text-slate-600">
                                    <th class="px-4 py-2">Vrsta</th>
                                    <th class="px-4 py-2">St. početak</th>
                                    <th class="px-4 py-2">St. završetak</th>
                                    <th class="px-4 py-2">Pl. početak</th>
                                    <th class="px-4 py-2">Pl. završetak</th>
                                    <th class="px-4 py-2">Soba</th>
                                    <th class="px-4 py-2">Odgoda</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach (($data['rocista'] ?? []) as $r)
                                    <tr class="border-t border-slate-100">
                                        <td class="px-4 py-2">{{ $r['vrstaRadnje'] ?? '—' }}</td>
                                        <td class="px-4 py-2">{{ $r['stPocetak'] ?? '—' }}</td>
                                        <td class="px-4 py-2">{{ $r['stZavrsetak'] ?? '—' }}</td>
                                        <td class="px-4 py-2">{{ $r['plPocetak'] ?? '—' }}</td>
                                        <td class="px-4 py-2">{{ $r['plZavrsetak'] ?? '—' }}</td>
                                        <td class="px-4 py-2">{{ ($r['sobanaziv'] ?? '—') . (($r['sobaoznaka'] ?? null) ? ' (' . $r['sobaoznaka'] . ')' : '') }}</td>
                                        <td class="px-4 py-2">{{ ($r['odgoda'] ?? false) ? 'da' : 'ne' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif


                    <div class="grid gap-4 md:grid-cols-2">
                        @if (!empty($data['povezaniPredmeti'] ?? []))
                            <div class="rounded-2xl border border-slate-200">
                                <div class="px-4 py-2 border-b border-slate-100 font-semibold">Povezani predmeti</div>
                                <ul class="divide-y divide-slate-100 text-sm">
                                    @foreach (($data['povezaniPredmeti'] ?? []) as $pp)
                                        <li class="px-4 py-2">
                                            <div class="font-medium">{{ $pp['vezaniOznakaBroj'] ?? ($pp['vezaniOznaka'] ?? '—') }}</div>
                                            <div class="text-slate-600">{{ $pp['opis'] ?? '—' }}</div>
                                            <div class="text-slate-500 text-xs">{{ $pp['datumVeze'] ?? '—' }} · {{ $pp['tip'] ?? '' }}</div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if (!empty($data['vjecnici'] ?? []))
                            <div class="rounded-2xl border border-slate-200">
                                <div class="px-4 py-2 border-b border-slate-100 font-semibold">Vijećnici</div>
                                <ul class="divide-y divide-slate-100 text-sm">
                                    @foreach (($data['vjecnici'] ?? []) as $v)
                                        <li class="px-4 py-2 flex items-center justify-between">
                                            <div class="font-medium">{{ $v['ime'] ?? '—' }}</div>
                                            <div class="text-slate-600">{{ $v['vrsta'] ?? '—' }}</div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    @if (!empty($data['stranke'] ?? []))
                        <div class="rounded-2xl border border-slate-200">
                            <div class="px-4 py-2 border-b border-slate-100 font-semibold">Stranke</div>
                            <ul class="divide-y divide-slate-100 text-sm">
                                @foreach (($data['stranke'] ?? []) as $s)
                                    <li class="px-4 py-2 flex items-center justify-between">
                                        <div class="font-medium">{{ $s['naziv'] ?? '—' }}</div>
                                        <div class="text-slate-600">{{ $s['nazivuloge'] ?? '—' }}</div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
