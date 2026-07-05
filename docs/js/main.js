const pageFiles = new Set([
    'index.html',
    'study.html',
    'game.html',
    'reward.html',
    'gacha.html',
    'history.html',
    'settings.html'
]);

const homeMessages = [
    '今日も一緒に頑張ろうね♡',
    'ご褒美、楽しみにしててね…？',
    '今日はスロットで運試しする？',
    '交換ポイントは大切に使おうね。',
    'ゲームポイントもちゃんと貯めていこう。'
];

const reelSymbols = ['7', 'R', 'C', '♠', '♦', '★', '◆', '◎'];

const state = {
    bet: 20,
    bubbleIndex: 0,
    spinLock: false,
    bubbleTimer: null
};

function getPageFile() {
    const file = window.location.pathname.split('/').pop();
    return file || 'index.html';
}

function normalizeHref(href) {
    if (!href || href === '#') return '';
    try {
        const url = new URL(href, window.location.href);
        return url.pathname.split('/').pop() || 'index.html';
    } catch {
        return '';
    }
}

function animateGirlTap() {
    const girl = document.getElementById('mainGirl');
    if (!girl) return;
    girl.classList.remove('tap');
    void girl.offsetWidth;
    girl.classList.add('tap');
    window.setTimeout(() => girl.classList.remove('tap'), 380);
}

function setBubble(text) {
    const bubble = document.getElementById('speechBubble');
    if (!bubble) return;
    bubble.textContent = text;
}

function rotateBubble() {
    if (!homeMessages.length) return;
    state.bubbleIndex = (state.bubbleIndex + 1) % homeMessages.length;
    setBubble(homeMessages[state.bubbleIndex]);
}

function updateBet(delta) {
    state.bet = Math.max(10, Math.min(999, state.bet + delta));
    const target = document.getElementById('betValue');
    if (target) target.textContent = String(state.bet);
}

function spinReels() {
    if (state.spinLock) return;
    state.spinLock = true;

    const reels = Array.from(document.querySelectorAll('[data-reel]'));
    const btn = document.getElementById('spinButton');
    animateGirlTap();
    setBubble('スピン開始。結果を見てみよう。');

    reels.forEach((reel, index) => {
        reel.animate(
            [
                { transform: 'translateY(0) scale(1)', filter: 'blur(0px)' },
                { transform: 'translateY(-18px) scale(1.06)', filter: 'blur(1px)' },
                { transform: 'translateY(0) scale(1)', filter: 'blur(0px)' }
            ],
            {
                duration: 650 + index * 120,
                iterations: 1,
                easing: 'cubic-bezier(.2,.85,.2,1)'
            }
        );
    });

    window.setTimeout(() => {
        reels.forEach((reel, index) => {
            const symbol = reelSymbols[Math.floor(Math.random() * reelSymbols.length)];
            reel.textContent = symbol;
            reel.style.color = index === 1 ? 'var(--pink)' : 'var(--gold)';
        });

        const win = Math.random() > 0.55;
        setBubble(win ? 'キラッと揃った。今日はいい流れだね。' : '惜しい。でも次はもう少し狙えそう。');
        if (btn) btn.textContent = win ? 'JACKPOT' : 'SPIN';

        state.spinLock = false;
    }, 980);
}

function setActiveNavigation() {
    const current = getPageFile();
    document.querySelectorAll('.side-item, .bottom-nav-item').forEach((link) => {
        const target = normalizeHref(link.getAttribute('href'));
        const isActive = pageFiles.has(target) && target === current;
        link.classList.toggle('active', isActive);
        if (isActive) {
            link.setAttribute('aria-current', 'page');
        } else {
            link.removeAttribute('aria-current');
        }
    });
}

function setupHomePage() {
    const messagePool = document.getElementById('bubbleMessages');
    if (messagePool) {
        const messages = Array.from(messagePool.querySelectorAll('[data-message]'))
            .map((node) => node.textContent || '')
            .filter(Boolean);

        if (messages.length) {
            state.bubbleIndex = 0;
            setBubble(messages[0]);
            if (state.bubbleTimer) {
                window.clearInterval(state.bubbleTimer);
            }
            state.bubbleTimer = window.setInterval(() => {
                state.bubbleIndex = (state.bubbleIndex + 1) % messages.length;
                setBubble(messages[state.bubbleIndex]);
                animateGirlTap();
            }, 9000);
        }
    }

    document.querySelectorAll('[data-bet]').forEach((button) => {
        button.addEventListener('click', () => {
            updateBet(button.dataset.bet === '+' ? 10 : -10);
            animateGirlTap();
        });
    });

    const spinButton = document.getElementById('spinButton');
    if (spinButton) {
        spinButton.addEventListener('click', spinReels);
    }

    const mainGirl = document.getElementById('mainGirl');
    if (mainGirl) {
        mainGirl.addEventListener('click', () => {
            animateGirlTap();
            rotateBubble();
        });
    }
}

function setupPills() {
    document.querySelectorAll('.toggle-pill[data-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const isOn = button.classList.toggle('on');
            button.setAttribute('aria-pressed', String(isOn));
            button.textContent = isOn ? 'ON' : 'OFF';
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    setActiveNavigation();
    setupHomePage();
    setupPills();

    document.querySelectorAll('.bottom-nav-item, .side-item, .icon-pill').forEach((element) => {
        element.addEventListener('click', (event) => {
            const href = element.getAttribute('href');
            if (href === '#') {
                event.preventDefault();
            }
            if (getPageFile() === 'index.html') {
                animateGirlTap();
            }
        });
    });
});
