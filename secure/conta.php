<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

startSecureSession();
if (!isAuthenticated()) {
    header('Location: ' . appPath('/access/login.php?mode=login&next=' . rawurlencode(appPath('/secure/conta.php'))));
    exit;
}

$cfg = appConfig();
$pdo = new PDO("mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset={$cfg['db_charset']}", $cfg['db_user'], $cfg['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$profileStmt = $pdo->prepare('SELECT * FROM profissionais WHERE user_id = :user_id LIMIT 1');
$profileStmt->execute([':user_id' => (int) currentUserId()]);
$profile = $profileStmt->fetch() ?: [];
$csrf = ensureCsrfToken();
$deleteError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_account') {
    if (!verifyCsrfTokenOrFail($_POST['csrf_token'] ?? null)) {
        $deleteError = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        try {
            $pdo->beginTransaction();
            $deleteProfile = $pdo->prepare('DELETE FROM profissionais WHERE user_id = :user_id');
            $deleteProfile->execute([':user_id' => (int) currentUserId()]);
            $deleteUser = $pdo->prepare('DELETE FROM usuarios WHERE id = :user_id');
            $deleteUser->execute([':user_id' => (int) currentUserId()]);
            $pdo->commit();
            logoutUser();
            header('Location: ' . appPath('/index.html?account_deleted=1'));
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $deleteError = 'Não foi possível excluir a conta neste momento. Tente novamente.';
        }
    }
}
$name = trim((string) ($profile['nome'] ?? currentUserName() ?: 'Usuário'));
$email = currentUserEmail();
$initials = strtoupper(substr($name, 0, 1));
$phone = preg_replace('/\D+/', '', (string) ($profile['whatsapp'] ?? ''));
if (strlen($phone) === 11) $phone = '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7);
elseif (strlen($phone) === 10) $phone = '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 4) . '-' . substr($phone, 6);
$location = trim(implode(', ', array_filter([(string) ($profile['cidade'] ?? ''), (string) ($profile['bairro'] ?? '')])));
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?>
<style>
.sidebar nav > a { font-size: 0 !important; gap: 0 !important; }
.sidebar nav > a::before { font-size: .875rem; }
.sidebar nav > a:nth-of-type(1)::before { content: 'Agendamentos'; }
.sidebar nav > a:nth-of-type(2)::before { content: 'Mini marketplace'; }
.sidebar nav > a:nth-of-type(3)::before { content: 'Conta'; }
</style>
<?php
?><script>document.addEventListener('DOMContentLoaded',function(){var s=document.createElement('style');s.textContent='@media(max-width:1023px){aside.sidebar{display:flex!important;flex-direction:column!important;height:auto!important;padding:12px!important}aside.sidebar nav{display:flex!important;flex:0 0 auto!important;height:42px!important;min-height:42px!important;margin:0!important}aside.sidebar nav>a{display:flex!important;flex:1 1 0!important;align-items:center!important;justify-content:center!important;height:42px!important;padding:0!important}aside.sidebar>.mt-auto{display:block!important;margin:12px 0 0!important;padding:0!important;border:0!important}aside.sidebar>.mt-auto a:last-child{display:none!important}}';document.head.appendChild(s)});</script>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Minha conta</title><script src="https://cdn.tailwindcss.com"></script><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"><style>body{font-family:Inter,sans-serif;background:#f6f8fc}.sidebar{background:linear-gradient(160deg,#082d59,#123e70)}@media(min-width:1024px){.sidebar{position:sticky;top:0;height:100vh;align-self:start}}@media(max-width:1023px){.sidebar{display:flex!important;position:sticky;top:0;z-index:40;min-height:0;padding:.6rem .5rem}.sidebar>a:first-child,.sidebar>.mt-auto{display:none}.sidebar nav{display:flex;flex:1;gap:.2rem;margin:0}.sidebar nav p{display:none}.sidebar nav>a{flex:1;min-width:0;justify-content:center;overflow:hidden;padding:.68rem .2rem;text-align:center;font-size:0;white-space:nowrap}.sidebar nav>a:before{font-size:.72rem;font-weight:700}.sidebar nav>a:nth-of-type(1):before{content:"Agenda"}.sidebar nav>a:nth-of-type(2):before{content:"Loja"}.sidebar nav>a:nth-of-type(3):before{content:"Conta"}.sidebar nav>*+*{margin-top:0!important}main{min-width:0;overflow-x:hidden}.card{min-width:0}.card dd{min-width:0;overflow-wrap:anywhere}.card dd strong{overflow-wrap:anywhere}}.card{border:1px solid #e4ebf4;background:#fff;border-radius:14px;box-shadow:0 2px 5px rgba(15,23,42,.03)}.detail-icon{display:flex;height:34px;width:34px;flex:none;align-items:center;justify-content:center;border-radius:10px;background:#eff6ff;color:#2563eb;font-size:17px}.quick-link{display:flex;align-items:center;gap:12px;padding:15px 0;border-bottom:1px solid #e8eef5}.quick-link:last-child{border-bottom:0}.quick-link:hover strong{color:#2563eb}</style></head>
<body class="min-h-screen text-slate-900"><div class="grid min-h-screen lg:grid-cols-[300px_1fr]"><aside class="sidebar hidden flex-col p-6 text-white lg:flex"><a href="<?=e(appPath('/access/painel.php'))?>" class="flex items-center gap-3 border-b border-white/10 px-2 pb-6 font-extrabold"><img src="../img/logomenor.png" class="h-8 w-8 object-contain" alt="">Clube dos Parceiros</a><nav class="mt-7 space-y-1"><p class="mb-3 px-2 text-[10px] font-bold tracking-wide text-blue-200">CONFIGURAÇÕES</p><a href="<?=e(appPath('/access/configurar_agenda.php'))?>" class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-semibold text-blue-100 hover:bg-white/10">▣ Agendamentos</a><a href="<?=e(appPath('/access/mini_marketplace.php'))?>" class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-semibold text-blue-100 hover:bg-white/10">⊞ Mini marketplace</a><a href="<?=e(appPath('/secure/conta.php'))?>" class="flex items-center gap-3 rounded-lg bg-blue-600 px-3 py-3 text-sm font-bold">◯ Conta</a></nav><div class="mt-auto space-y-3 border-t border-white/10 pt-5"><a href="<?=e(appPath('/access/perfil.php'))?>" class="flex items-center justify-center gap-2 rounded-lg border border-white/20 px-3 py-2.5 text-sm font-bold text-white transition hover:bg-white/10">◉ Meu perfil</a><a href="<?=e(appPath('/access/painel.php'))?>" class="flex items-center justify-center gap-2 rounded-lg bg-white px-3 py-2.5 text-sm font-bold text-[#123e70] transition hover:bg-blue-50">← Voltar ao painel</a><a href="<?=e(appPath('/access/painel.php'))?>" class="block px-2 pt-2 text-sm font-semibold text-blue-100 hover:text-white">Precisa de ajuda?<small class="mt-1 block font-normal text-blue-200">Fale com nosso suporte</small></a></div></aside>
<main class="mx-auto w-full max-w-7xl px-5 py-8 sm:px-8 lg:px-12"><header class="flex flex-wrap items-start justify-between gap-4"><div><h1 class="text-3xl font-extrabold tracking-tight">Conta</h1><p class="mt-1 text-sm text-slate-500">Gerencie suas informações pessoais, acesso e preferências da sua conta.</p></div><span class="rounded-md border border-emerald-100 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">● Conta verificada</span></header>
<div class="mt-7 grid gap-5 lg:grid-cols-[1.35fr_.85fr]"><section class="card p-5 sm:p-6"><div class="flex items-start justify-between gap-3"><div><h2 class="font-bold">Informações pessoais</h2><p class="mt-1 text-xs text-slate-500">Atualize seus dados pessoais e como seus clientes entrarão em contato com você.</p></div><a href="<?=e(appPath('/secure/save_profile.php'))?>" class="rounded-md border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-50">✎ Editar</a></div><div class="mt-7 flex items-center gap-4 border-b border-slate-200 pb-5"><span class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-2xl font-bold text-slate-700"><?=e($initials)?></span><div><h3 class="font-bold"><?=e($name)?></h3><p class="mt-1 text-xs text-slate-500">Profissional Parceiro</p><?php if (!empty($profile['public_id'])): ?><a class="mt-1 inline-block text-xs font-semibold text-blue-600" href="<?=e(appPath('/access/perfil.php?p=' . rawurlencode((string) $profile['public_id'])))?>" target="_blank" rel="noopener">Perfil público: ver perfil ↗</a><?php endif; ?></div></div><dl class="mt-5 space-y-5 text-sm"><div class="flex gap-3"><dt class="detail-icon">♙</dt><dd><span class="block text-xs font-semibold text-slate-500">Nome ou nome fantasia</span><strong class="text-xs"><?=e($name)?></strong></dd></div><div class="flex gap-3"><dt class="detail-icon">✉</dt><dd><span class="block text-xs font-semibold text-slate-500">E-mail</span><strong class="text-xs"><?=e($email ?: 'Não informado')?></strong></dd></div><div class="flex gap-3"><dt class="detail-icon">⌕</dt><dd><span class="block text-xs font-semibold text-slate-500">Telefone</span><strong class="text-xs"><?=e($phone ?: 'Não informado')?></strong></dd></div><div class="flex gap-3"><dt class="detail-icon">◈</dt><dd><span class="block text-xs font-semibold text-slate-500">Categoria principal</span><strong class="text-xs"><?=e((string) ($profile['tags'] ?? 'Não informada'))?></strong></dd></div><div class="flex gap-3"><dt class="detail-icon">⌖</dt><dd><span class="block text-xs font-semibold text-slate-500">Localização de atendimento</span><strong class="text-xs"><?=e($location ?: 'Não informada')?></strong></dd></div><div class="flex gap-3"><dt class="detail-icon">ⓘ</dt><dd><span class="block text-xs font-semibold text-slate-500">Sobre você</span><strong class="text-xs leading-5"><?=e((string) ($profile['descricao'] ?? 'Não informado'))?></strong></dd></div></dl></section>
<aside class="space-y-5"><section class="card p-5"><h2 class="font-bold">Acesso rápido</h2><p class="mt-1 text-xs text-slate-500">Gerencie opções importantes da sua conta.</p><div class="mt-3"><a href="<?=e(appPath('/access/logout.php'))?>" class="quick-link text-red-600"><span class="detail-icon bg-red-50 text-red-500">↪</span><span class="flex-1"><strong class="block text-xs">Sair da conta</strong><small class="text-xs text-slate-500">Encerrar sessão neste dispositivo</small></span><b>›</b></a></div></section><section class="card p-5"><h2 class="font-bold">Zona de perigo</h2><p class="mt-1 text-xs text-slate-500">Ações que não podem ser desfeitas.</p><?php if ($deleteError): ?><p class="mt-3 rounded-md bg-red-50 p-3 text-xs font-semibold text-red-600"><?=e($deleteError)?></p><?php endif; ?><form method="post" class="mt-4 rounded-md border border-red-200 bg-red-50 p-4 text-red-600" onsubmit="return confirm('Tem certeza? Esta ação excluirá sua conta e perfil permanentemente.');"><input type="hidden" name="csrf_token" value="<?=e($csrf)?>"><input type="hidden" name="action" value="delete_account"><strong class="block text-xs">Excluir conta</strong><p class="mt-1 text-xs leading-5">Todos os seus dados e o seu perfil serão permanentemente removidos.</p><button type="submit" class="mt-3 rounded-md bg-red-600 px-3 py-2 text-xs font-bold text-white hover:bg-red-700">Excluir minha conta</button></form></section></aside></div></main></div></body></html>
