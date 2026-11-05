import sys, time, io, zipfile, csv
from lib import *
COLS16=['number','created','cdata__subject','user__name','user__emails__address','cdata__priority','dept_id','topic_id','source','status__id','lastupdate','est_duedate','sla_id','duedate','closed','isoverdue']
def gen(s, fmt='xlsx', grupos=('creados',), periodo='todos', desde='', hasta='', cols=COLS16, fecha=True, extra=None):
    d=[('__CSRFToken__',s.csrf),('a','generar'),('formato',fmt)]
    if fecha: d.append(('fecha_reporte','1'))
    for g in grupos:
        d+= [(f'incluir[{g}]','1'),(f'periodo[{g}]',periodo),(f'desde[{g}]',desde),(f'hasta[{g}]',hasta)]
    d+= [('columnas[]',c) for c in cols]
    if extra: d+=extra
    t=time.time(); r=s.post(BASE+'/scp/reportes.php',data=d); return r, time.time()-t
res=[]
def check(name, cond, info=''):
    res.append((name,cond)); print(('PASS' if cond else 'FAIL'), name, info); sys.stdout.flush()
adm=staff('adminqa','AdminQA#2026x')
for fmt in ('xlsx','csv'):
    r,t=gen(adm,fmt)
    ct=r.headers.get('Content-Type','')
    check(f'{fmt} todos creados genera archivo', r.status_code==200 and 'html' not in ct, f'{t:.1f}s {len(r.content)/1e6:.2f}MB ct={ct}')
    if fmt=='xlsx' and r.status_code==200:
        z=zipfile.ZipFile(io.BytesIO(r.content)); check('xlsx zip valido', z.testzip() is None, str(len(z.namelist()))+' partes')
    if fmt=='csv': rows=list(csv.reader(io.StringIO(r.content.decode('utf-8-sig')))); check('csv filas', len(rows)>1000, f'{len(rows)} filas')
# Validaciones
r,_=gen(adm,'xlsx',grupos=()); check('sin grupos -> error', 'Marca al menos un grupo' in r.text)
r,_=gen(adm,'xlsx',cols=[]); check('sin columnas -> error', 'Elige al menos una columna' in r.text)
r,_=gen(adm,'xlsx',periodo='rango',desde='2026-13-40',hasta='2026-01-01'); check('fecha invalida -> error', 'Escribe la fecha' in r.text, r.headers.get('Content-Type'))
r,_=gen(adm,'xlsx',periodo='rango',desde='2026-05-01',hasta='2026-01-01'); check('rango invertido -> error', 'después de la fecha final' in r.text)
r,_=gen(adm,'xlsx',grupos=('abiertos',),periodo='igual'); check('igual sin creados -> error', 'mismo periodo' in r.text)
r,_=gen(adm,'xlsx',cols=['; DROP TABLE x','../../etc']); check('columnas inyectadas se descartan', 'Elige al menos una columna' in r.text)
r,_=gen(adm,'exe'); check('formato raro -> xlsx por defecto', 'spreadsheetml' in r.headers.get('Content-Type',''))
r,_=gen(adm,'csv',periodo='rango',desde='2026-10-01',hasta='2026-10-31'); rows=list(csv.reader(io.StringIO(r.content.decode('utf-8-sig')))); check('rango octubre csv', r.status_code==200, f'{len(rows)-2} tickets')
r,t=gen(adm,'xlsx',grupos=('creados','abiertos','cerrados')); check('3 grupos xlsx', r.status_code==200 and 'spreadsheetml' in r.headers.get('Content-Type',''), f'{t:.1f}s {len(r.content)/1e6:.2f}MB')
r,t=gen(adm,'pdf',grupos=('creados','abiertos','cerrados'),periodo='rango',desde='2026-10-15',hasta='2026-10-31'); check('3 grupos pdf (oct 15-31)', r.status_code==200 and r.content[:4]==b'%PDF', f'{t:.1f}s {len(r.content)/1e6:.2f}MB status={r.status_code}')
# CSRF
r=adm.post(BASE+'/scp/reportes.php',data={'a':'generar','formato':'csv','incluir[creados]':'1','columnas[]':'number'}); check('sin CSRF -> 400', r.status_code==400)
# Visibilidad: agente1 (Mantenimiento, acceso limitado)
a1=staff('agente1','Agente1#2026x')
r,_=gen(a1,'csv',cols=['number','dept_id'])
rows=list(csv.reader(io.StringIO(r.content.decode('utf-8-sig'))))
depts=set(x[1] for x in rows[2:])
check('agente1 solo ve su departamento', depts<= {'Maintenance'}, f'{len(rows)-2} tickets, depts={depts}')
# PDF grande -> mensaje claro
r,_=gen(adm,'pdf'); check('pdf con >2000 tickets -> aviso de limite', 'máximo por reporte' in r.text)
r,t=gen(adm,'pdf',periodo='rango',desde='2026-10-01',hasta='2026-10-31'); check('pdf 1431 tickets (octubre)', r.content[:4]==b'%PDF', f'{t:.1f}s {len(r.content)/1e6:.2f}MB')
# Inyeccion de formulas en CSV
r,_=gen(adm,'csv',periodo='rango',desde='2026-10-29',hasta='2026-10-29',cols=['number','cdata__subject','user__name'])
txt=r.content.decode('utf-8-sig'); bad=[l for l in txt.splitlines() if ',=' in l or ',"=' in l]
check('csv sin formulas ejecutables', not bad, str(bad[:2]))
# XLSX: el asunto malicioso queda como texto (inlineStr), nunca <f>
r,_=gen(adm,'xlsx',periodo='rango',desde='2026-10-29',hasta='2026-10-29')
z=zipfile.ZipFile(io.BytesIO(r.content)); sh=z.read('xl/worksheets/sheet1.xml').decode()
check('xlsx sin formulas', '<f>' not in sh and 'HYPERLINK' in sh)
# agente2 (Soporte)
a2=staff('agente2','Agente2#2026x'); r,_=gen(a2,'csv',cols=['number','dept_id'])
rows=list(csv.reader(io.StringIO(r.content.decode('utf-8-sig')))); check('agente2 solo Soporte', set(x[1] for x in rows[2:])<={'Support'}, f'{len(rows)-2}')
print('RESUMEN', sum(1 for _,c in res if c),'/',len(res))
