<div class="mx-auto w-full max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-3 rounded-xl border border-warning-300 bg-warning-50 p-4 text-sm text-warning-950 shadow-sm dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-100 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="font-semibold">Platforma boshqaruv rejimi</p>
            <p>{{ $organization->name }} tashkilotini boshqaryapsiz.</p>
        </div>

        <form method="POST" action="{{ route('platform.organization-access.leave') }}">
            @csrf
            <x-filament::button type="submit" color="warning" outlined>
                Platformaga qaytish
            </x-filament::button>
        </form>
    </div>
</div>
