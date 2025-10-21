@section('content')
    @php($chatUrl = route('vizra.chat', ['agent' => 'odluke_agent']))
    {{-- Reuse the package dashboard via Livewire and append our custom card via a small section below --}}
    @livewire('vizra-adk-dashboard')

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid md:grid-cols-3 gap-6">
            <a href="{{ $chatUrl }}" class="group bg-gray-800/50 hover:bg-gray-800/70 rounded-xl p-6 transition-all duration-200 border border-gray-700/50 hover:border-cyan-500/50">
                <div class="w-12 h-12 bg-cyan-500/20 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <svg class="w-6 h-6 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h8M8 14h6M5 20l2-2h10a2 2 0 002-2V6a2 2 0 00-2-2H7a2 2 0 00-2 2v14z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">Odluke RH Agent</h3>
                <p class="text-gray-400 text-sm">Brzo otvori chat s agentom za pretragu i preuzimanje sudskih odluka</p>
                <div class="mt-4 flex items-center text-cyan-400 text-sm font-medium">
                    Otvori chat
                    <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
            </a>
        </div>
    </div>
@endsection

