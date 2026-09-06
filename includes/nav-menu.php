                            <ul class="mainmenu has-nav-bg-shape-hover">

                                <!-- Шинэ ирсэн -->
                                <li>
                                    <a href="<?= h(navShopUrl(['new' => 1])) ?>">Шинэ ирсэн</a>
                                </li>

                                <?php
                                $_genders = [
                                    'men'   => 'Эрэгтэй',
                                    'women' => 'Эмэгтэй',
                                    'kids'  => 'Хүүхэд',
                                ];
                                foreach ($_genders as $gKey => $gLabel):
                                    $gShopUrl = navGenderUrl($gKey);
                                ?>
                                <li class="with-rbt-megamenu has-menu-child-item position-static">
                                    <a href="<?= h($gShopUrl) ?>"><?= h($gLabel) ?> <i class="fa-regular fa-chevron-down"></i></a>
                                    <div class="rbt-megamenu container pl_sm--0 pl_md--0 pl_lg--0">
                                        <div class="rbt-megamenu-wrapper">
                                            <div class="row row--12 d-flex justify-content-between">
                                                <div class="col-xl-9">
                                                    <div class="row row--12">

                                                        <!-- Column 1: Гутал -->
                                                        <div class="col-xl-3 single-mega-item rbt-scroll-trigger fade_in animation-order-1">
                                                            <p class="rbt-short-title h5">
                                                                <a href="<?= h(navGenderUrl($gKey, ['category' => 'shoes'])) ?>">Гутал</a>
                                                            </p>
                                                            <ul class="mega-menu-item">
                                                                <?php foreach ($navShoeTypes as $st): ?>
                                                                <li><a href="<?= h(navGenderUrl($gKey, ['shoe_type' => $st['slug']])) ?>"><?= h($st['name_mn'] ?: $st['name']) ?> гутал</a></li>
                                                                <?php endforeach; ?>
                                                                <li><a href="<?= h(navGenderUrl($gKey, ['category' => 'road,trail,race,lightweight'])) ?>"><strong>Бүх гутал</strong></a></li>
                                                            </ul>
                                                        </div>

                                                        <!-- Column 2: Хувцас -->
                                                        <div class="col-xl-3 single-mega-item rbt-scroll-trigger fade_in animation-order-2">
                                                            <p class="rbt-short-title h5">
                                                                <a href="<?= h(navGenderUrl($gKey, ['category' => 'clothes'])) ?>">Хувцас</a>
                                                            </p>
                                                            <ul class="mega-menu-item">
                                                                <?php if (!empty($navClothingCats)): ?>
                                                                    <?php foreach ($navClothingCats as $cc): ?>
                                                                    <li><a href="<?= h(navGenderUrl($gKey, ['category' => $cc['slug']])) ?>"><?= h($cc['name_mn'] ?: $cc['name']) ?></a></li>
                                                                    <?php endforeach; ?>
                                                                <?php else: ?>
                                                                    <li class="text-muted"><em>Хувцасны ангилал алга</em></li>
                                                                <?php endif; ?>
                                                                <li><a href="<?= h(navGenderUrl($gKey, ['category' => 'clothes'])) ?>"><strong>Бүх хувцас</strong></a></li>
                                                            </ul>
                                                        </div>

                                                        <!-- Column 3: Гүйлтийн зориулалт -->
                                                        <?php if (!empty($navRunTypes)): ?>
                                                        <div class="col-xl-3 single-mega-item rbt-scroll-trigger fade_in animation-order-3">
                                                            <p class="rbt-short-title h5">Гүйлтийн төрөл</p>
                                                            <ul class="mega-menu-item">
                                                                <?php foreach ($navRunTypes as $rt): ?>
                                                                <li><a href="<?= h(navGenderUrl($gKey, ['run_type' => $rt['slug']])) ?>"><?= h($rt['name_mn'] ?: $rt['name']) ?></a></li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </div>
                                                        <?php endif; ?>

                                                        <!-- Column 4: Дагалдах + Outlet -->
                                                        <div class="col-xl-3 single-mega-item rbt-scroll-trigger fade_in animation-order-4">
                                                            <p class="rbt-short-title h5">Бусад</p>
                                                            <ul class="mega-menu-item">
                                                                <?php foreach ($navAccessoryCats as $ac): ?>
                                                                <li><a href="<?= h(navGenderUrl($gKey, ['category' => $ac['slug']])) ?>"><?= h($ac['name_mn'] ?: $ac['name']) ?></a></li>
                                                                <?php endforeach; ?>
                                                                <li><a href="<?= h(navGenderUrl($gKey, ['category' => 'outlet'])) ?>">Аутлет</a></li>
                                                                <li><a href="<?= h(navGenderUrl($gKey, ['discount' => 1])) ?>">Хямдралтай</a></li>
                                                                <li><a href="<?= h(navGenderUrl($gKey, ['new' => 1])) ?>">Шинэ ирсэн</a></li>
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

                                                <!-- Right teaser -->
                                                <div class="col-xl-3 single-mega-item rbt-scroll-trigger fade_in animation-order-5">
                                                    <div class="rbt-menu-offer-card rbt-bg-style-box rbt-bg-two">
                                                        <div class="mega-top-banner">
                                                            <div class="rbt-banner-inner flex-column justify-content-center rbt-gap--8 align-items-center text-center">
                                                                <div class="rbt-banner-content">
                                                                    <h2 class="title rbt-text-color-white"><?= h($gLabel) ?></h2>
                                                                    <p class="b3 desc rbt-text-color-gray-200">Бүх сүүлийн үеийн бүтээгдэхүүн</p>
                                                                </div>
                                                                <a class="rbt-btn rbt-btn-sm" href="<?= h($gShopUrl) ?>">Дэлгүүр орох</a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>

                                <!-- Гүйлтийн гутал -->
                                <li class="with-rbt-megamenu has-menu-child-item position-static">
                                    <a href="<?= h(navShopUrl(['category' => 'road,trail,race,lightweight'])) ?>">Гүйлтийн гутал <i class="fa-regular fa-chevron-down"></i></a>
                                    <div class="rbt-megamenu container pl_sm--0 pl_md--0 pl_lg--0">
                                        <div class="rbt-megamenu-wrapper">
                                            <div class="row row--12">

                                                <!-- Column 1: Төрөл -->
                                                <div class="col-xl-3 single-mega-item rbt-scroll-trigger fade_in animation-order-1">
                                                    <p class="rbt-short-title h5">Шинэ ирсэн</p>
                                                    <ul class="mega-menu-item">
                                                        <li><a href="<?= h(navShopUrl(['new' => 1, 'category' => 'road,trail,race,lightweight'])) ?>">Шинэ ирсэн гутал</a></li>
                                                    </ul>
                                                    <p class="rbt-short-title h5 mt--16">Гутлын төрөл</p>
                                                    <ul class="mega-menu-item">
                                                        <?php foreach ($navShoeTypes as $st): ?>
                                                        <li><a href="<?= h(navShopUrl(['shoe_type' => $st['slug']])) ?>"><?= h($st['name_mn'] ?: $st['name']) ?> гүйлт</a></li>
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
                                                        <li><a href="<?= h(navShopUrl(['category' => 'recovery-injury-prevention'])) ?>">Нөхөн сэргээлт</a></li>
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
                                                        <li><a href="<?= h(navShopUrl(['category' => 'outlet'])) ?>">Аутлет</a></li>
                                                        <li><a href="<?= h(navShopUrl(['category' => 'road,trail,race,lightweight'])) ?>"><strong>Бүх гутал</strong></a></li>
                                                    </ul>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </li>

                                <!-- Хувцас -->
                                <?php if (!empty($navClothingCats)): ?>
                                <li class="with-rbt-megamenu has-menu-child-item position-static">
                                    <a href="<?= h(navShopUrl(['category' => 'clothes'])) ?>">Хувцас <i class="fa-regular fa-chevron-down"></i></a>
                                    <div class="rbt-megamenu container pl_sm--0 pl_md--0 pl_lg--0">
                                        <div class="rbt-megamenu-wrapper">
                                            <div class="row row--12">
                                                <div class="col-12">
                                                    <ul class="mega-menu-item d-flex flex-wrap gap-3">
                                                        <li><a href="<?= h(navShopUrl(['new' => 1, 'category' => 'clothes'])) ?>">Шинэ ирсэн</a></li>
                                                        <?php foreach ($navClothingCats as $cc): ?>
                                                        <li><a href="<?= h(navShopUrl(['category' => $cc['slug']])) ?>"><?= h($cc['name_mn'] ?: $cc['name']) ?></a></li>
                                                        <?php endforeach; ?>
                                                        <li><a href="<?= h(navShopUrl(['category' => 'outlet'])) ?>">Аутлет</a></li>
                                                        <li><a href="<?= h(navShopUrl(['category' => 'clothes'])) ?>"><strong>Бүх хувцас</strong></a></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <?php endif; ?>

                                <!-- Техник & Дагалдах -->
                                <?php if (!empty($navAccessoryCats)): ?>
                                <li class="with-rbt-megamenu has-menu-child-item position-static">
                                    <a href="<?= h(navShopUrl(['category' => 'accessories'])) ?>">Техник & Дагалдах <i class="fa-regular fa-chevron-down"></i></a>
                                    <div class="rbt-megamenu container pl_sm--0 pl_md--0 pl_lg--0">
                                        <div class="rbt-megamenu-wrapper">
                                            <div class="row row--12">
                                                <div class="col-12">
                                                    <ul class="mega-menu-item d-flex flex-wrap gap-3">
                                                        <?php foreach ($navAccessoryCats as $ac): ?>
                                                        <li><a href="<?= h(navShopUrl(['category' => $ac['slug']])) ?>"><?= h($ac['name_mn'] ?: $ac['name']) ?></a></li>
                                                        <?php endforeach; ?>
                                                        <li><a href="<?= h(navShopUrl(['category' => 'recovery-injury-prevention'])) ?>">Нөхөн сэргээлт</a></li>
                                                        <li><a href="<?= h(navShopUrl(['category' => 'outlet'])) ?>">Аутлет</a></li>
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

                                <!-- Хямдрал -->
                                <li>
                                    <a href="<?= h(navShopUrl(['discount' => 1])) ?>" class="rbt-text-color-secondary"><strong>Хямдрал</strong></a>
                                </li>

                            </ul>
