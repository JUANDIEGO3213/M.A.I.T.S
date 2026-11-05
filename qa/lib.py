import re, requests, time
import os
BASE=os.environ.get('MAITS_URL','http://127.0.0.1:8081')
def tok(html):
    m=re.search(r'name="__CSRFToken__" value="([^"]+)"',html) or re.search(r'name="csrf_token" content="([^"]+)"',html)
    return m.group(1) if m else None
def staff(user,pw):
    s=requests.Session()
    r=s.get(BASE+'/scp/login.php'); t=tok(r.text)
    r=s.post(BASE+'/scp/login.php',data={'__CSRFToken__':t,'do':'scplogin','userid':user,'passwd':pw})
    assert 'logout.php' in r.text, ('login failed',user,r.status_code,r.text[:300])
    s.csrf=tok(r.text); return s
