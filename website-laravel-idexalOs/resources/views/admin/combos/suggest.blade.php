@extends('layouts.admin')

@section('title', 'Suggest Combos')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-semibold text-paper">Suggested Hybrid Models</h2>
        <a href="{{ route('admin.combos.index') }}" class="btn btn-secondary">Back to All Combos</a>
    </div>

    @if($suggestions->isEmpty())
        <div class="alert alert-info">
            <p>No automatic combo suggestions available yet.</p>
            <p class="text-sm text-ink-400 mt-2">Add more providers and models across different use cases (coding, vision, search) to get intelligent recommendations.</p>
        </div>
    @else
        <div class="alert alert-success mb-6">
            <p><strong>{{ $suggestions->count() }}</strong> smart combos detected!</p>
            <p class="text-sm text-ink-400 mt-1">Each combo groups similar models from multiple providers for optimal performance through round-robin rotation or role-based routing.</p>
        </div>

        <!-- Advanced Combo Creation Form -->
        <div class="bg-dark p-6 rounded-lg border border-paper/10">
            <h3 class="text-xl font-semibold text-paper mb-4">Create Custom Combo Manually</h3>
            
            <form action="{{ route('admin.combos.store.advanced') }}" method="POST" id="createComboForm">
                @csrf
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-ink-500 mb-2">Combo Name *</label>
                        <input type="text" 
                               name="name" 
                               id="name"
                               placeholder="e.g., IDEXAL-CODING"
                               required
                               class="input w-full @error('name') border-error @enderror">
                        @error('name')
                            <span class="text-error text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label for="slug" class="block text-sm font-medium text-ink-500 mb-2">ID (optional)</label>
                        <input type="text" 
                               name="slug" 
                               id="slug"
                               placeholder="idexal-coding"
                               class="input w-full">
                        <small class="text-ink-400">Auto-generated from name if left empty</small>
                    </div>

                    <div class="md:col-span-2">
                        <label for="description" class="block text-sm font-medium text-ink-500 mb-2">Description *</label>
                        <textarea name="description" 
                                  id="description"
                                  rows="3"
                                  required
                                  class="input w-full @error('description') border-error @enderror"
                                  placeholder="Describe what this hybrid model does, e.g.: A coding-focused combo that uses Qwen for code generation, DeepSeek for debugging, and Google for documentation search..."></textarea>
                        @error('description')
                            <span class="text-error text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label for="strategy" class="block text-sm font-medium text-ink-500 mb-2">Strategy *</label>
                        <select name="strategy" id="strategy" required class="input w-full">
                            <option value="round_robin">Round Robin - Rotate on every request</option>
                            <option value="role">Role-Based - Route by task type</option>
                            <option value="failover">Failover - Priority-based fallback</option>
                        </select>
                        @error('strategy')
                            <span class="text-error text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <!-- Member Selection -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-ink-500 mb-2">Select Models *</label>
                    <select id="memberSelector" 
                            name="member_ids[]" 
                            multiple
                            class="input w-full"
                            style="min-height: 200px;">
                        @foreach(config('services.mock.providers', []) as $providerName)
                            <optgroup label="{{ $providerName }}">
                                <option value="model1">Model from {{ $providerName }}</option>
                                <option value="model2">Another Model from {{ $providerName }}</option>
                            </optgroup>
                        @endforeach
                    </select>
                    <small class="text-ink-400">Hold Ctrl/Cmd to select multiple models</small>
                </div>

                <button type="submit" class="btn btn-primary mt-4">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Create Advanced Combo
                </button>
            </form>
        </div>

        <!-- Suggestions List -->
        <div class="mt-8 space-y-4">
            <h3 class="text-xl font-semibold text-paper">Automatic Suggestions</h3>
            <p class="text-ink-400">These combos were automatically detected based on similar models across providers.</p>

            @foreach($suggestions as $suggestion)
                <div class="bg-dark p-6 rounded-lg border border-paper/10 hover:border-paper/20 transition-colors">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <h4 class="text-lg font-semibold text-paper">{{ $suggestion['display_name'] }} Combo</h4>
                                <span class="px-2 py-1 text-xs font-medium bg-blue/20 text-blue rounded">
                                    {{ $suggestion['member_count'] }} members
                                </span>
                            </div>

                            <p class="text-sm text-ink-400 mb-3">{{ $suggestion['description'] }}</p>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                <div>
                                    <span class="text-xs text-ink-500">Strategy</span>
                                    <div class="font-medium text-ink-300 capitalize">
                                        {{ str_replace('_', ' ', $suggestion['strategy']) }}
                                    </div>
                                </div>
                                <div>
                                    <span class="text-xs text-ink-500">Capabilities</span>
                                    <div class="flex flex-wrap gap-1 mt-1">
                                        @foreach(array_keys($suggestion['capabilities']) as $capability)
                                            <span class="px-2 py-0.5 text-xs bg-paper/5 text-paper/70 rounded">
                                                {{ $capability }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <span class="text-xs text-ink-500 block mb-1">Member Models:</span>
                                <div class="space-y-2">
                                    @foreach($suggestion['members'] as $member)
                                        <div class="flex items-center justify-between bg-paper/5 p-2 rounded">
                                            <div class="flex items-center gap-3">
                                                <input type="checkbox" 
                                                       name="selected_members[]" 
                                                       value="{{ $member['id'] }}" 
                                                       class="rounded border-paper/20">
                                                <div>
                                                    <div class="text-sm font-medium text-ink-300">{{ $member['name'] }}</div>
                                                    <div class="text-xs text-ink-500">
                                                        {{ $member['provider'] }} • Role: {{ ucfirst($member['role']) }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-xs text-ink-400">Priority: {{ $member['priority'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <form action="{{ route('admin.combos.store') }}" method="POST" class="inline">
                                @csrf
                                <input type="hidden" name="name" value="{{ $suggestion['display_name'] }} Combo">
                                <input type="hidden" name="description" value="{{ $suggestion['description'] }}">
                                <input type="hidden" name="strategy" value="{{ $suggestion['strategy'] }}">
                                @foreach($suggestion['members'] as $member)
                                    <input type="hidden" name="member_ids[]" value="{{ $member['id'] }}">
                                @endforeach
                                <button type="submit" class="btn btn-primary btn-sm">
                                    Use This Suggestion
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<script>
// Enable better multi-select behavior
document.addEventListener('DOMContentLoaded', function() {
    const selector = document.getElementById('memberSelector');
    
    // Add keyboard shortcuts
    selector.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === ' ') {
            e.preventDefault();
            const options = Array.from(this.options);
            const selectedIndex = this.selectedIndex;
            if (selectedIndex >= 0 && selectedIndex < options.length) {
                options[selectedIndex].selected = !options[selectedIndex].selected;
            }
        }
    });
});
</script>
@endsection