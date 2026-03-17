<?php
function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function db_connect(): mysqli
{
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = (int) (getenv('DB_PORT') ?: 3306);
    $database = getenv('DB_NAME') ?: 'lab2db';
    $user = getenv('DB_USER') ?: 'lab2user';
    $password = getenv('DB_PASSWORD') ?: 'lab2pass';

    $connection = @new mysqli($host, $user, $password, $database, $port);
    if ($connection->connect_error) {
        throw new RuntimeException('Falha ao conectar no MySQL: ' . $connection->connect_error);
    }

    $connection->set_charset('utf8mb4');
    return $connection;
}

function ensure_schema(mysqli $connection): void
{
    $connection->query(
        "CREATE TABLE IF NOT EXISTS game_scores (
            id INT AUTO_INCREMENT PRIMARY KEY,
            score INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function dashboard_payload(mysqli $connection): array
{
    $summary = ['best_score' => 0, 'total_games' => 0, 'avg_score' => 0];
    $summaryQuery = $connection->query('SELECT COALESCE(MAX(score),0) AS best_score, COUNT(*) AS total_games, COALESCE(ROUND(AVG(score),2),0) AS avg_score FROM game_scores');
    if ($summaryQuery) {
        $summary = $summaryQuery->fetch_assoc() ?: $summary;
    }

    $top = [];
    $topQuery = $connection->query('SELECT score, created_at FROM game_scores ORDER BY score DESC, created_at DESC LIMIT 5');
    if ($topQuery) {
        while ($row = $topQuery->fetch_assoc()) {
            $top[] = $row;
        }
    }

    return [
        'best_score' => (int) $summary['best_score'],
        'total_games' => (int) $summary['total_games'],
        'avg_score' => (float) $summary['avg_score'],
        'top_scores' => $top,
    ];
}

$api = $_GET['api'] ?? null;
if ($api) {
    try {
        $connection = db_connect();
        ensure_schema($connection);

        if ($api === 'save_score') {
            $input = json_decode(file_get_contents('php://input') ?: '{}', true);
            $score = isset($input['score']) ? (int) $input['score'] : 0;
            if ($score < 0) {
                $score = 0;
            }

            $statement = $connection->prepare('INSERT INTO game_scores (score) VALUES (?)');
            if (!$statement) {
                throw new RuntimeException('Falha ao preparar insert.');
            }
            $statement->bind_param('i', $score);
            $statement->execute();

            json_response([
                'ok' => true,
                'message' => 'Pontuação salva com sucesso.',
                'dashboard' => dashboard_payload($connection),
            ]);
        }

        if ($api === 'dashboard') {
            json_response([
                'ok' => true,
                'dashboard' => dashboard_payload($connection),
            ]);
        }

        json_response(['ok' => false, 'message' => 'Endpoint inválido.'], 404);
    } catch (Throwable $exception) {
        json_response(['ok' => false, 'message' => $exception->getMessage()], 500);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Docker Stacker - LAB 2</title>
    <style>
        body { margin: 0; background: #e0f7fa; display: flex; justify-content: center; align-items: center; height: 100vh; overflow: hidden; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        #game-container { position: relative; width: 100%; max-width: 500px; height: 100vh; background: linear-gradient(to bottom, #81d4fa, #29b6f6); overflow: hidden; box-shadow: 0 0 30px rgba(0,0,0,0.3); }
        #stack-container { position: absolute; width: 100%; height: 100%; top: 0; left: 0; }
        .container-block { position: absolute; height: 60px; z-index: 10; }
        .falling-piece { z-index: 9; }
        #whale { position: absolute; width: 180px; height: 100px; z-index: 5; }
        .waves { position: absolute; bottom: 0; left: 0; width: 100%; height: 150px; z-index: 20; pointer-events: none; }
        #ui { position: absolute; top: 20px; left: 20px; color: white; font-size: 28px; font-weight: bold; z-index: 100; text-shadow: 2px 2px 4px rgba(0,0,0,0.5); }
        #start-screen, #game-over { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(255,255,255,0.95); color: #0277bd; padding: 24px; border-radius: 15px; text-align: center; z-index: 200; box-shadow: 0 10px 30px rgba(0,0,0,0.3); width: 86%; max-width: 360px; }
        #game-over { display: none; }
        h2 { margin-top: 0; font-size: 30px; }
        p { font-size: 17px; margin: 14px 0; }
        .start-art { width: 100%; max-height: 140px; object-fit: contain; border-radius: 10px; border: 2px solid #b3e5fc; margin-bottom: 12px; background: #fff; }
        .start-icons { display: flex; justify-content: center; gap: 12px; margin: 8px 0 10px; }
        .start-icons svg { width: 34px; height: 34px; }
        button { background: #0288d1; color: #fff; border: none; padding: 12px 20px; font-size: 18px; cursor: pointer; border-radius: 8px; font-weight: bold; transition: background 0.2s; width: 100%; }
        button:hover { background: #0277bd; }
        .cloud { position: absolute; background: white; border-radius: 50px; opacity: 0.6; z-index: 1; }
        .cloud::before { content: ''; position: absolute; background: white; border-radius: 50%; width: 50px; height: 50px; top: -20px; left: 15px; }
        .cloud::after { content: ''; position: absolute; background: white; border-radius: 50%; width: 40px; height: 40px; top: -15px; right: 15px; }
        .floating-text { position: absolute; color: #FFD700; font-weight: bold; font-size: 24px; text-shadow: 1px 1px 2px #000; z-index: 100; transition: all 1s ease-out; pointer-events: none; }
        .dashboard { margin-top: 12px; text-align: left; background: #f4fbff; border: 1px solid #c3e7fa; border-radius: 10px; padding: 10px; }
        .dashboard h3 { margin: 0 0 8px 0; font-size: 15px; color: #025f92; }
        .dashboard-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px; margin-bottom: 10px; }
        .dashboard-card { background: white; border: 1px solid #d4eefc; border-radius: 8px; padding: 6px; text-align: center; }
        .dashboard-card strong { display: block; font-size: 15px; color: #03466d; }
        .dashboard-card span { font-size: 11px; color: #2b6f97; }
        .top-list { margin: 0; padding-left: 18px; color: #045581; font-size: 13px; }
        .dashboard-status { font-size: 12px; color: #3f7ea2; margin-top: 6px; }
    </style>
</head>
<body>
    <div id="game-container">
        <div class="cloud" style="width: 100px; height: 30px; top: 10%; left: 10%;"></div>
        <div class="cloud" style="width: 150px; height: 40px; top: 25%; left: 60%;"></div>
        <div class="cloud" style="width: 80px; height: 25px; top: 40%; left: 20%;"></div>

        <div id="stack-container">
            <div id="whale">
                <svg viewBox="0 0 200 120" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
                    <path d="M 140 45 Q 130 20 110 10" fill="none" stroke="#e0f7fa" stroke-width="4" stroke-linecap="round"/>
                    <path d="M 145 45 Q 160 15 180 10" fill="none" stroke="#e0f7fa" stroke-width="4" stroke-linecap="round"/>
                    <path d="M 142.5 45 L 142.5 5" fill="none" stroke="#e0f7fa" stroke-width="4" stroke-linecap="round"/>
                    <path d="M 25 50 C 10 30 -5 15 5 10 C 15 20 25 35 35 45 C 45 25 60 10 70 15 C 55 30 45 45 40 55 Z" fill="#0277bd"/>
                    <path d="M 30 45 L 160 45 C 185 45 195 60 195 80 C 195 105 150 115 100 115 C 50 115 15 95 15 70 C 15 55 20 45 30 45 Z" fill="#0db7ed"/>
                    <path d="M 15 70 C 15 95 50 115 100 115 C 150 115 195 105 195 80 C 195 95 150 105 100 105 C 50 105 20 85 15 70 Z" fill="#0288d1"/>
                    <circle cx="170" cy="65" r="6" fill="white"/>
                    <circle cx="172" cy="63" r="2.5" fill="#000"/>
                    <circle cx="173" cy="62" r="1" fill="white"/>
                    <path d="M 175 80 Q 182 85 188 78" fill="none" stroke="#0288d1" stroke-width="2" stroke-linecap="round"/>
                    <path d="M 110 85 C 130 85 140 100 120 105 C 100 110 90 90 110 85 Z" fill="#0277bd"/>
                </svg>
            </div>
        </div>

        <svg class="waves" viewBox="0 0 1440 320" preserveAspectRatio="none">
            <path fill="#0277bd" fill-opacity="1" d="M0,160L48,170.7C96,181,192,203,288,197.3C384,192,480,160,576,160C672,160,768,192,864,197.3C960,203,1056,181,1152,160C1248,139,1344,117,1392,106.7L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
        </svg>

        <div id="ui">Score: <span id="score">0</span></div>

        <div id="start-screen">
            <h2>Docker Stacker (LAB 2)</h2>
            <img class="start-art" src="docker-start.svg" alt="Ilustração docker" />
            <div class="start-icons" aria-hidden="true">
                <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
                    <rect x="8" y="18" width="48" height="28" rx="6" fill="#0db7ed"/>
                    <rect x="14" y="24" width="10" height="10" rx="2" fill="#fff"/>
                    <rect x="27" y="24" width="10" height="10" rx="2" fill="#fff"/>
                    <rect x="40" y="24" width="10" height="10" rx="2" fill="#fff"/>
                </svg>
                <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
                    <rect x="18" y="10" width="28" height="14" rx="3" fill="#0db7ed"/>
                    <rect x="18" y="26" width="28" height="14" rx="3" fill="#0aa4d6"/>
                    <rect x="18" y="42" width="28" height="14" rx="3" fill="#0288d1"/>
                </svg>
            </div>
            <p>Clique para soltar containers e empilhar na baleia!</p>
            <button onclick="startGame()">Start Game</button>
        </div>

        <div id="game-over">
            <h2>Game Over!</h2>
            <p>Score: <span id="final-score">0</span></p>
            <div class="dashboard" id="dashboard">
                <h3>Dashboard de Partidas</h3>
                <div class="dashboard-grid">
                    <div class="dashboard-card"><strong id="db-best">0</strong><span>Melhor</span></div>
                    <div class="dashboard-card"><strong id="db-total">0</strong><span>Partidas</span></div>
                    <div class="dashboard-card"><strong id="db-avg">0</strong><span>Média</span></div>
                </div>
                <ol class="top-list" id="db-top"></ol>
                <div class="dashboard-status" id="db-status">Aguardando dados do banco...</div>
            </div>
            <button onclick="startGame()">Try Again</button>
        </div>
    </div>

    <script>
        const gameContainer = document.getElementById('game-container');
        const stackContainer = document.getElementById('stack-container');
        const scoreEl = document.getElementById('score');
        const gameOverEl = document.getElementById('game-over');
        const startScreenEl = document.getElementById('start-screen');
        const finalScoreEl = document.getElementById('final-score');
        const whaleEl = document.getElementById('whale');

        const dbBestEl = document.getElementById('db-best');
        const dbTotalEl = document.getElementById('db-total');
        const dbAvgEl = document.getElementById('db-avg');
        const dbTopEl = document.getElementById('db-top');
        const dbStatusEl = document.getElementById('db-status');

        let blocks = [];
        let currentBlock = null;
        let state = 'idle';
        let score = 0;
        let cameraY = 0;
        let targetCameraY = 0;

        let gameWidth = gameContainer.clientWidth;
        let gameHeight = gameContainer.clientHeight;

        const initialBlockWidth = 120;
        const blockHeight = 60;
        const whaleWidth = 180;
        const whaleHeight = 100;

        const colors = ['#0db7ed', '#FF5722', '#4CAF50', '#FFC107', '#9C27B0', '#E91E63', '#3F51B5'];

        function createContainerHTML(color) {
            return `<div style="width: 100%; height: 100%; background-color: ${color}; border: 2px solid #000; box-sizing: border-box; background-image: linear-gradient(90deg, transparent 50%, rgba(0,0,0,0.1) 50%); background-size: 20px 100%;"></div>`;
        }

        function startGame() {
            const oldBlocks = document.querySelectorAll('.container-block, .floating-text, .falling-piece');
            oldBlocks.forEach(b => b.remove());

            gameWidth = gameContainer.clientWidth;
            gameHeight = gameContainer.clientHeight;

            blocks = [];
            score = 0;
            scoreEl.innerText = score;
            cameraY = 0;
            targetCameraY = 0;
            stackContainer.style.transform = `translateY(0px)`;

            startScreenEl.style.display = 'none';
            gameOverEl.style.display = 'none';

            const whaleX = gameWidth / 2 - whaleWidth / 2;
            const whaleY = gameHeight - whaleHeight - 60;

            whaleEl.style.left = `${whaleX}px`;
            whaleEl.style.top = `${whaleY}px`;

            blocks.push({
                x: whaleX,
                y: whaleY + 38,
                width: whaleWidth,
                height: whaleHeight,
                isWhale: true
            });

            spawnBlock(initialBlockWidth);
            state = 'swinging';
            lastTime = performance.now();
            requestAnimationFrame(gameLoop);
        }

        function spawnBlock(width) {
            const topBlock = blocks[blocks.length - 1];
            const startY = topBlock.y - 300;

            const el = document.createElement('div');
            el.className = 'container-block';
            el.style.width = `${width}px`;
            const color = colors[Math.floor(Math.random() * colors.length)];
            el.innerHTML = createContainerHTML(color);
            stackContainer.appendChild(el);

            currentBlock = {
                el,
                x: 0,
                y: startY,
                width,
                height: blockHeight,
                speed: 5 + score * 0.2,
                direction: 1,
                color
            };
        }

        function createFallingPiece(x, y, width, height, color, dir) {
            const el = document.createElement('div');
            el.className = 'container-block falling-piece';
            el.style.width = `${width}px`;
            el.style.height = `${height}px`;
            el.style.left = '0px';
            el.style.top = '0px';
            el.style.transform = `translate(${x}px, ${y}px)`;
            el.innerHTML = createContainerHTML(color);
            stackContainer.appendChild(el);

            let fallY = y;
            let fallX = x;
            let rot = 0;
            let fallSpeed = 0;

            function fallAnim() {
                fallSpeed += 0.5;
                fallY += fallSpeed;
                fallX += dir * 2;
                rot += dir * 5;
                el.style.transform = `translate(${fallX}px, ${fallY}px) rotate(${rot}deg)`;

                if (fallY < gameHeight + 200) {
                    requestAnimationFrame(fallAnim);
                } else {
                    el.remove();
                }
            }
            requestAnimationFrame(fallAnim);
        }

        function showFloatingText(text, x, y) {
            const el = document.createElement('div');
            el.className = 'floating-text';
            el.innerText = text;
            el.style.left = `${x}px`;
            el.style.top = `${y}px`;
            stackContainer.appendChild(el);

            void el.offsetWidth;
            el.style.top = `${y - 50}px`;
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 1000);
        }

        async function saveScoreAndLoadDashboard(finalScore) {
            dbStatusEl.textContent = 'Salvando no MySQL...';
            try {
                const response = await fetch('?api=save_score', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({score: finalScore})
                });

                if (!response.ok) {
                    throw new Error('Falha ao consultar API.');
                }

                const payload = await response.json();
                if (!payload.ok || !payload.dashboard) {
                    throw new Error(payload.message || 'Resposta inválida do servidor.');
                }

                renderDashboard(payload.dashboard);
                dbStatusEl.textContent = 'Dashboard atualizado com dados persistidos.';
            } catch (error) {
                dbStatusEl.textContent = `Dashboard indisponível: ${error.message}`;
                dbTopEl.innerHTML = '<li>Sem dados do banco no momento.</li>';
            }
        }

        function renderDashboard(dashboard) {
            dbBestEl.textContent = dashboard.best_score ?? 0;
            dbTotalEl.textContent = dashboard.total_games ?? 0;
            dbAvgEl.textContent = dashboard.avg_score ?? 0;

            const items = dashboard.top_scores || [];
            if (items.length === 0) {
                dbTopEl.innerHTML = '<li>Nenhuma pontuação registrada.</li>';
                return;
            }

            dbTopEl.innerHTML = items
                .map((item, index) => `<li>#${index + 1} - ${item.score} pts (${new Date(item.created_at).toLocaleString('pt-BR')})</li>`)
                .join('');
        }

        let lastTime = 0;

        function gameLoop(time) {
            if (state === 'idle') return;

            const dt = Math.min((time - lastTime) / 16.66, 3);
            lastTime = time;

            if (state === 'swinging') {
                currentBlock.x += currentBlock.speed * currentBlock.direction * dt;
                if (currentBlock.x <= 0) {
                    currentBlock.x = 0;
                    currentBlock.direction = 1;
                } else if (currentBlock.x + currentBlock.width >= gameWidth) {
                    currentBlock.x = gameWidth - currentBlock.width;
                    currentBlock.direction = -1;
                }
            } else if (state === 'dropping') {
                currentBlock.y += 20 * dt;

                const target = blocks[blocks.length - 1];

                if (currentBlock.y + currentBlock.height >= target.y) {
                    currentBlock.y = target.y - currentBlock.height;

                    let overlapStart = Math.max(currentBlock.x, target.x);
                    let overlapEnd = Math.min(currentBlock.x + currentBlock.width, target.x + target.width);
                    let overlapWidth = overlapEnd - overlapStart;

                    if (overlapWidth > 0) {
                        const currentCenter = currentBlock.x + currentBlock.width / 2;
                        const targetCenter = target.x + target.width / 2;
                        const distance = Math.abs(currentCenter - targetCenter);
                        const tolerance = 5;

                        if (distance < tolerance && currentBlock.width <= target.width + tolerance) {
                            currentBlock.x = targetCenter - currentBlock.width / 2;
                            if (!target.isWhale) {
                                currentBlock.x = target.x;
                                currentBlock.width = target.width;
                            }
                            score += 2;
                            showFloatingText('+2 Perfect!', currentBlock.x + currentBlock.width/2 - 40, currentBlock.y);
                        } else if (overlapWidth < currentBlock.width) {
                            const cutWidth = currentBlock.width - overlapWidth;
                            const cutX = currentBlock.x < target.x ? currentBlock.x : overlapEnd;

                            currentBlock.x = overlapStart;
                            currentBlock.width = overlapWidth;

                            createFallingPiece(cutX, currentBlock.y, cutWidth, blockHeight, currentBlock.color, currentBlock.x < target.x ? -1 : 1);
                            score += 1;
                            showFloatingText('+1', currentBlock.x + currentBlock.width/2 - 10, currentBlock.y);
                        } else {
                            score += 1;
                            showFloatingText('+1', currentBlock.x + currentBlock.width/2 - 10, currentBlock.y);
                        }

                        currentBlock.el.style.width = `${currentBlock.width}px`;
                        currentBlock.el.style.transform = `translate(${currentBlock.x}px, ${currentBlock.y}px)`;

                        scoreEl.innerText = score;
                        blocks.push(currentBlock);

                        targetCameraY = Math.max(0, gameHeight - 400 - currentBlock.y);
                        spawnBlock(currentBlock.width);
                        state = 'swinging';
                    } else {
                        state = 'gameover';
                        currentBlock.el.style.transition = 'transform 0.8s ease-in';
                        currentBlock.el.style.transform = `translate(${currentBlock.x}px, ${gameHeight + 200}px) rotate(${currentBlock.direction * 45}deg)`;

                        setTimeout(() => {
                            gameOverEl.style.display = 'block';
                            finalScoreEl.innerText = score;
                            saveScoreAndLoadDashboard(score);
                        }, 800);
                    }
                }
            }

            if (currentBlock && state !== 'gameover') {
                currentBlock.el.style.transform = `translate(${currentBlock.x}px, ${currentBlock.y}px)`;
            }

            if (cameraY !== targetCameraY) {
                cameraY += (targetCameraY - cameraY) * 0.1 * dt;
                if (Math.abs(targetCameraY - cameraY) < 1) cameraY = targetCameraY;
                stackContainer.style.transform = `translateY(${cameraY}px)`;
            }

            if (state !== 'idle') {
                requestAnimationFrame(gameLoop);
            }
        }

        function handleInput(event) {
            if (event.type === 'keydown' && event.code !== 'Space') return;
            if (state === 'swinging') {
                state = 'dropping';
                event.preventDefault();
            }
        }

        window.addEventListener('mousedown', handleInput);
        window.addEventListener('touchstart', handleInput, {passive: false});
        window.addEventListener('keydown', handleInput);
        window.addEventListener('resize', () => {
            gameWidth = gameContainer.clientWidth;
            gameHeight = gameContainer.clientHeight;
        });
    </script>
</body>
</html>
