<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?=htmlspecialchars($detail['title'])?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>body{font-family:Inter,Arial,sans-serif;background:linear-gradient(180deg,#f8fbff,#f2f6fb);color:#0a2450}.thumb.active{border-color:#0d4da2;box-shadow:0 0 0 1px #0d4da2}@media(max-width:767px){body{background:#fff}.site-navbar{left:50%;width:min(100%,670px);transform:translateX(-50%);border-radius:0 0 0 0}.site-navbar>div{height:112px;padding:0 24px}.site-navbar .nav-link{display:none}#detail-main{max-width:670px;padding:30px 24px 24px}#detail-card{border:0;border-radius:0;padding:0;box-shadow:none}#detail-back{display:none}#main-image{height:450px}.thumb-row{gap:10px}.thumb-row .thumb{height:112px;min-width:112px}.product-title{font-size:2.65rem;line-height:1.07}.benefits{margin:42px 0 34px;padding-bottom:28px}.benefits>div{gap:6px}.benefits small{font-size:.68rem}.professional-card{padding:18px}.security-bar{margin-top:34px;justify-content:flex-start;text-align:left;line-height:1.45}.security-bar a{margin-left:auto;white-space:nowrap}}@media(max-width:430px){.site-navbar>div{height:88px;padding:0 18px}.site-navbar img{height:42px}.site-navbar span.truncate{font-size:1.05rem}#detail-main{padding:22px 16px}#main-image{height:310px}.thumb-row .thumb{height:82px;min-width:82px}.product-title{font-size:2.15rem}.benefits{gap:8px}.benefits b{font-size:.68rem}.professional-card{gap:10px}.professional-card img,.professional-card>span:first-child{height:62px;width:62px}.professional-card .profile-button{padding:8px 12px}.security-bar{font-size:.75rem}}</style>
</head>
<body>
<?php
$whatsappPhone = preg_replace('/\D+/', '', (string) ($detail['whatsapp'] ?? ''));
if ($whatsappPhone !== '' && !(strlen($whatsappPhone) > 11 && str_starts_with($whatsappPhone, '55'))) {
    $whatsappPhone = '55' . $whatsappPhone;
}
$detail['whatsapp'] = $whatsappPhone;
?>
<?php require __DIR__.'/_site_nav.php'; ?>
<main id="detail-main" class="mx-auto max-w-[1400px] px-4 py-10 sm:px-8 sm:py-12">
  <section id="detail-card" class="rounded-[28px] border border-slate-200/80 bg-white p-5 shadow-[0_20px_60px_rgba(15,45,85,.08)] sm:p-9">
    <a id="detail-back" class="inline-flex items-center gap-3 text-sm font-bold text-[#0b3978]" href="<?=htmlspecialchars(appPath('/access/produtos_servicos.php'))?>"><span class="text-2xl">←</span> Voltar para produtos e serviços</a>
    <div class="mt-7 grid gap-10 lg:grid-cols-[1.16fr_.84fr] lg:gap-11">
      <div>
        <div class="relative overflow-hidden rounded-[24px] border border-slate-200 bg-[#f8fafc]">
          <img id="main-image" class="h-[330px] w-full object-contain sm:h-[500px]" src="<?=htmlspecialchars($detail['image'])?>" alt="<?=htmlspecialchars($detail['title'])?>">
          <span id="favorite" class="hidden" aria-hidden="true"></span>
          <button id="previous-image" class="absolute left-5 top-1/2 hidden h-14 w-14 -translate-y-1/2 rounded-full bg-white text-3xl text-[#0b3978] shadow-lg sm:block">‹</button>
          <button id="next-image" class="absolute right-5 top-1/2 hidden h-14 w-14 -translate-y-1/2 rounded-full bg-white text-3xl text-[#0b3978] shadow-lg sm:block">›</button>
        </div>
        <?php if($detail['images']):?><div class="thumb-row mt-5 flex gap-3 overflow-x-auto pb-1"><?php foreach($detail['images'] as $n=>$image):?><button class="thumb <?=!$n?'active':''?> h-24 min-w-24 overflow-hidden rounded-xl border-2 border-transparent bg-slate-50 p-1" data-image="<?=htmlspecialchars($image)?>"><img class="h-full w-full object-contain" src="<?=htmlspecialchars($image)?>" alt="Miniatura"></button><?php endforeach?></div><?php endif?>
      </div>
      <div class="flex flex-col py-1">
        <span class="inline-flex w-max rounded-full bg-blue-50 px-4 py-2 text-sm font-bold text-blue-700">◇ Produto</span>
        <h1 class="product-title mt-6 text-4xl font-black leading-tight tracking-tight text-[#071b40] sm:text-5xl"><?=htmlspecialchars($detail['title'])?></h1>
        <p class="mt-4 text-lg leading-relaxed text-slate-500"><?=htmlspecialchars($detail['description']?:'Produto ou serviço oferecido por este profissional.')?></p>
        <div class="my-7 border-t border-slate-200"></div>
        <p class="text-base text-slate-500">A partir de</p><p class="mt-1 text-5xl font-black tracking-tight text-[#093674]"><?=htmlspecialchars($detail['price']?:'A combinar')?></p>
        <?php if($detail['whatsapp']):$m='Olá! Tenho interesse em “'.$detail['title'].'”.';?><a class="mt-7 flex items-center justify-center gap-3 rounded-xl bg-[#083875] px-5 py-5 text-center text-base font-bold text-white shadow-md" target="_blank" rel="noopener" href="https://wa.me/<?=htmlspecialchars($detail['whatsapp'])?>?text=<?=rawurlencode($m)?>"><span class="text-2xl">◔</span> Tenho interesse no WhatsApp</a><?php endif?>
        <div class="benefits my-9 grid grid-cols-3 gap-3 border-b border-slate-200 pb-7 text-[#083875]"><div class="flex gap-2 text-xs"><span class="text-2xl">ϟ</span><span><b class="block">Resposta rápida</b><small class="text-slate-500">Mais segurança</small></span></div><div class="flex gap-2 text-xs"><span class="text-2xl">♢</span><span><b class="block">Compra protegida</b><small class="text-slate-500">Atendimento seguro</small></span></div><div class="flex gap-2 text-xs"><span class="text-2xl">♙</span><span><b class="block">Profissional verificado</b><small class="text-slate-500">Qualidade garantida</small></span></div></div>
        <a class="professional-card flex items-center gap-4 rounded-2xl border border-slate-200 p-4" href="<?=htmlspecialchars(appPath('/access/perfil.php?p='.$detail['public_id']))?>"><?php if($detail['photo']):?><img class="h-20 w-20 rounded-full object-cover" src="<?=htmlspecialchars($detail['photo'])?>" alt="<?=htmlspecialchars($detail['professional'])?>"><?php else:?><span class="flex h-20 w-20 items-center justify-center rounded-full bg-blue-100 text-2xl font-black text-blue-700"><?=htmlspecialchars(mb_strtoupper(mb_substr($detail['professional'],0,1)))?></span><?php endif?><span class="min-w-0 flex-1"><b class="block text-xl text-[#071b40]"><?=htmlspecialchars($detail['professional'])?></b><small class="mt-1 block text-slate-500">⌖ <?=htmlspecialchars($detail['city']?:'Localização não informada')?></small><small class="mt-2 block font-semibold text-[#083875]">★ <?=number_format($detail['rating'],1,',','.')?> <span class="font-normal text-slate-500">(<?=$detail['reviews']?> avaliações)</span></small></span><span class="profile-button rounded-lg border border-blue-600 px-4 py-2 text-sm font-bold text-blue-700">Ver perfil</span></a>
      </div>
    </div>
    <div class="security-bar mt-9 flex items-center justify-center gap-3 rounded-xl bg-blue-50 px-5 py-4 text-center text-sm text-[#315c9a]">♢ <span>Seu contato é seguro e não compartilhamos seus dados com terceiros.</span><a class="font-bold text-blue-700" href="politica_privacidade.php">Saiba mais</a></div>
  </section>
</main>
<script>const images=[...document.querySelectorAll('.thumb')].map(x=>x.dataset.image),mainImage=document.querySelector('#main-image');let current=0;function selectImage(index){if(!images.length)return;current=(index+images.length)%images.length;mainImage.src=images[current];document.querySelectorAll('.thumb').forEach((x,i)=>x.classList.toggle('active',i===current))}document.querySelectorAll('.thumb').forEach((x,i)=>x.addEventListener('click',()=>selectImage(i)));document.querySelector('#previous-image').addEventListener('click',()=>selectImage(current-1));document.querySelector('#next-image').addEventListener('click',()=>selectImage(current+1));document.querySelector('#favorite').addEventListener('click',e=>{e.currentTarget.textContent=e.currentTarget.textContent==='♡'?'♥':'♡';e.currentTarget.classList.toggle('text-red-500')});mainImage.addEventListener('error',()=>{mainImage.removeAttribute('src');mainImage.alt='Produto sem foto'});</script>
</body></html>
