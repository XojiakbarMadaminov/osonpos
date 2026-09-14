@if ($stores->isNotEmpty())
    <div class="op-admin-context-switcher">
        <form method="POST" action="{{ route('admin.context.switch') }}">
            @csrf

            <label for="admin-active-store">Faol filial</label>
            <select
                id="admin-active-store"
                name="store_id"
                aria-label="Faol filial"
                onchange="this.disabled = true; this.form.submit()"
            >
                @foreach ($stores as $store)
                    <option value="{{ $store->getKey() }}" @selected($store->getKey() === $currentStoreId)>
                        {{ $store->organization->name }} — {{ $store->name }}
                    </option>
                @endforeach
            </select>

            @error('store_id')
                <p>{{ $message }}</p>
            @enderror
        </form>
    </div>
@endif
