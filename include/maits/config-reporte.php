<?php
/*********************************************************************
    include/maits/config-reporte.php   [M.A.I.T.S]

    Texto de la FIRMA que aparece arriba a la derecha del reporte
    (Excel y PDF). Cambia solo lo que está entre comillas y guarda.

    - nombre:      Nombre de quien firma (déjalo vacío '' para que salga
                   "Nombre y firma del responsable" y se escriba a mano).
    - cargo:       Puesto de quien firma, por ejemplo
                   'Jefe del Departamento de Recursos Materiales y Servicios'.
    - institucion: Nombre del instituto (va en la última línea).

    Ejemplo:
        'nombre'      => 'Ing. Juan Pérez López',
        'cargo'       => 'Jefe del Centro de Cómputo',
**********************************************************************/
return array(
    'nombre'      => '',
    'cargo'       => '',
    'institucion' => 'Instituto Tecnológico de Saltillo',
);
