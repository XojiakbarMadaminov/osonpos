@if (session('pos_login_required'))
    <div class="mb-6 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm text-amber-700 dark:text-amber-300" role="status">
        POS’dan foydalanish uchun avval tizimga kiring.
    </div>
@endif

@if (session('pos_logout_success'))
    <div class="mb-6 rounded-xl border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm text-emerald-700 dark:text-emerald-300" role="status">
        Akkauntdan chiqildi. Keyingi kassir o‘z akkaunti bilan kirsin.
    </div>
@endif
