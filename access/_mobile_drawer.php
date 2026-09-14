<?php
$mobileDrawerData = isset($mobileDrawer) && is_array($mobileDrawer) ? $mobileDrawer : [];
$mobileDrawerEscape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$mobileDrawerValue = static function (string $key, string $fallback = '') use ($mobileDrawerData): string {
    return isset($mobileDrawerData[$key]) && $mobileDrawerData[$key] !== '' ? (string) $mobileDrawerData[$key] : $fallback;
};

$mobileDrawerLoggedIn = !empty($mobileDrawerData['logged_in']);
$mobileDrawerName = trim($mobileDrawerValue('user_name', 'Usuário'));
$mobileDrawerFirstName = explode(' ', $mobileDrawerName)[0] ?: 'Usuário';
$mobileDrawerInitial = strtoupper(substr($mobileDrawerFirstName, 0, 1)) ?: 'U';
$mobileDrawerLogoUrl = $mobileDrawerValue('logo_url', appPath('/img/logomenor.png'));
$mobileDrawerIndexUrl = $mobileDrawerValue('index_url', appPath('/index.html'));
$mobileDrawerProductsUrl = $mobileDrawerValue('products_url', appPath('/access/produtos_servicos.php'));
$mobileDrawerDirectoryUrl = $mobileDrawerValue('directory_url', appPath('/access/painel.php'));
$mobileDrawerHowItWorksUrl = $mobileDrawerValue('how_it_works_url', appPath('/index.html') . '#como-funciona');
$mobileDrawerLoginUrl = $mobileDrawerValue('login_url', appPath('/access/login.php?mode=login'));
$mobileDrawerSignupUrl = $mobileDrawerValue('signup_url', appPath('/access/login.php?mode=register'));
$mobileDrawerProfileUrl = $mobileDrawerValue('profile_url', appPath('/access/perfil.php'));
$mobileDrawerEditUrl = $mobileDrawerValue('edit_url', appPath('/secure/save_profile.php'));
$mobileDrawerAppointmentsUrl = $mobileDrawerValue('appointments_url', appPath('/access/configurar_agenda.php'));
$mobileDrawerLogoutUrl = $mobileDrawerValue('logout_url', appPath('/access/logout.php'));
$mobileDrawerCategories = [
    'Casa e Reforma' => appPath('/access/painel.php?category=casa-reforma'),
    'Instalações e Manutenção' => appPath('/access/painel.php?category=instalacoes-manutencao'),
    'Tecnologia' => appPath('/access/painel.php?category=tecnologia'),
    'Eventos' => appPath('/access/painel.php?category=eventos'),
    'Outros' => appPath('/access/painel.php?category=outros'),
];
?>
<div id="mobile-menu" class="hidden lg:hidden" role="dialog" aria-modal="true" aria-hidden="true" aria-label="Menu principal">
    <button type="button" class="mobile-drawer-backdrop" data-mobile-menu-backdrop aria-label="Fechar menu"></button>
    <div class="mobile-drawer-panel" role="document">
        <div class="mobile-drawer-header">
            <a href="<?= $mobileDrawerEscape($mobileDrawerIndexUrl) ?>" class="mobile-drawer-brand">
                <img src="<?= $mobileDrawerEscape($mobileDrawerLogoUrl) ?>" alt="Logo Clube dos Parceiros">
                <span>Clube dos Parceiros</span>
            </a>
            <button id="mobile-menu-close" type="button" class="mobile-drawer-close" aria-label="Fechar menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"></path></svg>
            </button>
        </div>

        <div class="mobile-drawer-content">
            <?php if ($mobileDrawerLoggedIn): ?>
                <div id="nav-account-mobile" class="mobile-drawer-account">
                    <a id="account-profile-mobile" href="<?= $mobileDrawerEscape($mobileDrawerProfileUrl) ?>" class="mobile-drawer-account-card">
                        <span id="nav-avatar-mobile" class="mobile-drawer-avatar"><?= $mobileDrawerEscape($mobileDrawerInitial) ?><i></i></span>
                        <span class="mobile-drawer-account-copy"><strong id="nav-user-greeting-mobile">Olá, <?= $mobileDrawerEscape($mobileDrawerFirstName) ?>!</strong><small>Ver meu perfil</small></span>
                        <svg class="mobile-drawer-link__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
                    </a>
                </div>
            <?php endif; ?>

            <section class="mobile-drawer-section">
                <p class="mobile-drawer-label">Navegação</p>

                <?php if (!$mobileDrawerLoggedIn): ?>
                    <div id="mobile-guest-actions" class="mobile-drawer-list">
                        <a id="nav-auth-btn-mobile" href="<?= $mobileDrawerEscape($mobileDrawerLoginUrl) ?>" class="mobile-drawer-link">
                            <span class="mobile-drawer-link__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"></path><path d="M5 9.5V21h14V9.5M9 21v-6h6v6"></path></svg></span>Entrar
                            <svg class="mobile-drawer-link__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
                        </a>
                        <a id="nav-signup-btn-mobile" href="<?= $mobileDrawerEscape($mobileDrawerSignupUrl) ?>" class="mobile-drawer-link mobile-drawer-link--primary">
                            <span class="mobile-drawer-link__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="9" cy="7" r="3"></circle><path d="M3.5 21v-2a5.5 5.5 0 0 1 11 0v2M18 8v8m-4-4h8"></path></svg></span>Cadastrar como parceiro
                            <svg class="mobile-drawer-link__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
                        </a>
                    </div>
                <?php endif; ?>

                <div class="mobile-drawer-list<?= $mobileDrawerLoggedIn ? '' : ' mt-2' ?>">
                    <?php if ($mobileDrawerLoggedIn): ?>
                        <a href="<?= $mobileDrawerEscape($mobileDrawerIndexUrl) ?>" class="mobile-drawer-link">
                            <span class="mobile-drawer-link__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"></path><path d="M5 9.5V21h14V9.5M9 21v-6h6v6"></path></svg></span>Início
                            <svg class="mobile-drawer-link__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
                        </a>
                    <?php endif; ?>
                    <a href="<?= $mobileDrawerEscape($mobileDrawerProductsUrl) ?>" class="mobile-drawer-link" data-nav-products>
                        <span class="mobile-drawer-link__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 9.5 5 4h14l2 5.5M4 10h16v10H4z"></path><path d="M9 20v-5h6v5"></path></svg></span>Produtos<span class="mobile-drawer-badge">NOVO</span>
                        <svg class="mobile-drawer-link__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
                    </a>
                    <a href="<?= $mobileDrawerEscape($mobileDrawerDirectoryUrl) ?>" class="mobile-drawer-link">
                        <span class="mobile-drawer-link__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="6"></circle><path d="m20 20-4.2-4.2"></path></svg></span>Encontrar profissionais
                        <svg class="mobile-drawer-link__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
                    </a>
                    <a href="<?= $mobileDrawerEscape($mobileDrawerHowItWorksUrl) ?>" class="mobile-drawer-link">
                        <span class="mobile-drawer-link__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 10v6m0-9h.01"></path></svg></span>Como funciona
                        <svg class="mobile-drawer-link__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
                    </a>
                    <button id="mobile-categories-toggle" type="button" class="mobile-drawer-link" aria-expanded="false" aria-controls="mobile-categories-menu">
                        <span class="mobile-drawer-link__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 9.5 5 4h14l2 5.5M4 10h16v10H4z"></path><path d="M9 20v-5h6v5"></path></svg></span>Categorias
                        <svg class="mobile-drawer-link__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
                    </button>
                    <div id="mobile-categories-menu" class="hidden mobile-drawer-categories">
                        <?php foreach ($mobileDrawerCategories as $mobileDrawerCategory => $mobileDrawerCategoryUrl): ?>
                            <a href="<?= $mobileDrawerEscape($mobileDrawerCategoryUrl) ?>" class="mobile-drawer-category-link"><?= $mobileDrawerEscape($mobileDrawerCategory) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <?php if ($mobileDrawerLoggedIn): ?>
                <div class="mobile-drawer-divider"></div>
                <section id="mobile-account-links" class="mobile-drawer-section">
                    <p class="mobile-drawer-label">Conta</p>
                    <div class="mobile-drawer-list">
                        <a href="<?= $mobileDrawerEscape($mobileDrawerProfileUrl) ?>" class="mobile-drawer-link"><span class="mobile-drawer-link__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21v-2a8 8 0 0 1 16 0v2"></path></svg></span>Meu perfil<svg class="mobile-drawer-link__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg></a>
                        <a id="account-edit-mobile" href="<?= $mobileDrawerEscape($mobileDrawerEditUrl) ?>" class="mobile-drawer-link"><span class="mobile-drawer-link__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3a2.2 2.2 0 0 0-2.15 1.74l-.17.76a6.8 6.8 0 0 0-1.04.6l-.75-.23A2.2 2.2 0 0 0 5.3 7l-.38.68a2.2 2.2 0 0 0 .48 2.73l.59.5a7.3 7.3 0 0 0 0 1.2l-.59.5a2.2 2.2 0 0 0-.48 2.73l.38.68a2.2 2.2 0 0 0 2.59 1.13l.75-.23c.33.24.68.44 1.04.6l.17.76A2.2 2.2 0 0 0 12 21h.78a2.2 2.2 0 0 0 2.15-1.74l.17-.76c.36-.16.71-.36 1.04-.6l.75.23a2.2 2.2 0 0 0 2.59-1.13l.38-.68a2.2 2.2 0 0 0-.48-2.73l-.59-.5a7.3 7.3 0 0 0 0-1.2l.59-.5a2.2 2.2 0 0 0 .48-2.73L19.48 7a2.2 2.2 0 0 0-2.59-1.13l-.75.23a6.8 6.8 0 0 0-1.04-.6l-.17-.76A2.2 2.2 0 0 0 12.78 3H12Z"></path><circle cx="12.4" cy="12" r="2.3"></circle></svg></span>Configurações<svg class="mobile-drawer-link__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg></a>
                        <a href="<?= $mobileDrawerEscape($mobileDrawerAppointmentsUrl) ?>" class="mobile-drawer-link"><span class="mobile-drawer-link__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"></rect><path d="M8 3v4m8-4v4M4 10h16"></path></svg></span>Meus agendamentos<svg class="mobile-drawer-link__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg></a>
                        <a id="account-logout-mobile" href="<?= $mobileDrawerEscape($mobileDrawerLogoutUrl) ?>" class="mobile-drawer-link mobile-drawer-link--danger"><span class="mobile-drawer-link__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2v-3"></path><path d="m10 12 9 0m-3-3 3 3-3 3"></path></svg></span>Sair<svg class="mobile-drawer-link__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg></a>
                    </div>
                </section>
            <?php endif; ?>

            <div class="mobile-drawer-safety">
                <span class="mobile-drawer-safety__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3 4.5 6v5.2c0 4.9 3.1 8.6 7.5 9.8 4.4-1.2 7.5-4.9 7.5-9.8V6L12 3Z"></path><path d="m8.8 12 2.1 2.1 4.5-4.5"></path></svg></span>
                <div><strong>Ambiente seguro</strong><p>Seus dados protegidos com segurança.</p></div>
                <svg class="mobile-drawer-link__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
            </div>

            <?php if (!$mobileDrawerLoggedIn): ?>
                <div class="mobile-drawer-promo">
                    <div class="mobile-drawer-promo__top">
                        <span class="mobile-drawer-promo__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="9" cy="8" r="3"></circle><path d="M3.5 21v-2.2A5.8 5.8 0 0 1 9.3 13h.4a5.8 5.8 0 0 1 5.8 5.8V21M17 8a3 3 0 1 1 0 6m2.7 7v-2.2a5.8 5.8 0 0 0-3.4-5.3"></path></svg></span>
                        <div><strong>Conecte-se com os melhores profissionais</strong><p>Encontre serviços e produtos de qualidade perto de você.</p></div>
                    </div>
                    <a href="<?= $mobileDrawerEscape($mobileDrawerLoginUrl) ?>" class="mobile-drawer-promo__button">Entrar ou criar conta</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
