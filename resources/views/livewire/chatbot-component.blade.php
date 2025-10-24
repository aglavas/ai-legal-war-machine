<div class="h-screen flex flex-col bg-slate-50">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-sky-500 via-indigo-500 to-fuchsia-500 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-white/20 p-2 text-white backdrop-blur-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12c0 5.52 4.48 10 10 10s10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-white">AI Legal Assistant</h1>
                        <p class="text-sm text-white/80">Chat with AI for legal research and case analysis</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button wire:click="newConversation" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-white/20 text-white hover:bg-white/30 backdrop-blur-sm transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                        </svg>
                        New Chat
                    </button>
                    <a href="/dashboard" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-white/20 text-white hover:bg-white/30 backdrop-blur-sm transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3 3h8v8H3zM13 3h8v5h-8zM13 10h8v11h-8zM3 13h8v8H3z"/>
                        </svg>
                        Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Content --}}
    <div class="flex-1 flex overflow-hidden">
        {{-- Sidebar - Conversations List --}}
        <div class="w-80 bg-white border-r border-slate-200 overflow-y-auto">
            <div class="p-4">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Select Agent</label>
                    <select wire:model.live="agentType" class="w-full rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 text-sm">
                        @foreach($agentTypes as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <h3 class="text-sm font-semibold text-slate-700 mb-2">Recent Conversations</h3>
                </div>

                <div class="space-y-2">
                    @forelse($conversations as $conv)
                        <div class="group relative rounded-lg border {{ $conversation === $conv['uuid'] ? 'bg-sky-50 border-sky-300' : 'bg-white border-slate-200 hover:border-slate-300' }} transition cursor-pointer">
                            <button wire:click="loadConversation('{{ $conv['uuid'] }}')" wire:loading.attr="disabled" wire:loading.class="opacity-50" class="w-full text-left p-3 relative">
                                <div wire:loading wire:target="loadConversation('{{ $conv['uuid'] }}')" class="absolute inset-0 flex items-center justify-center bg-white/50 rounded-lg">
                                    <svg class="animate-spin h-5 w-5 text-sky-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                                <div class="font-medium text-sm text-slate-900 truncate">
                                    {{ $conv['title'] }}
                                </div>
                                <div class="text-xs text-slate-500 mt-1">
                                    {{ $conv['message_count'] }} messages • {{ $conv['last_message_at'] }}
                                </div>
                                @if($conv['agent_type'])
                                    <div class="mt-1">
                                        <span class="inline-block px-2 py-0.5 text-xs rounded-full bg-slate-100 text-slate-600">
                                            {{ $agentTypes[$conv['agent_type']] ?? $conv['agent_type'] }}
                                        </span>
                                    </div>
                                @endif
                            </button>
                            <button wire:click="deleteConversation('{{ $conv['uuid'] }}')" wire:confirm="Delete this conversation?" class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 p-1 rounded text-slate-400 hover:text-red-600 hover:bg-red-50 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                </svg>
                            </button>
                        </div>
                    @empty
                        <div class="text-center py-8 text-slate-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto opacity-30 mb-2" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                            </svg>
                            <p class="text-sm">No conversations yet</p>
                            <p class="text-xs mt-1">Start a new chat to begin</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Chat Area --}}
        <div class="flex-1 flex flex-col">
            {{-- Messages Container --}}
            <div class="flex-1 overflow-y-auto p-6 space-y-4" id="messages-container">
                @if(empty($messages))
                    {{-- Welcome Screen --}}
                    <div class="flex items-center justify-center h-full">
                        <div class="text-center max-w-2xl">
                            <div class="rounded-full bg-gradient-to-br from-sky-100 to-indigo-100 p-6 inline-block mb-4">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-sky-600" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C6.48 2 2 6.48 2 12c0 5.52 4.48 10 10 10s10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                                </svg>
                            </div>
                            <h2 class="text-2xl font-bold text-slate-900 mb-2">AI Legal Assistant</h2>
                            <p class="text-slate-600 mb-6">Ask me anything about Croatian law, court decisions, or legal cases. I'm here to help with your legal research.</p>
                            <div class="grid grid-cols-2 gap-3 text-left">
                                <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
                                    <div class="font-medium text-slate-900 mb-1">Legal Research</div>
                                    <div class="text-xs text-slate-600">Get information about Croatian laws and regulations</div>
                                </div>
                                <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
                                    <div class="font-medium text-slate-900 mb-1">Court Decisions</div>
                                    <div class="text-xs text-slate-600">Analyze court rulings and precedents</div>
                                </div>
                                <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
                                    <div class="font-medium text-slate-900 mb-1">Case Analysis</div>
                                    <div class="text-xs text-slate-600">Get insights on legal cases and strategies</div>
                                </div>
                                <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
                                    <div class="font-medium text-slate-900 mb-1">General Help</div>
                                    <div class="text-xs text-slate-600">Ask any legal question you have</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    {{-- Messages --}}
                    @foreach($messages as $message)
                        <div class="flex {{ $message['is_user'] ? 'justify-end' : 'justify-start' }}">
                            <div class="flex gap-3 max-w-3xl {{ $message['is_user'] ? 'flex-row-reverse' : '' }}">
                                {{-- Avatar --}}
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center {{ $message['is_user'] ? 'bg-sky-600' : 'bg-slate-700' }}">
                                        @if($message['is_user'])
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                            </svg>
                                        @else
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 2C6.48 2 2 6.48 2 12c0 5.52 4.48 10 10 10s10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                                            </svg>
                                        @endif
                                    </div>
                                </div>

                                {{-- Message Content --}}
                                <div class="flex-1">
                                    <div class="rounded-2xl px-4 py-3 {{ $message['is_user'] ? 'bg-sky-600 text-white' : 'bg-white border border-slate-200 text-slate-900' }}">
                                        @if($message['is_user'])
                                            <div class="text-sm whitespace-pre-wrap break-words">{{ $message['content'] }}</div>
                                        @else
                                            <div class="text-sm prose prose-sm max-w-none prose-slate prose-headings:font-semibold prose-a:text-sky-600 prose-code:bg-slate-100 prose-code:px-1 prose-code:py-0.5 prose-code:rounded prose-code:text-slate-900 prose-pre:bg-slate-900 prose-pre:text-slate-100">
                                                {!! $message['content_html'] ?? e($message['content']) !!}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="text-xs text-slate-500 mt-1 {{ $message['is_user'] ? 'text-right' : '' }}" title="{{ $message['full_timestamp'] ?? '' }}">
                                        {{ $message['created_at'] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    {{-- Loading Indicator --}}
                    @if($isLoading)
                        <div class="flex justify-start">
                            <div class="flex gap-3 max-w-3xl">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center bg-slate-700">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 2C6.48 2 2 6.48 2 12c0 5.52 4.48 10 10 10s10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <div class="rounded-2xl px-4 py-3 bg-white border border-slate-200">
                                        <div class="flex gap-1">
                                            <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                                            <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                                            <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            </div>

            {{-- Error Message --}}
            @if($error)
                <div class="px-6 py-3 bg-red-50 border-t border-red-200">
                    <div class="flex items-center gap-2 text-red-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                        <span class="text-sm">{{ $error }}</span>
                        <button wire:click="$set('error', null)" class="ml-auto text-red-600 hover:text-red-800">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            @endif

            {{-- Input Area --}}
            <div class="border-t border-slate-200 bg-white p-4">
                <form wire:submit.prevent="sendMessage" class="flex gap-3">
                    <div class="flex-1">
                        <textarea
                            wire:model="currentInput"
                            placeholder="Type your message..."
                            rows="1"
                            class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500 resize-none"
                            style="min-height: 44px; max-height: 200px"
                            @keydown.enter.prevent="if (!event.shiftKey) { $wire.sendMessage(); }"
                            @input="this.style.height = 'auto'; this.style.height = Math.min(this.scrollHeight, 200) + 'px'"
                        ></textarea>
                        @error('currentInput')
                            <span class="text-xs text-red-600 mt-1">{{ $message }}</span>
                        @enderror
                    </div>
                    <button
                        type="submit"
                        @disabled($isLoading || empty(trim($currentInput)))
                        class="px-6 py-2 bg-sky-600 text-white rounded-xl hover:bg-sky-700 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center gap-2"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                        <span class="font-medium">Send</span>
                    </button>
                </form>
                <div class="mt-2 text-xs text-slate-500 text-center">
                    Press Enter to send, Shift+Enter for new line
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Auto-scroll to bottom when new messages arrive
    function scrollToBottom() {
        const container = document.getElementById('messages-container');
        if (container) {
            // Use requestAnimationFrame for smooth scrolling
            requestAnimationFrame(() => {
                container.scrollTo({
                    top: container.scrollHeight,
                    behavior: 'smooth'
                });
            });
        }
    }

    // Livewire hooks for auto-scroll
    document.addEventListener('livewire:init', () => {
        // Scroll when component updates
        Livewire.hook('morph.updated', ({ el, component }) => {
            if (el && el.id === 'messages-container') {
                scrollToBottom();
            }
        });

        // Scroll after message is sent
        Livewire.hook('message.processed', (message, component) => {
            setTimeout(scrollToBottom, 100);
        });
    });

    // Initial scroll to bottom on page load
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(scrollToBottom, 200);
    });

    // Also scroll when Livewire finishes loading
    document.addEventListener('livewire:navigated', () => {
        setTimeout(scrollToBottom, 200);
    });
</script>
@endpush
