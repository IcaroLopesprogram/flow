<?php
$navUserId = currentUserId();
$navName = $navUserId ? (explode(' ', trim(currentUserName()))[0] ?: 'Usuário') : '';
$navInitial = $navName !== '' ? strtoupper(substr($navName, 0, 1)) : 'U';
?>
<link rel="stylesheet" href="<?= htmlspecialchars(appPath('/assets/theme.css?v=20260822'), ENT_QUOTES, 'UTF-8') ?>">
<style>
    body { padding-top: 76px; }
    .site-navbar { background: #0b2a52; transition: box-shadow .2s ease; }
    .site-navbar.is-scrolled { box-shadow: 0 8px 22px rgba(2, 19, 43, .18); }
    .nav-link { display: inline-flex; align-items: center; gap: .55rem; color: rgba(255,255,255,.88); font-size: .92rem; font-weight: 600; transition: color .18s ease; }
    .nav-link:hover, .nav-link[aria-expanded="true"] { color: #fff; }
    #site-navbar [data-nav-products] { display: inline-flex !important; position: relative; visibility: visible !important; opacity: 1 !important; }
    .nav-products { position: relative; }
    .nav-products-badge { position: absolute; left: 50%; top: -1.55rem; transform: translateX(-50%); border-radius: 999px; background: #2563eb; padding: .25rem .6rem; color: #fff; font-size: .62rem; font-weight: 900; letter-spacing: .04em; box-shadow: 0 5px 12px rgba(37,99,235,.35); }
    .category-dropdown { transform-origin: top center; transition: opacity .16s ease, transform .16s ease; }
    .category-dropdown.is-hidden { opacity: 0; transform: translateY(-6px) scale(.98); pointer-events: none; }
    .category-item { display: flex; align-items: center; gap: .7rem; border-radius: .55rem; padding: .65rem .75rem; color: #16345f; font-size: .88rem; font-weight: 600; transition: background-color .16s ease, color .16s ease; }
    .category-item:hover { background: #eff6ff; color: #0755d9; }
</style>

<nav id="site-navbar" class="site-navbar fixed top-0 z-50 w-full border-b border-white/10 text-white">
    <div class="mx-auto flex h-[76px] max-w-none items-center justify-between gap-5 px-[5.5vw]">
        <a href="<?= htmlspecialchars(appPath('/index.html'), ENT_QUOTES, 'UTF-8') ?>" class="flex min-w-0 shrink-0 items-center gap-3 font-black text-white" aria-label="Ir para a página inicial">
            <img src="<?= htmlspecialchars(appPath('/img/logomenor.png'), ENT_QUOTES, 'UTF-8') ?>" class="h-11 w-auto object-contain sm:h-12" alt="Logo Clube dos Parceiros">
            <span class="truncate text-base sm:text-lg">Clube dos Parceiros</span>
        </a>

        <div class="hidden flex-1 items-center justify-center gap-12 xl:gap-16 lg:flex">
            <a href="<?= htmlspecialchars(appPath('/access/painel.php'), ENT_QUOTES, 'UTF-8') ?>" class="nav-link"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="6"></circle><path d="m20 20-4.2-4.2"></path></svg>Encontrar profissionais</a>
            <a href="<?= htmlspecialchars(appPath('/access/produtos_servicos.php'), ENT_QUOTES, 'UTF-8') ?>" class="nav-link nav-products" data-nav-products><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 9.5 5 4h14l2 5.5M4 10h16v10H4z"></path><path d="M9 20v-5h6v5"></path></svg>Produtos<span class="nav-products-badge">NOVO</span></a>
            <a href="<?= htmlspecialchars(appPath('/index.html') . '#como-funciona', ENT_QUOTES, 'UTF-8') ?>" class="nav-link"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 10v6m0-9h.01"></path></svg>Como funciona</a>
            <div class="relative" id="categories-wrapper">
                <button id="categories-toggle" class="nav-link" type="button" aria-expanded="false" aria-controls="categories-menu"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 9.5 5 4h14l2 5.5M4 10h16v10H4z"></path><path d="M9 20v-5h6v5"></path></svg>Categorias<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg></button>
                <div id="categories-menu" class="category-dropdown is-hidden absolute left-1/2 top-[calc(100%+20px)] z-50 w-72 -translate-x-1/2 rounded-[10px] border border-slate-100 bg-white p-2 shadow-lg" role="menu">
                    <a href="<?= htmlspecialchars(appPath('/access/painel.php?category=casa-reforma'), ENT_QUOTES, 'UTF-8') ?>" class="category-item" role="menuitem">⌂ Casa e Reforma</a>
                    <a href="<?= htmlspecialchars(appPath('/access/painel.php?category=instalacoes-manutencao'), ENT_QUOTES, 'UTF-8') ?>" class="category-item" role="menuitem">⌕ Instalações e Manutenção</a>
                    <a href="<?= htmlspecialchars(appPath('/access/painel.php?category=tecnologia'), ENT_QUOTES, 'UTF-8') ?>" class="category-item" role="menuitem">▣ Tecnologia</a>
                    <a href="<?= htmlspecialchars(appPath('/access/painel.php?category=eventos'), ENT_QUOTES, 'UTF-8') ?>" class="category-item" role="menuitem">☆ Eventos</a>
                    <a href="<?= htmlspecialchars(appPath('/access/painel.php?category=outros'), ENT_QUOTES, 'UTF-8') ?>" class="category-item" role="menuitem">••• Outros</a>
                </div>
            </div>
        </div>

        <?php if ($navUserId): ?>
            <div class="relative hidden shrink-0 items-center gap-4 lg:flex">
                <button id="account-toggle" type="button" class="flex items-center gap-2 rounded-lg px-1 py-1 text-sm font-semibold hover:bg-white/10" aria-expanded="false" aria-controls="account-menu"><span class="relative flex h-9 w-9 items-center justify-center rounded-full bg-blue-500 text-xs font-bold"><?= htmlspecialchars($navInitial, ENT_QUOTES, 'UTF-8') ?><i class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full border-2 border-[#0b2a52] bg-emerald-400"></i></span>Olá, <?= htmlspecialchars($navName, ENT_QUOTES, 'UTF-8') ?>!<svg id="account-caret" class="h-4 w-4 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg></button>
                <div id="account-menu" class="category-dropdown is-hidden absolute right-0 top-[calc(100%+17px)] z-[60] w-[230px] rounded-[10px] border border-slate-100 bg-white p-2 text-slate-800 shadow-lg"><a href="<?= htmlspecialchars(appPath('/access/perfil.php'), ENT_QUOTES, 'UTF-8') ?>" class="category-item">◯ Meu perfil</a><a href="<?= htmlspecialchars(appPath('/secure/save_profile.php'), ENT_QUOTES, 'UTF-8') ?>" class="category-item">✎ Editar perfil</a><a href="<?= htmlspecialchars(appPath('/access/configurar_agenda.php'), ENT_QUOTES, 'UTF-8') ?>" class="category-item">▣ Meus agendamentos</a><div class="my-2 border-t border-slate-100"></div><a href="<?= htmlspecialchars(appPath('/access/logout.php'), ENT_QUOTES, 'UTF-8') ?>" class="category-item text-red-600 hover:bg-red-50 hover:text-red-700">↪ Sair</a></div>
            </div>
        <?php else: ?>
            <div class="hidden shrink-0 items-center gap-5 lg:flex"><a href="<?= htmlspecialchars(appPath('/access/login.php?mode=login'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-white/90 transition hover:text-white">Entrar</a><span class="h-7 w-px bg-white/30" aria-hidden="true"></span><a href="<?= htmlspecialchars(appPath('/access/login.php?mode=register'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg bg-blue-600 px-5 py-3 text-sm font-bold text-white transition duration-200 hover:-translate-y-px hover:bg-blue-500">Cadastrar como parceiro</a></div>
        <?php endif; ?>

        <?php if ($navUserId): ?><a href="<?= htmlspecialchars(appPath('/access/perfil.php'), ENT_QUOTES, 'UTF-8') ?>" class="relative flex h-12 w-12 items-center justify-center rounded-full bg-[#123f75] text-sm font-bold text-white lg:hidden" aria-label="Meu perfil"><?= htmlspecialchars($navInitial, ENT_QUOTES, 'UTF-8') ?><i class="absolute bottom-0.5 right-0.5 h-2.5 w-2.5 rounded-full border-2 border-[#0b2a52] bg-emerald-400"></i></a><?php endif; ?>
        <button id="nav-toggle" type="button" class="rounded-lg p-2 text-white transition hover:bg-white/10 lg:hidden" aria-label="Abrir menu" aria-controls="mobile-menu" aria-expanded="false"><svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"></path></svg></button>
    </div>

    <?php
    $mobileDrawer = [
        'logged_in' => (bool) $navUserId,
        'user_name' => $navName,
        'logo_url' => appPath('/img/logomenor.png'),
        'index_url' => appPath('/index.html'),
        'products_url' => appPath('/access/produtos_servicos.php'),
        'directory_url' => appPath('/access/painel.php'),
        'how_it_works_url' => appPath('/index.html') . '#como-funciona',
        'login_url' => appPath('/access/login.php?mode=login'),
        'signup_url' => appPath('/access/login.php?mode=register'),
        'profile_url' => appPath('/access/perfil.php'),
        'edit_url' => appPath('/secure/save_profile.php'),
        'appointments_url' => appPath('/access/configurar_agenda.php'),
        'logout_url' => appPath('/access/logout.php'),
    ];
    require __DIR__ . '/_mobile_drawer.php';
    unset($mobileDrawer);
    ?>
</nav>

<script>
(() => {
    const navbar = document.getElementById('site-navbar');
    const categoriesToggle = document.getElementById('categories-toggle');
    const categoriesMenu = document.getElementById('categories-menu');
    const accountToggle = document.getElementById('account-toggle');
    const accountMenu = document.getElementById('account-menu');
    const accountCaret = document.getElementById('account-caret');
    const navToggle = document.getElementById('nav-toggle');
    const mobileMenu = document.getElementById('mobile-menu');
    const mobileClose = document.getElementById('mobile-menu-close');
    const mobileBackdrop = mobileMenu?.querySelector('[data-mobile-menu-backdrop]');
    const mobileCategoriesToggle = document.getElementById('mobile-categories-toggle');
    const mobileCategoriesMenu = document.getElementById('mobile-categories-menu');
    let closeTimer = 0;

    const closeMobileMenu = (restoreFocus = false) => {
        if (!mobileMenu || !navToggle) return;
        window.clearTimeout(closeTimer);
        mobileMenu.classList.remove('is-open');
        mobileMenu.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('mobile-menu-open');
        navToggle.setAttribute('aria-expanded', 'false');
        closeTimer = window.setTimeout(() => { if (!mobileMenu.classList.contains('is-open')) mobileMenu.classList.add('hidden'); }, 280);
        if (restoreFocus) navToggle.focus({ preventScroll: true });
    };
    const openMobileMenu = () => {
        if (!mobileMenu || !navToggle || window.matchMedia('(min-width: 1024px)').matches) return;
        window.clearTimeout(closeTimer);
        categoriesMenu?.classList.add('is-hidden');
        mobileMenu.classList.remove('hidden');
        mobileMenu.setAttribute('aria-hidden', 'false');
        document.body.classList.add('mobile-menu-open');
        navToggle.setAttribute('aria-expanded', 'true');
        window.requestAnimationFrame(() => mobileMenu.classList.add('is-open'));
        window.setTimeout(() => mobileClose?.focus({ preventScroll: true }), 30);
    };

    navToggle?.addEventListener('click', () => mobileMenu?.classList.contains('hidden') ? openMobileMenu() : closeMobileMenu());
    mobileClose?.addEventListener('click', () => closeMobileMenu(true));
    mobileBackdrop?.addEventListener('click', () => closeMobileMenu(true));
    mobileMenu?.addEventListener('click', (event) => { if (event.target instanceof Element && event.target.closest('a')) closeMobileMenu(); });
    mobileCategoriesToggle?.addEventListener('click', () => {
        const isOpen = !mobileCategoriesMenu?.classList.contains('hidden');
        mobileCategoriesMenu?.classList.toggle('hidden', isOpen);
        mobileCategoriesToggle.setAttribute('aria-expanded', String(!isOpen));
    });

    const closeDesktopMenus = () => {
        categoriesMenu?.classList.add('is-hidden');
        accountMenu?.classList.add('is-hidden');
        categoriesToggle?.setAttribute('aria-expanded', 'false');
        accountToggle?.setAttribute('aria-expanded', 'false');
        accountCaret?.classList.remove('rotate-180');
    };
    categoriesToggle?.addEventListener('click', (event) => { event.stopPropagation(); categoriesMenu?.classList.toggle('is-hidden'); categoriesToggle.setAttribute('aria-expanded', String(!categoriesMenu?.classList.contains('is-hidden'))); accountMenu?.classList.add('is-hidden'); });
    accountToggle?.addEventListener('click', (event) => { event.stopPropagation(); accountMenu?.classList.toggle('is-hidden'); accountToggle.setAttribute('aria-expanded', String(!accountMenu?.classList.contains('is-hidden'))); accountCaret?.classList.toggle('rotate-180', !accountMenu?.classList.contains('is-hidden')); categoriesMenu?.classList.add('is-hidden'); });
    document.addEventListener('click', closeDesktopMenus);
    window.addEventListener('keydown', (event) => { if (event.key === 'Escape') { closeDesktopMenus(); closeMobileMenu(true); } });
    window.addEventListener('resize', () => { if (window.matchMedia('(min-width: 1024px)').matches) closeMobileMenu(); });
    const syncShadow = () => navbar?.classList.toggle('is-scrolled', window.scrollY > 6);
    syncShadow();
    window.addEventListener('scroll', syncShadow, { passive: true });
})();
</script>
