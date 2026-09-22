<!doctype html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <title>{{ $store->name }} — Menyu</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, -apple-system, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-width: 280px; background: #fff; color: #20242c; }
        .site-header { padding: 1.15rem clamp(.75rem, 3vw, 2.5rem); }
        .site-header h1 { margin: 0; font-size: clamp(1.35rem, 3vw, 2rem); line-height: 1.2; }
        .site-header p { margin: .25rem 0 0; color: #717684; font-size: .9rem; }
        .category-nav { position: sticky; z-index: 10; top: 0; background: #fff; border-block: 1px solid #eceef1; }
        .category-tabs { display: flex; gap: .25rem; overflow-x: auto; overscroll-behavior-inline: contain; scrollbar-width: none; padding: .55rem clamp(.75rem, 3vw, 2.5rem); }
        .category-tabs::-webkit-scrollbar { display: none; }
        .category-tab { flex: none; border: 0; border-radius: 999px; background: transparent; color: #5c626d; padding: .65rem .9rem; font: inherit; font-size: .9rem; white-space: nowrap; cursor: pointer; }
        .category-tab[aria-selected="true"] { background: #f0f1f4; color: #20242c; font-weight: 650; }
        .category-tab:focus-visible { outline: 2px solid #7468ec; outline-offset: 2px; }
        main { padding: 1rem clamp(.55rem, 3vw, 2.5rem) 3rem; }
        .category-panel { margin: 0 0 2rem; }
        .category-panel h2 { margin: .35rem .2rem 1rem; font-size: clamp(1.15rem, 2.5vw, 1.5rem); }
        .menu-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .65rem; }
        .product-card { min-width: 0; border-radius: 1rem; background: #f5f5f7; padding: .5rem; }
        .product-image { display: grid; place-items: center; width: 100%; aspect-ratio: 1; overflow: hidden; border-radius: .8rem; background: #fff; }
        .product-image img { display: block; width: 100%; height: 100%; object-fit: contain; }
        .product-image span { color: #8a8e99; font-size: .8rem; text-align: center; padding: .5rem; }
        .product-copy { min-width: 0; padding: .7rem .1rem .45rem; }
        .product-price { margin: 0 0 .4rem; color: #7468ec; font-weight: 750; font-size: clamp(.9rem, 2.5vw, 1.12rem); font-variant-numeric: tabular-nums; }
        .product-name { margin: 0; font-size: .92rem; font-weight: 600; line-height: 1.35; overflow-wrap: anywhere; }
        .product-description { margin: .35rem 0 0; color: #707582; font-size: .8rem; line-height: 1.35; white-space: pre-line; overflow-wrap: anywhere; }
        .empty { margin: 2rem auto; text-align: center; color: #707582; }
        @media (min-width: 600px) { .menu-grid { grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 1rem; } .product-card { padding: .65rem; } }
    </style>
</head>
<body>
<header class="site-header">
    <h1>{{ $store->name }}</h1>
    <p>Menyu</p>
</header>

@if ($categories->isNotEmpty())
    <nav class="category-nav" aria-label="Menyu kategoriyalari">
        <div class="category-tabs" role="tablist" aria-label="Kategoriyalar">
            @foreach ($categories as $category)
                <button
                    type="button"
                    class="category-tab"
                    role="tab"
                    id="category-tab-{{ $category->id }}"
                    aria-controls="category-panel-{{ $category->id }}"
                    aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                    data-menu-tab
                >{{ $category->name }}</button>
            @endforeach
        </div>
    </nav>
@endif

<main>
    @forelse ($categories as $category)
        <section
            class="category-panel"
            id="category-panel-{{ $category->id }}"
            role="tabpanel"
            aria-labelledby="category-tab-{{ $category->id }}"
            tabindex="0"
            data-menu-panel
        >
            <h2>{{ $category->name }}</h2>
            <div class="menu-grid">
                @foreach ($category->products as $product)
                    <article class="product-card">
                        <div class="product-image">
                            @if ($product->image_path)
                                <img src="{{ Storage::disk('public')->url($product->image_path) }}" alt="{{ $product->name }}" loading="lazy">
                            @else
                                <span>Rasm mavjud emas</span>
                            @endif
                        </div>
                        <div class="product-copy">
                            <p class="product-price">{{ number_format($product->price, 0, ',', ' ') }} UZS</p>
                            <h3 class="product-name">{{ $product->name }}</h3>
                            @if ($product->description)
                                <p class="product-description">{{ $product->description }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        <p class="empty">Hozircha menyuda mahsulot yo‘q.</p>
    @endforelse
</main>
<script>
    (() => {
        const tabs = [...document.querySelectorAll('[data-menu-tab]')];
        const panels = [...document.querySelectorAll('[data-menu-panel]')];
        if (!tabs.length) return;

        const activate = (index, focus = false) => {
            tabs.forEach((tab, position) => {
                const selected = position === index;
                tab.setAttribute('aria-selected', selected ? 'true' : 'false');
                tab.tabIndex = selected ? 0 : -1;
                panels[position].hidden = !selected;
            });
            if (focus) tabs[index].focus();
        };

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => activate(index));
            tab.addEventListener('keydown', (event) => {
                let next = index;
                if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
                else if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
                else if (event.key === 'Home') next = 0;
                else if (event.key === 'End') next = tabs.length - 1;
                else return;
                event.preventDefault();
                activate(next, true);
            });
        });
        activate(0);
    })();
</script>
</body>
</html>
