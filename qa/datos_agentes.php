<?php
if (PHP_SAPI !== 'cli') exit(1);  // solo desde la terminal
chdir((getenv('OST_ROOT') ?: dirname(__DIR__)) . '/api'); define('APICALL',true); define('API_SESSION',true);
require '../main.inc.php';
function mk($u,$f,$l,$dept,$role,$pw,$admin=0){
  if ($s=Staff::lookup($u)) return $s;
  $s = Staff::create();
  $errors=array();
  $vars=array('username'=>$u,'firstname'=>$f,'lastname'=>$l,'email'=>"$u@saltillo.tecnm.mx",
   'dept_id'=>$dept,'role_id'=>$role,'islocked'=>0,'isadmin'=>$admin,'isactive'=>1,
   'perms'=>array(), 'passwd1'=>$pw,'passwd2'=>$pw,'welcome_email'=>0,'timezone'=>'America/Monterrey');
  if(!$s->update($vars,$errors)) { var_dump($errors); return null;}
  $s->setPassword($pw, null); $s->change_passwd=0; $s->save();
  echo "created $u id=".$s->getId()."\n"; return $s;
}
mk('agente1','Ana','Mantenimiento',3,3,'Agente1#2026x');   // Maintenance, Limited Access
mk('agente2','Beto','Soporte',1,1,'Agente2#2026x');        // Support, All access
