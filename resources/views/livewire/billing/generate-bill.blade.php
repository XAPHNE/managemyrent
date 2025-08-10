<div>
    <form wire:submit.prevent="submit" class="space-y-4">
        <select wire:model="tenancy_id" required>
            <option value="">Select tenant/property</option>
            @foreach($tenancies as $t)
                <option value="{{ $t->id }}">
                    {{ $t->tenant->name }} — {{ $t->property->name }}
                </option>
            @endforeach
        </select>

        <input type="number" step="0.01" min="0" wire:model="present_units" placeholder="Present meter units" required />

        <button type="submit">Generate Bill</button>
    </form>

    @if (session('success'))
        <div>{{ session('success') }}</div>
    @endif
</div>
