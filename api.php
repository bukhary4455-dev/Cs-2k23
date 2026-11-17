<?php
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']);
session_start();
header('Content-Type: application/json; charset=utf-8');
function out($d,$c=200){http_response_code($c);echo json_encode($d,JSON_UNESCAPED_UNICODE);exit;}
set_exception_handler(function($e){out(['error'=>'Server error. Please try again.'],500);});
$cfg = require __DIR__.'/config.php';
try { $db = new PDO("mysql:host={$cfg['host']};dbname={$cfg['db']};charset=utf8mb4",$cfg['user'],$cfg['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); }
catch (Throwable $e) { out(['error'=>'Database connection failed. Check config.php and run install.php'],500); }
function q($s,$p=[]){global $db;$st=$db->prepare($s);$st->execute($p);return $st;}
function v($k,$max=500){return mb_substr(trim((string)($_POST[$k]??'')),0,$max);}
function upload($f){
  if(!$f||$f['error']!==0)return null;
  if($f['size']>6000000)out(['error'=>'Image is too large (max 6 MB)'],400);
  $i=@getimagesize($f['tmp_name']);
  $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$i['mime']??'']??null;
  if(!$ext)out(['error'=>'Only JPG, PNG or WebP images are allowed'],400);
  $n='uploads/'.bin2hex(random_bytes(12)).'.'.$ext;
  move_uploaded_file($f['tmp_name'],__DIR__.'/'.$n);
  return $n;
}
function pub($r){return ['id'=>(int)$r['id'],'username'=>$r['username'],'roll'=>$r['roll'],'name'=>$r['name'],'role'=>$r['role'],'pic'=>$r['pic'],'city'=>$r['city'],'pos'=>$r['pos'],'job'=>$r['job']];}
$a=$_GET['a']??'';
if($_SERVER['REQUEST_METHOD']==='POST'&&($_SERVER['HTTP_X_REQUESTED_WITH']??'')!=='cs2k23')out(['error'=>'Bad request'],403);
$u=null;
if(!empty($_SESSION['uid']))$u=q("SELECT * FROM users WHERE id=? AND status='approved'",[$_SESSION['uid']])->fetch()?:null;

if($a==='register'){
  $n=v('name',80);$un=strtolower(v('username',20));$r=strtoupper(v('roll',30));$pw=$_POST['password']??'';
  if(!$n||!preg_match('/^[a-z0-9_.]{3,20}$/',$un)||!$r||strlen($pw)<6)out(['error'=>'Fill every field. Username: 3-20 letters or numbers. Password: 6+ characters.'],400);
  if(empty($_FILES['pic'])||$_FILES['pic']['error']!==0)out(['error'=>'A profile picture is required.'],400);
  if(q("SELECT 1 FROM users WHERE username=?",[$un])->fetch())out(['error'=>'That username is taken.'],409);
  if(q("SELECT 1 FROM users WHERE roll=?",[$r])->fetch())out(['error'=>'This roll number is already registered.'],409);
  $pic=upload($_FILES['pic']);
  q("INSERT INTO users(username,roll,pass_hash,name,pic) VALUES(?,?,?,?,?)",[$un,$r,password_hash($pw,PASSWORD_DEFAULT),$n,$pic]);
  out(['ok'=>1]);
}
if($a==='login'){
  $r=q("SELECT * FROM users WHERE username=? AND roll=?",[strtolower(v('username')),strtoupper(v('roll'))])->fetch();
  if(!$r||!password_verify($_POST['password']??'',$r['pass_hash'])){sleep(1);out(['error'=>'Username, roll number or password is wrong.'],401);}
  if($r['status']==='pending')out(['pending'=>true]);
  if($r['status']==='rejected')out(['error'=>'Your request was declined. Contact the admin.'],403);
  session_regenerate_id(true);$_SESSION['uid']=$r['id'];out(['ok'=>1]);
}
if($a==='me')out(['user'=>$u?pub($u):null]);
if($a==='logout'){session_destroy();out(['ok'=>1]);}
if(!$u)out(['error'=>'Please log in again.'],401);
$adm=$u['role']==='admin';
function need_admin(){global $adm;if(!$adm)out(['error'=>'Admin only'],403);}
$pid=(int)($_POST['id']??0);

if($a==='feed'){
  $ps=q("SELECT p.id,p.user_id,p.kind,p.title,p.loc,p.body,p.img,p.is_ad,p.reports,UNIX_TIMESTAMP(p.created_at) ts,u.name,u.pic upic,u.job ujob,(SELECT COUNT(*) FROM likes l WHERE l.post_id=p.id) lc,EXISTS(SELECT 1 FROM likes l WHERE l.post_id=p.id AND l.user_id=?) liked FROM posts p JOIN users u ON u.id=p.user_id ORDER BY p.id DESC LIMIT 100",[$u['id']])->fetchAll();
  $ids=array_map('intval',array_column($ps,'id'));$cm=[];
  if($ids)foreach(q("SELECT c.post_id,c.body,u.name FROM comments c JOIN users u ON u.id=c.user_id WHERE c.post_id IN(".implode(',',$ids).") ORDER BY c.id")->fetchAll() as $c)$cm[$c['post_id']][]=['name'=>$c['name'],'t'=>$c['body']];
  foreach($ps as &$p){$p['cm']=$cm[$p['id']]??[];$p['lc']=(int)$p['lc'];$p['liked']=(bool)$p['liked'];$p['is_ad']=(bool)$p['is_ad'];if(!$adm)unset($p['reports']);}
  $s=fn($k)=>q("SELECT v FROM settings WHERE k=?",[$k])->fetchColumn();
  out(['posts'=>$ps,'motd'=>['t'=>$s('motd'),'d'=>(int)$s('motd_at')],'stats'=>[
    'members'=>(int)q("SELECT COUNT(*) FROM users WHERE status='approved' AND role='member'")->fetchColumn(),
    'posts'=>(int)q("SELECT COUNT(*) FROM posts")->fetchColumn(),
    'jobs'=>(int)q("SELECT COUNT(*) FROM posts WHERE kind='job'")->fetchColumn()]]);
}
if($a==='students')out(['students'=>q("SELECT id,name,pic,city,pos,job FROM users WHERE status='approved' AND role='member' ORDER BY name")->fetchAll()]);
if($a==='post'){
  $k=v('kind',4)==='job'?'job':'post';$b=v('body',2000);$t=v('title',120);$img=upload($_FILES['img']??null);
  if($k==='job'&&!$t)out(['error'=>'Add a job title'],400);
  if(!$b&&!$img)out(['error'=>'Write something or add a photo'],400);
  q("INSERT INTO posts(user_id,kind,title,loc,body,img,is_ad) VALUES(?,?,?,?,?,?,?)",[$u['id'],$k,$k==='job'?$t:'',$k==='job'?v('loc',120):'',$b,$img,($k==='job'&&v('ad',1)==='1')?1:0]);
  out(['ok'=>1]);
}
if($a==='like'){$d=q("DELETE FROM likes WHERE post_id=? AND user_id=?",[$pid,$u['id']]);if(!$d->rowCount())q("INSERT IGNORE INTO likes(post_id,user_id) VALUES(?,?)",[$pid,$u['id']]);out(['ok'=>1]);}
if($a==='comment'){$b=v('body',500);if(!$b)out(['error'=>'Write a comment'],400);q("INSERT INTO comments(post_id,user_id,body) VALUES(?,?,?)",[$pid,$u['id'],$b]);out(['ok'=>1]);}
if($a==='report'){q("UPDATE posts SET reports=reports+1 WHERE id=?",[$pid]);out(['ok'=>1]);}
if($a==='delete'){
  $p=q("SELECT * FROM posts WHERE id=?",[$pid])->fetch();if(!$p)out(['error'=>'Post not found'],404);
  if($p['user_id']!=$u['id']&&!$adm)out(['error'=>'Not allowed'],403);
  if($p['img']&&is_file(__DIR__.'/'.$p['img']))@unlink(__DIR__.'/'.$p['img']);
  q("DELETE FROM posts WHERE id=?",[$pid]);out(['ok'=>1]);
}
if($a==='profile'){
  q("UPDATE users SET city=?,pos=?,job=? WHERE id=?",[v('city',80),v('pos',80),v('job',120),$u['id']]);
  if($pic=upload($_FILES['pic']??null))q("UPDATE users SET pic=? WHERE id=?",[$pic,$u['id']]);
  out(['ok'=>1]);
}
if($a==='passwd'){
  if(!password_verify($_POST['old']??'',$u['pass_hash']))out(['error'=>'Current password is wrong'],400);
  if(strlen($_POST['new']??'')<6)out(['error'=>'New password needs 6+ characters'],400);
  q("UPDATE users SET pass_hash=? WHERE id=?",[password_hash($_POST['new'],PASSWORD_DEFAULT),$u['id']]);out(['ok'=>1]);
}
/* ---- admin ---- */
if($a==='admin'){need_admin();out([
  'pending'=>q("SELECT id,name,username,roll,pic FROM users WHERE status='pending' ORDER BY id")->fetchAll(),
  'members'=>q("SELECT id,name,username,roll,pic FROM users WHERE status='approved' AND role='member' ORDER BY name")->fetchAll()]);}
if($a==='decide'){need_admin();$s=v('status',10)==='approved'?'approved':'rejected';q("UPDATE users SET status=? WHERE id=? AND role='member'",[$s,$pid]);out(['ok'=>1]);}
if($a==='remove'){need_admin();q("DELETE FROM users WHERE id=? AND role='member'",[$pid]);out(['ok'=>1]);}
if($a==='motd'){need_admin();$t=v('text',300);if(!$t)out(['error'=>'Write a message'],400);
  q("REPLACE INTO settings(k,v) VALUES('motd',?),('motd_at',?)",[$t,time()]);out(['ok'=>1]);}
out(['error'=>'Unknown action'],404);
