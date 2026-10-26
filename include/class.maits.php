<?php
/*********************************************************************
    class.maits.php   [M.A.I.T.S]

    Funciones propias de M.A.I.T.S para el panel de agentes:
      - Permiso de rol "Crear y editar equipos" (Administración ->
        Agentes -> Roles -> pestaña "M.A.I.T.S").
      - Datos para la pestaña "Equipos" (mi área / gestionar equipos).

    Se carga desde scp/staff.inc.php.
**********************************************************************/

class MaitsEquipos {

    const PERM_GESTIONAR = 'maits.equipos.gestionar';

    static protected $perms = array(
        self::PERM_GESTIONAR => array(
            'title' => 'Crear y editar equipos',
            'desc'  => 'Permite crear equipos, elegir a su líder y a sus miembros desde el panel de agentes (pestaña "Equipos"), sin entrar a Administración.',
        ),
    );

    static function getPermissions() {
        return self::$perms;
    }

    /** ¿El agente puede crear/editar equipos? (administradores siempre) */
    static function puedeGestionar($staff) {
        if (!$staff instanceof Staff)
            return false;
        // false = revisar el rol del departamento principal y de los
        // departamentos con acceso extendido
        return $staff->isAdmin()
            || $staff->hasPerm(self::PERM_GESTIONAR, false);
    }

    /** Departamentos del agente: principal + acceso extendido, con su rol */
    static function departamentosDe(Staff $staff) {
        $lista = array();
        if (($d = $staff->getDept()))
            $lista[$d->getId()] = array(
                'dept' => $d,
                'tipo' => 'Principal',
                'rol'  => $staff->getRole() ? $staff->getRole()->getName() : '',
            );
        foreach (StaffDeptAccess::objects()
                ->filter(array('staff_id' => $staff->getId()))
                ->select_related('dept', 'role') as $da) {
            if (!$da->dept || isset($lista[$da->dept_id]))
                continue;
            $lista[$da->dept_id] = array(
                'dept' => $da->dept,
                'tipo' => 'Acceso extendido',
                'rol'  => $da->role ? $da->role->getName() : '',
            );
        }
        return $lista;
    }

    /** Miembros de un departamento: [staff, tipo, rol] */
    static function miembrosDe(Dept $dept) {
        $lista = array();
        foreach (Staff::objects()
                ->filter(array('dept_id' => $dept->getId(), 'isactive' => 1))
                ->select_related('role')
                ->order_by('firstname', 'lastname') as $s) {
            $lista[$s->getId()] = array($s, 'Principal', $s->role ? $s->role->getName() : '');
        }
        foreach (StaffDeptAccess::objects()
                ->filter(array('dept_id' => $dept->getId(), 'staff__isactive' => 1))
                ->select_related('staff', 'role') as $da) {
            if (!$da->staff || isset($lista[$da->staff_id]))
                continue;
            $lista[$da->staff_id] = array($da->staff, 'Acceso extendido',
                $da->role ? $da->role->getName() : '');
        }
        return $lista;
    }

    /** Equipos donde el agente es miembro o líder */
    static function equiposDe(Staff $staff) {
        return Team::objects()
            ->filter(Q::any(array(
                'members__staff_id' => $staff->getId(),
                'lead_id' => $staff->getId(),
            )))
            ->distinct('team_id')
            ->order_by('name');
    }

    /** Agentes activos para elegir líder/miembros */
    static function agentesActivos() {
        return Staff::objects()
            ->filter(array('isactive' => 1))
            ->select_related('dept')
            ->order_by('dept__name', 'firstname', 'lastname');
    }

    /**
     * Crear o actualizar un equipo con los datos del formulario.
     * El líder se agrega automáticamente como miembro.
     */
    static function guardar(Team $team, $vars, &$errors) {
        $miembros = array_values(array_unique(array_filter(array_map('intval',
            (array) ($vars['miembros'] ?? array())))));
        $lider = (int) ($vars['lider'] ?? 0);
        if ($lider && !in_array($lider, $miembros))
            $miembros[] = $lider;

        $alertas = array();
        foreach ($miembros as $id)
            $alertas[$id] = 1;  // los miembros reciben avisos del equipo

        $datos = array(
            'id'        => $team->getId() ?: 0,
            'name'      => trim($vars['nombre'] ?? ''),
            'lead_id'   => $lider,
            'isenabled' => !empty($vars['activo']) ? 1 : 0,
            'notes'     => $vars['notas'] ?? '',
            'members'   => $miembros,
            'member_alerts' => $alertas,
        );
        if (!$datos['name'])
            $errors['nombre'] = 'Escribe el nombre del equipo.';
        if (!$miembros)
            $errors['miembros'] = 'Elige al menos un miembro (o un líder).';
        if ($errors)
            return false;

        $ok = $team->update($datos, $errors);
        if (isset($errors['name']))
            $errors['nombre'] = ($errors['name'] == __('Team name already exists'))
                ? 'Ya existe un equipo con ese nombre.' : $errors['name'];
        return $ok;
    }
}

RolePermission::register('M.A.I.T.S', MaitsEquipos::getPermissions());
