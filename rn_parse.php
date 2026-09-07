<?php
// One-off parser: reads rn_mens.html, prints JSON array of products.
$src = $argv[1] ?? 'rn_mens.html';
$html = file_get_contents(__DIR__ . '/' . $src);

libxml_use_internal_errors(true);
$doc = new DOMDocument();
$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
$xp = new DOMXPath($doc);

// Product tiles: each contains an <a href="/p/..."> and lives inside a card.
// Walk up to the nearest ancestor that contains a price + name.
$productLinks = $xp->query('//a[starts-with(@href, "/p/") or starts-with(@href, "https://www.runnersneed.com/p/")]');
$seen = [];
$products = [];
foreach ($productLinks as $a) {
    $href = $a->getAttribute('href');
    if (str_starts_with($href, 'https://')) $href = parse_url($href, PHP_URL_PATH) . (parse_url($href, PHP_URL_QUERY) ? '?' . parse_url($href, PHP_URL_QUERY) : '');
    $path = strtok($href, '?');
    if (isset($seen[$path])) continue;
    $seen[$path] = true;

    // Slug + SKU from URL: /p/{slug-with-brand}-{SKU}.html
    if (!preg_match('#^/p/(.+)-([A-Z0-9]+)\.html$#', $path, $mm)) continue;
    $slugPart = $mm[1];
    $sku = $mm[2];

    // Walk up to find a container that has BOTH text price and image
    $container = $a;
    for ($depth = 0; $depth < 8; $depth++) {
        $container = $container->parentNode;
        if (!$container) break 1;
        $txt = $container->textContent;
        if (preg_match('/£\s*\d/', $txt)) break;
    }
    if (!$container) continue;

    // Image inside this container
    $img = null;
    $imgs = $xp->query('.//img', $container);
    foreach ($imgs as $ig) {
        $src = $ig->getAttribute('src') ?: $ig->getAttribute('data-src');
        if (str_contains($src, 'productimage001.runnersneed.com') && str_contains($src, '/316x474/')) {
            $img = $src; break;
        }
        if (!$img && str_contains($src, 'productimage001.runnersneed.com')) $img = $src;
    }

    // Prices — grab all £NN.NN in container, first = current, second (if higher) = RRP
    preg_match_all('/£\s*([0-9]+(?:\.[0-9]{2})?)/', $container->textContent, $pm);
    $prices = array_map('floatval', $pm[1] ?? []);
    if (!$prices) continue;
    $curr = min($prices);      // discounted price is the lower one
    $rrp  = count($prices) > 1 ? max($prices) : null;
    if ($rrp !== null && $rrp <= $curr) $rrp = null;

    // Name — the visible title inside the anchor or nearby
    $name = trim(preg_replace('/\s+/', ' ', $a->textContent));
    if ($name === '' || mb_strlen($name) < 3) {
        // Try headings inside container
        $h = $xp->query('.//*[self::h2 or self::h3 or self::h4 or self::span[contains(@class,"title")]]', $container);
        foreach ($h as $hn) {
            $t = trim(preg_replace('/\s+/', ' ', $hn->textContent));
            if (mb_strlen($t) > 3) { $name = $t; break; }
        }
    }

    // Derive brand from the slug (first token before "-mens/-womens/-unisex")
    $brand = '';
    if (preg_match('/^([a-z0-9]+(?:-[a-z0-9]+)?)-(mens|womens|unisex|kids)/i', $slugPart, $bm)) {
        $brand = ucwords(str_replace('-', ' ', $bm[1]));
    } else {
        // fallback: first slug token
        $brand = ucwords(explode('-', $slugPart)[0]);
    }
    // Fixups
    $brandFix = [
        'New' => 'New Balance', // 'new-balance' -> 'New Balance' handled above via double-token match
        'Ray' => 'Ray-Ban',
        'Mountain' => 'Mountain Equipment',
        'Ultimate' => 'Ultimate Performance',
    ];
    if (isset($brandFix[$brand])) $brand = $brandFix[$brand];

    // Category (rough): matched in priority order — most specific first.
    // Shoes wins over anything else because -shoes is unambiguous.
    $cat = 'Other';
    if (str_contains($slugPart, '-shoes'))                       $cat = 'Shoes';
    elseif (str_contains($slugPart, 'sunglasses'))               $cat = 'Sunglasses';
    elseif (str_contains($slugPart, 'socks'))                    $cat = 'Socks';
    elseif (str_contains($slugPart, 'gloves'))                   $cat = 'Gloves';
    elseif (preg_match('/-(jacket|windbreaker|shell|gilet|vest)(-|$)/', $slugPart)) $cat = 'Jackets';
    elseif (preg_match('/-(shorts?)(-|$)/', $slugPart))          $cat = 'Shorts';
    elseif (preg_match('/-(tights?)(-|$)/', $slugPart))          $cat = 'Tights';
    elseif (preg_match('/-(leggings?|leggins?|joggers?|pants?|trousers?)(-|$)/', $slugPart)) $cat = 'Leggings';
    elseif (preg_match('/-(t-shirt|tee|jersey|top|singlet|tank|jumper|fleece|hoodie|sweatshirt|midlayer|base-layer|long-sleeve|short-sleeve|quarter-zip|half-zip|crew-neck)(-|$)/', $slugPart)) $cat = 'Tops';
    elseif (str_contains($slugPart, '-bra') || str_contains($slugPart, 'sports-bra')) $cat = 'Bras';
    elseif (preg_match('/-(cap|hat|beanie|headband|visor)(-|$)/', $slugPart)) $cat = 'Headwear';
    elseif (preg_match('/-(pack|rucksack|backpack|bag|belt|bottle|flask|hydration)(-|$)/', $slugPart)) $cat = 'Packs';
    elseif (preg_match('/-(gel|cleaning|proofer|wash|lube|balm)(-|$)/', $slugPart)) $cat = 'Care';
    elseif (preg_match('/-(compression|sleeves?|support|tape|brace)(-|$)/', $slugPart)) $cat = 'Accessories';

    // Pretty product name — from slug (strip brand + gender)
    $prettySlug = preg_replace('/^([a-z0-9-]+?)-(mens|womens|unisex|kids)-/', '', $slugPart);
    if ($prettySlug === $slugPart) $prettySlug = preg_replace('/^' . preg_quote(explode('-', $slugPart)[0], '/') . '-/', '', $slugPart);
    $prettyName = ucwords(str_replace('-', ' ', $prettySlug));

    $products[] = [
        'sku'        => $sku,
        'slug'       => $slugPart,
        'brand'      => $brand,
        'name'       => $prettyName,
        'category'   => $cat,
        'image'      => $img,
        'price_gbp'  => $curr,
        'rrp_gbp'    => $rrp,
        'url'        => 'https://www.runnersneed.com' . $path,
    ];
}

echo json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
