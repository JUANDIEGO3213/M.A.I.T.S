<?php
if (PHP_SAPI !== 'cli') exit(1);  // solo desde la terminal
chdir((getenv('OST_ROOT') ?: dirname(__DIR__)) . '/scp'); define('APICALL',true); define('API_SESSION',true);
require '../main.inc.php'; require_once INCLUDE_DIR.'maits/reporte.php';
// uso: php perfil_reporte.php <usuario> <xlsx|pdf|csv> [todos|rango desde hasta]
$staff=Staff::lookup($argv[1]); $fmt=$argv[2]; $per=$argv[3]??'todos';
$cols=array('number','created','cdata__subject','user__name','user__emails__address','cdata__priority','dept_id','topic_id','source','status__id','lastupdate','est_duedate','sla_id','duedate','closed','isoverdue');
$e=array(); $o=MaitsReporte::leerOpciones(array('formato'=>$fmt,'fecha_reporte'=>1,'incluir'=>array('creados'=>1),'periodo'=>array('creados'=>$per),'desde'=>array('creados'=>$argv[4]??''),'hasta'=>array('creados'=>$argv[5]??''),'columnas'=>$cols),$e);
if($e) die(print_r($e,1));
$r=new MaitsReporte($staff,$o); $t=microtime(true);
$filas=array_fill(0,$r->contar("creados"),0); $t1=microtime(true);
printf("filas=%d  query+map=%.2fs  mem=%.0fMB\n", count($filas), $t1-$t, memory_get_peak_usage()/1e6);
$m=$fmt=="xlsx"?"excel":$fmt; $d=$r->$m(); printf("render %s=%.2fs size=%.2fMB peak=%.0fMB\n",$fmt,microtime(true)-$t1,strlen($d)/1e6,memory_get_peak_usage()/1e6);
if ($fmt=='csv') echo substr($d,0,300);
