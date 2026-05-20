<?php
/**
 * PONNU — admin/nail_positions.php
 * SUPER_ADMIN専用 ネイルチップ位置調整ツール
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
require_once APP_ROOT . '/views/dashboard_layout.php';

// SUPER_ADMIN 権限チェック
Auth::requireRole('SUPER_ADMIN');

$pageTitle = 'ネイル位置調整ツール';
dashboard_layout_start($pageTitle);
?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
    <div class="flex flex-col md:flex-row gap-8">
        
        <!-- 左側: プレビューエリア -->
        <div class="flex-1">
            <h2 class="text-lg font-bold mb-4">プレビュー (500x500px)</h2>
            <div id="canvas" class="relative bg-gray-100 border border-gray-300 mx-auto overflow-hidden select-none" 
                 style="width: 500px; height: 500px;">
                <!-- 背景の手 -->
                <img src="<?= e(ASSET_HAND_GOO) ?>" class="absolute inset-0 w-full h-full object-contain pointer-events-none opacity-50" alt="">
                
                <!-- 5つのマーカー (親指〜小指) -->
                <?php 
                $colors = ['bg-red-500', 'bg-blue-500', 'bg-green-500', 'bg-yellow-500', 'bg-purple-500'];
                $labels = ['親指', '人差', '中指', '薬指', '小指'];
                for ($i = 0; $i < 5; $i++): ?>
                    <div id="nail-<?= $i ?>" 
                         class="absolute cursor-move flex items-center justify-center text-[10px] text-white font-bold <?= $colors[$i] ?> opacity-60 rounded-sm"
                         style="width: 40px; height: 60px; top: <?= 100 + ($i * 20) ?>px; left: <?= 100 + ($i * 50) ?>px; transform: rotate(0deg);">
                        <?= $labels[$i] ?>
                        <!-- リサイズハンドル -->
                        <div class="resizer absolute bottom-0 right-0 w-3 h-3 bg-white border border-gray-400 cursor-se-resize"></div>
                    </div>
                <?php endfor; ?>
            </div>
            <p class="text-sm text-gray-500 mt-2">
                ドラッグで移動、右下ハンドルでリサイズ、右側スライダーで回転
            </p>
        </div>

        <!-- 右側: コントロールエリア -->
        <div class="w-full md:w-80 space-y-6">
            <h2 class="text-lg font-bold">コントロール</h2>
            
            <?php for ($i = 0; $i < 5; $i++): ?>
                <div class="p-3 border border-gray-100 rounded-lg bg-gray-50">
                    <p class="text-sm font-bold mb-2 flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full <?= $colors[$i] ?>"></span>
                        <?= $labels[$i] ?> (Slot <?= $i ?>)
                    </p>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-400 w-8">回転</span>
                        <input type="range" min="-180" max="180" value="0" class="flex-1 rotate-slider" data-index="<?= $i ?>">
                        <span class="text-xs font-mono w-8 rotate-val" data-index="<?= $i ?>">0°</span>
                    </div>
                </div>
            <?php endfor; ?>

            <div class="pt-4 border-t border-gray-200">
                <h3 class="text-sm font-bold mb-2">出力 (PHP配列形式)</h3>
                <textarea id="output-php" class="w-full h-40 text-xs font-mono p-2 border border-gray-300 rounded bg-gray-900 text-green-400" readonly></textarea>
                <button onclick="copyToClipboard('output-php')" class="mt-2 w-full py-2 bg-indigo-600 text-white rounded-lg text-sm font-bold hover:bg-indigo-700 transition">
                    コピーする
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const nails = [];
for (let i = 0; i < 5; i++) {
    const el = document.getElementById(`nail-${i}`);
    nails.push({
        el: el,
        top: parseFloat(el.style.top),
        left: parseFloat(el.style.left),
        width: parseFloat(el.style.width),
        height: parseFloat(el.style.height),
        rotate: 0
    });
}

const canvas = document.getElementById('canvas');
let activeIndex = null;
let mode = null; // 'move' or 'resize'
let startX, startY;
let startTop, startLeft, startWidth, startHeight;

document.querySelectorAll('[id^="nail-"]').forEach((el, index) => {
    el.addEventListener('mousedown', (e) => {
        activeIndex = index;
        if (e.target.classList.contains('resizer')) {
            mode = 'resize';
        } else {
            mode = 'move';
        }
        startX = e.clientX;
        startY = e.clientY;
        startTop = nails[index].top;
        startLeft = nails[index].left;
        startWidth = nails[index].width;
        startHeight = nails[index].height;
        
        e.preventDefault();
    });
});

window.addEventListener('mousemove', (e) => {
    if (activeIndex === null) return;
    
    const dx = e.clientX - startX;
    const dy = e.clientY - startY;
    const n = nails[activeIndex];
    
    if (mode === 'move') {
        n.top = startTop + dy;
        n.left = startLeft + dx;
    } else if (mode === 'resize') {
        n.width = Math.max(10, startWidth + dx);
        n.height = Math.max(10, startHeight + dy);
    }
    
    updateUI(activeIndex);
});

window.addEventListener('mouseup', () => {
    activeIndex = null;
    mode = null;
});

// 回転スライダー
document.querySelectorAll('.rotate-slider').forEach(slider => {
    slider.addEventListener('input', (e) => {
        const idx = e.target.dataset.index;
        const val = parseInt(e.target.value);
        nails[idx].rotate = val;
        document.querySelector(`.rotate-val[data-index="${idx}"]`).textContent = val + '°';
        updateUI(idx);
    });
});

function updateUI(idx) {
    const n = nails[idx];
    n.el.style.top = n.top + 'px';
    n.el.style.left = n.left + 'px';
    n.el.style.width = n.width + 'px';
    n.el.style.height = n.height + 'px';
    n.el.style.transform = `rotate(${n.rotate}deg)`;
    generateOutput();
}

function generateOutput() {
    let php = "$nailPositions = [\n";
    nails.forEach((n, i) => {
        const topP = (n.top / 500 * 100).toFixed(2);
        const leftP = (n.left / 500 * 100).toFixed(2);
        const wP = (n.width / 500 * 100).toFixed(2);
        const hP = (n.height / 500 * 100).toFixed(2);
        php += `    ${i} => ['top' => '${topP}%', 'left' => '${leftP}%', 'width' => '${wP}%', 'height' => '${hP}%', 'rotate' => '${n.rotate}deg'],\n`;
    });
    php += "];";
    document.getElementById('output-php').value = php;
}

async function copyToClipboard(id) {
    const text = document.getElementById(id).value;
    try {
        await navigator.clipboard.writeText(text);
        alert('コピーしました');
    } catch (err) {
        console.error('Failed to copy: ', err);
    }
}

// 初期出力
generateOutput();

</script>

<?php
dashboard_layout_end();
