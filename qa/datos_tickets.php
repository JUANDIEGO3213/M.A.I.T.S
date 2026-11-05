<?php
if (PHP_SAPI !== 'cli') exit(1);  // solo desde la terminal
chdir((getenv('OST_ROOT') ?: dirname(__DIR__)) . '/api'); define('APICALL',true); define('API_SESSION',true);
require '../main.inc.php'; require_once INCLUDE_DIR.'api.tickets.php';
$n=(int)$argv[1]; $off=(int)$argv[2];
$topics=array(1,2,10,11); $depts=array(1,2,3);
$subj=array('Proyector no enciende','Sin internet en laboratorio','Impresora atascada','Cambio de contraseña','Fuga de agua en baño','Aire acondicionado','Equipo lento','Correo institucional');
mt_srand($off);
for($i=0;$i<$n;$i++){
  $k=$off+$i; $u=$k%300;
  $data=array('email'=>"alumno$u@saltillo.tecnm.mx",'name'=>"Alumno $u",'subject'=>$subj[$k%8]." #$k",
   'message'=>"Descripción del problema número $k en el aula ".($k%40),'topicId'=>$topics[$k%4],
   'deptId'=>$depts[$k%3],'priorityId'=>1+$k%4,'alert'=>false,'autorespond'=>false,'source'=>'Web');
  $errors=array(); $t=Ticket::create($data,$errors,'Web',false,false);
  if(!$t){echo json_encode($errors),"\n";continue;}
  if($k%5<2){ $t->setStatus(TicketStatus::lookup(3)); }
  $days=mt_rand(0,400);
  db_query("UPDATE ".TICKET_TABLE." SET created=DATE_SUB(NOW(), INTERVAL $days DAY)".($k%5<2?", closed=DATE_SUB(NOW(), INTERVAL ".max(0,$days-2)." DAY)":"")." WHERE ticket_id=".$t->getId());
}
echo "done $off\n";
