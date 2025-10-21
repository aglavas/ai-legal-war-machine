@extends('vizra-adk::layouts.app')

@section('content')
    @livewire('vizra-adk-dashboard')

    <!-- Odluke Quick Link Card -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="bg-gray-900/50 backdrop-blur-xl rounded-2xl p-8 border border-gray-800/50">
            <h2 class="text-2xl font-bold text-white text-center mb-8">Odluke Quick Link</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <a href="{{ route('vizra.chat', ['agent' => 'odluke_agent']) }}" class="group bg-gray-800/50 hover:bg-gray-800/70 rounded-xl p-6 transition-all duration-200 border border-gray-700/50 hover:border-emerald-500/50">
                    <div class="w-12 h-12 bg-emerald-500/20 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2">Open Odluke Agent</h3>
                    <p class="text-gray-400 text-sm">Jump straight into chat with the Odluke agent to search and download court decisions.</p>
                    <div class="mt-4 flex items-center text-emerald-400 text-sm font-medium">
                        Start with Odluke
                        <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </a>
            </div>
        </div>
    </div>
@endsection

