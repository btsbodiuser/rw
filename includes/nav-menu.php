                            <ul class="mainmenu has-nav-bg-shape-hover">

                                <?php
                                // Top nav labels come from the real top-level categories
                                // (categories.name_mn) so renaming a category in the admin
                                // updates the menu without a code change.
                                $_navLabel = function (string $slug, string $fallback) use ($navTopCategories): string {
                                    $c = $navTopCategories[$slug] ?? null;
                                    return $c ? ($c['name_mn'] ?: $c['name']) : $fallback;
                                };
                                $_genders = [
                                    'men'   => 'Эрэгтэй',
                                    'women' => 'Эмэгтэй',
                                ];
                                foreach ($_genders as $gKey => $gLabel):
                                    $gShopUrl = navGenderUrl($gKey);
                                ?>
                                <li class="with-rbt-megamenu has-menu-child-item position-static">
                                    <a href="<?= h($gShopUrl) ?>"><?= h($gLabel) ?> <i class="fa-regular fa-chevron-down"></i></a>
                                    <div class="rbt-megamenu container pl_sm--0 pl_md--0 pl_lg--0">
                                        <div class="rbt-megamenu-wrapper">
                                            <?php
                                            // Real top-level categories + their real subcategories.
                                            // Per-gender product counts (mens_count / womens_count on each
                                            // category row) filter subcategories that only carry items for
                                            // the other gender — e.g. "bras" never appears under men.
                                            $_countKey = $gKey === 'men' ? 'mens_count' : 'womens_count';
                                            $_gTopCats = array_values(array_filter(
                                                [$navTopCategories['shoes'] ?? null, $navTopCategories['clothes'] ?? null, $navTopCategories['accessories'] ?? null]
                                            ));
                                            ?>
                                            <div class="row row--12 d-flex justify-content-between">
                                                <div class="col-xl-12">
                                                    <div class="row row--12">

                                                        <?php foreach ($_gTopCats as $_ci => $_top):
                                                            $_subs = array_values(array_filter(
                                                                $navSubCategories[$_top['id']] ?? [],
                                                                fn($sc) => (int)($sc[$_countKey] ?? 0) > 0
                                                            ));
                                                        ?>
                                                        <!-- Column: <?= h($_top['name_mn'] ?: $_top['name']) ?> -->
                                                        <div class="col-xl-3 single-mega-item rbt-scroll-trigger fade_in animation-order-<?= $_ci + 1 ?>">
                                                            <p class="rbt-short-title h5">
                                                                <a href="<?= h(navGenderUrl($gKey, ['category' => $_top['slug']])) ?>"><?= h($_top['name_mn'] ?: $_top['name']) ?></a>
                                                            </p>
                                                            <ul class="mega-menu-item">
                                                                <?php foreach ($_subs as $sc): ?>
                                                                <li><a href="<?= h(navGenderUrl($gKey, ['category' => $sc['slug']])) ?>"><?= h($sc['name_mn'] ?: $sc['name']) ?></a></li>
                                                                <?php endforeach; ?>
                                                                <li><a href="<?= h(navGenderUrl($gKey, ['category' => $_top['slug']])) ?>"><strong>Бүх <?= h(mb_strtolower($_top['name_mn'] ?: $_top['name'])) ?></strong></a></li>
                                                            </ul>
                                                        </div>
                                                        <?php endforeach; ?>

                                                        <!-- Column: Шинэ / Хямдрал -->
                                                        <div class="col-xl-3 single-mega-item rbt-scroll-trigger fade_in animation-order-4">
                                                            <p class="rbt-short-title h5">Бусад</p>
                                                            <ul class="mega-menu-item">
                                                                <li><a href="<?= h(navGenderUrl($gKey, ['new' => 1])) ?>">Шинэ ирсэн</a></li>
                                                                <li><a href="<?= h(navGenderUrl($gKey, ['discount' => 1])) ?>">Хямдралтай</a></li>
                                                                <li><a href="<?= h($gShopUrl) ?>"><strong>Бүх <?= h($gLabel) ?> бараа</strong></a></li>
                                                            </ul>
                                                        </div>

                                                    </div>

                                                    <?php if (!empty($navBrands)): ?>
                                                    <div class="row row--12 d-none d-xl-flex">
                                                        <div class="col-12">
                                                            <hr class="rbt-separator rbt-separator-gray200 mb--16 mt--16 rbt-bg-color-gray-100">
                                                        </div>
                                                        <div class="col-lg-12">
                                                            <ul class="rbt-nav-brand-list liststyle d-flex justify-content-xl-between">
                                                                <?php foreach ($navBrands as $brand):
                                                                    $bUrl  = navGenderUrl($gKey, ['shop' => $brand['slug']]);
                                                                    $bLogo = !empty($brand['logo']) ? fixImageUrl($brand['logo']) : assetUrl('images/brands/brand-a-01.webp');
                                                                ?>
                                                                <li><a href="<?= h($bUrl) ?>" title="<?= h($brand['name']) ?>"><img src="<?= h($bLogo) ?>" alt="<?= h($brand['name']) ?>"></a></li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>

                                <!-- Гүйлтийн гутал -->
                                <li class="with-rbt-megamenu has-menu-child-item position-static">
                                    <a href="<?= h(navShopUrl(['category' => 'road,trail,race,lightweight'])) ?>"><?= h($_navLabel('shoes', 'Гүйлтийн гутал')) ?> <i class="fa-regular fa-chevron-down"></i></a>
                                    <div class="rbt-megamenu container pl_sm--0 pl_md--0 pl_lg--0">
                                        <div class="rbt-megamenu-wrapper">
                                            <div class="row row--12">

                                                <!-- Column 1: Гутлын ангилал (real subcategories) -->
                                                <div class="col-xl-3 single-mega-item rbt-scroll-trigger fade_in animation-order-1">
                                                    <p class="rbt-short-title h5">Ангилал</p>
                                                    <ul class="mega-menu-item">
                                                        <?php foreach (($navSubCategories[$navTopCategories['shoes']['id'] ?? 0] ?? []) as $sc): ?>
                                                        <li><a href="<?= h(navShopUrl(['category' => $sc['slug']])) ?>"><?= h($sc['name_mn'] ?: $sc['name']) ?></a></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>

                                                <!-- Column 2: Алхаа + Онцлог -->
                                                <div class="col-xl-3 single-mega-item rbt-scroll-trigger fade_in animation-order-2">
                                                    <p class="rbt-short-title h5">Алхааны төрөл</p>
                                                    <ul class="mega-menu-item">
                                                        <?php foreach ($navGaitTypes as $gt): ?>
                                                        <li><a href="<?= h(navShopUrl(['gait' => $gt['slug'], 'category' => 'road,trail,race,lightweight'])) ?>"><?= h($gt['name_mn'] ?: $gt['name']) ?> гутал</a></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                    <p class="rbt-short-title h5 mt--16">Онцлог</p>
                                                    <ul class="mega-menu-item">
                                                        <li><a href="<?= h(navShopUrl(['feature' => 'waterproof', 'category' => 'road,trail,race,lightweight'])) ?>">Усны нэвчилтгүй гутал</a></li>
                                                    </ul>
                                                </div>

                                                <!-- Column 3: Гүйлтийн зорилго -->
                                                <?php if (!empty($navRunTypes)): ?>
                                                <div class="col-xl-3 single-mega-item rbt-scroll-trigger fade_in animation-order-3">
                                                    <p class="rbt-short-title h5">Гүйлтийн зорилго</p>
                                                    <ul class="mega-menu-item">
                                                        <?php foreach ($navRunTypes as $rt): ?>
                                                        <li><a href="<?= h(navShopUrl(['run_type' => $rt['slug']])) ?>"><?= h($rt['name_mn'] ?: $rt['name']) ?></a></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                                <?php endif; ?>

                                                <!-- Column 4: Бусад -->
                                                <div class="col-xl-3 single-mega-item rbt-scroll-trigger fade_in animation-order-4">
                                                    <p class="rbt-short-title h5">Бусад</p>
                                                    <ul class="mega-menu-item">
                                                        <?php if (sBool('shoe_finder_enabled', true)): ?>
                                                        <li><a href="<?= h(url('shoe-finder')) ?>" style="color:#0284C7;font-weight:600;">✨ AI гутал сонгогч</a></li>
                                                        <?php endif; ?>
                                                        <li><a href="<?= h(navShopUrl(['new' => 1, 'category' => 'road,trail,race,lightweight'])) ?>">Шинэ ирсэн</a></li>
                                                        <li><a href="<?= h(navShopUrl(['discount' => 1, 'category' => 'road,trail,race,lightweight'])) ?>">Хямдралтай</a></li>
                                                        <li><a href="<?= h(navShopUrl(['category' => 'road,trail,race,lightweight'])) ?>"><strong>Бүх гутал</strong></a></li>
                                                    </ul>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </li>

                                <!-- Хувцас -->
                                <?php $_clothesSubs = $navSubCategories[$navTopCategories['clothes']['id'] ?? 0] ?? []; ?>
                                <?php if (!empty($_clothesSubs)): ?>
                                <li class="with-rbt-megamenu has-menu-child-item position-static">
                                    <a href="<?= h(navShopUrl(['category' => 'clothes'])) ?>"><?= h($_navLabel('clothes', 'Хувцас')) ?> <i class="fa-regular fa-chevron-down"></i></a>
                                    <div class="rbt-megamenu container pl_sm--0 pl_md--0 pl_lg--0">
                                        <div class="rbt-megamenu-wrapper">
                                            <div class="row row--12">
                                                <div class="col-12">
                                                    <ul class="mega-menu-item d-flex flex-wrap gap-3">
                                                        <li><a href="<?= h(navShopUrl(['new' => 1, 'category' => 'clothes'])) ?>">Шинэ ирсэн</a></li>
                                                        <li><a href="<?= h(navShopUrl(['discount' => 1, 'category' => 'clothes'])) ?>">Хямдралтай</a></li>
                                                        <?php foreach ($_clothesSubs as $cc): ?>
                                                        <li><a href="<?= h(navShopUrl(['category' => $cc['slug']])) ?>"><?= h($cc['name_mn'] ?: $cc['name']) ?></a></li>
                                                        <?php endforeach; ?>
                                                        <li><a href="<?= h(navShopUrl(['category' => 'clothes'])) ?>"><strong>Бүх хувцас</strong></a></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <?php endif; ?>

                                <!-- Техник & Дагалдах -->
                                <?php $_accessorySubs = $navSubCategories[$navTopCategories['accessories']['id'] ?? 0] ?? []; ?>
                                <?php if (!empty($_accessorySubs)): ?>
                                <li class="with-rbt-megamenu has-menu-child-item position-static">
                                    <a href="<?= h(navShopUrl(['category' => 'accessories'])) ?>"><?= h($_navLabel('accessories', 'Техник & Дагалдах')) ?> <i class="fa-regular fa-chevron-down"></i></a>
                                    <div class="rbt-megamenu container pl_sm--0 pl_md--0 pl_lg--0">
                                        <div class="rbt-megamenu-wrapper">
                                            <div class="row row--12">
                                                <div class="col-12">
                                                    <ul class="mega-menu-item d-flex flex-wrap gap-3">
                                                        <li><a href="<?= h(navShopUrl(['new' => 1, 'category' => 'accessories'])) ?>">Шинэ ирсэн</a></li>
                                                        <li><a href="<?= h(navShopUrl(['discount' => 1, 'category' => 'accessories'])) ?>">Хямдралтай</a></li>
                                                        <?php foreach ($_accessorySubs as $ac): ?>
                                                        <li><a href="<?= h(navShopUrl(['category' => $ac['slug']])) ?>"><?= h($ac['name_mn'] ?: $ac['name']) ?></a></li>
                                                        <?php endforeach; ?>
                                                        <li><a href="<?= h(navShopUrl(['category' => 'accessories'])) ?>"><strong>Бүх дагалдах</strong></a></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <?php endif; ?>

                                <!-- Брэнд -->
                                <li>
                                    <a href="<?= h(url('brands')) ?>">Брэнд</a>
                                </li>

                            </ul>
