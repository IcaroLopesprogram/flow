<?php
require_once __DIR__ . '/../secure/auth.php';
require_once __DIR__ . '/../secure/config.php';
startSecureSession();
header('Content-Type: application/json; charset=utf-8');
if (!isAuthenticated()) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Sessão expirada.']); exit; }
$cfg=appConfig();
$pdo=new PDO("mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset={$cfg['db_charset']}",$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$stmt=$pdo->prepare('SELECT id,marketplace_products FROM profissionais WHERE user_id=:u ORDER BY id DESC LIMIT 1');$stmt->execute([':u'=>(int)currentUserId()]);$profile=$stmt->fetch();
if(!$profile){http_response_code(404);echo json_encode(['ok'=>false,'error'=>'Perfil não encontrado.']);exit;}
if(!verifyCsrfTokenOrFail($_POST['csrf_token']??null)){http_response_code(419);echo json_encode(['ok'=>false,'error'=>'Sessão inválida.']);exit;}
$items=json_decode((string)$profile['marketplace_products'],true);if(!is_array($items))$items=[];
$items=array_values(array_filter($items,'is_array'));
$action=(string)($_POST['action']??'');$id=(string)($_POST['id']??'');
foreach($items as $i=>&$item){$item['id']=(string)($item['id']??bin2hex(random_bytes(8)));$item['active']=array_key_exists('active',$item)?(bool)$item['active']:true;$item['order']=(int)($item['order']??$i);$item['images']=array_values(array_filter((array)($item['images']??[$item['image']??''])));}unset($item);
if($action==='delete'){$items=array_values(array_filter($items,fn($x)=>$x['id']!==$id));}
elseif($action==='toggle'){foreach($items as &$item)if($item['id']===$id)$item['active']=!$item['active'];unset($item);}
elseif($action==='reorder'){ $order=json_decode((string)($_POST['order']??'[]'),true);if(is_array($order))foreach($order as $pos=>$pid)foreach($items as &$item)if($item['id']===$pid)$item['order']=$pos;unset($item);}
elseif($action==='save'){
  $title=mb_substr(trim((string)($_POST['title']??'')),0,100);$description=mb_substr(trim((string)($_POST['description']??'')),0,60);$price=trim((string)($_POST['price']??''));
  if($title===''||($id===''&&empty($_FILES['images']['name'][0]))){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Nome e primeira foto são obrigatórios.']);exit;}
  $images=[];foreach($items as $item)if($item['id']===$id)$images=$item['images'];
  $dir=__DIR__.'/../uploads/marketplace';if(!is_dir($dir))mkdir($dir,0775,true);
  foreach((array)($_FILES['images']['name']??[]) as $k=>$name){if($name===''||count($images)>=4)continue;$tmp=$_FILES['images']['tmp_name'][$k]??'';$type=mime_content_type($tmp);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$type]??null;if(!$ext)continue;$file=bin2hex(random_bytes(12)).'.'.$ext;if(move_uploaded_file($tmp,$dir.'/'.$file))$images[]=appPath('/uploads/marketplace/'.$file);}
  $record=['id'=>$id?:bin2hex(random_bytes(8)),'title'=>$title,'description'=>$description,'price'=>$price,'images'=>$images,'image'=>$images[0]??'','active'=>isset($_POST['active']),'order'=>count($items)];$found=false;foreach($items as &$item)if($item['id']===$record['id']){$record['order']=$item['order'];$item=$record;$found=true;}unset($item);if(!$found){if(count($items)>=20){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Limite de 20 produtos atingido.']);exit;}$items[]=$record;}
}
usort($items,fn($a,$b)=>$a['order']<=>$b['order']);$save=$pdo->prepare('UPDATE profissionais SET marketplace_products=:p WHERE id=:id');$save->execute([':p'=>json_encode($items,JSON_UNESCAPED_SLASHES),':id'=>$profile['id']]);echo json_encode(['ok'=>true,'items'=>$items],JSON_UNESCAPED_SLASHES);
