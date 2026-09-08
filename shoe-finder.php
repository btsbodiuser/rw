<?php
require_once __DIR__ . '/includes/config.php';

$siteName   = s('site_name', 'Runners World');
$page_title = 'AI гутал сонгогч — ' . $siteName;

// Kill switch — hides the wizard when the admin disables the feature.
if (!sBool('shoe_finder_enabled', true)) {
    header('Location: ' . $urlShop);
    exit;
}

$extraStyles = <<<'CSS'
<style>
.mainmenu > li > a { white-space: nowrap; }

.sf-wrap { max-width: 780px; margin: 0 auto; }
.sf-hero { text-align: center; padding: 48px 20px 32px; }
.sf-hero h1 { font-size: 32px; font-weight: 700; margin: 0 0 12px; color: #0a0a0a; }
.sf-hero .badge {
    display: inline-block; padding: 4px 12px; background: linear-gradient(90deg, #00B7FF, #0284C7);
    color: #fff; font-size: 11px; letter-spacing: .1em; text-transform: uppercase;
    border-radius: 999px; font-weight: 700; margin-bottom: 16px;
}
.sf-hero p { color: #6b7280; font-size: 15px; max-width: 520px; margin: 0 auto; }

.sf-question { margin: 24px 0; }
.sf-question .q-label { font-size: 15px; font-weight: 600; margin-bottom: 12px; color: #0a0a0a; }
.sf-options { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 8px; }
.sf-opt {
    padding: 14px 12px; border: 1.5px solid #e5e7eb; border-radius: 10px; background: #fff;
    cursor: pointer; text-align: center; font-size: 14px; font-weight: 500; color: #374151;
    transition: all .15s;
}
.sf-opt:hover { border-color: #00B7FF; color: #0284C7; }
.sf-opt.selected { border-color: #00B7FF; background: rgba(0, 183, 255, 0.08); color: #0284C7; font-weight: 600; }
.sf-opt input { display: none; }

.sf-submit {
    display: block; width: 100%; margin-top: 32px; padding: 16px;
    background: linear-gradient(90deg, #00B7FF, #0284C7); color: #fff;
    border: 0; border-radius: 10px; font-size: 16px; font-weight: 700;
    cursor: pointer; transition: opacity .15s;
}
.sf-submit:hover { opacity: 0.92; }
.sf-submit:disabled { opacity: 0.5; cursor: not-allowed; }

.sf-loading {
    text-align: center; padding: 60px 20px; display: none;
}
.sf-loading.show { display: block; }
.sf-loading .spinner {
    width: 40px; height: 40px; border: 3px solid #e5e7eb; border-top-color: #00B7FF;
    border-radius: 50%; margin: 0 auto 16px; animation: sf-spin 0.8s linear infinite;
}
@keyframes sf-spin { to { transform: rotate(360deg); } }
.sf-loading p { color: #6b7280; margin: 0; }

.sf-results { display: none; }
.sf-results.show { display: block; }
.sf-results-title { font-size: 20px; font-weight: 700; margin: 32px 0 8px; color: #0a0a0a; }
.sf-results-sub { color: #6b7280; margin: 0 0 20px; font-size: 14px; }
.sf-result-card {
    display: flex; gap: 16px; padding: 16px; border: 1px solid #e5e7eb;
    border-radius: 12px; margin-bottom: 12px; background: #fff; transition: box-shadow .15s;
}
.sf-result-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
.sf-result-card .thumb {
    width: 110px; height: 110px; flex-shrink: 0; background: #f6f6f6;
    border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center;
}
.sf-result-card .thumb img { width: 100%; height: 100%; object-fit: contain; }
.sf-result-card .info { flex: 1; min-width: 0; }
.sf-result-card .info .brand { font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
.sf-result-card .info .name {
    font-size: 15px; font-weight: 600; margin: 4px 0 6px; color: #0a0a0a;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.sf-result-card .info .reason {
    font-size: 13px; color: #4b5563; line-height: 1.55; margin: 6px 0 8px;
    padding: 8px 10px; background: rgba(0, 183, 255, 0.06); border-left: 3px solid #00B7FF;
    border-radius: 0 6px 6px 0;
}
.sf-result-card .info .footer { display: flex; justify-content: space-between; align-items: center; }
.sf-result-card .info .price { font-size: 15px; font-weight: 700; color: #0a0a0a; }
.sf-result-card .info .cta { font-size: 13px; color: #0284C7; font-weight: 600; text-decoration: none; }

.sf-error {
    padding: 16px; background: #fef2f2; color: #991b1b; border-radius: 8px; margin-top: 16px;
    font-size: 14px; display: none;
}
.sf-error.show { display: block; }

.sf-retry {
    display: inline-block; margin-top: 20px; padding: 10px 20px;
    border: 1.5px solid #e5e7eb; border-radius: 8px; background: #fff;
    font-size: 14px; color: #374151; cursor: pointer;
}
.sf-retry:hover { border-color: #00B7FF; color: #0284C7; }

@media (max-width: 640px) {
    .sf-hero h1 { font-size: 24px; }
    .sf-result-card { flex-direction: column; }
    .sf-result-card .thumb { width: 100%; height: 180px; }
}
</style>
CSS;

require __DIR__ . '/includes/header.php';
?>

<div class="rbt-breadcrumb-two rbt-bg-color-gray-100">
    <div class="container">
        <div class="rbt-breadcrumb-inner text-left">
            <ul class="rbt-breadcrumb-page-list justify-content-start mt--0">
                <li class="rbt-breadcrumb-item"><a href="<?= h($urlHome) ?>">Нүүр</a></li>
                <li class="rbt-breadcrumb-item"><span class="mr--8 ml--8">/</span></li>
                <li class="rbt-breadcrumb-item active">AI гутал сонгогч</li>
            </ul>
        </div>
    </div>
</div>

<div class="rbt-component-area rbt-section-gap rbt-bg-color-gray-light">
    <div class="container">
        <div class="sf-wrap">
            <div class="sf-hero">
                <span class="badge">AI-Powered</span>
                <h1>Танд тохирсон гутлыг олъё</h1>
                <p>5 богино асуултад хариулаад Claude AI танд хамгийн тохирох гүйлтийн гутлыг санал болгоно.</p>
            </div>

            <form id="sfForm">
                <div class="sf-question">
                    <div class="q-label">1. Хүйс</div>
                    <div class="sf-options" data-name="gender">
                        <label class="sf-opt"><input type="radio" name="gender" value="men" required>Эрэгтэй</label>
                        <label class="sf-opt"><input type="radio" name="gender" value="women">Эмэгтэй</label>
                    </div>
                </div>

                <div class="sf-question">
                    <div class="q-label">2. Ямар зайд гүйдэг вэ?</div>
                    <div class="sf-options" data-name="distance">
                        <label class="sf-opt"><input type="radio" name="distance" value="under_5k" required>5км хүртэл</label>
                        <label class="sf-opt"><input type="radio" name="distance" value="5_15k">5-15км</label>
                        <label class="sf-opt"><input type="radio" name="distance" value="half">Хагас марафон</label>
                        <label class="sf-opt"><input type="radio" name="distance" value="full">Марафон</label>
                        <label class="sf-opt"><input type="radio" name="distance" value="trail">Урт трейл</label>
                    </div>
                </div>

                <div class="sf-question">
                    <div class="q-label">3. Хаана гүйдэг вэ?</div>
                    <div class="sf-options" data-name="terrain">
                        <label class="sf-opt"><input type="radio" name="terrain" value="road" required>Хатуу зам</label>
                        <label class="sf-opt"><input type="radio" name="terrain" value="soft">Зөөлөн зам</label>
                        <label class="sf-opt"><input type="radio" name="terrain" value="trail">Уулын жим</label>
                        <label class="sf-opt"><input type="radio" name="terrain" value="mixed">Холимог</label>
                    </div>
                </div>

                <div class="sf-question">
                    <div class="q-label">4. Хөлийн байрлал (pronation)</div>
                    <div class="sf-options" data-name="gait">
                        <label class="sf-opt"><input type="radio" name="gait" value="neutral" checked>Нейтрал</label>
                        <label class="sf-opt"><input type="radio" name="gait" value="overpronation">Overpronation</label>
                        <label class="sf-opt"><input type="radio" name="gait" value="underpronation">Underpronation</label>
                        <label class="sf-opt selected"><input type="radio" name="gait" value="unknown" checked>Мэдэхгүй</label>
                    </div>
                </div>

                <div class="sf-question">
                    <div class="q-label">5. Төсөв</div>
                    <div class="sf-options" data-name="budget">
                        <label class="sf-opt"><input type="radio" name="budget" value="under_200k">200,000₮ хүртэл</label>
                        <label class="sf-opt"><input type="radio" name="budget" value="200_400k">200-400 мянга</label>
                        <label class="sf-opt"><input type="radio" name="budget" value="400_600k">400-600 мянга</label>
                        <label class="sf-opt"><input type="radio" name="budget" value="over_600k">600 мянгаас дээш</label>
                        <label class="sf-opt selected"><input type="radio" name="budget" value="any" checked>Хамаагүй</label>
                    </div>
                </div>

                <button type="submit" class="sf-submit" id="sfSubmit">Гутлыг олох</button>
                <div class="sf-error" id="sfError"></div>
            </form>

            <div class="sf-loading" id="sfLoading">
                <div class="spinner"></div>
                <p>Claude танд тохирсон гутлыг сонгож байна…</p>
            </div>

            <div class="sf-results" id="sfResults">
                <h2 class="sf-results-title">Санал болгож буй гутал</h2>
                <p class="sf-results-sub">AI-аас сонгосон, таны хариултад тохирсон гутлууд</p>
                <div id="sfResultList"></div>
                <button type="button" class="sf-retry" onclick="location.reload()">Дахин оролдох</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    // Radio-styled buttons — click to toggle .selected class on the label.
    document.querySelectorAll('.sf-options').forEach(function (grp) {
        grp.addEventListener('change', function () {
            grp.querySelectorAll('.sf-opt').forEach(function (opt) {
                var input = opt.querySelector('input');
                opt.classList.toggle('selected', input.checked);
            });
        });
        // initial paint (defaults marked with `checked`)
        grp.querySelectorAll('.sf-opt').forEach(function (opt) {
            if (opt.querySelector('input').checked) opt.classList.add('selected');
        });
    });

    var form    = document.getElementById('sfForm');
    var submit  = document.getElementById('sfSubmit');
    var loading = document.getElementById('sfLoading');
    var results = document.getElementById('sfResults');
    var list    = document.getElementById('sfResultList');
    var errBox  = document.getElementById('sfError');
    var apiUrl  = <?= json_encode(rtrim(getBaseUrl(), '/') . '/backend/api/shoe-finder.php') ?>;
    var shopUrl = <?= json_encode(url('product?slug=')) ?>;

    function fmtPrice(p) {
        return new Intl.NumberFormat('mn-MN').format(Math.round(p)) + '₮';
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        errBox.classList.remove('show');
        var fd = new FormData(form);
        var payload = {
            gender:   fd.get('gender'),
            distance: fd.get('distance'),
            terrain:  fd.get('terrain'),
            gait:     fd.get('gait'),
            budget:   fd.get('budget'),
        };
        if (!payload.gender || !payload.distance || !payload.terrain) {
            errBox.textContent = 'Эхний 3 асуултад хариулна уу.';
            errBox.classList.add('show');
            return;
        }
        form.style.display = 'none';
        loading.classList.add('show');

        try {
            var res = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            var data = await res.json();
            loading.classList.remove('show');

            if (!res.ok) {
                errBox.textContent = data.error || 'Алдаа гарлаа. Дараа дахин оролдоно уу.';
                errBox.classList.add('show');
                form.style.display = '';
                return;
            }
            var recs = data.recommendations || [];
            if (!recs.length) {
                list.innerHTML = '<p style="text-align:center;color:#6b7280;padding:20px;">' + (data.message || 'Тохирох гутал олдсонгүй.') + '</p>';
            } else {
                list.innerHTML = recs.map(function (r) {
                    var img = r.image || '';
                    return '<div class="sf-result-card">'
                        + '<div class="thumb">' + (img ? '<img src="' + img + '" alt="">' : '') + '</div>'
                        + '<div class="info">'
                        +   (r.brand ? '<div class="brand">' + r.brand + '</div>' : '')
                        +   '<div class="name">' + r.name_mn + '</div>'
                        +   '<div class="reason">' + r.reason_mn + '</div>'
                        +   '<div class="footer">'
                        +     '<div class="price">' + fmtPrice(r.price) + '</div>'
                        +     '<a class="cta" href="' + shopUrl + encodeURIComponent(r.slug) + '">Дэлгэрэнгүй →</a>'
                        +   '</div>'
                        + '</div>'
                        + '</div>';
                }).join('');
            }
            results.classList.add('show');
        } catch (err) {
            loading.classList.remove('show');
            form.style.display = '';
            errBox.textContent = 'Сүлжээний алдаа. Дараа дахин оролдоно уу.';
            errBox.classList.add('show');
        }
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
