import re
from lib import *
res=[]
def check(n,c,i=''): res.append(c); print('PASS' if c else 'FAIL', n, i)
a1=staff('agente1','Agente1#2026x')   # rol Limited Access, sin permiso
a2=staff('agente2','Agente2#2026x')   # rol All Access + permiso maits
r=a1.get(BASE+'/scp/equipos.php'); check('agente1 ve Mi area', r.status_code==200 and 'Maintenance' in r.text)
r=a1.get(BASE+'/scp/equipos.php?a=gestionar'); check('agente1 sin permiso no gestiona', 'No tienes permiso' in r.text)
r=a1.post(BASE+'/scp/equipos.php',data={'__CSRFToken__':a1.csrf,'a':'guardar','nombre':'Hack','miembros[]':['2']}); check('agente1 POST guardar bloqueado', 'No tienes permiso' in r.text)
r=a2.get(BASE+'/scp/equipos.php?a=gestionar'); check('agente2 gestiona', r.status_code==200 and 'Crear' in r.text)
r=a2.post(BASE+'/scp/equipos.php',data={'a':'guardar','nombre':'SinCSRF','miembros[]':['2']}); check('sin CSRF -> 400', r.status_code==400)
xss='<script>alert(1)</script><img src=x onerror=alert(2)>'
r=a2.post(BASE+'/scp/equipos.php',data={'__CSRFToken__':a2.csrf,'a':'guardar','nombre':'Redes'+xss,'lider':'3','miembros[]':['2'],'activo':'1','notas':xss+' nota'})
r=a2.get(BASE+'/scp/equipos.php?a=gestionar'); check('XSS en nombre escapado (gestionar)', '<script>alert(1)' not in r.text and 'onerror=alert' not in r.text.replace('&lt;','<') or '<img src=x onerror' not in r.text)
r=a2.get(BASE+'/scp/equipos.php'); check('XSS en nombre escapado (mi area)', '<script>alert(1)' not in r.text)
adm=staff('adminqa','AdminQA#2026x')
tid=re.search(r'equipos.php\?a=editar&amp;id=(\d+)',a2.get(BASE+'/scp/equipos.php?a=gestionar').text).group(1)
r=adm.get(BASE+f'/scp/teams.php?id={tid}'); check('XSS en notas no se ejecuta en Admin > Equipos', '<script>alert(1)' not in r.text and 'onerror=alert(2)' not in r.text)
r=a2.post(BASE+'/scp/equipos.php',data={'__CSRFToken__':a2.csrf,'a':'guardar','nombre':'','miembros[]':[]}); check('validacion nombre/miembros', 'Escribe el nombre' in r.text and 'al menos un miembro' in r.text)
r=a2.post(BASE+'/scp/equipos.php',data={'__CSRFToken__':a2.csrf,'a':'guardar','nombre':'Fantasma','lider':'9999','miembros[]':['8888']},allow_redirects=False)
import subprocess
out=subprocess.run(['mysql','maits','-N','-e',"select team_id,lead_id from ost_team where name='Fantasma'"],capture_output=True,text=True).stdout.strip()
check('agentes inexistentes rechazados (no crea equipo)', out=='', f'BD: {out!r} status={r.status_code}')
r=a2.get(BASE+'/scp/equipos.php?a=editar&id=99999'); check('editar id inexistente', 'Ese equipo no existe' in r.text)
r=a2.post(BASE+'/scp/equipos.php',data={'__CSRFToken__':a2.csrf,'a':'guardar','id':tid,'nombre':'Redes'+xss,'lider':'3','miembros[]':['3'],'activo':'1'})
r=a2.post(BASE+'/scp/equipos.php',data={'__CSRFToken__':a2.csrf,'a':'guardar','nombre':'Redes'+xss,'miembros[]':['3']}); check('nombre duplicado', 'Ya existe un equipo' in r.text)
print('RESUMEN',sum(res),'/',len(res))
