<!doctype html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#12352e">
    <title>{{ $store->name }} — Menyu</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, -apple-system, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f7f5ef; color: #17352d; }
        header { background: #173f33; color: #fff; padding: 2.5rem 1rem 2.2rem; text-align: center; }
        header h1 { font-size: clamp(1.7rem, 5vw, 2.5rem); margin: 0 0 .45rem; }
        header p { color: #cde8d9; margin: 0; }
        main { max-width: 860px; margin: auto; padding: 1.5rem 1rem 3rem; }
        h2 { font-size: 1.45rem; margin: 2rem 0 1rem; }
        .items { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 310px), 1fr)); gap: .9rem; }
        article { display: flex; background: white; border: 1px solid #e5e9e1; border-radius: 1rem; overflow: hidden; box-shadow: 0 3px 12px #17352d0a; }
        article img { width: 104px; height: 104px; object-fit: cover; flex: none; }
        .item-copy { padding: .9rem; min-width: 0; }
        h3 { font-size: 1rem; margin: 0 0 .35rem; }
        .description { color: #5b6e65; font-size: .87rem; line-height: 1.4; margin: .25rem 0 .6rem; white-space: pre-line; overflow-wrap: anywhere; }
        .price { color: #1a7554; font-weight: 700; margin: .45rem 0 0; }
        .empty { text-align: center; padding: 3rem 1rem; color: #5b6e65; }
        @media (max-width: 420px) { article img { width: 88px; height: 88px; } }
    </style>
</head>
<body>
<header>
    <h1>{{ $store->name }}</h1>
    <p>Menyu</p>
</header>
<main>
    @forelse ($categories as $category)
        <section aria-labelledby="category-{{ $category->id }}">
            <h2 id="category-{{ $category->id }}">{{ $category->name }}</h2>
            <div class="items">
                @foreach ($category->products as $product)
                    <article>
                        @if ($product->image_path)
                            <img src="{{ Storage::disk('public')->url($product->image_path) }}" alt="{{ $product->name }}" loading="lazy">
                        @endif
                        <div class="item-copy">
                            <h3>{{ $product->name }}</h3>
                            @if ($product->description)
                                <p class="description">{{ $product->description }}</p>
                            @endif
                            <p class="price">{{ number_format($product->price, 0, ',', ' ') }} UZS</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        <p class="empty">Hozircha menyuda mahsulot yo‘q.</p>
    @endforelse
</main>
</body>
</html>
