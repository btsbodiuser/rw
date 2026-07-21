<?php
/**
 * Shared "My Account" sidebar, included by account.php / account-orders.php /
 * account-addresses.php / account-setting.php.
 *
 * Expects $user (from getSessionUser()) and $activeAccountPage to be set by
 * the including page, where $activeAccountPage is one of:
 * 'dashboard' | 'orders' | 'addresses' | 'settings'.
 */
$accountNavItems = [
    'dashboard' => ['href' => url('account.php'),           'icon' => 'icon-circle-four',    'label' => 'Профайл'],
    'orders'    => ['href' => url('account-orders.php'),    'icon' => 'icon-box-arrow-down', 'label' => 'Захиалгууд'],
    'addresses' => ['href' => url('account-addresses.php'), 'icon' => 'icon-map-pin',        'label' => 'Хаяг'],
    'settings'  => ['href' => url('account-setting.php'),   'icon' => 'icon-setting',        'label' => 'Тохиргоо'],
];
?>
                    <!-- Sidebar -->
                    <div class="col-xl-3 d-none d-xl-block">
                        <div class="sidebar-account sidebar-content-wrap sticky-top">
                            <div class="account-author">
                                <div class="author_avatar">
                                    <div class="image">
                                        <img src="<?= assetUrl('images/avatar/avatar-4.jpg') ?>" alt="Avatar">
                                    </div>
                                </div>
                                <h4 class="author_name"><?= htmlspecialchars($user['name'] ?? '') ?></h4>
                                <p class="author_email h6"><?= htmlspecialchars($user['phone'] ?? $user['email'] ?? '') ?></p>
                            </div>
                            <ul class="my-account-nav">
                                <?php foreach ($accountNavItems as $key => $item): ?>
                                <li>
                                    <a href="<?= $item['href'] ?>" class="my-account-nav_item h5<?= $activeAccountPage === $key ? ' active' : '' ?>">
                                        <i class="icon <?= $item['icon'] ?>"></i>
                                        <?= $item['label'] ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                                <li>
                                    <a href="<?= url('logout-action.php') ?>" class="my-account-nav_item h5"
                                       onclick="return confirm('Гарахдаа итгэлтэй байна уу?')">
                                        <i class="icon icon-sign-out"></i>
                                        Гарах
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
